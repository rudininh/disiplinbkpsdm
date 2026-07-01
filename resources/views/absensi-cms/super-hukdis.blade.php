<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Hukdis - Disiplin BKPSDM</title>
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
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    ASN
                </a>
                <a href="{{ route('cms.super-hukdis.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
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

        <main class="min-w-0 flex-1 overflow-x-hidden">
            <header class="border-b border-zinc-200 bg-white">
                <div class="flex w-full flex-col gap-4 px-4 py-4 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-md bg-zinc-950 text-white">
                            <i data-lucide="file-badge" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight">Super Hukdis</h1>
                            <p class="mt-1 text-sm text-zinc-500">Generate Surat Pernyataan Tidak Pernah Dijatuhi Hukuman Disiplin.</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                            <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                            Dashboard
                        </a>
                        <a href="{{ route('cms.siasn.index') }}" class="inline-flex items-center justify-center gap-2 rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-800">
                            <i data-lucide="database-zap" class="h-4 w-4"></i>
                            SIASN Login
                        </a>
                    </div>
                </div>
            </header>

            <section class="mx-auto max-w-[1200px] px-4 py-6 sm:px-6 lg:px-8">
                {{-- Alert Token SIASN --}}
                @if ($storedToken === null)
                    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-start gap-3">
                            <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"></i>
                            <div>
                                <h3 class="text-sm font-semibold text-amber-800">Token SIASN Belum Tersimpan</h3>
                                <p class="mt-1 text-sm text-amber-700">
                                    Untuk generate surat, Anda harus login SIASN terlebih dahulu.
                                    <a href="{{ route('cms.siasn.index') }}" class="font-semibold underline hover:text-amber-900">Login SIASN →</a>
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <div class="flex items-start gap-3">
                            <i data-lucide="shield-check" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600"></i>
                            <div>
                                <h3 class="text-sm font-semibold text-emerald-800">Token SIASN Aktif</h3>
                                <p class="mt-1 text-sm text-emerald-700">
                                    Login sebagai <strong>{{ $storedToken['identity'] }}</strong>.
                                    @if ($storedToken['expires_at_text'])
                                        Berlaku sampai {{ $storedToken['expires_at_text'] }}.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Alert Result --}}
                @if (is_array($result))
                    <div class="mb-6 rounded-lg border {{ ($result['success'] ?? false) ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }} p-4">
                        <div class="flex items-start gap-3">
                            @if ($result['success'] ?? false)
                                <i data-lucide="check-circle-2" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600"></i>
                            @else
                                <i data-lucide="x-circle" class="mt-0.5 h-5 w-5 shrink-0 text-rose-600"></i>
                            @endif
                            <div>
                                <p class="text-sm font-medium {{ ($result['success'] ?? false) ? 'text-emerald-800' : 'text-rose-800' }}">
                                    {{ $result['message'] ?? 'Proses selesai.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid gap-6 lg:grid-cols-[400px_minmax(0,1fr)]">
                    {{-- Form Generate --}}
                    <form method="POST" action="{{ route('cms.super-hukdis.generate') }}" class="space-y-4">
                        @csrf

                        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                                <div class="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-900 text-white">
                                    <i data-lucide="file-text" class="h-4 w-4"></i>
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold">Generate Surat</h2>
                                    <p class="text-sm text-zinc-500">Isi NIP dan pilih kategori surat.</p>
                                </div>
                            </div>

                            {{-- Kategori --}}
                            <div class="mt-4">
                                <label for="kategori" class="block text-sm font-medium text-zinc-700">Kategori Surat</label>
                                <select name="kategori" id="kategori" required
                                    class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                    <option value="">— Pilih Kategori —</option>
                                    @foreach ($kategoriList as $key => $kat)
                                        <option value="{{ $key }}" @selected(old('kategori') === $key)>
                                            {{ $kat['label'] }} — {{ $kat['deskripsi'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('kategori')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- NIP --}}
                            <div class="mt-4">
                                <label for="nip" class="block text-sm font-medium text-zinc-700">NIP (18 Digit)</label>
                                <input name="nip" id="nip" type="text" inputmode="numeric" maxlength="18" required
                                    value="{{ old('nip') }}" placeholder="Contoh: 199001012020121001"
                                    class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2.5 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                @error('nip')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-zinc-500">
                                    Data pegawai akan diambil dari SIASN API berdasarkan NIP ini.
                                </p>
                            </div>

                            <button type="submit" @disabled($storedToken === null)
                                class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-800 disabled:cursor-not-allowed disabled:opacity-50">
                                <i data-lucide="file-down" class="h-4 w-4"></i>
                                Generate & Download Surat
                            </button>
                        </div>
                    </form>

                    {{-- Preview Data / Panduan --}}
                    <div class="space-y-4">
                        {{-- Preview data pegawai terakhir yang di-generate --}}
                        @if (is_array($lastProfile) && count($lastProfile) > 0)
                            <div class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                                <div class="border-b border-zinc-200 px-5 py-4">
                                    <h2 class="text-base font-semibold">Data Pegawai Terakhir</h2>
                                    <p class="text-sm text-zinc-500">Data yang digunakan untuk generate surat terakhir.</p>
                                </div>
                                <div class="divide-y divide-zinc-100 px-5 py-2">
                                    @foreach ($lastProfile as $field => $value)
                                        <div class="flex items-start gap-4 py-2.5">
                                            <span class="w-40 shrink-0 text-sm font-medium text-zinc-500">{{ str_replace('_', ' ', $field) }}</span>
                                            <span class="text-sm text-zinc-800">{{ $value ?: '-' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Panduan / Info --}}
                        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                                <div class="flex h-9 w-9 items-center justify-center rounded-md bg-cyan-100 text-cyan-700">
                                    <i data-lucide="info" class="h-4 w-4"></i>
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold">Panduan</h2>
                                </div>
                            </div>

                            <div class="mt-4 space-y-3 text-sm text-zinc-600">
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-xs font-semibold text-cyan-700">1</span>
                                    <p>Pastikan sudah <strong>login SIASN</strong> terlebih dahulu di halaman <a href="{{ route('cms.siasn.index') }}" class="font-semibold text-cyan-700 hover:underline">SIASN Profil ASN</a>.</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-xs font-semibold text-cyan-700">2</span>
                                    <p>Pilih <strong>kategori</strong> surat yang sesuai (Mutasi, Pensiun, SLKS, dll).</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-xs font-semibold text-cyan-700">3</span>
                                    <p>Masukkan <strong>NIP 18 digit</strong> pegawai yang bersangkutan.</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-xs font-semibold text-cyan-700">4</span>
                                    <p>Klik <strong>Generate & Download</strong> — file Word (.docx) akan otomatis terunduh.</p>
                                </div>
                            </div>

                            <div class="mt-5 rounded-md border border-zinc-200 bg-zinc-50 p-3">
                                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Placeholder yang tersedia di template:</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach (['NAMA', 'NIP', 'PANGKAT_GOLONGAN', 'PANGKAT', 'GOLONGAN', 'JABATAN', 'JENIS_JABATAN', 'UNIT_KERJA', 'UNIT_KERJA_INDUK', 'INSTANSI', 'SATUAN_KERJA', 'LOKASI_KERJA', 'TEMPAT_LAHIR', 'TANGGAL_LAHIR', 'TTL', 'ALAMAT', 'JENIS_KELAMIN', 'AGAMA', 'PENDIDIKAN', 'TANGGAL', 'TAHUN', 'JENIS_ASN', 'KEPALA_NAMA', 'KEPALA_NIP', 'KEPALA_PANGKAT', 'KEPALA_JABATAN'] as $placeholder)
                                        <code class="rounded bg-zinc-200 px-1.5 py-0.5 text-xs font-mono text-zinc-700">${{{ $placeholder }}}</code>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-xs text-zinc-500">
                                    Gunakan placeholder di atas di dalam file template .docx untuk bagian yang perlu diisi otomatis.
                                    Template diletakkan di <code class="rounded bg-zinc-200 px-1 text-xs">storage/app/templates/super-hukdis/</code>.
                                </p>
                            </div>
                        </div>

                        {{-- Kategori Info Cards --}}
                        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-zinc-700">Kategori Tersedia</h3>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ($kategoriList as $key => $kat)
                                    <div class="rounded-md border border-zinc-200 p-3">
                                        <p class="text-sm font-semibold text-zinc-800">{{ $kat['label'] }}</p>
                                        <p class="mt-0.5 text-xs text-zinc-500">{{ $kat['deskripsi'] }}</p>
                                        <p class="mt-1 text-xs text-zinc-400">Template: <code class="text-zinc-500">{{ $kat['template'] }}</code></p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
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
