<?php

use App\Models\Exam;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Grading;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);

    $this->class1 = SchoolClass::create(['name' => 'Class 1']);
    $this->secA = $this->class1->sections()->create(['name' => 'A']);
    $this->secB = $this->class1->sections()->create(['name' => 'B']);
    $this->class2 = SchoolClass::create(['name' => 'Class 2']);
    $this->sec2A = $this->class2->sections()->create(['name' => 'A']);

    $this->maths = Subject::create(['name' => 'Maths']);
    $this->english = Subject::create(['name' => 'English']);
    $this->class1->subjects()->attach([$this->maths->id, $this->english->id]);
    $this->class2->subjects()->attach([$this->maths->id]);

    $this->exam = Exam::create(['name' => 'Mid Term']);

    $this->ali = mk_student($this->class1, $this->secA, 'Ali');
    $this->sara = mk_student($this->class1, $this->secA, 'Sara');
    $this->bilal = mk_student($this->class1, $this->secB, 'Bilal');
    $this->zoya = mk_student($this->class2, $this->sec2A, 'Zoya');

    $this->actingAs($this->admin);
});

function mk_student(SchoolClass $class, $section, string $name, string $status = 'active'): Student
{
    static $n = 0;
    $n++;

    return Student::create([
        'admission_no' => "MK-{$n}", 'first_name' => $name, 'gender' => 'male', 'guardian_name' => 'G',
        'guardian_phone' => '1', 'school_class_id' => $class->id, 'section_id' => $section->id, 'status' => $status,
    ]);
}

function mk_payload($t, array $marks, array $over = []): array
{
    return array_merge([
        'exam_id' => $t->exam->id, 'school_class_id' => $t->class1->id, 'section_id' => $t->secA->id,
        'subject_id' => $t->maths->id, 'total_marks' => 100, 'marks' => $marks,
    ], $over);
}

function mk_put($t, Student $s, Subject $sub, float $obtained, float $total = 100): Mark
{
    return Mark::create(['exam_id' => $t->exam->id, 'student_id' => $s->id, 'subject_id' => $sub->id,
        'total_marks' => $total, 'obtained_marks' => $obtained]);
}

function mk_teacher(array $assignments, string $status = 'active'): User
{
    $user = User::factory()->create(['role' => 'teacher']);
    $teacher = Teacher::create(['name' => 'Teach', 'email' => $user->email, 'user_id' => $user->id, 'status' => $status]);
    foreach ($assignments as [$class, $section, $subject]) {
        $teacher->assignments()->create([
            'school_class_id' => $class->id, 'section_id' => $section?->id, 'subject_id' => $subject->id,
        ]);
    }

    return $user;
}

function mk_row(string $html, string $name): string
{
    preg_match_all('#<tr>.*?</tr>#s', $html, $m);
    foreach ($m[0] as $tr) {
        if (str_contains($tr, $name)) {
            return ' ' . trim(preg_replace('/\s+/', ' ', strip_tags($tr))) . ' ';
        }
    }

    return '';
}

// ---------------- access ----------------

test('guests cannot open marks pages', function () {
    auth()->logout();
    $this->get('/marks')->assertRedirect('/login');
    $this->get('/marks/entry')->assertRedirect('/login');
    $this->get("/marks/card/{$this->ali->id}?exam_id={$this->exam->id}")->assertRedirect('/login');
    $this->post('/marks', [])->assertRedirect('/login');
});

// ---------------- grading ----------------

test('grade boundaries', function () {
    expect(Grading::grade(100))->toBe('A+')->and(Grading::grade(80))->toBe('A+')
        ->and(Grading::grade(79.99))->toBe('A')->and(Grading::grade(70))->toBe('A')
        ->and(Grading::grade(69.99))->toBe('B')->and(Grading::grade(60))->toBe('B')
        ->and(Grading::grade(59.99))->toBe('C')->and(Grading::grade(50))->toBe('C')
        ->and(Grading::grade(49.99))->toBe('D')->and(Grading::grade(40))->toBe('D')
        ->and(Grading::grade(39.99))->toBe('F')->and(Grading::grade(0))->toBe('F');
});

test('pass percentage defaults to 40 and can be changed in settings', function () {
    expect(Grading::passMark())->toBe(40.0);
    Setting::set('pass_percentage', '33');
    expect(Grading::passMark())->toBe(33.0);
});

// ---------------- entry page ----------------

test('entry page shows only active students of the chosen class and section', function () {
    mk_student($this->class1, $this->secA, 'LeftKid', 'left');
    $q = "exam_id={$this->exam->id}&school_class_id={$this->class1->id}&section_id={$this->secA->id}&subject_id={$this->maths->id}";

    $this->get('/marks/entry')->assertOk()->assertSee('Select exam');
    $this->get("/marks/entry?{$q}")->assertOk()->assertSee('Ali')->assertSee('Sara')
        ->assertDontSee('Bilal')->assertDontSee('LeftKid')->assertDontSee('Zoya');
    $this->get("/marks/entry?exam_id={$this->exam->id}&school_class_id={$this->class1->id}&subject_id={$this->maths->id}")
        ->assertSee('Bilal');
});

test('entry page refuses a subject that is not part of the class', function () {
    $q = "exam_id={$this->exam->id}&school_class_id={$this->class2->id}&subject_id={$this->english->id}";
    $this->get("/marks/entry?{$q}")->assertOk()->assertSee('assign nahi hai')->assertDontSee('Zoya');
});

test('entry page prefills saved marks and the total', function () {
    mk_put($this, $this->ali, $this->maths, 45.5, 50);
    $q = "exam_id={$this->exam->id}&school_class_id={$this->class1->id}&section_id={$this->secA->id}&subject_id={$this->maths->id}";

    $html = $this->get("/marks/entry?{$q}")->getContent();
    expect($html)->toContain('value="45.5"')->and($html)->toContain('value="50"')
        ->and($html)->toContain('ke marks pehle se lage hain');
});

// ---------------- saving marks ----------------

test('marks for a whole class are saved', function () {
    $this->post('/marks', mk_payload($this, [$this->ali->id => '80', $this->sara->id => '65.5']))
        ->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('success');

    $rows = Mark::orderBy('student_id')->get();
    expect($rows)->toHaveCount(2)
        ->and((float) $rows[0]->obtained_marks)->toBe(80.0)
        ->and((float) $rows[1]->obtained_marks)->toBe(65.5)
        ->and((float) $rows[0]->total_marks)->toBe(100.0);
});

test('saving again updates the same rows and never duplicates', function () {
    $this->post('/marks', mk_payload($this, [$this->ali->id => '50', $this->sara->id => '60']));
    $this->post('/marks', mk_payload($this, [$this->ali->id => '70', $this->sara->id => '90'], ['total_marks' => 120]));

    expect(Mark::count())->toBe(2)
        ->and((float) Mark::where('student_id', $this->ali->id)->value('obtained_marks'))->toBe(70.0)
        ->and((float) Mark::where('student_id', $this->sara->id)->value('total_marks'))->toBe(120.0);
});

test('a blank box skips that student and keeps an old mark', function () {
    mk_put($this, $this->sara, $this->maths, 55);

    $this->post('/marks', mk_payload($this, [$this->ali->id => '80', $this->sara->id => '']))->assertSessionHasNoErrors();

    expect(Mark::count())->toBe(2)
        ->and((float) Mark::where('student_id', $this->sara->id)->value('obtained_marks'))->toBe(55.0);
});

test('zero marks are valid and saved', function () {
    $this->post('/marks', mk_payload($this, [$this->ali->id => '0']))->assertSessionHasNoErrors();
    expect(Mark::count())->toBe(1)->and((float) Mark::first()->obtained_marks)->toBe(0.0);
});

test('different subjects for the same student are separate rows', function () {
    $this->post('/marks', mk_payload($this, [$this->ali->id => '80']));
    $this->post('/marks', mk_payload($this, [$this->ali->id => '60'], ['subject_id' => $this->english->id]));
    expect(Mark::where('student_id', $this->ali->id)->count())->toBe(2);
});

test('validation: marks above total, negative, text, too many decimals', function () {
    $id = $this->ali->id;

    $this->post('/marks', mk_payload($this, [$id => '101']))->assertSessionHasErrors("marks.{$id}");
    $this->post('/marks', mk_payload($this, [$id => '51'], ['total_marks' => 50]))->assertSessionHasErrors("marks.{$id}");
    $this->post('/marks', mk_payload($this, [$id => '-5']))->assertSessionHasErrors("marks.{$id}");
    $this->post('/marks', mk_payload($this, [$id => 'abc']))->assertSessionHasErrors("marks.{$id}");
    $this->post('/marks', mk_payload($this, [$id => '50.123']))->assertSessionHasErrors("marks.{$id}");
    expect(Mark::count())->toBe(0);
});

test('validation: total marks, no marks, unknown exam, subject not in class, wrong section', function () {
    $id = $this->ali->id;

    $this->post('/marks', mk_payload($this, [$id => '5'], ['total_marks' => '']))->assertSessionHasErrors('total_marks');
    $this->post('/marks', mk_payload($this, [$id => '0'], ['total_marks' => 0]))->assertSessionHasErrors('total_marks');
    $this->post('/marks', mk_payload($this, [$id => '5'], ['total_marks' => 5000]))->assertSessionHasErrors('total_marks');
    $this->post('/marks', mk_payload($this, [$id => '']))->assertSessionHasErrors('marks');          // sab khali
    $this->post('/marks', mk_payload($this, []))->assertSessionHasErrors('marks');
    $this->post('/marks', mk_payload($this, [$id => '5'], ['exam_id' => 9999]))->assertSessionHasErrors('exam_id');
    $this->post('/marks', mk_payload($this, [$id => '5'], ['subject_id' => 9999]))->assertSessionHasErrors('subject_id');
    $this->post('/marks', mk_payload($this, [$this->zoya->id => '5'], [
        'school_class_id' => $this->class2->id, 'section_id' => $this->sec2A->id, 'subject_id' => $this->english->id,
    ]))->assertSessionHasErrors('subject_id');                                                            // English class 2 me nahi
    $this->post('/marks', mk_payload($this, [$id => '5'], ['section_id' => $this->sec2A->id]))->assertSessionHasErrors('section_id');

    expect(Mark::count())->toBe(0);
});

test('students of another class or section, and left students, cannot be sneaked in', function () {
    $left = mk_student($this->class1, $this->secA, 'Gone', 'left');

    $this->post('/marks', mk_payload($this, [$this->zoya->id => '50']))->assertSessionHasErrors('marks');
    $this->post('/marks', mk_payload($this, [$this->bilal->id => '50']))->assertSessionHasErrors('marks'); // section B
    $this->post('/marks', mk_payload($this, [$left->id => '50']))->assertSessionHasErrors('marks');

    expect(Mark::count())->toBe(0);
});

test('a class can be saved without choosing a section', function () {
    $this->post('/marks', mk_payload($this, [$this->ali->id => '50', $this->bilal->id => '60'], ['section_id' => null]))
        ->assertSessionHasNoErrors();
    expect(Mark::count())->toBe(2);
});

// ---------------- teacher restrictions ----------------

test('a teacher can enter marks only for their own subject', function () {
    $this->actingAs(mk_teacher([[$this->class1, null, $this->maths]]));

    $this->post('/marks', mk_payload($this, [$this->ali->id => '70']))->assertSessionHasNoErrors();
    $this->post('/marks', mk_payload($this, [$this->ali->id => '70'], ['subject_id' => $this->english->id]))
        ->assertSessionHasErrors('subject_id');

    expect(Mark::count())->toBe(1);
});

test('a teacher only gets their own subjects in the entry page', function () {
    $this->actingAs(mk_teacher([[$this->class1, null, $this->maths]]));
    $q = "exam_id={$this->exam->id}&school_class_id={$this->class1->id}";

    $html = $this->get("/marks/entry?{$q}")->getContent();
    expect($html)->toContain('>Maths</option>')->and($html)->not->toContain('>English</option>');

    $this->get("/marks/entry?{$q}&subject_id={$this->english->id}")->assertOk()->assertSee('aap ko assign nahi hai')->assertDontSee('Ali');
    $this->get("/marks/entry?{$q}&subject_id={$this->maths->id}")->assertOk()->assertSee('Ali');
});

test('a teacher with a section-only assignment cannot enter marks for other sections or the whole class', function () {
    $this->actingAs(mk_teacher([[$this->class1, $this->secA, $this->maths]]));

    $this->post('/marks', mk_payload($this, [$this->ali->id => '70']))->assertSessionHasNoErrors();
    $this->post('/marks', mk_payload($this, [$this->bilal->id => '70'], ['section_id' => $this->secB->id]))->assertSessionHasErrors('section_id');
    $this->post('/marks', mk_payload($this, [$this->ali->id => '70'], ['section_id' => null]))->assertSessionHasErrors('section_id');

    expect(Mark::count())->toBe(1);
});

test('a teacher cannot enter marks for a class that is not theirs', function () {
    $this->actingAs(mk_teacher([[$this->class1, null, $this->maths]]));

    $this->post('/marks', mk_payload($this, [$this->zoya->id => '70'], [
        'school_class_id' => $this->class2->id, 'section_id' => $this->sec2A->id,
    ]))->assertSessionHasErrors('school_class_id');
    expect(Mark::count())->toBe(0);
});

test('a teacher without a teacher record or who has left gets no classes and a clear message', function () {
    $this->actingAs(User::factory()->create(['role' => 'teacher']));
    $this->get('/marks/entry')->assertOk()->assertSee('koi class assign nahi hui');
    $this->get('/marks')->assertOk()->assertSee('koi class assign nahi hui');

    $this->actingAs(mk_teacher([[$this->class1, null, $this->maths]], 'left'));
    $this->get('/marks')->assertOk()->assertDontSee('Class 1');
});

// ---------------- results ----------------

test('results page asks to choose an exam and class first', function () {
    $this->get('/marks')->assertOk()->assertSee('Show results')->assertSee('Mid Term');
});

test('results show subject marks, total, percentage, grade, result and position', function () {
    mk_put($this, $this->ali, $this->maths, 80);
    mk_put($this, $this->ali, $this->english, 60);   // 140/200 = 70%  -> A, Pass
    mk_put($this, $this->sara, $this->maths, 90);
    mk_put($this, $this->sara, $this->english, 30);  // 120/200 = 60% -> B, but English 30% < 40 -> Fail

    $html = $this->get("/marks?exam_id={$this->exam->id}&school_class_id={$this->class1->id}")->assertOk()->getContent();

    $ali = mk_row($html, 'Ali');
    expect($ali)->toContain('80/100')->toContain('60/100')->toContain('140/200')->toContain(' 70 ')->toContain(' A ')->toContain('Pass')->toContain(' 1 ');

    $sara = mk_row($html, 'Sara');
    expect($sara)->toContain('120/200')->toContain(' 60 ')->toContain(' B ')->toContain('Fail')->toContain(' 2 ');

    expect(mk_row($html, 'Bilal'))->toContain('Marks nahi lage');
    expect($html)->not->toContain('Zoya');
});

test('position is by percentage, equal percentages share a position', function () {
    mk_put($this, $this->ali, $this->maths, 50);       // 50%
    mk_put($this, $this->sara, $this->maths, 50);      // 50% (tie)
    mk_put($this, $this->bilal, $this->maths, 90);     // 90%
    mk_put($this, $this->bilal, $this->english, 90);

    $html = $this->get("/marks?exam_id={$this->exam->id}&school_class_id={$this->class1->id}")->getContent();

    expect(mk_row($html, 'Bilal'))->toContain(' 90 ')->toContain(' A+ ')->toContain(' 1 ');
    expect(mk_row($html, 'Ali'))->toContain(' 2 ');
    expect(mk_row($html, 'Sara'))->toContain(' 2 ');
});

test('a student with fewer subjects is compared fairly by percentage not total', function () {
    mk_put($this, $this->ali, $this->maths, 100);                     // 100/100 = 100%
    mk_put($this, $this->sara, $this->maths, 80);
    mk_put($this, $this->sara, $this->english, 80);                   // 160/200 = 80%

    $html = $this->get("/marks?exam_id={$this->exam->id}&school_class_id={$this->class1->id}")->getContent();
    expect(mk_row($html, 'Ali'))->toContain(' 1 ')->and(mk_row($html, 'Sara'))->toContain(' 2 ');
});

test('changing the pass percentage in settings changes Pass/Fail', function () {
    mk_put($this, $this->sara, $this->maths, 35);

    $url = "/marks?exam_id={$this->exam->id}&school_class_id={$this->class1->id}";
    expect(mk_row($this->get($url)->getContent(), 'Sara'))->toContain('Fail');

    Setting::set('pass_percentage', '30');
    expect(mk_row($this->get($url)->getContent(), 'Sara'))->toContain('Pass');
});

test('results can be limited to a section', function () {
    mk_put($this, $this->ali, $this->maths, 80);
    mk_put($this, $this->bilal, $this->maths, 70);

    $html = $this->get("/marks?exam_id={$this->exam->id}&school_class_id={$this->class1->id}&section_id={$this->secB->id}")->getContent();
    expect($html)->toContain('Bilal')->and($html)->not->toContain('Ali');
});

test('a class with no marks shows a friendly message', function () {
    $this->get("/marks?exam_id={$this->exam->id}&school_class_id={$this->class1->id}")->assertOk()->assertSee('koi marks enter nahi hue');
});

test('a teacher sees results only for their own class and section', function () {
    mk_put($this, $this->ali, $this->maths, 80);
    mk_put($this, $this->bilal, $this->maths, 70);
    mk_put($this, $this->zoya, $this->maths, 60);

    $this->actingAs(mk_teacher([[$this->class1, $this->secA, $this->maths]]));
    $html = $this->get("/marks?exam_id={$this->exam->id}&school_class_id={$this->class1->id}")->getContent();
    expect($html)->toContain('Ali')->and($html)->not->toContain('Bilal');

    $this->get("/marks?exam_id={$this->exam->id}&school_class_id={$this->class2->id}")->assertOk()->assertDontSee('Zoya');
});

// ---------------- result card ----------------

test('result card shows the school, subjects, totals, result and position', function () {
    Setting::set('school_name', 'ABC Public School');
    mk_put($this, $this->ali, $this->maths, 80);
    mk_put($this, $this->ali, $this->english, 60);
    mk_put($this, $this->sara, $this->maths, 50);

    $html = $this->get("/marks/card/{$this->ali->id}?exam_id={$this->exam->id}")->assertOk()->getContent();

    expect($html)->toContain('ABC Public School')->toContain('Mid Term')->toContain('Ali')
        ->toContain('Maths')->toContain('English')->toContain('Grand total')
        ->toContain('Pass')->toContain('Position in class')->toContain('window.print()');
});

test('result card position counts the whole class and fails for a student with no marks', function () {
    mk_put($this, $this->ali, $this->maths, 60);
    mk_put($this, $this->bilal, $this->maths, 90);    // doosra section, phir bhi position me gina jata hai

    $html = $this->get("/marks/card/{$this->ali->id}?exam_id={$this->exam->id}")->getContent();
    expect($html)->toMatch('/Position in class<\/div>\s*<div[^>]*>\s*2\s*<span[^>]*>\/ 2</');

    $this->get("/marks/card/{$this->sara->id}?exam_id={$this->exam->id}")->assertNotFound();
});

test('result card needs a valid exam', function () {
    mk_put($this, $this->ali, $this->maths, 60);
    $this->get("/marks/card/{$this->ali->id}")->assertNotFound();
    $this->get("/marks/card/{$this->ali->id}?exam_id=9999")->assertNotFound();
});

test('a teacher cannot open the result card of a student outside their class', function () {
    mk_put($this, $this->ali, $this->maths, 60);
    mk_put($this, $this->zoya, $this->maths, 60);

    $this->actingAs(mk_teacher([[$this->class1, $this->secA, $this->maths]]));
    $this->get("/marks/card/{$this->ali->id}?exam_id={$this->exam->id}")->assertOk();
    $this->get("/marks/card/{$this->zoya->id}?exam_id={$this->exam->id}")->assertNotFound();
});

test('the student profile links to result cards for exams with marks', function () {
    $this->get("/students/{$this->ali->id}")->assertOk()->assertDontSee('Result cards');

    mk_put($this, $this->ali, $this->maths, 60);
    $this->get("/students/{$this->ali->id}")->assertOk()->assertSee('Result cards')->assertSee('Mid Term')
        ->assertSee("marks/card/{$this->ali->id}?exam_id={$this->exam->id}", false);
});
