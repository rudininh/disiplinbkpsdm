<?php

namespace App\Services;

use App\Models\SiasnPnsProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SiasnProfileService
{
    private const BASE_URL = 'https://api-siasn.bkn.go.id';

    public function fetchAndStore(string $nip, string $bearerToken): array
    {
        $nip = preg_replace('/\D+/', '', $nip) ?? '';
        $token = $this->normalizeToken($bearerToken);

        if ($nip === '' || strlen($nip) !== 18) {
            throw new RuntimeException('NIP harus berisi 18 digit.');
        }

        if ($token === '') {
            throw new RuntimeException('Token SIASN belum diisi.');
        }

        [$summaryResponse, $summary, $jenisAsn] = $this->findSummary($nip, $token);

        $pnsId = $this->value($summary, 'id');
        if ($pnsId === null) {
            throw new RuntimeException('Data ASN ditemukan, tetapi id SIASN tidak tersedia.');
        }

        $orangResponse = $this->get('/profilasn/api/orang', [
            'id' => $pnsId,
        ], $token);

        $orang = $this->payloadArray($orangResponse);
        $merged = $this->mergeSiasnData($orang, $summary);
        $profile = SiasnPnsProfile::updateOrCreate(
            ['nip' => (string) ($this->value($merged, 'nip_baru') ?? $nip)],
            $this->profilePayload($merged, $summary, $orang, $summaryResponse, $jenisAsn)
        );

        return [
            'success' => true,
            'message' => 'Data jabatan dan unit kerja SIASN berhasil disimpan.',
            'profile' => $profile,
        ];
    }

    private function findSummary(string $nip, string $token): array
    {
        $attempts = [
            ['PNS', '/profilasn/api/pns-siasn'],
            ['PPPK', '/profilasn/api/pppk-siasn'],
        ];

        foreach ($attempts as [$jenisAsn, $path]) {
            $response = $this->get($path, ['nip_baru' => $nip], $token);
            $summary = $this->firstValue($response);
            if ($summary !== null) {
                return [$response, $summary, $jenisAsn];
            }
        }

        throw new RuntimeException('Data PNS/PPPK tidak ditemukan dari SIASN untuk NIP tersebut.');
    }

    private function get(string $path, array $query, string $token): array
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
            ->timeout(30)
            ->get(self::BASE_URL . $path, $query);

        if ($response->unauthorized() || $response->forbidden()) {
            throw new RuntimeException('Token SIASN ditolak atau sudah kedaluwarsa. Silakan login ulang lalu ambil token baru.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('SIASN mengembalikan error HTTP ' . $response->status() . '.');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Respons SIASN tidak berbentuk JSON yang valid.');
        }

        return $json;
    }

    private function firstValue(array $payload): ?array
    {
        $values = $payload['Value'] ?? $payload['value'] ?? $payload['data'] ?? null;
        if (is_array($values) && isset($values[0]) && is_array($values[0])) {
            return $values[0];
        }

        return null;
    }

    private function payloadArray(array $payload): array
    {
        foreach (['Value', 'value', 'data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return $payload;
    }

    private function mergeSiasnData(array $detail, array $summary): array
    {
        $merged = $detail;

        foreach ($summary as $key => $value) {
            $existing = $merged[$key] ?? null;
            if ($this->isFilled($value) || ! $this->isFilled($existing)) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private function profilePayload(array $merged, array $summary, array $orang, array $summaryResponse, string $jenisAsn): array
    {
        return [
            'pns_id' => $this->stringValue($this->value($merged, 'id')),
            'jenis_asn' => $jenisAsn,
            'nama' => $this->stringValue($this->value($merged, 'nama')),
            'jabatan' => $this->jabatan($merged),
            'jenis_jabatan' => $this->stringValue($this->nestedName($this->value($merged, 'jenis_jabatan_nama'))),
            'unit_organisasi' => $this->stringValue($this->value($merged, 'unor_nama')),
            'unit_organisasi_induk' => $this->stringValue($this->value($merged, 'unor_induk_nama')),
            'unor_id' => $this->stringValue($this->value($merged, 'unor_id')),
            'instansi_kerja' => $this->stringValue($this->value($merged, 'instansi_kerja_nama')),
            'satuan_kerja' => $this->stringValue($this->value($merged, 'satuan_kerja_kerja_nama')),
            'lokasi_kerja' => $this->stringValue($this->value($merged, 'lokasi_kerja_nama')),
            'tmt_jabatan' => $this->dateValue($this->value($merged, 'tmt_jabatan')),
            'raw_data' => [
                'jenis_asn' => $jenisAsn,
                'summary_response' => $summaryResponse,
                'summary' => $summary,
                'orang' => $orang,
                'merged' => $merged,
            ],
            'fetched_at' => now(),
        ];
    }

    private function jabatan(array $data): ?string
    {
        $jenisId = (string) ($this->value($data, 'jenis_jabatan_id') ?? '');

        $value = match ($jenisId) {
            '1' => $this->value($data, 'nama_jabatan_struktural'),
            '2' => $this->nestedName($this->value($data, 'jabatan_fungsional_nama')),
            '4' => $this->nestedName($this->value($data, 'jabatan_fungsional_umum_nama')),
            default => $this->value($data, 'nama_jabatan')
                ?? $this->nestedName($this->value($data, 'jabatan_fungsional_nama'))
                ?? $this->nestedName($this->value($data, 'jabatan_fungsional_umum_nama'))
                ?? $this->value($data, 'nama_jabatan_struktural'),
        };

        return $this->stringValue($value);
    }

    private function value(array $data, string $key): mixed
    {
        return $data[$key] ?? null;
    }

    private function nestedName(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value['nama'] ?? $value['name'] ?? null;
        }

        return $value;
    }

    private function stringValue(mixed $value): ?string
    {
        $value = $this->nestedName($value);
        if (! $this->isFilled($value)) {
            return null;
        }

        return Str::of((string) $value)->squish()->toString();
    }

    private function dateValue(mixed $value): ?string
    {
        if (! $this->isFilled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isFilled(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        $value = trim((string) $value);

        return $value !== '' && $value !== '-';
    }

    private function normalizeToken(string $token): string
    {
        $token = trim($token);
        if (str_starts_with(strtolower($token), 'bearer ')) {
            return trim(substr($token, 7));
        }

        return $token;
    }
}
