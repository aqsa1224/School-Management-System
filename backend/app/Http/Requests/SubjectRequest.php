<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $subject = $this->route('subject'); // update par maujooda subject

        return [
            'name'        => ['required', 'string', 'max:100', Rule::unique('subjects', 'name')->ignore($subject)],
            'code'        => ['nullable', 'string', 'max:20', Rule::unique('subjects', 'code')->ignore($subject)],
            'class_ids'   => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:school_classes,id'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'subject name', 'code' => 'subject code', 'class_ids.*' => 'class'];
    }
}
