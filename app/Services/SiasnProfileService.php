<?php

namespace App\Services;

use App\Models\SiasnPnsProfile;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SiasnProfileService
{
    private const BASE_URL = 'https://api-siasn.bkn.go.id';

    public function tokenInfo(string $bearerToken): array
    {
        $token = $this->normalizeToken($bearerToken);

        if ($token === '') {
            throw new RuntimeException('Token SIASN belum diisi.');
        }

        $this->ensureBearerTokenLooksValid($token);

        $payload = $this->jwtPayload($token);
        $expiresAt = is_array($payload) && isset($payload['exp'])
            ? Carbon::createFromTimestamp((int) $payload['exp'])
            : null;

        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw new RuntimeException('Token SIASN sudah kedaluwarsa pada ' . $expiresAt->format('Y-m-d H:i:s') . '. Silakan login ulang lalu ambil token baru.');
        }

        $pegawai = is_array($payload['pegawai'] ?? null) ? $payload['pegawai'] : [];
        $nama = $this->stringValue($pegawai['nama'] ?? null);
        $nip = $this->stringValue($pegawai['nip'] ?? $pegawai['username'] ?? null);

        return [
            'token' => $token,
            'expires_at' => $expiresAt?->timestamp,
            'expires_at_text' => $expiresAt?->format('Y-m-d H:i:s'),
            'nama' => $nama,
            'nip' => $nip,
        ];
    }

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
        $this->ensureBearerTokenLooksValid($token);

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

    public function testAccess(string $bearerToken, ?string $nip = null): array
    {
        $token = $this->normalizeToken($bearerToken);
        $nip = preg_replace('/\D+/', '', (string) $nip) ?? '';

        if ($token === '') {
            throw new RuntimeException('Token SIASN belum diisi.');
        }
        $this->ensureBearerTokenLooksValid($token);

        if ($nip === '') {
            $info = $this->tokenInfo($token);
            $nama = $info['nama'];
            $nipToken = $info['nip'];
            $identity = $nama ?: ($nipToken ? 'NIP ' . $nipToken : 'pengguna SIASN');
            $expiryText = $info['expires_at_text'] ? ' Berlaku sampai ' . $info['expires_at_text'] . '.' : '';

            return [
                'success' => true,
                'message' => 'Token SIASN terbaca untuk ' . $identity . '.' . $expiryText . ' Isi NIP Test bila ingin lanjut cek akses profil PNS/PPPK ke API SIASN.',
            ];
        }

        if (strlen($nip) !== 18) {
            throw new RuntimeException('NIP harus berisi 18 digit.');
        }

        try {
            [$summaryResponse, $summary, $jenisAsn] = $this->findSummary($nip, $token);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Data PNS/PPPK tidak ditemukan')) {
                return [
                    'success' => true,
                    'message' => 'Login SIASN berhasil, tetapi data PNS/PPPK tidak ditemukan untuk NIP tersebut.',
                ];
            }

            throw $exception;
        }

        $pnsId = $this->value($summary, 'id');
        $orang = [];
        if ($pnsId !== null) {
            $orangResponse = $this->get('/profilasn/api/orang', [
                'id' => $pnsId,
            ], $token);
            $orang = $this->payloadArray($orangResponse);
        }

        $merged = $this->mergeSiasnData($orang, $summary);
        $nama = $this->stringValue($this->value($merged, 'nama')) ?: 'ASN';

        return [
            'success' => true,
            'message' => "Login SIASN berhasil. Profil {$jenisAsn} untuk {$nama} bisa diakses.",
            'profile' => $this->displayProfilePayload($merged, $jenisAsn),
        ];
    }

    private function findSummary(string $nip, string $token): array
    {
        $attempts = [
            ['PNS', '/profilasn/api/pns-siasn', ['nip_baru' => $nip]],
            ['PPPK', '/profilasn/api/pppk', ['nip_lama' => '', 'nip_baru' => $nip]],
            ['PPPK', '/profilasn/api/pppk-siasn', ['nip_baru' => $nip]],
        ];

        foreach ($attempts as [$jenisAsn, $path, $query]) {
            try {
                $response = $this->get($path, $query, $token);
            } catch (RuntimeException $exception) {
                if ($exception->getCode() === 404) {
                    continue;
                }

                throw $exception;
            }

            $summary = $this->firstValue($response);
            if ($summary !== null) {
                return [$response, $summary, $jenisAsn];
            }
        }

        throw new RuntimeException('Data PNS/PPPK tidak ditemukan dari SIASN untuk NIP tersebut.');
    }

    private function get(string $path, array $query, string $token): array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->timeout(30)
                ->get(self::BASE_URL . $path, $query);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('API SIASN tidak bisa dihubungi dari server lokal: ' . $exception->getMessage(), 0, $exception);
        }

        if ($response->unauthorized() || $response->forbidden()) {
            throw new RuntimeException('Token SIASN ditolak atau sudah kedaluwarsa. Silakan login ulang lalu ambil token baru.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('SIASN mengembalikan error HTTP ' . $response->status() . '.', $response->status());
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

    private function displayProfilePayload(array $merged, string $jenisAsn): array
    {
        return [
            'Jenis ASN' => $jenisAsn,
            'NIP' => $this->stringValue($this->value($merged, 'nip_baru') ?? $this->value($merged, 'nip')),
            'Nama' => $this->stringValue($this->value($merged, 'nama')),
            'Jabatan' => $this->jabatan($merged),
            'Jenis Jabatan' => $this->stringValue($this->nestedName($this->value($merged, 'jenis_jabatan_nama'))),
            'Unit Organisasi' => $this->stringValue($this->value($merged, 'unor_nama')),
            'Unit Induk' => $this->stringValue($this->value($merged, 'unor_induk_nama')),
            'Instansi Kerja' => $this->stringValue($this->value($merged, 'instansi_kerja_nama')),
            'Satuan Kerja' => $this->stringValue($this->value($merged, 'satuan_kerja_kerja_nama')),
            'Lokasi Kerja' => $this->stringValue($this->value($merged, 'lokasi_kerja_nama')),
            'TMT Jabatan' => $this->dateValue($this->value($merged, 'tmt_jabatan')),
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

        if (preg_match('/authorization\s*:\s*bearer\s+([A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+)/i', $token, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/["\']token["\']\s*:\s*["\']([^"\']+)["\']/i', $token, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/(?:^|[;\s])token\s*=\s*"?([^";\s]+)"?/i', $token, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/(?:^|\R)\s*token\s+"([^"]+)"/i', $token, $matches) === 1) {
            return trim($matches[1]);
        }

        if (str_starts_with(strtolower($token), 'bearer ')) {
            return trim(substr($token, 7));
        }

        return $token;
    }

    private function ensureBearerTokenLooksValid(string $token): void
    {
        $lowerToken = strtolower($token);

        if (str_starts_with($lowerToken, 'http://') || str_starts_with($lowerToken, 'https://') || str_contains($lowerToken, 'openid-connect/auth')) {
            throw new RuntimeException('Yang ditempel adalah URL login SSO SIASN, bukan token. Selesaikan login di browser, lalu salin access_token atau header Authorization: Bearer dari request ASN Digital/SIASN.');
        }

        if (str_contains($lowerToken, 'code_challenge=') || str_contains($lowerToken, 'response_type=code') || str_contains($lowerToken, 'client_id=bkn-portal')) {
            throw new RuntimeException('Yang ditempel terlihat seperti parameter login OpenID Connect, bukan token SIASN. Token yang dibutuhkan adalah access_token setelah login berhasil.');
        }

        if (preg_match('/^\d{4,8}$/', $token) === 1) {
            throw new RuntimeException('Yang ditempel terlihat seperti kode OTP/authenticator, bukan token SIASN. Token SIASN biasanya panjang dan diawali eyJ... atau Bearer eyJ...');
        }

        $payload = $this->jwtPayload($token);
        if (is_array($payload) && strtolower((string) ($payload['typ'] ?? '')) === 'refresh') {
            throw new RuntimeException('Yang ditempel adalah refresh token. Gunakan access token atau cookie token, bukan refresh_token/sso_refresh_token.');
        }

        if (strlen($token) < 40) {
            throw new RuntimeException('Token SIASN terlalu pendek. Salin nilai Authorization/Bearer token dari request SIASN, bukan kode OTP.');
        }

        if (! str_starts_with($token, 'eyJ') && substr_count($token, '.') < 2) {
            throw new RuntimeException('Token SIASN belum terlihat seperti access_token JWT. Biasanya token panjang, diawali eyJ..., dan memiliki tiga bagian yang dipisahkan titik.');
        }
    }

    private function jwtPayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return null;
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        $json = json_decode($decoded, true);

        return is_array($json) ? $json : null;
    }
}
