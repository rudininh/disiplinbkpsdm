<?php

namespace App\Services;

use App\Models\SiasnAbsensiLocationEmployee;
use App\Models\SiasnPnsProfile;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SiasnPnsExcelSyncService
{
    private const DEFAULT_PATH = 'C:/Users/JACKDAW/Downloads/PNS - 27 JANUARI 2026 - Copy.xlsx';

    private const DINAS_PENDIDIKAN_SKPD_ID = 1;

    public function sync(array $educationUnits): array
    {
        $path = (string) config('services.siasn.pns_excel_path', self::DEFAULT_PATH);

        if ($path === '' || ! is_file($path)) {
            return [
                'success' => false,
                'message' => 'File Excel PNS belum ditemukan: '.($path ?: self::DEFAULT_PATH),
            ];
        }

        $rows = $this->targetRows($this->readRows($path));
        if ($rows === []) {
            return [
                'success' => false,
                'message' => 'Tidak ada data jabatan Guru, Pengawas Sekolah, atau Penjaga Sekolah di Excel.',
            ];
        }

        $referenceLookup = $this->referenceLookup($educationUnits);
        $skpd = config('services.absensi.skpd.'.self::DINAS_PENDIDIKAN_SKPD_ID, []);
        $summary = [
            'excel_rows' => count($rows),
            'profiles_upserted' => 0,
            'employees_created' => 0,
            'employees_updated' => 0,
            'matched_reference_units' => 0,
            'excel_only_units' => 0,
            'guru' => 0,
            'pengawas_sekolah' => 0,
            'penjaga_sekolah' => 0,
        ];
        $matchedUnitKeys = [];
        $excelOnlyUnitKeys = [];

        foreach ($rows as $row) {
            $matchedUnit = $this->matchReferenceUnit($row['unit_kerja'], $referenceLookup);
            $unitKey = $matchedUnit !== null
                ? (string) ($matchedUnit['npsn'] ?? '')
                : $this->excelUnitKey($row['unit_kerja']);
            $unitName = $matchedUnit['unit_kerja'] ?? $row['unit_kerja'];
            $lokasiId = $matchedUnit !== null
                ? 'excel:'.(string) ($matchedUnit['npsn'] ?? $this->unitKey($unitName))
                : $unitKey;

            if ($matchedUnit !== null) {
                $matchedUnitKeys[$unitKey] = true;
            } else {
                $excelOnlyUnitKeys[$unitKey] = true;
            }

            $profile = SiasnPnsProfile::query()->updateOrCreate(
                ['nip' => $row['nip']],
                [
                    'jenis_asn' => 'PNS',
                    'nama' => $row['nama'],
                    'jabatan' => $row['jabatan'],
                    'jenis_jabatan' => $row['jenis_jabatan'],
                    'unit_organisasi' => $row['unit_kerja'],
                    'unit_organisasi_induk' => $row['unit_induk'],
                    'tmt_jabatan' => $row['tmt_jabatan'],
                    'raw_data' => [
                        'source' => 'excel_pns_27_januari_2026',
                        'path' => $path,
                        'excel_row' => $row['excel_row'],
                        'row' => $row['raw'],
                    ],
                    'fetched_at' => now(),
                ]
            );
            $summary['profiles_upserted']++;

            $employee = SiasnAbsensiLocationEmployee::query()
                ->where('skpd_id', self::DINAS_PENDIDIKAN_SKPD_ID)
                ->where('nip', $row['nip'])
                ->orderByRaw("CASE WHEN match_status = 'lokasi_absensi_cocok' THEN 0 ELSE 1 END")
                ->first();

            $rowData = is_array($employee?->row_data) ? $employee->row_data : [];
            $rowData = array_merge($rowData, [
                'referensi_npsn' => $matchedUnit['npsn'] ?? $unitKey,
                'referensi_unit_kerja' => $unitName,
                'referensi_match_source' => $matchedUnit !== null ? 'excel_pns_reference' : 'excel_pns_unit',
                'excel_siasn_pns_source' => basename($path),
                'excel_siasn_row' => $row['excel_row'],
                'excel_siasn_jabatan_kategori' => $row['kategori'],
                'excel_siasn_unit_kerja' => $row['unit_kerja'],
                'excel_siasn_unor_3' => $row['unor_3'],
                'excel_siasn_unor_2' => $row['unor_2'],
                'excel_siasn_unor_1' => $row['unor_1'],
            ]);

            $payload = [
                'skpd_id' => self::DINAS_PENDIDIKAN_SKPD_ID,
                'kode_skpd' => $skpd['kode'] ?? null,
                'nama_skpd' => $skpd['nama'] ?? 'Dinas Pendidikan',
                'lokasi_nama' => $employee?->lokasi_nama ?: $unitName,
                'lokasi_alamat' => $employee?->lokasi_alamat,
                'lokasi_lat' => $employee?->lokasi_lat,
                'lokasi_long' => $employee?->lokasi_long,
                'nama' => $row['nama'],
                'siasn_pns_profile_id' => $profile->id,
                'siasn_unit_organisasi' => $row['unit_kerja'],
                'siasn_jabatan' => $row['jabatan'],
                'match_status' => 'lokasi_absensi_cocok',
                'row_data' => $rowData,
                'fetched_at' => now(),
            ];

            if ($employee === null) {
                SiasnAbsensiLocationEmployee::query()->create([
                    ...$payload,
                    'lokasi_id' => $lokasiId,
                    'nip' => $row['nip'],
                ]);
                $summary['employees_created']++;
            } else {
                if (str_starts_with((string) $employee->lokasi_id, 'excel:')) {
                    $payload['lokasi_id'] = $lokasiId;
                }

                $employee->update($payload);
                $summary['employees_updated']++;
            }

            match ($row['kategori']) {
                'GURU' => $summary['guru']++,
                'PENGAWAS SEKOLAH' => $summary['pengawas_sekolah']++,
                'PENJAGA SEKOLAH' => $summary['penjaga_sekolah']++,
                default => null,
            };
        }

        $summary['matched_reference_units'] = count($matchedUnitKeys);
        $summary['excel_only_units'] = count($excelOnlyUnitKeys);

        return [
            'success' => true,
            'message' => 'Cek Data Excel ke SIASN selesai. '.number_format($summary['excel_rows'], 0, ',', '.').' pegawai diproses dari Excel.',
            'summary' => $summary,
        ];
    }

    private function readRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel PNS tidak bisa dibuka.');
        }

        $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $relationshipMap = [];

        foreach ($relationships->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            $relationshipMap[(string) $attributes['Id']] = 'xl/'.ltrim((string) $attributes['Target'], '/');
        }

        $workbook = $this->xml($zip, 'xl/workbook.xml');
        $sharedStrings = $this->sharedStrings($zip);
        $firstSheet = $workbook->sheets->sheet[0] ?? null;
        if ($firstSheet === null) {
            $zip->close();

            return [];
        }

        $relationshipAttributes = $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheetFile = $relationshipMap[(string) $relationshipAttributes['id']] ?? null;
        if ($sheetFile === null) {
            $zip->close();

            return [];
        }

        $worksheet = $this->xml($zip, $sheetFile);
        $rows = [];

        foreach ($worksheet->sheetData->row as $rowNode) {
            $rowNumber = (int) $rowNode->attributes()['r'];

            foreach ($rowNode->c as $cell) {
                $reference = (string) $cell->attributes()['r'];
                $rows[$rowNumber][$this->columnIndex($reference)] = $this->normalizeText($this->cellValue($cell, $sharedStrings));
            }
        }

        $zip->close();
        ksort($rows);

        return $rows;
    }

    private function targetRows(array $rows): array
    {
        $header = [];
        $records = [];

        foreach ($rows[1] ?? [] as $column => $value) {
            $key = $this->headerKey($value);
            if ($key !== '') {
                $header[$key] = $column;
            }
        }

        foreach ($rows as $rowNumber => $cells) {
            if ((int) $rowNumber === 1) {
                continue;
            }

            $nip = preg_replace('/\D+/', '', $this->cellByHeader($cells, $header, 'NIP BARU')) ?? '';
            if (strlen($nip) !== 18) {
                continue;
            }

            $jabatan = $this->cellByHeader($cells, $header, 'JABATAN NAMA');
            $kategori = $this->targetCategory($jabatan);
            if ($kategori === null) {
                continue;
            }

            $unor3 = $this->cellByHeader($cells, $header, 'UNOR (3)');
            $unor2 = $this->cellByHeader($cells, $header, 'UNOR (2)');
            $unor1 = $this->cellByHeader($cells, $header, 'UNOR (1)');
            $unitKerja = $this->firstFilled([$unor3, $unor2, $unor1]);
            if ($unitKerja === '') {
                continue;
            }

            $records[] = [
                'excel_row' => (int) $rowNumber,
                'nip' => $nip,
                'nama' => $this->cellByHeader($cells, $header, 'NAMA'),
                'jenis_jabatan' => $this->cellByHeader($cells, $header, 'JENIS JABATAN NAMA'),
                'jabatan' => $jabatan,
                'kategori' => $kategori,
                'tmt_jabatan' => $this->dateValue($this->cellByHeader($cells, $header, 'TMT JABATAN')),
                'unit_kerja' => $unitKerja,
                'unit_induk' => $unor1,
                'unor_3' => $unor3,
                'unor_2' => $unor2,
                'unor_1' => $unor1,
                'raw' => [
                    'NIP BARU' => $nip,
                    'NAMA' => $this->cellByHeader($cells, $header, 'NAMA'),
                    'GOL AKHIR NAMA' => $this->cellByHeader($cells, $header, 'GOL AKHIR NAMA'),
                    'JENIS JABATAN NAMA' => $this->cellByHeader($cells, $header, 'JENIS JABATAN NAMA'),
                    'JABATAN NAMA' => $jabatan,
                    'TMT JABATAN' => $this->cellByHeader($cells, $header, 'TMT JABATAN'),
                    'UNOR (3)' => $unor3,
                    'UNOR (2)' => $unor2,
                    'UNOR (1)' => $unor1,
                ],
            ];
        }

        return $records;
    }

    private function targetCategory(string $jabatan): ?string
    {
        $upper = Str::of($jabatan)->upper()->toString();

        if (str_contains($upper, 'PENGAWAS SEKOLAH')) {
            return 'PENGAWAS SEKOLAH';
        }

        if (str_contains($upper, 'PENJAGA SEKOLAH')) {
            return 'PENJAGA SEKOLAH';
        }

        if (preg_match('/\bGURU\b/u', $upper) === 1) {
            return 'GURU';
        }

        return null;
    }

    private function matchReferenceUnit(string $unitName, array $referenceLookup): ?array
    {
        $key = $this->unitKey($unitName);
        if ($key === '') {
            return null;
        }

        return $referenceLookup[$key] ?? null;
    }

    private function referenceLookup(array $educationUnits): array
    {
        $lookup = [];

        foreach ($educationUnits as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $key = $this->unitKey((string) ($unit['unit_kerja'] ?? ''));
            if ($key === '') {
                continue;
            }

            $lookup[$key] = $unit;
        }

        return $lookup;
    }

    private function unitKey(string $value): string
    {
        $normalized = Str::of($value)
            ->lower()
            ->replaceMatches('/\bkota\s+banjarmasin\b/u', '')
            ->replaceMatches('/\bbanjarmasin\b/u', '')
            ->replaceMatches('/\bsdn\b/u', 'sd negeri')
            ->replaceMatches('/\bsd\s*n\b/u', 'sd negeri')
            ->replaceMatches('/\bsmpn\b/u', 'smp negeri')
            ->replaceMatches('/\bsmp\s*n\b/u', 'smp negeri')
            ->replaceMatches('/\bmtsn\b/u', 'mts negeri')
            ->replaceMatches('/\bmin\b/u', 'mi negeri')
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->replaceMatches('/\b(sd|smp|tk)\s*n\b/u', '$1 negeri')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();

        return preg_replace('/\b0+(\d+)\b/u', '$1', $normalized) ?? $normalized;
    }

    private function excelUnitKey(string $unitName): string
    {
        $slug = Str::slug($this->unitKey($unitName));
        $hash = substr(md5($unitName), 0, 8);

        if ($slug === '') {
            return 'excel:'.$hash;
        }

        return 'excel:'.Str::limit($slug, 42, '').'-'.$hash;
    }

    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            $value = $this->normalizeText((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function cellByHeader(array $cells, array $header, string $name): string
    {
        $column = $header[$this->headerKey($name)] ?? null;
        if ($column === null) {
            return '';
        }

        return $this->normalizeText((string) ($cells[$column] ?? ''));
    }

    private function headerKey(string $value): string
    {
        return Str::of($value)
            ->upper()
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();
    }

    private function dateValue(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                //
            }
        }

        return null;
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

    private function columnIndex(string $cellReference): int
    {
        preg_match('/([A-Z]+)/', $cellReference, $match);
        $letters = $match[1] ?? 'A';
        $number = 0;

        foreach (str_split($letters) as $letter) {
            $number = ($number * 26) + (ord($letter) - 64);
        }

        return $number;
    }

    private function xml(ZipArchive $zip, string $name): \SimpleXMLElement
    {
        $xml = $zip->getFromName($name);
        if ($xml === false) {
            throw new RuntimeException('Struktur Excel tidak lengkap: '.$name);
        }

        $loaded = simplexml_load_string($xml);
        if (! $loaded instanceof \SimpleXMLElement) {
            throw new RuntimeException('XML Excel tidak valid: '.$name);
        }

        return $loaded;
    }
}
