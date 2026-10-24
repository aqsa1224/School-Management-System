<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\Grading;
use App\Support\Pdf;
use App\Support\ResultCalculator;
use App\Support\School;
use App\Support\TeacherScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reports (Step 11).
 *
 * Har report ek jaisa "report array" banati hai (title, filters, summary, columns, rows, footer).
 * Phir finish() usi array se ye sab deta hai: screen (pagination ke saath), Print page, PDF aur CSV.
 * Is tarah naya report add karna ho to sirf ek chhota method likhna parta hai.
 *
 * Permissions (config/roles.php):
 *   reports.academic  -> Student, Attendance, Result reports (teacher ko sirf apni classes)
 *   reports.fees      -> Fee, Daily collection, Monthly collection reports
 */
class ReportController extends Controller
{
    private const PER_PAGE = 25;

    // Is se kam attendance % wale students laal (red) dikhte hain
    private const LOW_ATTENDANCE = 75;

    // ---------------- Reports ka home page ----------------

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canAny(['reports.academic', 'reports.fees']), 403);

        $groups = [
            'Academic reports' => [
                ['route' => 'reports.students',   'can' => 'reports.academic', 'icon' => 'people',          'title' => 'Student Report',    'text' => 'Class, section, status aur gender ke hisaab se students ki list.'],
                ['route' => 'reports.attendance', 'can' => 'reports.academic', 'icon' => 'calendar-check',  'title' => 'Attendance Report', 'text' => 'Do tareekhon ke beech har student ki present / absent / late / leave aur percentage.'],
                ['route' => 'reports.results',    'can' => 'reports.academic', 'icon' => 'journal-text',    'title' => 'Result Report',     'text' => 'Exam aur class ka subject-wise result, grade, pass/fail aur position.'],
            ],
            'Fee reports' => [
                ['route' => 'reports.fees',    'can' => 'reports.fees', 'icon' => 'cash-coin',      'title' => 'Fee Report',         'text' => 'Vouchers: kitna banaya, kitna wasool hua, kitna baqi hai (paid / unpaid / overdue).'],
                ['route' => 'reports.daily',   'can' => 'reports.fees', 'icon' => 'calendar-day',   'title' => 'Daily Collection',   'text' => 'Kisi ek din me kitni receipts bani aur kitna paisa wasool hua.'],
                ['route' => 'reports.monthly', 'can' => 'reports.fees', 'icon' => 'calendar-month', 'title' => 'Monthly Collection', 'text' => 'Kisi mahine ka din ba din collection (cash / bank / online).'],
            ],
        ];

        $groups = collect($groups)
            ->map(fn ($items) => collect($items)->filter(fn ($i) => $user->can($i['can']))->values())
            ->filter(fn ($items) => $items->isNotEmpty());

        return view('reports.index', compact('groups'));
    }

    // ---------------- 1) Student report ----------------

    public function students(Request $request)
    {
        $allowed = TeacherScope::forUser($request->user());
        $classes = TeacherScope::classes($allowed);
        [$class, $sectionId] = $this->classAndSection($request, $classes);

        $statusLabels = ['active' => 'Active', 'left' => 'Left', 'passed_out' => 'Passed out'];
        $status = $this->text($request, 'status');
        $status = in_array($status, ['active', 'left', 'passed_out', 'all'], true) ? $status : 'active';
        $gender = $this->text($request, 'gender');
        $gender = in_array($gender, ['male', 'female', 'other'], true) ? $gender : '';
        $search = $this->text($request, 'q');

        $students = TeacherScope::restrictStudents(Student::query(), $allowed)
            ->with(['schoolClass', 'section'])
            ->when($class, fn ($q) => $q->where('students.school_class_id', $class->id))
            ->when($sectionId, fn ($q) => $q->where('students.section_id', $sectionId))
            ->when($status !== 'all', fn ($q) => $q->where('students.status', $status))
            ->when($gender !== '', fn ($q) => $q->where('students.gender', $gender))
            // Har lafz naam, admission no, guardian ya phone me se kisi me milna chahiye
            ->when($search !== '', function ($q) use ($search) {
                foreach (preg_split('/\s+/', $search) as $term) {
                    $q->where(fn ($w) => $w
                        ->where('students.first_name', 'like', "%{$term}%")
                        ->orWhere('students.last_name', 'like', "%{$term}%")
                        ->orWhere('students.admission_no', 'like', "%{$term}%")
                        ->orWhere('students.guardian_name', 'like', "%{$term}%")
                        ->orWhere('students.guardian_phone', 'like', "%{$term}%"));
                }
            })
            ->get();

        $students = $this->byClass($students);

        $rows = $students->map(fn ($s) => [
            ['text' => $s->admission_no, 'url' => route('students.show', $s)],
            $s->full_name,
            ucfirst($s->gender),
            $this->classLabel($s),
            $s->guardian_name,
            $s->guardian_phone,
            $s->admission_date?->format('d M Y') ?? '-',
            ['text' => $statusLabels[$s->status] ?? ucfirst($s->status), 'class' => $s->status === 'active' ? 'success' : 'secondary'],
        ])->all();

        return $this->finish($request, [
            'title'    => 'Student Report',
            'route'    => 'reports.students',
            'notice'   => $this->teacherNotice($allowed, $classes),
            'classes'  => $classes,
            'filters'  => [
                ['type' => 'text', 'name' => 'q', 'label' => 'Search', 'value' => $search, 'col' => 'col-12 col-md-3', 'placeholder' => 'Name, admission no, guardian...'],
                ['type' => 'class', 'label' => 'Class', 'value' => $class?->id],
                ['type' => 'section', 'label' => 'Section', 'value' => $sectionId],
                ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'value' => $status, 'all' => null,
                    'options' => ['active' => 'Active', 'left' => 'Left', 'passed_out' => 'Passed out', 'all' => 'All students']],
                ['type' => 'select', 'name' => 'gender', 'label' => 'Gender', 'value' => $gender, 'all' => 'All',
                    'options' => ['male' => 'Male', 'female' => 'Female', 'other' => 'Other']],
            ],
            'subtitle' => $this->join([
                $this->classText($class, $sectionId),
                $status === 'all' ? 'All students' : $statusLabels[$status],
                $gender !== '' ? ucfirst($gender) : null,
                $search !== '' ? "Search: {$search}" : null,
            ]),
            'summary'  => [
                ['label' => 'Total students', 'value' => (string) $students->count()],
                ['label' => 'Male', 'value' => (string) $students->where('gender', 'male')->count()],
                ['label' => 'Female', 'value' => (string) $students->where('gender', 'female')->count()],
            ],
            'columns'  => [
                ['label' => 'Admission no'], ['label' => 'Student'], ['label' => 'Gender'], ['label' => 'Class'],
                ['label' => 'Guardian'], ['label' => 'Guardian phone'], ['label' => 'Admitted'], ['label' => 'Status'],
            ],
            'rows'     => $rows,
            'empty'    => 'Is filter se koi student nahi mila.',
        ]);
    }

    // ---------------- 2) Attendance report ----------------

    public function attendance(Request $request)
    {
        $allowed = TeacherScope::forUser($request->user());
        $classes = TeacherScope::classes($allowed);
        [$class, $sectionId] = $this->classAndSection($request, $classes);

        // Default: is mahine ki 1 tareekh se aaj tak
        $from = $this->date($request, 'from', today()->startOfMonth());
        $to   = $this->date($request, 'to', today());
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $students = $this->byClass(
            $this->activeStudents($allowed, $class, $sectionId)->with(['schoolClass', 'section'])->get()
        );

        $counts = [];
        $grouped = $this->attendanceBase($allowed, $class, $sectionId, $from, $to)
            ->selectRaw('attendances.student_id as sid, attendances.status as st, count(*) as total')
            ->groupBy('attendances.student_id', 'attendances.status')
            ->get();
        foreach ($grouped as $g) {
            $counts[$g->sid][$g->st] = (int) $g->total;
        }

        // Is muddat me kitne din attendance lagi (kisi bhi student ki)
        $markedDays = $this->attendanceBase($allowed, $class, $sectionId, $from, $to)
            ->distinct()->count('attendances.date');

        $tot = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
        $rows = [];
        foreach ($students as $s) {
            $c = $counts[$s->id] ?? [];
            $p = $c['present'] ?? 0;
            $a = $c['absent'] ?? 0;
            $l = $c['late'] ?? 0;
            $v = $c['leave'] ?? 0;
            $all = $p + $a + $l + $v;

            $tot['present'] += $p;
            $tot['absent']  += $a;
            $tot['late']    += $l;
            $tot['leave']   += $v;

            // Percentage = (present + late) / jitne din attendance lagi
            $pct = $all > 0 ? round(($p + $l) / $all * 100, 1) : null;

            $rows[] = [
                ['text' => $s->admission_no, 'url' => route('students.show', $s)],
                $s->full_name,
                $this->classLabel($s),
                (string) $p, (string) $a, (string) $l, (string) $v, (string) $all,
                $pct === null
                    ? '-'
                    : ['text' => $pct . '%', 'csv' => (string) $pct, 'class' => $pct < self::LOW_ATTENDANCE ? 'danger' : 'success'],
            ];
        }

        $grand = array_sum($tot);
        $overall = $grand > 0 ? round(($tot['present'] + $tot['late']) / $grand * 100, 1) : null;

        return $this->finish($request, [
            'title'    => 'Attendance Report',
            'route'    => 'reports.attendance',
            'notice'   => $this->teacherNotice($allowed, $classes),
            'classes'  => $classes,
            'filters'  => [
                ['type' => 'date', 'name' => 'from', 'label' => 'From', 'value' => $from->toDateString(), 'required' => true],
                ['type' => 'date', 'name' => 'to', 'label' => 'To', 'value' => $to->toDateString(), 'required' => true],
                ['type' => 'class', 'label' => 'Class', 'value' => $class?->id, 'col' => 'col-6 col-md-3'],
                ['type' => 'section', 'label' => 'Section', 'value' => $sectionId],
            ],
            'subtitle' => $this->join([
                $from->format('d M Y') . ' to ' . $to->format('d M Y'),
                $this->classText($class, $sectionId) ?? 'All classes',
            ]),
            'summary'  => [
                ['label' => 'Students', 'value' => (string) $students->count()],
                ['label' => 'Days marked', 'value' => (string) $markedDays],
                ['label' => 'Present', 'value' => (string) $tot['present'], 'class' => 'success'],
                ['label' => 'Absent', 'value' => (string) $tot['absent'], 'class' => 'danger'],
                ['label' => 'Late', 'value' => (string) $tot['late']],
                ['label' => 'Leave', 'value' => (string) $tot['leave']],
                ['label' => 'Attendance %', 'value' => $overall === null ? '-' : $overall . '%'],
            ],
            'note'     => 'Attendance % = (Present + Late) / jitne din attendance lagi. ' . self::LOW_ATTENDANCE . '% se kam laal dikhta hai.',
            'columns'  => [
                ['label' => 'Admission no'], ['label' => 'Student'], ['label' => 'Class'],
                ['label' => 'Present', 'align' => 'center'], ['label' => 'Absent', 'align' => 'center'],
                ['label' => 'Late', 'align' => 'center'], ['label' => 'Leave', 'align' => 'center'],
                ['label' => 'Days', 'align' => 'center'], ['label' => '%', 'align' => 'center'],
            ],
            'rows'     => $rows,
            'empty'    => 'Is class me koi active student nahi hai.',
        ]);
    }

    // ---------------- 3) Result report ----------------

    public function results(Request $request)
    {
        $allowed = TeacherScope::forUser($request->user());
        $classes = TeacherScope::classes($allowed);
        $exams   = Exam::orderByDesc('start_date')->orderByDesc('id')->get();

        $exam = $exams->firstWhere('id', $request->integer('exam_id'));
        [$class, $sectionId] = $this->classAndSection($request, $classes);

        $report = [
            'title'   => 'Result Report',
            'route'   => 'reports.results',
            'notice'  => $this->teacherNotice($allowed, $classes),
            'classes' => $classes,
            'filters' => [
                ['type' => 'select', 'name' => 'exam_id', 'label' => 'Exam', 'value' => $exam?->id, 'all' => 'Select exam...', 'required' => true, 'col' => 'col-6 col-md-3',
                    'options' => $exams->pluck('name', 'id')->all()],
                ['type' => 'class', 'label' => 'Class', 'value' => $class?->id, 'all' => 'Select class...', 'required' => true, 'col' => 'col-6 col-md-3'],
                ['type' => 'section', 'label' => 'Section', 'value' => $sectionId],
            ],
            'ready'   => (bool) ($exam && $class),
            'prompt'  => 'Exam aur class chun kar "Show" dabayein.',
            'columns' => [],
            'rows'    => [],
        ];

        if (! $report['ready']) {
            return $this->finish($request, $report);
        }

        $students = $this->activeStudents($allowed, $class, $sectionId)
            ->with('section')->orderBy('students.first_name')->orderBy('students.last_name')->get();

        $result   = ResultCalculator::forGroup($exam, $students);
        $passMark = Grading::passMark();
        $subjects = $result['subjects'];
        $passText = rtrim(rtrim(number_format($passMark, 2), '0'), '.');

        // Position wale pehle (position ke hisaab se), jin ke marks nahi lage wo aakhir me
        $sorted = collect($result['rows'])->sortBy(fn ($r) => sprintf(
            '%d|%06d|%s', $r['position'] === null ? 1 : 0, $r['position'] ?? 0, $r['student']->first_name
        ))->values();

        $columns = [['label' => 'Pos.', 'align' => 'center'], ['label' => 'Admission no'], ['label' => 'Student']];
        foreach ($subjects as $sub) {
            $columns[] = ['label' => $sub->name, 'align' => 'center'];
        }
        array_push($columns,
            ['label' => 'Total', 'align' => 'center'], ['label' => '%', 'align' => 'center'],
            ['label' => 'Grade', 'align' => 'center'], ['label' => 'Result', 'align' => 'center'],
        );

        $rows = [];
        if ($subjects->isNotEmpty()) {
            foreach ($sorted as $row) {
                $s = $row['student'];
                $cells = [
                    $row['position'] === null ? '-' : (string) $row['position'],
                    ['text' => $s->admission_no, 'url' => route('students.show', $s)],
                    $s->full_name,
                ];
                foreach ($subjects as $sub) {
                    $m = $row['marks']->get($sub->id);
                    $cells[] = $m
                        ? ['text' => (float) $m->obtained_marks . '/' . (float) $m->total_marks, 'class' => $m->percentage < $passMark ? 'danger' : '']
                        : '-';
                }
                if ($row['percentage'] === null) {
                    array_push($cells, '-', '-', '-', ['text' => 'No marks', 'class' => 'secondary']);
                } else {
                    array_push($cells,
                        (float) $row['obtained'] . '/' . (float) $row['total'],
                        ['text' => $row['percentage'] . '%', 'csv' => (string) $row['percentage']],
                        $row['grade'],
                        ['text' => $row['result'], 'class' => $row['result'] === 'Pass' ? 'success' : 'danger'],
                    );
                }
                $rows[] = $cells;
            }
        }

        $appeared = $sorted->filter(fn ($r) => $r['percentage'] !== null);
        $passed   = $appeared->where('result', 'Pass')->count();
        $failed   = $appeared->where('result', 'Fail')->count();
        $pcts     = $appeared->pluck('percentage');

        $report['subtitle'] = $this->join([
            $exam->name,
            $this->classText($class, $sectionId),
            "Pass marks: {$passText}% (har subject me)",
        ]);
        $report['summary'] = [
            ['label' => 'Students appeared', 'value' => (string) $appeared->count()],
            ['label' => 'Passed', 'value' => (string) $passed, 'class' => 'success'],
            ['label' => 'Failed', 'value' => (string) $failed, 'class' => 'danger'],
            ['label' => 'Pass %', 'value' => $appeared->count() ? round($passed / $appeared->count() * 100, 1) . '%' : '-'],
            ['label' => 'Highest %', 'value' => $pcts->isNotEmpty() ? $pcts->max() . '%' : '-'],
            ['label' => 'Average %', 'value' => $pcts->isNotEmpty() ? round($pcts->avg(), 1) . '%' : '-'],
        ];
        $report['columns'] = $columns;
        $report['rows']    = $rows;
        $report['empty']   = 'Is exam ke is class me abhi koi marks enter nahi hue.';

        return $this->finish($request, $report);
    }

    // ---------------- 4) Fee report ----------------

    public function fees(Request $request)
    {
        $classes = TeacherScope::classes(null); // fee reports admin/accountant ke liye: sab classes
        [$class, $sectionId] = $this->classAndSection($request, $classes);
        $cur = School::info()['currency'];

        $month  = $this->month($request, 'month');
        $typeId = $request->integer('fee_type_id');
        $status = $this->text($request, 'status');
        $status = in_array($status, ['unpaid', 'partial', 'paid', 'overdue'], true) ? $status : '';
        $types  = FeeType::orderBy('name')->get();
        $type   = $types->firstWhere('id', $typeId);

        $fees = Fee::query()
            ->with(['student.schoolClass', 'student.section', 'feeType'])
            ->withSum('payments', 'amount')
            ->when($month, fn ($q) => $q->whereDate('for_month', $month->toDateString()))
            ->when($class, fn ($q) => $q->whereHas('student', fn ($s) => $s
                ->where('school_class_id', $class->id)
                ->when($sectionId, fn ($x) => $x->where('section_id', $sectionId))))
            ->when($type, fn ($q) => $q->where('fee_type_id', $type->id))
            ->when(in_array($status, ['unpaid', 'partial', 'paid'], true), fn ($q) => $q->where('status', $status))
            // Overdue = abhi poori ada nahi hui aur due date guzar chuki
            ->when($status === 'overdue', fn ($q) => $q->where('status', '!=', 'paid')->whereDate('due_date', '<', today()->toDateString()))
            ->orderBy('id')
            ->get();

        $fees = $fees->sortBy(fn ($f) => sprintf('%05d|%s|%s|%s',
            (int) $f->student->schoolClass->sort_order, $f->student->schoolClass->name,
            $f->student->first_name, $f->voucher_no))->values();

        $badge = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'];
        $sumNet = $sumPaid = $sumBalance = 0.0;
        $rows = [];

        foreach ($fees as $f) {
            $net     = $f->net_amount;
            $paid    = (float) ($f->payments_sum_amount ?? 0);
            $balance = max(0, $net - $paid);
            $overdue = $f->status !== 'paid' && $f->due_date && $f->due_date->lt(today());

            $sumNet     += $net;
            $sumPaid    += $paid;
            $sumBalance += $balance;

            $rows[] = [
                ['text' => $f->voucher_no, 'url' => route('fees.show', $f)],
                "{$f->student->full_name} ({$f->student->admission_no})",
                $this->classLabel($f->student),
                $f->feeType->name,
                $f->for_month?->format('M Y') ?? '-',
                $this->money($net),
                $this->money($paid),
                $this->money($balance, $balance > 0 ? 'danger' : ''),
                $f->due_date?->format('d M Y') ?? '-',
                ['text' => ucfirst($f->status) . ($overdue ? ' (Overdue)' : ''), 'class' => $overdue ? 'danger' : ($badge[$f->status] ?? '')],
            ];
        }

        return $this->finish($request, [
            'title'    => 'Fee Report',
            'route'    => 'reports.fees',
            'classes'  => $classes,
            'filters'  => [
                ['type' => 'month', 'name' => 'month', 'label' => 'Month', 'value' => $month?->format('Y-m')],
                ['type' => 'class', 'label' => 'Class', 'value' => $class?->id],
                ['type' => 'section', 'label' => 'Section', 'value' => $sectionId],
                ['type' => 'select', 'name' => 'fee_type_id', 'label' => 'Fee type', 'value' => $type?->id, 'all' => 'All fee types',
                    'options' => $types->pluck('name', 'id')->all()],
                ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'value' => $status, 'all' => 'All status',
                    'options' => ['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue']],
            ],
            'subtitle' => $this->join([
                $month?->format('F Y') ?? 'All months',
                $this->classText($class, $sectionId) ?? 'All classes',
                $type?->name,
                $status !== '' ? ucfirst($status) : null,
            ]),
            'summary'  => [
                ['label' => 'Vouchers', 'value' => (string) $fees->count()],
                ['label' => 'Total billed', 'value' => $this->amount($cur, $sumNet)],
                ['label' => 'Collected', 'value' => $this->amount($cur, $sumPaid), 'class' => 'success'],
                ['label' => 'Balance (baqi)', 'value' => $this->amount($cur, $sumBalance), 'class' => 'danger'],
                ['label' => 'Collection %', 'value' => $sumNet > 0 ? round(min($sumPaid, $sumNet) / $sumNet * 100, 1) . '%' : '-'],
            ],
            'note'     => "Amounts in {$cur}. Billed = amount minus discount.",
            'columns'  => [
                ['label' => 'Voucher'], ['label' => 'Student'], ['label' => 'Class'], ['label' => 'Fee type'], ['label' => 'Month'],
                ['label' => 'Net', 'align' => 'end'], ['label' => 'Paid', 'align' => 'end'], ['label' => 'Balance', 'align' => 'end'],
                ['label' => 'Due date'], ['label' => 'Status'],
            ],
            'rows'     => $rows,
            'footer'   => ['Total', '', '', '', '', $this->money($sumNet), $this->money($sumPaid), $this->money($sumBalance), '', ''],
            'empty'    => 'Is filter se koi voucher nahi mila.',
        ]);
    }

    // ---------------- 5) Daily collection ----------------

    public function daily(Request $request)
    {
        $cur  = School::info()['currency'];
        $date = $this->date($request, 'date', today());

        $method = $this->text($request, 'method');
        $method = in_array($method, ['cash', 'bank', 'online'], true) ? $method : '';

        $payments = FeePayment::query()
            ->with(['fee.student.schoolClass', 'fee.student.section', 'fee.feeType', 'receivedBy'])
            ->whereDate('payment_date', $date->toDateString())
            ->when($method !== '', fn ($q) => $q->where('method', $method))
            ->orderBy('id')
            ->get();

        $rows = $payments->map(fn ($p) => [
            ['text' => $p->receipt_no, 'url' => route('fees.receipt', [$p->fee_id, $p])],
            ['text' => $p->fee->voucher_no, 'url' => route('fees.show', $p->fee_id)],
            "{$p->fee->student->full_name} ({$p->fee->student->admission_no})",
            $this->classLabel($p->fee->student),
            $p->fee->feeType->name,
            ucfirst($p->method),
            $p->receivedBy?->name ?? '-',
            $this->money($p->amount),
        ])->all();

        return $this->finish($request, [
            'title'    => 'Daily Collection Report',
            'route'    => 'reports.daily',
            'filters'  => [
                ['type' => 'date', 'name' => 'date', 'label' => 'Date', 'value' => $date->toDateString(), 'required' => true],
                ['type' => 'select', 'name' => 'method', 'label' => 'Payment method', 'value' => $method, 'all' => 'All methods',
                    'options' => ['cash' => 'Cash', 'bank' => 'Bank', 'online' => 'Online']],
            ],
            'subtitle' => $this->join([$date->format('l, d M Y'), $method !== '' ? ucfirst($method) . ' only' : null]),
            'summary'  => $this->collectionSummary($payments, $cur),
            'note'     => "Amounts in {$cur}.",
            'columns'  => [
                ['label' => 'Receipt'], ['label' => 'Voucher'], ['label' => 'Student'], ['label' => 'Class'], ['label' => 'Fee type'],
                ['label' => 'Method'], ['label' => 'Received by'], ['label' => 'Amount', 'align' => 'end'],
            ],
            'rows'     => $rows,
            'footer'   => ['Total', '', '', '', '', '', '', $this->money($this->sumPayments($payments))],
            'empty'    => 'Is din koi payment wasool nahi hui.',
        ]);
    }

    // ---------------- 6) Monthly collection ----------------

    public function monthly(Request $request)
    {
        $cur   = School::info()['currency'];
        $month = $this->month($request, 'month') ?? today()->startOfMonth();

        $payments = FeePayment::query()
            ->whereDate('payment_date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('payment_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->get(['id', 'amount', 'payment_date', 'method']);

        $days = $payments->groupBy(fn ($p) => $p->payment_date->toDateString())->sortKeys();

        $rows = [];
        foreach ($days as $date => $list) {
            $rows[] = [
                ['text' => Carbon::parse($date)->format('D, d M Y'), 'url' => route('reports.daily', ['date' => $date])],
                (string) $list->count(),
                $this->money($this->sumPayments($list, 'cash')),
                $this->money($this->sumPayments($list, 'bank')),
                $this->money($this->sumPayments($list, 'online')),
                $this->money($this->sumPayments($list)),
            ];
        }

        return $this->finish($request, [
            'title'    => 'Monthly Collection Report',
            'route'    => 'reports.monthly',
            'filters'  => [
                ['type' => 'month', 'name' => 'month', 'label' => 'Month', 'value' => $month->format('Y-m'), 'required' => true],
            ],
            'subtitle' => $month->format('F Y'),
            'summary'  => $this->collectionSummary($payments, $cur),
            'note'     => "Amounts in {$cur}. Sirf wo din dikhte hain jin me payment wasool hui.",
            'columns'  => [
                ['label' => 'Date'], ['label' => 'Receipts', 'align' => 'center'],
                ['label' => 'Cash', 'align' => 'end'], ['label' => 'Bank', 'align' => 'end'],
                ['label' => 'Online', 'align' => 'end'], ['label' => 'Total', 'align' => 'end'],
            ],
            'rows'     => $rows,
            'footer'   => [
                'Total', (string) $payments->count(),
                $this->money($this->sumPayments($payments, 'cash')), $this->money($this->sumPayments($payments, 'bank')),
                $this->money($this->sumPayments($payments, 'online')), $this->money($this->sumPayments($payments)),
            ],
            'empty'    => 'Is mahine me koi payment wasool nahi hui.',
        ]);
    }

    // =====================================================================
    // Screen / Print / PDF / CSV
    // =====================================================================

    private function finish(Request $request, array $report)
    {
        $report = $this->normalize($report);
        $export = $this->text($request, 'export');

        if ($report['ready'] && in_array($export, ['print', 'pdf', 'csv'], true)) {
            return $this->export($export, $report, $request);
        }

        // Screen par pagination (Print / PDF / CSV me poori list aati hai)
        $page = max(1, (int) $request->query('page', 1));
        $paged = (new LengthAwarePaginator(
            array_slice($report['rows'], ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($report['rows']),
            self::PER_PAGE,
            $page,
            ['path' => $request->url()]
        ))->withQueryString();

        return view('reports.show', [
            'report' => $report,
            'paged'  => $paged,
            'links'  => [
                'print' => $request->fullUrlWithQuery(['export' => 'print', 'page' => null]),
                'pdf'   => $request->fullUrlWithQuery(['export' => 'pdf', 'page' => null]),
                'csv'   => $request->fullUrlWithQuery(['export' => 'csv', 'page' => null]),
            ],
        ]);
    }

    private function export(string $type, array $report, Request $request)
    {
        $file = Str::slug($report['title']) . '-' . now()->format('Ymd-His');

        if ($type === 'csv') {
            return $this->csv($report, $file . '.csv');
        }

        $landscape = count($report['columns']) > 8;
        $backUrl   = $request->fullUrlWithQuery(['export' => null, 'page' => null]);

        $render = fn (bool $pdf) => view('reports.document', [
            'report'    => $report,
            'school'    => School::forDocument($pdf),
            'pdf'       => $pdf,
            'landscape' => $landscape,
            'backUrl'   => $backUrl,
            'pdfUrl'    => $request->fullUrlWithQuery(['export' => 'pdf', 'page' => null]),
        ]);

        if ($type === 'print') {
            return $render(false);
        }

        if (! Pdf::available()) {
            return redirect($backUrl)->with('error',
                'PDF banane wali library install nahi hai. Terminal me ye chalayen: composer require dompdf/dompdf  (phir page dobara kholein). Tab tak "Print" button se bhi PDF save ho sakta hai.');
        }

        return Pdf::download($render(true)->render(), $file . '.pdf', 'A4', $landscape ? 'landscape' : 'portrait');
    }

    private function csv(array $report, string $filename)
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel me sahi khulne ke liye

            $put = fn (array $line) => fputcsv($out, $line, ',', '"', '\\');

            $put([$report['title']]);
            if ($report['subtitle'] !== '') {
                $put([$report['subtitle']]);
            }
            $put([]);
            $put(array_column($report['columns'], 'label'));
            foreach ($report['rows'] as $row) {
                $put(array_column($row, 'csv'));
            }
            if ($report['footer']) {
                $put(array_column($report['footer'], 'csv'));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Report array ko ek jaisi shakal dena + "#" column lagana */
    private function normalize(array $r): array
    {
        $r += [
            'subtitle' => '', 'summary' => [], 'rows' => [], 'footer' => null, 'classes' => collect(),
            'notice' => null, 'prompt' => '', 'note' => '', 'ready' => true,
            'empty' => 'Is filter se koi record nahi mila.',
        ];

        $r['columns'] = array_map(fn ($c) => ['label' => $c['label'], 'align' => $c['align'] ?? 'start'], $r['columns']);
        array_unshift($r['columns'], ['label' => '#', 'align' => 'end']);

        $rows = [];
        foreach (array_values($r['rows']) as $i => $row) {
            $rows[] = array_map(fn ($c) => $this->cell($c), array_merge([$i + 1], $row));
        }
        $r['rows'] = $rows;

        $r['footer'] = $r['footer'] ? array_map(fn ($c) => $this->cell($c), array_merge([''], $r['footer'])) : null;

        return $r;
    }

    /** Har cell: text, rang (class), link (url) aur CSV me jane wali value */
    private function cell(mixed $cell): array
    {
        $c    = is_array($cell) ? $cell : ['text' => $cell];
        $text = (string) ($c['text'] ?? '');

        if (array_key_exists('csv', $c)) {
            $csv = (string) $c['csv'];
        } else {
            // "-" CSV me khali; "=" "+" "-" "@" se shuru hone wala text Excel formula na ban jaye
            $csv = $text === '-' ? '' : (preg_match('/^[=+\-@\t\r]/', $text) ? "'" . $text : $text);
        }

        return ['text' => $text, 'class' => $c['class'] ?? '', 'url' => $c['url'] ?? null, 'csv' => $csv];
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /** Query string se sirf text lena (array ho to khali) */
    private function text(Request $request, string $key): string
    {
        $v = $request->query($key);

        return is_string($v) ? trim($v) : '';
    }

    private function date(Request $request, string $key, Carbon $default): Carbon
    {
        $v = $this->text($request, $key);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            try {
                return Carbon::createFromFormat('!Y-m-d', $v);
            } catch (\Throwable) {
                // ghalat tareekh: neeche default
            }
        }

        return $default->copy()->startOfDay();
    }

    /** "2026-10" => 1 Oct 2026 (ghalat ya khali ho to null) */
    private function month(Request $request, string $key): ?Carbon
    {
        $v = $this->text($request, $key);

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v) ? Carbon::createFromFormat('!Y-m', $v) : null;
    }

    private function classAndSection(Request $request, Collection $classes): array
    {
        $class = $classes->firstWhere('id', $request->integer('school_class_id'));

        $sectionId = null;
        if ($class && $request->filled('section_id')) {
            $sectionId = $class->sections->firstWhere('id', $request->integer('section_id'))?->id;
        }

        return [$class, $sectionId];
    }

    private function teacherNotice(?array $allowed, Collection $classes): ?string
    {
        return ($allowed !== null && $classes->isEmpty())
            ? 'Aap ka account "teacher" hai, lekin aap ko abhi koi class assign nahi hui (ya aap ka teacher record link nahi hai). Admin se kahein ke Teachers me ja kar aap ko class/subject assign kar de.'
            : null;
    }

    private function activeStudents(?array $allowed, ?SchoolClass $class, ?int $sectionId)
    {
        $query = Student::query()
            ->where('students.status', 'active')
            ->when($class, fn ($q) => $q->where('students.school_class_id', $class->id))
            ->when($sectionId, fn ($q) => $q->where('students.section_id', $sectionId));

        return TeacherScope::restrictStudents($query, $allowed);
    }

    private function attendanceBase(?array $allowed, ?SchoolClass $class, ?int $sectionId, Carbon $from, Carbon $to)
    {
        $query = Attendance::query()
            ->join('students', 'students.id', '=', 'attendances.student_id')
            ->where('students.status', 'active')
            ->when($class, fn ($q) => $q->where('students.school_class_id', $class->id))
            ->when($sectionId, fn ($q) => $q->where('students.section_id', $sectionId))
            ->whereDate('attendances.date', '>=', $from->toDateString())
            ->whereDate('attendances.date', '<=', $to->toDateString());

        return TeacherScope::restrictStudents($query, $allowed);
    }

    /** Class ki tarteeb (sort_order), phir section, phir naam */
    private function byClass(Collection $students): Collection
    {
        return $students->sortBy(fn ($s) => sprintf('%05d|%s|%s|%s|%s',
            (int) $s->schoolClass->sort_order, $s->schoolClass->name, $s->section?->name ?? '', $s->first_name, $s->last_name
        ))->values();
    }

    private function classLabel(Student $s): string
    {
        return $s->schoolClass->name . ($s->section ? ' - ' . $s->section->name : '');
    }

    private function classText(?SchoolClass $class, ?int $sectionId): ?string
    {
        if (! $class) {
            return null;
        }

        $section = $sectionId ? $class->sections->firstWhere('id', $sectionId)?->name : null;

        return $class->name . ($section ? " - {$section}" : '');
    }

    /** Khali cheezein hata kar " | " se jorna */
    private function join(array $parts): string
    {
        return implode('  |  ', array_filter($parts, fn ($p) => $p !== null && $p !== ''));
    }

    private function money(float|int|string $value, string $class = ''): array
    {
        return [
            'text'  => number_format((float) $value, 2),
            'csv'   => number_format((float) $value, 2, '.', ''),
            'class' => $class,
        ];
    }

    private function amount(string $currency, float $value): string
    {
        return $currency . ' ' . number_format($value, 2);
    }

    private function sumPayments(Collection $payments, ?string $method = null): float
    {
        return (float) $payments
            ->when($method !== null, fn ($c) => $c->where('method', $method))
            ->sum(fn ($p) => (float) $p->amount);
    }

    private function collectionSummary(Collection $payments, string $cur): array
    {
        return [
            ['label' => 'Total collected', 'value' => $this->amount($cur, $this->sumPayments($payments)), 'class' => 'success'],
            ['label' => 'Receipts', 'value' => (string) $payments->count()],
            ['label' => 'Cash', 'value' => $this->amount($cur, $this->sumPayments($payments, 'cash'))],
            ['label' => 'Bank', 'value' => $this->amount($cur, $this->sumPayments($payments, 'bank'))],
            ['label' => 'Online', 'value' => $this->amount($cur, $this->sumPayments($payments, 'online'))],
        ];
    }
}
