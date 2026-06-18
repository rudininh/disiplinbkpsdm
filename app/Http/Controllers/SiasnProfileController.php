<?php

namespace App\Http\Controllers;

use App\Services\SiasnEducationLocationSyncService;
use App\Services\SiasnProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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

    private function siasnView(Request $request, ?array $result = null): View
    {
        return view('absensi-cms.siasn', [
            'result' => $result ?? $request->session()->get('siasn_result'),
        ]);
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
