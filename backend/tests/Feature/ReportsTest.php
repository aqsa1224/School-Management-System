<?php

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Pdf;

function rp_urls(): array
{
    return [
        '/reports', '/reports/students', '/reports/attendance', '/reports/results',
        '/reports/fees', '/reports/daily-collection', '/reports/monthly-collection',
    ];
}

function rp_student(SchoolClass $class, $section, string $name, string $gender = 'male', string $status = 'active'): Student
{
    static $n = 0;
    $n++;

    return Student::create([
        'admission_no' => "RP-{$n}", 'first_name' => $name, 'gender' => $gender, 'guardian_name' => 'G',
        'guardian_phone' => '1', 'school_class_id' => $class->id, 'section_id' => $section->id, 'status' => $status,
    ]);
}

function rp_teacher(SchoolClass $class, $section = null): User
{
    $user = User::factory()->create(['role' => 'teacher']);
    $teacher = Teacher::create(['name' => 'RP Teacher', 'email' => $user->email, 'user_id' => $user->id]);
    $subject = Subject::firstOrCreate(['name' => 'RP Subject']);
    $class->subjects()->syncWithoutDetaching([$subject->id]);
    $teacher->assignments()->create(['school_class_id' => $class->id, 'section_id' => $section?->id, 'subject_id' => $subject->id]);

    return $user;
}

function rp_text($response): string
{
    return preg_replace('/\s+/', ' ', strip_tags($response->getContent()));
}

function rp_pay(Fee $fee, float $amount, string $no, string $date, string $method): FeePayment
{
    $p = FeePayment::create([
        'receipt_no' => $no, 'fee_id' => $fee->id, 'amount' => $amount, 'payment_date' => $date,
        'method' => $method, 'received_by' => User::where('role', 'admin')->value('id'),
    ]);
    $fee->refresh()->refreshStatus();

    return $p;
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);

    Setting::set('school_name', 'ABC Public School');
    Setting::set('currency', 'PKR');

    $this->class1 = SchoolClass::create(['name' => 'Class 1', 'sort_order' => 1]);
    $this->secA = $this->class1->sections()->create(['name' => 'A']);
    $this->secB = $this->class1->sections()->create(['name' => 'B']);
    $this->class2 = SchoolClass::create(['name' => 'Class 2', 'sort_order' => 2]);
    $this->sec2A = $this->class2->sections()->create(['name' => 'A']);

    // Naam jaan boojh kar anokhe rakhe hain taake assertSee galti se kisi aur lafz se na mil jaye
    $this->zaidan  = rp_student($this->class1, $this->secA, 'Zaidan', 'male');
    $this->hibaa   = rp_student($this->class1, $this->secA, 'Hibaa', 'female');
    $this->bilawal = rp_student($this->class1, $this->secB, 'Bilawal', 'male');
    $this->kainat  = rp_student($this->class2, $this->sec2A, 'Kainat', 'female');
    $this->oldman  = rp_student($this->class1, $this->secA, 'Oldman', 'male', 'left');

    $this->type = FeeType::create(['name' => 'Tuition', 'default_amount' => 3000]);

    // fee1: net 2500, 1500 wasool (partial), due future
    $this->fee1 = Fee::create(['voucher_no' => 'VCH-T-1', 'student_id' => $this->zaidan->id, 'fee_type_id' => $this->type->id,
        'amount' => 3000, 'discount' => 500, 'for_month' => '2026-10-01', 'due_date' => '2030-01-01']);
    // fee2: 3000 unpaid, due guzar chuki (overdue)
    $this->fee2 = Fee::create(['voucher_no' => 'VCH-T-2', 'student_id' => $this->hibaa->id, 'fee_type_id' => $this->type->id,
        'amount' => 3000, 'for_month' => '2026-10-01', 'due_date' => '2020-01-01']);
    // fee3: 2000 poori ada (paid), September ki fee
    $this->fee3 = Fee::create(['voucher_no' => 'VCH-T-3', 'student_id' => $this->kainat->id, 'fee_type_id' => $this->type->id,
        'amount' => 2000, 'for_month' => '2026-09-01', 'due_date' => '2026-09-10']);

    rp_pay($this->fee1, 1000, 'RCP-T-1', '2026-10-05', 'cash');
    rp_pay($this->fee3, 2000, 'RCP-T-2', '2026-10-05', 'bank');
    rp_pay($this->fee1, 500, 'RCP-T-3', '2026-10-06', 'online');

    // Result report ke liye: do subjects aur ek exam
    $this->maths = Subject::create(['name' => 'Maths']);
    $this->english = Subject::create(['name' => 'English']);
    $this->class1->subjects()->attach([$this->maths->id, $this->english->id]);
    $this->exam = Exam::create(['name' => 'Mid Term']);

    $this->actingAs($this->admin);
});

// ---------------------------------------------------------------- access

test('guests are sent to login', function () {
    auth()->logout();

    foreach (rp_urls() as $url) {
        $this->get($url)->assertRedirect('/login');
    }
});

test('every role opens only its own reports', function (string $role, array $allowed) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    foreach (rp_urls() as $url) {
        $this->get($url)->assertStatus(in_array($url, $allowed, true) ? 200 : 403);
    }
})->with([
    'admin'        => ['admin', rp_urls()],
    'teacher'      => ['teacher', ['/reports', '/reports/students', '/reports/attendance', '/reports/results']],
    'accountant'   => ['accountant', ['/reports', '/reports/fees', '/reports/daily-collection', '/reports/monthly-collection']],
    'receptionist' => ['receptionist', []],
]);

test('the home page lists only the reports the user may open', function () {
    $this->actingAs(User::factory()->create(['role' => 'accountant']));
    $this->get('/reports')->assertOk()
        ->assertSee('Fee Report')->assertSee('Daily Collection')->assertSee('Monthly Collection')
        ->assertDontSee('Attendance Report')->assertDontSee('Result Report');

    $this->actingAs(User::factory()->create(['role' => 'teacher']));
    $this->get('/reports')->assertOk()
        ->assertSee('Student Report')->assertSee('Attendance Report')->assertSee('Result Report')
        ->assertDontSee('Fee Report');
});

test('the sidebar shows the Reports link only to roles that have a report', function () {
    $this->actingAs(User::factory()->create(['role' => 'teacher']));
    $this->get('/dashboard')->assertSee(route('reports.index'), false);

    $this->actingAs(User::factory()->create(['role' => 'receptionist']));
    $this->get('/dashboard')->assertDontSee(route('reports.index'), false);
});

test('odd query strings never crash a report', function () {
    $this->get('/reports/students?q[]=x&status[]=y&gender[]=z&school_class_id[]=1&export[]=csv')->assertOk();
    $this->get('/reports/attendance?from=garbage&to=2026-13-99')->assertOk();
    $this->get('/reports/fees?month=nope&status=weird&fee_type_id=abc')->assertOk();
    $this->get('/reports/daily-collection?date=31-12-2026&method[]=cash')->assertOk();
    $this->get('/reports/monthly-collection?month=2026-99')->assertOk();
});

// ---------------------------------------------------------------- student report

test('student report shows active students by default and obeys the filters', function () {
    $this->get('/reports/students')->assertOk()
        ->assertSee('Zaidan')->assertSee('Hibaa')->assertSee('Bilawal')->assertSee('Kainat')->assertDontSee('Oldman');

    $this->get('/reports/students?status=left')->assertOk()->assertSee('Oldman')->assertDontSee('Zaidan');
    $this->get('/reports/students?status=all')->assertOk()->assertSee('Oldman')->assertSee('Zaidan');

    $this->get('/reports/students?school_class_id=' . $this->class2->id)->assertOk()
        ->assertSee('Kainat')->assertDontSee('Zaidan');

    $this->get("/reports/students?school_class_id={$this->class1->id}&section_id={$this->secB->id}")->assertOk()
        ->assertSee('Bilawal')->assertDontSee('Zaidan')->assertDontSee('Kainat');

    $this->get('/reports/students?gender=female')->assertOk()
        ->assertSee('Hibaa')->assertSee('Kainat')->assertDontSee('Zaidan');

    $this->get('/reports/students?q=Bilaw')->assertOk()->assertSee('Bilawal')->assertDontSee('Zaidan');
});

test('student report summary counts the people it lists', function () {
    expect(rp_text($this->get('/reports/students')))
        ->toContain('Total students 4')->toContain('Male 2')->toContain('Female 2');
});

test('a teacher sees only the students of the classes assigned to them', function () {
    $this->actingAs(rp_teacher($this->class2));

    $this->get('/reports/students')->assertOk()->assertSee('Kainat')->assertDontSee('Zaidan');
    // Kisi aur class ka filter lagane se bhi kuch nahi khulta
    $this->get('/reports/students?school_class_id=' . $this->class1->id)->assertOk()
        ->assertSee('Kainat')->assertDontSee('Zaidan');
});

test('a teacher without any assignment sees a clear notice and no students', function () {
    $this->actingAs(User::factory()->create(['role' => 'teacher']));

    $this->get('/reports/students')->assertOk()->assertSee('koi class assign nahi hui')->assertDontSee('Zaidan');
});

// ---------------------------------------------------------------- attendance report

function rp_attendance(Student $s, string $from, array $statuses): void
{
    foreach ($statuses as $i => $status) {
        Attendance::create([
            'student_id' => $s->id, 'status' => $status,
            'date' => \Carbon\Carbon::parse($from)->addDays($i)->toDateString(),
        ]);
    }
}

test('attendance report counts each student inside the chosen dates only', function () {
    // Zaidan: 7 present, 2 absent, 1 late = 80%.  Hibaa: 5 present, 5 absent = 50%
    rp_attendance($this->zaidan, '2026-09-01', [...array_fill(0, 7, 'present'), 'absent', 'absent', 'late']);
    rp_attendance($this->hibaa, '2026-09-01', [...array_fill(0, 5, 'present'), ...array_fill(0, 5, 'absent')]);
    // Muddat ke bahar: ginti me nahi aani chahiye
    Attendance::create(['student_id' => $this->zaidan->id, 'status' => 'absent', 'date' => '2026-10-05']);

    $url = '/reports/attendance?from=2026-09-01&to=2026-09-30';

    $text = rp_text($this->get($url)->assertOk());
    expect($text)->toContain('Days marked 10')->toContain('Present 12')->toContain('Absent 7')
        ->toContain('Late 1')->toContain('Attendance % 65%');

    $csv = $this->get($url . '&export=csv')->streamedContent();
    expect($csv)->toContain('"Class 1 - A",7,2,1,0,10,80')->toContain('"Class 1 - A",5,5,0,0,10,50');
});

test('attendance report can be limited to a class and a section', function () {
    $this->get("/reports/attendance?school_class_id={$this->class2->id}")->assertOk()
        ->assertSee('Kainat')->assertDontSee('Zaidan');

    $this->get("/reports/attendance?school_class_id={$this->class1->id}&section_id={$this->secB->id}")->assertOk()
        ->assertSee('Bilawal')->assertDontSee('Hibaa');
});

test('a teacher sees attendance only of their own class', function () {
    $this->actingAs(rp_teacher($this->class2));

    $this->get('/reports/attendance')->assertOk()->assertSee('Kainat')->assertDontSee('Zaidan');
});

// ---------------------------------------------------------------- result report

function rp_marks($t, Student $s, int $maths, int $english): void
{
    foreach ([[$t->maths, $maths], [$t->english, $english]] as [$subject, $got]) {
        Mark::create(['exam_id' => $t->exam->id, 'student_id' => $s->id, 'subject_id' => $subject->id,
            'total_marks' => 100, 'obtained_marks' => $got]);
    }
}

test('result report needs an exam and a class first', function () {
    $this->get('/reports/results')->assertOk()->assertSee('Exam aur class chun kar');
    $this->get('/reports/results?export=csv')->assertOk()->assertSee('Exam aur class chun kar'); // export bhi tab tak nahi
});

test('result report gives grade, pass or fail and position', function () {
    rp_marks($this, $this->zaidan, maths: 90, english: 80); // 85% => A+, Pass
    rp_marks($this, $this->hibaa, maths: 30, english: 70);  // 50% => C, Maths me fail

    $url = "/reports/results?exam_id={$this->exam->id}&school_class_id={$this->class1->id}";

    $text = rp_text($this->get($url)->assertOk());
    expect($text)->toContain('Mid Term')->toContain('Students appeared 2')->toContain('Passed 1')->toContain('Failed 1')
        ->toContain('Highest % 85%')->toContain('Average % 67.5%');

    $csv = $this->get($url . '&export=csv')->streamedContent();
    expect($csv)->toContain('English,Maths,Total,%,Grade,Result')
        ->toContain('Zaidan,80/100,90/100,170/200,85,A+,Pass')
        ->toContain('Hibaa,70/100,30/100,100/200,50,C,Fail')
        ->toContain('No marks')
        ->not->toContain('Oldman');

    // Position wale pehle, jin ke marks nahi lage wo aakhir me
    expect(strpos($csv, 'Zaidan'))->toBeLessThan(strpos($csv, 'Hibaa'))
        ->and(strpos($csv, 'Hibaa'))->toBeLessThan(strpos($csv, 'Bilawal'));
});

test('a teacher cannot build a result report for a class that is not theirs', function () {
    rp_marks($this, $this->zaidan, maths: 90, english: 80);
    $this->actingAs(rp_teacher($this->class2));

    $this->get("/reports/results?exam_id={$this->exam->id}&school_class_id={$this->class1->id}")
        ->assertOk()->assertDontSee('Zaidan')->assertSee('Exam aur class chun kar');
});

// ---------------------------------------------------------------- fee report

test('fee report adds up billed, collected and balance', function () {
    $text = rp_text($this->get('/reports/fees')->assertOk());

    expect($text)->toContain('Vouchers 3')->toContain('PKR 7,500.00')->toContain('PKR 3,500.00')
        ->toContain('PKR 4,000.00')->toContain('Collection % 46.7%');

    $csv = $this->get('/reports/fees?export=csv')->streamedContent();
    expect($csv)->toContain(',2500.00,1500.00,1000.00,')->toContain('7500.00,3500.00,4000.00');
});

test('fee report filters by status, overdue, class, month and fee type', function () {
    $this->get('/reports/fees?status=paid')->assertOk()->assertSee('VCH-T-3')->assertDontSee('VCH-T-1')->assertDontSee('VCH-T-2');
    $this->get('/reports/fees?status=partial')->assertOk()->assertSee('VCH-T-1')->assertDontSee('VCH-T-3');
    $this->get('/reports/fees?status=overdue')->assertOk()->assertSee('VCH-T-2')->assertDontSee('VCH-T-1')->assertDontSee('VCH-T-3');

    $this->get('/reports/fees?school_class_id=' . $this->class2->id)->assertOk()
        ->assertSee('VCH-T-3')->assertDontSee('VCH-T-1');

    $this->get('/reports/fees?month=2026-10')->assertOk()->assertSee('VCH-T-1')->assertSee('VCH-T-2')->assertDontSee('VCH-T-3');
    $this->get('/reports/fees?month=2026-09')->assertOk()->assertSee('VCH-T-3')->assertDontSee('VCH-T-1');

    $other = FeeType::create(['name' => 'Exam Fee', 'default_amount' => 500]);
    $this->get('/reports/fees?fee_type_id=' . $other->id)->assertOk()->assertDontSee('VCH-T-1')->assertSee('Is filter se koi voucher nahi mila');
});

// ---------------------------------------------------------------- collection reports

test('daily collection totals one day and splits it by method', function () {
    $text = rp_text($this->get('/reports/daily-collection?date=2026-10-05')->assertOk());

    expect($text)->toContain('Total collected PKR 3,000.00')->toContain('Receipts 2')
        ->toContain('Cash PKR 1,000.00')->toContain('Bank PKR 2,000.00')->toContain('Online PKR 0.00')
        ->toContain('RCP-T-1')->toContain('RCP-T-2')->not->toContain('RCP-T-3');

    $this->get('/reports/daily-collection?date=2026-10-05&method=bank')->assertOk()
        ->assertSee('RCP-T-2')->assertDontSee('RCP-T-1');

    $this->get('/reports/daily-collection?date=2026-10-07')->assertOk()->assertSee('Is din koi payment wasool nahi hui');
});

test('monthly collection groups the month day by day', function () {
    $url = '/reports/monthly-collection?month=2026-10';

    $text = rp_text($this->get($url)->assertOk());
    expect($text)->toContain('Total collected PKR 3,500.00')->toContain('Receipts 3')
        ->toContain('05 Oct 2026')->toContain('06 Oct 2026');

    $csv = $this->get($url . '&export=csv')->streamedContent();
    expect($csv)->toContain('2,1000.00,2000.00,0.00,3000.00')->toContain('1,0.00,0.00,500.00,500.00');

    $this->get('/reports/monthly-collection?month=2026-09')->assertOk()->assertSee('Is mahine me koi payment wasool nahi hui');
});

// ---------------------------------------------------------------- print / pdf / csv

test('print view has the school header, a print button and the full list', function () {
    $this->get('/reports/students?export=print')->assertOk()
        ->assertSee('ABC Public School')->assertSee('Student Report')->assertSee('window.print()', false)
        ->assertSee('Zaidan')->assertSee('Printed on');
});

test('pdf export downloads a real pdf', function () {
    $r = $this->get('/reports/fees?export=pdf')->assertOk();

    expect($r->headers->get('content-type'))->toContain('application/pdf')
        ->and($r->headers->get('content-disposition'))->toContain('fee-report')
        ->and($r->getContent())->toStartWith('%PDF');
})->skip(fn () => ! Pdf::available(), 'dompdf install nahi hai');

test('csv export has a BOM, the right headers and spreadsheet-safe text', function () {
    rp_student($this->class2, $this->sec2A, '=SUM(1)');

    $r = $this->get('/reports/students?export=csv')->assertOk();
    $csv = $r->streamedContent();

    expect($r->headers->get('content-type'))->toContain('text/csv')
        ->and($r->headers->get('content-disposition'))->toContain('student-report')
        // fputcsv har us text ko inverted commas me likhta hai jis me space ho (jaise "Student Report"): ye sahi CSV hai
        ->and($csv)->toStartWith("\xEF\xBB\xBF" . '"Student Report"')
        ->and($csv)->toContain('Admission no')->toContain('Zaidan')
        // "=" se shuru hone wala naam Excel me formula na ban jaye
        ->and($csv)->toContain("'=SUM(1)");
});

test('the screen is paged but print and csv carry every row', function () {
    foreach (range(1, 30) as $i) {
        rp_student($this->class2, $this->sec2A, sprintf('Pagey%02d', $i));
    }
    $base = '/reports/students?school_class_id=' . $this->class2->id;

    // 31 students in class 2: pehle page par 25
    $this->get($base)->assertOk()->assertSee('Pagey24')->assertDontSee('Pagey25');
    $this->get($base . '&page=2')->assertOk()->assertSee('Pagey25')->assertSee('Pagey30')->assertDontSee('Pagey24');

    $this->get($base . '&export=print')->assertOk()->assertSee('Pagey01')->assertSee('Pagey30');
    expect($this->get($base . '&export=csv')->streamedContent())->toContain('Pagey01')->toContain('Pagey30');
});
