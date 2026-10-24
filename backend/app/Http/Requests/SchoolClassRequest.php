<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $class = $this->route('schoolClass'); // update par maujooda class, create par null

        return [
            'name'        => ['required', 'string', 'max:100', Rule::unique('school_classes', 'name')->ignore($class)],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:1000'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'class name', 'sort_order' => 'sort order'];
    }
}
