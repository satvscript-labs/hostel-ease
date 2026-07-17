<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Brand-aligned with the W6.1 invoice PDF: indigo #4f46e5, slate ink,
           soft rules. DejaVu Sans ships with dompdf and renders ₹. */
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #0f172a; font-size: 11px; margin: 0; padding: 28px; }
        .head { border-bottom: 3px solid #4f46e5; padding-bottom: 10px; margin-bottom: 4px; }
        .brand { font-size: 10px; font-weight: bold; letter-spacing: 2px; color: #4f46e5; text-transform: uppercase; }
        h1 { font-size: 18px; margin: 2px 0 3px; color: #0f172a; }
        .muted { color: #64748b; font-size: 9.5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { background: #4f46e5; color: #fff; padding: 7px 8px; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #4f46e5; border-bottom: none; background: #eef2ff !important; color: #4f46e5; }
        .foot { margin-top: 16px; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    @php $hostel = \App\Models\Hostel::find(\App\Support\Tenant::id()); @endphp
    <div class="head">
        <div class="brand">HostelEase</div>
        <h1>{{ $title }}</h1>
        <div class="muted">
            {{ $hostel?->name }}
            @if($needsRange) · {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }} @else · Live snapshot @endif
            · Generated {{ now()->format('d M Y, h:i A') }}
        </div>
    </div>

    <table>
        <thead><tr>
            @foreach($data['headings'] as $i => $h)<th class="{{ in_array($i, $data['money']) ? 'right' : '' }}">{{ $h }}</th>@endforeach
        </tr></thead>
        <tbody>
        @forelse($data['rows'] as $row)
            <tr>
                @foreach($row as $i => $cell)
                    <td class="{{ in_array($i, $data['money']) ? 'right' : '' }}">{{ in_array($i, $data['money']) ? hostelease_money($cell) : $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($data['headings']) }}">No data in this range.</td></tr>
        @endforelse
        </tbody>
        @if(! is_null($data['total']) && count($data['rows']))
        <tfoot><tr>
            <td colspan="{{ count($data['headings']) - 1 }}" class="right">TOTAL</td>
            <td class="right">{{ hostelease_money($data['total']) }}</td>
        </tr></tfoot>
        @endif
    </table>

    <div class="foot">HostelEase · {{ $hostel?->name }} · This report reflects data at the moment of generation.</div>
</body>
</html>
