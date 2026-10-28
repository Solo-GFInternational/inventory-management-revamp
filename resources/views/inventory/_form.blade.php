{{--
    The inventory form's fields, shared by the create modal and each row's
    edit modal on the Inventory page.
      $inventory    the row being edited (stock, department and site loaded), or null when creating
      $stocks       active stock rows (id, item, qty), for the Item select
      $departments  Type::options('department'), id => name
      $sites        Type::options('site'), id => name
      $prefix       prefix for the field ids, unique per form on the page
      $useOld       whether to repopulate from old() input — only the form that
                    was actually submitted should
--}}
@php
    $value = fn (string $field, $default = null) => $useOld ? old($field, $default) : $default;
    $isSelected = fn (string $field, $default, $id) => (string) $value($field, $default) === (string) $id;

    // An old row may hold an item, department or site that has since been
    // deactivated; keep it selectable so saving the row doesn't silently change it.
    $extraStock = $inventory?->stock && ! $stocks->contains('id', $inventory->stock_id) ? $inventory->stock : null;
    $departmentOptions = $departments;
    if ($inventory?->department && ! $departments->has($inventory->department_id)) {
        $departmentOptions = $departments->union([$inventory->department_id => $inventory->department->type_name]);
    }
    $siteOptions = $sites;
    if ($inventory?->site && ! $sites->has($inventory->site_id)) {
        $siteOptions = $sites->union([$inventory->site_id => $inventory->site->type_name]);
    }

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
        <label for="{{ $prefix }}stock_id" class="gf-label">Item</label>
        <select id="{{ $prefix }}stock_id" name="stock_id" required class="gf-input select-arrow">
            <option value="">Select an item</option>
            @foreach ($stocks as $stock)
                <option value="{{ $stock->id }}" @selected($isSelected('stock_id', $inventory?->stock_id, $stock->id))>
                    {{ $stock->item }} ({{ (int) $stock->qty }} in stock)
                </option>
            @endforeach
            @if ($extraStock)
                <option value="{{ $extraStock->id }}" @selected($isSelected('stock_id', $inventory->stock_id, $extraStock->id))>{{ $extraStock->item }} (not in stock list)</option>
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
        <label for="{{ $prefix }}department_id" class="gf-label">Department</label>
        <select id="{{ $prefix }}department_id" name="department_id" required class="gf-input select-arrow">
            <option value="">Select a department</option>
            @foreach ($departmentOptions as $id => $name)
                <option value="{{ $id }}" @selected($isSelected('department_id', $inventory?->department_id, $id))>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="{{ $prefix }}site_id" class="gf-label">Site</label>
        <select id="{{ $prefix }}site_id" name="site_id" required class="gf-input select-arrow">
            <option value="">Select a site</option>
            @foreach ($siteOptions as $id => $name)
                <option value="{{ $id }}" @selected($isSelected('site_id', $inventory?->site_id, $id))>{{ $name }}</option>
            @endforeach
        </select>
    </div>
</div>
