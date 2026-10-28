@use('App\Http\Controllers\InventoryUpdateController')
@use('App\Models\InventoryUpdate')

{{--
    The item's timeline (InventoryUpdateController): an add-update form, then
    every active entry newest first — notes people typed and the returned /
    assigned events the handover actions write — ending with the record's
    creation, built from the inventory row itself (not stored as an entry).
    Expects $inventory with updates.author loaded (InventoryController@show).
    Times as on Settings > Audit trail.
--}}
@php
    // Icon, dot colour and label by kind. Kept here so Tailwind sees the classes.
    $kinds = [
        InventoryUpdate::NOTE => ['icon' => 'chat-bubble-left-ellipsis', 'label' => 'Note', 'classes' => 'bg-primary-50 text-primary-600 ring-primary-600/20'],
        InventoryUpdate::RETURNED => ['icon' => 'arrow-uturn-left', 'label' => 'Returned to stock', 'classes' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
        InventoryUpdate::ASSIGNED => ['icon' => 'user-plus', 'label' => 'Assigned', 'classes' => 'bg-sky-50 text-sky-700 ring-sky-600/20'],
    ];
    $created = [
        'when' => $inventory->created_at ?? \Illuminate\Support\Carbon::parse($inventory->date),
        'who' => $inventory->giver?->name ?? $inventory->creator?->name,
    ];
    // taken_by is the current holder. Once the item has been handed over, its
    // first "Returned" entry names the original one, so the created entry doesn't.
    $handedOver = $inventory->updates->contains(fn ($update) => $update->kind !== InventoryUpdate::NOTE);
    $viewer = auth()->user();
@endphp

<x-card id="timeline" title="Timeline" icon="clock" class="mt-6 scroll-mt-6"
        subtitle="Notes on this item, such as parts fitted or repairs, and its handovers, newest first.">
    @if ($inventory->active_yn)
        <form method="POST" action="{{ route('inventory.updates.store', $inventory) }}" class="border-b border-gray-200 px-6 py-4"
              data-processing="Adding update…">
            @csrf
            <label for="timeline-body" class="gf-label">Add an update</label>
            <textarea id="timeline-body" name="body" rows="3" maxlength="{{ InventoryUpdateController::MAX_LENGTH }}"
                      placeholder="e.g. New battery fitted, old one sent for recycling"
                      @error('body', 'timeline') aria-invalid="true" aria-describedby="timeline-body-error" @enderror
                      @class(['gf-input', 'ring-danger-600' => $errors->timeline->has('body')])>{{ old('body') }}</textarea>
            <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
                <div>
                    @error('body', 'timeline')
                        <p id="timeline-body-error" class="text-sm text-danger-600">{{ $message }}</p>
                    @else
                        <p class="text-sm text-gray-500">Up to {{ number_format(InventoryUpdateController::MAX_LENGTH) }} characters. Everyone who can see this item can read it.</p>
                    @enderror
                </div>
                <x-button type="submit" icon="plus">Add update</x-button>
            </div>
        </form>
    @endif

    <div class="px-6 py-5">
        @if ($inventory->updates->isEmpty())
            <p class="mb-5 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500">
                No updates yet.
                @if ($inventory->active_yn)
                    Add the first one above, e.g. a part fitted or a repair.
                @endif
            </p>
        @endif

        <ol class="relative">
            @foreach ($inventory->updates as $update)
                @php($kind = $kinds[$update->kind] ?? $kinds[InventoryUpdate::NOTE])
                <li class="relative flex gap-4 pb-6">
                    {{-- The line joining this entry's dot to the next one down. --}}
                    <span class="absolute top-9 bottom-0 left-4 -ml-px w-0.5 bg-gray-200" aria-hidden="true"></span>
                    <span class="relative flex size-8 shrink-0 items-center justify-center rounded-full ring-1 {{ $kind['classes'] }}">
                        <x-icon :name="$kind['icon']" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="text-sm text-gray-500">
                                <x-badge :classes="$kind['classes']">{{ $kind['label'] }}</x-badge>
                                <span class="ml-1 font-medium text-gray-950">{{ $update->author?->name ?? \App\Support\AuditTrail::SYSTEM }}</span>
                                <span class="px-1 text-gray-400">·</span>
                                <time datetime="{{ $update->created_at->toIso8601String() }}" class="tabular-nums">{{ $update->created_at->format('d/m/Y H:i:s') }}</time>
                            </p>
                            @if ($inventory->active_yn && InventoryUpdateController::canRemove($viewer, $update))
                                <form method="POST" action="{{ route('inventory.updates.destroy', [$inventory, $update]) }}"
                                      data-confirm="The note is removed from this item's timeline."
                                      data-confirm-title="Remove this update?" data-confirm-button="Yes, remove"
                                      data-processing="Removing update…">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button type="submit" icon="trash" label="Remove update" color="danger" />
                                </form>
                            @endif
                        </div>
                        @if (filled($update->body))
                            <p class="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-sm break-words whitespace-pre-line text-gray-950">{{ $update->body }}</p>
                        @endif
                    </div>
                </li>
            @endforeach

            {{-- Always last: the record being created. --}}
            <li class="relative flex gap-4">
                <span class="relative flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-50 text-gray-500 ring-1 ring-gray-500/20">
                    <x-icon name="document-plus" class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-gray-500">
                        <x-badge classes="bg-gray-50 text-gray-600 ring-gray-500/20">Record created</x-badge>
                        <span class="ml-1 font-medium text-gray-950">{{ $created['who'] ?? \App\Support\AuditTrail::SYSTEM }}</span>
                        <span class="px-1 text-gray-400">·</span>
                        <time datetime="{{ $created['when']->toIso8601String() }}" class="tabular-nums">{{ $created['when']->format('d/m/Y H:i:s') }}</time>
                    </p>
                    <p class="mt-2 text-sm text-gray-950">
                        {{ $handedOver ? 'Issued' : 'Issued to '.$inventory->taken_by }}{{ $created['who'] ? ' by '.$created['who'] : '' }}.
                    </p>
                </div>
            </li>
        </ol>
    </div>
</x-card>
