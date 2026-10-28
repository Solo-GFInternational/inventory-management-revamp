@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Dashboard" description="Stock on the shelf, equipment issued, and what is waiting for approval." />

    {{-- Filament's stats-overview widget: icon + label, then the big value. --}}
    <div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['label' => 'In stock', 'value' => $inStock, 'icon' => 'archive-box', 'tone' => 'text-primary-500',
             'hint' => 'Units on the shelf now'],
            ['label' => 'Given out', 'value' => $givenOut, 'icon' => 'arrow-up-tray', 'tone' => 'text-emerald-500',
             'hint' => 'New units issued, all time'],
            ['label' => 'Pending approvals', 'value' => $pendingCount, 'icon' => 'clock', 'tone' => 'text-amber-500',
             'hint' => 'Additions and edits awaiting review',
             'link' => auth()->user()->isAdmin() ? route('inventory.pending') : null],
            ['label' => 'Issued this month', 'value' => $issuedThisMonth, 'icon' => 'calendar-days', 'tone' => 'text-gray-400',
             'hint' => 'Units issued in '.now()->format('F Y')],
        ] as $stat)
            <div class="flex flex-col rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-center gap-x-2">
                    <x-icon :name="$stat['icon']" class="size-5 {{ $stat['tone'] }}" />
                    <span class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</span>
                </div>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 tabular-nums">{{ number_format($stat['value']) }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $stat['hint'] }}</p>
                @if (! empty($stat['link']))
                    <a href="{{ $stat['link'] }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:underline">
                        Review approvals
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <x-card title="Quantity by item" icon="chart-bar" subtitle="Approved issues, totalled per item.">
            <form method="GET" action="{{ route('dashboard') }}"
                  class="flex flex-col gap-3 border-b border-gray-200 px-6 py-4 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="site" class="gf-label">Site</label>
                    <select id="site" name="site" class="gf-input select-arrow">
                        <option value="">All sites</option>
                        @foreach ($sites as $option)
                            <option value="{{ $option }}" @selected($site === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-0 flex-1">
                    <label for="department" class="gf-label">Department</label>
                    <select id="department" name="department" class="gf-input select-arrow">
                        <option value="">All departments</option>
                        @foreach ($departments as $option)
                            <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <x-button type="submit" icon="funnel">Filter</x-button>
                    @if ($site || $department)
                        <x-button color="gray" :href="route('dashboard')">Reset</x-button>
                    @endif
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="gf-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-end">Total quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $row)
                            <tr>
                                <td class="font-medium">{{ $row->item }}</td>
                                <td class="text-end tabular-nums">{{ number_format($row->total_qty) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-12 text-center">
                                    <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-gray-100">
                                        <x-icon name="x-mark" class="size-6 text-gray-500" />
                                    </div>
                                    <p class="text-base font-semibold text-gray-950">
                                        {{ $site || $department ? 'No issued items for this filter.' : 'No issued items yet.' }}
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($items->isNotEmpty())
                        <tfoot>
                            <tr class="border-t border-gray-200 bg-gray-50">
                                <td class="px-3 py-3 text-sm font-semibold first:ps-6">Total</td>
                                <td class="px-3 py-3 text-end text-sm font-semibold tabular-nums last:pe-6">{{ number_format($items->sum('total_qty')) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </x-card>

        <x-card title="Recent issues" icon="clipboard-document-list" subtitle="The latest equipment recorded.">
            <x-slot:actions>
                <x-button color="gray" size="sm" :href="route('inventory.index')">View all</x-button>
            </x-slot:actions>

            <div class="overflow-x-auto">
                <table class="gf-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th class="text-end">Qty</th>
                            <th>Taken by</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $issue)
                            <tr>
                                <td class="whitespace-nowrap text-gray-500">{{ \Illuminate\Support\Carbon::parse($issue->date)->format('d/m/Y') }}</td>
                                <td class="w-full max-w-0">
                                    <p class="truncate font-medium">{{ $issue->item }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ collect([$issue->department, $issue->site])->filter()->implode(' · ') }}</p>
                                </td>
                                <td class="text-end tabular-nums">{{ $issue->qty }}</td>
                                <td class="whitespace-nowrap">{{ $issue->taken_by }}</td>
                                <td>
                                    @if ($issue->status === 'pending')
                                        <x-badge classes="bg-amber-50 text-amber-700 ring-amber-600/20">Pending</x-badge>
                                    @else
                                        <x-badge classes="bg-emerald-50 text-emerald-700 ring-emerald-600/20">Approved</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-gray-100">
                                        <x-icon name="x-mark" class="size-6 text-gray-500" />
                                    </div>
                                    <p class="text-base font-semibold text-gray-950">Nothing issued yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
