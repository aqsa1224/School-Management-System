<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $exam = $this->route('exam'); // update par maujooda exam

        return [
            'name'       => ['required', 'string', 'max:100', Rule::unique('exams', 'name')->ignore($exam)],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return ['end_date.after_or_equal' => 'End date, start date se pehle nahi ho sakti.'];
    }

    public function attributes(): array
    {
        return ['name' => 'exam name'];
    }
}
