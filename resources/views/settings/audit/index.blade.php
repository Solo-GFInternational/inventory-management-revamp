@extends('layouts.app')
@section('title', 'Audit trail')

@use('App\Support\AuditTrail')

@php
    // Badge colours by AuditTrail::type(). Kept here so Tailwind sees the classes.
    $typeClasses = [
        'sign_in' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'failed_sign_in' => 'bg-red-50 text-red-700 ring-red-600/20',
        'navigation' => 'bg-gray-50 text-gray-600 ring-gray-500/20',
        'changes' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
    ];
@endphp

@section('content')
    <x-settings-tabs active="audit" />

    <x-card title="Audit trail" icon="clipboard-document-list"
            subtitle="Read-only. Signing in and out, pages opened and every change to inventory, stock, types and users, newest first.">
        {{-- Table toolbar: filters above the table, at the right. --}}
        <form method="GET" class="flex flex-wrap items-end justify-end gap-3 px-6 py-4">
            <select name="user" aria-label="User" class="gf-input select-arrow w-full sm:w-56">
                <option value="">All users</option>
                <option value="system" @selected($filters['user'] === 'system')>{{ AuditTrail::SYSTEM }}</option>
                @foreach ($users as $id => $name)
                    <option value="{{ $id }}" @selected($filters['user'] === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <select name="type" aria-label="Type" class="gf-input select-arrow w-full sm:w-52">
                <option value="">All types</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <label class="flex w-full items-center gap-2 text-sm text-gray-500 sm:w-auto">
                From
                <input type="date" name="from" value="{{ $filters['from'] }}" aria-label="Date from" class="gf-input sm:w-40">
            </label>
            <label class="flex w-full items-center gap-2 text-sm text-gray-500 sm:w-auto">
                Until
                <input type="date" name="until" value="{{ $filters['until'] }}" aria-label="Date until" class="gf-input sm:w-40">
            </label>
            <x-button type="submit" color="gray" icon="funnel">Filter</x-button>
            @if ($hasFilters)
                <x-button color="gray" :href="route('audit.index')" icon="x-mark">Clear</x-button>
            @endif
        </form>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="gf-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Type</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>About</th>
                        <th>IP address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        @php
                            $changes = AuditTrail::type($entry) === AuditTrail::TYPE_CHANGES ? AuditTrail::changes($entry, $names) : [];
                            $hasOld = collect($changes)->contains(fn ($change) => $change['old'] !== null);
                            $hasNew = collect($changes)->contains(fn ($change) => $change['new'] !== null);
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap text-gray-500 tabular-nums">{{ $entry->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <x-badge :classes="$typeClasses[AuditTrail::type($entry)]">{{ AuditTrail::typeLabel($entry) }}</x-badge>
                            </td>
                            <td @class(['whitespace-nowrap', 'font-medium' => $entry->causer_id !== null, 'text-gray-500' => $entry->causer_id === null])>
                                {{ AuditTrail::who($entry) }}
                            </td>
                            <td>
                                @if ($changes)
                                    {{-- The fields that changed, old → new. --}}
                                    <details class="group">
                                        <summary class="flex cursor-pointer list-none items-center gap-1 [&::-webkit-details-marker]:hidden">
                                            {{ AuditTrail::action($entry) }}
                                            <span class="inline-flex items-center gap-0.5 text-xs font-medium text-primary-600">
                                                {{ count($changes) }} {{ \Illuminate\Support\Str::plural('field', count($changes)) }}
                                                <x-icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                                            </span>
                                        </summary>
                                        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-lg bg-gray-50 px-3 py-2 text-xs">
                                            @foreach ($changes as $change)
                                                <dt class="font-medium text-gray-500">{{ $change['field'] }}</dt>
                                                <dd class="break-words text-gray-950">
                                                    @if ($hasOld && $hasNew)
                                                        <span class="text-gray-500 line-through decoration-gray-400">{{ $change['old'] ?? '—' }}</span>
                                                        <span class="px-1 text-gray-400">→</span>
                                                        <span>{{ $change['new'] ?? '—' }}</span>
                                                    @else
                                                        {{ $change['new'] ?? $change['old'] ?? '—' }}
                                                    @endif
                                                </dd>
                                            @endforeach
                                        </dl>
                                    </details>
                                @else
                                    {{ AuditTrail::action($entry) }}
                                @endif
                            </td>
                            <td class="text-gray-500">{{ AuditTrail::about($entry, $names) ?? '—' }}</td>
                            {{-- Recorded from the request; none for entries made outside one (seeders, commands). --}}
                            <td class="font-mono text-xs whitespace-nowrap text-gray-500">{{ AuditTrail::ip($entry) ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-gray-100">
                                    <x-icon name="x-mark" class="size-6 text-gray-500" />
                                </div>
                                <p class="text-base font-semibold text-gray-950">No audit entries</p>
                                @if ($hasFilters)
                                    <p class="mt-1 text-sm text-gray-500">Try clearing the filters.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($entries->hasPages())
            <div class="border-t border-gray-200 px-6 py-3">{{ $entries->links() }}</div>
        @endif
    </x-card>
@endsection
