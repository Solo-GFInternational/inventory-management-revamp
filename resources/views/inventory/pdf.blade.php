<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory List</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }
        h2 {
            text-align: center;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
        }
        th {
            background-color: #e5e5e5;
        }
    </style>
</head>
<body>

<h2>Inventory List</h2>
<p>Date: {{ now()->format('d-m-Y') }}</p>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Date</th>
            <th>Item</th>
            <th>Qty</th>
            <th>Condition</th>
            <th>Edited By</th>
        </tr>
    </thead>
    <tbody>
        @foreach($inventories as $index => $inventory)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $inventory->date }}</td>
                <td>{{ $inventory->item }}</td>
                <td>{{ $inventory->qty }}</td>
                <td>{{ ucfirst($inventory->condition) }}</td>
                <td>{{ $inventory->editor->name ?? 'Unknown' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
