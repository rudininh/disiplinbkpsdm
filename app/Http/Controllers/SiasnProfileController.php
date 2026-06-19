<?php

namespace App\Http\Controllers;

use App\Models\SiasnAbsensiLocationEmployee;
use App\Services\SiasnEducationLocationSyncService;
use App\Services\SiasnProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

class SiasnProfileController extends Controller
{
    private const EDUCATION_UNIT_REFERENCE_PATH = 'resources/data/siasn-unit-kerja-dikdas-banjarmasin.json';

    public function __construct(
        private readonly SiasnProfileService $service,
        private readonly SiasnEducationLocationSyncService $educationSync
    )
    {
    }

    public function index(Request $request): View
    {
        $this->forgetExpiredSiasnToken($request);

        return $this->siasnView($request);
    }

    public function fetch(Request $request): View
    {
        $data = $request->validate([
            'nip' => ['required', 'digits:18'],
            'bearer_token' => ['required', 'string'],
        ]);

        try {
            $result = $this->service->fetchAndStore($data['nip'], $data['bearer_token']);
        } catch (\Throwable $exception) {
            $result = [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        return $this->siasnView($request, $result);
    }

    public function testLogin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nip' => ['nullable', 'digits:18'],
            'bearer_token' => ['required', 'string'],
        ]);

        try {
            $result = $this->service->testAccess(
                $data['bearer_token'],
                $data['nip'] ?? null
            );

            if ($result['success'] ?? false) {
                $this->storeSiasnToken($request, $data['bearer_token']);
            }
        } catch (\Throwable $exception) {
            $result = [
                'success' => false,
                'message' => 'Tes login SIASN gagal: ' . $exception->getMessage(),
            ];
        }

        return redirect()
            ->route('cms.siasn.index')
            ->with('siasn_result', $result)
            ->withInput($request->only('nip'));
    }

    public function forgetToken(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'siasn_token',
            'siasn_token_expires_at',
            'siasn_token_expires_at_text',
            'siasn_token_identity',
            'siasn_result',
        ]);

        return redirect()
            ->route('cms.siasn.index')
            ->with('siasn_result', [
                'success' => true,
                'message' => 'Token SIASN tersimpan sudah dihapus.',
            ]);
    }

    public function syncEducationLocations(Request $request): View
    {
        $data = $request->validate([
            'bearer_token' => ['required', 'string'],
            'pegawai_limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'school_only' => ['nullable', 'boolean'],
        ]);

        $credentials = $this->absensiCredentials();
        if ($credentials === null) {
            $result = [
                'success' => false,
                'message' => 'ABSENSI_USERNAME dan ABSENSI_PASSWORD belum diatur di .env.',
            ];
        } else {
            try {
                $result = $this->educationSync->sync(
                    $credentials['username'],
                    $credentials['password'],
                    $data['bearer_token'],
                    (int) ($data['pegawai_limit'] ?? 20),
                    $request->boolean('school_only', true)
                );
            } catch (\Throwable $exception) {
                $result = [
                    'success' => false,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $this->siasnView($request, $result);
    }

    public function syncAbsensiReferenceEmployees(Request $request): RedirectResponse
    {
        $credentials = $this->absensiCredentials();
        if ($credentials === null) {
            $result = [
                'success' => false,
                'message' => 'ABSENSI_USERNAME dan ABSENSI_PASSWORD belum diatur di .env.',
            ];
        } else {
            try {
                $reference = $this->educationUnitReference();
                $result = $this->educationSync->syncReferenceUnitEmployees(
                    $credentials['username'],
                    $credentials['password'],
                    $reference['rows']
                );
            } catch (\Throwable $exception) {
                $result = [
                    'success' => false,
                    'message' => 'Sinkron pegawai absensi gagal: ' . $exception->getMessage(),
                ];
            }
        }

        return redirect()
            ->route('cms.siasn.index')
            ->with('siasn_result', $result);
    }

    public function syncAbsensiEmployeeSiasn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lokasi_id' => ['required', 'string', 'max:64'],
            'nip' => ['required', 'digits:18'],
        ]);

        $storedToken = $this->storedSiasnToken($request);
        if ($storedToken === null) {
            return redirect()
                ->route('cms.siasn.index')
                ->with('siasn_result', [
                    'success' => false,
                    'message' => 'Token SIASN belum tersimpan atau sudah kedaluwarsa. Jalankan Tes Login SIASN terlebih dahulu.',
                ]);
        }

        $employee = SiasnAbsensiLocationEmployee::query()
            ->where('lokasi_id', $data['lokasi_id'])
            ->where('nip', $data['nip'])
            ->first();

        if ($employee === null) {
            return redirect()
                ->route('cms.siasn.index')
                ->with('siasn_result', [
                    'success' => false,
                    'message' => 'Pegawai absensi tidak ditemukan untuk sinkron SIASN.',
            ]);
        }

        $sync = $this->syncAbsensiEmployeeWithSiasn($employee, $storedToken['token']);
        $result = [
            'success' => $sync['success'],
            'message' => $sync['message'],
        ];

        return redirect()
            ->route('cms.siasn.index')
            ->with('siasn_result', $result);
    }

    public function syncAllAbsensiEmployeesSiasn(Request $request): RedirectResponse
    {
        $storedToken = $this->storedSiasnToken($request);
        if ($storedToken === null) {
            return redirect()
                ->route('cms.siasn.index')
                ->with('siasn_result', [
                    'success' => false,
                    'message' => 'Token SIASN belum tersimpan atau sudah kedaluwarsa. Jalankan Tes Login SIASN terlebih dahulu.',
                ]);
        }

        $employees = SiasnAbsensiLocationEmployee::query()
            ->where('skpd_id', 1)
            ->where('match_status', 'lokasi_absensi_cocok')
            ->whereNotNull('nip')
            ->orderBy('lokasi_nama')
            ->orderBy('nama')
            ->get();

        if ($employees->isEmpty()) {
            return redirect()
                ->route('cms.siasn.index')
                ->with('siasn_result', [
                    'success' => false,
                    'message' => 'Belum ada pegawai absensi yang bisa dicek ke SIASN. Jalankan sinkron pegawai absensi terlebih dahulu.',
                ]);
        }

        $checked = 0;
        $success = 0;
        $notFound = 0;
        $failed = 0;
        $skipped = 0;
        $failedExamples = [];

        foreach ($employees as $employee) {
            $nip = preg_replace('/\D+/', '', (string) $employee->nip) ?? '';
            if (strlen($nip) !== 18) {
                $skipped++;
                continue;
            }

            $checked++;
            $sync = $this->syncAbsensiEmployeeWithSiasn($employee, $storedToken['token']);
            if ($sync['not_found']) {
                $notFound++;
            } elseif ($sync['success']) {
                $success++;
            } else {
                $failed++;
                if (count($failedExamples) < 3) {
                    $failedExamples[] = ($employee->nama ?: $employee->nip) . ': ' . $sync['error'];
                }
            }
        }

        $message = 'Cek SIASN semua pegawai selesai. '
            . $success . ' aktif tersinkron, '
            . $notFound . ' ditandai PENSIUN/MUTASI, '
            . $failed . ' gagal';

        if ($skipped > 0) {
            $message .= ', ' . $skipped . ' dilewati karena NIP tidak valid';
        }

        $message .= ' dari ' . $checked . ' pegawai dicek.';

        if ($failedExamples !== []) {
            $message .= ' Contoh gagal: ' . implode('; ', $failedExamples) . '.';
        }

        return redirect()
            ->route('cms.siasn.index')
            ->with('siasn_result', [
                'success' => $failed === 0,
                'message' => $message,
            ]);
    }

    private function siasnView(Request $request, ?array $result = null): View
    {
        $educationUnitReference = $this->educationUnitReference();
        $educationUnits = $this->withAbsensiEmployees($educationUnitReference['rows']);

        return view('absensi-cms.siasn', [
            'result' => $result ?? $request->session()->get('siasn_result'),
            'storedToken' => $this->storedSiasnToken($request),
            'educationUnits' => $educationUnits,
            'educationUnitSummary' => $educationUnitReference['summary'],
            'educationUnitSource' => $educationUnitReference['source'],
            'educationEmployeeSummary' => $this->educationEmployeeSummary($educationUnits),
        ]);
    }

    private function educationUnitReference(): array
    {
        $defaultSource = [
            'name' => 'Referensi Data Kemendikdasmen',
            'url' => 'https://referensi.data.kemendikdasmen.go.id/pendidikan/dikdas/156000/2/jf/all/s1',
            'scope' => 'Kota Banjarmasin, jalur formal, DIKDAS negeri',
            'captured_at' => null,
        ];

        $path = base_path(self::EDUCATION_UNIT_REFERENCE_PATH);
        if (! is_file($path)) {
            return [
                'source' => $defaultSource,
                'summary' => $this->educationUnitSummary([]),
                'rows' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return [
                'source' => $defaultSource,
                'summary' => $this->educationUnitSummary([]),
                'rows' => [],
            ];
        }

        $rows = array_values(array_filter($decoded['rows'] ?? [], 'is_array'));
        $source = is_array($decoded['source'] ?? null)
            ? array_merge($defaultSource, $decoded['source'])
            : $defaultSource;

        return [
            'source' => $source,
            'summary' => $this->educationUnitSummary($rows),
            'rows' => $rows,
        ];
    }

    private function educationUnitSummary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'tk' => 0,
            'sd_sederajat' => 0,
            'smp_sederajat' => 0,
            'sd' => 0,
            'mi' => 0,
            'smp' => 0,
            'mts' => 0,
        ];

        foreach ($rows as $row) {
            if (($row['jenjang'] ?? null) === 'TK' || ($row['bentuk'] ?? null) === 'TK') {
                $summary['tk']++;
            }

            if (($row['jenjang'] ?? null) === 'SD Sederajat') {
                $summary['sd_sederajat']++;
            }

            if (($row['jenjang'] ?? null) === 'SMP Sederajat') {
                $summary['smp_sederajat']++;
            }

            match ($row['bentuk'] ?? null) {
                'TK' => null,
                'SD' => $summary['sd']++,
                'MI' => $summary['mi']++,
                'SMP' => $summary['smp']++,
                'MTs' => $summary['mts']++,
                default => null,
            };
        }

        return $summary;
    }

    private function withAbsensiEmployees(array $rows): array
    {
        if (! Schema::hasTable('siasn_absensi_location_employees')) {
            foreach ($rows as &$row) {
                $row['absensi_employees'] = [];
                $row['absensi_employee_count'] = 0;
                $row['absensi_lokasi_nama'] = null;
                $row['absensi_lokasi_id'] = null;
            }
            unset($row);

            return $rows;
        }

        $employeeRows = SiasnAbsensiLocationEmployee::query()
            ->with('siasnProfile')
            ->where('skpd_id', 1)
            ->where('match_status', 'lokasi_absensi_cocok')
            ->latest('fetched_at')
            ->get();

        $employeesByNpsn = $employeeRows
            ->groupBy(fn (SiasnAbsensiLocationEmployee $employee): string => (string) data_get($employee->row_data, 'referensi_npsn'));

        foreach ($rows as &$row) {
            $employees = $employeesByNpsn
                ->get((string) ($row['npsn'] ?? ''), collect())
                ->unique(fn (SiasnAbsensiLocationEmployee $employee): string => (string) $employee->nip)
                ->sortBy('nama')
                ->values()
                ->map(fn (SiasnAbsensiLocationEmployee $employee): array => [
                    'nama' => (string) ($employee->nama ?? ''),
                    'nip' => (string) $employee->nip,
                    'lokasi_id' => (string) $employee->lokasi_id,
                    'lokasi_nama' => (string) $employee->lokasi_nama,
                    'siasn_unit_organisasi' => (string) ($employee->siasn_unit_organisasi ?? ''),
                    'siasn_jabatan' => (string) ($employee->siasn_jabatan ?? ''),
                    'status_asn' => $this->employeeAsnStatus($employee),
                    'fetched_at' => $employee->fetched_at?->format('Y-m-d H:i:s'),
                ])
                ->all();

            $row['absensi_employees'] = $employees;
            $row['absensi_employee_count'] = count($employees);
            $row['absensi_lokasi_nama'] = $employees[0]['lokasi_nama'] ?? null;
            $row['absensi_lokasi_id'] = $employees[0]['lokasi_id'] ?? null;
            $row['siasn_unit_organisasi'] = collect($employees)
                ->pluck('siasn_unit_organisasi')
                ->filter()
                ->unique()
                ->values()
                ->implode(', ');
        }
        unset($row);

        return $rows;
    }

    private function employeeAsnStatus(SiasnAbsensiLocationEmployee $employee): string
    {
        $jenisAsn = strtoupper(trim((string) ($employee->siasnProfile?->jenis_asn ?? '')));
        if (in_array($jenisAsn, ['PNS', 'PPPK'], true)) {
            return $jenisAsn;
        }

        $siasnStatus = strtoupper(trim((string) data_get($employee->row_data, 'siasn_status')));
        if ($siasnStatus === 'PENSIUN/MUTASI') {
            return 'PENSIUN/MUTASI';
        }

        return '';
    }

    private function syncAbsensiEmployeeWithSiasn(SiasnAbsensiLocationEmployee $employee, string $token): array
    {
        $nip = preg_replace('/\D+/', '', (string) $employee->nip) ?? '';

        try {
            $siasn = $this->service->fetchAndStore($nip, $token);
            $profile = $siasn['profile'];
            $rowData = is_array($employee->row_data) ? $employee->row_data : [];
            unset($rowData['siasn_status'], $rowData['siasn_error']);

            $employee->update([
                'siasn_pns_profile_id' => $profile->id,
                'siasn_unit_organisasi' => $profile->unit_organisasi,
                'siasn_jabatan' => $profile->jabatan,
                'row_data' => $rowData,
                'fetched_at' => now(),
            ]);

            return [
                'success' => true,
                'not_found' => false,
                'error' => null,
                'message' => 'Sinkron SIASN berhasil untuk ' . ($employee->nama ?: $nip) . '.',
            ];
        } catch (\Throwable $exception) {
            $rowData = is_array($employee->row_data) ? $employee->row_data : [];
            $notFound = str_contains($exception->getMessage(), 'Data PNS/PPPK tidak ditemukan');
            $rowData['siasn_status'] = $notFound ? 'PENSIUN/MUTASI' : null;
            $rowData['siasn_error'] = $exception->getMessage();

            $update = [
                'row_data' => array_filter($rowData, static fn ($value) => $value !== null),
                'fetched_at' => now(),
            ];

            if ($notFound) {
                $update['siasn_pns_profile_id'] = null;
                $update['siasn_unit_organisasi'] = null;
                $update['siasn_jabatan'] = null;
            }

            $employee->update($update);

            return [
                'success' => $notFound,
                'not_found' => $notFound,
                'error' => $exception->getMessage(),
                'message' => $notFound
                    ? 'Data SIASN tidak ditemukan untuk ' . ($employee->nama ?: $nip) . '; status ditandai PENSIUN/MUTASI.'
                    : 'Sinkron SIASN gagal untuk ' . ($employee->nama ?: $nip) . ': ' . $exception->getMessage(),
            ];
        }
    }

    private function educationEmployeeSummary(array $rows): array
    {
        $matchedUnits = 0;
        $employeeCount = 0;

        foreach ($rows as $row) {
            $count = (int) ($row['absensi_employee_count'] ?? 0);
            if ($count > 0) {
                $matchedUnits++;
                $employeeCount += $count;
            }
        }

        return [
            'matched_units' => $matchedUnits,
            'employee_count' => $employeeCount,
        ];
    }

    private function storeSiasnToken(Request $request, string $bearerToken): void
    {
        $info = $this->service->tokenInfo($bearerToken);
        $identity = $info['nama'] ?: ($info['nip'] ? 'NIP ' . $info['nip'] : 'pengguna SIASN');

        $request->session()->put([
            'siasn_token' => $info['token'],
            'siasn_token_expires_at' => $info['expires_at'],
            'siasn_token_expires_at_text' => $info['expires_at_text'],
            'siasn_token_identity' => $identity,
        ]);
    }

    private function storedSiasnToken(Request $request): ?array
    {
        $token = (string) $request->session()->get('siasn_token', '');
        if ($token === '') {
            return null;
        }

        $expiresAt = $request->session()->get('siasn_token_expires_at');
        if ($expiresAt !== null && (int) $expiresAt <= now()->timestamp) {
            $this->forgetExpiredSiasnToken($request);

            return null;
        }

        return [
            'token' => $token,
            'expires_at_text' => $request->session()->get('siasn_token_expires_at_text'),
            'identity' => $request->session()->get('siasn_token_identity', 'pengguna SIASN'),
        ];
    }

    private function forgetExpiredSiasnToken(Request $request): void
    {
        $expiresAt = $request->session()->get('siasn_token_expires_at');
        if ($expiresAt !== null && (int) $expiresAt <= now()->timestamp) {
            $request->session()->forget([
                'siasn_token',
                'siasn_token_expires_at',
                'siasn_token_expires_at_text',
                'siasn_token_identity',
            ]);
        }
    }

    private function absensiCredentials(): ?array
    {
        $username = trim((string) config('services.absensi.username'));
        $password = (string) config('services.absensi.password');

        if ($username === '' || $password === '') {
            return null;
        }

        return [
            'username' => $username,
            'password' => $password,
        ];
    }
}
