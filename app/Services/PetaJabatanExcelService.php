<?php

namespace App\Services;

use App\Models\SiasnAbsensiLocationEmployee;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class PetaJabatanExcelService
{
    private const DEFAULT_PATH = 'C:/Users/RUDINI/Downloads/Lampiran Perubahan kedua dan ketiga gabung Peta Jabatan 2026.xlsx';

    public function comparison(?array $tppPayload, ?int $selectedSheet = null): array
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
        $siasnFunctionalPools = $this->siasnFunctionalPools();
        $sheets = [];
        $summary = $this->emptySummary();

        foreach ($workbook['sheets'] as $index => $sheet) {
            $matchedSkpd = $this->matchSkpd($sheet, $real['skpd']);
            $pool = $matchedSkpd ? [
                'jobs' => $real['by_skpd'][$matchedSkpd['skpd_id']] ?? [],
                'categories' => $real['by_skpd_category'][$matchedSkpd['skpd_id']] ?? [],
                'siasn_jobs' => $siasnFunctionalPools[$matchedSkpd['skpd_id']]['jobs'] ?? [],
            ] : [
                'jobs' => $real['global'],
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
        $zip = new ZipArchive();
        $zip->open($path);
        $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $relationshipMap = [];

        foreach ($relationships->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            $relationshipMap[(string) $attributes['Id']] = 'xl/' . ltrim((string) $attributes['Target'], '/');
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
                'records' => $this->extractRecords($rows),
                'grid' => $this->compactGrid($rows, $merges, (string) ($worksheet->dimension->attributes()['ref'] ?? '')),
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
            $categoryMatchingKeys = array_values(array_filter($keys, fn (string $key): bool => isset($categoryJobs[$key])));
            $matchingKeys = $categoryMatchingKeys !== []
                ? $categoryMatchingKeys
                : ($categoryExists ? [] : array_values(array_filter($keys, fn (string $key): bool => isset($jobPool[$key]))));
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
        if (! str_contains(strtoupper((string) ($record['category'] ?? '')), 'JABATAN FUNGSIONAL')) {
            return [];
        }

        $people = [];
        $unitKind = $this->educationUnitKindForFunctionalRecord($record['jabatan'] ?? '');

        foreach ($keys as $key) {
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
                return 'nip:' . $nip;
            }

            return implode('|', [
                (string) ($person['source'] ?? 'person'),
                $this->jobKey($person['name'] ?? ''),
                $this->jobKey($person['unit_kerja'] ?? ''),
                $this->jobKey($person['jabatan'] ?? ''),
            ]);
        }

        return 'name:' . $this->jobKey((string) $person);
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

    private function extractRecords(array $rows): array
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

                $records[] = [
                    'row' => $row,
                    'col' => $header['jabatan_col'],
                    'jabatan' => $jabatan,
                    'kelas' => $kelas,
                    'bezetting' => $bezetting,
                    'kebutuhan' => $kebutuhan,
                    'selisih' => $this->numericValue($rows[$row][$header['selisih_col']] ?? null),
                    'category' => $category['name'],
                    'category_match' => $this->categoryMatchForRecord($jabatan, $category['name']),
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
        $startColumn = max((int) $header['jabatan_col'] - 2, 1);
        $endColumn = (int) ($header['selisih_col'] ?? $header['kebutuhan_col']) + 2;
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

        return 'Kepala ' . $value;
    }

    private function isIgnoredCategoryLabel(string $value): bool
    {
        $key = $this->headerKey($value);

        return $this->isIgnoredRecordLabel($value)
            || in_array($key, ['org', 'kelas', 'kls', 'b', 'k', '+/-', '-/+', 'kekuatan pegawai'], true)
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

    private function compactGrid(array $rows, array $merges, string $dimension): array
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

                $merge = $merges[$rowNumber . ':' . $column] ?? ['cols' => 1, 'rows' => 1];
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

        return [
            'max_col' => min($maxColumn, 140),
            'max_row' => min($maxRow, 180),
            'cells' => array_values(array_filter($chartCells, fn (array $cell): bool => $cell['col'] <= 140 && $cell['row'] <= 180)),
        ];
    }

    private function flattenTppPayload(?array $payload): array
    {
        $global = [];
        $bySkpd = [];
        $bySkpdCategory = [];
        $skpdRows = [];

        foreach (($payload['skpd'] ?? []) as $skpd) {
            $skpdId = (int) ($skpd['skpd_id'] ?? 0);
            $skpdRows[] = [
                'skpd_id' => $skpdId,
                'nama' => (string) ($skpd['nama'] ?? ''),
                'kode' => (string) ($skpd['kode'] ?? ''),
            ];

            foreach ($this->flattenTree($skpd['tree'] ?? []) as $row) {
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
            ->whereIn('match_status', ['lokasi_absensi_cocok', 'unit_cocok'])
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
        $haystack = $this->orgKey(($sheet['title'] ?? '') . ' ' . ($sheet['name'] ?? ''));

        if (str_contains($haystack, 'PUSKESMAS') || str_contains($haystack, 'RUMAH SAKIT') || str_contains($haystack, 'KESEHATAN')) {
            return $this->findSkpdContaining($skpdRows, 'KESEHATAN');
        }

        if (str_contains($haystack, 'SET DPRD')) {
            return $this->findSkpdContaining($skpdRows, 'DPRD');
        }

        $best = null;
        $bestScore = 0;

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
        }

        return $bestScore >= 1 ? $best : null;
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

            $merges[$startRow . ':' . $startColumn] = [
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
