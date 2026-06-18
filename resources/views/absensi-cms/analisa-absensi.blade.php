<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analisa Absensi - Disiplin BKPSDM CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900">
    <div class="flex min-h-screen">
        <aside class="hidden w-72 shrink-0 border-r border-zinc-200 bg-zinc-950 text-white lg:block">
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
                <a href="{{ route('cms.analisa-absensi.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
                    <i data-lucide="radar" class="h-4 w-4"></i>
                    Analisa Absensi
                </a>
                <a href="{{ route('cms.peta-jabatan-real.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="network" class="h-4 w-4"></i>
                    Peta Jabatan Real
                </a>
                <a href="{{ route('cms.siasn.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="database-zap" class="h-4 w-4"></i>
                    SIASN Profil PNS
                </a>
                <a href="{{ route('cms.laporan-balai-kota.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="building-2" class="h-4 w-4"></i>
                    Laporan Balai Kota
                </a>
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    ASN
                </a>
                <a href="{{ route('absensi-scraper.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="braces" class="h-4 w-4"></i>
                    API Scraper
                </a>
                <a href="{{ route('disiplin-scraper.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="network" class="h-4 w-4"></i>
                    Disiplin Tools
                </a>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="border-b border-zinc-200 bg-white">
                <div class="mx-auto flex max-w-[1800px] items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">Analisa Absensi</h1>
                        <p class="mt-1 text-sm text-zinc-500">Anomali pegawai aktif yang tidak absen masuk/pulang dan tidak tercatat cuti/tugas.</p>
                    </div>
                    <a href="{{ route('cms.analisa-absensi.export', request()->query()) }}" class="inline-flex items-center gap-2 rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-800">
                        <i data-lucide="download" class="h-4 w-4"></i>
                        Export
                    </a>
                </div>
            </header>

            <section class="mx-auto max-w-[1800px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">Pegawai Aktif</p>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($summary['total_pegawai']) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">Tidak Absen Masuk</p>
                        <p class="mt-3 text-2xl font-semibold text-rose-700">{{ number_format($summary['tidak_absen_masuk']) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">Tidak Absen Pulang</p>
                        <p class="mt-3 text-2xl font-semibold text-rose-700">{{ number_format($summary['tidak_absen_pulang']) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">Berturut-turut</p>
                        <p class="mt-3 text-2xl font-semibold text-orange-700">{{ number_format($summary['berturut_turut']) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">Terlambat/Pulang Cepat</p>
                        <p class="mt-3 text-2xl font-semibold text-amber-700">{{ number_format($summary['terlambat'] + $summary['pulang_cepat']) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-medium text-zinc-500">Hasil Filter</p>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($summary['filtered_anomali']) }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <form method="GET" action="{{ route('cms.analisa-absensi.index') }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-900 text-white">
                                <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Filter Analisa</h2>
                                <p class="text-sm text-zinc-500">Pilih periode, SKPD, dan pola anomali.</p>
                            </div>
                        </div>

                        <label class="mt-5 block text-sm font-medium text-zinc-700" for="date_start">Tanggal Mulai</label>
                        <input id="date_start" name="date_start" type="date" value="{{ $dateStart }}" required
                            class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">

                        <label class="mt-4 block text-sm font-medium text-zinc-700" for="date_end">Tanggal Selesai</label>
                        <input id="date_end" name="date_end" type="date" value="{{ $dateEnd }}" required
                            class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">

                        <label class="mt-4 block text-sm font-medium text-zinc-700" for="skpd_id">SKPD</label>
                        <select id="skpd_id" name="skpd_id" class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            <option value="">Semua SKPD</option>
                            @foreach ($skpdOptions as $skpd)
                                <option value="{{ $skpd['id'] }}" @selected((string) request('skpd_id') === (string) $skpd['id'])>{{ $skpd['label'] }}</option>
                            @endforeach
                        </select>

                        <label class="mt-4 block text-sm font-medium text-zinc-700" for="source">Jenis ASN</label>
                        <select id="source" name="source" class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            <option value="all" @selected(request('source', 'all') === 'all')>PNS dan PPPK</option>
                            <option value="pns" @selected(request('source') === 'pns')>PNS</option>
                            <option value="pppk" @selected(request('source') === 'pppk')>PPPK</option>
                        </select>

                        <label class="mt-4 block text-sm font-medium text-zinc-700" for="type">Jenis Anomali</label>
                        <select id="type" name="type" class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            <option value="all" @selected(request('type', 'all') === 'all')>Semua Anomali</option>
                            <option value="Tidak Absen Masuk" @selected(request('type') === 'Tidak Absen Masuk')>Tidak Absen Masuk</option>
                            <option value="Tidak Absen Pulang" @selected(request('type') === 'Tidak Absen Pulang')>Tidak Absen Pulang</option>
                            <option value="Tidak Absen Berturut-turut" @selected(request('type') === 'Tidak Absen Berturut-turut')>Tidak Absen Berturut-turut</option>
                            <option value="Sering Terlambat" @selected(request('type') === 'Sering Terlambat')>Sering Terlambat</option>
                            <option value="Pulang Cepat" @selected(request('type') === 'Pulang Cepat')>Pulang Cepat</option>
                        </select>

                        <label class="mt-4 block text-sm font-medium text-zinc-700" for="unit_kerja">Unit Kerja</label>
                        <input id="unit_kerja" name="unit_kerja" type="text" value="{{ request('unit_kerja') }}" placeholder="Nama unit persis bila diperlukan"
                            class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="min_consecutive_days">Minimal Berturut</label>
                                <input id="min_consecutive_days" name="min_consecutive_days" type="number" min="2" value="{{ request('min_consecutive_days', 3) }}"
                                    class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="min_occurrences">Minimal Sering</label>
                                <input id="min_occurrences" name="min_occurrences" type="number" min="1" value="{{ request('min_occurrences', 3) }}"
                                    class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                        </div>

                        <div class="mt-4 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-3 text-sm text-zinc-700">
                            <div class="font-medium">Jadwal deteksi otomatis</div>
                            <div class="mt-1 text-xs text-zinc-500">5 hari kerja: Senin-Kamis 08:00-16:30, Jumat 07:30-11:00. 6 hari kerja/sekolah ikut menghitung Sabtu 08:00-16:30. Shift, RS, dan Puskesmas tidak dinilai telat/pulang cepat berbasis jam kantor.</div>
                        </div>

                        <label class="mt-4 block text-sm font-medium text-zinc-700" for="search">Cari</label>
                        <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Nama, NIP, jabatan, alasan"
                            class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">

                        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800">
                            <i data-lucide="search" class="h-4 w-4"></i>
                            Tampilkan
                        </button>
                    </form>

                    <div class="min-w-0 rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="border-b border-zinc-200 px-5 py-4">
                            <h2 class="text-base font-semibold">Rekap Anomali per SKPD</h2>
                            <p class="mt-1 text-sm text-zinc-500">{{ \Carbon\Carbon::parse($dateStart)->locale('id')->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($dateEnd)->locale('id')->translatedFormat('d F Y') }} · {{ number_format($summary['hari_data']) }} hari kerja efektif</p>
                            <p class="mt-1 text-xs text-zinc-400">Tanggal merah dan cuti bersama nasional otomatis dikecualikan. Weekend mengikuti jenis presensi masing-masing pegawai.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                                <thead class="bg-zinc-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-zinc-600">SKPD</th>
                                        <th class="px-4 py-3 text-right font-semibold text-zinc-600">Aktif</th>
                                        <th class="px-4 py-3 text-right font-semibold text-zinc-600">Tanpa Absen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 bg-white">
                                    @forelse ($skpdSummaries as $row)
                                        <tr class="hover:bg-cyan-50/50">
                                            <td class="px-4 py-3 text-zinc-700">{{ $row['skpd'] }}</td>
                                            <td class="px-4 py-3 text-right text-zinc-700">{{ number_format($row['total_pegawai']) }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-rose-700">{{ number_format($row['anomali']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-10 text-center text-sm text-zinc-500">Belum ada data untuk filter ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-6 rounded-lg border border-zinc-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-zinc-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold">Daftar Anomali</h2>
                            <p class="text-sm text-zinc-500">Pegawai aktif dengan pola tidak absen masuk, tidak absen pulang, berturut-turut, terlambat, atau pulang cepat.</p>
                        </div>
                        <div class="inline-flex items-center gap-2 rounded-md bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">
                            <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                            {{ number_format($summary['filtered_anomali']) }} pola perlu tindak lanjut
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-[1800px] table-fixed divide-y divide-zinc-200 text-sm">
                            <thead class="bg-zinc-50">
                                <tr>
                                    <th class="w-14 px-4 py-3 text-left font-semibold text-zinc-600">No</th>
                                    <th class="w-28 px-4 py-3 text-left font-semibold text-zinc-600">Kategori</th>
                                    <th class="w-52 px-4 py-3 text-left font-semibold text-zinc-600">Alasan</th>
                                    <th class="w-28 px-4 py-3 text-left font-semibold text-zinc-600">Jumlah</th>
                                    <th class="w-28 px-4 py-3 text-left font-semibold text-zinc-600">Streak</th>
                                    <th class="w-24 px-4 py-3 text-left font-semibold text-zinc-600">Jenis</th>
                                    <th class="w-72 px-4 py-3 text-left font-semibold text-zinc-600">SKPD</th>
                                    <th class="w-80 px-4 py-3 text-left font-semibold text-zinc-600">Unit Kerja</th>
                                    <th class="w-48 px-4 py-3 text-left font-semibold text-zinc-600">NIP</th>
                                    <th class="w-72 px-4 py-3 text-left font-semibold text-zinc-600">Nama</th>
                                    <th class="w-72 px-4 py-3 text-left font-semibold text-zinc-600">Jabatan</th>
                                    <th class="w-96 px-4 py-3 text-left font-semibold text-zinc-600">Detail Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 bg-white">
                                @forelse ($rows as $row)
                                    <tr class="hover:bg-cyan-50/50">
                                        <td class="px-4 py-3 text-zinc-500">{{ $rows->firstItem() + $loop->index }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ $row['kategori'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-700">{{ $row['alasan'] }}</td>
                                        <td class="px-4 py-3 text-zinc-700">{{ number_format($row['jumlah_hari']) }}</td>
                                        <td class="px-4 py-3 text-zinc-700">{{ $row['streak_hari'] > 0 ? number_format($row['streak_hari']) : '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-md bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-700">{{ $row['source'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-700">{{ $row['nama_skpd'] ?: '-' }}</td>
                                        <td class="px-4 py-3 text-zinc-700">{{ $row['unit_kerja'] ?: '-' }}</td>
                                        <td class="px-4 py-3 font-medium text-zinc-700">{{ $row['nip'] ?: '-' }}</td>
                                        <td class="px-4 py-3 text-zinc-800">{{ $row['nama'] ?: '-' }}</td>
                                        <td class="px-4 py-3 text-zinc-600">{{ $row['jabatan'] ?: '-' }}</td>
                                        <td class="px-4 py-3 text-zinc-600">{{ $row['detail_tanggal'] ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" class="px-6 py-12 text-center text-sm text-zinc-500">Tidak ada anomali untuk filter ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-zinc-200 px-5 py-3">
                        {{ $rows->links() }}
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
