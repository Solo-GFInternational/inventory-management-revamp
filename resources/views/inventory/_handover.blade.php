{{--
    Handover buttons for the record's page header (HandoverController). An
    approved item still with someone can be Returned to stock; a returned one
    can then be Assigned to a new person on the same record, keeping its
    barcode. Records awaiting approval or deactivated show neither.
      $inventory  the record (department and site loaded)
--}}
@php
    $reopen = old('_modal');
    $canReturn = $inventory->active_yn && ! $inventory->isPending() && ! $inventory->isReturned();
    $canAssign = $inventory->active_yn && ! $inventory->isPending() && $inventory->isReturned();
    $isAdmin = auth()->user()->isAdmin();
@endphp

@if ($canReturn)
    <x-button color="gray" icon="arrow-uturn-left" data-modal-open="return-inventory">Return to stock</x-button>

    <x-modal id="return-inventory" heading="Return to stock" icon="arrow-uturn-left"
             :description="'Back from '.$inventory->taken_by.'. Once returned, the item can be assigned to a new person with the same barcode.'"
             :open="$reopen === 'return-inventory'">
        @if ($reopen === 'return-inventory')
            <x-form-errors />
        @endif
        <form id="return-inventory-form" method="POST" action="{{ route('inventory.return', $inventory) }}"
              data-confirm="{{ $inventory->condition === 'new' ? 'The '.$inventory->stock?->item.' goes back into stock.' : 'The item is marked as returned.' }}"
              data-confirm-title="Return this item to stock?" data-confirm-button="Yes, return it"
              data-processing="Returning…">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="return-inventory">
            <label for="return-reason" class="gf-label">Reason <span class="font-normal text-gray-400">(optional)</span></label>
            <textarea id="return-reason" name="reason" rows="3" maxlength="500" class="gf-input"
                      placeholder="e.g. Staff member left, replaced with a newer model">{{ $reopen === 'return-inventory' ? old('reason') : '' }}</textarea>
        </form>

        <x-slot:footer>
            <x-button type="submit" form="return-inventory-form">Return to stock</x-button>
            <x-button color="gray" data-modal-close>Cancel</x-button>
        </x-slot:footer>
    </x-modal>
@elseif ($canAssign)
    @php
        $useOld = $reopen === 'assign-inventory';
        $value = fn (string $field, $default = null) => $useOld ? old($field, $default) : $default;
        $departments = \App\Models\Type::options(\App\Models\Type::DEPARTMENT);
        $sites = \App\Models\Type::options(\App\Models\Type::SITE);
    @endphp

    <x-button icon="user-plus" data-modal-open="assign-inventory">Assign to new person</x-button>

    <x-modal id="assign-inventory" heading="Assign to new person" width="max-w-3xl" icon="user-plus"
             :description="$isAdmin
                 ? 'The same record and barcode, with its new holder.'.($inventory->condition === 'new' ? ' The item is taken out of stock again.' : '')
                 : 'The same record and barcode, with its new holder. Goes to an administrator for approval; stock is taken when it is approved.'"
             :open="$useOld">
        @if ($useOld)
            <x-form-errors />
        @endif
        <form id="assign-inventory-form" method="POST" action="{{ route('inventory.assign', $inventory) }}"
              data-processing="{{ $isAdmin ? 'Assigning…' : 'Submitting for approval…' }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="assign-inventory">

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="assign-date" class="gf-label">Date</label>
                    <input id="assign-date" name="date" type="date" required class="gf-input"
                           value="{{ $value('date', now()->toDateString()) }}">
                    <p class="gf-hint">When it is handed over.</p>
                </div>

                <div>
                    <label for="assign-taken_by" class="gf-label">Taken by</label>
                    <input id="assign-taken_by" name="taken_by" type="text" required maxlength="255" placeholder="Full name"
                           value="{{ $value('taken_by') }}" class="gf-input">
                </div>

                <div>
                    <label for="assign-department_id" class="gf-label">Department</label>
                    <select id="assign-department_id" name="department_id" required class="gf-input select-arrow">
                        <option value="">Select a department</option>
                        @foreach ($departments as $id => $name)
                            <option value="{{ $id }}" @selected((string) $value('department_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="assign-site_id" class="gf-label">Site</label>
                    <select id="assign-site_id" name="site_id" required class="gf-input select-arrow">
                        <option value="">Select a site</option>
                        @foreach ($sites as $id => $name)
                            <option value="{{ $id }}" @selected((string) $value('site_id', $inventory->site_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="assign-notes" class="gf-label">Notes <span class="font-normal text-gray-400">(optional)</span></label>
                    <textarea id="assign-notes" name="notes" rows="3" maxlength="500" class="gf-input">{{ $value('notes') }}</textarea>
                    <p class="gf-hint">Item, condition, serial number and barcode stay the same.</p>
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-button type="submit" form="assign-inventory-form">{{ $isAdmin ? 'Assign' : 'Submit for approval' }}</x-button>
            <x-button color="gray" data-modal-close>Cancel</x-button>
        </x-slot:footer>
    </x-modal>
@elseif ($inventory->active_yn && $inventory->isPending())
    <p class="text-sm text-gray-500">Return and handover are available once this record is approved.</p>
@endif
