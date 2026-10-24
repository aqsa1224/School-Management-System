<?php

use App\Models\Fee;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('guests cannot open fee types', function () {
    auth()->logout();
    $this->get('/fee-types')->assertRedirect('/login');
});

test('fee type pages open and a fee type can be created', function () {
    $this->get('/fee-types')->assertOk();
    $this->get('/fee-types/create')->assertOk();

    $this->post('/fee-types', ['name' => 'Tuition Fee', 'default_amount' => '3000.50'])
        ->assertRedirect('/fee-types')->assertSessionHas('success');

    $type = FeeType::first();
    expect($type->name)->toBe('Tuition Fee')->and((float) $type->default_amount)->toBe(3000.5);
    $this->get('/fee-types')->assertSee('Tuition Fee')->assertSee('3,000.50');
    $this->get("/fee-types/{$type->id}/edit")->assertOk();
});

test('fee type validation', function () {
    FeeType::create(['name' => 'Tuition Fee', 'default_amount' => 100]);

    $this->post('/fee-types', ['name' => '', 'default_amount' => 10])->assertSessionHasErrors('name');
    $this->post('/fee-types', ['name' => 'Tuition Fee', 'default_amount' => 10])->assertSessionHasErrors('name');
    $this->post('/fee-types', ['name' => 'A', 'default_amount' => ''])->assertSessionHasErrors('default_amount');
    $this->post('/fee-types', ['name' => 'B', 'default_amount' => '-5'])->assertSessionHasErrors('default_amount');
    $this->post('/fee-types', ['name' => 'C', 'default_amount' => 'abc'])->assertSessionHasErrors('default_amount');
    $this->post('/fee-types', ['name' => 'D', 'default_amount' => '10.123'])->assertSessionHasErrors('default_amount');
    $this->post('/fee-types', ['name' => 'E', 'default_amount' => '99999999'])->assertSessionHasErrors('default_amount');
    $this->post('/fee-types', ['name' => 'Free', 'default_amount' => '0'])->assertSessionHasNoErrors();
    expect(FeeType::count())->toBe(2);
});

test('update keeps own name and rejects another name', function () {
    $a = FeeType::create(['name' => 'Tuition Fee', 'default_amount' => 100]);
    FeeType::create(['name' => 'Exam Fee', 'default_amount' => 50]);

    $this->put("/fee-types/{$a->id}", ['name' => 'Tuition Fee', 'default_amount' => 200])->assertSessionHasNoErrors();
    expect((float) $a->refresh()->default_amount)->toBe(200.0);
    $this->put("/fee-types/{$a->id}", ['name' => 'Exam Fee', 'default_amount' => 200])->assertSessionHasErrors('name');
});

test('a fee type with vouchers cannot be deleted but an unused one can', function () {
    $used = FeeType::create(['name' => 'Used', 'default_amount' => 1]);
    $free = FeeType::create(['name' => 'Free', 'default_amount' => 1]);
    $class = SchoolClass::create(['name' => 'C1']);
    $student = Student::create(['admission_no' => 'A1', 'first_name' => 'Ali', 'gender' => 'male', 'guardian_name' => 'G',
        'guardian_phone' => '1', 'school_class_id' => $class->id]);
    Fee::create(['voucher_no' => 'V1', 'student_id' => $student->id, 'fee_type_id' => $used->id, 'amount' => 10]);

    $this->delete("/fee-types/{$used->id}")->assertSessionHas('error');
    $this->delete("/fee-types/{$free->id}")->assertSessionHas('success');
    expect(FeeType::find($used->id))->not->toBeNull()->and(FeeType::find($free->id))->toBeNull();
});

test('fee type search and pagination, and sidebar link', function () {
    FeeType::create(['name' => 'Tuition Fee', 'default_amount' => 1]);
    FeeType::create(['name' => 'Exam Fee', 'default_amount' => 1]);
    $this->get('/fee-types?q=Tuit')->assertSee('Tuition Fee')->assertDontSee('Exam Fee');
    $this->get('/fee-types?q=zzz')->assertSee('koi fee type nahi mili');
    foreach (range(1, 12) as $i) {
        FeeType::create(['name' => "T{$i}", 'default_amount' => 1]);
    }
    $this->get('/fee-types?page=2')->assertOk();
    $this->get('/dashboard')->assertSee('Fee Types');
});
