<?php

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);

    $this->class1 = SchoolClass::create(['name' => 'Class 1']);
    $this->secA = $this->class1->sections()->create(['name' => 'A']);
    $this->secB = $this->class1->sections()->create(['name' => 'B']);
    $this->class2 = SchoolClass::create(['name' => 'Class 2']);
    $this->sec2A = $this->class2->sections()->create(['name' => 'A']);

    $this->ali = att_student($this->class1, $this->secA, 'Ali');
    $this->sara = att_student($this->class1, $this->secA, 'Sara');
    $this->bilal = att_student($this->class1, $this->secB, 'Bilal');
    $this->zoya = att_student($this->class2, $this->sec2A, 'Zoya');

    $this->actingAs($this->admin);
});

function att_student(SchoolClass $class, $section, string $name, string $status = 'active'): Student
{
    static $n = 0;
    $n++;

    return Student::create([
        'admission_no' => "ATT-{$n}", 'first_name' => $name, 'gender' => 'male', 'guardian_name' => 'G',
        'guardian_phone' => '1', 'school_class_id' => $class->id, 'section_id' => $section->id, 'status' => $status,
    ]);
}

function att_payload($test, array $rows, array $over = []): array
{
    return array_merge([
        'date' => today()->toDateString(),
        'school_class_id' => $test->class1->id,
        'section_id' => $test->secA->id,
        'attendance' => $rows,
    ], $over);
}

function att_teacher(array $assignments, string $status = 'active'): User
{
    $user = User::factory()->create(['role' => 'teacher']);
    $teacher = Teacher::create(['name' => 'Teach', 'email' => $user->email, 'user_id' => $user->id, 'status' => $status]);
    $subject = Subject::firstOrCreate(['name' => 'Maths']);
    foreach ($assignments as [$class, $section]) {
        $class->subjects()->syncWithoutDetaching([$subject->id]);
        $teacher->assignments()->create([
            'school_class_id' => $class->id, 'section_id' => $section?->id, 'subject_id' => $subject->id,
        ]);
    }

    return $user;
}

test('guests cannot open attendance pages', function () {
    auth()->logout();
    $this->get('/attendances')->assertRedirect('/login');
    $this->get('/attendances/mark')->assertRedirect('/login');
    $this->post('/attendances', [])->assertRedirect('/login');
});

test('overview shows every class with counts for the chosen date', function () {
    Attendance::create(['student_id' => $this->ali->id, 'date' => today(), 'status' => 'present']);
    Attendance::create(['student_id' => $this->sara->id, 'date' => today(), 'status' => 'absent']);

    $this->get('/attendances')->assertOk()->assertSee('Class 1')->assertSee('Class 2')->assertSee('Not taken');
});

test('student-wise list shows marked and not marked students', function () {
    Attendance::create(['student_id' => $this->ali->id, 'date' => today(), 'status' => 'late', 'remarks' => 'Bus late']);

    $this->get("/attendances?school_class_id={$this->class1->id}")->assertOk()
        ->assertSee('Ali')->assertSee('Late')->assertSee('Bus late')
        ->assertSee('Sara')->assertSee('Not marked')
        ->assertDontSee('Zoya');
});

test('the mark page loads only active students of the chosen class and section', function () {
    att_student($this->class1, $this->secA, 'LeftKid', 'left');

    $this->get('/attendances/mark')->assertOk()->assertSee('Select class')->assertDontSee('Ali');
    $this->get("/attendances/mark?school_class_id={$this->class1->id}&section_id={$this->secA->id}")->assertOk()
        ->assertSee('Ali')->assertSee('Sara')->assertDontSee('Bilal')->assertDontSee('LeftKid')->assertDontSee('Zoya');
    $this->get("/attendances/mark?school_class_id={$this->class1->id}")->assertSee('Bilal');
});

test('the mark page refuses a future date', function () {
    $tomorrow = today()->addDay()->toDateString();
    $this->get("/attendances/mark?school_class_id={$this->class1->id}&date={$tomorrow}")->assertOk()
        ->assertSee('Aane wali tareekh');
});

test('a bad date in the url falls back to today instead of crashing', function () {
    $this->get('/attendances?date=not-a-date')->assertOk();
    $this->get('/attendances/mark?date=2026-99-99')->assertOk();
});

test('attendance for a whole class is saved with marked_by and a summary message', function () {
    $this->post('/attendances', att_payload($this, [
        $this->ali->id  => ['status' => 'present'],
        $this->sara->id => ['status' => 'absent', 'remarks' => 'Fever'],
    ]))->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('success');

    $rows = Attendance::orderBy('student_id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->status)->toBe('present')
        ->and($rows[1]->status)->toBe('absent')
        ->and($rows[1]->remarks)->toBe('Fever')
        ->and($rows[0]->marked_by)->toBe($this->admin->id);

    $this->get(session('_previous.url') ?? '/attendances')->assertOk();
});

test('saving again updates the same rows and never creates duplicates', function () {
    $rows = fn ($a, $b) => [$this->ali->id => ['status' => $a], $this->sara->id => ['status' => $b]];

    $this->post('/attendances', att_payload($this, $rows('present', 'present')));
    $this->post('/attendances', att_payload($this, $rows('late', 'leave')));

    expect(Attendance::count())->toBe(2)
        ->and(Attendance::where('student_id', $this->ali->id)->value('status'))->toBe('late')
        ->and(Attendance::where('student_id', $this->sara->id)->value('status'))->toBe('leave');
});

test('a past date can be corrected', function () {
    $past = today()->subDays(3)->toDateString();
    $this->post('/attendances', att_payload($this, [$this->ali->id => ['status' => 'absent']], ['date' => $past]))
        ->assertSessionHasNoErrors();
    $this->post('/attendances', att_payload($this, [$this->ali->id => ['status' => 'present']], ['date' => $past]));

    expect(Attendance::count())->toBe(1)->and(Attendance::first()->status)->toBe('present');
});

test('the mark page prefills saved statuses', function () {
    Attendance::create(['student_id' => $this->ali->id, 'date' => today(), 'status' => 'absent']);

    $html = $this->get("/attendances/mark?school_class_id={$this->class1->id}&section_id={$this->secA->id}")->getContent();
    expect($html)->toContain('Pehle se 1 ki attendance lagi hui hai')
        ->and($html)->toMatch('/id="att-' . $this->ali->id . '-absent"[^>]*checked/');
});

test('validation: future date, bad status, no rows, long remarks', function () {
    $ok = [$this->ali->id => ['status' => 'present']];

    $this->post('/attendances', att_payload($this, $ok, ['date' => today()->addDay()->toDateString()]))->assertSessionHasErrors('date');
    $this->post('/attendances', att_payload($this, $ok, ['date' => '']))->assertSessionHasErrors('date');
    $this->post('/attendances', att_payload($this, [$this->ali->id => ['status' => 'holiday']]))->assertSessionHasErrors('attendance.' . $this->ali->id . '.status');
    $this->post('/attendances', att_payload($this, [$this->ali->id => []]))->assertSessionHasErrors('attendance.' . $this->ali->id . '.status');
    $this->post('/attendances', att_payload($this, []))->assertSessionHasErrors('attendance');
    $this->post('/attendances', att_payload($this, [$this->ali->id => ['status' => 'present', 'remarks' => str_repeat('x', 300)]]))
        ->assertSessionHasErrors('attendance.' . $this->ali->id . '.remarks');
    $this->post('/attendances', att_payload($this, $ok, ['school_class_id' => 9999]))->assertSessionHasErrors('school_class_id');

    expect(Attendance::count())->toBe(0);
});

test('students of another class or section cannot be sneaked in', function () {
    $this->post('/attendances', att_payload($this, [$this->zoya->id => ['status' => 'present']]))
        ->assertSessionHasErrors('attendance');
    $this->post('/attendances', att_payload($this, [$this->bilal->id => ['status' => 'present']]))   // Section B, form A ka hai
        ->assertSessionHasErrors('attendance');
    // section class ka na ho
    $this->post('/attendances', att_payload($this, [$this->ali->id => ['status' => 'present']], ['section_id' => $this->sec2A->id]))
        ->assertSessionHasErrors('section_id');

    expect(Attendance::count())->toBe(0);
});

test('left students cannot be marked', function () {
    $left = att_student($this->class1, $this->secA, 'Gone', 'left');
    $this->post('/attendances', att_payload($this, [$left->id => ['status' => 'present']]))->assertSessionHasErrors('attendance');
    expect(Attendance::count())->toBe(0);
});

test('marking a class without choosing a section covers all its sections', function () {
    $this->post('/attendances', att_payload($this, [
        $this->ali->id => ['status' => 'present'], $this->bilal->id => ['status' => 'absent'],
    ], ['section_id' => null]))->assertSessionHasNoErrors();

    expect(Attendance::count())->toBe(2);
});

// ---------------- teacher restrictions ----------------

test('a teacher sees only the classes assigned to them', function () {
    $teacher = att_teacher([[$this->class1, null]]);
    $this->actingAs($teacher);

    $this->get('/attendances')->assertOk()->assertSee('Class 1')->assertDontSee('Class 2');
    $this->get("/attendances/mark?school_class_id={$this->class1->id}")->assertOk()->assertSee('Ali')->assertSee('Bilal');
    // doosri class ka id url me daalne se bhi kuch nahi aata
    $this->get("/attendances/mark?school_class_id={$this->class2->id}")->assertOk()->assertDontSee('Zoya');
    $this->get("/attendances?school_class_id={$this->class2->id}")->assertOk()->assertDontSee('Zoya');
});

test('a teacher with one section only sees and marks that section', function () {
    $teacher = att_teacher([[$this->class1, $this->secA]]);
    $this->actingAs($teacher);

    $this->get("/attendances/mark?school_class_id={$this->class1->id}")->assertOk()
        ->assertSee('Ali')->assertDontSee('Bilal');
    $this->get("/attendances?school_class_id={$this->class1->id}")->assertSee('Ali')->assertDontSee('Bilal');

    $this->post('/attendances', att_payload($this, [$this->ali->id => ['status' => 'present']]))->assertSessionHasNoErrors();
    // Section B ka student ya section B ki request nahi chalegi
    $this->post('/attendances', att_payload($this, [$this->bilal->id => ['status' => 'present']], ['section_id' => $this->secB->id]))
        ->assertSessionHasErrors('section_id');
    $this->post('/attendances', att_payload($this, [$this->bilal->id => ['status' => 'present']], ['section_id' => null]))
        ->assertSessionHasErrors('section_id');

    expect(Attendance::count())->toBe(1);
});

test('a teacher cannot save attendance for a class that is not theirs', function () {
    $teacher = att_teacher([[$this->class1, null]]);
    $this->actingAs($teacher);

    $this->post('/attendances', att_payload($this, [$this->zoya->id => ['status' => 'present']], [
        'school_class_id' => $this->class2->id, 'section_id' => $this->sec2A->id,
    ]))->assertSessionHasErrors('school_class_id');

    expect(Attendance::count())->toBe(0);
});

test('a teacher account without a teacher record or assignments sees a clear message and no classes', function () {
    $this->actingAs(User::factory()->create(['role' => 'teacher']));
    $this->get('/attendances')->assertOk()->assertSee('koi class assign nahi hui')->assertDontSee('Class 1');

    $this->actingAs(att_teacher([]));
    $this->get('/attendances/mark')->assertOk()->assertSee('koi class assign nahi hui');
});

test('a teacher who has left the school gets no classes', function () {
    $this->actingAs(att_teacher([[$this->class1, null]], 'left'));
    $this->get('/attendances')->assertOk()->assertDontSee('Class 1');
});

test('an admin is not restricted to any class', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->get('/attendances')->assertOk()->assertSee('Class 1')->assertSee('Class 2');
});

test('accountant and receptionist have no attendance access at all', function () {
    foreach (['accountant', 'receptionist'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get('/attendances')->assertForbidden();
        $this->get('/attendances/mark')->assertForbidden();
    }
});

test('overview numbers for a teacher count only their own students', function () {
    $teacher = att_teacher([[$this->class1, $this->secA]]);
    Attendance::create(['student_id' => $this->ali->id, 'date' => today(), 'status' => 'present']);
    Attendance::create(['student_id' => $this->bilal->id, 'date' => today(), 'status' => 'absent']); // Section B

    $this->actingAs($teacher);
    $html = $this->get('/attendances')->getContent();

    // Class 1 row: 2 students (Ali, Sara), 1 present, 0 absent (Bilal ka absent nazar nahi aana chahiye)
    expect($html)->toMatch('/Class 1<\/td>\s*<td class="text-center">2<\/td>\s*<td class="text-center text-success">1<\/td>\s*<td class="text-center text-danger">0<\/td>/');
});

test('the sidebar shows the Attendance link', function () {
    $this->get('/dashboard')->assertOk()->assertSee('Attendance');
});

test('the student profile page counts the saved attendance', function () {
    $this->post('/attendances', att_payload($this, [$this->ali->id => ['status' => 'late'], $this->sara->id => ['status' => 'present']]));
    $this->get("/students/{$this->ali->id}")->assertOk()->assertSee('1 L /');
});

// ---------------- user:role command ----------------

test('user:role makes an account admin', function () {
    $u = User::factory()->create(['role' => 'teacher', 'email' => 'ali@school.test']);

    $this->artisan('user:role ali@school.test')->assertSuccessful();
    expect($u->refresh()->role)->toBe('admin');

    $this->artisan('user:role ali@school.test accountant')->assertSuccessful();
    expect($u->refresh()->role)->toBe('accountant');
});

test('user:role rejects a wrong role or unknown email', function () {
    $u = User::factory()->create(['role' => 'teacher', 'email' => 'ali@school.test']);

    $this->artisan('user:role ali@school.test superman')->assertFailed();
    $this->artisan('user:role nobody@school.test admin')->assertFailed();
    expect($u->refresh()->role)->toBe('teacher');
});
