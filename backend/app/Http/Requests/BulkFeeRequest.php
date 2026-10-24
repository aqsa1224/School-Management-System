<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Poori class (ya section) ke liye ek saath vouchers */
class BulkFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        return [
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'section_id'      => ['nullable', 'integer', Rule::exists('sections', 'id')->where('school_class_id', $this->input('school_class_id'))],
            'fee_type_id'     => ['required', 'integer', 'exists:fee_types,id'],
            'for_month'       => ['required', 'date_format:Y-m'],
            'amount'          => ['required', 'numeric', 'gt:0', 'max:9999999.99', 'decimal:0,2'],
            'discount'        => ['nullable', 'numeric', 'min:0', 'lte:amount', 'decimal:0,2'],
            'due_date'        => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount.lte'          => 'Discount, amount se zyada nahi ho sakta.',
            'section_id.exists'     => 'Ye section chuni hui class ka nahi hai.',
            'for_month.required'    => 'Month chunna zaroori hai.',
            'amount.gt'             => 'Amount 0 se zyada hona chahiye.',
        ];
    }

    public function attributes(): array
    {
        return ['school_class_id' => 'class', 'section_id' => 'section', 'fee_type_id' => 'fee type', 'for_month' => 'month', 'due_date' => 'due date'];
    }
}
