<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login SIASN - Disiplin BKPSDM</title>
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
                <a href="{{ route('cms.siasn.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
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
                <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-md bg-zinc-950 text-white">
                            <i data-lucide="shield-check" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight">Login SIASN</h1>
                            <p class="mt-1 text-sm text-zinc-500">Validasi sesi SSO SIASN sebelum fitur profil ASN dilanjutkan.</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                            <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                            Dashboard
                        </a>
                        <a href="https://asndigital.bkn.go.id/" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-800">
                            <i data-lucide="external-link" class="h-4 w-4"></i>
                            ASN Digital
                        </a>
                    </div>
                </div>
            </header>

            <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,0.9fr)_minmax(420px,1fr)]">
                    <div class="space-y-4">
                <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-2">
                        <i data-lucide="route" class="h-5 w-5 text-cyan-700"></i>
                        <h2 class="text-sm font-semibold">Alur Login</h2>
                    </div>

                    <div class="mt-5 space-y-4 text-sm text-zinc-600">
                        <div class="flex gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-zinc-900 text-xs font-semibold text-white">1</div>
                            <p>Buka ASN Digital, lalu masuk melalui halaman SSO SIASN sampai berhasil kembali ke portal.</p>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-zinc-900 text-xs font-semibold text-white">2</div>
                            <p>Ambil <span class="font-mono text-xs text-zinc-800">access_token</span>, header <span class="font-mono text-xs text-zinc-800">Authorization: Bearer</span>, atau cookie <span class="font-mono text-xs text-zinc-800">token</span> dari browser setelah login.</p>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-zinc-900 text-xs font-semibold text-white">3</div>
                            <p>Tempel token panjang yang diawali <span class="font-mono text-xs text-zinc-800">eyJ...</span>. Kosongkan NIP untuk cek token lokal; isi NIP untuk lanjut cek API profil SIASN.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    <div class="flex items-start gap-3">
                        <i data-lucide="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0"></i>
                        <div>
                            <h2 class="font-semibold">Bukan Token</h2>
                            <p class="mt-2 leading-6">URL SSO yang berisi <span class="font-mono text-xs">client_id</span>, <span class="font-mono text-xs">code_challenge</span>, atau <span class="font-mono text-xs">response_type=code</span> bukan token. Kode OTP 6 digit, <span class="font-mono text-xs">refresh_token</span>, dan <span class="font-mono text-xs">sso_refresh_token</span> juga bukan token untuk form ini.</p>
                        </div>
                    </div>
                </div>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 border-b border-zinc-200 pb-4">
                    <i data-lucide="key-round" class="h-5 w-5 text-cyan-700"></i>
                    <h2 class="text-sm font-semibold">Tes Login SIASN</h2>
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

                    @if (! empty($result['profile']) && is_array($result['profile']))
                        <div class="mt-4 rounded-md border border-zinc-200 bg-zinc-50 p-4">
                            <div class="flex items-center gap-2">
                                <i data-lucide="id-card" class="h-5 w-5 text-cyan-700"></i>
                                <h3 class="text-sm font-semibold">Profil SIASN</h3>
                            </div>

                            <dl class="mt-4 divide-y divide-zinc-200 rounded-md border border-zinc-200 bg-white text-sm">
                                @foreach ($result['profile'] as $label => $value)
                                    <div class="grid gap-1 px-3 py-2 sm:grid-cols-[150px_minmax(0,1fr)]">
                                        <dt class="font-medium text-zinc-500">{{ $label }}</dt>
                                        <dd class="break-words text-zinc-900">{{ $value ?: '-' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                @endif

                @if ($storedToken)
                    <div class="mt-4 rounded-md border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-semibold">Token SIASN tersimpan</div>
                                <div class="mt-1 text-xs leading-5">
                                    {{ $storedToken['identity'] }}
                                    @if ($storedToken['expires_at_text'])
                                        <span class="text-cyan-700">Berlaku sampai {{ $storedToken['expires_at_text'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('cms.siasn.forget-token') }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md border border-cyan-300 bg-white px-3 py-2 text-xs font-semibold text-cyan-800 hover:bg-cyan-100">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                    Hapus Token
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('cms.siasn.test-login') }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-zinc-700" for="login_nip">NIP Test</label>
                        <input id="login_nip" name="nip" value="{{ old('nip') }}" inputmode="numeric" maxlength="18" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100" placeholder="Kosongkan untuk cek token lokal">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700" for="login_bearer_token">Access Token SIASN</label>
                        <textarea id="login_bearer_token" name="bearer_token" rows="9" class="mt-1 w-full resize-y rounded-md border border-zinc-300 px-3 py-2 font-mono text-xs outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100" placeholder="Bearer eyJ..., langsung eyJ..., atau blok cookie yang memuat token=eyJ...">{{ old('bearer_token', $storedToken['token'] ?? '') }}</textarea>
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-800">
                        <i data-lucide="log-in" class="h-4 w-4"></i>
                        Tes Login SIASN
                    </button>
                </form>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-zinc-200 bg-white shadow-sm">
                    <div class="border-b border-zinc-200 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="school" class="h-5 w-5 text-cyan-700"></i>
                                    <h2 class="text-sm font-semibold">Referensi Unit Kerja Dikdas</h2>
                                </div>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600">
                                    Acuan unit kerja dari data DIKDAS Kota Banjarmasin status negeri, diurutkan SD/MI lalu SMP/MTs.
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                    <a href="{{ $educationUnitSource['url'] }}" target="_blank" class="inline-flex items-center gap-1 font-medium text-cyan-700 hover:text-cyan-800">
                                        <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                                        {{ $educationUnitSource['name'] }}
                                    </a>
                                    @if (! empty($educationUnitSource['captured_at']))
                                        <span class="text-zinc-300">/</span>
                                        <span>Diambil {{ $educationUnitSource['captured_at'] }}</span>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('cms.siasn.sync-absensi-reference-employees') }}" class="mt-4">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-zinc-950 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-800">
                                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                        Sinkron Pegawai Absensi
                                    </button>
                                </form>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-center text-sm sm:grid-cols-5">
                                <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2">
                                    <div class="text-lg font-semibold text-zinc-950">{{ number_format($educationUnitSummary['total'] ?? 0, 0, ',', '.') }}</div>
                                    <div class="text-xs text-zinc-500">Total</div>
                                </div>
                                <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2">
                                    <div class="text-lg font-semibold text-zinc-950">{{ number_format($educationUnitSummary['sd_sederajat'] ?? 0, 0, ',', '.') }}</div>
                                    <div class="text-xs text-zinc-500">SD/MI</div>
                                </div>
                                <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2">
                                    <div class="text-lg font-semibold text-zinc-950">{{ number_format($educationUnitSummary['smp_sederajat'] ?? 0, 0, ',', '.') }}</div>
                                    <div class="text-xs text-zinc-500">SMP/MTs</div>
                                </div>
                                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2">
                                    <div class="text-lg font-semibold text-emerald-900">{{ number_format($educationEmployeeSummary['matched_units'] ?? 0, 0, ',', '.') }}</div>
                                    <div class="text-xs text-emerald-700">Lokasi Cocok</div>
                                </div>
                                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2">
                                    <div class="text-lg font-semibold text-emerald-900">{{ number_format($educationEmployeeSummary['employee_count'] ?? 0, 0, ',', '.') }}</div>
                                    <div class="text-xs text-emerald-700">Pegawai</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-left text-sm">
                            <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                                <tr>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold">No</th>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold">Jenjang</th>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold">NPSN</th>
                                    <th class="min-w-64 px-4 py-3 font-semibold">Unit Kerja</th>
                                    <th class="min-w-72 px-4 py-3 font-semibold">Unit Kerja Real di SIASN</th>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold">Kecamatan</th>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold">Kelurahan</th>
                                    <th class="min-w-72 px-4 py-3 font-semibold">Alamat</th>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold">Pegawai</th>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 bg-white">
                                @forelse ($educationUnits as $unit)
                                    <tr class="cursor-pointer hover:bg-zinc-50" data-unit-toggle="unit-employees-{{ $unit['npsn'] ?? $loop->iteration }}" aria-expanded="false">
                                        <td class="whitespace-nowrap px-4 py-3 text-zinc-500">{{ $unit['no'] ?? $loop->iteration }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <span class="inline-flex rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs font-medium text-zinc-700">
                                                {{ $unit['jenjang'] ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">
                                            @if (! empty($unit['source_url']))
                                                <a href="{{ $unit['source_url'] }}" target="_blank" class="text-cyan-700 hover:text-cyan-800">{{ $unit['npsn'] ?? '-' }}</a>
                                            @else
                                                {{ $unit['npsn'] ?? '-' }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-medium text-zinc-900">{{ $unit['unit_kerja'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-zinc-600">{{ $unit['siasn_unit_organisasi'] ?: '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-zinc-600">{{ $unit['kecamatan'] ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-zinc-600">{{ $unit['kelurahan'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-zinc-600">{{ $unit['alamat'] ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            @if (($unit['absensi_employee_count'] ?? 0) > 0)
                                                <span class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">
                                                    <i data-lucide="users" class="h-3.5 w-3.5"></i>
                                                    {{ $unit['absensi_employee_count'] }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs font-medium text-zinc-500">
                                                    <i data-lucide="user-x" class="h-3.5 w-3.5"></i>
                                                    -
                                                </span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-zinc-600">{{ $unit['status'] ?? '-' }}</td>
                                    </tr>
                                    <tr id="unit-employees-{{ $unit['npsn'] ?? $loop->iteration }}" class="hidden bg-zinc-50">
                                        <td colspan="10" class="px-4 py-4">
                                            <div class="rounded-md border border-zinc-200 bg-white p-4">
                                                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <div class="text-sm font-semibold text-zinc-900">{{ $unit['unit_kerja'] ?? '-' }}</div>
                                                        <div class="mt-1 text-xs text-zinc-500">
                                                            Lokasi Absensi:
                                                            <span class="font-medium text-zinc-700">{{ $unit['absensi_lokasi_nama'] ?? 'Belum cocok / belum disinkron' }}</span>
                                                            @if (! empty($unit['absensi_lokasi_id']))
                                                                <span class="text-zinc-400">#{{ $unit['absensi_lokasi_id'] }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="text-xs text-zinc-500">{{ $unit['absensi_employee_count'] ?? 0 }} pegawai</div>
                                                </div>

                                                @if (! empty($unit['absensi_employees']))
                                                    <div class="mt-4 overflow-x-auto rounded-md border border-zinc-200">
                                                        <table class="min-w-full divide-y divide-zinc-200 text-left text-xs">
                                                            <thead class="bg-zinc-50 uppercase tracking-wide text-zinc-500">
                                                                <tr>
                                                                    <th class="whitespace-nowrap px-3 py-2 font-semibold">No</th>
                                                                    <th class="min-w-56 px-3 py-2 font-semibold">Nama Pegawai</th>
                                                                    <th class="whitespace-nowrap px-3 py-2 font-semibold">NIP</th>
                                                                    <th class="min-w-64 px-3 py-2 font-semibold">Lokasi Absensi</th>
                                                                    <th class="min-w-72 px-3 py-2 font-semibold">Unit Kerja Real di SIASN</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-zinc-100 bg-white">
                                                                @foreach ($unit['absensi_employees'] as $employee)
                                                                    <tr class="hover:bg-zinc-50">
                                                                        <td class="whitespace-nowrap px-3 py-2 text-zinc-500">{{ $loop->iteration }}</td>
                                                                        <td class="px-3 py-2 font-medium text-zinc-900">{{ $employee['nama'] ?: '-' }}</td>
                                                                        <td class="whitespace-nowrap px-3 py-2 font-mono text-zinc-600">{{ $employee['nip'] ?: '-' }}</td>
                                                                        <td class="px-3 py-2 text-zinc-600">
                                                                            {{ $employee['lokasi_nama'] ?: '-' }}
                                                                            @if (! empty($employee['lokasi_id']))
                                                                                <span class="text-zinc-400">#{{ $employee['lokasi_id'] }}</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-3 py-2 text-zinc-600">{{ $employee['siasn_unit_organisasi'] ?: '-' }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="mt-4 rounded-md border border-dashed border-zinc-300 bg-zinc-50 px-3 py-4 text-sm text-zinc-500">
                                                        Belum ada pegawai absensi tersimpan untuk unit kerja ini. Jalankan sinkron pegawai absensi terlebih dahulu.
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-4 py-8 text-center text-sm text-zinc-500">Data referensi unit kerja belum tersedia.</td>
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

        document.querySelectorAll('[data-unit-toggle]').forEach((row) => {
            row.addEventListener('click', (event) => {
                if (event.target.closest('a')) {
                    return;
                }

                const target = document.getElementById(row.dataset.unitToggle);
                if (! target) {
                    return;
                }

                const willOpen = target.classList.contains('hidden');
                target.classList.toggle('hidden', ! willOpen);
                row.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
        });
    </script>
</body>
</html>
