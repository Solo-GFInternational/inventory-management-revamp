<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">
                Inventory Dashboard
            </h2>

            <a href="{{ route('stock.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                + Add Stock
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Filters --}}
            <form method="GET" class="bg-white p-4 rounded shadow flex flex-col sm:flex-row sm:items-center gap-4">
                <select name="site" class="border rounded px-3 py-2 w-full sm:w-1/3">
                    <option value="">All Sites</option>
                    @foreach($sites as $s)
                        <option value="{{ $s }}" {{ ($site ?? '') == $s ? 'selected' : '' }}>
                            {{ $s }}
                        </option>
                    @endforeach
                </select>

                <select name="department" class="border rounded px-3 py-2 w-full sm:w-1/3">
                    <option value="">All Departments</option>
                    @foreach($departments as $d)
                        <option value="{{ $d }}" {{ ($department ?? '') == $d ? 'selected' : '' }}>
                            {{ $d }}
                        </option>
                    @endforeach
                </select>

                <button class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-900 transition self-start sm:self-auto">
                    Filter
                </button>
            </form>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-blue-50 p-4 rounded shadow text-center hover:shadow-lg transition">
                    <p class="text-gray-500">Total Items</p>
                    <p class="text-2xl font-bold">{{ $items->count() }}</p>
                </div>

                <div class="bg-green-50 p-4 rounded shadow text-center hover:shadow-lg transition">
                    <p class="text-gray-500">Total Quantity</p>
                    <p class="text-2xl font-bold">{{ $items->sum('total_qty') }}</p>
                </div>

                <div class="bg-yellow-50 p-4 rounded shadow text-center hover:shadow-lg transition">
                    <p class="text-gray-500">Site</p>
                    <p class="font-semibold">{{ $site ?? 'All' }}</p>
                </div>

                <div class="bg-purple-50 p-4 rounded shadow text-center hover:shadow-lg transition">
                    <p class="text-gray-500">Department</p>
                    <p class="font-semibold">{{ $department ?? 'All' }}</p>
                </div>
            </div>

            {{-- Inventory Table --}}
            <div class="bg-white shadow rounded overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left">Item</th>
                            <th class="px-4 py-2 text-right">Total Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-2 border">{{ $item->item }}</td>
                                <td class="px-4 py-2 border text-right font-semibold">{{ $item->total_qty }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center py-4 text-gray-500">No records found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
