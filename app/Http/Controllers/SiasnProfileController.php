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
            'sd_sederajat' => 0,
            'smp_sederajat' => 0,
            'sd' => 0,
            'mi' => 0,
            'smp' => 0,
            'mts' => 0,
        ];

        foreach ($rows as $row) {
            if (($row['jenjang'] ?? null) === 'SD Sederajat') {
                $summary['sd_sederajat']++;
            }

            if (($row['jenjang'] ?? null) === 'SMP Sederajat') {
                $summary['smp_sederajat']++;
            }

            match ($row['bentuk'] ?? null) {
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

        $employeesByNpsn = SiasnAbsensiLocationEmployee::query()
            ->where('skpd_id', 1)
            ->where('match_status', 'lokasi_absensi_cocok')
            ->latest('fetched_at')
            ->get()
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
