<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Pending Inventory Approvals
        </h2>
    </x-slot>

    <div class="p-6">
        <a href="{{ route('inventory.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded">
            Back to Inventory List
        </a>

        <table class="mt-4 w-full border">
            <tr class="bg-gray-200">
                <th class="border p-2">Date</th>
                <th class="border p-2">Item</th>
                <th class="border p-2">Qty</th>
                <th class="border p-2">Condition</th>
                <th class="border p-2">Edited By</th>
                <th class="border p-2">Action</th>
            </tr>

            @foreach($inventories as $inventory)
            <tr>
                <td class="border p-2">{{ $inventory->date }}</td>
                <td class="border p-2">{{ $inventory->item }}</td>
                <td class="border p-2">{{ $inventory->qty }}</td>
                <td class="border p-2">{{ ucfirst($inventory->condition) }}</td>
                <td class="border p-2">{{ $inventory->editor->name ?? 'Unknown' }}</td>
                <td class="border p-2">
                    <form action="{{ route('inventory.approve', $inventory->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-green-500 text-white px-4 py-1 rounded">
                            Approve
                        </button>
                        
                    </form>
                </td>
            </tr>
            @endforeach
        </table>
    </div>
</x-app-layout>
