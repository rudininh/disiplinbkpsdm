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
    <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 py-5 sm:px-6 lg:px-8">
        <header class="flex flex-col gap-4 border-b border-zinc-200 pb-5 md:flex-row md:items-center md:justify-between">
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
        </header>

        <section class="grid flex-1 items-start gap-5 py-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(420px,1fr)]">
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

                <form method="POST" action="{{ route('cms.siasn.test-login') }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-zinc-700" for="login_nip">NIP Test</label>
                        <input id="login_nip" name="nip" value="{{ old('nip') }}" inputmode="numeric" maxlength="18" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100" placeholder="Kosongkan untuk cek token lokal">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700" for="login_bearer_token">Access Token SIASN</label>
                        <textarea id="login_bearer_token" name="bearer_token" rows="9" class="mt-1 w-full resize-y rounded-md border border-zinc-300 px-3 py-2 font-mono text-xs outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100" placeholder="Bearer eyJ..., langsung eyJ..., atau blok cookie yang memuat token=eyJ...">{{ old('bearer_token') }}</textarea>
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-800">
                        <i data-lucide="log-in" class="h-4 w-4"></i>
                        Tes Login SIASN
                    </button>
                </form>
            </div>
        </section>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
