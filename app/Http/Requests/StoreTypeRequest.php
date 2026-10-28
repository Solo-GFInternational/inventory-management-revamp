<?php

namespace App\Http\Requests;

use App\Models\Type;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Settings > Types, "New type". Admin-only by route middleware.
 *
 * The name only has to be unique among the group's active types, matching
 * the filtered index ux_types_type_group_type_name: a name a deactivated
 * type had is accepted here, and TypeController::store() reactivates that
 * type instead of adding a second row.
 */
class StoreTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'type_group' => ['required', 'string', Rule::in(array_keys(Type::GROUPS))],
            // The model class, not 'ref.types': a dotted table string is read
            // as connection.table.
            'type_name' => ['required', 'string', 'max:100',
                Rule::unique(Type::class, 'type_name')
                    ->where('type_group', (string) $this->input('type_group'))
                    ->where('active_yn', true)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type_group.in' => 'Choose one of the listed groups.',
            'type_name.unique' => 'This group already has an active type with this name.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type_group' => 'group',
            'type_name' => 'name',
            'sort_order' => 'order',
        ];
    }
}
