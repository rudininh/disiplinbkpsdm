<?php

namespace App\Http\Controllers;

use App\Services\AbsensiScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AbsensiScraperController extends Controller
{
    public function __construct(private readonly AbsensiScraperService $scraper)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'name' => 'Absensi Cuti Scraper',
            'status' => 'ready',
            'endpoints' => [
                'GET /absensi-scraper',
                'POST /absensi-scraper/login { username, password, skpd_id? }',
                'POST /absensi-scraper/cuti { username?, password?, skpd_id?, redact? }',
            ],
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'skpd_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->scraper->login($data['username'], $data['password'], (int) ($data['skpd_id'] ?? 1))
        );
    }

    public function cuti(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'skpd_id' => ['nullable', 'integer', 'min:1'],
            'redact' => ['nullable', 'boolean'],
        ]);

        $redact = $request->boolean('redact', true);
        $skpdId = (int) ($data['skpd_id'] ?? 1);

        if (! empty($data['username']) && ! empty($data['password'])) {
            return response()->json(
                $this->scraper->scrapeCuti($data['username'], $data['password'], $redact, $skpdId)
            );
        }

        return response()->json(
            $this->scraper->getCutiData($redact, $skpdId)
        );
    }
}
