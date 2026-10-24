<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        return [
            'amount'       => ['required', 'numeric', 'gt:0', 'max:9999999.99', 'decimal:0,2'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'method'       => ['required', Rule::in(['cash', 'bank', 'online'])],
            'note'         => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt'                    => 'Amount 0 se zyada hona chahiye.',
            'payment_date.before_or_equal' => 'Payment ki tareekh aane wali nahi ho sakti.',
        ];
    }

    public function attributes(): array
    {
        return ['payment_date' => 'payment date'];
    }
}
