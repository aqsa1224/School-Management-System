<?php

namespace App\Http\Requests;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $student = $this->route('student');          // update par maujooda student, create par null
        $classId = $this->input('school_class_id');

        return [
            'admission_no'    => ['required', 'string', 'max:50', Rule::unique('students', 'admission_no')->ignore($student)],
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['nullable', 'string', 'max:100'],
            'gender'          => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth'   => ['nullable', 'date', 'before:today'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:255'],
            'address'         => ['nullable', 'string', 'max:500'],
            'guardian_name'   => ['required', 'string', 'max:100'],
            'guardian_phone'  => ['required', 'string', 'max:30'],
            'photo'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo'    => ['nullable', 'boolean'],
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            // Agar class me sections hain to section chunna zaroori, aur wo usi class ka ho
            'section_id'      => [
                Rule::requiredIf(fn () => $classId && Section::where('school_class_id', $classId)->exists()),
                'nullable', 'integer',
                Rule::exists('sections', 'id')->where('school_class_id', $classId),
            ],
            'admission_date'  => ['nullable', 'date'],
            'status'          => ['required', Rule::in(['active', 'left', 'passed_out'])],
        ];
    }

    public function messages(): array
    {
        return [
            'section_id.exists'   => 'Ye section chunni hui class ka nahi hai.',
            'section_id.required' => 'Is class me sections hain, isliye section chunna zaroori hai.',
            'photo.max'           => 'Photo 2 MB se chhoti honi chahiye.',
            'date_of_birth.before'=> 'Date of birth aaj se pehle ki honi chahiye.',
        ];
    }

    public function attributes(): array
    {
        return [
            'school_class_id' => 'class',
            'section_id'      => 'section',
            'admission_no'    => 'admission number',
            'guardian_name'   => 'guardian name',
            'guardian_phone'  => 'guardian phone',
        ];
    }
}
