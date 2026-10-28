{{--
    The type form's fields, shared by the "New type" and "Edit type" modals.
      $type    the type being edited, or null when creating
      $groups  group value => label (Type::GROUPS)
      $prefix  prefix for the field ids, unique per form on the page
      $useOld  whether to repopulate from old() input — only the form that was
               actually submitted should
      $defaultGroup  the group preselected when creating (the current filter)
--}}
@php
    $value = fn (string $field, $default = null) => $useOld ? old($field, $default) : $default;
    $selectedGroup = $value('type_group', $defaultGroup ?? null);
@endphp

<div class="space-y-6">
    <div>
        <label for="{{ $prefix }}type_group" class="gf-label">Group</label>
        @if ($type === null)
            <select id="{{ $prefix }}type_group" name="type_group" required class="gf-input select-arrow">
                <option value="" disabled @selected(blank($selectedGroup))>Choose a group</option>
                @foreach ($groups as $groupValue => $groupLabel)
                    <option value="{{ $groupValue }}" @selected($selectedGroup === $groupValue)>{{ $groupLabel }}</option>
                @endforeach
            </select>
            <p class="gf-hint">The list this option appears in. It can't be changed later.</p>
        @else
            {{-- Fixed: inventory rows use this type as their department (or site), so moving it would change what they mean. --}}
            <div id="{{ $prefix }}type_group" class="gf-readonly-field">{{ $groups[$type->type_group] ?? $type->type_group }}</div>
            <p class="gf-hint">The group can't be changed: records already use this as a {{ \Illuminate\Support\Str::lower($groups[$type->type_group] ?? $type->type_group) }}. Deactivate it and add a new type instead.</p>
        @endif
    </div>

    <div>
        <label for="{{ $prefix }}type_name" class="gf-label">Name</label>
        <input id="{{ $prefix }}type_name" name="type_name" type="text" value="{{ $value('type_name', $type?->type_name) }}" required maxlength="100"
               autocomplete="off" class="gf-input">
        @if ($type === null)
            <p class="gf-hint">If this group has a deactivated type with this name, that type is reactivated instead.</p>
        @endif
    </div>

    <div>
        <label for="{{ $prefix }}sort_order" class="gf-label">Order</label>
        <input id="{{ $prefix }}sort_order" name="sort_order" type="number" min="0" max="9999" step="1" required
               value="{{ $value('sort_order', $type?->sort_order ?? 0) }}" class="gf-input sm:w-40">
        <p class="gf-hint">Lower numbers come first in the dropdown; types with the same number are listed alphabetically.</p>
    </div>
</div>
