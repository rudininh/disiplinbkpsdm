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

        foreach (($scraped['employees']['rows'] ?? []) as $employee) {
            $nip = preg_replace('/\D+/', '', (string) ($employee['nip'] ?? '')) ?? '';
            if ($nip === '') {
                continue;
            }

            $row = SiasnAbsensiLocationEmployee::query()->updateOrCreate(
                [
                    'lokasi_id' => (string) ($employee['lokasi_id'] ?? ''),
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
            ],
            'results' => $results,
        ];
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
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->replaceMatches('/\b(sd|smp|tk)\s*n\b/u', '$1 negeri')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();
    }
}
