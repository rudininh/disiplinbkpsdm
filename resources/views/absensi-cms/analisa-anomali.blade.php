@php
    $sessionResult = session('result');
    $displayResult = $sessionResult ?? $result;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analisa Anomali Pegawai - Disiplin BKPSDM CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
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
                <a href="{{ route('cms.laporan-balai-kota.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="building-2" class="h-4 w-4"></i>
                    Laporan Balai Kota
                </a>
                <a href="{{ route('cms.laporan-apel-skpd.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="clipboard-check" class="h-4 w-4"></i>
                    Laporan Apel SKPD
                </a>
                <a href="{{ route('cms.analisa-anomali.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
                    <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                    Analisa Anomali
                </a>
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    ASN
                </a>
                <a href="{{ route('cms.super-hukdis.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="file-badge" class="h-4 w-4"></i>
                    Super Hukdis
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
                        <h1 class="text-xl font-semibold tracking-tight">Analisa Anomali Pegawai</h1>
                        <p class="mt-1 text-sm text-zinc-500">Bandingkan data pegawai di database dengan Excel SIASN terbaru. Identifikasi pegawai yang tidak ada di SIASN.</p>
                    </div>
                    <a href="{{ route('dashboard') }}" class="hidden items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600 hover:bg-white sm:flex">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Dashboard
                    </a>
                </div>
            </header>

            <section class="mx-auto max-w-[1800px] px-4 py-6 sm:px-6 lg:px-8">

                {{-- Flash / Result Message --}}
                @if ($displayResult)
                    <div class="mb-6 rounded-lg border px-4 py-3 text-sm {{ ($displayResult['success'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' }}">
                        <div class="flex items-center gap-2">
                            <i data-lucide="{{ ($displayResult['success'] ?? false) ? 'check-circle-2' : 'alert-circle' }}" class="h-4 w-4 shrink-0"></i>
                            <span>{{ $displayResult['message'] ?? '' }}</span>
                        </div>
                    </div>
                @endif

                @if (!$success)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-center">
                        <i data-lucide="file-warning" class="mx-auto h-12 w-12 text-amber-400"></i>
                        <p class="mt-3 text-amber-800">{{ $message }}</p>
                        <p class="mt-1 text-sm text-amber-600">Letakkan file <code class="rounded bg-amber-100 px-1">.xlsx</code> hasil tarik data dari SIASN di folder <code class="rounded bg-amber-100 px-1">datapegawai/</code></p>
                    </div>
                @else
                    {{-- Stats Cards --}}
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-zinc-500">Total di Database</p>
                                <i data-lucide="database" class="h-5 w-5 text-blue-600"></i>
                            </div>
                            <p class="mt-3 text-2xl font-semibold">{{ number_format($stats['total_db'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-zinc-500">Total di Excel SIASN</p>
                                <i data-lucide="file-spreadsheet" class="h-5 w-5 text-emerald-600"></i>
                            </div>
                            <p class="mt-3 text-2xl font-semibold">{{ number_format($stats['total_excel'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-zinc-500">Cocok / Matched</p>
                                <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600"></i>
                            </div>
                            <p class="mt-3 text-2xl font-semibold text-emerald-700">{{ number_format($stats['matched'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-zinc-500">Anomali (di DB, tidak di Excel)</p>
                                <i data-lucide="alert-triangle" class="h-5 w-5 text-red-600"></i>
                            </div>
                            <p class="mt-3 text-2xl font-semibold text-red-700">{{ number_format($stats['anomaly_count'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-zinc-500">File Excel</p>
                                <i data-lucide="file" class="h-5 w-5 text-zinc-500"></i>
                            </div>
                            <p class="mt-3 break-words text-sm font-semibold text-zinc-800">{{ $excelFile }}</p>
                        </div>
                    </div>

                    {{-- Severity Breakdown --}}
                    @if (!empty($stats['by_severity']))
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-3 w-3 rounded-full bg-red-500"></span>
                                <span class="text-sm font-medium text-red-800">Severity Tinggi</span>
                            </div>
                            <p class="mt-2 text-2xl font-bold text-red-700">{{ number_format($stats['by_severity']['tinggi'] ?? 0) }}</p>
                            <p class="text-xs text-red-600">Kemungkinan pensiun, non-ASN, nonaktif</p>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-3 w-3 rounded-full bg-amber-500"></span>
                                <span class="text-sm font-medium text-amber-800">Severity Sedang</span>
                            </div>
                            <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format($stats['by_severity']['sedang'] ?? 0) }}</p>
                            <p class="text-xs text-amber-600">Mendekati pensiun, NIP tidak standar</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-3 w-3 rounded-full bg-blue-500"></span>
                                <span class="text-sm font-medium text-blue-800">Severity Rendah</span>
                            </div>
                            <p class="mt-2 text-2xl font-bold text-blue-700">{{ number_format($stats['by_severity']['rendah'] ?? 0) }}</p>
                            <p class="text-xs text-blue-600">Perlu verifikasi manual</p>
                        </div>
                    </div>
                    @endif

                    {{-- Search & Filter --}}
                    <div class="mt-6 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <form method="GET" action="{{ route('cms.analisa-anomali.index') }}" class="flex flex-wrap items-end gap-3">
                            <div class="flex-1">
                                <label for="search" class="mb-1 block text-xs font-medium text-zinc-600">Cari (NIP / Nama / SKPD / Jabatan)</label>
                                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Ketik untuk mencari..."
                                       class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                            </div>
                            <div>
                                <label for="severity" class="mb-1 block text-xs font-medium text-zinc-600">Severity</label>
                                <select name="severity" id="severity" class="rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                                    <option value="">Semua</option>
                                    <option value="tinggi" {{ request('severity') === 'tinggi' ? 'selected' : '' }}>🔴 Tinggi</option>
                                    <option value="sedang" {{ request('severity') === 'sedang' ? 'selected' : '' }}>🟡 Sedang</option>
                                    <option value="rendah" {{ request('severity') === 'rendah' ? 'selected' : '' }}>🔵 Rendah</option>
                                </select>
                            </div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">
                                <i data-lucide="search" class="h-4 w-4"></i>
                                Filter
                            </button>
                            @if (request('search') || request('severity'))
                                <a href="{{ route('cms.analisa-anomali.index') }}" class="inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-600 hover:bg-zinc-50">
                                    <i data-lucide="x" class="h-4 w-4"></i>
                                    Reset
                                </a>
                            @endif
                        </form>
                    </div>

                    {{-- Anomaly Table --}}
                    <div class="mt-6 rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <form method="POST" action="{{ route('cms.analisa-anomali.delete') }}" id="deleteForm"
                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus pegawai terpilih?\n\nData akan dihapus dari:\n- absensi_pegawais\n- siasn_pns_profiles\n- siasn_absensi_location_employees\n\nTindakan ini TIDAK BISA dibatalkan!')">
                            @csrf

                            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-medium text-zinc-700">
                                        {{ number_format($anomalies->total()) }} pegawai anomali
                                    </span>
                                    <span class="text-xs text-zinc-400">(Halaman {{ $anomalies->currentPage() }} dari {{ $anomalies->lastPage() }})</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="toggleAll()" class="inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-600 hover:bg-zinc-50">
                                        <i data-lucide="check-square" class="h-3.5 w-3.5"></i>
                                        Pilih Semua
                                    </button>
                                    <button type="button" onclick="selectSeverity('tinggi')" class="inline-flex items-center gap-1 rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-xs text-red-700 hover:bg-red-100">
                                        Pilih Severity Tinggi
                                    </button>
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50" id="deleteBtn">
                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                        Hapus Terpilih
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[1200px] text-sm">
                                    <thead>
                                        <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                            <th class="px-4 py-3 text-center" style="width:40px">
                                                <input type="checkbox" id="checkAll" onchange="toggleAll()" class="rounded">
                                            </th>
                                            <th class="px-4 py-3">No</th>
                                            <th class="px-4 py-3">NIP</th>
                                            <th class="px-4 py-3">Nama</th>
                                            <th class="px-4 py-3">SKPD</th>
                                            <th class="px-4 py-3">Jabatan</th>
                                            <th class="px-4 py-3">Pangkat</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Severity</th>
                                            <th class="px-4 py-3">Kemungkinan Penyebab</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100">
                                        @forelse ($anomalies as $index => $anomaly)
                                        @php
                                            $rowBg = match($anomaly['severity']) {
                                                'tinggi' => 'bg-red-50/50',
                                                'sedang' => 'bg-amber-50/50',
                                                default => '',
                                            };
                                        @endphp
                                        <tr class="{{ $rowBg }} hover:bg-zinc-50" data-severity="{{ $anomaly['severity'] }}">
                                            <td class="px-4 py-2.5 text-center">
                                                <input type="checkbox" name="ids[]" value="{{ $anomaly['id'] }}" class="anomaly-check rounded">
                                            </td>
                                            <td class="px-4 py-2.5 text-zinc-400">{{ $anomalies->firstItem() + $index }}</td>
                                            <td class="px-4 py-2.5 font-mono text-xs">{{ $anomaly['nip'] }}</td>
                                            <td class="px-4 py-2.5 font-medium">{{ $anomaly['nama'] }}</td>
                                            <td class="px-4 py-2.5 text-xs">{{ Str::limit($anomaly['skpd'], 40) }}</td>
                                            <td class="px-4 py-2.5 text-xs">{{ Str::limit($anomaly['jabatan'], 35) }}</td>
                                            <td class="px-4 py-2.5 text-xs">{{ $anomaly['pangkat_golongan'] ?? '-' }}</td>
                                            <td class="px-4 py-2.5">
                                                @if (($anomaly['status_pegawai'] ?? '-') === 'Nonaktif')
                                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Nonaktif</span>
                                                @elseif (($anomaly['status_pegawai'] ?? '-') === 'Aktif')
                                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                                                @else
                                                    <span class="text-xs text-zinc-400">{{ $anomaly['status_pegawai'] ?? '-' }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5">
                                                @if ($anomaly['severity'] === 'tinggi')
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>Tinggi
                                                    </span>
                                                @elseif ($anomaly['severity'] === 'sedang')
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Sedang
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>Rendah
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <div class="space-y-1">
                                                    @foreach ($anomaly['reasons'] as $reason)
                                                    <div class="text-xs {{ $reason['severity'] === 'tinggi' ? 'text-red-700' : ($reason['severity'] === 'sedang' ? 'text-amber-700' : 'text-zinc-600') }}">
                                                        <span class="font-medium">{{ $reason['label'] }}</span>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="10" class="px-4 py-12 text-center text-zinc-400">
                                                <i data-lucide="check-circle-2" class="mx-auto h-10 w-10 text-emerald-400"></i>
                                                <p class="mt-2 text-sm">Tidak ada anomali ditemukan. Semua data pegawai cocok dengan Excel SIASN.</p>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </form>

                        {{-- Pagination --}}
                        @if ($anomalies->hasPages())
                        <div class="border-t border-zinc-200 px-4 py-3">
                            {{ $anomalies->links() }}
                        </div>
                        @endif
                    </div>

                    {{-- Reason Breakdown --}}
                    @if (!empty($stats['by_reason']))
                    <div class="mt-6 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-3 text-sm font-semibold text-zinc-700">Ringkasan Penyebab Anomali</h3>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($stats['by_reason'] as $reason => $count)
                            <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                                <span class="text-xs text-zinc-600">{{ Str::limit($reason, 50) }}</span>
                                <span class="ml-2 inline-flex items-center rounded-full bg-zinc-200 px-2 py-0.5 text-xs font-bold text-zinc-700">{{ number_format($count) }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endif

            </section>
        </main>
    </div>

    <script>
        lucide.createIcons();

        function toggleAll() {
            const checkAll = document.getElementById('checkAll');
            const checks = document.querySelectorAll('.anomaly-check');
            const newState = !Array.from(checks).every(c => c.checked);
            checks.forEach(c => c.checked = newState);
            checkAll.checked = newState;
        }

        function selectSeverity(severity) {
            // Uncheck all first
            document.querySelectorAll('.anomaly-check').forEach(c => c.checked = false);
            // Check only matching severity
            document.querySelectorAll(`tr[data-severity="${severity}"] .anomaly-check`).forEach(c => c.checked = true);
            document.getElementById('checkAll').checked = false;
        }
    </script>
</body>
</html>
