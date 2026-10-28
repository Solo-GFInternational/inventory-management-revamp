<?php

namespace App\Http\Requests;

use App\Models\Type;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Settings > Types, "Edit type". Only the name and order can change: the
 * group is fixed once created, because inventory rows already point at the
 * type as a department (or site), and moving it to another group would
 * turn their department into a site.
 */
class UpdateTypeRequest extends FormRequest
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
        /** @var Type $type */
        $type = $this->route('type');

        return [
            'type_name' => ['required', 'string', 'max:100',
                Rule::unique(Type::class, 'type_name')
                    ->where('type_group', $type->type_group)
                    ->where('active_yn', true)
                    ->ignore($type->getKey())],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type_name.unique' => 'This group already has an active type with this name.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type_name' => 'name',
            'sort_order' => 'order',
        ];
    }
}
