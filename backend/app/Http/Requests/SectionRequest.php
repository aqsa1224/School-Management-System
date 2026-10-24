<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $class   = $this->route('schoolClass');
        $section = $this->route('section'); // update par maujooda section

        return [
            // Same class me same section name dobara nahi (doosri class me ho sakta hai)
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sections', 'name')
                    ->where('school_class_id', $class->id)
                    ->ignore($section),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'section name'];
    }
}
