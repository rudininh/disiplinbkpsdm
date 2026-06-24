@php
    $payload = is_array($payload ?? null) ? $payload : null;
    $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
    $skpdRows = collect(is_array($payload['skpd'] ?? null) ? $payload['skpd'] : []);
    $successRows = $skpdRows->where('success', true);
    $failedRows = $skpdRows->where('success', false);
    $totalJabatan = (int) ($meta['total_jabatan'] ?? $successRows->sum(fn ($row) => (int) ($row['jabatan_count'] ?? 0)));
    $siasnEmployeeTotal = (int) ($siasnEmployeeTotal ?? 0);
    $lastStatus = $result['success'] ?? null;
    $viewMode = $viewMode ?? 'tree';
    $excelComparison = is_array($excelComparison ?? null) ? $excelComparison : ['success' => false, 'sheets' => [], 'summary' => []];
    $excelSheets = collect($excelComparison['sheets'] ?? []);
    $selectedSheet = (int) ($selectedSheet ?? 0);
    $selectedExcelSheet = $excelSheets->firstWhere('index', $selectedSheet) ?? $excelSheets->first();
    $excelSummary = is_array($excelComparison['summary'] ?? null) ? $excelComparison['summary'] : [];
    $isDinasBaruSheet = function (array $sheet): bool {
        $haystack = strtoupper(trim(($sheet['name'] ?? '') . ' ' . ($sheet['title'] ?? '') . ' ' . ($sheet['matched_skpd']['nama'] ?? '')));

        return str_contains($haystack, 'DINAS BARU');
    };
    $orgSheets = $excelSheets->reject(fn ($sheet) => $isDinasBaruSheet((array) $sheet))->values();
    $orgSkpdGroups = $orgSheets
        ->groupBy(fn ($sheet) => (string) ($sheet['matched_skpd']['skpd_id'] ?? 'unmatched:' . ($sheet['index'] ?? '')))
        ->map(function ($sheets, $key) {
            $first = $sheets->first();
            $summary = [
                'sheets' => $sheets->count(),
                'records' => $sheets->sum(fn ($sheet) => (int) ($sheet['summary']['records'] ?? 0)),
                'needed' => $sheets->sum(fn ($sheet) => (int) ($sheet['summary']['needed'] ?? 0)),
                'filled' => $sheets->sum(fn ($sheet) => (int) ($sheet['summary']['filled'] ?? 0)),
                'vacant' => $sheets->sum(fn ($sheet) => (int) ($sheet['summary']['vacant'] ?? 0)),
                'real_extra' => $sheets->sum(fn ($sheet) => (int) ($sheet['summary']['real_extra'] ?? 0)),
            ];

            return [
                'key' => (string) $key,
                'skpd' => $first['matched_skpd'] ?? null,
                'label' => $first['matched_skpd']['nama'] ?? 'Belum Dicocokkan',
                'kode' => $first['matched_skpd']['kode'] ?? null,
                'sheets' => $sheets->values(),
                'summary' => $summary,
            ];
        })
        ->sortBy(fn ($group) => (($group['skpd']['kode'] ?? 'ZZZ') . ' ' . ($group['label'] ?? '')))
        ->values();
    $firstOrgGroup = $orgSkpdGroups->first();
    $selectedOrgSkpdKey = (string) request('org_skpd', is_array($firstOrgGroup) ? ($firstOrgGroup['key'] ?? '') : '');
    $selectedOrgGroup = $orgSkpdGroups->firstWhere('key', $selectedOrgSkpdKey) ?? $orgSkpdGroups->first();
    $selectedSkpd = (string) request('skpd', 'all');
    $selectedSkpdMode = 'all';
    $selectedSkpdValue = '';

    if ($selectedSkpd !== 'all') {
        if (str_starts_with($selectedSkpd, 'skpd:')) {
            $selectedSkpdMode = 'skpd';
            $selectedSkpdValue = substr($selectedSkpd, 5);
        } elseif (str_starts_with($selectedSkpd, 'index:')) {
            $selectedSkpdMode = 'index';
            $selectedSkpdValue = substr($selectedSkpd, 6);
        } elseif ($skpdRows->contains(fn ($row) => (string) ($row['skpd_id'] ?? '') === $selectedSkpd)) {
            $selectedSkpdMode = 'skpd';
            $selectedSkpdValue = $selectedSkpd;
        } else {
            $selectedSkpdMode = 'index';
            $selectedSkpdValue = $selectedSkpd;
        }
    }

    $selectedSkpdKey = $selectedSkpdMode === 'all' ? 'all' : $selectedSkpdMode . ':' . $selectedSkpdValue;
    $treeRows = $selectedSkpdMode === 'all'
        ? $skpdRows
        : $skpdRows->filter(fn ($row) => $selectedSkpdMode === 'skpd'
            ? (string) ($row['skpd_id'] ?? '') === $selectedSkpdValue
            : (string) ($row['index'] ?? '') === $selectedSkpdValue
        )->values();
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
    $displayJobName = function (?string $value): string {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        if ($value === '') {
            return '-';
        }

        $parts = preg_split('~/~u', $value) ?: [$value];
        $displayParts = array_map(function (string $part): string {
            $part = trim($part);

            if ($part === '') {
                return $part;
            }

            $alias = $part;
            $upper = strtoupper($part);
            $changed = false;

            if (str_contains($upper, 'SUMBER DAYA MANUSIA') && ! preg_match('/\bSDM\b/iu', $part)) {
                $alias = preg_replace('/Sumber\s+Daya\s+Manusia/iu', 'SDM', $alias) ?: $alias;
                $changed = true;
            } elseif (preg_match('/\bSDM\b/iu', $part) && ! str_contains($upper, 'SUMBER DAYA MANUSIA')) {
                $alias = preg_replace('/\bSDM\b/iu', 'Sumber Daya Manusia', $alias) ?: $alias;
                $changed = true;
            }

            if (str_contains($upper, 'APARATUR SIPIL NEGARA') && ! preg_match('/\bASN\b/iu', $part)) {
                $alias = preg_replace('/Aparatur\s+Sipil\s+Negara/iu', 'ASN', $alias) ?: $alias;
                $changed = true;
            } elseif (preg_match('/\bASN\b/iu', $part) && ! str_contains($upper, 'APARATUR SIPIL NEGARA')) {
                $alias = preg_replace('/\bASN\b/iu', 'Aparatur Sipil Negara', $alias) ?: $alias;
                $changed = true;
            }

            $alias = trim($alias);

            if (! $changed || $alias === '' || strcasecmp($alias, $part) === 0) {
                return $part;
            }

            return $part . ' (' . $alias . ')';
        }, $parts);

        return implode('/ ', $displayParts);
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
    $kelurahanContextMatches = function (string $left, string $right) use ($significantJobTokens): bool {
        if (! preg_match('/\bKELURAHAN\s+(.+)$/u', $right, $match)) {
            return true;
        }

        foreach ($significantJobTokens($match[1] ?? '') as $token) {
            if (! str_contains($left, $token)) {
                return false;
            }
        }

        return true;
    };
    $jobMatches = function (?string $left, ?string $right) use ($jobKey, $significantJobTokens, $kelurahanContextMatches): bool {
        $left = $jobKey($left);
        $right = $jobKey($right);

        if ($left === '' || $right === '') {
            return false;
        }

        if (! $kelurahanContextMatches($left, $right)) {
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

        $overlap = count(array_intersect($leftTokens, $rightTokens));

        return ($overlap / $smaller) >= 0.8;
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
            $category = $category !== '' ? $category : 'Jabatan Kosong';
            $grouped[$category][] = $vacancy;
            unset($remaining[$index]);
        }

        foreach ($grouped as $category => $items) {
            $itemRows = collect($items);
            $isPositionGroup = $itemRows->contains(fn ($item) => (bool) ($item['category_is_position'] ?? false));
            $categoryKelas = $itemRows
                ->pluck('category_kelas')
                ->first(fn ($value) => $value !== null && $value !== '');
            $sheetName = $itemRows
                ->pluck('sheet_name')
                ->filter()
                ->unique()
                ->implode(', ');

            if ($isPositionGroup) {
                $nodes[] = [
                    'kelas' => $categoryKelas,
                    'jabatan' => $category,
                    'pegawai' => null,
                    'children' => $items,
                    'callout_class' => 'vacant',
                    'source' => 'excel_parent',
                    'sheet_name' => $sheetName !== '' ? $sheetName : null,
                    'bezetting' => 0,
                    'kebutuhan' => 1,
                    'selisih' => -1,
                    'vacancy_count' => 1,
                ];

                continue;
            }

            $nodes[] = [
                'kelas' => null,
                'jabatan' => $category,
                'pegawai' => null,
                'children' => $items,
                'callout_class' => str_contains(strtoupper($category), 'FUNGSIONAL') ? 'functional' : 'warning',
                'source' => 'category',
            ];
        }

        return $nodes;
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
    $excelVacancyMapBySkpd = [];
    $siasnFunctionalMapBySkpd = [];

    foreach ($excelSheets as $sheet) {
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
                (string) ($record['category_match'] ?? $record['category'] ?? 'Jabatan Kosong'),
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

    $excelVacanciesBySkpd = collect($excelVacancyMapBySkpd)
        ->map(fn ($items) => array_values($items))
        ->all();
    $siasnFunctionalTreesBySkpd = collect($siasnFunctionalMapBySkpd)
        ->map(function ($units) {
            $unitNodes = collect($units)
                ->sortBy('unit')
                ->map(function ($unit) {
                    $jobNodes = collect($unit['jobs'] ?? [])
                        ->sortBy('jabatan')
                        ->map(function ($job) {
                            $people = collect($job['people'] ?? [])
                                ->sortBy('pegawai')
                                ->values();
                            $count = $people->count();
                            $personNodes = $people
                                ->map(fn ($person) => [
                                    'kelas' => $person['kelas'] ?? null,
                                    'jabatan' => $person['jabatan'] ?? '-',
                                    'pegawai' => implode(' | ', array_values(array_filter([
                                        $person['pegawai'] ?? '-',
                                        $person['nip'] ?? null,
                                        $person['status_asn'] ?? null,
                                        ! empty($person['jabatan']) ? 'Jabatan SIASN: ' . $person['jabatan'] : null,
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
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Peta Jabatan Real - Disiplin BKPSDM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900">
    <div class="flex min-h-screen">
        <aside class="hidden w-72 shrink-0 border-r border-zinc-200 bg-zinc-950 text-white">
            <div class="px-6 py-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-400 text-zinc-950">
                        <i data-lucide="scan-line" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold tracking-wide">Disiplin BKPSDM</div>
                        <div class="text-xs text-zinc-400">TPP CMS</div>
                    </div>
                </div>
            </div>

            <nav class="space-y-1 px-3">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                    Dashboard
                </a>
                <a href="{{ route('cms.laporan-cuti.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="file-spreadsheet" class="h-4 w-4"></i>
                    Laporan Cuti
                </a>
                <a href="{{ route('cms.laporan-absensi-harian.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="calendar-check" class="h-4 w-4"></i>
                    Laporan Absensi PNS
                </a>
                <a href="{{ route('cms.laporan-pppk.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="id-card" class="h-4 w-4"></i>
                    Laporan Absensi PPPK
                </a>
                <a href="{{ route('cms.analisa-absensi.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="radar" class="h-4 w-4"></i>
                    Analisa Absensi
                </a>
                <a href="{{ route('cms.peta-jabatan-real.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
                    <i data-lucide="network" class="h-4 w-4"></i>
                    Peta Jabatan Real
                </a>
                <a href="{{ route('cms.siasn.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="database-zap" class="h-4 w-4"></i>
                    SIASN Profil ASN
                </a>
                <a href="{{ route('cms.laporan-balai-kota.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="building-2" class="h-4 w-4"></i>
                    Laporan Balai Kota
                </a>
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    ASN
                </a>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="border-b border-zinc-200 bg-white">
                <div class="flex w-full flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">Peta Jabatan Real</h1>
                        <p class="mt-1 text-sm text-zinc-500">Data struktur jabatan real dari portal TPP untuk seluruh SKPD.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
                            <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                            Dashboard
                        </a>
                        <div class="flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600">
                            <i data-lucide="database" class="h-4 w-4 text-emerald-600"></i>
                            storage/scraping/tpp_peta_jabatan_real.json
                        </div>
                    </div>
                </div>
            </header>

            <section class="w-full px-4 py-6 sm:px-6 lg:px-8">
                @if ($result)
                    <div class="mb-5 rounded-md border {{ ($result['success'] ?? false) || ($result['partial_success'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }} px-4 py-3 text-sm">
                        {{ $result['message'] ?? 'Proses fetch selesai.' }}
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-5">
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Status Fetch</p>
                            <i data-lucide="{{ $lastStatus === false ? 'circle-alert' : 'circle-check' }}" class="h-5 w-5 {{ $lastStatus === false ? 'text-rose-500' : 'text-emerald-600' }}"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">
                            @if ($lastStatus === true)
                                Berhasil
                            @elseif (($result['partial_success'] ?? false) === true)
                                Sebagian
                            @elseif ($lastStatus === false)
                                Gagal
                            @else
                                Siap
                            @endif
                        </p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">SKPD Berhasil</p>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($successRows->count()) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">Gagal: {{ number_format($failedRows->count()) }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">Total Jabatan</p>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($totalJabatan) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">Node jabatan TPP</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">Pegawai SIASN</p>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($siasnEmployeeTotal) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">NIP unik dari import/SIASN</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">Terakhir Simpan</p>
                        <p class="mt-3 break-words text-sm font-semibold text-zinc-800">{{ $meta['fetched_at'] ?? 'Belum ada data' }}</p>
                        @if (isset($meta['start_index'], $meta['end_index']))
                            <p class="mt-1 text-xs text-zinc-500">SKPD {{ $meta['start_index'] }} sampai {{ $meta['end_index'] }}</p>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('cms.peta-jabatan-real.fetch') }}" class="mt-6 rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                    @csrf
                    <div class="grid gap-4 lg:grid-cols-[1fr_160px_160px_auto] lg:items-end">
                        <div>
                            <h2 class="text-base font-semibold">Ambil Data TPP</h2>
                            <p class="mt-1 text-sm text-zinc-500">Login sebagai superadmin, masuk ke SKPD satu per satu, lalu baca halaman /admin/jabatan.</p>
                        </div>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-zinc-500">Dari SKPD</span>
                            <input name="start_index" type="number" min="1" value="{{ old('start_index', $startIndex) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-zinc-500">Sampai SKPD</span>
                            <input name="end_index" type="number" min="1" value="{{ old('end_index', $endIndex) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm">
                        </label>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">
                            <i data-lucide="download-cloud" class="h-4 w-4"></i>
                            Fetch
                        </button>
                    </div>
                    @error('start_index')
                        <p class="mt-3 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                    @error('end_index')
                        <p class="mt-3 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </form>

                <div class="mt-6 flex flex-wrap gap-2 border-b border-zinc-200">
                    <a href="{{ route('cms.peta-jabatan-real.index', ['view' => 'tree', 'skpd' => $selectedSkpdKey]) }}" class="-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold {{ $viewMode === 'tree' ? 'border-zinc-950 text-zinc-950' : 'border-transparent text-zinc-500 hover:text-zinc-900' }}">
                        <i data-lucide="network" class="h-4 w-4"></i>
                        Tree Chart
                    </a>
                    <a href="{{ route('cms.peta-jabatan-real.index', ['view' => 'org', 'org_skpd' => $selectedOrgSkpdKey]) }}" class="-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold {{ $viewMode === 'org' ? 'border-zinc-950 text-zinc-950' : 'border-transparent text-zinc-500 hover:text-zinc-900' }}">
                        <i data-lucide="git-fork" class="h-4 w-4"></i>
                        Organizational Chart
                    </a>
                </div>

                @if ($viewMode === 'org')
                    <div class="mt-6 grid gap-4 md:grid-cols-5">
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <p class="text-sm font-medium text-zinc-500">Sheet Chart</p>
                            <p class="mt-3 text-2xl font-semibold">{{ number_format((int) ($excelSummary['sheets'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <p class="text-sm font-medium text-zinc-500">Baris Jabatan</p>
                            <p class="mt-3 text-2xl font-semibold">{{ number_format((int) ($excelSummary['records'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <p class="text-sm font-medium text-zinc-500">Kebutuhan</p>
                            <p class="mt-3 text-2xl font-semibold">{{ number_format((int) ($excelSummary['needed'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <p class="text-sm font-medium text-zinc-500">Terisi Real</p>
                            <p class="mt-3 text-2xl font-semibold">{{ number_format((int) ($excelSummary['filled'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <p class="text-sm font-medium text-zinc-500">Kosong</p>
                            <p class="mt-3 text-2xl font-semibold text-rose-700">{{ number_format((int) ($excelSummary['vacant'] ?? 0)) }}</p>
                        </div>
                    </div>

                    @if (! ($excelComparison['success'] ?? false))
                        <div class="mt-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            {{ $excelComparison['message'] ?? 'Data Excel belum bisa dibaca.' }}
                        </div>
                    @else
                        <div class="mt-6 grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                            <aside class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                                <div class="border-b border-zinc-200 px-4 py-3">
                                    <h2 class="text-sm font-semibold">Daftar SKPD</h2>
                                </div>
                                <div class="max-h-[760px] overflow-auto p-2">
                                    @foreach ($orgSkpdGroups as $group)
                                        <a href="{{ route('cms.peta-jabatan-real.index', ['view' => 'org', 'org_skpd' => $group['key']]) }}" class="block rounded-md px-3 py-2 text-sm {{ (string) $group['key'] === (string) ($selectedOrgGroup['key'] ?? '') ? 'bg-zinc-950 text-white' : 'hover:bg-zinc-100' }}">
                                            <span class="block font-semibold">{{ $group['label'] }}</span>
                                            <span class="mt-1 block text-xs {{ (string) $group['key'] === (string) ($selectedOrgGroup['key'] ?? '') ? 'text-zinc-300' : 'text-zinc-500' }}">
                                                {{ number_format((int) ($group['summary']['sheets'] ?? 0)) }} sheet,
                                                {{ number_format((int) ($group['summary']['vacant'] ?? 0)) }} kosong
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </aside>

                            <div class="min-w-0 space-y-6">
                                @if ($selectedOrgGroup)
                                    <section class="rounded-lg border border-zinc-200 bg-white px-5 py-4 shadow-sm">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div>
                                                <h2 class="text-lg font-semibold">{{ $selectedOrgGroup['label'] }}</h2>
                                                <p class="mt-1 text-sm text-zinc-500">
                                                    {{ number_format((int) ($selectedOrgGroup['summary']['sheets'] ?? 0)) }} sheet chart digabung sesuai SKPD Tree Chart.
                                                </p>
                                            </div>
                                            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                                <div class="rounded-md bg-zinc-100 px-3 py-2">
                                                    <span class="block text-zinc-500">Kebutuhan</span>
                                                    <strong class="text-zinc-900">{{ number_format((int) ($selectedOrgGroup['summary']['needed'] ?? 0)) }}</strong>
                                                </div>
                                                <div class="rounded-md bg-emerald-50 px-3 py-2">
                                                    <span class="block text-emerald-700">Terisi</span>
                                                    <strong class="text-emerald-900">{{ number_format((int) ($selectedOrgGroup['summary']['filled'] ?? 0)) }}</strong>
                                                </div>
                                                <div class="rounded-md bg-rose-50 px-3 py-2">
                                                    <span class="block text-rose-700">Kosong</span>
                                                    <strong class="text-rose-900">{{ number_format((int) ($selectedOrgGroup['summary']['vacant'] ?? 0)) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                    @foreach (($selectedOrgGroup['sheets'] ?? collect()) as $selectedExcelSheet)
                                    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                                        <div class="border-b border-zinc-200 px-5 py-4">
                                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                <div>
                                                    <h2 class="text-lg font-semibold">{{ $selectedExcelSheet['name'] }}</h2>
                                                    <p class="mt-1 text-sm text-zinc-500">{{ $selectedExcelSheet['title'] }}</p>
                                                    @if (! empty($selectedExcelSheet['matched_skpd']))
                                                        <p class="mt-1 text-xs text-zinc-500">Dicocokkan dengan TPP: {{ $selectedExcelSheet['matched_skpd']['nama'] }}</p>
                                                    @endif
                                                </div>
                                                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                                    <div class="rounded-md bg-zinc-100 px-3 py-2">
                                                        <span class="block text-zinc-500">Kebutuhan</span>
                                                        <strong class="text-zinc-900">{{ number_format((int) ($selectedExcelSheet['summary']['needed'] ?? 0)) }}</strong>
                                                    </div>
                                                    <div class="rounded-md bg-emerald-50 px-3 py-2">
                                                        <span class="block text-emerald-700">Terisi</span>
                                                        <strong class="text-emerald-900">{{ number_format((int) ($selectedExcelSheet['summary']['filled'] ?? 0)) }}</strong>
                                                    </div>
                                                    <div class="rounded-md bg-rose-50 px-3 py-2">
                                                        <span class="block text-rose-700">Kosong</span>
                                                        <strong class="text-rose-900">{{ number_format((int) ($selectedExcelSheet['summary']['vacant'] ?? 0)) }}</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="overflow-auto bg-zinc-200 p-4">
                                            @php
                                                $grid = $selectedExcelSheet['grid'] ?? [];
                                                $maxCol = max((int) ($grid['max_col'] ?? 1), 1);
                                                $maxRow = max((int) ($grid['max_row'] ?? 1), 1);
                                                $cellWidth = 34;
                                                $cellHeight = 22;
                                                $chartScale = 1.28;
                                                $chartWidth = (int) ceil((int) ($grid['width_px'] ?? ($maxCol * $cellWidth)) * $chartScale);
                                                $chartHeight = (int) ceil((int) ($grid['height_px'] ?? ($maxRow * $cellHeight)) * $chartScale);
                                            @endphp
                                            <div class="relative bg-white"
                                                style="width: {{ $chartWidth }}px; height: {{ $chartHeight }}px;">
                                                <svg class="pointer-events-none absolute inset-0 z-0" width="{{ $chartWidth }}" height="{{ $chartHeight }}" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" aria-hidden="true">
                                                    @php
                                                        $gridLineX = 0;
                                                    @endphp
                                                    <line x1="0" y1="0" x2="0" y2="{{ $chartHeight }}" stroke="#d4d4d8" stroke-width="1"></line>
                                                    @foreach (($grid['columns_px'] ?? []) as $columnWidth)
                                                        @php
                                                            $gridLineX += (float) $columnWidth * $chartScale;
                                                        @endphp
                                                        <line x1="{{ $gridLineX }}" y1="0" x2="{{ $gridLineX }}" y2="{{ $chartHeight }}" stroke="#d4d4d8" stroke-width="1"></line>
                                                    @endforeach
                                                    @php
                                                        $gridLineY = 0;
                                                    @endphp
                                                    <line x1="0" y1="0" x2="{{ $chartWidth }}" y2="0" stroke="#d4d4d8" stroke-width="1"></line>
                                                    @foreach (($grid['rows_px'] ?? []) as $rowHeight)
                                                        @php
                                                            $gridLineY += (float) $rowHeight * $chartScale;
                                                        @endphp
                                                        <line x1="0" y1="{{ $gridLineY }}" x2="{{ $chartWidth }}" y2="{{ $gridLineY }}" stroke="#d4d4d8" stroke-width="1"></line>
                                                    @endforeach
                                                </svg>
                                                <svg class="pointer-events-none absolute inset-0" style="z-index: 5;" width="{{ $chartWidth }}" height="{{ $chartHeight }}" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" aria-hidden="true">
                                                    <defs>
                                                        <marker id="org-arrow" markerWidth="12" markerHeight="12" refX="10.5" refY="6" orient="auto" markerUnits="userSpaceOnUse">
                                                            <path d="M1,1 L11,6 L1,11 Z" fill="#18181b"></path>
                                                        </marker>
                                                    </defs>
                                                    @foreach (($grid['connectors'] ?? []) as $connector)
                                                        @if (! empty($connector['paths']))
                                                            @foreach (($connector['paths'] ?? []) as $path)
                                                                @php
                                                                    $pointStrings = [];

                                                                    foreach (($path['points'] ?? []) as $point) {
                                                                        $pointStrings[] = round((float) ($point['x'] ?? 0) * $chartScale, 3) . ',' . round((float) ($point['y'] ?? 0) * $chartScale, 3);
                                                                    }
                                                                @endphp
                                                                @if (count($pointStrings) >= 2)
                                                                    <polyline points="{{ implode(' ', $pointStrings) }}" fill="none" stroke="#18181b" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" marker-end="url(#org-arrow)"></polyline>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            @php
                                                                $parentX = (float) ($connector['parent_x'] ?? 0) * $chartScale;
                                                                $parentY = (float) ($connector['parent_y'] ?? 0) * $chartScale + 3;
                                                                $busY = (float) ($connector['bus_y'] ?? 0) * $chartScale;
                                                                $minX = (float) ($connector['min_x'] ?? 0) * $chartScale;
                                                                $maxX = (float) ($connector['max_x'] ?? 0) * $chartScale;
                                                            @endphp
                                                            <line x1="{{ $parentX }}" y1="{{ $parentY }}" x2="{{ $parentX }}" y2="{{ $busY }}" stroke="#18181b" stroke-width="2.3" stroke-linecap="round"></line>
                                                            <line x1="{{ $minX }}" y1="{{ $busY }}" x2="{{ $maxX }}" y2="{{ $busY }}" stroke="#18181b" stroke-width="2.3" stroke-linecap="round"></line>
                                                            @foreach (($connector['children'] ?? []) as $child)
                                                                @php
                                                                    $childX = (float) ($child['x'] ?? 0) * $chartScale;
                                                                    $childY = max((float) ($child['y'] ?? 0) * $chartScale - 8, $busY);
                                                                @endphp
                                                                <line x1="{{ $childX }}" y1="{{ $busY }}" x2="{{ $childX }}" y2="{{ $childY }}" stroke="#18181b" stroke-width="2.3" stroke-linecap="round" marker-end="url(#org-arrow)"></line>
                                                            @endforeach
                                                        @endif
                                                    @endforeach
                                                </svg>
                                                @foreach (($grid['cells'] ?? []) as $cell)
                                                    @php
                                                        $kind = $cell['kind'] ?? 'text';
                                                        $cellClass = match ($kind) {
                                                            'title' => 'border-transparent bg-white text-center text-[15px] font-bold uppercase leading-tight text-zinc-950',
                                                            'position' => 'flex items-center justify-center border-zinc-900 bg-lime-200 text-center text-[14px] font-semibold leading-[1.18] text-zinc-950',
                                                            'class' => 'flex items-center justify-center border-zinc-900 bg-lime-100 text-center text-[12px] font-medium leading-tight text-zinc-950',
                                                            'header' => 'flex items-center justify-center border-zinc-900 bg-zinc-200 text-center text-[12px] font-semibold leading-tight text-zinc-950',
                                                            'number' => 'flex items-center justify-center border-zinc-900 bg-white text-center text-[12px] leading-tight text-zinc-950',
                                                            default => 'border-zinc-900 bg-white text-[12px] leading-[1.18] text-zinc-950',
                                                        };
                                                        $left = max((float) ($cell['left_px'] ?? (((int) $cell['col'] - 1) * $cellWidth)) * $chartScale, 0);
                                                        $top = max((float) ($cell['top_px'] ?? (((int) $cell['row'] - 1) * $cellHeight)) * $chartScale, 0);
                                                        $width = max((float) ($cell['width_px'] ?? ((int) ($cell['col_span'] ?? 1) * $cellWidth)) * $chartScale, $cellWidth * $chartScale);
                                                        $height = max((float) ($cell['height_px'] ?? ((int) ($cell['row_span'] ?? 1) * $cellHeight)) * $chartScale, $cellHeight * $chartScale);
                                                    @endphp
                                                    <div class="absolute z-10 overflow-hidden whitespace-normal break-words border px-1 py-0.5 {{ $cellClass }}"
                                                        title="{{ $cell['text'] }}"
                                                        style="left: {{ $left }}px; top: {{ $top }}px; width: {{ $width }}px; height: {{ $height }}px;">
                                                        {{ $cell['text'] }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </section>

                                    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                                        <div class="border-b border-zinc-200 px-5 py-4">
                                            <h2 class="text-base font-semibold">Slot Terisi dan Kosong</h2>
                                            <p class="mt-1 text-sm text-zinc-500">Setiap baris kebutuhan Excel dibuat menjadi slot, lalu diisi nama pegawai real dari TPP atau Jabatan SIASN bila cocok.</p>
                                        </div>
                                        <div class="overflow-auto">
                                            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                                                <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                                                    <tr>
                                                        <th class="px-4 py-3">Jabatan</th>
                                                        <th class="px-4 py-3">Kelas</th>
                                                        <th class="px-4 py-3">B</th>
                                                        <th class="px-4 py-3">K</th>
                                                        <th class="px-4 py-3">Terisi Real/SIASN</th>
                                                        <th class="px-4 py-3">Kosong</th>
                                                        <th class="px-4 py-3">Pegawai / Slot</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-zinc-100">
                                                    @forelse (($selectedExcelSheet['comparison_records'] ?? []) as $record)
                                                        <tr>
                                                            <td class="max-w-xl px-4 py-3 align-top font-medium text-zinc-900">{{ $displayJobName($record['jabatan'] ?? '-') }}</td>
                                                            <td class="px-4 py-3 align-top">{{ $record['kelas'] ?? '-' }}</td>
                                                            <td class="px-4 py-3 align-top">{{ $record['bezetting'] ?? '-' }}</td>
                                                            <td class="px-4 py-3 align-top">{{ $record['kebutuhan'] ?? '-' }}</td>
                                                            <td class="px-4 py-3 align-top text-emerald-700">{{ number_format((int) ($record['filled'] ?? 0)) }}</td>
                                                            <td class="px-4 py-3 align-top text-rose-700">{{ number_format((int) ($record['vacant'] ?? 0)) }}</td>
                                                            <td class="min-w-96 px-4 py-3 align-top">
                                                                <div class="flex flex-wrap gap-1.5">
                                                                    @foreach (($record['slots'] ?? []) as $slot)
                                                                        <span class="inline-flex items-center rounded-md border px-2 py-1 text-xs {{ ($slot['status'] ?? '') === 'Terisi' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
                                                                            {{ $slot['nama'] ?? 'KOSONG' }}
                                                                        </span>
                                                                    @endforeach
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="7" class="px-4 py-10 text-center text-zinc-500">Tidak ada baris jabatan yang terdeteksi dari sheet ini.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endif
                @else
                    <form method="GET" action="{{ route('cms.peta-jabatan-real.index') }}" class="mt-6 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <input type="hidden" name="view" value="tree">
                        <div class="grid gap-4 lg:grid-cols-[1fr_360px_auto] lg:items-end">
                            <div>
                                <h2 class="text-base font-semibold">Filter Tree Chart</h2>
                                <p class="mt-1 text-sm text-zinc-500">Pilih satu SKPD untuk fokus ke struktur tertentu, atau tampilkan semua SKPD sekaligus.</p>
                            </div>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-zinc-500">SKPD</span>
                                <select name="skpd" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm">
                                    <option value="all" @selected($selectedSkpdKey === 'all')>Semua SKPD</option>
                                    @foreach ($skpdRows as $option)
                                        @php
                                            $optionValue = isset($option['skpd_id'])
                                                ? 'skpd:' . (string) $option['skpd_id']
                                                : 'index:' . (string) ($option['index'] ?? '');
                                            $optionLabel = trim(($option['kode'] ?? '-') . ' - ' . ($option['nama'] ?? 'SKPD tanpa nama'));
                                        @endphp
                                        <option value="{{ $optionValue }}" @selected($selectedSkpdKey === $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">
                                <i data-lucide="filter" class="h-4 w-4"></i>
                                Tampilkan
                            </button>
                        </div>
                    </form>

                    <div class="mt-6 space-y-4">
                        @forelse ($treeRows as $skpd)
                            @php
                                $vacancyNodes = $excelVacanciesBySkpd[(string) ($skpd['skpd_id'] ?? '')] ?? [];
                                $vacancyCount = collect($vacancyNodes)->sum(fn ($node) => (int) ($node['vacancy_count'] ?? 0));
                                $realTree = $compactEmptyTppNodes(is_array($skpd['tree'] ?? null) ? $skpd['tree'] : []);
                                $realTree = array_values([...$realTree, ...($siasnFunctionalTreesBySkpd[(string) ($skpd['skpd_id'] ?? '')] ?? [])]);
                                $mergedTree = $appendVacancies($realTree, $vacancyNodes);
                            @endphp
                            <article class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                                <div class="border-b border-zinc-200 bg-cyan-700 px-5 py-4 text-white">
                                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <h2 class="text-lg font-semibold">{{ $skpd['nama'] ?? 'SKPD tanpa nama' }}</h2>
                                            <p class="text-sm text-cyan-50">Kode SKPD: {{ $skpd['kode'] ?? '-' }} | ID login: {{ $skpd['skpd_id'] ?? '-' }}</p>
                                        </div>
                                        <div class="flex flex-wrap gap-2 text-xs">
                                            <span class="rounded-md bg-white/15 px-2 py-1">ASN {{ number_format((int) ($skpd['asn_count'] ?? 0)) }}</span>
                                            <span class="rounded-md bg-white/15 px-2 py-1">Jabatan {{ number_format((int) ($skpd['jabatan_count'] ?? 0)) }}</span>
                                            <span class="rounded-md bg-rose-500/80 px-2 py-1">Kosong Excel {{ number_format($vacancyCount) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-5">
                                    @if (! ($skpd['success'] ?? false))
                                        <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                                            {{ $skpd['message'] ?? 'Data SKPD ini belum berhasil diambil.' }}
                                        </div>
                                    @elseif ($mergedTree !== [])
                                        <ul class="space-y-2">
                                            @include('absensi-cms._jabatan-tree', ['nodes' => $mergedTree, 'displayJobName' => $displayJobName])
                                        </ul>
                                    @else
                                        <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">
                                            Tidak ada struktur jabatan pada halaman ini.
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-lg border border-dashed border-zinc-300 bg-white px-6 py-12 text-center text-sm text-zinc-500">
                                Tidak ada data untuk pilihan SKPD ini.
                            </div>
                        @endforelse
                    </div>
                @endif
            </section>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
