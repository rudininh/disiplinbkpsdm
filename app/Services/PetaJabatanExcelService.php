<?php

namespace App\Services;

use App\Models\SiasnAbsensiLocationEmployee;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class PetaJabatanExcelService
{
    private const DEFAULT_PATH = 'C:/Users/RUDINI/Downloads/Lampiran Perubahan kedua dan ketiga gabung Peta Jabatan 2026.xlsx';

    public function comparison(?array $tppPayload, ?int $selectedSheet = null, bool $includeSiasn = false, bool $includeTppPeople = true): array
    {
        $path = (string) config('services.tpp.peta_jabatan_excel_path', self::DEFAULT_PATH);

        if ($path === '' || ! is_file($path)) {
            return [
                'success' => false,
                'message' => 'File Excel Peta Jabatan belum ditemukan.',
                'path' => $path,
                'sheets' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $workbook = $this->readWorkbook($path);
        $real = $this->flattenTppPayload($tppPayload);
        $siasnFunctionalPools = $includeSiasn ? $this->siasnFunctionalPools() : [];
        $sheets = [];
        $summary = $this->emptySummary();

        foreach ($workbook['sheets'] as $index => $sheet) {
            $matchedSkpd = $this->matchSkpd($sheet, $real['skpd']);
            $pool = $matchedSkpd ? [
                'jobs' => $includeTppPeople ? ($real['by_skpd'][$matchedSkpd['skpd_id']] ?? []) : [],
                'categories' => $includeTppPeople ? ($real['by_skpd_category'][$matchedSkpd['skpd_id']] ?? []) : [],
                'siasn_jobs' => $siasnFunctionalPools[$matchedSkpd['skpd_id']]['jobs'] ?? [],
            ] : [
                'jobs' => $includeTppPeople ? $real['global'] : [],
                'categories' => [],
                'siasn_jobs' => [],
            ];
            $records = $this->buildComparisonRows($sheet['records'], $pool);
            $sheetSummary = [
                'records' => count($records),
                'needed' => array_sum(array_column($records, 'needed')),
                'filled' => array_sum(array_column($records, 'filled')),
                'vacant' => array_sum(array_column($records, 'vacant')),
                'real_extra' => array_sum(array_column($records, 'real_extra')),
            ];

            $sheets[] = [
                ...$sheet,
                'index' => $index,
                'matched_skpd' => $matchedSkpd,
                'comparison_records' => $records,
                'summary' => $sheetSummary,
                'grid' => $selectedSheet === null || $selectedSheet === $index ? $sheet['grid'] : null,
            ];

            $summary['sheets']++;
            $summary['records'] += $sheetSummary['records'];
            $summary['needed'] += $sheetSummary['needed'];
            $summary['filled'] += $sheetSummary['filled'];
            $summary['vacant'] += $sheetSummary['vacant'];
            $summary['real_extra'] += $sheetSummary['real_extra'];
        }

        return [
            'success' => true,
            'path' => $path,
            'sheets' => $sheets,
            'summary' => $summary,
        ];
    }

    private function readWorkbook(string $path): array
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $relationshipMap = [];

        foreach ($relationships->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            $relationshipMap[(string) $attributes['Id']] = 'xl/'.ltrim((string) $attributes['Target'], '/');
        }

        $workbook = $this->xml($zip, 'xl/workbook.xml');
        $sharedStrings = $this->sharedStrings($zip);
        $sheets = [];

        foreach ($workbook->sheets->sheet as $sheetNode) {
            $attributes = $sheetNode->attributes();
            $relationshipAttributes = $sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $file = $relationshipMap[(string) $relationshipAttributes['id']] ?? null;

            if (! $file) {
                continue;
            }

            $worksheet = $this->xml($zip, $file);
            $rows = $this->sheetRows($worksheet, $sharedStrings);
            $merges = $this->mergedCells($worksheet);
            $title = $this->sheetTitle($rows, (string) $attributes['name']);

            $sheets[] = [
                'name' => (string) $attributes['name'],
                'title' => $title,
                'dimension' => (string) ($worksheet->dimension->attributes()['ref'] ?? ''),
                'records' => $this->extractRecords($rows, $title),
                'grid' => $this->compactGrid($rows, $merges, (string) ($worksheet->dimension->attributes()['ref'] ?? ''), $worksheet),
            ];
        }

        $zip->close();

        return ['sheets' => $sheets];
    }

    private function buildComparisonRows(array $records, array $realPool): array
    {
        $jobPool = $realPool['jobs'] ?? $realPool;
        $categoryPool = $realPool['categories'] ?? [];
        $siasnJobPool = $realPool['siasn_jobs'] ?? [];
        $assignedPeople = [];

        return array_map(function (array $record) use ($jobPool, $categoryPool, $siasnJobPool, &$assignedPeople): array {
            $keys = $this->jobKeys($record['jabatan']);
            $categoryKey = $this->jobKey($record['category_match'] ?? $record['category'] ?? '');
            $categoryMatch = $this->matchingCategoryJobs($categoryPool, $categoryKey);
            $categoryJobs = $categoryMatch['jobs'];
            $categoryExists = $categoryMatch['matched'];
            $categoryMatchingKeys = $this->matchingJobPoolKeys($keys, $categoryJobs);
            $requiresCategoryMatch = (bool) ($record['category_is_position'] ?? false);
            $matchingKeys = $categoryMatchingKeys !== []
                ? $categoryMatchingKeys
                : ($categoryExists || $requiresCategoryMatch ? [] : $this->matchingJobPoolKeys($keys, $jobPool));
            $pool = $categoryMatchingKeys !== [] ? $categoryJobs : $jobPool;
            $people = [];

            foreach ($matchingKeys as $key) {
                $people = $this->mergePeople($people, $pool[$key] ?? []);
            }

            $people = $this->mergePeople($people, $this->siasnPeopleForRecord($record, $keys, $siasnJobPool));
            $people = array_values(array_filter($people, fn (mixed $person): bool => ! isset($assignedPeople[$this->personKey($person)])));
            $needed = max((int) ($record['kebutuhan'] ?? 0), (int) ($record['bezetting'] ?? 0), 1);
            $assigned = array_slice($people, 0, max($needed, count($people)));

            foreach ($assigned as $person) {
                $assignedPeople[$this->personKey($person)] = true;
            }
            $filled = count(array_filter($assigned));
            $vacant = max($needed - $filled, 0);
            $assignedNames = array_map(fn (mixed $person): string => $this->personName($person), array_values($assigned));
            $assignedDetails = array_map(fn (mixed $person): array => $this->personDetail($person), array_values($assigned));
            $assignedSlotNames = array_map(fn (mixed $person): string => $this->personDisplayName($person), array_values($assigned));

            return [
                ...$record,
                'bezetting_excel' => $record['bezetting'] ?? null,
                'kebutuhan_excel' => $record['kebutuhan'] ?? null,
                'selisih_excel' => $record['selisih'] ?? null,
                'bezetting' => $filled,
                'kebutuhan' => $needed,
                'selisih' => $filled - $needed,
                'needed' => $needed,
                'filled' => $filled,
                'vacant' => $vacant,
                'real_extra' => max(count($people) - count($assigned), 0),
                'people' => array_values($assignedNames),
                'people_details' => array_values($assignedDetails),
                'slots' => [
                    ...array_map(fn (string $name): array => ['status' => 'Terisi', 'nama' => $name], array_values($assignedSlotNames)),
                    ...array_fill(0, $vacant, ['status' => 'Kosong', 'nama' => null]),
                ],
            ];
        }, $records);
    }

    private function siasnPeopleForRecord(array $record, array $keys, array $siasnJobPool): array
    {
        $people = [];
        $unitKind = $this->educationUnitKindForFunctionalRecord($record['jabatan'] ?? '');

        foreach ($this->matchingJobPoolKeys($keys, $siasnJobPool) as $key) {
            foreach (($siasnJobPool[$key] ?? []) as $person) {
                if ($unitKind !== null && ($person['unit_kind'] ?? null) !== $unitKind) {
                    continue;
                }

                $people[] = [
                    ...$person,
                    'record_jabatan' => $record['jabatan'] ?? ($person['jabatan'] ?? null),
                    'record_kelas' => $record['kelas'] ?? null,
                ];
            }
        }

        return $this->mergePeople([], $people);
    }

    private function mergePeople(array ...$groups): array
    {
        $merged = [];
        $seen = [];

        foreach ($groups as $group) {
            foreach ($group as $person) {
                $key = $this->personKey($person);

                if ($key === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $merged[] = $person;
            }
        }

        return $merged;
    }

    private function personKey(mixed $person): string
    {
        if (is_array($person)) {
            $nip = preg_replace('/\D+/', '', (string) ($person['nip'] ?? '')) ?? '';

            if ($nip !== '') {
                return 'nip:'.$nip;
            }

            return implode('|', [
                (string) ($person['source'] ?? 'person'),
                $this->jobKey($person['name'] ?? ''),
                $this->jobKey($person['unit_kerja'] ?? ''),
                $this->jobKey($person['jabatan'] ?? ''),
            ]);
        }

        return 'name:'.$this->jobKey((string) $person);
    }

    private function personName(mixed $person): string
    {
        if (is_array($person)) {
            return trim((string) ($person['name'] ?? $person['nama'] ?? $person['display'] ?? ''));
        }

        return trim((string) $person);
    }

    private function personDisplayName(mixed $person): string
    {
        $name = $this->personName($person);

        if (! is_array($person)) {
            return $name;
        }

        $parts = array_values(array_filter([
            $name,
            preg_replace('/\D+/', '', (string) ($person['nip'] ?? '')) ?: null,
            $this->asnStatus($person['status_asn'] ?? null),
            isset($person['jabatan']) && (string) $person['jabatan'] !== '' ? 'Jabatan SIASN: '.$person['jabatan'] : null,
        ]));

        return implode(' | ', $parts);
    }

    private function personDetail(mixed $person): array
    {
        if (is_array($person)) {
            $name = $this->personName($person);

            return [
                ...$person,
                'name' => $name,
                'display' => $this->personDisplayName($person),
            ];
        }

        $name = $this->personName($person);

        return [
            'source' => 'tpp',
            'name' => $name,
            'display' => $name,
        ];
    }

    private function matchingCategoryJobs(array $categoryPool, string $categoryKey): array
    {
        $jobs = [];
        $matched = false;

        if ($categoryKey === '') {
            return ['matched' => false, 'jobs' => []];
        }

        foreach ($categoryPool as $candidateKey => $candidateJobs) {
            if (! $this->categoryContextMatches($categoryKey, (string) $candidateKey)) {
                continue;
            }

            if (! $this->jobKeyMatches($categoryKey, (string) $candidateKey)) {
                continue;
            }

            $matched = true;

            foreach ($candidateJobs as $jobKey => $people) {
                $jobs[$jobKey] = array_values(array_unique([
                    ...($jobs[$jobKey] ?? []),
                    ...$people,
                ]));
            }
        }

        return ['matched' => $matched, 'jobs' => $jobs];
    }

    private function categoryContextMatches(string $categoryKey, string $candidateKey): bool
    {
        if (! preg_match('/\bKELURAHAN\s+(.+)$/u', $categoryKey, $match)) {
            return true;
        }

        $tokens = $this->significantOrgTokens($match[1] ?? '');

        if ($tokens === []) {
            return true;
        }

        foreach ($tokens as $token) {
            if (! str_contains($candidateKey, $token)) {
                return false;
            }
        }

        return true;
    }

    private function matchingJobPoolKeys(array $keys, array $pool): array
    {
        $matches = [];

        foreach ($keys as $key) {
            foreach (array_keys($pool) as $candidateKey) {
                if ($this->jobKeyMatches($key, (string) $candidateKey)) {
                    $matches[] = (string) $candidateKey;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    private function extractRecords(array $rows, ?string $sheetTitle = null): array
    {
        $headers = $this->tableHeaders($rows);
        $records = [];

        foreach ($headers as $header) {
            $blankRows = 0;
            $maxRow = max(array_keys($rows) ?: [0]);
            $category = $this->categoryForHeader($rows, $header);

            for ($row = $header['row'] + 1; $row <= $maxRow; $row++) {
                $jabatan = $this->normalizeText($rows[$row][$header['jabatan_col']] ?? '');
                $kelas = $this->numericValue($rows[$row][$header['kelas_col']] ?? null);
                $bezetting = $this->numericValue($rows[$row][$header['bezetting_col']] ?? null);
                $kebutuhan = $this->numericValue($rows[$row][$header['kebutuhan_col']] ?? null);

                if ($jabatan === '') {
                    $blankRows++;

                    if ($blankRows >= 6) {
                        break;
                    }

                    continue;
                }

                $blankRows = 0;

                if ($this->isIgnoredRecordLabel($jabatan) || ($kelas === null && $bezetting === null && $kebutuhan === null)) {
                    continue;
                }

                $categoryMatch = $this->categoryMatchForRecord($jabatan, $category['name']);

                if ((bool) ($category['is_position'] ?? false)) {
                    $categoryMatch = $this->withSheetUnitContext($categoryMatch, $sheetTitle);
                }

                $records[] = [
                    'row' => $row,
                    'col' => $header['jabatan_col'],
                    'jabatan' => $jabatan,
                    'kelas' => $kelas,
                    'bezetting' => $bezetting,
                    'kebutuhan' => $kebutuhan,
                    'selisih' => $this->numericValue($rows[$row][$header['selisih_col']] ?? null),
                    'category' => $category['name'],
                    'category_match' => $categoryMatch,
                    'category_kelas' => $category['kelas'],
                    'category_is_position' => $category['is_position'],
                ];
            }
        }

        return $this->uniqueRecords($records);
    }

    private function tableHeaders(array $rows): array
    {
        $headers = [];

        foreach ($rows as $rowNumber => $cells) {
            foreach ($cells as $column => $value) {
                if (! $this->isJabatanHeader($value)) {
                    continue;
                }

                $kelasColumn = $this->findHeaderColumn($cells, $column + 1, ['kelas', 'kls']);
                $bezettingColumn = $this->findHeaderColumn($cells, $column + 1, ['b']);
                $kebutuhanColumn = $this->findHeaderColumn($cells, $column + 1, ['k']);
                $selisihColumn = $this->findHeaderColumn($cells, $column + 1, ['+/-', '-/+']);

                if (! $kelasColumn || ! $bezettingColumn || ! $kebutuhanColumn) {
                    continue;
                }

                $headers[] = [
                    'row' => $rowNumber,
                    'jabatan_col' => $column,
                    'kelas_col' => $kelasColumn,
                    'bezetting_col' => $bezettingColumn,
                    'kebutuhan_col' => $kebutuhanColumn,
                    'selisih_col' => $selisihColumn,
                ];
            }
        }

        return $headers;
    }

    private function categoryForHeader(array $rows, array $header): array
    {
        $startRow = max((int) $header['row'] - 12, 1);
        $startColumn = max((int) $header['jabatan_col'] - 16, 1);
        $endColumn = (int) ($header['selisih_col'] ?? $header['kebutuhan_col']) + 6;
        $fallback = null;
        $fallbackRow = null;
        $fallbackColumn = null;

        for ($row = (int) $header['row'] - 1; $row >= $startRow; $row--) {
            for ($column = $startColumn; $column <= $endColumn; $column++) {
                $text = $this->normalizeText($rows[$row][$column] ?? '');

                if ($text === '') {
                    continue;
                }

                if (str_contains(strtoupper($text), 'JABATAN FUNGSIONAL')) {
                    return [
                        'name' => 'Jabatan Fungsional',
                        'kelas' => null,
                        'is_position' => false,
                    ];
                }

                if ($this->classValue($text) !== null) {
                    continue;
                }

                if ($this->isIgnoredCategoryLabel($text)) {
                    continue;
                }

                if ($fallback === null) {
                    $fallback = $text;
                    $fallbackRow = $row;
                    $fallbackColumn = $column;
                }
            }
        }

        $kelas = $fallbackRow !== null
            ? $this->nearbyClassForCategory($rows, $fallbackRow, $fallbackColumn ?? $startColumn, $startColumn, $endColumn)
            : null;

        return [
            'name' => $fallback,
            'kelas' => $kelas,
            'is_position' => $fallback !== null && ($kelas !== null || $this->looksLikePositionLabel($fallback)),
        ];
    }

    private function nearbyClassForCategory(array $rows, int $categoryRow, int $categoryColumn, int $startColumn, int $endColumn): ?int
    {
        foreach ([0, 1, -1, 2, -2, 3, -3] as $offset) {
            $row = $categoryRow + $offset;

            if (! isset($rows[$row])) {
                continue;
            }

            $columns = array_values(array_unique([
                $categoryColumn,
                ...range($startColumn, $endColumn),
            ]));

            foreach ($columns as $column) {
                $kelas = $this->classValue($rows[$row][$column] ?? null);

                if ($kelas !== null) {
                    return $kelas;
                }
            }
        }

        return null;
    }

    private function classValue(mixed $value): ?int
    {
        $text = $this->normalizeText((string) $value);

        if (preg_match('/\bKELAS\s*\(?\s*(\d+)/i', $text, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    private function looksLikePositionLabel(string $value): bool
    {
        $upper = strtoupper($this->normalizeText($value));

        return preg_match('/^(KEPALA|SEKRETARIS|CAMAT|LURAH|DIREKTUR)\b/u', $upper) === 1
            || str_contains($upper, ' BIDANG ')
            || str_contains($upper, ' BAGIAN ')
            || str_contains($upper, ' SUB BAGIAN ');
    }

    private function categoryMatchForRecord(string $jabatan, ?string $category): ?string
    {
        $category = $this->normalizeText($category);

        if (strcasecmp($category, 'Jabatan Fungsional') !== 0) {
            return $category !== '' ? $category : null;
        }

        preg_match_all('/\(([^)]*)\)/u', $jabatan, $matches);

        foreach ($matches[1] ?? [] as $hint) {
            $hint = $this->normalizeText($hint);

            if ($hint === '' || ! $this->looksLikeUnitHint($hint)) {
                continue;
            }

            return $this->positionLabelFromUnitHint($hint);
        }

        return $category;
    }

    private function looksLikeUnitHint(string $value): bool
    {
        $upper = strtoupper($this->normalizeText($value));

        return preg_match('/^(BAGIAN|BIDANG|SUB\s*BAGIAN|SUBBAGIAN|SEKSI|SEKRETARIAT|UPT|UPTD|UNIT)\b/u', $upper) === 1;
    }

    private function positionLabelFromUnitHint(string $value): string
    {
        $value = preg_replace('/^SUBBAGIAN\b/iu', 'Sub Bagian', $value) ?: $value;

        if ($this->looksLikePositionLabel($value)) {
            return $value;
        }

        return 'Kepala '.$value;
    }

    private function withSheetUnitContext(?string $category, ?string $sheetTitle): ?string
    {
        $category = $this->normalizeText($category);
        $unit = $this->unitContextFromSheetTitle($sheetTitle);

        if ($category === '' || $unit === '') {
            return $category !== '' ? $category : null;
        }

        if (str_contains($this->jobKey($category), $this->jobKey($unit))) {
            return $category;
        }

        return trim($category.' '.$unit);
    }

    private function unitContextFromSheetTitle(?string $sheetTitle): string
    {
        $title = $this->normalizeText($sheetTitle);

        if (preg_match('/\b(KELURAHAN\s+.+)$/iu', $title, $match)) {
            return $this->normalizeText($match[1]);
        }

        return '';
    }

    private function isIgnoredCategoryLabel(string $value): bool
    {
        $key = $this->headerKey($value);

        return $this->isIgnoredRecordLabel($value)
            || in_array($key, ['org', 'kelas', 'kls', 'b', 'k', '+/-', '-/+', 'beban kerja', 'kekuatan pegawai'], true)
            || str_starts_with($key, 'peta jabatan')
            || str_starts_with($key, 'jumlah pegawai')
            || preg_match('/\bkelas\s*\(?\s*\d+/i', $value)
            || ! preg_match('/[a-z]/i', $value)
            || preg_match('/^-?\d+$/', $value);
    }

    private function findHeaderColumn(array $cells, int $startColumn, array $labels): ?int
    {
        for ($column = $startColumn; $column <= $startColumn + 24; $column++) {
            $value = $this->headerKey($cells[$column] ?? '');

            if (in_array($value, $labels, true)) {
                return $column;
            }
        }

        return null;
    }

    private function compactGrid(array $rows, array $merges, string $dimension, \SimpleXMLElement $worksheet): array
    {
        $chartCells = [];
        $maxColumn = 0;
        $maxRow = 0;

        foreach ($rows as $rowNumber => $rowCells) {
            $maxRow = max($maxRow, $rowNumber);

            foreach ($rowCells as $column => $value) {
                $text = $this->normalizeText($value);

                if ($text === '') {
                    continue;
                }

                $merge = $merges[$rowNumber.':'.$column] ?? ['cols' => 1, 'rows' => 1];
                $chartCells[] = [
                    'row' => $rowNumber,
                    'col' => $column,
                    'row_span' => $merge['rows'],
                    'col_span' => $merge['cols'],
                    'text' => $text,
                    'kind' => $this->cellKind($text),
                ];
                $maxColumn = max($maxColumn, $column);
            }
        }

        [$dimensionMaxColumn, $dimensionMaxRow] = $this->dimensionBounds($dimension);
        $maxColumn = max($maxColumn, $dimensionMaxColumn);
        $maxRow = max($maxRow, $dimensionMaxRow);
        $maxColumn = min($maxColumn, 140);
        $maxRow = min($maxRow, 180);
        $layout = $this->sheetPixelLayout($worksheet, $maxColumn, $maxRow);
        $chartCells = array_map(function (array $cell) use ($layout): array {
            $column = (int) $cell['col'];
            $row = (int) $cell['row'];
            $colSpan = max((int) ($cell['col_span'] ?? 1), 1);
            $rowSpan = max((int) ($cell['row_span'] ?? 1), 1);

            return [
                ...$cell,
                'left_px' => $this->columnLeft($layout, $column),
                'top_px' => $this->rowTop($layout, $row),
                'width_px' => max($this->columnLeft($layout, $column + $colSpan) - $this->columnLeft($layout, $column), 1),
                'height_px' => max($this->rowTop($layout, $row + $rowSpan) - $this->rowTop($layout, $row), 1),
            ];
        }, array_values(array_filter($chartCells, fn (array $cell): bool => $cell['col'] <= 140 && $cell['row'] <= 180)));

        return [
            'max_col' => $maxColumn,
            'max_row' => $maxRow,
            'width_px' => $this->columnLeft($layout, $maxColumn + 1),
            'height_px' => $this->rowTop($layout, $maxRow + 1),
            'columns_px' => $layout['columns'],
            'rows_px' => $layout['rows'],
            'cells' => $chartCells,
            'connectors' => $this->orgChartConnectors($chartCells),
        ];
    }

    private function sheetPixelLayout(\SimpleXMLElement $worksheet, int $maxColumn, int $maxRow): array
    {
        $defaultColumnWidth = (float) ($worksheet->sheetFormatPr->attributes()['defaultColWidth'] ?? 8.43);
        $defaultRowHeight = (float) ($worksheet->sheetFormatPr->attributes()['defaultRowHeight'] ?? 15);
        $columns = array_fill(1, $maxColumn, $this->excelColumnWidthToPixels($defaultColumnWidth));
        $rows = array_fill(1, $maxRow, $this->excelRowHeightToPixels($defaultRowHeight));

        foreach ($worksheet->cols->col ?? [] as $column) {
            $attributes = $column->attributes();
            $min = max((int) ($attributes['min'] ?? 1), 1);
            $max = min((int) ($attributes['max'] ?? $min), $maxColumn);
            $hidden = ((string) ($attributes['hidden'] ?? '0')) === '1';
            $width = $hidden ? 0 : $this->excelColumnWidthToPixels((float) ($attributes['width'] ?? $defaultColumnWidth));

            for ($index = $min; $index <= $max; $index++) {
                $columns[$index] = $width;
            }
        }

        foreach ($worksheet->sheetData->row as $row) {
            $attributes = $row->attributes();
            $rowNumber = (int) ($attributes['r'] ?? 0);
            if ($rowNumber < 1 || $rowNumber > $maxRow) {
                continue;
            }

            if (isset($attributes['ht'])) {
                $rows[$rowNumber] = $this->excelRowHeightToPixels((float) $attributes['ht']);
            }
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'column_offsets' => $this->pixelOffsets($columns),
            'row_offsets' => $this->pixelOffsets($rows),
        ];
    }

    private function excelColumnWidthToPixels(float $width): int
    {
        return max((int) round(($width * 7) + 5), 12);
    }

    private function excelRowHeightToPixels(float $height): int
    {
        return max((int) round($height * 96 / 72), 18);
    }

    private function pixelOffsets(array $sizes): array
    {
        $offsets = [1 => 0];
        $running = 0;

        foreach ($sizes as $index => $size) {
            $running += (int) $size;
            $offsets[(int) $index + 1] = $running;
        }

        return $offsets;
    }

    private function columnLeft(array $layout, int $column): int
    {
        return (int) ($layout['column_offsets'][$column] ?? end($layout['column_offsets']));
    }

    private function rowTop(array $layout, int $row): int
    {
        return (int) ($layout['row_offsets'][$row] ?? end($layout['row_offsets']));
    }

    private function orgChartConnectors(array $cells): array
    {
        $nodes = $this->orgChartNodes($cells);
        $tables = $this->orgChartTables($cells);
        $obstacles = array_map(fn (array $table): array => [
            'key' => $table['key'],
            'left' => (float) $table['left_x'] - 8,
            'right' => (float) $table['right_x'] + 8,
            'top' => (float) $table['top_y'] - 8,
            'bottom' => (float) $table['bottom_y'] + 8,
        ], $tables);
        $rows = collect($nodes)
            ->groupBy('row')
            ->sortKeys()
            ->values();
        $connectors = [];

        for ($index = 1; $index < $rows->count(); $index++) {
            $parents = $rows[$index - 1]->values();
            $children = $rows[$index]->values();

            if ($parents->isEmpty() || $children->isEmpty()) {
                continue;
            }

            $childrenByParent = [];
            foreach ($children as $child) {
                $parentIndex = $parents
                    ->keys()
                    ->sortBy(fn ($parentIndex): float => abs((float) $parents[$parentIndex]['center_x'] - (float) $child['center_x']))
                    ->first();

                if ($parentIndex === null) {
                    continue;
                }

                $childrenByParent[(int) $parentIndex][] = $child;
            }

            foreach ($childrenByParent as $parentIndex => $assignedChildren) {
                $parent = $parents[$parentIndex] ?? null;
                if ($parent === null || $assignedChildren === []) {
                    continue;
                }

                foreach ($assignedChildren as $child) {
                    if ((float) $child['top_y'] <= (float) $parent['bottom_y']) {
                        continue;
                    }

                    $connectors[] = $this->routedConnector(
                        (float) $parent['center_x'],
                        (float) $parent['bottom_y'],
                        (float) $child['center_x'],
                        max((float) $child['top_y'] - 7, (float) $parent['bottom_y'] + 8),
                        $obstacles
                    );
                }
            }
        }

        foreach ($tables as $table) {
            $parent = $this->nearestTableParent($nodes, $table);

            if ($parent === null) {
                continue;
            }

            $tableObstacles = array_values(array_filter(
                $obstacles,
                fn (array $obstacle): bool => ($obstacle['key'] ?? null) !== ($table['key'] ?? null)
            ));

            $connectors[] = $this->routedConnector(
                (float) $parent['center_x'],
                (float) $parent['bottom_y'],
                (float) $table['center_x'],
                max((float) $table['top_y'] - 34, (float) $parent['bottom_y'] + 8),
                $tableObstacles
            );
        }

        return $connectors;
    }

    private function orgChartNodes(array $cells): array
    {
        $classCells = collect($cells)
            ->where('kind', 'class')
            ->values();
        $nodes = [];

        foreach ($cells as $cell) {
            $kind = $cell['kind'] ?? 'text';
            if (! in_array($kind, ['position', 'text'], true)) {
                continue;
            }

            $left = (float) ($cell['left_px'] ?? 0);
            $right = $left + (float) ($cell['width_px'] ?? 0);
            $top = (float) ($cell['top_px'] ?? 0);
            $height = (float) ($cell['height_px'] ?? 0);
            $classCell = $classCells
                ->filter(function (array $classCell) use ($cell, $left, $right): bool {
                    $classLeft = (float) ($classCell['left_px'] ?? 0);
                    $classRight = $classLeft + (float) ($classCell['width_px'] ?? 0);

                    return (int) $classCell['row'] > (int) $cell['row']
                        && (int) $classCell['row'] <= (int) $cell['row'] + 4
                        && $classLeft < $right
                        && $classRight > $left;
                })
                ->sortBy('row')
                ->first();

            if (! is_array($classCell) && $kind !== 'position') {
                continue;
            }

            $bottom = is_array($classCell)
                ? (float) ($classCell['top_px'] ?? 0) + (float) ($classCell['height_px'] ?? 0)
                : $top + $height;

            $nodes[] = [
                'row' => (int) $cell['row'],
                'col' => (int) $cell['col'],
                'left_x' => $left,
                'right_x' => $right,
                'center_x' => $left + ((float) ($cell['width_px'] ?? 0) / 2),
                'top_y' => $top,
                'bottom_y' => $bottom,
                'text' => (string) ($cell['text'] ?? ''),
            ];
        }

        return $nodes;
    }

    private function orgChartTables(array $cells): array
    {
        $cellsByRow = collect($cells)->groupBy('row');
        $tables = [];

        foreach ($cells as $cell) {
            if (($cell['kind'] ?? '') !== 'header' || ! $this->isJabatanHeader($cell['text'] ?? '')) {
                continue;
            }

            $row = (int) $cell['row'];
            $startColumn = (int) $cell['col'];
            $rowCells = $cellsByRow->get($row, collect())->sortBy('col')->values();
            $headerCells = [$cell];

            foreach ($rowCells as $rowCell) {
                $column = (int) ($rowCell['col'] ?? 0);

                if ($column <= $startColumn || ($rowCell['kind'] ?? '') !== 'header') {
                    continue;
                }

                if ($this->isJabatanHeader($rowCell['text'] ?? '')) {
                    break;
                }

                if ($column > $startColumn + 8) {
                    break;
                }

                $key = $this->headerKey($rowCell['text'] ?? '');

                if (in_array($key, ['kelas', 'kls', 'b', 'k', '+/-', '-/+'], true)) {
                    $headerCells[] = $rowCell;
                }
            }

            $headerCells = collect($headerCells);
            $headerKeys = $headerCells
                ->map(fn (array $rowCell): string => $this->headerKey($rowCell['text'] ?? ''))
                ->all();

            if (! in_array('kelas', $headerKeys, true) && ! in_array('kls', $headerKeys, true)) {
                continue;
            }

            if (! in_array('b', $headerKeys, true) || ! in_array('k', $headerKeys, true)) {
                continue;
            }

            $endColumn = (int) $headerCells->max('col');
            $left = (float) $cell['left_px'];
            $right = (float) $headerCells
                ->map(fn (array $headerCell): float => (float) ($headerCell['left_px'] ?? 0) + (float) ($headerCell['width_px'] ?? 0))
                ->max();
            $topRow = $row;
            $topY = (float) ($cell['top_px'] ?? 0);
            $captionCell = collect($cells)
                ->filter(function (array $candidate) use ($row, $left, $right): bool {
                    if (($candidate['kind'] ?? '') !== 'header') {
                        return false;
                    }

                    $candidateRow = (int) ($candidate['row'] ?? 0);
                    if ($candidateRow < $row - 2 || $candidateRow >= $row) {
                        return false;
                    }

                    $candidateLeft = (float) ($candidate['left_px'] ?? 0);
                    $candidateRight = $candidateLeft + (float) ($candidate['width_px'] ?? 0);
                    $text = $this->headerKey($candidate['text'] ?? '');

                    return $this->isJabatanHeader($candidate['text'] ?? '')
                        && $text !== 'jabatan'
                        && $candidateLeft < $right
                        && $candidateRight > $left;
                })
                ->sortByDesc('row')
                ->first();

            if (is_array($captionCell)) {
                $topRow = (int) $captionCell['row'];
                $topY = (float) ($captionCell['top_px'] ?? $topY);
                $left = min($left, (float) ($captionCell['left_px'] ?? $left));
                $right = max($right, (float) ($captionCell['left_px'] ?? 0) + (float) ($captionCell['width_px'] ?? 0));
            }

            $nextPositionRow = collect($cells)
                ->filter(function (array $candidate) use ($row, $left, $right): bool {
                    if (($candidate['kind'] ?? '') !== 'position' || (int) ($candidate['row'] ?? 0) <= $row) {
                        return false;
                    }

                    $candidateLeft = (float) ($candidate['left_px'] ?? 0);
                    $candidateRight = $candidateLeft + (float) ($candidate['width_px'] ?? 0);

                    return $candidateLeft < $right && $candidateRight > $left;
                })
                ->min('row');
            $scanLimit = $nextPositionRow ? (int) $nextPositionRow - 1 : $row + 42;
            $endRow = $row;
            $blankRows = 0;

            for ($scanRow = $row; $scanRow <= $scanLimit; $scanRow++) {
                $hasContent = $cellsByRow
                    ->get($scanRow, collect())
                    ->contains(fn (array $candidate): bool => (int) ($candidate['col'] ?? 0) >= $startColumn
                        && (int) ($candidate['col'] ?? 0) <= $endColumn
                        && $this->normalizeText($candidate['text'] ?? '') !== '');

                if ($hasContent) {
                    $endRow = $scanRow;
                    $blankRows = 0;

                    continue;
                }

                if ($scanRow > $row) {
                    $blankRows++;
                }

                if ($blankRows >= 4) {
                    break;
                }
            }

            $tableCells = collect($cells)
                ->filter(fn (array $candidate): bool => (int) ($candidate['row'] ?? 0) >= $topRow
                    && (int) ($candidate['row'] ?? 0) <= $endRow
                    && (int) ($candidate['col'] ?? 0) >= $startColumn
                    && (int) ($candidate['col'] ?? 0) <= $endColumn)
                ->values();

            $bottom = (float) $tableCells
                ->map(fn (array $tableCell): float => (float) ($tableCell['top_px'] ?? 0) + (float) ($tableCell['height_px'] ?? 0))
                ->max();

            if ($bottom <= (float) ($cell['top_px'] ?? 0)) {
                continue;
            }

            $tables[] = [
                'key' => $row.':'.$startColumn,
                'row' => $row,
                'col' => $startColumn,
                'left_x' => $left,
                'right_x' => $right,
                'center_x' => $left + (($right - $left) / 2),
                'top_y' => $topY,
                'bottom_y' => $bottom,
            ];
        }

        return $tables;
    }

    private function nearestTableParent(array $nodes, array $table): ?array
    {
        $best = null;
        $bestScore = null;
        $tableWidth = max((float) $table['right_x'] - (float) $table['left_x'], 1);

        foreach ($nodes as $node) {
            $gap = (float) $table['top_y'] - (float) $node['bottom_y'];

            if ($gap < 8 || $gap > 680) {
                continue;
            }

            $nodeWidth = max((float) $node['right_x'] - (float) $node['left_x'], 1);
            $dx = abs((float) $node['center_x'] - (float) $table['center_x']);
            $maxDx = max($tableWidth * 1.15, $nodeWidth * 1.25, 260);

            if ($dx > $maxDx) {
                continue;
            }

            $score = $gap + ($dx * 0.35);

            if ($bestScore === null || $score < $bestScore) {
                $best = $node;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function routedConnector(float $startX, float $startY, float $endX, float $endY, array $obstacles): array
    {
        $points = $this->connectorPoints($startX, $startY, $endX, $endY, $obstacles);
        $roundedPoints = array_map(fn (array $point): array => [
            'x' => round((float) $point[0], 3),
            'y' => round((float) $point[1], 3),
        ], $points);
        $busY = (float) ($roundedPoints[1]['y'] ?? (($startY + $endY) / 2));

        return [
            'parent_x' => round($startX, 3),
            'parent_y' => round($startY, 3),
            'bus_y' => round($busY, 3),
            'min_x' => round(min($startX, $endX), 3),
            'max_x' => round(max($startX, $endX), 3),
            'children' => [[
                'x' => round($endX, 3),
                'y' => round($endY, 3),
            ]],
            'paths' => [[
                'points' => $roundedPoints,
            ]],
        ];
    }

    private function connectorPoints(float $startX, float $startY, float $endX, float $endY, array $obstacles): array
    {
        if ($endY <= $startY + 8) {
            return $this->compactConnectorPoints([[$startX, $startY], [$endX, $endY]]);
        }

        $busY = $this->safeConnectorBusY($startX, $startY, $endX, $endY, $obstacles);
        $simple = $this->compactConnectorPoints([[$startX, $startY], [$startX, $busY], [$endX, $busY], [$endX, $endY]]);

        if ($this->pathAvoidsObstacles($simple, $obstacles)) {
            return $simple;
        }

        $blockers = $this->connectorBlockers($startX, $startY, $endX, $endY, $obstacles);

        if ($blockers !== []) {
            $topY = min($endY - 10, max($startY + 12, min(array_column($blockers, 'top')) - 12));
            $bottomY = min($endY - 10, max($topY + 18, max(array_column($blockers, 'bottom')) + 12));
            $leftX = min(array_column($blockers, 'left')) - 12;
            $rightX = max(array_column($blockers, 'right')) + 12;
            $candidates = [
                $this->compactConnectorPoints([[$startX, $startY], [$startX, $topY], [$leftX, $topY], [$leftX, $bottomY], [$endX, $bottomY], [$endX, $endY]]),
                $this->compactConnectorPoints([[$startX, $startY], [$startX, $topY], [$rightX, $topY], [$rightX, $bottomY], [$endX, $bottomY], [$endX, $endY]]),
            ];
            usort($candidates, function (array $left, array $right) use ($obstacles): int {
                $leftHits = $this->pathObstacleHits($left, $obstacles);
                $rightHits = $this->pathObstacleHits($right, $obstacles);

                if ($leftHits !== $rightHits) {
                    return $leftHits <=> $rightHits;
                }

                return $this->pathLength($left) <=> $this->pathLength($right);
            });

            return $candidates[0];
        }

        return $simple;
    }

    private function safeConnectorBusY(float $startX, float $startY, float $endX, float $endY, array $obstacles): float
    {
        $minY = $startY + 10;
        $maxY = $endY - 10;

        if ($maxY <= $minY) {
            return ($startY + $endY) / 2;
        }

        $candidates = [($startY + $endY) / 2, $minY, $maxY];

        foreach ($obstacles as $obstacle) {
            $candidates[] = (float) $obstacle['top'] - 12;
            $candidates[] = (float) $obstacle['bottom'] + 12;
        }

        $candidates = array_values(array_unique(array_map(
            fn (float $candidate): float => min(max($candidate, $minY), $maxY),
            $candidates
        )));
        usort($candidates, fn (float $left, float $right): int => abs($left - (($startY + $endY) / 2)) <=> abs($right - (($startY + $endY) / 2)));

        foreach ($candidates as $candidate) {
            $path = $this->compactConnectorPoints([[$startX, $startY], [$startX, $candidate], [$endX, $candidate], [$endX, $endY]]);

            if ($this->pathAvoidsObstacles($path, $obstacles)) {
                return $candidate;
            }
        }

        return ($startY + $endY) / 2;
    }

    private function connectorBlockers(float $startX, float $startY, float $endX, float $endY, array $obstacles): array
    {
        $left = min($startX, $endX);
        $right = max($startX, $endX);

        return array_values(array_filter($obstacles, fn (array $obstacle): bool => (float) $obstacle['bottom'] > $startY
            && (float) $obstacle['top'] < $endY
            && (float) $obstacle['right'] >= $left
            && (float) $obstacle['left'] <= $right));
    }

    private function pathAvoidsObstacles(array $points, array $obstacles): bool
    {
        return $this->pathObstacleHits($points, $obstacles) === 0;
    }

    private function pathObstacleHits(array $points, array $obstacles): int
    {
        $hits = 0;

        for ($index = 1; $index < count($points); $index++) {
            foreach ($obstacles as $obstacle) {
                if ($this->segmentIntersectsObstacle($points[$index - 1], $points[$index], $obstacle)) {
                    $hits++;
                }
            }
        }

        return $hits;
    }

    private function segmentIntersectsObstacle(array $start, array $end, array $obstacle): bool
    {
        $x1 = (float) $start[0];
        $y1 = (float) $start[1];
        $x2 = (float) $end[0];
        $y2 = (float) $end[1];

        if (abs($x1 - $x2) < 0.001) {
            return $x1 > (float) $obstacle['left']
                && $x1 < (float) $obstacle['right']
                && max($y1, $y2) > (float) $obstacle['top']
                && min($y1, $y2) < (float) $obstacle['bottom'];
        }

        if (abs($y1 - $y2) < 0.001) {
            return $y1 > (float) $obstacle['top']
                && $y1 < (float) $obstacle['bottom']
                && max($x1, $x2) > (float) $obstacle['left']
                && min($x1, $x2) < (float) $obstacle['right'];
        }

        return false;
    }

    private function compactConnectorPoints(array $points): array
    {
        $compacted = [];

        foreach ($points as $point) {
            $last = $compacted[count($compacted) - 1] ?? null;

            if ($last !== null && abs((float) $last[0] - (float) $point[0]) < 0.001 && abs((float) $last[1] - (float) $point[1]) < 0.001) {
                continue;
            }

            $compacted[] = $point;
        }

        return $compacted;
    }

    private function pathLength(array $points): float
    {
        $length = 0;

        for ($index = 1; $index < count($points); $index++) {
            $length += abs((float) $points[$index][0] - (float) $points[$index - 1][0]);
            $length += abs((float) $points[$index][1] - (float) $points[$index - 1][1]);
        }

        return $length;
    }

    private function flattenTppPayload(?array $payload): array
    {
        $global = [];
        $bySkpd = [];
        $bySkpdCategory = [];
        $skpdRows = [];

        foreach (($payload['skpd'] ?? []) as $skpd) {
            $skpdId = (int) ($skpd['skpd_id'] ?? 0);
            $treeRows = $this->flattenTree($skpd['tree'] ?? []);
            $unitKeys = [];

            foreach ($treeRows as $row) {
                $unitKey = $this->orgKey($row['jabatan'] ?? '');

                if ($unitKey !== '') {
                    $unitKeys[] = $unitKey;
                }
            }

            $skpdRows[] = [
                'skpd_id' => $skpdId,
                'nama' => (string) ($skpd['nama'] ?? ''),
                'kode' => (string) ($skpd['kode'] ?? ''),
                'unit_keys' => array_values(array_unique($unitKeys)),
            ];

            foreach ($treeRows as $row) {
                $key = $this->jobKey($row['jabatan'] ?? '');
                $pegawai = trim((string) ($row['pegawai'] ?? ''));

                if ($key === '' || $this->isVacantPegawai($pegawai, $row['jabatan'] ?? '')) {
                    continue;
                }

                $categoryKey = $this->jobKey($row['_parent_jabatan'] ?? '');
                $bySkpd[$skpdId][$key][] = $pegawai;
                if ($categoryKey !== '') {
                    $bySkpdCategory[$skpdId][$categoryKey][$key][] = $pegawai;
                }
                $global[$key][] = $pegawai;
            }
        }

        return [
            'global' => $global,
            'by_skpd' => $bySkpd,
            'by_skpd_category' => $bySkpdCategory,
            'skpd' => $skpdRows,
        ];
    }

    private function flattenTree(array $nodes, ?string $parentJabatan = null): array
    {
        $rows = [];

        foreach ($nodes as $node) {
            $node['_parent_jabatan'] = $parentJabatan;
            $rows[] = $node;
            $rows = array_merge($rows, $this->flattenTree($node['children'] ?? [], $node['jabatan'] ?? null));
        }

        return $rows;
    }

    private function siasnFunctionalPools(): array
    {
        if (! Schema::hasTable('siasn_absensi_location_employees')) {
            return [];
        }

        $pools = [];
        $employees = SiasnAbsensiLocationEmployee::query()
            ->where('match_status', 'excel_siasn_import')
            ->whereNotNull('siasn_pns_profile_id')
            ->whereNotNull('siasn_jabatan')
            ->with('siasnProfile')
            ->orderBy('skpd_id')
            ->orderBy('lokasi_nama')
            ->orderBy('nama')
            ->get();

        foreach ($employees as $employee) {
            if (! $this->isActiveSiasnEmployee($employee)) {
                continue;
            }

            $skpdId = (int) ($employee->skpd_id ?? 0);
            $jabatan = $this->normalizeText((string) ($employee->siasn_jabatan ?? ''));
            $nama = $this->normalizeText((string) ($employee->nama ?? ''));
            $unitKerja = $this->normalizeText((string) (($employee->siasn_unit_organisasi ?? '') ?: ($employee->lokasi_nama ?? '')));
            $key = $this->jobKey($jabatan);

            if ($skpdId <= 0 || $key === '' || $nama === '' || $unitKerja === '') {
                continue;
            }

            $person = [
                'source' => 'siasn',
                'name' => $nama,
                'nip' => preg_replace('/\D+/', '', (string) ($employee->nip ?? '')) ?: null,
                'jabatan' => $jabatan,
                'unit_kerja' => $unitKerja,
                'lokasi_nama' => $this->normalizeText((string) ($employee->lokasi_nama ?? '')),
                'unit_kind' => $this->educationUnitKind($unitKerja),
                'status_asn' => $this->asnStatus($employee->siasnProfile?->jenis_asn ?? data_get($employee->row_data, 'siasn_status')),
                'match_status' => $employee->match_status,
            ];

            $pools[$skpdId]['jobs'][$key][] = $person;
        }

        foreach ($pools as $skpdId => $pool) {
            foreach (($pool['jobs'] ?? []) as $key => $people) {
                $pools[$skpdId]['jobs'][$key] = $this->mergePeople([], $people);
            }
        }

        return $pools;
    }

    private function isActiveSiasnEmployee(SiasnAbsensiLocationEmployee $employee): bool
    {
        $raw = $employee->siasnProfile?->raw_data ?: [];
        $status = $this->rawText(
            data_get($raw, 'merged.kedudukan_hukum_nama')
                ?? data_get($raw, 'summary.kedudukan_hukum_nama')
                ?? data_get($employee->row_data, 'siasn_status')
        );

        if ($status === '') {
            return true;
        }

        $upper = strtoupper($status);

        if (in_array($upper, ['PNS', 'CPNS', 'PPPK', 'PPPK PARUH WAKTU'], true)) {
            return true;
        }

        return str_contains($upper, 'AKTIF')
            && ! str_contains($upper, 'TIDAK AKTIF')
            && ! str_contains($upper, 'PENSIUN')
            && ! str_contains($upper, 'BERHENTI')
            && ! str_contains($upper, 'MENINGGAL');
    }

    private function rawText(mixed $value): string
    {
        if (is_array($value)) {
            return $this->normalizeText((string) ($value['nama'] ?? reset($value) ?: ''));
        }

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $this->rawText($decoded);
            }
        }

        return $this->normalizeText((string) $value);
    }

    private function educationUnitKindForFunctionalRecord(?string $jabatan): ?string
    {
        $upper = strtoupper($this->normalizeText($jabatan));

        if (! str_contains($upper, 'GURU')) {
            return null;
        }

        if (str_contains($upper, 'TAMAN KANAK') || preg_match('/\bTK\b/u', $upper)) {
            return 'TK';
        }

        if (str_contains($upper, 'SEKOLAH DASAR') || preg_match('/\bSD\b/u', $upper) || preg_match('/\bMI\b/u', $upper)) {
            return 'SD';
        }

        if (str_contains($upper, 'SEKOLAH MENENGAH PERTAMA') || preg_match('/\bSMP\b/u', $upper) || preg_match('/\bMTS\b/u', $upper)) {
            return 'SMP';
        }

        return null;
    }

    private function educationUnitKind(?string $unitKerja): string
    {
        $upper = strtoupper($this->normalizeText($unitKerja));

        return match (true) {
            str_starts_with($upper, 'TK') || str_contains($upper, 'TAMAN KANAK') => 'TK',
            str_starts_with($upper, 'SD') || str_starts_with($upper, 'MI') || str_contains($upper, 'SEKOLAH DASAR') => 'SD',
            str_starts_with($upper, 'SMP') || str_starts_with($upper, 'MTS') || str_contains($upper, 'SEKOLAH MENENGAH PERTAMA') => 'SMP',
            default => 'LAIN',
        };
    }

    private function asnStatus(mixed $value): ?string
    {
        $status = strtoupper(trim((string) $value));

        return in_array($status, ['PNS', 'PPPK'], true) ? $status : null;
    }

    private function isVacantPegawai(string $pegawai, ?string $jabatan): bool
    {
        if ($pegawai === '' || $pegawai === '-') {
            return true;
        }

        $key = $this->jobKey($pegawai);

        return in_array($key, ['', 'KOSONG', 'LOWONG'], true)
            || $key === $this->jobKey($jabatan);
    }

    private function matchSkpd(array $sheet, array $skpdRows): ?array
    {
        $haystack = $this->orgKey(($sheet['title'] ?? '').' '.($sheet['name'] ?? ''));

        if (str_contains($haystack, 'PUSKESMAS') || str_contains($haystack, 'RUMAH SAKIT') || str_contains($haystack, 'KESEHATAN')) {
            return $this->findSkpdContaining($skpdRows, 'KESEHATAN');
        }

        if (str_contains($haystack, 'SET DPRD')) {
            return $this->findSkpdContaining($skpdRows, 'DPRD');
        }

        if (preg_match('/\bKELURAHAN\s+(.+)$/u', $haystack, $match)) {
            $unitTokens = $this->significantOrgTokens($match[1] ?? '');

            foreach ($skpdRows as $skpd) {
                foreach (($skpd['unit_keys'] ?? []) as $unitKey) {
                    $normalizedUnitKey = $this->orgKey((string) $unitKey);

                    if (! str_contains($normalizedUnitKey, 'KELURAHAN') && ! str_contains($normalizedUnitKey, 'LURAH')) {
                        continue;
                    }

                    $matchesAllTokens = $unitTokens !== [];

                    foreach ($unitTokens as $token) {
                        if (! str_contains($normalizedUnitKey, $token)) {
                            $matchesAllTokens = false;
                            break;
                        }
                    }

                    if ($matchesAllTokens) {
                        return $skpd;
                    }
                }
            }
        }

        $best = null;
        $bestScore = 0;
        $bestUnit = null;
        $bestUnitScore = 0;

        foreach ($skpdRows as $skpd) {
            $orgKey = $this->orgKey($skpd['nama'] ?? '');
            $tokens = array_filter(explode(' ', $orgKey), fn (string $token): bool => strlen($token) > 3);
            $score = 0;

            if ($orgKey !== '' && str_contains($haystack, $orgKey)) {
                return $skpd;
            }

            foreach ($tokens as $token) {
                if (str_contains($haystack, $token)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $best = $skpd;
                $bestScore = $score;
            }

            foreach (($skpd['unit_keys'] ?? []) as $unitKey) {
                $unitScore = 0;

                foreach ($this->significantOrgTokens((string) $unitKey) as $token) {
                    if (str_contains($haystack, $token)) {
                        $unitScore++;
                    }
                }

                if ($unitScore > $bestUnitScore) {
                    $bestUnit = $skpd;
                    $bestUnitScore = $unitScore;
                }
            }
        }

        if ($bestUnitScore >= 2) {
            return $bestUnit;
        }

        return $bestScore >= 1 ? $best : null;
    }

    private function significantOrgTokens(string $value): array
    {
        $stopwords = [
            'DAN' => true,
            'PADA' => true,
            'KOTA' => true,
            'BANJARMASIN' => true,
            'KEPALA' => true,
            'SEKSI' => true,
            'KASI' => true,
            'SUB' => true,
            'BAGIAN' => true,
            'BIDANG' => true,
            'KELURAHAN' => true,
            'LURAH' => true,
            'KECAMATAN' => true,
            'CAMAT' => true,
            'SEKRETARIS' => true,
        ];

        return array_values(array_unique(array_filter(
            explode(' ', $this->orgKey($value)),
            fn (string $token): bool => strlen($token) > 3 && ! isset($stopwords[$token])
        )));
    }

    private function findSkpdContaining(array $skpdRows, string $needle): ?array
    {
        foreach ($skpdRows as $skpd) {
            if (str_contains($this->orgKey($skpd['nama'] ?? ''), $needle)) {
                return $skpd;
            }
        }

        return null;
    }

    private function sheetRows(\SimpleXMLElement $worksheet, array $sharedStrings): array
    {
        $rows = [];

        foreach ($worksheet->sheetData->row as $row) {
            $rowNumber = (int) $row->attributes()['r'];

            foreach ($row->c as $cell) {
                $reference = (string) $cell->attributes()['r'];
                $rows[$rowNumber][$this->columnIndex($reference)] = $this->cellValue($cell, $sharedStrings);
            }
        }

        ksort($rows);

        return $rows;
    }

    private function mergedCells(\SimpleXMLElement $worksheet): array
    {
        $merges = [];

        foreach ($worksheet->mergeCells->mergeCell ?? [] as $mergeCell) {
            $reference = (string) $mergeCell->attributes()['ref'];

            if (! str_contains($reference, ':')) {
                continue;
            }

            [$start, $end] = explode(':', $reference, 2);
            [$startColumn, $startRow] = $this->cellPosition($start);
            [$endColumn, $endRow] = $this->cellPosition($end);

            $merges[$startRow.':'.$startColumn] = [
                'cols' => max($endColumn - $startColumn + 1, 1),
                'rows' => max($endRow - $startRow + 1, 1),
            ];
        }

        return $merges;
    }

    private function dimensionBounds(string $dimension): array
    {
        if (! str_contains($dimension, ':')) {
            return [0, 0];
        }

        [, $end] = explode(':', $dimension, 2);

        return $this->cellPosition($end);
    }

    private function cellPosition(string $cellReference): array
    {
        preg_match('/([A-Z]+)(\d+)/', $cellReference, $match);

        return [
            $this->columnIndex($match[1] ?? 'A'),
            (int) ($match[2] ?? 1),
        ];
    }

    private function cellKind(string $text): string
    {
        $upper = strtoupper($text);

        if (str_starts_with($upper, 'PETA JABATAN')) {
            return 'title';
        }

        if (preg_match('/^\(?KELAS\s+\d+\)?$/i', $text)) {
            return 'class';
        }

        if (in_array($this->headerKey($text), ['jabatan', 'jabatan fungsional', 'kelas', 'kls', 'b', 'k', '+/-', '-/+'], true)) {
            return 'header';
        }

        if (str_starts_with($upper, 'KEPALA ') || str_starts_with($upper, 'SEKRETARIS') || str_contains($upper, 'BIDANG') || str_contains($upper, 'BAGIAN') || str_contains($upper, 'SUB BAGIAN')) {
            return 'position';
        }

        if (preg_match('/^-?\d+$/', $text)) {
            return 'number';
        }

        return 'text';
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $strings = [];
        $sharedStrings = $this->xml($zip, 'xl/sharedStrings.xml');

        foreach ($sharedStrings->si as $stringItem) {
            $parts = [];

            if (isset($stringItem->t)) {
                $parts[] = (string) $stringItem->t;
            }

            foreach ($stringItem->r ?? [] as $run) {
                $parts[] = (string) $run->t;
            }

            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $attributes = $cell->attributes();
        $type = (string) ($attributes['t'] ?? '');
        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            return (string) ($sharedStrings[(int) $value] ?? '');
        }

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        return $value;
    }

    private function sheetTitle(array $rows, string $fallback): string
    {
        foreach ($rows as $cells) {
            foreach ($cells as $value) {
                $text = $this->normalizeText($value);

                if (str_starts_with(strtoupper($text), 'PETA JABATAN')) {
                    return $text;
                }
            }
        }

        return $fallback;
    }

    private function uniqueRecords(array $records): array
    {
        $seen = [];
        $unique = [];

        foreach ($records as $record) {
            $key = implode('|', [$record['row'], $record['col'], $this->jobKey($record['jabatan'])]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $record;
        }

        return $unique;
    }

    private function isJabatanHeader(?string $value): bool
    {
        $key = $this->headerKey($value);

        return $key === 'jabatan' || $key === 'jabatan fungsional';
    }

    private function isIgnoredRecordLabel(string $value): bool
    {
        $key = $this->headerKey($value);

        return in_array($key, ['jabatan', 'jabatan fungsional', 'jumlah pegawai per kelas jabatan'], true)
            || str_starts_with($key, 'kelas ')
            || preg_match('/^\(?kelas\s+\d+\)?$/i', $value);
    }

    private function numericValue(mixed $value): ?int
    {
        $text = $this->normalizeText((string) $value);

        if ($text === '' || ! preg_match('/^-?\d+$/', $text)) {
            return null;
        }

        return (int) $text;
    }

    private function columnIndex(string $cellReference): int
    {
        preg_match('/([A-Z]+)/', $cellReference, $match);
        $letters = $match[1] ?? 'A';
        $number = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $number = $number * 26 + (ord($letters[$i]) - 64);
        }

        return $number;
    }

    private function headerKey(?string $value): string
    {
        return strtolower($this->normalizeText($value));
    }

    private function jobKey(?string $value): string
    {
        $value = strtoupper($this->normalizeText($value));
        $value = str_replace([
            'SUMBER DAYA MANUSIA',
            'APARATUR SIPIL NEGARA',
            'UNIT PELAKSANA TEKNIS DAERAH',
            'UNIT PELAKSANA TEKNIS',
            'TATA USAHA',
            'PENDIDIKAN ANAK USIA DINI',
            'PENDIDIKAN NON FORMAL',
            'PENDIDIKAN NONFORMAL',
            'PENERANGAN JALAN UMUM',
            'PENERANGAN JALAN LINGKUNGAN',
            'PENGUJIAN KENDARAAN BERMOTOR',
            'RUMAH POTONG HEWAN',
            'TEMPAT PENDARATAN IKAN',
            'BALAI LATIHAN KERJA',
            'PERLINDUNGAN PEREMPUAN DAN ANAK',
            'KEPENDUDUKAN DAN PENCATATAN SIPIL',
            'RUMAH SUSUN SEDERHANA SEWA',
            'RUMAH SUSUN DAN SEWA',
            'SUBBAGIAN',
            'TATA LAKSANA',
            'SPIRITUAL',
        ], [
            'SDM',
            'ASN',
            'UPTD',
            'UPT',
            'TU',
            'PAUD',
            'PNF',
            'PNF',
            'PJU',
            'PJL',
            'PKB',
            'RPH',
            'TPI',
            'BLK',
            'PPA',
            'DUKCAPIL',
            'RUMAH SUSUN SEWA',
            'RUMAH SUSUN SEWA',
            'SUB BAGIAN',
            'TATALAKSANA',
            'SPRITUAL',
        ], $value);
        $value = preg_replace('/\bKASI\b/u', 'KEPALA SEKSI', $value) ?: $value;
        $value = preg_replace('/\bKASUBBID\b/u', 'KEPALA SUB BIDANG', $value) ?: $value;
        $value = preg_replace('/\bKASUBBAG\b/u', 'KEPALA SUB BAGIAN', $value) ?: $value;
        $value = preg_replace('/\s+PADA\s+.+$/u', '', $value) ?: $value;
        $value = preg_replace('/\s+KOTA\s+BANJARMASIN$/u', '', $value) ?: $value;
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?: $value;

        return trim($value);
    }

    private function jobKeyMatches(string $left, string $right): bool
    {
        $left = trim($left);
        $right = trim($right);

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right || str_contains($left, $right) || str_contains($right, $left)) {
            return true;
        }

        $leftTokens = $this->significantJobTokens($left);
        $rightTokens = $this->significantJobTokens($right);
        $smaller = min(count($leftTokens), count($rightTokens));

        if ($smaller < 3) {
            return false;
        }

        $overlap = count(array_intersect($leftTokens, $rightTokens));

        return ($overlap / $smaller) >= 0.8;
    }

    private function significantJobTokens(string $key): array
    {
        $stopwords = [
            'DAN' => true,
            'DI' => true,
            'KE' => true,
            'PADA' => true,
            'KOTA' => true,
            'BANJARMASIN' => true,
            'KEPALA' => true,
            'SUB' => true,
            'BAGIAN' => true,
            'BIDANG' => true,
            'SEKSI' => true,
            'UNIT' => true,
            'UPT' => true,
            'UPTD' => true,
            'DAERAH' => true,
            'DINAS' => true,
            'BADAN' => true,
        ];

        return array_values(array_unique(array_filter(
            explode(' ', $key),
            fn (string $token): bool => strlen($token) > 2 && ! isset($stopwords[$token])
        )));
    }

    /**
     * Excel sometimes stores equivalent job names as slash-separated aliases,
     * while TPP may use an abbreviation such as SDM.
     */
    private function jobKeys(?string $value): array
    {
        $value = $this->normalizeText($value);
        $parts = preg_split('~/+~u', $value) ?: [$value];
        $keys = [];

        foreach (array_unique([$value, ...$parts]) as $part) {
            $key = $this->jobKey($part);

            if ($key !== '') {
                $keys[] = $key;
            }

            $withoutParentheses = $this->normalizeText((string) preg_replace('/\s*\([^)]*\)/u', '', $part));

            if ($withoutParentheses !== $part) {
                $key = $this->jobKey($withoutParentheses);

                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function orgKey(?string $value): string
    {
        $value = strtoupper($this->normalizeText($value));
        $value = str_replace(['PETA JABATAN', 'KOTA BANJARMASIN'], '', $value);
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?: $value;

        return trim($value);
    }

    private function normalizeText(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function xml(ZipArchive $zip, string $name): \SimpleXMLElement
    {
        return simplexml_load_string((string) $zip->getFromName($name));
    }

    private function emptySummary(): array
    {
        return [
            'sheets' => 0,
            'records' => 0,
            'needed' => 0,
            'filled' => 0,
            'vacant' => 0,
            'real_extra' => 0,
        ];
    }
}
