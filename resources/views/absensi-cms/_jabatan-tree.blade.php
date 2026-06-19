@foreach ($nodes as $node)
    @php
        $pegawaiText = trim((string) ($node['pegawai'] ?? ''));
        $source = $node['source'] ?? 'tpp';
        $isSiasnGroup = $source === 'siasn_group';
        $isFilled = ($pegawaiText !== '' && $pegawaiText !== '-') || $isSiasnGroup;
        $vacancyCount = (int) ($node['vacancy_count'] ?? 1);
        $isCategory = $source === 'category' || ($node['callout_class'] ?? '') === 'functional';
        $renderedJabatan = isset($displayJobName)
            ? $displayJobName($node['jabatan'] ?? '-')
            : ($node['jabatan'] ?? '-');
        $tone = match ($node['callout_class'] ?? 'default') {
            'info', 'warning', 'danger' => $isFilled ? 'border-emerald-600 bg-emerald-50' : 'border-rose-600 bg-rose-50',
            'vacant' => 'border-rose-600 bg-rose-50',
            'functional' => 'border-indigo-600 bg-indigo-50',
            default => $isFilled ? 'border-emerald-500 bg-emerald-50' : 'border-zinc-300 bg-zinc-50',
        };
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
    @endphp

    <li class="relative pl-5">
        <span class="absolute left-0 top-4 h-2 w-2 rounded-full {{ $isCategory ? 'bg-indigo-500' : ($isFilled ? 'bg-emerald-500' : 'bg-rose-500') }}"></span>
        <div class="rounded-md border-l-4 border border-zinc-200 {{ $tone }} px-3 py-2 shadow-sm">
            <div class="flex items-start justify-between gap-3 text-sm">
                <div class="flex min-w-0 flex-wrap items-start gap-x-2 gap-y-1">
                    <span class="font-semibold text-zinc-700">{{ $node['kelas'] ?? '-' }}</span>
                    <span class="text-zinc-400">|</span>
                    <span class="font-semibold text-zinc-950">{{ $renderedJabatan }}</span>
                    @if ($isSiasnGroup)
                        <span class="text-zinc-400">|</span>
                        <span class="text-emerald-700">
                            B {{ number_format((int) ($node['bezetting'] ?? 0)) }} pegawai SIASN
                        </span>
                        @if (! empty($node['sheet_name']))
                            <span class="text-zinc-400">|</span>
                            <span class="text-zinc-500">{{ $node['sheet_name'] }}</span>
                        @endif
                    @elseif ($isFilled && ! $isCategory)
                        <span class="text-zinc-400">|</span>
                        <span class="text-zinc-700">{{ $pegawaiText }}</span>
                    @elseif ($isCategory && ! empty($node['pegawai']))
                        <span class="text-zinc-400">|</span>
                        <span class="text-indigo-700">{{ $node['pegawai'] }}</span>
                    @elseif (in_array($source, ['excel', 'tpp_empty', 'excel_parent'], true))
                        <span class="text-zinc-400">|</span>
                        <span class="text-rose-700">
                            B {{ $node['bezetting'] ?? 0 }} pegawai terisi / K {{ $node['kebutuhan'] ?? 0 }} kebutuhan / +/- {{ $node['selisih'] ?? '-' }} kekurangan
                        </span>
                        @if (! empty($node['sheet_name']))
                            <span class="text-zinc-400">|</span>
                            <span class="text-zinc-500">{{ $node['sheet_name'] }}</span>
                        @endif
                    @endif
                </div>
                <span class="shrink-0 rounded-md border px-2 py-1 text-xs font-semibold {{ $isCategory ? 'border-indigo-200 bg-indigo-100 text-indigo-800' : ($isFilled ? 'border-emerald-200 bg-emerald-100 text-emerald-800' : 'border-rose-200 bg-rose-100 text-rose-800') }}">
                    @if ($isCategory)
                        Kategori
                    @elseif ($isSiasnGroup)
                        Terisi
                    @elseif (! $isFilled)
                        Ada {{ number_format($vacancyCount) }} Jabatan Lowong
                    @else
                        Terisi
                    @endif
                </span>
            </div>
        </div>

        @if ($children !== [])
            <ul class="mt-2 space-y-2 border-l border-dashed border-zinc-300 pl-4">
                @include('absensi-cms._jabatan-tree', ['nodes' => $children, 'displayJobName' => $displayJobName ?? null])
            </ul>
        @endif
    </li>
@endforeach
