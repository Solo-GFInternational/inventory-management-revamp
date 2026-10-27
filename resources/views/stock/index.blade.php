<x-app-layout>
    <x-slot name="header">Stock</x-slot>

    <div class="p-6">
        <a href="{{ route('stock.create') }}"
           class="bg-blue-500 text-white px-4 py-2 rounded mt-2">
            Add Stock
        </a>

        <table class="mt-4 w-full border">
            <tr class="bg-gray-100">
                <th class="border p-2">Item</th>
                <th class="border p-2">Available Qty</th>
            </tr>

            @foreach($stocks as $stock)
                <tr>
                    <td class="border p-2">{{ $stock->item }}</td>
                    <td class="border p-2">{{ $stock->qty }}</td>
                </tr>
            @endforeach
        </table>
    </div>
</x-app-layout>
