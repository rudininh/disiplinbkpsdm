<?php

namespace App\Http\Controllers;

use App\Models\SiasnPnsProfile;
use App\Models\SiasnAbsensiLocationEmployee;
use App\Services\SiasnEducationLocationSyncService;
use App\Services\SiasnProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SiasnProfileController extends Controller
{
    public function __construct(
        private readonly SiasnProfileService $service,
        private readonly SiasnEducationLocationSyncService $educationSync
    )
    {
    }

    public function index(Request $request): View
    {
        return view('absensi-cms.siasn', [
            'profiles' => $this->profiles($request),
            'totalRows' => SiasnPnsProfile::query()->count(),
            'lastFetchedAt' => SiasnPnsProfile::query()->max('fetched_at'),
            'educationRows' => $this->educationRows(),
            'educationSummary' => $this->educationSummary(),
            'result' => null,
            'search' => (string) $request->input('q', ''),
        ]);
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

        return view('absensi-cms.siasn', [
            'profiles' => $this->profiles($request),
            'totalRows' => SiasnPnsProfile::query()->count(),
            'lastFetchedAt' => SiasnPnsProfile::query()->max('fetched_at'),
            'educationRows' => $this->educationRows(),
            'educationSummary' => $this->educationSummary(),
            'result' => $result,
            'search' => (string) $request->input('q', ''),
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

        return view('absensi-cms.siasn', [
            'profiles' => $this->profiles($request),
            'totalRows' => SiasnPnsProfile::query()->count(),
            'lastFetchedAt' => SiasnPnsProfile::query()->max('fetched_at'),
            'educationRows' => $this->educationRows(),
            'educationSummary' => $this->educationSummary(),
            'result' => $result,
            'search' => (string) $request->input('q', ''),
        ]);
    }

    private function profiles(Request $request)
    {
        $query = SiasnPnsProfile::query()->latest('fetched_at')->latest('id');
        $search = trim((string) $request->input('q', ''));

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('nip', 'like', '%' . $search . '%')
                    ->orWhere('nama', 'like', '%' . $search . '%')
                    ->orWhere('jabatan', 'like', '%' . $search . '%')
                    ->orWhere('unit_organisasi', 'like', '%' . $search . '%')
                    ->orWhere('unit_organisasi_induk', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(50)->withQueryString();
    }

    private function educationRows()
    {
        return SiasnAbsensiLocationEmployee::query()
            ->latest('fetched_at')
            ->latest('id')
            ->limit(100)
            ->get();
    }

    private function educationSummary(): array
    {
        return [
            'total' => SiasnAbsensiLocationEmployee::query()->count(),
            'unit_cocok' => SiasnAbsensiLocationEmployee::query()->where('match_status', 'unit_cocok')->count(),
            'lokasi_bukan_unit' => SiasnAbsensiLocationEmployee::query()->where('match_status', 'lokasi_bukan_unit')->count(),
            'siasn_gagal' => SiasnAbsensiLocationEmployee::query()->where('match_status', 'siasn_gagal')->count(),
        ];
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
