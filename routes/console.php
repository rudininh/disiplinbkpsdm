<?php

use App\Services\PetaJabatanExcelService;
use App\Services\TppScraperService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('peta-jabatan:export-static', function (TppScraperService $tppScraper, PetaJabatanExcelService $excelService) {
    $payload = $tppScraper->latestPetaJabatanReal();
    $comparison = $excelService->comparison($payload, null);
    $skpdRows = is_array($payload['skpd'] ?? null) ? $payload['skpd'] : [];

    $jobKey = function (?string $value): string {
        $value = strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));
        $value = str_replace([
            'SUMBER DAYA MANUSIA',
            'APARATUR SIPIL NEGARA',
            'UNIT PELAKSANA TEKNIS DAERAH',
            'UNIT PELAKSANA TEKNIS',
            'TATA USAHA',
            'PENDIDIKAN ANAK USIA DINI',
            'PENDIDIKAN NON FORMAL',
            'PENDIDIKAN NONFORMAL',
            'PENERANGAN JALAN UMUM',
            'PENERANGAN JALAN LINGKUNGAN',
            'PENGUJIAN KENDARAAN BERMOTOR',
            'RUMAH POTONG HEWAN',
            'TEMPAT PENDARATAN IKAN',
            'BALAI LATIHAN KERJA',
            'PERLINDUNGAN PEREMPUAN DAN ANAK',
            'KEPENDUDUKAN DAN PENCATATAN SIPIL',
            'RUMAH SUSUN SEDERHANA SEWA',
            'RUMAH SUSUN DAN SEWA',
            'SUBBAGIAN',
            'TATA LAKSANA',
            'SPIRITUAL',
        ], [
            'SDM',
            'ASN',
            'UPTD',
            'UPT',
            'TU',
            'PAUD',
            'PNF',
            'PNF',
            'PJU',
            'PJL',
            'PKB',
            'RPH',
            'TPI',
            'BLK',
            'PPA',
            'DUKCAPIL',
            'RUMAH SUSUN SEWA',
            'RUMAH SUSUN SEWA',
            'SUB BAGIAN',
            'TATALAKSANA',
            'SPRITUAL',
        ], $value);
        $value = preg_replace('/\s+PADA\s+.+$/u', '', $value) ?: $value;
        $value = preg_replace('/\s+KOTA\s+BANJARMASIN$/u', '', $value) ?: $value;
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?: $value;

        return trim($value);
    };

    $significantJobTokens = function (string $key): array {
        $stopwords = [
            'DAN' => true,
            'DI' => true,
            'KE' => true,
            'PADA' => true,
            'KOTA' => true,
            'BANJARMASIN' => true,
            'KEPALA' => true,
            'SUB' => true,
            'BAGIAN' => true,
            'BIDANG' => true,
            'SEKSI' => true,
            'UNIT' => true,
            'UPT' => true,
            'UPTD' => true,
            'DAERAH' => true,
            'DINAS' => true,
            'BADAN' => true,
        ];

        return array_values(array_unique(array_filter(
            explode(' ', $key),
            fn (string $token): bool => strlen($token) > 2 && ! isset($stopwords[$token])
        )));
    };

    $jobMatches = function (?string $left, ?string $right) use ($jobKey, $significantJobTokens): bool {
        $left = $jobKey($left);
        $right = $jobKey($right);

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right || str_contains($left, $right) || str_contains($right, $left)) {
            return true;
        }

        $leftTokens = $significantJobTokens($left);
        $rightTokens = $significantJobTokens($right);
        $smaller = min(count($leftTokens), count($rightTokens));

        if ($smaller < 3) {
            return false;
        }

        return (count(array_intersect($leftTokens, $rightTokens)) / $smaller) >= 0.8;
    };

    $isVacantPegawai = function (?string $pegawai, ?string $jabatan) use ($jobKey): bool {
        $pegawai = trim((string) $pegawai);

        if ($pegawai === '' || $pegawai === '-') {
            return true;
        }

        $key = $jobKey($pegawai);

        return in_array($key, ['', 'KOSONG', 'LOWONG'], true)
            || $key === $jobKey($jabatan);
    };

    $compactEmptyTppNodes = function (array $nodes) use (&$compactEmptyTppNodes, $jobKey, $isVacantPegawai): array {
        $compacted = [];
        $emptyGroups = [];

        foreach ($nodes as $node) {
            $children = $compactEmptyTppNodes(is_array($node['children'] ?? null) ? $node['children'] : []);
            $pegawai = trim((string) ($node['pegawai'] ?? ''));
            $source = $node['source'] ?? 'tpp';
            $isEmptyLeaf = $source === 'tpp' && $children === [] && $isVacantPegawai($pegawai, $node['jabatan'] ?? '');

            if ($isEmptyLeaf) {
                $key = implode('|', [
                    $jobKey($node['jabatan'] ?? ''),
                    (string) ($node['kelas'] ?? ''),
                    (string) ($node['callout_class'] ?? ''),
                ]);

                if (! isset($emptyGroups[$key])) {
                    $emptyGroups[$key] = [
                        ...$node,
                        'pegawai' => null,
                        'children' => [],
                        'callout_class' => 'vacant',
                        'source' => 'tpp_empty',
                        'bezetting' => 0,
                        'kebutuhan' => 0,
                        'selisih' => 0,
                        'vacancy_count' => 0,
                    ];
                }

                $emptyGroups[$key]['kebutuhan']++;
                $emptyGroups[$key]['selisih']--;
                $emptyGroups[$key]['vacancy_count']++;

                continue;
            }

            $node['children'] = $children;
            $compacted[] = $node;
        }

        return array_values([...$compacted, ...array_values($emptyGroups)]);
    };

    $attachVacancies = function (array &$nodes, array &$remaining) use (&$attachVacancies, $jobMatches): void {
        foreach ($nodes as &$node) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $attachVacancies($children, $remaining);
            $matched = [];

            foreach ($remaining as $index => $vacancy) {
                if ($jobMatches($node['jabatan'] ?? '', $vacancy['category_match'] ?? $vacancy['category'] ?? '')) {
                    $matched[] = $vacancy;
                    unset($remaining[$index]);
                }
            }

            $isFunctionalNode = str_contains(strtoupper((string) ($node['jabatan'] ?? '')), 'JABATAN FUNGSIONAL')
                || ($node['callout_class'] ?? '') === 'functional';
            $node['children'] = $isFunctionalNode
                ? array_values([...$matched, ...$children])
                : array_values([...$children, ...$matched]);
        }
        unset($node);
    };

    $appendVacancies = function (array $nodes, array $vacancies) use ($attachVacancies): array {
        $remaining = $vacancies;
        $attachVacancies($nodes, $remaining);
        $grouped = [];

        foreach ($remaining as $index => $vacancy) {
            $category = trim((string) (($vacancy['category_match'] ?? null) ?: ($vacancy['category'] ?? 'Jabatan Kosong')));
            $grouped[$category !== '' ? $category : 'Jabatan Kosong'][] = $vacancy;
            unset($remaining[$index]);
        }

        foreach ($grouped as $category => $items) {
            $isPositionGroup = collect($items)->contains(fn ($item) => (bool) ($item['category_is_position'] ?? false));
            $categoryKelas = collect($items)->pluck('category_kelas')->first(fn ($value) => $value !== null && $value !== '');
            $sheetName = collect($items)->pluck('sheet_name')->filter()->unique()->implode(', ');

            $nodes[] = [
                'kelas' => $isPositionGroup ? $categoryKelas : null,
                'jabatan' => $category,
                'pegawai' => null,
                'children' => $items,
                'callout_class' => $isPositionGroup ? 'vacant' : (str_contains(strtoupper($category), 'FUNGSIONAL') ? 'functional' : 'warning'),
                'source' => $isPositionGroup ? 'excel_parent' : 'category',
                'sheet_name' => $sheetName !== '' ? $sheetName : null,
                'bezetting' => $isPositionGroup ? 0 : null,
                'kebutuhan' => $isPositionGroup ? 1 : null,
                'selisih' => $isPositionGroup ? -1 : null,
                'vacancy_count' => $isPositionGroup ? 1 : null,
            ];
        }

        return $nodes;
    };

    $excelVacancyMapBySkpd = [];
    $siasnFunctionalMapBySkpd = [];
    foreach (($comparison['sheets'] ?? []) as $sheet) {
        $matchedSkpdId = $sheet['matched_skpd']['skpd_id'] ?? null;

        if (! $matchedSkpdId) {
            continue;
        }

        foreach (($sheet['comparison_records'] ?? []) as $record) {
            foreach (($record['people_details'] ?? []) as $detail) {
                if (($detail['source'] ?? '') !== 'siasn') {
                    continue;
                }

                $unit = trim((string) (($detail['unit_kerja'] ?? null) ?: ($detail['lokasi_nama'] ?? 'Unit Kerja SIASN')));
                $unit = $unit !== '' ? $unit : 'Unit Kerja SIASN';
                $unitKey = $jobKey($unit);
                $job = trim((string) (($detail['record_jabatan'] ?? null) ?: ($record['jabatan'] ?? ($detail['jabatan'] ?? '-'))));
                $job = $job !== '' ? $job : '-';
                $groupKey = implode('|', [$unitKey, $jobKey($job), (string) ($record['kelas'] ?? '')]);
                $personKey = preg_replace('/\D+/', '', (string) ($detail['nip'] ?? '')) ?: $jobKey(($detail['name'] ?? '') . ' ' . $unit . ' ' . $job);

                $siasnFunctionalMapBySkpd[(string) $matchedSkpdId][$unitKey]['unit'] = $unit;
                $siasnFunctionalMapBySkpd[(string) $matchedSkpdId][$unitKey]['jobs'][$groupKey]['kelas'] = $record['kelas'] ?? ($detail['record_kelas'] ?? null);
                $siasnFunctionalMapBySkpd[(string) $matchedSkpdId][$unitKey]['jobs'][$groupKey]['jabatan'] = $job;
                $siasnFunctionalMapBySkpd[(string) $matchedSkpdId][$unitKey]['jobs'][$groupKey]['sheet_name'] = $sheet['name'] ?? null;
                $siasnFunctionalMapBySkpd[(string) $matchedSkpdId][$unitKey]['jobs'][$groupKey]['people'][$personKey] = [
                    'kelas' => $record['kelas'] ?? ($detail['record_kelas'] ?? null),
                    'jabatan' => $detail['jabatan'] ?? $job,
                    'pegawai' => $detail['name'] ?? $detail['display'] ?? '-',
                    'nip' => $detail['nip'] ?? null,
                    'status_asn' => $detail['status_asn'] ?? null,
                ];
            }

            $vacant = (int) ($record['vacant'] ?? 0);

            if ($vacant <= 0) {
                continue;
            }

            $mapKey = implode('|', [
                (string) $matchedSkpdId,
                (string) ($record['category'] ?? 'Jabatan Kosong'),
                $jobKey($record['jabatan'] ?? '-'),
                (string) ($record['kelas'] ?? ''),
            ]);
            $existing = $excelVacancyMapBySkpd[(string) $matchedSkpdId][$mapKey] ?? null;
            $excelVacancyMapBySkpd[(string) $matchedSkpdId][$mapKey] = [
                'kelas' => $record['kelas'] ?? null,
                'jabatan' => $record['jabatan'] ?? '-',
                'pegawai' => null,
                'children' => [],
                'callout_class' => 'vacant',
                'source' => 'excel',
                'category' => $record['category'] ?? 'Jabatan Kosong',
                'category_match' => $record['category_match'] ?? $record['category'] ?? 'Jabatan Kosong',
                'category_kelas' => $record['category_kelas'] ?? null,
                'category_is_position' => (bool) ($record['category_is_position'] ?? false)
                    || (
                        ! empty($record['category_match'])
                        && (string) ($record['category_match'] ?? '') !== (string) ($record['category'] ?? '')
                    ),
                'sheet_name' => $sheet['name'] ?? null,
                'bezetting' => (int) ($existing['bezetting'] ?? 0) + (int) ($record['filled'] ?? 0),
                'kebutuhan' => (int) ($existing['kebutuhan'] ?? 0) + (int) ($record['needed'] ?? $record['kebutuhan'] ?? 0),
                'selisih' => (int) ($existing['selisih'] ?? 0) + ((int) ($record['filled'] ?? 0) - (int) ($record['needed'] ?? $record['kebutuhan'] ?? 0)),
                'vacancy_count' => (int) ($existing['vacancy_count'] ?? 0) + $vacant,
            ];
        }
    }

    $siasnFunctionalTreesBySkpd = collect($siasnFunctionalMapBySkpd)
        ->map(function ($units) {
            $unitNodes = collect($units)
                ->sortBy('unit')
                ->map(function ($unit) {
                    $jobNodes = collect($unit['jobs'] ?? [])
                        ->sortBy('jabatan')
                        ->map(function ($job) {
                            $people = collect($job['people'] ?? [])->sortBy('pegawai')->values();
                            $count = $people->count();
                            $personNodes = $people
                                ->map(fn ($person) => [
                                    'kelas' => $person['kelas'] ?? null,
                                    'jabatan' => $person['jabatan'] ?? '-',
                                    'pegawai' => implode(' | ', array_values(array_filter([
                                        $person['pegawai'] ?? '-',
                                        $person['nip'] ?? null,
                                        $person['status_asn'] ?? null,
                                    ]))),
                                    'children' => [],
                                    'callout_class' => 'info',
                                    'source' => 'siasn_person',
                                ])
                                ->all();

                            return [
                                'kelas' => $job['kelas'] ?? null,
                                'jabatan' => $job['jabatan'] ?? '-',
                                'pegawai' => null,
                                'children' => $personNodes,
                                'callout_class' => 'info',
                                'source' => 'siasn_group',
                                'sheet_name' => $job['sheet_name'] ?? null,
                                'bezetting' => $count,
                                'kebutuhan' => $count,
                                'selisih' => 0,
                                'vacancy_count' => 0,
                            ];
                        })
                        ->values()
                        ->all();
                    $count = collect($jobNodes)->sum(fn ($node) => (int) ($node['bezetting'] ?? 0));

                    return [
                        'kelas' => null,
                        'jabatan' => $unit['unit'] ?? 'Unit Kerja SIASN',
                        'pegawai' => number_format($count) . ' pegawai SIASN',
                        'children' => $jobNodes,
                        'callout_class' => 'functional',
                        'source' => 'siasn_unit',
                    ];
                })
                ->values()
                ->all();
            $count = collect($unitNodes)->sum(function ($unitNode) {
                return collect($unitNode['children'] ?? [])->sum(fn ($node) => (int) ($node['bezetting'] ?? 0));
            });

            return [[
                'kelas' => null,
                'jabatan' => 'Jabatan Fungsional',
                'pegawai' => number_format($count) . ' pegawai SIASN',
                'children' => $unitNodes,
                'callout_class' => 'functional',
                'source' => 'category',
            ]];
        })
        ->all();

    foreach ($skpdRows as &$skpd) {
        $vacancyNodes = array_values($excelVacancyMapBySkpd[(string) ($skpd['skpd_id'] ?? '')] ?? []);
        $realTree = $compactEmptyTppNodes(is_array($skpd['tree'] ?? null) ? $skpd['tree'] : []);
        $realTree = array_values([...$realTree, ...($siasnFunctionalTreesBySkpd[(string) ($skpd['skpd_id'] ?? '')] ?? [])]);
        $skpd['tree'] = $appendVacancies($realTree, $vacancyNodes);
        $skpd['vacancy_count'] = collect($vacancyNodes)->sum(fn ($node) => (int) ($node['vacancy_count'] ?? 0));
        $skpd['siasn_functional_count'] = collect($siasnFunctionalTreesBySkpd[(string) ($skpd['skpd_id'] ?? '')] ?? [])
            ->sum(fn ($node) => (int) filter_var((string) ($node['pegawai'] ?? '0'), FILTER_SANITIZE_NUMBER_INT));
    }
    unset($skpd);

    $export = [
        ...$payload,
        'meta' => [
            ...($payload['meta'] ?? []),
            'excel_vacant' => (int) ($comparison['summary']['vacant'] ?? 0),
            'excel_records' => (int) ($comparison['summary']['records'] ?? 0),
        ],
        'skpd' => $skpdRows,
        'excel_summary' => $comparison['summary'] ?? null,
    ];

    $path = base_path('peta-jabatan-static/data/static_peta_jabatan_real.json');
    file_put_contents(
        $path,
        json_encode($export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );

    $this->info('Exported static peta jabatan data to '.$path);
})->purpose('Export merged peta jabatan data for the static Vercel viewer');
