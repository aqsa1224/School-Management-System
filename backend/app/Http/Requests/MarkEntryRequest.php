<?php

namespace App\Http\Requests;

use App\Models\Student;
use App\Models\SchoolClass;
use App\Support\TeacherScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MarkEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // roles/permissions Step 10 me lagengi
    }

    public function rules(): array
    {
        $classId = $this->input('school_class_id');

        return [
            'exam_id'         => ['required', 'integer', 'exists:exams,id'],
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'section_id'      => ['nullable', 'integer', Rule::exists('sections', 'id')->where('school_class_id', $classId)],
            // Subject usi class ka hona chahiye
            'subject_id'      => ['required', 'integer', Rule::exists('class_subject', 'subject_id')->where('school_class_id', $classId)],
            'total_marks'     => ['required', 'numeric', 'min:1', 'max:1000', 'decimal:0,2'],
            'marks'           => ['required', 'array'],
            'marks.*'         => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_id.exists' => 'Ye subject is class me assign nahi hai. Pehle Subjects me ja kar subject ko class se jorein.',
            'section_id.exists' => 'Ye section chuni hui class ka nahi hai.',
            'marks.*.numeric'   => 'Marks sirf number hone chahiyen.',
            'marks.*.min'       => 'Marks manfi (negative) nahi ho sakte.',
            'marks.*.decimal'   => 'Marks me ziyada se ziyada 2 decimal places ho sakti hain.',
        ];
    }

    public function attributes(): array
    {
        return ['school_class_id' => 'class', 'section_id' => 'section', 'subject_id' => 'subject', 'total_marks' => 'total marks', 'exam_id' => 'exam'];
    }

    /** Is class/section ke active students jin ke marks is user ko lagane ki ijazat hai */
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

                $user      = $this->user();
                $classId   = (int) $this->input('school_class_id');
                $sectionId = $this->filled('section_id') ? (int) $this->input('section_id') : null;
                $allowed   = TeacherScope::forUser($user);

                // Teacher: sirf apni class/section
                if ($allowed !== null) {
                    if (! array_key_exists($classId, $allowed)) {
                        $validator->errors()->add('school_class_id', 'Ye class aap ko assign nahi hai.');
                        return;
                    }
                    $sections = $allowed[$classId];
                    if ($sections !== null && ($sectionId === null || ! in_array($sectionId, $sections, true))) {
                        $validator->errors()->add('section_id', 'Ye section aap ko assign nahi hai.');
                        return;
                    }
                }

                // Teacher: sirf apna subject
                $subjects = TeacherScope::subjectIds($user, $classId, $sectionId);
                if ($subjects !== null && ! in_array((int) $this->input('subject_id'), $subjects, true)) {
                    $validator->errors()->add('subject_id', 'Is class/section me ye subject aap ko assign nahi hai.');
                    return;
                }

                // Sirf is class/section ke active students
                $eligible = $this->eligibleStudentIds();
                $sent     = array_map('intval', array_keys($this->input('marks', [])));
                if (array_diff($sent, $eligible) !== []) {
                    $validator->errors()->add('marks', 'Kuch students is class/section ke nahi hain ya active nahi hain.');
                    return;
                }

                // Obtained marks total se zyada nahi, aur kam az kam ek student ke marks likhe hon
                $total   = (float) $this->input('total_marks');
                $entered = 0;
                foreach ($this->input('marks', []) as $studentId => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $entered++;
                    if ((float) $value > $total) {
                        $name = Student::find($studentId)?->full_name ?? "#{$studentId}";
                        $validator->errors()->add("marks.{$studentId}", "{$name} ke marks total marks ({$total}) se zyada nahi ho sakte.");
                    }
                }
                if ($entered === 0) {
                    $validator->errors()->add('marks', 'Kam az kam ek student ke marks likhein.');
                }
            },
        ];
    }
}
