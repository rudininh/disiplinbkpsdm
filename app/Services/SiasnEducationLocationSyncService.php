<?php

namespace App\Services;

use App\Models\SiasnAbsensiLocationEmployee;
use Illuminate\Support\Str;

class SiasnEducationLocationSyncService
{
    private const DINAS_PENDIDIKAN_SKPD_ID = 1;

    public function __construct(
        private readonly AbsensiScraperService $absensiScraper,
        private readonly SiasnProfileService $siasnProfileService
    ) {
    }

    public function sync(
        string $absensiUsername,
        string $absensiPassword,
        string $siasnToken,
        ?int $pegawaiLimit = 20,
        bool $schoolOnly = true
    ): array
    {
        $pegawaiLimit = $pegawaiLimit !== null ? max(1, $pegawaiLimit) : null;
        $scraped = $this->absensiScraper->scrapeLokasiPegawai(
            $absensiUsername,
            $absensiPassword,
            self::DINAS_PENDIDIKAN_SKPD_ID,
            $pegawaiLimit,
            $schoolOnly ? fn (array $location): bool => $this->isLikelySchoolLocation((string) ($location['nama'] ?? '')) : null
        );

        if (! ($scraped['success'] ?? false)) {
            return $scraped;
        }

        $skpd = config('services.absensi.skpd.' . self::DINAS_PENDIDIKAN_SKPD_ID, []);
        $stored = 0;
        $siasnSuccess = 0;
        $siasnFailed = 0;
        $locationMatches = 0;
        $locationMismatches = 0;
        $results = [];
        $activeKeys = [];

        foreach (($scraped['employees']['rows'] ?? []) as $employee) {
            $nip = preg_replace('/\D+/', '', (string) ($employee['nip'] ?? '')) ?? '';
            $lokasiId = (string) ($employee['lokasi_id'] ?? '');
            if ($nip === '') {
                continue;
            }

            $activeKeys[$lokasiId . '|' . $nip] = true;

            $row = SiasnAbsensiLocationEmployee::query()->updateOrCreate(
                [
                    'lokasi_id' => $lokasiId,
                    'nip' => $nip,
                ],
                [
                    'skpd_id' => self::DINAS_PENDIDIKAN_SKPD_ID,
                    'kode_skpd' => $skpd['kode'] ?? null,
                    'nama_skpd' => $skpd['nama'] ?? null,
                    'lokasi_nama' => $employee['lokasi_nama'] ?: null,
                    'lokasi_alamat' => $employee['lokasi_alamat'] ?: null,
                    'lokasi_lat' => $employee['lokasi_lat'] ?: null,
                    'lokasi_long' => $employee['lokasi_long'] ?: null,
                    'nama' => $employee['nama'] ?: null,
                    'row_data' => $employee,
                    'fetched_at' => now(),
                ]
            );
            $stored++;

            try {
                $siasn = $this->siasnProfileService->fetchAndStore($nip, $siasnToken);
                $profile = $siasn['profile'];
                $matchStatus = $this->matchStatus((string) $row->lokasi_nama, (string) ($profile->unit_organisasi ?? ''));

                $row->update([
                    'siasn_pns_profile_id' => $profile->id,
                    'siasn_unit_organisasi' => $profile->unit_organisasi,
                    'siasn_jabatan' => $profile->jabatan,
                    'match_status' => $matchStatus,
                ]);

                $siasnSuccess++;
                $matchStatus === 'unit_cocok' ? $locationMatches++ : $locationMismatches++;
                $results[] = [
                    'success' => true,
                    'nip' => $nip,
                    'nama' => $profile->nama ?? $row->nama,
                    'lokasi_absensi' => $row->lokasi_nama,
                    'unit_organisasi' => $profile->unit_organisasi,
                    'match_status' => $matchStatus,
                ];
            } catch (\Throwable $exception) {
                $siasnFailed++;
                $rowData = is_array($row->row_data) ? $row->row_data : [];
                $rowData['siasn_error'] = $exception->getMessage();
                $row->update([
                    'match_status' => 'siasn_gagal',
                    'row_data' => $rowData,
                ]);
                $results[] = [
                    'success' => false,
                    'nip' => $nip,
                    'nama' => $row->nama,
                    'lokasi_absensi' => $row->lokasi_nama,
                    'message' => $exception->getMessage(),
                    'match_status' => 'siasn_gagal',
                ];
            }
        }

        $inactiveRows = $pegawaiLimit === null
            ? $this->markMissingEmployeesInactive(
                self::DINAS_PENDIDIKAN_SKPD_ID,
                $this->visitedLocationIds($scraped),
                $activeKeys
            )
            : 0;

        return [
            'success' => $siasnSuccess > 0 && $siasnFailed === 0,
            'partial_success' => $siasnSuccess > 0 && $siasnFailed > 0,
            'message' => 'Sinkron lokasi Dinas Pendidikan selesai.',
            'summary' => [
                'lokasi_count' => $scraped['locations']['row_count'] ?? 0,
                'pegawai_scraped' => count($scraped['employees']['rows'] ?? []),
                'stored_rows' => $stored,
                'siasn_success' => $siasnSuccess,
                'siasn_failed' => $siasnFailed,
                'location_matches' => $locationMatches,
                'location_mismatches' => $locationMismatches,
                'inactive_rows' => $inactiveRows,
            ],
            'results' => $results,
        ];
    }

    public function syncReferenceUnitEmployees(string $absensiUsername, string $absensiPassword, array $educationUnits): array
    {
        $referenceLookup = $this->referenceLookup($educationUnits);
        if ($referenceLookup === []) {
            return [
                'success' => false,
                'message' => 'Data referensi unit kerja belum tersedia.',
            ];
        }

        $matchedLocationNames = [];
        $scraped = $this->absensiScraper->scrapeLokasiPegawai(
            $absensiUsername,
            $absensiPassword,
            self::DINAS_PENDIDIKAN_SKPD_ID,
            null,
            function (array $location) use ($referenceLookup, &$matchedLocationNames): bool {
                $matched = $this->matchReferenceUnit((string) ($location['nama'] ?? ''), $referenceLookup);
                if ($matched === null) {
                    return false;
                }

                $matchedLocationNames[(string) ($location['lokasi_id'] ?? '')] = $matched;

                return true;
            }
        );

        if (! ($scraped['success'] ?? false)) {
            return $scraped;
        }

        $skpd = config('services.absensi.skpd.' . self::DINAS_PENDIDIKAN_SKPD_ID, []);
        $stored = 0;
        $matchedLocations = [];
        $results = [];
        $activeKeys = [];

        foreach (($scraped['employees']['rows'] ?? []) as $employee) {
            $nip = preg_replace('/\D+/', '', (string) ($employee['nip'] ?? '')) ?? '';
            $lokasiId = (string) ($employee['lokasi_id'] ?? '');
            $lokasiNama = (string) ($employee['lokasi_nama'] ?? '');
            $matchedUnit = $matchedLocationNames[$lokasiId] ?? $this->matchReferenceUnit($lokasiNama, $referenceLookup);
            if (is_string($matchedUnit)) {
                $matchedUnit = $referenceLookup[$this->normalizeUnitName($matchedUnit)] ?? null;
            }

            if ($nip === '' || $matchedUnit === null) {
                continue;
            }

            $activeKeys[$lokasiId . '|' . $nip] = true;

            SiasnAbsensiLocationEmployee::query()->updateOrCreate(
                [
                    'lokasi_id' => $lokasiId,
                    'nip' => $nip,
                ],
                [
                    'skpd_id' => self::DINAS_PENDIDIKAN_SKPD_ID,
                    'kode_skpd' => $skpd['kode'] ?? null,
                    'nama_skpd' => $skpd['nama'] ?? null,
                    'lokasi_nama' => $lokasiNama ?: null,
                    'lokasi_alamat' => ($employee['lokasi_alamat'] ?? null) ?: null,
                    'lokasi_lat' => ($employee['lokasi_lat'] ?? null) ?: null,
                    'lokasi_long' => ($employee['lokasi_long'] ?? null) ?: null,
                    'nama' => ($employee['nama'] ?? null) ?: null,
                    'match_status' => 'lokasi_absensi_cocok',
                    'row_data' => array_merge($employee, [
                        'referensi_npsn' => $matchedUnit['npsn'] ?? null,
                        'referensi_unit_kerja' => $matchedUnit['unit_kerja'] ?? null,
                    ]),
                    'fetched_at' => now(),
                ]
            );

            $stored++;
            $matchedLocations[$lokasiId] = $matchedUnit['unit_kerja'] ?? $lokasiNama;
            $results[] = [
                'nip' => $nip,
                'nama' => $employee['nama'] ?? null,
                'lokasi_absensi' => $lokasiNama,
                'unit_kerja' => $matchedUnit['unit_kerja'] ?? null,
            ];
        }

        $inactiveRows = $this->markMissingEmployeesInactive(
            self::DINAS_PENDIDIKAN_SKPD_ID,
            $this->visitedLocationIds($scraped),
            $activeKeys
        );

        return [
            'success' => true,
            'message' => 'Sinkron pegawai lokasi absensi selesai.',
            'summary' => [
                'lokasi_absensi' => $scraped['locations']['row_count'] ?? 0,
                'lokasi_cocok' => count($matchedLocations),
                'pegawai_scraped' => count($scraped['employees']['rows'] ?? []),
                'stored_rows' => $stored,
                'inactive_rows' => $inactiveRows,
            ],
            'results' => $results,
        ];
    }

    private function visitedLocationIds(array $scraped): array
    {
        return collect($scraped['visited_locations']['rows'] ?? [])
            ->pluck('lokasi_id')
            ->map(fn ($value) => (string) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function markMissingEmployeesInactive(int $skpdId, array $locationIds, array $activeKeys): int
    {
        if ($locationIds === []) {
            return 0;
        }

        $inactiveRows = 0;
        $now = now();

        SiasnAbsensiLocationEmployee::query()
            ->where('skpd_id', $skpdId)
            ->whereIn('lokasi_id', $locationIds)
            ->get()
            ->each(function (SiasnAbsensiLocationEmployee $row) use ($activeKeys, $now, &$inactiveRows): void {
                $nip = preg_replace('/\D+/', '', (string) ($row->nip ?? '')) ?? '';
                $key = (string) $row->lokasi_id . '|' . $nip;

                if ($nip === '' || isset($activeKeys[$key])) {
                    return;
                }

                $rowData = is_array($row->row_data) ? $row->row_data : [];
                $rowData['inactive_marked_at'] = $now->toDateTimeString();

                $row->update([
                    'siasn_pns_profile_id' => null,
                    'siasn_unit_organisasi' => null,
                    'siasn_jabatan' => null,
                    'match_status' => 'tidak_aktif_absensi',
                    'row_data' => $rowData,
                    'fetched_at' => $now,
                ]);

                $inactiveRows++;
            });

        return $inactiveRows;
    }

    private function matchStatus(string $lokasiAbsensi, string $unitOrganisasi): string
    {
        $lokasi = $this->normalizeUnitName($lokasiAbsensi);
        $unit = $this->normalizeUnitName($unitOrganisasi);

        if ($unit === '') {
            return 'unit_siasn_kosong';
        }

        if ($lokasi !== '' && ($lokasi === $unit || str_contains($unit, $lokasi) || str_contains($lokasi, $unit))) {
            return 'unit_cocok';
        }

        return 'lokasi_bukan_unit';
    }

    private function isLikelySchoolLocation(string $value): bool
    {
        $value = $this->normalizeUnitName($value);

        return preg_match('/\b(sd|smp|tk|paud|skb|sanggar)\b/u', $value) === 1
            || str_contains($value, 'sekolah')
            || str_contains($value, 'negeri')
            || str_contains($value, 'pembina');
    }

    private function normalizeUnitName(string $value): string
    {
        $normalized = Str::of($value)
            ->lower()
            ->replaceMatches('/\bkota\s+banjarmasin\b/u', '')
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

    private function referenceLookup(array $educationUnits): array
    {
        $lookup = [];

        foreach ($educationUnits as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $key = $this->normalizeUnitName((string) ($unit['unit_kerja'] ?? ''));
            if ($key === '') {
                continue;
            }

            $lookup[$key] = $unit;
        }

        return $lookup;
    }

    private function matchReferenceUnit(string $locationName, array $referenceLookup): ?array
    {
        $key = $this->normalizeUnitName($locationName);
        if ($key === '') {
            return null;
        }

        return $referenceLookup[$key] ?? null;
    }
}
