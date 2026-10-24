<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkEntryRequest;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Support\Grading;
use App\Support\ResultCalculator;
use App\Support\School;
use App\Support\TeacherScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarkController extends Controller
{
    /** Result sheet: exam + class (+ section) chunein */
    public function index(Request $request)
    {
        $allowed = TeacherScope::forUser($request->user());
        $classes = TeacherScope::classes($allowed);
        $exams   = Exam::orderByDesc('start_date')->orderByDesc('id')->get();

        $exam = $exams->firstWhere('id', (int) $request->query('exam_id'));
        [$class, $sectionId] = $this->classAndSection($request, $classes);

        $data = [
            'allowed'   => $allowed,
            'classes'   => $classes,
            'exams'     => $exams,
            'exam'      => $exam,
            'class'     => $class,
            'sectionId' => $sectionId,
            'result'    => null,
            'passMark'  => Grading::passMark(),
        ];

        if ($exam && $class) {
            $students = $this->studentsQuery($allowed, $class->id, $sectionId)
                ->with('section')->orderBy('first_name')->orderBy('last_name')->get();

            $data['result'] = ResultCalculator::forGroup($exam, $students);
        }

        return view('marks.index', $data);
    }

    /** Marks enter karne ka page: exam, class, section, subject chunein, phir poori class */
    public function entry(Request $request)
    {
        $user    = $request->user();
        $allowed = TeacherScope::forUser($user);
        $classes = TeacherScope::classes($allowed);
        $exams   = Exam::orderByDesc('start_date')->orderByDesc('id')->get();

        $exam = $exams->firstWhere('id', (int) $request->query('exam_id'));
        [$class, $sectionId] = $this->classAndSection($request, $classes);

        // Class ke subjects (teacher ko sirf apne wale)
        $subjectsByClass = TeacherScope::subjectsByClass($user);
        $classSubjects = [];
        foreach ($classes as $c) {
            $q = $c->subjects()->orderBy('name');
            if ($subjectsByClass !== null) {
                $q->whereIn('subjects.id', $subjectsByClass[$c->id] ?? []);
            }
            $classSubjects[$c->id] = $q->get(['subjects.id', 'subjects.name']);
        }

        $subject = null;
        $denied  = null;
        if ($class && $request->filled('subject_id')) {
            $subject = $class->subjects()->find((int) $request->query('subject_id'));
            if (! $subject) {
                $denied = 'Ye subject is class me assign nahi hai.';
            } else {
                $allowedSubjects = TeacherScope::subjectIds($user, $class->id, $sectionId);
                if ($allowedSubjects !== null && ! in_array($subject->id, $allowedSubjects, true)) {
                    $denied  = 'Is class/section me ye subject aap ko assign nahi hai.';
                    $subject = null;
                }
            }
        }

        $data = [
            'allowed'       => $allowed,
            'classes'       => $classes,
            'exams'         => $exams,
            'classSubjects' => $classSubjects,
            'exam'          => $exam,
            'class'         => $class,
            'sectionId'     => $sectionId,
            'subject'       => $subject,
            'denied'        => $denied,
            'students'      => null,
        ];

        if ($exam && $class && $subject) {
            $students = $this->studentsQuery($allowed, $class->id, $sectionId)
                ->with('section')->orderBy('first_name')->orderBy('last_name')->get();

            $records = Mark::where('exam_id', $exam->id)->where('subject_id', $subject->id)
                ->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');

            $data = array_merge($data, [
                'students'   => $students,
                'records'    => $records,
                'totalMarks' => $records->first()?->total_marks ?? 100,
            ]);
        }

        return view('marks.entry', $data);
    }

    public function store(MarkEntryRequest $request)
    {
        $eligible = $request->eligibleStudentIds();
        $examId   = (int) $request->input('exam_id');
        $subjectId = (int) $request->input('subject_id');
        $total    = $request->input('total_marks');
        $saved    = 0;

        DB::transaction(function () use ($request, $eligible, $examId, $subjectId, $total, &$saved) {
            foreach ($request->input('marks') as $studentId => $value) {
                // Khali box = marks nahi lagaye, pehle se jo hai wo waisa hi rahega
                if ($value === null || $value === '') {
                    continue;
                }

                Mark::updateOrCreate(
                    ['exam_id' => $examId, 'student_id' => (int) $studentId, 'subject_id' => $subjectId],
                    ['total_marks' => $total, 'obtained_marks' => $value]
                );
                $saved++;
            }
        });

        $subject = Subject::find($subjectId);

        return redirect()->route('marks.index', array_filter([
            'exam_id'         => $examId,
            'school_class_id' => $request->input('school_class_id'),
            'section_id'      => $request->input('section_id'),
        ]))->with('success', "{$subject->name} ke marks save ho gaye ({$saved} students).");
    }

    /** Printable result card (ek student, ek exam) */
    public function card(Request $request, Student $student)
    {
        $allowed = TeacherScope::forUser($request->user());

        // Teacher sirf apni class/section ke students ka card dekh sakta hai
        abort_unless(
            TeacherScope::restrictStudents(Student::query()->whereKey($student->id), $allowed)->exists(),
            404
        );

        $student->load(['schoolClass', 'section']);

        $exam = Exam::find((int) $request->query('exam_id'));
        abort_unless($exam, 404);

        // Position poori class ke active students me (jin ke marks lage hon)
        $classmates = Student::where('status', 'active')->where('school_class_id', $student->school_class_id)->get();
        if (! $classmates->contains('id', $student->id)) {
            $classmates->push($student);
        }

        $result = ResultCalculator::forGroup($exam, $classmates);
        $row = collect($result['rows'])->first(fn ($r) => $r['student']->id === $student->id);
        abort_unless($row && $row['marks']->isNotEmpty(), 404);

        return view('marks.card', [
            'student'    => $student,
            'exam'       => $exam,
            'row'        => $row,
            'ranked'     => collect($result['rows'])->where('percentage', '!==', null)->count(),
            'passMark'   => Grading::passMark(),
            'school'     => School::info(),
        ]);
    }

    // ---------------- helpers ----------------

    private function classAndSection(Request $request, $classes): array
    {
        $class = $classes->firstWhere('id', (int) $request->query('school_class_id'));

        $sectionId = null;
        if ($class && $request->filled('section_id')) {
            $sectionId = $class->sections->firstWhere('id', (int) $request->query('section_id'))?->id;
        }

        return [$class, $sectionId];
    }

    private function studentsQuery(?array $allowed, int $classId, ?int $sectionId)
    {
        $query = Student::query()
            ->where('students.status', 'active')
            ->where('students.school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where('students.section_id', $sectionId));

        return TeacherScope::restrictStudents($query, $allowed);
    }
}
