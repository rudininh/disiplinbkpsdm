@php
    $pageMode = $pageMode ?? 'balai-kota';
    $pageTitle = $pageTitle ?? 'Laporan Balai Kota';
    $pageDescription = $pageDescription ?? 'Rekap apel dengan pencocokan cuti, tugas luar, sakit, dan diklat.';
    $reportScopeLabel = $reportScopeLabel ?? 'Balai Kota';
    $printSubject = $printSubject ?? 'Apel Pagi / Apel Hari Besar';
    $fetchRoute = $fetchRoute ?? 'cms.laporan-balai-kota.fetch';
    $fetchHariBesarRoute = $fetchHariBesarRoute ?? 'cms.laporan-balai-kota.fetch-hari-besar';
    $fetchCutiRoute = $fetchCutiRoute ?? 'cms.laporan-balai-kota.fetch-cuti';
    $indexRoute = $indexRoute ?? 'cms.laporan-balai-kota.index';
    $showSkpdSelector = $showSkpdSelector ?? false;
    $skpdOptions = $skpdOptions ?? [];
    $selectedSkpdIds = collect($selectedSkpdIds ?? [])->map(fn ($id) => (int) $id)->values()->all();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} - Disiplin BKPSDM CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .print-sheet { box-shadow: none !important; border: 0 !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900">
    <div class="flex min-h-screen">
        <aside class="no-print hidden w-72 shrink-0 border-r border-zinc-200 bg-zinc-950 text-white lg:block">
            <div class="px-6 py-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-400 text-zinc-950">
                        <i data-lucide="scan-line" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold tracking-wide">Disiplin BKPSDM</div>
                        <div class="text-xs text-zinc-400">Absensi CMS</div>
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
                <a href="{{ route('cms.peta-jabatan-real.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="network" class="h-4 w-4"></i>
                    Peta Jabatan Real
                </a>
                <a href="{{ route('cms.peta-jabatan-siasn.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="git-compare-arrows" class="h-4 w-4"></i>
                    Peta Jabatan SIASN
                </a>
                <a href="{{ route('cms.siasn.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="database-zap" class="h-4 w-4"></i>
                    SIASN Profil ASN
                </a>
                <a href="{{ route('cms.laporan-balai-kota.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm {{ $pageMode === 'balai-kota' ? 'bg-white/10 font-medium text-white' : 'text-zinc-300 hover:bg-white/10 hover:text-white' }}">
                    <i data-lucide="building-2" class="h-4 w-4"></i>
                    Laporan Balai Kota
                </a>
                <a href="{{ route('cms.laporan-apel-skpd.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm {{ $pageMode === 'apel-skpd' ? 'bg-white/10 font-medium text-white' : 'text-zinc-300 hover:bg-white/10 hover:text-white' }}">
                    <i data-lucide="clipboard-check" class="h-4 w-4"></i>
                    Laporan Apel SKPD
                </a>
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    ASN
                </a>
                <a href="{{ route('absensi-scraper.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="braces" class="h-4 w-4"></i>
                    API Scraper
                </a>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="no-print border-b border-zinc-200 bg-white">
                <div class="mx-auto flex max-w-[1400px] items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">{{ $pageTitle }}</h1>
                        <p class="mt-1 text-sm text-zinc-500">{{ $pageDescription }}</p>
                    </div>
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-800">
                        <i data-lucide="printer" class="h-4 w-4"></i>
                        Print
                    </button>
                </div>
            </header>

            <section class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="no-print mb-4 grid gap-4 lg:grid-cols-2 xl:grid-cols-[320px_320px_340px_minmax(0,1fr)]">
                    <form method="POST" action="{{ route($fetchRoute) }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        @csrf
                        @if ($showSkpdSelector)
                            <div data-selected-skpd-inputs>
                                @foreach ($selectedSkpdIds as $selectedSkpdId)
                                    <input type="hidden" name="skpd_ids[]" value="{{ $selectedSkpdId }}">
                                @endforeach
                            </div>
                        @endif
                        <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-900 text-white">
                                <i data-lucide="database-zap" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Ambil Apel {{ $reportScopeLabel }}</h2>
                                <p class="text-sm text-zinc-500">Mengambil laporan print harian untuk {{ strtolower($reportScopeLabel) }}.</p>
                            </div>
                        </div>

                        @if (isset($errors) && $errors->any())
                            <div class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        @if (is_array($result))
                            <div class="mt-4 rounded-md border {{ ($result['success'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-3 py-2 text-sm">
                                <div class="font-medium">Tersimpan {{ number_format($result['summary']['stored_rows'] ?? 0) }} baris.</div>
                                <div class="mt-1 text-xs">Berhasil {{ $result['summary']['success_count'] ?? 0 }} SKPD, gagal {{ $result['summary']['failed_count'] ?? 0 }} SKPD.</div>
                            </div>
                        @endif

                        <label class="mt-5 block text-sm font-medium text-zinc-700" for="fetch_date">Tanggal</label>
                        <input id="fetch_date" name="date" type="date" value="{{ old('date', $date) }}" required
                            class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">

                        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800">
                            <i data-lucide="download-cloud" class="h-4 w-4"></i>
                            Ambil & Susun Laporan
                        </button>
                    </form>

                    <form method="POST" action="{{ route($fetchHariBesarRoute) }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        @csrf
                        @if ($showSkpdSelector)
                            <div data-selected-skpd-inputs>
                                @foreach ($selectedSkpdIds as $selectedSkpdId)
                                    <input type="hidden" name="skpd_ids[]" value="{{ $selectedSkpdId }}">
                                @endforeach
                            </div>
                        @endif
                        <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-cyan-700 text-white">
                                <i data-lucide="calendar-check" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Ambil Apel Hari Besar {{ $reportScopeLabel }}</h2>
                                <p class="text-sm text-zinc-500">Mengambil kolom presensi hari besar untuk {{ strtolower($reportScopeLabel) }}.</p>
                            </div>
                        </div>

                        @if (is_array($hariBesarResult ?? null))
                            <div class="mt-4 rounded-md border {{ ($hariBesarResult['success'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-3 py-2 text-sm">
                                <div class="font-medium">Tersimpan {{ number_format($hariBesarResult['summary']['stored_rows'] ?? 0) }} baris.</div>
                                <div class="mt-1 text-xs">Presensi hari besar terisi {{ number_format($hariBesarResult['summary']['filled_rows'] ?? 0) }} baris. Berhasil {{ $hariBesarResult['summary']['success_count'] ?? 0 }} SKPD, gagal {{ $hariBesarResult['summary']['failed_count'] ?? 0 }} SKPD.</div>
                            </div>
                        @endif

                        <label class="mt-5 block text-sm font-medium text-zinc-700" for="hari_besar_date">Tanggal</label>
                        <input id="hari_besar_date" name="date" type="date" value="{{ old('date', $date) }}" required
                            class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">

                        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-800">
                            <i data-lucide="download-cloud" class="h-4 w-4"></i>
                            Ambil Apel Hari Besar {{ $reportScopeLabel }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route($fetchCutiRoute) }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        @csrf
                        @if ($showSkpdSelector)
                            <div data-selected-skpd-inputs>
                                @foreach ($selectedSkpdIds as $selectedSkpdId)
                                    <input type="hidden" name="skpd_ids[]" value="{{ $selectedSkpdId }}">
                                @endforeach
                            </div>
                        @endif
                        <input type="hidden" name="date" value="{{ $date }}">
                        <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-emerald-600 text-white">
                                <i data-lucide="calendar-plus" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Ambil Cuti {{ $reportScopeLabel }}</h2>
                                <p class="text-sm text-zinc-500">Mengambil cuti untuk {{ strtolower($reportScopeLabel) }}.</p>
                            </div>
                        </div>

                        @if (is_array($cutiResult ?? null))
                            <div class="mt-4 rounded-md border {{ ($cutiResult['success'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-3 py-2 text-sm">
                                @if (! empty($cutiResult['message']))
                                    <div class="font-medium">{{ $cutiResult['message'] }}</div>
                                @else
                                    <div class="font-medium">Tersimpan {{ number_format($cutiResult['summary']['stored_rows'] ?? 0) }} baris cuti.</div>
                                    <div class="mt-1 text-xs">Berhasil {{ $cutiResult['summary']['success_count'] ?? 0 }} SKPD, gagal {{ $cutiResult['summary']['failed_count'] ?? 0 }} SKPD.</div>
                                @endif
                            </div>
                        @endif

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="balai_cuti_date_start">Tanggal Awal</label>
                                <input id="balai_cuti_date_start" name="date_start" type="date" value="{{ old('date_start', $dateStart ?? $date) }}" required
                                    class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="balai_cuti_date_end">Tanggal Akhir</label>
                                <input id="balai_cuti_date_end" name="date_end" type="date" value="{{ old('date_end', $dateEnd ?? $date) }}" required
                                    class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                        </div>

                        <label class="mt-4 flex items-center gap-3 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-3 text-sm text-zinc-700">
                            <input type="checkbox" name="redact" value="1" class="h-4 w-4 rounded border-zinc-300 text-cyan-700 focus:ring-cyan-600">
                            Samarkan kolom sensitif sebelum simpan
                        </label>

                        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                            <i data-lucide="download-cloud" class="h-4 w-4"></i>
                            Ambil & Simpan Cuti
                        </button>
                    </form>

                    <form method="GET" action="{{ route($indexRoute) }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-100 text-zinc-700">
                                <i data-lucide="calendar-days" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Tampilkan Laporan</h2>
                                <p class="text-sm text-zinc-500">Pilih tanggal laporan yang sudah tersimpan.</p>
                            </div>
                        </div>
                        <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                            <input name="date" type="date" value="{{ $date }}" required
                                class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                                <i data-lucide="search" class="h-4 w-4"></i>
                                Tampilkan
                            </button>
                        </div>

                        @if ($showSkpdSelector)
                            <div class="mt-5 rounded-md border border-zinc-200 bg-zinc-50 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                        <input id="select_all_skpd" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-cyan-700 focus:ring-cyan-600">
                                        Pilih semua SKPD
                                    </label>
                                    <span id="selected_skpd_count" class="shrink-0 text-xs font-medium text-zinc-500"></span>
                                </div>
                                <input id="skpd_search" type="search" placeholder="Cari SKPD"
                                    class="mt-3 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                <div class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
                                    @foreach ($skpdOptions as $skpdOption)
                                        @php $skpdId = (int) ($skpdOption['id'] ?? 0); @endphp
                                        <label class="skpd-option flex items-start gap-2 rounded-md bg-white px-2 py-2 text-sm text-zinc-700 ring-1 ring-zinc-200" data-skpd-label="{{ \Illuminate\Support\Str::lower($skpdOption['label'] ?? '') }}">
                                            <input type="checkbox" name="skpd_ids[]" value="{{ $skpdId }}" class="skpd-option-checkbox mt-0.5 h-4 w-4 rounded border-zinc-300 text-cyan-700 focus:ring-cyan-600" @checked(in_array($skpdId, $selectedSkpdIds, true))>
                                            <span>{{ $skpdOption['label'] ?? ('SKPD ' . $skpdId) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </form>
                </div>

                <div class="print-sheet border border-zinc-300 bg-white px-10 py-12 shadow-sm">
                    <div class="text-center font-serif">
                        <div class="text-xl font-semibold">PEMERINTAH KOTA BANJARMASIN</div>
                        <div class="text-base font-semibold">BADAN KEPEGAWAIAN DAN PENGEMBANGAN SUMBER DAYA MANUSIA</div>
                        <div class="text-base font-semibold">KOTA BANJARMASIN</div>
                    </div>

                    <div class="mt-8 grid w-full max-w-md grid-cols-[80px_12px_1fr] text-sm">
                        <div>Tentang</div>
                        <div>:</div>
                        <div>{{ $printSubject }}</div>
                        <div>Tanggal</div>
                        <div>:</div>
                        <div>{{ \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y') }}</div>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[980px] border-collapse border border-black text-xs">
                            <thead>
                                <tr>
                                    <th class="border border-black px-2 py-3">NO</th>
                                    <th class="border border-black px-2 py-3">UNIT KERJA</th>
                                    <th class="border border-black px-2 py-3">JUMLAH ASN</th>
                                    <th class="border border-black px-2 py-3">TANPA<br>KETERANGAN</th>
                                    <th class="border border-black px-2 py-3">TUGAS<br>LUAR/TUGAS<br>BELAJAR/CUTI</th>
                                    <th class="border border-black px-2 py-3">TIDAK HADIR</th>
                                    <th class="border border-black px-2 py-3">HADIR</th>
                                    <th class="border border-black px-2 py-3">PERSENTASE</th>
                                </tr>
                                <tr>
                                    <th class="border border-black px-2 py-1">1</th>
                                    <th class="border border-black px-2 py-1">2</th>
                                    <th class="border border-black px-2 py-1">3</th>
                                    <th class="border border-black px-2 py-1">5</th>
                                    <th class="border border-black px-2 py-1">4</th>
                                    <th class="border border-black px-2 py-1">6</th>
                                    <th class="border border-black px-2 py-1">7</th>
                                    <th class="border border-black px-2 py-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="border border-black px-2 py-1 text-center">{{ $row['no'] }}</td>
                                        <td class="border border-black px-2 py-1">{{ $row['unit_kerja'] }}</td>
                                        <td class="border border-black px-2 py-1 text-center">{{ $row['jumlah_asn'] }}</td>
                                        <td class="border border-black px-2 py-1 text-center">{{ $row['tanpa_keterangan'] }}</td>
                                        <td class="border border-black px-2 py-1 text-center">{{ $row['tugas_cuti'] }}</td>
                                        <td class="border border-black px-2 py-1 text-center">{{ $row['tidak_hadir'] }}</td>
                                        <td class="border border-black px-2 py-1 text-center">{{ $row['hadir'] }}</td>
                                        <td class="border border-black px-2 py-1 text-center">{{ $row['persentase'] }}%</td>
                                    </tr>
                                @endforeach
                                <tr class="font-semibold">
                                    <td class="border border-black px-2 py-1"></td>
                                    <td class="border border-black px-2 py-1">Total</td>
                                    <td class="border border-black px-2 py-1 text-center">{{ $totals['jumlah_asn'] }}</td>
                                    <td class="border border-black px-2 py-1 text-center">{{ $totals['tanpa_keterangan'] }}</td>
                                    <td class="border border-black px-2 py-1 text-center">{{ $totals['tugas_cuti'] }}</td>
                                    <td class="border border-black px-2 py-1 text-center">{{ $totals['tidak_hadir'] }}</td>
                                    <td class="border border-black px-2 py-1 text-center">{{ $totals['hadir'] }}</td>
                                    <td class="border border-black px-2 py-1 text-center">{{ $totals['persentase'] }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-[11px] leading-relaxed text-zinc-700">
                        Catatan: Status hadir dihitung dari Apel Harian atau Apel Hari Besar. PPPK Paruh Waktu sudah wajib absensi dan dimasukkan dalam perhitungan kehadiran, tidak hadir, tanpa keterangan, serta persentase.
                    </p>
                </div>

                <div class="no-print mt-6 rounded-lg border border-zinc-200 bg-white shadow-sm">
                    <div class="border-b border-zinc-200 px-5 py-4">
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <h2 class="text-base font-semibold">Detail Pegawai</h2>
                                <p class="text-sm text-zinc-500">Status pegawai per unit kerja untuk tanggal {{ \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('d F Y') }}.</p>
                            </div>
                        </div>

                        <div class="mt-4 flex gap-2 overflow-x-auto pb-1" role="tablist">
                            @foreach ($details as $unit)
                                <button type="button"
                                    class="detail-tab shrink-0 rounded-md border px-3 py-2 text-left text-xs font-medium transition {{ $loop->first ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}"
                                    data-tab-target="{{ $unit['id'] }}"
                                    role="tab">
                                    <span class="block max-w-56 truncate">{{ $unit['label'] }}</span>
                                    <span class="mt-1 block text-[11px] opacity-75">
                                        {{ $unit['summary']['hadir'] }} hadir · {{ $unit['summary']['tugas_cuti'] }} cuti/TL · {{ $unit['summary']['tanpa_keterangan'] }} TK
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @foreach ($details as $unit)
                        <div id="{{ $unit['id'] }}" class="detail-panel {{ $loop->first ? 'block' : 'hidden' }}">
                            <div class="grid gap-3 border-b border-zinc-200 px-5 py-4 sm:grid-cols-4">
                                <div class="rounded-md bg-zinc-50 px-3 py-2">
                                    <div class="text-xs font-medium text-zinc-500">Jumlah ASN</div>
                                    <div class="mt-1 text-lg font-semibold">{{ number_format($unit['summary']['jumlah_asn']) }}</div>
                                </div>
                                <div class="rounded-md bg-emerald-50 px-3 py-2">
                                    <div class="text-xs font-medium text-emerald-700">Hadir</div>
                                    <div class="mt-1 text-lg font-semibold text-emerald-800">{{ number_format($unit['summary']['hadir']) }}</div>
                                </div>
                                <div class="rounded-md bg-amber-50 px-3 py-2">
                                    <div class="text-xs font-medium text-amber-700">Tugas/Cuti</div>
                                    <div class="mt-1 text-lg font-semibold text-amber-800">{{ number_format($unit['summary']['tugas_cuti']) }}</div>
                                </div>
                                <div class="rounded-md bg-rose-50 px-3 py-2">
                                    <div class="text-xs font-medium text-rose-700">Tanpa Keterangan</div>
                                    <div class="mt-1 text-lg font-semibold text-rose-800">{{ number_format($unit['summary']['tanpa_keterangan']) }}</div>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-[1380px] w-full divide-y divide-zinc-200 text-sm">
                                    <thead class="bg-zinc-50">
                                        <tr>
                                            <th class="w-12 px-4 py-3 text-left font-semibold text-zinc-600">No</th>
                                            <th class="w-48 px-4 py-3 text-left font-semibold text-zinc-600">NIP</th>
                                            <th class="w-64 px-4 py-3 text-left font-semibold text-zinc-600">Nama</th>
                                            <th class="w-24 px-4 py-3 text-left font-semibold text-zinc-600">Jenis</th>
                                            <th class="w-72 px-4 py-3 text-left font-semibold text-zinc-600">Jabatan</th>
                                            <th class="w-36 px-4 py-3 text-left font-semibold text-zinc-600">Apel Harian</th>
                                            <th class="w-40 px-4 py-3 text-left font-semibold text-zinc-600">Apel Hari Besar</th>
                                            <th class="w-52 px-4 py-3 text-left font-semibold text-zinc-600">Jenis Cuti/TL</th>
                                            <th class="w-48 px-4 py-3 text-left font-semibold text-zinc-600">Tanggal</th>
                                            <th class="w-40 px-4 py-3 text-left font-semibold text-zinc-600">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 bg-white">
                                        @forelse ($unit['rows'] as $detail)
                                            <tr class="hover:bg-cyan-50/50">
                                                <td class="px-4 py-3 text-zinc-500">{{ $loop->iteration }}</td>
                                                <td class="px-4 py-3 font-medium text-zinc-700">{{ $detail['nip'] ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-800">{{ $detail['nama'] ?: '-' }}</td>
                                                <td class="px-4 py-3">
                                                    @if (($detail['source'] ?? 'PNS') === 'PPPK')
                                                        <span class="inline-flex rounded-md bg-cyan-50 px-2 py-1 text-xs font-semibold text-cyan-700">PPPK</span>
                                                    @elseif (($detail['source'] ?? 'PNS') === 'PPPK Paruh Waktu')
                                                        <span class="inline-flex rounded-md bg-violet-50 px-2 py-1 text-xs font-semibold text-violet-700">PPPK Paruh Waktu</span>
                                                    @else
                                                        <span class="inline-flex rounded-md bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-600">PNS</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-zinc-600">{{ $detail['jabatan'] ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-600">{{ ($detail['apel_harian'] ?? $detail['apel'] ?? '-') ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-600">{{ ($detail['apel_hari_besar'] ?? '-') ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-600">{{ $detail['jenis_cuti'] ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-600">{{ $detail['tanggal_cuti'] ?: '-' }}</td>
                                                <td class="px-4 py-3">
                                                    @if ($detail['status'] === 'Hadir')
                                                        <span class="inline-flex rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Hadir</span>
                                                    @elseif ($detail['status'] === 'Tugas/Cuti')
                                                        <span class="inline-flex rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">Tugas/Cuti</span>
                                                    @else
                                                        <span class="inline-flex rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">Tanpa Keterangan</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="10" class="px-6 py-10 text-center text-sm text-zinc-500">Belum ada data pegawai untuk unit ini.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </main>
    </div>

    <script>
        lucide.createIcons();

        const skpdCheckboxes = Array.from(document.querySelectorAll('.skpd-option-checkbox'));
        const selectAllSkpd = document.getElementById('select_all_skpd');
        const selectedSkpdCount = document.getElementById('selected_skpd_count');
        const skpdSearch = document.getElementById('skpd_search');
        const hiddenSkpdContainers = Array.from(document.querySelectorAll('[data-selected-skpd-inputs]'));

        function selectedSkpdValues() {
            return skpdCheckboxes
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.value);
        }

        function syncSkpdSelection() {
            const values = selectedSkpdValues();

            hiddenSkpdContainers.forEach((container) => {
                container.replaceChildren();

                values.forEach((value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'skpd_ids[]';
                    input.value = value;
                    container.appendChild(input);
                });
            });

            if (selectAllSkpd) {
                selectAllSkpd.checked = values.length > 0 && values.length === skpdCheckboxes.length;
                selectAllSkpd.indeterminate = values.length > 0 && values.length < skpdCheckboxes.length;
            }

            if (selectedSkpdCount) {
                selectedSkpdCount.textContent = `${values.length}/${skpdCheckboxes.length}`;
            }
        }

        skpdCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', syncSkpdSelection);
        });

        if (selectAllSkpd) {
            selectAllSkpd.addEventListener('change', () => {
                skpdCheckboxes.forEach((checkbox) => {
                    checkbox.checked = selectAllSkpd.checked;
                });

                syncSkpdSelection();
            });
        }

        if (skpdSearch) {
            skpdSearch.addEventListener('input', () => {
                const keyword = skpdSearch.value.trim().toLowerCase();

                document.querySelectorAll('.skpd-option').forEach((option) => {
                    option.classList.toggle('hidden', keyword !== '' && ! (option.dataset.skpdLabel || '').includes(keyword));
                });
            });
        }

        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', syncSkpdSelection);
        });

        syncSkpdSelection();

        document.querySelectorAll('.detail-tab').forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.dataset.tabTarget;

                document.querySelectorAll('.detail-tab').forEach((tab) => {
                    tab.classList.remove('border-zinc-900', 'bg-zinc-900', 'text-white');
                    tab.classList.add('border-zinc-200', 'bg-white', 'text-zinc-700');
                });

                button.classList.add('border-zinc-900', 'bg-zinc-900', 'text-white');
                button.classList.remove('border-zinc-200', 'bg-white', 'text-zinc-700');

                document.querySelectorAll('.detail-panel').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.id !== target);
                    panel.classList.toggle('block', panel.id === target);
                });
            });
        });
    </script>
</body>
</html>
