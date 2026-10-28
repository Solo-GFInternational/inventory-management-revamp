<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory report {{ $year }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .header { width: 100%; border-bottom: 2px solid #1e3a5f; padding-bottom: 8px; margin-bottom: 12px; }
        .header td { border: none; padding: 0; vertical-align: middle; }
        .logo { height: 42px; }
        h1 { margin: 0; font-size: 18px; color: #1e3a5f; text-align: right; }
        .meta { margin-top: 2px; text-align: right; color: #6b7280; font-size: 9px; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; }
        table.list th { background-color: #1e3a5f; color: #ffffff; font-weight: bold; }
        table.list tr:nth-child(even) td { background-color: #f3f4f6; }
        .num { text-align: right; }
        .pending { color: #b45309; }
        .empty { padding: 24px; text-align: center; color: #6b7280; }
    </style>
</head>
<body>
@php
    $logo = public_path('images/goodman-fielder-logo.png');
    $logoData = is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null;
@endphp

<table class="header">
    <tr>
        <td>@if ($logoData)<img src="{{ $logoData }}" class="logo" alt="Goodman Fielder">@endif</td>
        <td>
            <h1>Inventory report {{ $year }}</h1>
            <div class="meta">{{ $inventories->count() }} {{ \Illuminate\Support\Str::plural('record', $inventories->count()) }} · generated {{ now()->format('d M Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="list">
    <thead>
        <tr>
            <th>#</th>
            <th>Date</th>
            <th>Item</th>
            <th class="num">Qty</th>
            <th>Serial</th>
            <th>Condition</th>
            <th>Taken by</th>
            <th>Department</th>
            <th>Site</th>
            <th>Given by</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($inventories as $index => $inventory)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($inventory->date)->format('d M Y') }}</td>
                <td>{{ $inventory->item }}</td>
                <td class="num">{{ $inventory->qty }}</td>
                <td>{{ $inventory->serial_number ?: '—' }}</td>
                <td>{{ ucfirst($inventory->condition) }}</td>
                <td>{{ $inventory->taken_by }}</td>
                <td>{{ $inventory->department }}</td>
                <td>{{ $inventory->site ?: '—' }}</td>
                <td>{{ $inventory->giver->name ?? '—' }}</td>
                <td @class(['pending' => $inventory->status !== 'approved'])>{{ $inventory->status === 'approved' ? 'Approved' : 'Awaiting approval' }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="empty">No inventory records for {{ $year }}.</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
