<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Inventory List
        </h2>
    </x-slot>

    <div class="flex justify-between items-center mb-4">
    {{-- Add Inventory Button --}}
    <a href="{{ route('inventory.create') }}"
       class="bg-blue-500 text-white px-4 py-2 rounded mt-2">
        Add Inventory
    </a>
    

    <style>
    .no-native-arrow {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: none;
    }
</style>

<div class="flex items-center gap-1 ml-2">
    <!-- Year selector -->
    <div class="relative">
        <select id="yearSelect"
            class="no-native-arrow border rounded px-3 pr-9 py-2 bg-white text-sm">
            @for($y = now()->year; $y >= 2020; $y--)
                <option value="{{ $y }}">{{ $y }}</option>
            @endfor
        </select>

        <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-gray-600 text-xs">
            
        </span>
    </div>

    <!-- Download button -->
    <a href="{{ route('inventory.download.pdf') }}"
       id="downloadPdfBtn"
       class="bg-red-600 text-white px-3 py-2 rounded text-sm">
        Download PDF
    </a>
</div>

<script>
    document.getElementById('downloadPdfBtn').addEventListener('click', function (e) {
        e.preventDefault();
        const year = document.getElementById('yearSelect').value;
        window.location.href = this.href + '?year=' + year;
    });
</script>



    {{-- Search Filters --}}
    <form method="GET" class="flex gap-2">
        {{-- Search Taken By --}}
        <input
            type="text"
            name="taken_by"
            value="{{ request('taken_by') }}"
            placeholder="Search Taken By"
            class="border rounded px-2 py-1"
        >

        {{-- Search Site --}}
        <select name="site" class="border rounded px-2 py-1">
            <option value="">All Sites</option>
            @foreach(\App\Models\Inventory::select('site')->distinct()->pluck('site') as $site)
                <option value="{{ $site }}" {{ request('site') == $site ? 'selected' : '' }}>
                    {{ $site }}
                </option>
            @endforeach
        </select>

        <button class="text-blue-600 hover:underline px-3 py-2 border rounded">
            Search
        </button>
    </form>
</div>


    <div class="overflow-x-auto mt-4">
        <table class="min-w-full bg-white border">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-2 px-4 border-b">Date</th>
                    <th class="py-2 px-4 border-b">Item</th>
                    <th class="py-2 px-4 border-b">Qty</th>
                    <th class="py-2 px-4 border-b">Serial Number</th>
                    <th class="py-2 px-4 border-b">Condition</th>
                    <th class="py-2 px-4 border-b">Given By</th>
                    <th class="py-2 px-4 border-b">Taken By</th>
                    <th class="py-2 px-4 border-b">Department</th>
                    <th class="py-2 px-4 border-b">Site</th>
                    <th class="py-2 px-4 border-b">Status</th>
                    <th class="py-2 px-4 border-b">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inventories as $inventory)
                @php
                    $changes = $inventory->change_log ?? [];
                @endphp
                <tr class="text-center hover:bg-gray-50">
                    <td class="py-2 px-4 border-b {{ isset($changes['date']) ? 'text-red-600 font-semibold' : '' }}">
                        {{ $inventory->date }}
                    </td>
                    <td class="py-2 px-4 border-b
    {{ isset($changes['item']) ? 'text-red-600 font-semibold' : '' }}">
    {{ $inventory->item }}
</td>

                    <td class="py-2 px-4 border-b {{ isset($changes['qty']) ? 'text-red-600 font-semibold' : '' }}">
                        {{ $inventory->qty }}
                    </td>
                    <td class="py-2 px-4 border-b {{ isset($changes['serial_number']) ? 'text-red-600 font-semibold' : '' }}">
                        {{ $inventory->serial_number }}
                    </td>
                    <td class="py-2 px-4 border-b {{ isset($changes['condition']) ? 'text-red-600 font-semibold' : '' }}">
                        {{ ucfirst($inventory->condition) }}
                    </td>
                    <td class="py-2 px-4 border-b">
                        {{ $inventory->given_by_user->name ?? 'N/A' }}
                    </td>
                    <td class="py-2 px-4 border-b {{ isset($changes['taken_by']) ? 'text-red-600 font-semibold' : '' }}">
                        {{ $inventory->taken_by }}
                    </td>
                    <td class="py-2 px-4 border-b {{ isset($changes['department']) ? 'text-red-600 font-semibold' : '' }}">
                        {{ $inventory->department }}
                    </td>
                    <td class="py-2 px-4 border-b {{ isset($changes['site']) ? 'text-red-600 font-semibold' : '' }}">
                        {{ $inventory->site }}
                    </td>
                    <td class="py-2 px-4 border-b">
                        @if($inventory->status === 'approved')
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
                                Approved
                            </span>
                        @elseif($inventory->approval_type === 'new')
                            <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-sm">
                                New Inventory Approval
                            </span>
                        @elseif($inventory->approval_type === 'edit')
                            <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-sm">
                                Edit Approval Pending
                            </span>
                        @endif
                    </td>
                    <td class="border p-2">
                        <div class="flex gap-2 justify-center">

                            {{-- Edit --}}
                            <a href="{{ route('inventory.edit', $inventory->id) }}"
                               class="text-blue-500 hover:underline px-2 py-1 border rounded">
                                Edit
                            </a>

                            {{-- Delete (Admin only) --}}
                            @if(auth()->user()->isAdmin())
                                <form action="{{ route('inventory.destroy', $inventory->id) }}"
                                      method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this item?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-500 hover:underline px-2 py-1 border rounded">
                                        Delete
                                    </button>
                                </form>
                            @endif

                            {{-- Approve (Admin only & Pending only) --}}
                            @if(auth()->user()->isAdmin() && $inventory->status === 'pending')
                                <form action="{{ route('inventory.approve', $inventory->id) }}"
                                      method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button class="text-blue-500 hover:underline px-2 py-1 border rounded">
                                        Approve
                                    </button>
                                </form>
                            @endif

                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
