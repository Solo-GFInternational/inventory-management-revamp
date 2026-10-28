{{--
    The inventory form's fields, shared by the create modal and each row's
    edit modal on the Inventory page.
      $inventory    the row being edited, or null when creating
      $stocks       active stock rows (item, qty), for the Item select
      $departments  config('inventory.departments')
      $sites        config('inventory.sites')
      $prefix       prefix for the field ids, unique per form on the page
      $useOld       whether to repopulate from old() input — only the form that
                    was actually submitted should
--}}
@php
    $value = fn (string $field, $default = null) => $useOld ? old($field, $default) : $default;

    // An old row may name an item, department or site that's no longer listed;
    // keep it selectable so saving the row doesn't silently change it.
    $itemNames = $stocks->pluck('item');
    $extraItem = $inventory && ! $itemNames->contains($inventory->item) ? $inventory->item : null;
    $departmentOptions = $inventory && $inventory->department && ! in_array($inventory->department, $departments, true)
        ? [...$departments, $inventory->department] : $departments;
    $siteOptions = $inventory && $inventory->site && ! in_array($inventory->site, $sites, true)
        ? [...$sites, $inventory->site] : $sites;

    $date = $value('date', $inventory ? substr((string) $inventory->date, 0, 10) : now()->toDateString());
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <label for="{{ $prefix }}date" class="gf-label">Date</label>
        @if ($inventory)
            <input id="{{ $prefix }}date" name="date" type="date" value="{{ $date }}" required class="gf-input">
        @else
            <input id="{{ $prefix }}date" name="date" type="date" value="{{ $date }}" required readonly
                   class="gf-input bg-gray-50 text-gray-500">
            <p class="gf-hint">New records are dated today.</p>
        @endif
    </div>

    <div>
        <label for="{{ $prefix }}condition" class="gf-label">Condition</label>
        <select id="{{ $prefix }}condition" name="condition" required class="gf-input select-arrow">
            @foreach (['new' => 'New (taken from stock)', 'used' => 'Used (not from stock)'] as $key => $label)
                <option value="{{ $key }}" @selected($value('condition', $inventory?->condition ?? 'new') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label for="{{ $prefix }}item" class="gf-label">Item</label>
        <select id="{{ $prefix }}item" name="item" required class="gf-input select-arrow">
            <option value="">Select an item</option>
            @foreach ($stocks as $stock)
                <option value="{{ $stock->item }}" @selected($value('item', $inventory?->item) === $stock->item)>
                    {{ $stock->item }} ({{ (int) $stock->qty }} in stock)
                </option>
            @endforeach
            @if ($extraItem)
                <option value="{{ $extraItem }}" @selected($value('item', $inventory->item) === $extraItem)>{{ $extraItem }} (not in stock list)</option>
            @endif
        </select>
        @if ($stocks->isEmpty())
            <p class="gf-hint">No stock items yet — add them on the Stock page first.</p>
        @endif
    </div>

    <div>
        <label for="{{ $prefix }}qty" class="gf-label">Quantity</label>
        <input id="{{ $prefix }}qty" name="qty" type="number" min="1" step="1" required
               value="{{ $value('qty', $inventory?->qty ?? 1) }}" class="gf-input">
        <p class="gf-hint">For a new item, no more than the number in stock.</p>
    </div>

    <div>
        <label for="{{ $prefix }}serial_number" class="gf-label">
            Serial number <span class="font-normal text-gray-400">(optional)</span>
        </label>
        <input id="{{ $prefix }}serial_number" name="serial_number" type="text" maxlength="255"
               value="{{ $value('serial_number', $inventory?->serial_number) }}" class="gf-input">
    </div>

    <div class="sm:col-span-2">
        <label for="{{ $prefix }}taken_by" class="gf-label">Taken by</label>
        <input id="{{ $prefix }}taken_by" name="taken_by" type="text" required maxlength="255"
               value="{{ $value('taken_by', $inventory?->taken_by) }}" class="gf-input">
    </div>

    <div>
        <label for="{{ $prefix }}department" class="gf-label">Department</label>
        <select id="{{ $prefix }}department" name="department" required class="gf-input select-arrow">
            <option value="">Select a department</option>
            @foreach ($departmentOptions as $department)
                <option value="{{ $department }}" @selected($value('department', $inventory?->department) === $department)>{{ $department }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="{{ $prefix }}site" class="gf-label">Site</label>
        <select id="{{ $prefix }}site" name="site" required class="gf-input select-arrow">
            <option value="">Select a site</option>
            @foreach ($siteOptions as $site)
                <option value="{{ $site }}" @selected($value('site', $inventory?->site) === $site)>{{ $site }}</option>
            @endforeach
        </select>
    </div>
</div>
