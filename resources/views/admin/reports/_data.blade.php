{{-- Report data fragment — summary tiles, chart, table, pager. Everything a
     filter changes lives INSIDE #report-data so the swap carries all of it
     (§4.3). The chart dataset rides as JSON; the drawing code lives on the
     page (swapped-in <script> tags never execute). --}}

@php $tones = [
    'hero' => null,
    'success' => ['background: var(--he-success-soft); color: var(--he-success);', 'text-success'],
    'danger' => ['background: var(--he-danger-soft); color: var(--he-danger);', 'text-danger'],
    'warning' => ['background: var(--he-warning-soft); color: #b45309;', 'text-warning'],
]; @endphp

{{-- ══ Summary tiles (auto-compact on phones via .he-stats) ══ --}}
<div class="he-stats mb-4">
    <div class="he-stats__grid" style="--he-stats-cols: {{ count($data['summary']) }};">
        @foreach($data['summary'] as [$tileIcon, $tileLabel, $value])
            @php $tone = $data['summary'][$loop->index][3] ?? null; @endphp
            <div class="he-stat {{ $tone === 'hero' ? 'he-stat--hero' : '' }}">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="{{ $tone === 'hero' ? 'background: rgba(255,255,255,0.15); color: #a5b4fc;' : ($tones[$tone][0] ?? 'background: var(--he-primary-soft); color: var(--he-primary);') }}">
                        <i class="fa-solid fa-{{ $tileIcon }}"></i>
                    </div>
                    <div class="he-stat__label">{{ $tileLabel }}</div>
                </div>
                <div class="he-stat__value {{ $tone !== 'hero' ? ($tones[$tone][1] ?? '') : '' }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>
</div>

{{-- ══ Chart ══ --}}
@if($data['chart'] && count($data['rows']))
    <div class="panel-card mb-4">
        <div class="panel-body">
            <div class="rp-chart-shell" id="rp-chart-shell">
                <div class="rp-chart-skeleton skeleton"></div>
                <canvas></canvas>
            </div>
        </div>
    </div>
    <script type="application/json" id="rp-chart-data">{!! json_encode($data['chart']) !!}</script>
@endif

{{-- ══ Table ══ --}}
<div class="panel-card">
    <div class="rp-table-wrap">
        <table class="rp-table">
            <thead>
                <tr>
                    @foreach($data['headings'] as $h)
                        <th class="{{ in_array($loop->index, $data['money']) ? 'num' : '' }}">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @forelse($data['rows'] as $row)
                <tr>
                    @foreach($row as $i => $cell)
                        <td class="{{ in_array($i, $data['money']) ? 'num' : 'text-secondary' }}">
                            {{ in_array($i, $data['money']) ? hostelease_money($cell) : $cell }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($data['headings']) }}" class="border-0" style="white-space: normal;">
                    <x-he-empty-state icon="chart-simple" title="{{ __('Nothing in this range') }}"
                        subtitle="{{ __('Try a wider date range, or a different grouping.') }}" />
                </td></tr>
            @endforelse
            </tbody>
            @if(! is_null($data['total']) && count($data['rows']))
                <tfoot>
                    <tr>
                        <td colspan="{{ count($data['headings']) - 1 }}" class="num text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">{{ __('Total') }}</td>
                        <td class="num">{{ hostelease_money($data['total']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ══ Pager (Dues) — .page-link anchors inside the fragment paginate in place ══ --}}
@if(($data['paginator'] ?? null)?->hasPages())
    <div class="mt-4">{{ $data['paginator']->links() }}</div>
@endif
