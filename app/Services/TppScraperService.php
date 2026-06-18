<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class TppScraperService
{
    private const DEFAULT_BASE_URL = 'https://tpp.banjarmasinkota.go.id';
    private const STORAGE_FILENAME = 'tpp_peta_jabatan_real.json';

    private Client $client;
    private CookieJar $cookieJar;
    private string $baseUrl;

    public function __construct(?Client $client = null)
    {
        $this->baseUrl = rtrim((string) config('services.tpp.base_url', self::DEFAULT_BASE_URL), '/');
        $this->cookieJar = CookieJar::fromArray([], parse_url($this->baseUrl, PHP_URL_HOST) ?: 'tpp.banjarmasinkota.go.id');
        $this->client = $client ?: $this->makeClient();
    }

    public function scrapePetaJabatanReal(string $username, string $password, int $startIndex = 1, int $endIndex = 35): array
    {
        $startIndex = max(1, $startIndex);
        $endIndex = max($startIndex, $endIndex);

        $this->resetClient();
        $auth = $this->authenticate($username, $password);
        $skpdPage = $this->request('GET', '/superadmin/skpd');
        $skpdRows = $this->parseSkpdList((string) $skpdPage->getBody());
        $selectedRows = array_values(array_filter($skpdRows, fn (array $row): bool => $row['index'] >= $startIndex && $row['index'] <= $endIndex));

        $results = [];
        $successCount = 0;
        $failedCount = 0;
        $totalJabatan = 0;

        foreach ($selectedRows as $skpd) {
            try {
                $loginResponse = $this->request('GET', (string) $skpd['login_path']);
                $jabatanResponse = $this->request('GET', '/admin/jabatan');
                $parsed = $this->parseJabatanPage((string) $jabatanResponse->getBody(), $skpd);

                $results[] = [
                    ...$skpd,
                    'success' => true,
                    'status_code' => $jabatanResponse->getStatusCode(),
                    'login_status_code' => $loginResponse->getStatusCode(),
                    ...$parsed,
                ];

                $successCount++;
                $totalJabatan += (int) ($parsed['jabatan_count'] ?? 0);

                if (! empty($parsed['superadmin_path'])) {
                    $this->request('GET', (string) $parsed['superadmin_path']);
                }
            } catch (Throwable $throwable) {
                $failedCount++;
                $results[] = [
                    ...$skpd,
                    'success' => false,
                    'message' => $throwable->getMessage(),
                    'tree' => [],
                    'jabatan_count' => 0,
                    'options' => [],
                ];

                Log::error('TPP peta jabatan fetch failed', [
                    'skpd_index' => $skpd['index'] ?? null,
                    'skpd_id' => $skpd['skpd_id'] ?? null,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        $payload = [
            'success' => $successCount > 0 && $failedCount === 0,
            'partial_success' => $successCount > 0 && $failedCount > 0,
            'message' => $successCount > 0
                ? 'Data Peta Jabatan Real berhasil diambil.'
                : 'Tidak ada data Peta Jabatan Real yang berhasil diambil.',
            'meta' => [
                'fetched_at' => now()->toDateTimeString(),
                'base_url' => $this->baseUrl,
                'start_index' => $startIndex,
                'end_index' => $endIndex,
                'available_skpd' => count($skpdRows),
                'selected_skpd' => count($selectedRows),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'total_jabatan' => $totalJabatan,
                'login_status_code' => $auth['status_code'] ?? null,
            ],
            'skpd' => $results,
        ];

        $this->savePayload($payload);

        return $payload;
    }

    public function latestPetaJabatanReal(): ?array
    {
        $path = $this->storagePath();

        if (! is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) ? $payload : null;
    }

    private function authenticate(string $username, string $password): array
    {
        $loginResponse = $this->request('GET', '/login');
        $crawler = new Crawler((string) $loginResponse->getBody());
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $a1 = (int) $crawler->filter('input[name="a1"]')->attr('value');
        $a2 = (int) $crawler->filter('input[name="a2"]')->attr('value');

        $response = $this->request('POST', '/login', [
            'form_params' => [
                '_token' => $token,
                'username' => $username,
                'password' => $password,
                'a1' => $a1,
                'a2' => $a2,
                'captcha_result' => $a1 + $a2,
            ],
        ]);

        return [
            'status_code' => $response->getStatusCode(),
            'redirect_history' => $response->getHeader('X-Guzzle-Redirect-History'),
        ];
    }

    private function parseSkpdList(string $html): array
    {
        $crawler = new Crawler($html);
        $rows = [];

        $crawler->filter('table tbody tr')->each(function (Crawler $tr) use (&$rows): void {
            $cells = $tr->filter('td');

            if ($cells->count() < 4) {
                return;
            }

            $loginLink = $tr->filter('a[href*="/superadmin/skpd/login/"]');

            if ($loginLink->count() === 0) {
                return;
            }

            $loginPath = $loginLink->last()->attr('href');
            preg_match('~/superadmin/skpd/login/(\d+)~', $loginPath, $loginMatch);
            preg_match('/ASN\s*:\s*(\d+)/i', $tr->text(''), $asnMatch);
            preg_match('/PETA\s+JABATAN\s*:\s*(\d+)/i', $tr->text(''), $jabatanMatch);

            $namaCell = $cells->eq(3);
            $nama = $namaCell->filter('strong')->count() > 0
                ? $this->normalizeText($namaCell->filter('strong')->first()->text(''))
                : null;
            $kode = $cells->eq(2)->filter('strong')->count() > 0
                ? $this->normalizeText($cells->eq(2)->filter('strong')->first()->text(''))
                : null;

            $rows[] = [
                'index' => (int) $this->normalizeText($cells->eq(0)->text('0')),
                'skpd_id' => isset($loginMatch[1]) ? (int) $loginMatch[1] : null,
                'kode' => $kode,
                'nama' => $nama,
                'asn_count' => isset($asnMatch[1]) ? (int) $asnMatch[1] : null,
                'peta_jabatan_count' => isset($jabatanMatch[1]) ? (int) $jabatanMatch[1] : null,
                'login_path' => $loginPath,
            ];
        });

        return $rows;
    }

    private function parseJabatanPage(string $html, array $fallbackSkpd): array
    {
        $crawler = new Crawler($html);
        $nama = $crawler->filter('.widget-user-username')->count() > 0
            ? $this->normalizeText($crawler->filter('.widget-user-username')->first()->text(''))
            : ($fallbackSkpd['nama'] ?? null);
        $kodeText = $crawler->filter('.widget-user-desc')->count() > 0
            ? $this->normalizeText($crawler->filter('.widget-user-desc')->first()->text(''))
            : null;
        $kode = $kodeText && preg_match('/Kode\s+Skpd\s*:\s*(.+)$/i', $kodeText, $match)
            ? $this->normalizeText($match[1])
            : ($fallbackSkpd['kode'] ?? null);

        $options = [];
        $crawler->filter('select[name="jabatan_id"] option')->each(function (Crawler $option) use (&$options): void {
            $options[] = [
                'id' => (int) $option->attr('value'),
                'nama' => $this->normalizeText($option->text('')),
            ];
        });
        $superadminLink = $crawler->filter('a[href*="/admin/superadmin/"]')->count() > 0
            ? $crawler->filter('a[href*="/admin/superadmin/"]')->first()->attr('href')
            : null;

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($document);
        $rootList = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " col-lg-8 ") and contains(concat(" ", normalize-space(@class), " "), " col-12 ")]/ul')->item(0);
        $tree = $rootList instanceof \DOMElement ? $this->parseJabatanList($rootList, 0) : [];

        return [
            'nama' => $nama,
            'kode' => $kode,
            'options' => $options,
            'tree' => $tree,
            'jabatan_count' => $this->countTree($tree),
            'superadmin_path' => $superadminLink,
        ];
    }

    private function parseJabatanList(\DOMElement $ul, int $depth): array
    {
        $items = [];

        foreach ($ul->childNodes as $child) {
            if (! $child instanceof \DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $callout = $this->firstDirectChildByClass($child, 'callout');

            if (! $callout) {
                continue;
            }

            $item = $this->parseCallout($callout, $depth);
            $children = [];

            foreach ($child->childNodes as $liChild) {
                if ($liChild instanceof \DOMElement && strtolower($liChild->tagName) === 'ul') {
                    $children = array_merge($children, $this->parseJabatanList($liChild, $depth + 1));
                }
            }

            $item['children'] = $children;
            $items[] = $item;
        }

        return $items;
    }

    private function parseCallout(\DOMElement $callout, int $depth): array
    {
        $crawler = new Crawler($callout);
        $text = $this->normalizeText($callout->textContent ?? '');
        $jabatan = $crawler->filter('strong')->count() > 0
            ? $this->normalizeText($crawler->filter('strong')->first()->text(''))
            : null;
        $editLink = $crawler->filter('a[href*="/admin/jabatan/edit/"]')->count() > 0
            ? $crawler->filter('a[href*="/admin/jabatan/edit/"]')->first()->attr('href')
            : null;
        preg_match('~/admin/jabatan/edit/(\d+)~', (string) $editLink, $idMatch);
        preg_match('/^\s*(\d+)/u', $text, $kelasMatch);
        preg_match('/\|\s*([^|]+)$/u', $text, $pegawaiMatch);

        return [
            'id' => isset($idMatch[1]) ? (int) $idMatch[1] : null,
            'kelas' => isset($kelasMatch[1]) ? (int) $kelasMatch[1] : null,
            'jabatan' => $jabatan,
            'pegawai' => isset($pegawaiMatch[1]) ? $this->normalizeText($pegawaiMatch[1]) : null,
            'depth' => $depth,
            'callout_class' => $this->calloutClass((string) $callout->getAttribute('class')),
            'edit_path' => $editLink,
        ];
    }

    private function firstDirectChildByClass(\DOMElement $element, string $class): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && str_contains(' ' . $child->getAttribute('class') . ' ', ' ' . $class . ' ')) {
                return $child;
            }
        }

        return null;
    }

    private function calloutClass(string $class): string
    {
        if (str_contains($class, 'callout-info')) {
            return 'info';
        }

        if (str_contains($class, 'callout-warning')) {
            return 'warning';
        }

        if (str_contains($class, 'callout-danger')) {
            return 'danger';
        }

        return 'default';
    }

    private function countTree(array $tree): int
    {
        return array_reduce($tree, fn (int $count, array $item): int => $count + 1 + $this->countTree($item['children'] ?? []), 0);
    }

    private function savePayload(array $payload): void
    {
        $directory = dirname($this->storagePath());

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($this->storagePath(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function storagePath(): string
    {
        return storage_path('scraping/' . self::STORAGE_FILENAME);
    }

    private function request(string $method, string $uri, array $options = [])
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $this->client->request($method, $uri, $options);
            } catch (Throwable $throwable) {
                $lastException = $throwable;
                usleep(400000 * $attempt);
            }
        }

        throw $lastException;
    }

    private function resetClient(): void
    {
        $this->cookieJar = CookieJar::fromArray([], parse_url($this->baseUrl, PHP_URL_HOST) ?: 'tpp.banjarmasinkota.go.id');
        $this->client = $this->makeClient();
    }

    private function makeClient(): Client
    {
        return new Client([
            'base_uri' => $this->baseUrl,
            'cookies' => $this->cookieJar,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 10,
                'track_redirects' => true,
                'referer' => true,
            ],
            'timeout' => 45,
            'connect_timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
        ]);
    }

    private function normalizeText(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
