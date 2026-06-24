<?php

namespace App\Services;

use App\Models\AbsensiPegawai;
use App\Models\SiasnAbsensiLocationEmployee;
use App\Models\SiasnPnsProfile;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SiasnPegawaiExcelImportService
{
    public function import(UploadedFile $file): array
    {
        $rows = $this->readRows($file->getRealPath() ?: $file->path());
        $records = $this->records($rows);

        if ($records === []) {
            return [
                'success' => false,
                'message' => 'Tidak ada pegawai valid yang terbaca dari Excel. Pastikan header berisi NIP BARU, NAMA, STATUS ASN, JABATAN NAMA, UNOR 2, dan UNOR 1.',
                'summary' => ['excel_rows' => 0],
            ];
        }

        $skpdLookup = $this->skpdLookup();
        $summary = [
            'excel_rows' => count($records),
            'absensi_pegawai_created' => 0,
            'absensi_pegawai_updated' => 0,
            'absensi_pegawai_inactivated' => 0,
            'profiles_upserted' => 0,
            'siasn_employees_created' => 0,
            'siasn_employees_updated' => 0,
            'siasn_employees_inactivated' => 0,
            'matched_skpd' => 0,
            'unmatched_skpd' => 0,
        ];
        $activeNips = [];

        foreach ($records as $record) {
            $activeNips[$record['nip']] = true;
            $skpd = $this->matchSkpd($record['unor_1'], $skpdLookup);
            $summary[$skpd === null ? 'unmatched_skpd' : 'matched_skpd']++;

            $profile = SiasnPnsProfile::query()->updateOrCreate(
                ['nip' => $record['nip']],
                [
                    'jenis_asn' => $record['status_asn'],
                    'nama' => $record['nama'],
                    'jabatan' => $record['jabatan'],
                    'jenis_jabatan' => $record['jenis_jabatan'],
                    'unit_organisasi' => $record['unit_kerja'],
                    'unit_organisasi_induk' => $record['unor_1'],
                    'tmt_jabatan' => $record['tmt_jabatan'],
                    'raw_data' => [
                        'source' => 'excel_siasn_pegawai',
                        'filename' => $file->getClientOriginalName(),
                        'excel_row' => $record['excel_row'],
                        'row' => $record['raw'],
                    ],
                    'fetched_at' => now(),
                ]
            );
            $summary['profiles_upserted']++;

            $pegawaiPayload = [
                'pegawai_id' => null,
                'nip' => $record['nip'],
                'nama' => $record['nama'],
                'pangkat_golongan' => $record['golongan'],
                'skpd' => $skpd['nama'] ?? $record['unor_1'],
                'unit_kerja' => $record['unit_kerja'],
                'jabatan' => $record['jabatan'],
                'puskesmas' => null,
                'device_id' => null,
                'history_url' => null,
                'row_data' => [
                    ...$record['raw'],
                    'source' => 'excel_siasn_pegawai',
                    'excel_import_active' => true,
                    'status_pegawai' => 'Aktif',
                    'matched_skpd_id' => $skpd['id'] ?? null,
                    'matched_skpd_name' => $skpd['nama'] ?? null,
                ],
                'fetched_at' => now(),
            ];

            $pegawai = AbsensiPegawai::query()->where('nip', $record['nip'])->first();

            if ($pegawai instanceof AbsensiPegawai) {
                $pegawai->update($pegawaiPayload);
                $summary['absensi_pegawai_updated']++;
            } else {
                AbsensiPegawai::query()->create([
                    ...$pegawaiPayload,
                    'row_hash' => hash('sha256', 'excel-siasn-pegawai:'.$record['nip']),
                ]);
                $summary['absensi_pegawai_created']++;
            }

            $skpdId = (int) ($skpd['id'] ?? 0);
            $lokasiId = 'excel-siasn:'.($skpdId > 0 ? $skpdId : Str::slug($record['unor_1'] ?: 'tanpa-skpd'));
            $employee = SiasnAbsensiLocationEmployee::query()
                ->where('lokasi_id', $lokasiId)
                ->where('nip', $record['nip'])
                ->first();

            $employeePayload = [
                'skpd_id' => $skpdId,
                'kode_skpd' => $skpd['kode'] ?? null,
                'nama_skpd' => $skpd['nama'] ?? $record['unor_1'],
                'lokasi_nama' => $record['unit_kerja'] ?: ($record['unor_2'] ?: $record['unor_1']),
                'lokasi_alamat' => null,
                'lokasi_lat' => null,
                'lokasi_long' => null,
                'nama' => $record['nama'],
                'siasn_pns_profile_id' => $profile->id,
                'siasn_unit_organisasi' => $record['unit_kerja'],
                'siasn_jabatan' => $record['jabatan'],
                'match_status' => 'excel_siasn_import',
                'row_data' => [
                    ...$record['raw'],
                    'source' => 'excel_siasn_pegawai',
                    'excel_import_active' => true,
                    'status_pegawai' => 'Aktif',
                    'siasn_status' => $record['status_asn'],
                    'excel_row' => $record['excel_row'],
                    'matched_skpd_id' => $skpd['id'] ?? null,
                    'matched_skpd_name' => $skpd['nama'] ?? null,
                ],
                'fetched_at' => now(),
            ];

            if ($employee instanceof SiasnAbsensiLocationEmployee) {
                $employee->update($employeePayload);
                $summary['siasn_employees_updated']++;
            } else {
                SiasnAbsensiLocationEmployee::query()->create([
                    ...$employeePayload,
                    'lokasi_id' => $lokasiId,
                    'nip' => $record['nip'],
                ]);
                $summary['siasn_employees_created']++;
            }
        }

        $inactiveSummary = $this->markMissingEmployeesInactive(array_keys($activeNips), $file->getClientOriginalName());
        $summary['absensi_pegawai_inactivated'] = $inactiveSummary['absensi_pegawai_inactivated'];
        $summary['siasn_employees_inactivated'] = $inactiveSummary['siasn_employees_inactivated'];

        return [
            'success' => true,
            'message' => 'Import Excel pegawai SIASN selesai. '.number_format($summary['excel_rows'], 0, ',', '.').' pegawai diproses.',
            'summary' => $summary,
        ];
    }

    private function records(array $rows): array
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

            $nama = $this->cellByHeader($cells, $header, 'NAMA');
            $jabatan = $this->cellByHeader($cells, $header, 'JABATAN NAMA');
            $unor3 = $this->cellByHeader($cells, $header, 'UNOR 3');
            $unor2 = $this->cellByHeader($cells, $header, 'UNOR 2');
            $unor1 = $this->cellByHeader($cells, $header, 'UNOR 1');

            if ($nama === '' || $jabatan === '' || ($unor1 === '' && $unor2 === '' && $unor3 === '')) {
                continue;
            }

            $records[] = [
                'excel_row' => (int) $rowNumber,
                'nip' => $nip,
                'nama' => $nama,
                'status_asn' => $this->cellByHeader($cells, $header, 'STATUS ASN') ?: 'PNS',
                'golongan' => $this->cellByHeader($cells, $header, 'GOL AKHIR NAMA'),
                'jenis_jabatan' => $this->cellByHeader($cells, $header, 'JENIS JABATAN NAMA'),
                'jabatan' => $jabatan,
                'tmt_jabatan' => $this->dateValue($this->cellByHeader($cells, $header, 'TMT JABATAN')),
                'unit_kerja' => $this->firstFilled([$unor3, $unor2, $unor1]),
                'unor_3' => $unor3,
                'unor_2' => $unor2,
                'unor_1' => $unor1,
                'raw' => [
                    'NIP BARU' => $nip,
                    'NAMA' => $nama,
                    'STATUS ASN' => $this->cellByHeader($cells, $header, 'STATUS ASN'),
                    'GOL AKHIR NAMA' => $this->cellByHeader($cells, $header, 'GOL AKHIR NAMA'),
                    'JENIS JABATAN NAMA' => $this->cellByHeader($cells, $header, 'JENIS JABATAN NAMA'),
                    'JABATAN NAMA' => $jabatan,
                    'TMT JABATAN' => $this->cellByHeader($cells, $header, 'TMT JABATAN'),
                    'UNOR 3' => $unor3,
                    'UNOR 2' => $unor2,
                    'UNOR 1' => $unor1,
                ],
            ];
        }

        return $records;
    }

    private function markMissingEmployeesInactive(array $activeNips, string $filename): array
    {
        $activeNips = array_values(array_unique(array_filter($activeNips)));
        $summary = [
            'absensi_pegawai_inactivated' => 0,
            'siasn_employees_inactivated' => 0,
        ];

        AbsensiPegawai::query()
            ->whereNotNull('nip')
            ->whereNotIn('nip', $activeNips)
            ->orderBy('id')
            ->chunkById(500, function ($employees) use ($filename, &$summary): void {
                foreach ($employees as $employee) {
                    $rowData = is_array($employee->row_data) ? $employee->row_data : [];
                    $rowData['excel_import_active'] = false;
                    $rowData['status_pegawai'] = 'Nonaktif';
                    $rowData['nonaktif_reason'] = 'Tidak ditemukan di Excel SIASN terakhir';
                    $rowData['last_excel_siasn_source'] = $filename;
                    $rowData['last_excel_siasn_checked_at'] = now()->toDateTimeString();

                    $employee->update([
                        'row_data' => $rowData,
                        'fetched_at' => now(),
                    ]);
                    $summary['absensi_pegawai_inactivated']++;
                }
            });

        SiasnAbsensiLocationEmployee::query()
            ->whereNotNull('nip')
            ->whereNotIn('nip', $activeNips)
            ->orderBy('id')
            ->chunkById(500, function ($employees) use ($filename, &$summary): void {
                foreach ($employees as $employee) {
                    $rowData = is_array($employee->row_data) ? $employee->row_data : [];
                    $rowData['excel_import_active'] = false;
                    $rowData['status_pegawai'] = 'Nonaktif';
                    $rowData['nonaktif_reason'] = 'Tidak ditemukan di Excel SIASN terakhir';
                    $rowData['last_excel_siasn_source'] = $filename;
                    $rowData['last_excel_siasn_checked_at'] = now()->toDateTimeString();

                    $employee->update([
                        'match_status' => 'excel_siasn_nonaktif',
                        'row_data' => $rowData,
                        'fetched_at' => now(),
                    ]);
                    $summary['siasn_employees_inactivated']++;
                }
            });

        return $summary;
    }

    private function readRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak bisa dibuka. Gunakan file .xlsx.');
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

    private function skpdLookup(): array
    {
        return collect(config('services.absensi.skpd', []))
            ->map(fn (array $skpd, int $id): array => [
                'id' => $id,
                'kode' => $skpd['kode'] ?? null,
                'nama' => $skpd['nama'] ?? null,
                'key' => $this->orgKey((string) ($skpd['nama'] ?? '')),
            ])
            ->filter(fn (array $skpd): bool => ($skpd['nama'] ?? '') !== '')
            ->values()
            ->all();
    }

    private function matchSkpd(string $name, array $lookup): ?array
    {
        $key = $this->orgKey($name);
        if ($key === '') {
            return null;
        }

        if (str_contains($key, 'KEPEGAWAIAN') && (str_contains($key, 'SUMBER DAYA MANUSIA') || str_contains($key, 'PENDIDIKAN PELATIHAN'))) {
            return collect($lookup)->firstWhere('id', 24);
        }

        foreach ($lookup as $skpd) {
            $candidate = (string) ($skpd['key'] ?? '');
            if ($candidate !== '' && ($candidate === $key || str_contains($candidate, $key) || str_contains($key, $candidate))) {
                return $skpd;
            }
        }

        $keyTokens = $this->tokens($key);
        $best = null;
        $bestScore = 0.0;

        foreach ($lookup as $skpd) {
            $candidateTokens = $this->tokens((string) ($skpd['key'] ?? ''));
            $smallest = min(count($keyTokens), count($candidateTokens));
            if ($smallest === 0) {
                continue;
            }

            $score = count(array_intersect($keyTokens, $candidateTokens)) / $smallest;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $skpd;
            }
        }

        return $bestScore >= 0.7 ? $best : null;
    }

    private function tokens(string $value): array
    {
        $stopwords = ['DAN' => true, 'KOTA' => true, 'BANJARMASIN' => true, 'DAERAH' => true];

        return array_values(array_unique(array_filter(
            explode(' ', $value),
            fn (string $token): bool => strlen($token) > 2 && ! isset($stopwords[$token])
        )));
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
            ->replaceMatches('/[^A-Z0-9]+/u', '')
            ->trim()
            ->toString();
    }

    private function orgKey(string $value): string
    {
        $value = Str::of($value)
            ->upper()
            ->replaceMatches('/\bBKPSDM\b/u', 'BADAN KEPEGAWAIAN PENGEMBANGAN SUMBER DAYA MANUSIA')
            ->replaceMatches('/[^A-Z0-9]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();

        return $value;
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

        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
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
