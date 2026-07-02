<?php

namespace App\Services;

use App\Models\AbsensiPegawai;
use App\Models\SiasnAbsensiLocationEmployee;
use App\Models\SiasnPnsProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class PegawaiAnomalyService
{
    /**
     * Analyze anomalies between the database and the latest Excel SIASN file.
     */
    public function analyze(): array
    {
        $excelPath = $this->findLatestExcelFile();

        if ($excelPath === null) {
            return [
                'success' => false,
                'message' => 'Tidak ditemukan file Excel di folder datapegawai. Letakkan file .xlsx dari SIASN di folder datapegawai.',
                'anomalies' => collect(),
                'stats' => [],
            ];
        }

        $excelNips = $this->readNipsFromExcel($excelPath);
        $excelNipSet = collect($excelNips)->pluck('nip')->flip();

        // All pegawai in database with NIP
        $dbPegawai = AbsensiPegawai::query()
            ->whereNotNull('nip')
            ->where('nip', '!=', '')
            ->get();

        $anomalies = collect();
        $matchedCount = 0;

        foreach ($dbPegawai as $pegawai) {
            $nip = $this->normalizeNip($pegawai->nip);
            if ($nip === '' || strlen($nip) < 10) {
                continue;
            }

            if ($excelNipSet->has($nip)) {
                $matchedCount++;
                continue;
            }

            // This pegawai is in DB but NOT in Excel → anomaly
            $reasons = $this->detectReasons($pegawai, $nip);

            $anomalies->push([
                'id' => $pegawai->id,
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'skpd' => $pegawai->skpd,
                'jabatan' => $pegawai->jabatan,
                'pangkat_golongan' => $pegawai->pangkat_golongan,
                'status_pegawai' => $pegawai->row_data['status_pegawai'] ?? '-',
                'excel_import_active' => $pegawai->row_data['excel_import_active'] ?? null,
                'source' => $pegawai->row_data['source'] ?? '-',
                'reasons' => $reasons,
                'primary_reason' => $reasons[0]['label'] ?? 'Tidak diketahui',
                'severity' => $this->severityFromReasons($reasons),
                'fetched_at' => $pegawai->fetched_at?->toDateTimeString(),
            ]);
        }

        // Sort by severity (high → low), then by NIP
        $anomalies = $anomalies->sortBy([
            ['severity', 'desc'],
            ['nip', 'asc'],
        ])->values();

        return [
            'success' => true,
            'message' => 'Analisa selesai.',
            'excel_file' => basename($excelPath),
            'anomalies' => $anomalies,
            'stats' => [
                'total_db' => $dbPegawai->count(),
                'total_excel' => count($excelNips),
                'matched' => $matchedCount,
                'anomaly_count' => $anomalies->count(),
                'by_reason' => $this->countByPrimaryReason($anomalies),
                'by_severity' => [
                    'tinggi' => $anomalies->where('severity', 'tinggi')->count(),
                    'sedang' => $anomalies->where('severity', 'sedang')->count(),
                    'rendah' => $anomalies->where('severity', 'rendah')->count(),
                ],
            ],
        ];
    }

    /**
     * Delete anomalous pegawai from all related tables.
     */
    public function deletePegawai(array $ids): array
    {
        $deleted = [
            'absensi_pegawai' => 0,
            'siasn_pns_profiles' => 0,
            'siasn_absensi_location_employees' => 0,
        ];

        $pegawaiList = AbsensiPegawai::query()->whereIn('id', $ids)->get();

        foreach ($pegawaiList as $pegawai) {
            $nip = $this->normalizeNip($pegawai->nip);

            // Delete from siasn_pns_profiles
            $deleted['siasn_pns_profiles'] += SiasnPnsProfile::query()
                ->where('nip', $nip)
                ->delete();

            // Delete from siasn_absensi_location_employees
            $deleted['siasn_absensi_location_employees'] += SiasnAbsensiLocationEmployee::query()
                ->where('nip', $nip)
                ->delete();

            // Delete from absensi_pegawais
            $pegawai->delete();
            $deleted['absensi_pegawai']++;
        }

        return $deleted;
    }

    /**
     * Detect possible reasons why this pegawai is an anomaly.
     */
    private function detectReasons(AbsensiPegawai $pegawai, string $nip): array
    {
        $reasons = [];
        $rowData = is_array($pegawai->row_data) ? $pegawai->row_data : [];

        // 1. Check if already marked inactive
        if (($rowData['excel_import_active'] ?? null) === false ||
            ($rowData['status_pegawai'] ?? '') === 'Nonaktif') {
            $reasons[] = [
                'code' => 'sudah_nonaktif',
                'label' => 'Sudah ditandai Nonaktif',
                'description' => 'Pegawai sudah pernah ditandai nonaktif pada import sebelumnya.',
                'severity' => 'tinggi',
            ];
        }

        // 2. Check NIP birth year for possible pension
        $birthYear = $this->extractBirthYearFromNip($nip);
        if ($birthYear !== null) {
            $currentYear = (int) date('Y');
            $age = $currentYear - $birthYear;

            if ($age >= 60) {
                $reasons[] = [
                    'code' => 'kemungkinan_pensiun',
                    'label' => "Kemungkinan Pensiun (lahir {$birthYear}, usia ~{$age} th)",
                    'description' => "NIP menunjukkan tahun lahir {$birthYear}. Usia sekarang ~{$age} tahun, melebihi batas usia pensiun.",
                    'severity' => 'tinggi',
                ];
            } elseif ($age >= 56) {
                $reasons[] = [
                    'code' => 'mendekati_pensiun',
                    'label' => "Mendekati Pensiun (lahir {$birthYear}, usia ~{$age} th)",
                    'description' => "NIP menunjukkan tahun lahir {$birthYear}. Usia sekarang ~{$age} tahun, mendekati batas pensiun.",
                    'severity' => 'sedang',
                ];
            }
        }

        // 3. Check if NIP format is unusual (not 18 digits)
        if (strlen($nip) !== 18) {
            $reasons[] = [
                'code' => 'nip_tidak_standar',
                'label' => 'NIP tidak standar (' . strlen($nip) . ' digit)',
                'description' => 'NIP tidak 18 digit. Kemungkinan NIP lama atau data tidak valid.',
                'severity' => 'sedang',
            ];
        }

        // 4. Check source - if from scraper (not excel import)
        $source = $rowData['source'] ?? '';
        if ($source !== '' && $source !== 'excel_siasn_pegawai') {
            $reasons[] = [
                'code' => 'sumber_bukan_siasn',
                'label' => 'Data bukan dari SIASN Excel',
                'description' => "Data bersumber dari '{$source}', bukan dari import Excel SIASN.",
                'severity' => 'rendah',
            ];
        }

        // 5. Check jabatan for THL/Honorer
        $jabatan = strtolower($pegawai->jabatan ?? '');
        if (Str::contains($jabatan, ['thl', 'honorer', 'kontrak', 'non asn', 'tenaga harian'])) {
            $reasons[] = [
                'code' => 'bukan_asn',
                'label' => 'Kemungkinan Non-ASN (THL/Honorer)',
                'description' => 'Jabatan mengindikasikan pegawai non-ASN.',
                'severity' => 'tinggi',
            ];
        }

        // 6. Check if data is very old
        if ($pegawai->fetched_at !== null) {
            $daysSinceFetch = $pegawai->fetched_at->diffInDays(now());
            if ($daysSinceFetch > 90) {
                $reasons[] = [
                    'code' => 'data_lama',
                    'label' => "Data terakhir diperbarui {$daysSinceFetch} hari lalu",
                    'description' => 'Data sudah lama tidak diperbarui, kemungkinan sudah tidak relevan.',
                    'severity' => 'rendah',
                ];
            }
        }

        // 7. Check NIP appointment year for anomaly
        $appointmentYear = $this->extractAppointmentYearFromNip($nip);
        if ($appointmentYear !== null && $appointmentYear < 1990) {
            $reasons[] = [
                'code' => 'pengangkatan_lama',
                'label' => "Pengangkatan tahun {$appointmentYear}",
                'description' => "NIP menunjukkan tahun pengangkatan {$appointmentYear}, sangat mungkin sudah pensiun.",
                'severity' => 'tinggi',
            ];
        }

        // 8. If no specific reason found
        if ($reasons === []) {
            $reasons[] = [
                'code' => 'tidak_ditemukan_di_excel',
                'label' => 'Tidak ditemukan di Excel SIASN terbaru',
                'description' => 'Pegawai ada di database tapi tidak ditemukan di file Excel SIASN terbaru. Perlu verifikasi manual.',
                'severity' => 'rendah',
            ];
        }

        return $reasons;
    }

    /**
     * Extract birth year from NIP (NIP baru format: YYYYMMDD YYYYMM X XXXXX)
     * First 8 digits = tanggal lahir (YYYYMMDD), next 6 = TMT CPNS (YYYYMM)
     */
    private function extractBirthYearFromNip(string $nip): ?int
    {
        $nip = preg_replace('/\D+/', '', $nip);
        if (strlen($nip) < 8) {
            return null;
        }

        $year = (int) substr($nip, 0, 4);
        if ($year >= 1940 && $year <= 2010) {
            return $year;
        }

        return null;
    }

    /**
     * Extract appointment year from NIP (digits 9-12 = YYYY of TMT CPNS)
     */
    private function extractAppointmentYearFromNip(string $nip): ?int
    {
        $nip = preg_replace('/\D+/', '', $nip);
        if (strlen($nip) < 14) {
            return null;
        }

        $year = (int) substr($nip, 8, 4);
        if ($year >= 1960 && $year <= 2030) {
            return $year;
        }

        return null;
    }

    private function severityFromReasons(array $reasons): string
    {
        foreach ($reasons as $reason) {
            if ($reason['severity'] === 'tinggi') {
                return 'tinggi';
            }
        }
        foreach ($reasons as $reason) {
            if ($reason['severity'] === 'sedang') {
                return 'sedang';
            }
        }

        return 'rendah';
    }

    private function countByPrimaryReason(Collection $anomalies): array
    {
        return $anomalies
            ->groupBy('primary_reason')
            ->map(fn (Collection $group) => $group->count())
            ->sortDesc()
            ->all();
    }

    private function normalizeNip(?string $nip): string
    {
        return preg_replace('/\D+/', '', (string) $nip) ?? '';
    }

    /**
     * Find the latest .xlsx file in datapegawai folder.
     */
    private function findLatestExcelFile(): ?string
    {
        $folder = base_path('datapegawai');

        if (! is_dir($folder)) {
            return null;
        }

        $files = glob($folder . '/*.xlsx');
        if ($files === false || $files === []) {
            return null;
        }

        // Sort by modification time, newest first
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    /**
     * Read all NIPs from an Excel file.
     */
    private function readNipsFromExcel(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak bisa dibuka: ' . $path);
        }

        $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $relationshipMap = [];

        foreach ($relationships->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            $relationshipMap[(string) $attributes['Id']] = 'xl/' . ltrim((string) $attributes['Target'], '/');
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

        // Find NIP column from header row
        $header = [];
        foreach ($rows[1] ?? [] as $column => $value) {
            $key = $this->headerKey($value);
            if ($key !== '') {
                $header[$key] = $column;
            }
        }

        $nipColumn = $header[$this->headerKey('NIP BARU')] ?? null;
        if ($nipColumn === null) {
            foreach (['NIP', 'NIP_BARU', 'NIPBARU'] as $alt) {
                $nipColumn = $header[$this->headerKey($alt)] ?? null;
                if ($nipColumn !== null) {
                    break;
                }
            }
        }

        if ($nipColumn === null) {
            return [];
        }

        $nips = [];
        foreach ($rows as $rowNumber => $cells) {
            if ((int) $rowNumber === 1) {
                continue;
            }

            $rawNip = $this->normalizeText((string) ($cells[$nipColumn] ?? ''));
            $nip = preg_replace('/\D+/', '', $rawNip) ?? '';

            if (strlen($nip) >= 15) {
                $nips[] = [
                    'nip' => $nip,
                    'row' => $rowNumber,
                ];
            }
        }

        return $nips;
    }

    // ── Excel parsing helpers (mirrored from SiasnPegawaiExcelImportService) ──

    private function headerKey(string $value): string
    {
        return Str::of($value)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/u', '')
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
            throw new RuntimeException('Struktur Excel tidak lengkap: ' . $name);
        }

        $loaded = simplexml_load_string($xml);
        if (! $loaded instanceof \SimpleXMLElement) {
            throw new RuntimeException('XML Excel tidak valid: ' . $name);
        }

        return $loaded;
    }
}
