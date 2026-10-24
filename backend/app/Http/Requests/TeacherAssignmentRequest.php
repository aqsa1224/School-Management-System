<?php

namespace App\Http\Requests;

use App\Models\TeacherAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $classId = $this->input('school_class_id');

        return [
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            // Section usi class ka hona chahiye
            'section_id' => ['nullable', 'integer',
                Rule::exists('sections', 'id')->where('school_class_id', $classId)],
            // Subject us class ke subjects me hona chahiye
            'subject_id' => ['required', 'integer',
                Rule::exists('class_subject', 'subject_id')->where('school_class_id', $classId)],
        ];
    }

    public function messages(): array
    {
        return [
            'section_id.exists' => 'Ye section is class ka nahi hai.',
            'subject_id.exists' => 'Ye subject is class me assign nahi hai. Pehle Subjects me ja kar subject ko class se jorein.',
        ];
    }

    public function attributes(): array
    {
        return ['school_class_id' => 'class', 'section_id' => 'section', 'subject_id' => 'subject'];
    }

    // Duplicate assignment rokna
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $exists = TeacherAssignment::query()
                    ->where('teacher_id', $this->route('teacher')->id)
                    ->where('school_class_id', $this->input('school_class_id'))
                    ->where('subject_id', $this->input('subject_id'))
                    ->when(
                        $this->filled('section_id'),
                        fn ($q) => $q->where('section_id', $this->input('section_id')),
                        fn ($q) => $q->whereNull('section_id')
                    )
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('subject_id', 'Ye assignment pehle se maujood hai.');
                }
            },
        ];
    }
}
