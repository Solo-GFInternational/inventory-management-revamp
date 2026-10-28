{{--
    One person on the create form: taken by, department and serial number,
    posted as people[N][...]. Also the <template> row app.js clones, with
    N = __INDEX__ (app.js re-numbers every row's names after adding/removing).
      $index        the row's position, or '__INDEX__' in the template
      $person       the submitted values (old input), or [] for a blank row
      $canRemove    whether the Remove button shows (more than one row)
      $showErrors   whether to show this row's validation errors
      $departments  Type::options('department'), id => name
--}}
@php
    $rowErrors = $showErrors
        ? collect(['taken_by', 'department_id', 'serial_number'])->map(fn ($field) => $errors->first("people.{$index}.{$field}"))->filter()
        : collect();
@endphp

<div data-person class="rounded-lg bg-gray-50 p-3 ring-1 ring-gray-950/5">
    <div class="flex items-start gap-3">
        <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-3">
            {{-- Each label wraps its field, so no ids need re-numbering. --}}
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-gray-700">Taken by</span>
                <input name="people[{{ $index }}][taken_by]" type="text" required maxlength="255" placeholder="Full name"
                       value="{{ $person['taken_by'] ?? '' }}"
                       @class(['gf-input', 'ring-danger-600' => $showErrors && $errors->has("people.{$index}.taken_by")])>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-gray-700">Department</span>
                <select name="people[{{ $index }}][department_id]" required
                        @class(['gf-input select-arrow', 'ring-danger-600' => $showErrors && $errors->has("people.{$index}.department_id")])>
                    <option value="">Select a department</option>
                    @foreach ($departments as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($person['department_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-gray-700">
                    Serial number <span class="font-normal text-gray-400">(optional)</span>
                </span>
                <input name="people[{{ $index }}][serial_number]" type="text" maxlength="255"
                       value="{{ $person['serial_number'] ?? '' }}" class="gf-input">
            </label>
        </div>
        <x-icon-button icon="trash" label="Remove person" color="danger" class="mt-5" data-person-remove :hidden="! $canRemove" />
    </div>

    @foreach ($rowErrors as $message)
        <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
    @endforeach
</div>
