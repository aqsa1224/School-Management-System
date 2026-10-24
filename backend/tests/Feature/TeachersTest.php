<?php

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

function teacherData(array $over = []): array
{
    return array_merge([
        'name' => 'Sana Khan', 'email' => 'sana@school.test', 'phone' => '0300-1234567',
        'gender' => 'female', 'status' => 'active',
    ], $over);
}

test('guests cannot open teachers pages', function () {
    auth()->logout();
    $this->get('/teachers')->assertRedirect('/login');
});

test('teachers pages open', function () {
    $teacher = Teacher::create(['name' => 'Sana', 'email' => 's@x.com']);

    $this->get('/teachers')->assertOk()->assertSee('Sana');
    $this->get('/teachers/create')->assertOk();
    $this->get("/teachers/{$teacher->id}")->assertOk()->assertSee('Class / Subject assignments');
    $this->get("/teachers/{$teacher->id}/edit")->assertOk();
});

test('a teacher can be created without a login account', function () {
    $this->post('/teachers', teacherData())->assertRedirect();

    $teacher = Teacher::where('email', 'sana@school.test')->first();
    expect($teacher)->not->toBeNull()->and($teacher->user_id)->toBeNull()->and($teacher->status)->toBe('active');
});

test('a teacher can be created with a login account that really logs in', function () {
    $this->post('/teachers', teacherData(['create_login' => 1, 'password' => 'secret123']))->assertRedirect();

    $teacher = Teacher::where('email', 'sana@school.test')->first();
    expect($teacher->user)->not->toBeNull()
        ->and($teacher->user->role)->toBe('teacher')
        ->and($teacher->user->name)->toBe('Sana Khan');

    auth()->logout();
    $this->post('/login', ['email' => 'sana@school.test', 'password' => 'secret123'])->assertRedirect('/dashboard');
    $this->assertAuthenticated();
});

test('login password is required when a login account is requested', function () {
    $this->post('/teachers', teacherData(['create_login' => 1]))->assertSessionHasErrors('password');
    $this->post('/teachers', teacherData(['create_login' => 1, 'password' => 'short']))->assertSessionHasErrors('password');
    expect(Teacher::count())->toBe(0);
});

test('validation: required fields, unique email, valid enums', function () {
    Teacher::create(['name' => 'Old', 'email' => 'sana@school.test']);

    $this->post('/teachers', teacherData(['name' => '']))->assertSessionHasErrors('name');
    $this->post('/teachers', teacherData())->assertSessionHasErrors('email');
    $this->post('/teachers', teacherData(['email' => 'not-an-email']))->assertSessionHasErrors('email');
    $this->post('/teachers', teacherData(['email' => 'a@x.com', 'gender' => 'zzz']))->assertSessionHasErrors('gender');
    $this->post('/teachers', teacherData(['email' => 'b@x.com', 'status' => 'zzz']))->assertSessionHasErrors('status');
    $this->post('/teachers', teacherData(['email' => 'c@x.com', 'joining_date' => 'abc']))->assertSessionHasErrors('joining_date');
});

test('a login email already used by another user is rejected', function () {
    $this->post('/teachers', teacherData(['email' => $this->admin->email, 'create_login' => 1, 'password' => 'secret123']))
        ->assertSessionHasErrors('email');
    expect(Teacher::count())->toBe(0);
});

test('update changes details and keeps the same email without a unique error', function () {
    $teacher = Teacher::create(['name' => 'Sana', 'email' => 'sana@school.test']);

    $this->put("/teachers/{$teacher->id}", teacherData(['name' => 'Sana Updated']))->assertSessionHasNoErrors();
    expect($teacher->refresh()->name)->toBe('Sana Updated');
});

test('update cannot take another teacher email', function () {
    Teacher::create(['name' => 'One', 'email' => 'one@x.com']);
    $two = Teacher::create(['name' => 'Two', 'email' => 'two@x.com']);

    $this->put("/teachers/{$two->id}", teacherData(['email' => 'one@x.com']))->assertSessionHasErrors('email');
});

test('update syncs the login account and can reset its password', function () {
    $this->post('/teachers', teacherData(['create_login' => 1, 'password' => 'secret123']));
    $teacher = Teacher::first();

    $this->put("/teachers/{$teacher->id}", teacherData(['name' => 'Sana New', 'email' => 'new@school.test', 'password' => 'brandnew99']))
        ->assertSessionHasNoErrors();

    $user = $teacher->refresh()->user;
    expect($user->name)->toBe('Sana New')->and($user->email)->toBe('new@school.test');

    auth()->logout();
    $this->post('/login', ['email' => 'new@school.test', 'password' => 'brandnew99']);
    $this->assertAuthenticated();
});

test('a login account can be added later while editing', function () {
    $teacher = Teacher::create(['name' => 'Sana', 'email' => 'sana@school.test']);

    $this->put("/teachers/{$teacher->id}", teacherData(['create_login' => 1, 'password' => 'secret123']))
        ->assertSessionHasNoErrors();

    expect($teacher->refresh()->user_id)->not->toBeNull();
});

test('deleting a teacher removes the teacher login and assignments but never an admin', function () {
    $class = SchoolClass::create(['name' => 'Class 1']);
    $subject = Subject::create(['name' => 'Maths']);
    $class->subjects()->attach($subject);

    $this->post('/teachers', teacherData(['create_login' => 1, 'password' => 'secret123']));
    $teacher = Teacher::first();
    $teacher->assignments()->create(['school_class_id' => $class->id, 'subject_id' => $subject->id]);
    $loginId = $teacher->user_id;

    $this->delete("/teachers/{$teacher->id}")->assertRedirect('/teachers')->assertSessionHas('success');

    expect(Teacher::count())->toBe(0)
        ->and(User::find($loginId))->toBeNull()
        ->and(User::find($this->admin->id))->not->toBeNull();

    // Teacher jis ka login account admin ka ho, wo account delete nahi hona chahiye
    $odd = Teacher::create(['name' => 'Odd', 'email' => 'odd@x.com', 'user_id' => $this->admin->id]);
    $this->delete("/teachers/{$odd->id}");
    expect(User::find($this->admin->id))->not->toBeNull();
});

test('search and status filter work and the list paginates', function () {
    Teacher::create(['name' => 'Sana Khan', 'email' => 'sana@x.com', 'phone' => '0300']);
    Teacher::create(['name' => 'Bilal Ahmed', 'email' => 'bilal@x.com', 'status' => 'left']);

    $this->get('/teachers?q=Sana')->assertOk()->assertSee('Sana Khan')->assertDontSee('Bilal');
    $this->get('/teachers?q=bilal@x')->assertOk()->assertSee('Bilal Ahmed');
    $this->get('/teachers?status=left')->assertOk()->assertSee('Bilal Ahmed')->assertDontSee('Sana Khan');
    $this->get('/teachers?q=zzz')->assertOk()->assertSee('koi teacher nahi mila');

    foreach (range(1, 12) as $i) {
        Teacher::create(['name' => sprintf('T%02d', $i), 'email' => "t{$i}@x.com"]);
    }
    $this->get('/teachers?page=2')->assertOk();
});

// ---------------- assignments ----------------

function setupAssignment(): array
{
    $class = SchoolClass::create(['name' => 'Class 1']);
    $section = $class->sections()->create(['name' => 'A']);
    $subject = Subject::create(['name' => 'Maths']);
    $class->subjects()->attach($subject);
    $teacher = Teacher::create(['name' => 'Sana', 'email' => 's@x.com']);

    return [$teacher, $class, $section, $subject];
}

test('a teacher can be assigned a class, section and subject', function () {
    [$teacher, $class, $section, $subject] = setupAssignment();

    $this->post("/teachers/{$teacher->id}/assignments", [
        'school_class_id' => $class->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
    ])->assertRedirect("/teachers/{$teacher->id}")->assertSessionHas('success');

    $this->get("/teachers/{$teacher->id}")->assertOk()->assertSee('Class 1')->assertSee('Maths');
    expect($teacher->assignments()->count())->toBe(1);
});

test('assignment without a section means all sections', function () {
    [$teacher, $class, , $subject] = setupAssignment();

    $this->post("/teachers/{$teacher->id}/assignments", ['school_class_id' => $class->id, 'subject_id' => $subject->id])
        ->assertSessionHasNoErrors();

    expect($teacher->assignments()->first()->section_id)->toBeNull();
    $this->get("/teachers/{$teacher->id}")->assertSee('All sections');
});

test('assignment is rejected when the subject is not part of that class', function () {
    [$teacher, $class] = setupAssignment();
    $other = Subject::create(['name' => 'Urdu']); // class se jora nahi

    $this->post("/teachers/{$teacher->id}/assignments", ['school_class_id' => $class->id, 'subject_id' => $other->id])
        ->assertSessionHasErrors('subject_id');
    expect($teacher->assignments()->count())->toBe(0);
});

test('assignment is rejected when the section belongs to another class', function () {
    [$teacher, $class, , $subject] = setupAssignment();
    $two = SchoolClass::create(['name' => 'Class 2']);
    $foreignSection = $two->sections()->create(['name' => 'Z']);

    $this->post("/teachers/{$teacher->id}/assignments", [
        'school_class_id' => $class->id, 'section_id' => $foreignSection->id, 'subject_id' => $subject->id,
    ])->assertSessionHasErrors('section_id');
});

test('duplicate assignment is rejected', function () {
    [$teacher, $class, $section, $subject] = setupAssignment();
    $payload = ['school_class_id' => $class->id, 'section_id' => $section->id, 'subject_id' => $subject->id];

    $this->post("/teachers/{$teacher->id}/assignments", $payload)->assertSessionHasNoErrors();
    $this->post("/teachers/{$teacher->id}/assignments", $payload)->assertSessionHasErrors('subject_id');

    // "All sections" ka duplicate bhi pakra jaye
    $all = ['school_class_id' => $class->id, 'subject_id' => $subject->id];
    $this->post("/teachers/{$teacher->id}/assignments", $all)->assertSessionHasNoErrors();
    $this->post("/teachers/{$teacher->id}/assignments", $all)->assertSessionHasErrors('subject_id');

    expect($teacher->assignments()->count())->toBe(2);
});

test('assignment needs class and subject', function () {
    [$teacher] = setupAssignment();
    $this->post("/teachers/{$teacher->id}/assignments", [])->assertSessionHasErrors(['school_class_id', 'subject_id']);
});

test('an assignment can be removed, but not through another teachers url', function () {
    [$teacher, $class, , $subject] = setupAssignment();
    $other = Teacher::create(['name' => 'Other', 'email' => 'o@x.com']);
    $assignment = $teacher->assignments()->create(['school_class_id' => $class->id, 'subject_id' => $subject->id]);

    $this->delete("/teachers/{$other->id}/assignments/{$assignment->id}")->assertNotFound();
    expect($teacher->assignments()->count())->toBe(1);

    $this->delete("/teachers/{$teacher->id}/assignments/{$assignment->id}")->assertRedirect()->assertSessionHas('success');
    expect($teacher->assignments()->count())->toBe(0);
});

test('subject page lists the teachers assigned to it', function () {
    [$teacher, $class, , $subject] = setupAssignment();
    $teacher->assignments()->create(['school_class_id' => $class->id, 'subject_id' => $subject->id]);

    $this->get("/subjects/{$subject->id}")->assertOk()->assertSee('Sana');
});
