<?php

use App\Models\Exam;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guests cannot open subjects pages', function () {
    auth()->logout();
    $this->get('/subjects')->assertRedirect('/login');
});

test('subjects pages open', function () {
    $subject = Subject::create(['name' => 'Maths', 'code' => 'MTH']);

    $this->get('/subjects')->assertOk()->assertSee('Maths');
    $this->get('/subjects/create')->assertOk();
    $this->get("/subjects/{$subject->id}")->assertOk()->assertSee('MTH');
    $this->get("/subjects/{$subject->id}/edit")->assertOk();
});

test('a subject is created and linked to the selected classes', function () {
    $one = SchoolClass::create(['name' => 'Class 1']);
    $two = SchoolClass::create(['name' => 'Class 2']);
    SchoolClass::create(['name' => 'Class 3']);

    $this->post('/subjects', ['name' => 'English', 'code' => 'ENG', 'class_ids' => [$one->id, $two->id]])
        ->assertRedirect();

    $subject = Subject::where('name', 'English')->first();
    expect($subject->classes->pluck('name')->sort()->values()->all())->toBe(['Class 1', 'Class 2']);
});

test('subject name is required and unique, code is unique', function () {
    Subject::create(['name' => 'Maths', 'code' => 'MTH']);

    $this->post('/subjects', ['name' => ''])->assertSessionHasErrors('name');
    $this->post('/subjects', ['name' => 'Maths'])->assertSessionHasErrors('name');
    $this->post('/subjects', ['name' => 'Math 2', 'code' => 'MTH'])->assertSessionHasErrors('code');
    $this->post('/subjects', ['name' => 'X', 'class_ids' => [9999]])->assertSessionHasErrors('class_ids.0');
    expect(Subject::count())->toBe(1);
});

test('update keeps own name/code and replaces the class links', function () {
    $one = SchoolClass::create(['name' => 'Class 1']);
    $two = SchoolClass::create(['name' => 'Class 2']);
    $subject = Subject::create(['name' => 'Maths', 'code' => 'MTH']);
    $subject->classes()->sync([$one->id]);

    $this->put("/subjects/{$subject->id}", ['name' => 'Maths', 'code' => 'MTH', 'class_ids' => [$two->id]])
        ->assertSessionHasNoErrors();

    expect($subject->refresh()->classes->pluck('name')->all())->toBe(['Class 2']);

    // sab classes uncheck karne par links khatam
    $this->put("/subjects/{$subject->id}", ['name' => 'Maths']);
    expect($subject->refresh()->classes()->count())->toBe(0);
});

test('update cannot take another subject name', function () {
    Subject::create(['name' => 'Maths']);
    $urdu = Subject::create(['name' => 'Urdu']);

    $this->put("/subjects/{$urdu->id}", ['name' => 'Maths'])->assertSessionHasErrors('name');
});

test('a subject without marks can be deleted with its links and assignments', function () {
    $class = SchoolClass::create(['name' => 'Class 1']);
    $subject = Subject::create(['name' => 'Maths']);
    $subject->classes()->sync([$class->id]);
    $teacher = Teacher::create(['name' => 'T', 'email' => 't@x.com']);
    $teacher->assignments()->create(['school_class_id' => $class->id, 'subject_id' => $subject->id]);

    $this->delete("/subjects/{$subject->id}")->assertRedirect('/subjects')->assertSessionHas('success');

    expect(Subject::find($subject->id))->toBeNull()
        ->and($teacher->assignments()->count())->toBe(0)
        ->and($class->subjects()->count())->toBe(0);
});

test('a subject that has marks cannot be deleted', function () {
    $class = SchoolClass::create(['name' => 'Class 1']);
    $subject = Subject::create(['name' => 'Maths']);
    $student = Student::create([
        'admission_no' => 'A1', 'first_name' => 'Ali', 'gender' => 'male',
        'guardian_name' => 'G', 'guardian_phone' => '1', 'school_class_id' => $class->id,
    ]);
    $exam = Exam::create(['name' => 'Mid']);
    Mark::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'subject_id' => $subject->id, 'obtained_marks' => 50]);

    $this->delete("/subjects/{$subject->id}")->assertSessionHas('error');
    expect(Subject::find($subject->id))->not->toBeNull();
});

test('subject search works by name and by code, and the list paginates', function () {
    Subject::create(['name' => 'Maths', 'code' => 'MTH']);
    Subject::create(['name' => 'Urdu', 'code' => 'URD']);

    $this->get('/subjects?q=Mat')->assertOk()->assertSee('Maths')->assertDontSee('Urdu');
    $this->get('/subjects?q=URD')->assertOk()->assertSee('Urdu')->assertDontSee('Maths');
    $this->get('/subjects?q=zzz')->assertOk()->assertSee('koi subject nahi mila');

    foreach (range(1, 12) as $i) {
        Subject::create(['name' => sprintf('S%02d', $i)]);
    }
    $this->get('/subjects?page=2')->assertOk();
});

test('sidebar shows Subjects and Teachers links', function () {
    $this->get('/dashboard')->assertOk()->assertSee('Subjects')->assertSee('Teachers');
});
