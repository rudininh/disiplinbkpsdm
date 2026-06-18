<?php

namespace App\Services;

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
        $sheets = [];
        $summary = $this->emptySummary();

        foreach ($workbook['sheets'] as $index => $sheet) {
            $matchedSkpd = $this->matchSkpd($sheet, $real['skpd']);
            $pool = $matchedSkpd ? ($real['by_skpd'][$matchedSkpd['skpd_id']] ?? []) : $real['global'];
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
        $cursors = [];

        return array_map(function (array $record) use ($realPool, &$cursors): array {
            $key = $this->jobKey($record['jabatan']);
            $people = $realPool[$key] ?? [];
            $cursor = $cursors[$key] ?? 0;
            $needed = max((int) ($record['kebutuhan'] ?? 0), (int) ($record['bezetting'] ?? 0), 1);
            $assigned = array_slice($people, $cursor, $needed);
            $cursors[$key] = $cursor + count($assigned);
            $filled = count(array_filter($assigned));
            $vacant = max($needed - $filled, 0);

            return [
                ...$record,
                'needed' => $needed,
                'filled' => $filled,
                'vacant' => $vacant,
                'real_extra' => max(count($people) - $cursors[$key], 0),
                'people' => array_values($assigned),
                'slots' => [
                    ...array_map(fn (string $name): array => ['status' => 'Terisi', 'nama' => $name], array_values($assigned)),
                    ...array_fill(0, $vacant, ['status' => 'Kosong', 'nama' => null]),
                ],
            ];
        }, $records);
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
                    'category' => $category,
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

    private function categoryForHeader(array $rows, array $header): ?string
    {
        $startRow = max((int) $header['row'] - 12, 1);
        $startColumn = max((int) $header['jabatan_col'] - 2, 1);
        $endColumn = (int) ($header['selisih_col'] ?? $header['kebutuhan_col']) + 2;
        $fallback = null;

        for ($row = (int) $header['row'] - 1; $row >= $startRow; $row--) {
            for ($column = $startColumn; $column <= $endColumn; $column++) {
                $text = $this->normalizeText($rows[$row][$column] ?? '');

                if ($text === '') {
                    continue;
                }

                if (str_contains(strtoupper($text), 'JABATAN FUNGSIONAL')) {
                    return 'Jabatan Fungsional';
                }

                if ($this->isIgnoredCategoryLabel($text)) {
                    continue;
                }

                $fallback ??= $text;
            }
        }

        return $fallback;
    }

    private function isIgnoredCategoryLabel(string $value): bool
    {
        $key = $this->headerKey($value);

        return $this->isIgnoredRecordLabel($value)
            || in_array($key, ['org', 'kelas', 'kls', 'b', 'k', '+/-', '-/+', 'kekuatan pegawai'], true)
            || str_starts_with($key, 'peta jabatan')
            || str_starts_with($key, 'jumlah pegawai')
            || preg_match('/^-?\d+$/', $value);
    }

    private function findHeaderColumn(array $cells, int $startColumn, array $labels): ?int
    {
        for ($column = $startColumn; $column <= $startColumn + 12; $column++) {
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

                if ($key === '' || empty($row['pegawai'])) {
                    continue;
                }

                $bySkpd[$skpdId][$key][] = (string) $row['pegawai'];
                $global[$key][] = (string) $row['pegawai'];
            }
        }

        return [
            'global' => $global,
            'by_skpd' => $bySkpd,
            'skpd' => $skpdRows,
        ];
    }

    private function flattenTree(array $nodes): array
    {
        $rows = [];

        foreach ($nodes as $node) {
            $rows[] = $node;
            $rows = array_merge($rows, $this->flattenTree($node['children'] ?? []));
        }

        return $rows;
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
        $value = preg_replace('/\s+PADA\s+.+$/u', '', $value) ?: $value;
        $value = preg_replace('/\s+KOTA\s+BANJARMASIN$/u', '', $value) ?: $value;
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?: $value;

        return trim($value);
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
