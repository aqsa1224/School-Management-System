<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use App\Models\Section;
use App\Models\Student;
use App\Support\TeacherScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        return [
            'date'                   => ['required', 'date', 'before_or_equal:today'],
            'school_class_id'        => ['required', 'integer', 'exists:school_classes,id'],
            'section_id'             => ['nullable', 'integer',
                                          Rule::exists('sections', 'id')->where('school_class_id', $this->input('school_class_id'))],
            'attendance'             => ['required', 'array', 'min:1'],
            'attendance.*.status'    => ['required', Rule::in(Attendance::STATUSES)],
            'attendance.*.remarks'   => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal'      => 'Aane wali tareekh ki attendance nahi lagayi ja sakti.',
            'attendance.required'       => 'Koi student nahi mila jis ki attendance lagayi ja sake.',
            'attendance.*.status.in'    => 'Attendance ka status galat hai.',
            'attendance.*.status.required' => 'Har student ka status chunna zaroori hai.',
        ];
    }

    public function attributes(): array
    {
        return ['school_class_id' => 'class', 'section_id' => 'section'];
    }

    /**
     * Ye students is user ke liye, is class/section me, active hon (teacher apni classes se bahar kuch nahi bhej sakta).
     */
    public function eligibleStudentIds(): array
    {
        $query = Student::query()
            ->where('students.status', 'active')
            ->where('students.school_class_id', $this->input('school_class_id'))
            ->when($this->filled('section_id'), fn ($q) => $q->where('students.section_id', $this->input('section_id')));

        return TeacherScope::restrictStudents($query, TeacherScope::forUser($this->user()))
            ->pluck('students.id')->all();
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $allowed = TeacherScope::forUser($this->user());
                $classId = (int) $this->input('school_class_id');

                // Teacher ki apni class/section hi
                if ($allowed !== null) {
                    if (! array_key_exists($classId, $allowed)) {
                        $validator->errors()->add('school_class_id', 'Ye class aap ko assign nahi hai.');
                        return;
                    }
                    $sections = $allowed[$classId];
                    if ($sections !== null && (! $this->filled('section_id') || ! in_array((int) $this->input('section_id'), $sections, true))) {
                        $validator->errors()->add('section_id', 'Ye section aap ko assign nahi hai.');
                        return;
                    }
                }

                // Sirf is class/section ke active students ki attendance
                $eligible = $this->eligibleStudentIds();
                $sent = array_map('intval', array_keys($this->input('attendance', [])));

                if (array_diff($sent, $eligible) !== []) {
                    $validator->errors()->add('attendance', 'Kuch students is class/section ke nahi hain ya active nahi hain.');
                }
            },
        ];
    }
}
