<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Support\TeacherScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $search  = trim((string) $request->query('q', ''));
        $classId = $request->query('class_id', '');
        $secId   = $request->query('section_id', '');
        $status  = $request->query('status', '');
        $gender  = $request->query('gender', '');

        // Teacher ko sirf apni classes/sections ke students (baqi roles ko sab)
        $allowed = TeacherScope::forUser($request->user());

        $students = Student::query()
            ->with(['schoolClass', 'section'])
            ->when($allowed !== null, fn ($q) => TeacherScope::restrictStudents($q, $allowed))
            // Har lafz naam, admission no, guardian ya phone me se kisi me milna chahiye
            ->when($search !== '', function ($q) use ($search) {
                foreach (preg_split('/\s+/', $search) as $term) {
                    $q->where(fn ($w) => $w
                        ->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('admission_no', 'like', "%{$term}%")
                        ->orWhere('guardian_name', 'like', "%{$term}%")
                        ->orWhere('guardian_phone', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%"));
                }
            })
            ->when($classId !== '', fn ($q) => $q->where('school_class_id', $classId))
            ->when($secId !== '', fn ($q) => $q->where('section_id', $secId))
            ->when(in_array($status, ['active', 'left', 'passed_out'], true), fn ($q) => $q->where('status', $status))
            ->when(in_array($gender, ['male', 'female', 'other'], true), fn ($q) => $q->where('gender', $gender))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(10)
            ->withQueryString();

        $classes = TeacherScope::classes($allowed);

        return view('students.index', compact('students', 'classes', 'search', 'classId', 'secId', 'status', 'gender'));
    }

    public function create()
    {
        return view('students.create', [
            'classes'     => $this->classesWithSections(),
            'admissionNo' => Student::nextAdmissionNo(),
        ]);
    }

    public function store(StudentRequest $request)
    {
        $data = $this->prepare($request);

        $student = Student::create($data);

        return redirect()->route('students.show', $student)
            ->with('success', "Student \"{$student->full_name}\" added.");
    }

    public function show(Request $request, Student $student)
    {
        $user = $request->user();

        // Teacher sirf apni class/section ke student ka profile dekh sakta hai
        abort_unless(
            TeacherScope::restrictStudents(Student::query()->whereKey($student->id), TeacherScope::forUser($user))->exists(),
            404
        );

        $student->load(['schoolClass', 'section']);

        // Sirf wahi data nikalte hain jo is role ko dekhne ki ijazat hai
        $attendance = $user->can('attendance.manage')
            ? $student->attendances()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        $feeSummary = ['total' => 0, 'paid' => 0, 'balance' => 0];
        $studentFees = collect();
        if ($user->can('fees.view')) {
            $fees = $student->fees()->with('payments')->get();
            $feeSummary = [
                'total' => $fees->sum(fn ($f) => $f->net_amount),
                'paid'  => $fees->sum(fn ($f) => (float) $f->payments->sum('amount')),
            ];
            $feeSummary['balance'] = max(0, $feeSummary['total'] - $feeSummary['paid']);

            // Aakhri 10 vouchers (profile par dikhane ke liye)
            $studentFees = $student->fees()->with(['feeType', 'payments'])->orderByDesc('id')->limit(10)->get();
        }

        $marksCount = 0;
        $examsTaken = collect();
        if ($user->can('marks.manage')) {
            $marksCount = $student->marks()->count();
            // Jin exams me is student ke marks lage hain (result card ke links ke liye)
            $examsTaken = \App\Models\Exam::whereIn('id', $student->marks()->select('exam_id'))
                ->orderByDesc('start_date')->orderByDesc('id')->get();
        }

        return view('students.show', compact('student', 'attendance', 'feeSummary', 'marksCount', 'examsTaken', 'studentFees'));
    }

    public function edit(Student $student)
    {
        return view('students.edit', [
            'student' => $student,
            'classes' => $this->classesWithSections(),
        ]);
    }

    public function update(StudentRequest $request, Student $student)
    {
        $data = $this->prepare($request, $student);

        $student->update($data);

        return redirect()->route('students.show', $student)
            ->with('success', 'Student updated successfully.');
    }

    public function destroy(Student $student)
    {
        // Attendance / marks / fees wale student ko delete karne se purana record (khaaskar fees) ud jata,
        // isliye rok diya. Aise student ko status "Left" kar dein.
        if ($student->attendances()->exists() || $student->marks()->exists() || $student->fees()->exists()) {
            return redirect()->route('students.show', $student)
                ->with('error', 'Is student ka attendance/marks/fees record maujood hai, isliye delete nahi ho sakta. Record mehfooz rakhne ke liye status "Left" kar dein.');
        }

        $name = $student->full_name;
        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }
        $student->delete();

        return redirect()->route('students.index')->with('success', "Student \"{$name}\" deleted.");
    }

    // ---------- helpers ----------

    private function classesWithSections()
    {
        return SchoolClass::with('sections')->orderBy('sort_order')->orderBy('name')->get();
    }

    // Validated data tayyar karta hai + photo upload/replace/remove sambhalta hai
    private function prepare(StudentRequest $request, ?Student $student = null): array
    {
        $data = collect($request->validated())->except(['photo', 'remove_photo'])->all();

        // Class me sections na hon to section khali
        if (empty($data['section_id'])) {
            $data['section_id'] = null;
        }

        if ($request->hasFile('photo')) {
            if ($student?->photo) {
                Storage::disk('public')->delete($student->photo);
            }
            $data['photo'] = $request->file('photo')->store('students', 'public');
        } elseif ($student && $request->boolean('remove_photo')) {
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
            $data['photo'] = null;
        }

        return $data;
    }
}
