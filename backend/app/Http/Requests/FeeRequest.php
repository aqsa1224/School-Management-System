<?php

namespace App\Http\Requests;

use App\Models\Fee;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Ek student ka ek voucher (naya banana ya badalna) */
class FeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $fee = $this->route('fee'); // update par maujooda voucher

        return [
            // Student sirf naya voucher banate waqt chunte hain, badalte waqt nahi
            'student_id'  => $fee ? ['prohibited'] : ['required', 'integer', Rule::exists('students', 'id')->where('status', 'active')],
            'fee_type_id' => ['required', 'integer', 'exists:fee_types,id'],
            'for_month'   => ['nullable', 'date_format:Y-m'],
            'amount'      => ['required', 'numeric', 'gt:0', 'max:9999999.99', 'decimal:0,2'],
            'discount'    => ['nullable', 'numeric', 'min:0', 'lte:amount', 'decimal:0,2'],
            'due_date'    => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.exists'      => 'Ye student nahi mila ya active nahi hai.',
            'student_id.prohibited'  => 'Voucher ka student badla nahi ja sakta.',
            'discount.lte'           => 'Discount, amount se zyada nahi ho sakta.',
            'for_month.date_format'  => 'Month ka format galat hai.',
            'amount.gt'              => 'Amount 0 se zyada hona chahiye.',
        ];
    }

    public function attributes(): array
    {
        return ['student_id' => 'student', 'fee_type_id' => 'fee type', 'for_month' => 'month', 'due_date' => 'due date'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $fee    = $this->route('fee');
                $net    = (float) $this->input('amount') - (float) ($this->input('discount') ?: 0);
                $studentId = $fee ? $fee->student_id : (int) $this->input('student_id');

                // Jo paisa pehle wasool ho chuka, net amount us se kam nahi ho sakta
                if ($fee && $net < $fee->paid_amount) {
                    $validator->errors()->add('amount', 'Is voucher par ' . number_format($fee->paid_amount, 2)
                        . ' wasool ho chuka hai, isliye net amount (amount - discount) us se kam nahi ho sakta.');
                    return;
                }

                // Same student + same fee type + same month ka dobara voucher nahi
                $month = $this->filled('for_month') ? $this->input('for_month') . '-01' : null;
                $dup = Fee::query()
                    ->where('student_id', $studentId)
                    ->where('fee_type_id', $this->input('fee_type_id'))
                    ->when($month, fn ($q) => $q->whereDate('for_month', $month), fn ($q) => $q->whereNull('for_month'))
                    ->when($fee, fn ($q) => $q->where('id', '!=', $fee->id))
                    ->first();

                if ($dup) {
                    $validator->errors()->add('fee_type_id', "Is student ka ye fee type (is month ke liye) pehle se maujood hai: {$dup->voucher_no}.");
                }
            },
        ];
    }
}
