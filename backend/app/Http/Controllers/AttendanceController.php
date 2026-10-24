<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRequest;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\TeacherScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /** Kisi ek din ki attendance: class chunein to student-wise, warna class-wise summary */
    public function index(Request $request)
    {
        $allowed = TeacherScope::forUser($request->user());
        $classes = TeacherScope::classes($allowed);

        [$date, $class, $sectionId] = $this->filters($request, $classes);

        $data = [
            'allowed'   => $allowed,
            'classes'   => $classes,
            'date'      => $date,
            'classId'   => $class?->id,
            'sectionId' => $sectionId,
            'students'  => null,
            'overview'  => null,
        ];

        if ($class) {
            $students = $this->studentsQuery($allowed, $class->id, $sectionId)
                ->with('section')->orderBy('first_name')->orderBy('last_name')->get();

            $records = Attendance::with('markedBy')
                ->whereIn('student_id', $students->pluck('id'))
                ->whereDate('date', $date)->get()->keyBy('student_id');

            $summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
            foreach ($records as $r) {
                $summary[$r->status]++;
            }
            $summary['not_marked'] = $students->count() - $records->count();

            // NOTE: array_merge, kyunke += pehle se maujood keys (students => null) ko overwrite nahi karta
            $data = array_merge($data, ['students' => $students, 'records' => $records, 'summary' => $summary]);
        } else {
            $data['overview'] = $this->overview($allowed, $classes, $date);
        }

        return view('attendances.index', $data);
    }

    /** Attendance lagane ka page: class/section/date chunein, phir poori class ek saath */
    public function mark(Request $request)
    {
        $allowed = TeacherScope::forUser($request->user());
        $classes = TeacherScope::classes($allowed);

        [$date, $class, $sectionId] = $this->filters($request, $classes, defaultDateToday: true);

        $data = [
            'allowed'   => $allowed,
            'classes'   => $classes,
            'date'      => $date,
            'classId'   => $class?->id,
            'sectionId' => $sectionId,
            'students'  => null,
            'future'    => Carbon::parse($date)->isFuture(),
        ];

        if ($class) {
            $students = $this->studentsQuery($allowed, $class->id, $sectionId)
                ->with('section')->orderBy('first_name')->orderBy('last_name')->get();

            $records = Attendance::whereIn('student_id', $students->pluck('id'))
                ->whereDate('date', $date)->get()->keyBy('student_id');

            $data = array_merge($data, ['students' => $students, 'records' => $records]);
        }

        return view('attendances.mark', $data);
    }

    public function store(AttendanceRequest $request)
    {
        $date     = Carbon::parse($request->input('date'))->toDateString();
        $eligible = $request->eligibleStudentIds();

        $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];

        DB::transaction(function () use ($request, $date, $eligible, &$counts) {
            $existing = Attendance::whereIn('student_id', $eligible)->whereDate('date', $date)->get()->keyBy('student_id');

            foreach ($request->input('attendance') as $studentId => $row) {
                $studentId = (int) $studentId;
                $values = [
                    'status'    => $row['status'],
                    'remarks'   => ($row['remarks'] ?? '') !== '' ? $row['remarks'] : null,
                    'marked_by' => $request->user()->id,
                ];

                // Pehle se hai to update (duplicate nahi banta), warna nayi entry
                if ($existing->has($studentId)) {
                    $existing[$studentId]->update($values);
                } else {
                    Attendance::create($values + ['student_id' => $studentId, 'date' => $date]);
                }
                $counts[$row['status']]++;
            }
        });

        $total = array_sum($counts);

        return redirect()->route('attendances.index', array_filter([
            'date'            => $date,
            'school_class_id' => $request->input('school_class_id'),
            'section_id'      => $request->input('section_id'),
        ]))->with('success', "Attendance save ho gayi ({$total} students): {$counts['present']} present, {$counts['absent']} absent, {$counts['late']} late, {$counts['leave']} leave.");
    }

    // ---------------- helpers ----------------

    /** @return array{0:string,1:?SchoolClass,2:?int} */
    private function filters(Request $request, $classes, bool $defaultDateToday = false): array
    {
        // Ghalat/khali tareekh ho to aaj ki
        try {
            $date = Carbon::parse($request->query('date', today()->toDateString()))->toDateString();
        } catch (\Throwable) {
            $date = today()->toDateString();
        }

        // Sirf wohi class jo is user ko allowed hai
        $class = $classes->firstWhere('id', (int) $request->query('school_class_id'));

        $sectionId = null;
        if ($class && $request->filled('section_id')) {
            $sectionId = $class->sections->firstWhere('id', (int) $request->query('section_id'))?->id;
        }

        return [$date, $class, $sectionId];
    }

    private function studentsQuery(?array $allowed, int $classId, ?int $sectionId)
    {
        $query = Student::query()
            ->where('students.status', 'active')
            ->where('students.school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where('students.section_id', $sectionId));

        return TeacherScope::restrictStudents($query, $allowed);
    }

    /** Har class ke liye: kitne students, kitni attendance lagi, present/absent... */
    private function overview(?array $allowed, $classes, string $date): array
    {
        $totals = TeacherScope::restrictStudents(Student::query()->where('students.status', 'active'), $allowed)
            ->select('students.school_class_id', DB::raw('count(*) as total'))
            ->groupBy('students.school_class_id')
            ->pluck('total', 'school_class_id');

        $rows = TeacherScope::restrictStudents(
            Attendance::query()
                ->join('students', 'students.id', '=', 'attendances.student_id')
                ->where('students.status', 'active')
                ->whereDate('attendances.date', $date),
            $allowed
        )
            ->selectRaw('students.school_class_id as class_id, attendances.status as st, count(*) as total')
            ->groupBy('students.school_class_id', 'attendances.status')
            ->get();

        $byClass = [];
        foreach ($rows as $r) {
            $byClass[$r->class_id][$r->st] = (int) $r->total;
        }

        return $classes->map(function ($class) use ($totals, $byClass) {
            $c = $byClass[$class->id] ?? [];
            $students = (int) ($totals[$class->id] ?? 0);
            $marked = array_sum($c);

            return [
                'class'      => $class,
                'students'   => $students,
                'marked'     => $marked,
                'present'    => $c['present'] ?? 0,
                'absent'     => $c['absent'] ?? 0,
                'late'       => $c['late'] ?? 0,
                'leave'      => $c['leave'] ?? 0,
                'not_marked' => max(0, $students - $marked),
            ];
        })->all();
    }
}
