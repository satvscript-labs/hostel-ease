<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 11px; margin: 0; padding: 24px; }
        h1 { color: #2563eb; font-size: 17px; margin: 0 0 2px; }
        .muted { color: #6b7280; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #2563eb; color: #fff; padding: 6px; text-align: left; font-size: 10px; }
        td { padding: 5px 6px; border-bottom: 1px solid #eee; }
        .right { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #999; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="muted">
        Generated {{ now()->format('d M Y H:i') }}
        @if($needsRange) · {{ $from->format('d M Y') }} → {{ $to->format('d M Y') }}@endif
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
            <tr><td colspan="{{ count($data['headings']) }}">No data.</td></tr>
        @endforelse
        </tbody>
        @if(! is_null($data['total']))
        <tfoot><tr>
            <td colspan="{{ count($data['headings']) - 1 }}" class="right">Total</td>
            <td class="right">{{ hostelease_money($data['total']) }}</td>
        </tr></tfoot>
        @endif
    </table>
</body>
</html>

