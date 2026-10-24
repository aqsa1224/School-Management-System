<?php

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function makeStudent(SchoolClass $class, ?Section $section = null, string $no = 'ADM-1'): Student
{
    return Student::create([
        'admission_no' => $no, 'first_name' => 'Ali', 'gender' => 'male',
        'guardian_name' => 'G', 'guardian_phone' => '1',
        'school_class_id' => $class->id, 'section_id' => $section?->id,
    ]);
}

test('guests cannot open classes pages', function () {
    auth()->logout();
    $this->get('/classes')->assertRedirect('/login');
});

test('classes list, create and edit pages open', function () {
    $class = SchoolClass::create(['name' => 'Class 1']);

    $this->get('/classes')->assertOk()->assertSee('Class 1');
    $this->get('/classes/create')->assertOk();
    $this->get("/classes/{$class->id}")->assertOk()->assertSee('Sections');
    $this->get("/classes/{$class->id}/edit")->assertOk();
});

test('a class can be created and then shows its page', function () {
    $this->post('/classes', ['name' => 'Class 9', 'description' => 'Matric'])
        ->assertRedirect();

    $class = SchoolClass::where('name', 'Class 9')->first();
    expect($class)->not->toBeNull();
    $this->get("/classes/{$class->id}")->assertOk()->assertSee('Matric');
});

test('sort order is filled automatically when left empty', function () {
    SchoolClass::create(['name' => 'A', 'sort_order' => 5]);
    $this->post('/classes', ['name' => 'B']);
    expect(SchoolClass::where('name', 'B')->value('sort_order'))->toBe(6);
});

test('class name is required and must be unique', function () {
    SchoolClass::create(['name' => 'Class 1']);

    $this->post('/classes', ['name' => ''])->assertSessionHasErrors('name');
    $this->post('/classes', ['name' => 'Class 1'])->assertSessionHasErrors('name');
    expect(SchoolClass::count())->toBe(1);
});

test('a class can be updated and keeps its own name without a unique error', function () {
    $class = SchoolClass::create(['name' => 'Class 1', 'sort_order' => 3]);

    $this->put("/classes/{$class->id}", ['name' => 'Class 1', 'description' => 'x'])
        ->assertSessionHasNoErrors();
    $this->put("/classes/{$class->id}", ['name' => 'Class One'])
        ->assertRedirect("/classes/{$class->id}");

    $class->refresh();
    expect($class->name)->toBe('Class One')->and($class->sort_order)->toBe(3);
});

test('update cannot take another class name', function () {
    SchoolClass::create(['name' => 'Class 1']);
    $two = SchoolClass::create(['name' => 'Class 2']);

    $this->put("/classes/{$two->id}", ['name' => 'Class 1'])->assertSessionHasErrors('name');
});

test('an empty class can be deleted and its sections go with it', function () {
    $class = SchoolClass::create(['name' => 'Temp']);
    $class->sections()->create(['name' => 'A']);

    $this->delete("/classes/{$class->id}")->assertRedirect('/classes')->assertSessionHas('success');

    expect(SchoolClass::find($class->id))->toBeNull()
        ->and(Section::where('school_class_id', $class->id)->count())->toBe(0);
});

test('a class that has students cannot be deleted', function () {
    $class = SchoolClass::create(['name' => 'Class 1']);
    makeStudent($class);

    $this->delete("/classes/{$class->id}")->assertRedirect('/classes')->assertSessionHas('error');
    expect(SchoolClass::find($class->id))->not->toBeNull();
});

test('search filters the classes list', function () {
    SchoolClass::create(['name' => 'Class 1']);
    SchoolClass::create(['name' => 'Nursery']);

    $this->get('/classes?q=Nurs')->assertOk()->assertSee('Nursery')->assertDontSee('Class 1');
    $this->get('/classes?q=zzz')->assertOk()->assertSee('koi class nahi mili');
});

test('the list is paginated', function () {
    foreach (range(1, 12) as $i) {
        SchoolClass::create(['name' => "C{$i}", 'sort_order' => $i]);
    }
    $this->get('/classes')->assertOk()->assertSee('C10')->assertDontSee('C11');
    $this->get('/classes?page=2')->assertOk()->assertSee('C11');
});

test('a section can be added to a class', function () {
    $class = SchoolClass::create(['name' => 'Class 1']);

    $this->post("/classes/{$class->id}/sections", ['name' => 'A', 'capacity' => 40])
        ->assertRedirect("/classes/{$class->id}")->assertSessionHas('success');

    expect($class->sections()->where('name', 'A')->value('capacity'))->toBe(40);
});

test('section name is required and unique inside the same class only', function () {
    $one = SchoolClass::create(['name' => 'Class 1']);
    $two = SchoolClass::create(['name' => 'Class 2']);
    $one->sections()->create(['name' => 'A']);

    $this->post("/classes/{$one->id}/sections", ['name' => ''])->assertSessionHasErrors('name');
    $this->post("/classes/{$one->id}/sections", ['name' => 'A'])->assertSessionHasErrors('name');
    // doosri class me same naam theek hai
    $this->post("/classes/{$two->id}/sections", ['name' => 'A'])->assertSessionHasNoErrors();
    // capacity galat ho to error
    $this->post("/classes/{$one->id}/sections", ['name' => 'B', 'capacity' => 0])->assertSessionHasErrors('capacity');
});

test('a section can be renamed and keep its own name', function () {
    $class = SchoolClass::create(['name' => 'Class 1']);
    $section = $class->sections()->create(['name' => 'A']);

    $this->put("/classes/{$class->id}/sections/{$section->id}", ['name' => 'A', 'capacity' => 30])
        ->assertSessionHasNoErrors();
    $this->put("/classes/{$class->id}/sections/{$section->id}", ['name' => 'Rose'])->assertRedirect();

    expect($section->refresh()->name)->toBe('Rose');
});

test('a section of another class cannot be changed through this class url', function () {
    $one = SchoolClass::create(['name' => 'Class 1']);
    $two = SchoolClass::create(['name' => 'Class 2']);
    $foreign = $two->sections()->create(['name' => 'A']);

    $this->put("/classes/{$one->id}/sections/{$foreign->id}", ['name' => 'Hack'])->assertNotFound();
    $this->delete("/classes/{$one->id}/sections/{$foreign->id}")->assertNotFound();
    expect($foreign->refresh()->name)->toBe('A');
});

test('an empty section can be deleted but one with students cannot', function () {
    $class = SchoolClass::create(['name' => 'Class 1']);
    $empty = $class->sections()->create(['name' => 'A']);
    $used = $class->sections()->create(['name' => 'B']);
    makeStudent($class, $used);

    $this->delete("/classes/{$class->id}/sections/{$empty->id}")->assertSessionHas('success');
    $this->delete("/classes/{$class->id}/sections/{$used->id}")->assertSessionHas('error');

    expect(Section::find($empty->id))->toBeNull()->and(Section::find($used->id))->not->toBeNull();
});

test('the sidebar shows the Classes link once the module exists', function () {
    $this->get('/dashboard')->assertOk()->assertSee('Classes');
});
