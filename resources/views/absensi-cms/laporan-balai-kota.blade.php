<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Balai Kota - TPP Insight CMS</title>
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
                        <div class="text-sm font-semibold tracking-wide">TPP Insight</div>
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
                    Laporan Absensi
                </a>
                <a href="{{ route('cms.laporan-balai-kota.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
                    <i data-lucide="building-2" class="h-4 w-4"></i>
                    Laporan Balai Kota
                </a>
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    Pegawai
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
                        <h1 class="text-xl font-semibold tracking-tight">Laporan Balai Kota</h1>
                        <p class="mt-1 text-sm text-zinc-500">Rekap apel dengan pencocokan cuti, tugas luar, sakit, dan diklat.</p>
                    </div>
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-800">
                        <i data-lucide="printer" class="h-4 w-4"></i>
                        Print
                    </button>
                </div>
            </header>

            <section class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="no-print mb-4 grid gap-4 lg:grid-cols-[340px_minmax(0,1fr)]">
                    <form method="POST" action="{{ route('cms.laporan-balai-kota.fetch') }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        @csrf
                        <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-900 text-white">
                                <i data-lucide="database-zap" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Ambil Apel Balai Kota</h2>
                                <p class="text-sm text-zinc-500">Mengambil laporan print harian untuk SKPD lingkungan balai kota.</p>
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

                    <form method="GET" action="{{ route('cms.laporan-balai-kota.index') }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
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
                        <div>Apel Pagi</div>
                        <div>Tanggal</div>
                        <div>:</div>
                        <div>{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}</div>
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
                </div>
            </section>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
