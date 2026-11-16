@props(['history', 'chart', 'report'])

<div class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
    <h2 class="font-heading text-h3 font-bold text-ink">Perkembangan</h2>
    <p class="mt-1 text-small text-muted">Dibandingkan dengan percobaanmu sendiri di paket yang sama.</p>

    @if ($chart['count'] > 1)
        <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="mt-4 h-auto w-full" role="img" aria-label="Grafik perkembangan skor">
            @foreach ($chart['series'] as $serie)
                @if ($serie['threshold_y'] !== null)
                    <line x1="{{ $chart['padding'] }}" y1="{{ $serie['threshold_y'] }}" x2="{{ $chart['width'] - $chart['padding'] }}" y2="{{ $serie['threshold_y'] }}"
                        stroke="{{ $serie['color'] }}" stroke-width="1" stroke-dasharray="4 4" opacity="0.4"></line>
                @endif
                <polyline points="{{ $serie['points'] }}" fill="none" stroke="{{ $serie['color'] }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
            @endforeach
        </svg>

        <ul class="mt-3 flex flex-wrap gap-4 text-caption text-muted">
            @foreach ($chart['series'] as $serie)
                <li class="inline-flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $serie['color'] }}"></span>
                    {{ $serie['label'] }}
                </li>
            @endforeach
            <li class="inline-flex items-center gap-2">garis putus-putus = ambang batas subtes</li>
        </ul>
    @else
        <p class="mt-4 text-small text-muted">Grafik muncul setelah kamu mengerjakan paket ini lebih dari sekali.</p>
    @endif

    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[520px] text-left text-small">
            <thead class="bg-surface text-caption uppercase text-muted">
                <tr>
                    <th class="px-3 py-2">Tanggal</th>
                    <th class="px-3 py-2">TWK</th>
                    <th class="px-3 py-2">TIU</th>
                    <th class="px-3 py-2">TKP</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Selisih</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($history as $row)
                    <tr class="border-t border-line {{ $row['is_current'] ? 'bg-brand/5' : '' }}">
                        <td class="px-3 py-2 text-ink">{{ $row['at']?->translatedFormat('d M Y H:i') }}</td>
                        <td class="px-3 py-2">{{ $row['twk'] }}</td>
                        <td class="px-3 py-2">{{ $row['tiu'] }}</td>
                        <td class="px-3 py-2">{{ $row['tkp'] }}</td>
                        <td class="px-3 py-2 font-semibold text-ink">{{ $row['total'] }} / {{ $report->maximum() }}</td>
                        <td class="px-3 py-2">
                            @if ($row['delta'] === null)
                                <span class="text-muted">—</span>
                            @else
                                <span class="{{ $row['delta'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $row['delta'] >= 0 ? '+' : '' }}{{ $row['delta'] }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if (! $row['is_current'])
                                <a href="{{ route('hasil.show', $row['id']) }}" class="inline-flex min-h-[44px] items-center font-semibold text-brand hover:text-brand-dark">Buka</a>
                            @else
                                <span class="text-caption text-muted">yang ini</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
