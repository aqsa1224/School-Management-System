<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $type = $this->route('feeType');

        return [
            'name'           => ['required', 'string', 'max:100', Rule::unique('fee_types', 'name')->ignore($type)],
            'default_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99', 'decimal:0,2'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'fee type name', 'default_amount' => 'default amount'];
    }
}
