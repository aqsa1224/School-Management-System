<?php

use App\Models\Exam;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('guests cannot open exams pages', function () {
    auth()->logout();
    $this->get('/exams')->assertRedirect('/login');
});

test('exams pages open and an exam can be created', function () {
    $this->get('/exams')->assertOk();
    $this->get('/exams/create')->assertOk();

    $this->post('/exams', ['name' => 'Mid Term 2026', 'start_date' => '2026-10-01', 'end_date' => '2026-10-10'])
        ->assertRedirect('/exams')->assertSessionHas('success');

    $exam = Exam::where('name', 'Mid Term 2026')->first();
    expect($exam)->not->toBeNull();
    $this->get('/exams')->assertSee('Mid Term 2026')->assertSee('01 Oct 2026');
    $this->get("/exams/{$exam->id}/edit")->assertOk();
});

test('exam dates are optional but name is required and unique', function () {
    $this->post('/exams', ['name' => 'Final'])->assertSessionHasNoErrors();
    $this->post('/exams', ['name' => ''])->assertSessionHasErrors('name');
    $this->post('/exams', ['name' => 'Final'])->assertSessionHasErrors('name');
    expect(Exam::count())->toBe(1);
});

test('end date cannot be before start date and dates must be real', function () {
    $this->post('/exams', ['name' => 'A', 'start_date' => '2026-10-10', 'end_date' => '2026-10-01'])->assertSessionHasErrors('end_date');
    $this->post('/exams', ['name' => 'B', 'start_date' => 'abc'])->assertSessionHasErrors('start_date');
    $this->post('/exams', ['name' => 'C', 'start_date' => '2026-10-10', 'end_date' => '2026-10-10'])->assertSessionHasNoErrors();
});

test('an exam can be updated and keeps its own name', function () {
    $exam = Exam::create(['name' => 'Mid']);
    Exam::create(['name' => 'Final']);

    $this->put("/exams/{$exam->id}", ['name' => 'Mid'])->assertSessionHasNoErrors();
    $this->put("/exams/{$exam->id}", ['name' => 'Mid Term'])->assertRedirect('/exams');
    expect($exam->refresh()->name)->toBe('Mid Term');
    $this->put("/exams/{$exam->id}", ['name' => 'Final'])->assertSessionHasErrors('name');
});

test('an exam without marks can be deleted, one with marks cannot', function () {
    $empty = Exam::create(['name' => 'Empty']);
    $used = Exam::create(['name' => 'Used']);

    $class = SchoolClass::create(['name' => 'Class 1']);
    $subject = Subject::create(['name' => 'Maths']);
    $student = Student::create([
        'admission_no' => 'A1', 'first_name' => 'Ali', 'gender' => 'male', 'guardian_name' => 'G',
        'guardian_phone' => '1', 'school_class_id' => $class->id,
    ]);
    Mark::create(['exam_id' => $used->id, 'student_id' => $student->id, 'subject_id' => $subject->id, 'obtained_marks' => 50]);

    $this->delete("/exams/{$empty->id}")->assertRedirect('/exams')->assertSessionHas('success');
    $this->delete("/exams/{$used->id}")->assertSessionHas('error');

    expect(Exam::find($empty->id))->toBeNull()->and(Exam::find($used->id))->not->toBeNull();
});

test('exam search works and the list paginates', function () {
    Exam::create(['name' => 'Mid Term']);
    Exam::create(['name' => 'Final Term']);

    $this->get('/exams?q=Mid')->assertOk()->assertSee('Mid Term')->assertDontSee('Final Term');
    $this->get('/exams?q=zzz')->assertOk()->assertSee('koi exam nahi mila');

    foreach (range(1, 12) as $i) {
        Exam::create(['name' => sprintf('Test %02d', $i), 'start_date' => now()->subDays($i)]);
    }
    $this->get('/exams?page=2')->assertOk();
});

test('exams list links to marks entry and results', function () {
    $exam = Exam::create(['name' => 'Mid']);
    $this->get('/exams')->assertSee("marks/entry?exam_id={$exam->id}", false)->assertSee("marks?exam_id={$exam->id}", false);
});

test('sidebar shows Exams and Marks links', function () {
    $this->get('/dashboard')->assertOk()->assertSee('Exams')->assertSee('Marks / Results');
});
