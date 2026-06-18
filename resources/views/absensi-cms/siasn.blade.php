<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIASN Profil PNS - Disiplin BKPSDM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900">
    <div class="flex min-h-screen">
        <aside class="hidden w-72 shrink-0 border-r border-zinc-200 bg-zinc-950 text-white lg:block">
            <div class="px-6 py-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-400 text-zinc-950">
                        <i data-lucide="id-card" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold tracking-wide">Disiplin BKPSDM</div>
                        <div class="text-xs text-zinc-400">SIASN Tools</div>
                    </div>
                </div>
            </div>

            <nav class="space-y-1 px-3">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                    Dashboard
                </a>
                <a href="{{ route('cms.peta-jabatan-real.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="network" class="h-4 w-4"></i>
                    Peta Jabatan Real
                </a>
                <a href="{{ route('cms.siasn.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
                    <i data-lucide="database-zap" class="h-4 w-4"></i>
                    SIASN Profil PNS
                </a>
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    ASN
                </a>
                <a href="{{ route('cms.analisa-absensi.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="radar" class="h-4 w-4"></i>
                    Analisa Absensi
                </a>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="border-b border-zinc-200 bg-white">
                <div class="px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight">SIASN Profil PNS</h1>
                            <p class="mt-1 text-sm text-zinc-500">Ambil sementara data jabatan dan unit kerja dari SIASN, lalu simpan ke database lokal.</p>
                        </div>
                        <a href="https://siasn-instansi.bkn.go.id/peremajaan/profil/pns/" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                            <i data-lucide="external-link" class="h-4 w-4"></i>
                            Buka SIASN
                        </a>
                    </div>
                </div>
            </header>

            <section class="px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Data Tersimpan</p>
                            <i data-lucide="database" class="h-5 w-5 text-cyan-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($totalRows) }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Terakhir Ambil</p>
                            <i data-lucide="clock-3" class="h-5 w-5 text-emerald-600"></i>
                        </div>
                        <p class="mt-3 break-words text-sm font-semibold text-zinc-800">
                            {{ $lastFetchedAt ? \Carbon\Carbon::parse($lastFetchedAt)->format('Y-m-d H:i:s') : 'Belum ada data' }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Unit Cocok</p>
                            <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($educationSummary['unit_cocok'] ?? 0) }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Lokasi Bukan Unit</p>
                            <i data-lucide="map-pin-off" class="h-5 w-5 text-amber-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($educationSummary['lokasi_bukan_unit'] ?? 0) }}</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if ($result)
                    <div class="mt-4 rounded-md border {{ $result['success'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }} p-4 text-sm">
                        {{ $result['message'] ?? 'Selesai.' }}
                    </div>
                @endif

                <div class="mt-4 grid gap-4 xl:grid-cols-[420px_minmax(0,1fr)]">
                    <div class="space-y-4">
                        <form method="POST" action="{{ route('cms.siasn.fetch') }}" class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            @csrf
                            <div class="flex items-center gap-2 border-b border-zinc-200 pb-3">
                                <i data-lucide="download-cloud" class="h-5 w-5 text-cyan-700"></i>
                                <h2 class="text-sm font-semibold">Ambil Data Per NIP</h2>
                            </div>

                            <label class="mt-4 block text-sm font-medium text-zinc-700" for="nip">NIP</label>
                            <input id="nip" name="nip" value="{{ old('nip') }}" inputmode="numeric" maxlength="18" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-100" placeholder="199711282020121001">

                            <label class="mt-4 block text-sm font-medium text-zinc-700" for="bearer_token">Token SIASN</label>
                            <textarea id="bearer_token" name="bearer_token" rows="5" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-100" placeholder="Bearer eyJ... atau langsung eyJ..."></textarea>
                            <p class="mt-2 text-xs leading-5 text-zinc-500">Login manual di SIASN, salin token aktif dari browser, lalu tempel di sini. Token dipakai sekali untuk request dan tidak disimpan ke database.</p>

                            <button type="submit" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">
                                <i data-lucide="cloud-download" class="h-4 w-4"></i>
                                Ambil Jabatan & Unit Kerja
                            </button>
                        </form>

                        <form method="POST" action="{{ route('cms.siasn.sync-education-locations') }}" class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                            @csrf
                            <div class="flex items-center gap-2 border-b border-zinc-200 pb-3">
                                <i data-lucide="school" class="h-5 w-5 text-emerald-700"></i>
                                <h2 class="text-sm font-semibold">Sinkron Lokasi Dinas Pendidikan</h2>
                            </div>

                            <label class="mt-4 block text-sm font-medium text-zinc-700" for="education_bearer_token">Token SIASN</label>
                            <textarea id="education_bearer_token" name="bearer_token" rows="5" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Bearer eyJ..."></textarea>

                            <label class="mt-4 block text-sm font-medium text-zinc-700" for="pegawai_limit">Batas NIP Test</label>
                            <input id="pegawai_limit" name="pegawai_limit" type="number" min="1" max="500" value="{{ old('pegawai_limit', 20) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            <label class="mt-3 flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700">
                                <input type="checkbox" name="school_only" value="1" class="h-4 w-4 rounded border-zinc-300 text-emerald-700 focus:ring-emerald-600" checked>
                                Hanya lokasi sekolah
                            </label>
                            <p class="mt-2 text-xs leading-5 text-zinc-500">Ambil lokasi absensi Dinas Pendidikan, baca NIP pegawai per lokasi, lalu isi unit organisasi dari SIASN. Nama lokasi seperti HARDIKNAS akan ditandai sebagai lokasi bukan unit bila tidak cocok dengan Unit Organisasi SIASN.</p>

                            <button type="submit" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                                <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                Sinkron Lokasi & SIASN
                            </button>
                        </form>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-zinc-200 p-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-sm font-semibold">Database SIASN Lokal</h2>
                                <p class="mt-1 text-xs text-zinc-500">Data terakhir yang berhasil diambil dari profil PNS SIASN.</p>
                            </div>
                            <form method="GET" action="{{ route('cms.siasn.index') }}" class="flex min-w-0 gap-2">
                                <input name="q" value="{{ $search }}" class="min-w-0 rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-100" placeholder="Cari NIP, nama, jabatan">
                                <button class="inline-flex items-center justify-center rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50" type="submit">
                                    <i data-lucide="search" class="h-4 w-4"></i>
                                </button>
                            </form>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">
                                    <tr>
                                        <th class="px-4 py-3 font-semibold">NIP</th>
                                        <th class="px-4 py-3 font-semibold">Nama</th>
                                        <th class="px-4 py-3 font-semibold">ASN</th>
                                        <th class="px-4 py-3 font-semibold">Jabatan</th>
                                        <th class="px-4 py-3 font-semibold">Jenis</th>
                                        <th class="px-4 py-3 font-semibold">Unit Kerja</th>
                                        <th class="px-4 py-3 font-semibold">Unit Induk</th>
                                        <th class="px-4 py-3 font-semibold">Diambil</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 bg-white">
                                    @forelse ($profiles as $profile)
                                        <tr class="hover:bg-zinc-50">
                                            <td class="whitespace-nowrap px-4 py-3 font-medium text-zinc-900">{{ $profile->nip }}</td>
                                            <td class="px-4 py-3">{{ $profile->nama ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ $profile->jenis_asn ?? '-' }}</td>
                                            <td class="min-w-64 px-4 py-3">{{ $profile->jabatan ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ $profile->jenis_jabatan ?? '-' }}</td>
                                            <td class="min-w-72 px-4 py-3">{{ $profile->unit_organisasi ?? '-' }}</td>
                                            <td class="min-w-72 px-4 py-3">{{ $profile->unit_organisasi_induk ?? '-' }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-zinc-500">{{ optional($profile->fetched_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-4 py-10 text-center text-sm text-zinc-500">Belum ada data SIASN yang tersimpan.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-zinc-200 p-4">
                            {{ $profiles->links() }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-zinc-200 bg-white shadow-sm">
                    <div class="border-b border-zinc-200 p-4">
                        <h2 class="text-sm font-semibold">Mapping Lokasi Absensi Pendidikan ke Unit Organisasi SIASN</h2>
                        <p class="mt-1 text-xs text-zinc-500">Acuan unit kerja final diambil dari Unit Organisasi SIASN, bukan dari nama lokasi presensi.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm">
                            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Lokasi Absensi</th>
                                    <th class="px-4 py-3 font-semibold">NIP</th>
                                    <th class="px-4 py-3 font-semibold">Nama</th>
                                    <th class="px-4 py-3 font-semibold">Unit Organisasi SIASN</th>
                                    <th class="px-4 py-3 font-semibold">Jabatan SIASN</th>
                                    <th class="px-4 py-3 font-semibold">Status</th>
                                    <th class="px-4 py-3 font-semibold">Keterangan</th>
                                    <th class="px-4 py-3 font-semibold">Diambil</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 bg-white">
                                @forelse ($educationRows as $row)
                                    @php
                                        $statusClass = match ($row->match_status) {
                                            'unit_cocok' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                            'lokasi_bukan_unit' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                            'siasn_gagal' => 'bg-rose-50 text-rose-700 ring-rose-200',
                                            default => 'bg-zinc-50 text-zinc-700 ring-zinc-200',
                                        };
                                    @endphp
                                    <tr class="hover:bg-zinc-50">
                                        <td class="min-w-56 px-4 py-3 font-medium text-zinc-900">{{ $row->lokasi_nama ?: '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">{{ $row->nip }}</td>
                                        <td class="px-4 py-3">{{ $row->nama ?: '-' }}</td>
                                        <td class="min-w-72 px-4 py-3">{{ $row->siasn_unit_organisasi ?: '-' }}</td>
                                        <td class="min-w-64 px-4 py-3">{{ $row->siasn_jabatan ?: '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex whitespace-nowrap rounded-md px-2 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                                                {{ str_replace('_', ' ', $row->match_status ?: 'belum dicek') }}
                                            </span>
                                        </td>
                                        <td class="min-w-80 px-4 py-3 text-xs text-zinc-500">{{ $row->row_data['siasn_error'] ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-zinc-500">{{ optional($row->fetched_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-10 text-center text-sm text-zinc-500">Belum ada mapping lokasi Pendidikan yang tersimpan.</td>
                                    </tr>
                                @endforelse
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
