<?php

use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Boss', 'email' => 'boss@school.test']);
    $this->actingAs($this->admin);
});

function us_data(array $over = []): array
{
    return array_merge([
        'name' => 'Sana Khan', 'email' => 'sana@school.test', 'role' => 'accountant',
        'password' => 'secret123', 'is_active' => 1,
    ], $over);
}

test('user pages open for admin', function () {
    $other = User::factory()->create(['role' => 'teacher']);
    $this->get('/users')->assertOk()->assertSee('Boss')->assertSee($other->email);
    $this->get('/users/create')->assertOk();
    $this->get("/users/{$other->id}/edit")->assertOk();
});

test('an admin can create a user who can then really log in with the chosen role', function () {
    $this->post('/users', us_data())->assertSessionHasNoErrors()->assertRedirect('/users')->assertSessionHas('success');

    $u = User::where('email', 'sana@school.test')->first();
    expect($u->role)->toBe('accountant')->and($u->is_active)->toBeTrue()->and(Hash::check('secret123', $u->password))->toBeTrue();

    auth()->logout();
    $this->post('/login', ['email' => 'sana@school.test', 'password' => 'secret123'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($u);
    $this->get('/fees')->assertOk();          // accountant ka kaam
    $this->get('/users')->assertForbidden();  // admin ka kaam nahi
});

test('a user can be created as inactive', function () {
    $this->post('/users', us_data(['is_active' => 0]))->assertSessionHasNoErrors();
    expect(User::where('email', 'sana@school.test')->first()->is_active)->toBeFalse();
});

test('user validation on create', function () {
    $this->post('/users', us_data(['name' => '']))->assertSessionHasErrors('name');
    $this->post('/users', us_data(['email' => 'nope']))->assertSessionHasErrors('email');
    $this->post('/users', us_data(['email' => 'boss@school.test']))->assertSessionHasErrors('email');
    $this->post('/users', us_data(['role' => 'superman']))->assertSessionHasErrors('role');
    $this->post('/users', us_data(['role' => '']))->assertSessionHasErrors('role');
    $this->post('/users', us_data(['password' => '']))->assertSessionHasErrors('password');
    $this->post('/users', us_data(['password' => 'short']))->assertSessionHasErrors('password');
    expect(User::count())->toBe(1);
});

test('every configured role can be assigned', function () {
    foreach (array_keys(config('roles.labels')) as $i => $role) {
        $this->post('/users', us_data(['role' => $role, 'email' => "r{$i}@school.test"]))->assertSessionHasNoErrors();
    }
    expect(User::whereIn('email', ['r0@school.test', 'r1@school.test', 'r2@school.test', 'r3@school.test'])->count())->toBe(4);
});

test('a user can be edited and keeps their own email without a unique error', function () {
    $u = User::factory()->create(['role' => 'teacher', 'email' => 'old@school.test']);

    $this->put("/users/{$u->id}", us_data(['email' => 'old@school.test', 'role' => 'receptionist', 'name' => 'New Name', 'password' => '']))
        ->assertSessionHasNoErrors()->assertRedirect('/users');

    $u->refresh();
    expect($u->role)->toBe('receptionist')->and($u->name)->toBe('New Name');
    $this->put("/users/{$u->id}", us_data(['email' => 'boss@school.test']))->assertSessionHasErrors('email');
});

test('leaving the password empty keeps it and a new password replaces it', function () {
    $u = User::factory()->create(['email' => 'p@school.test', 'role' => 'teacher', 'password' => 'original123']);

    $this->put("/users/{$u->id}", us_data(['email' => 'p@school.test', 'password' => '']))->assertSessionHasNoErrors();
    expect(Hash::check('original123', $u->refresh()->password))->toBeTrue();

    $this->put("/users/{$u->id}", us_data(['email' => 'p@school.test', 'password' => 'brandnew456']))->assertSessionHasNoErrors();
    expect(Hash::check('brandnew456', $u->refresh()->password))->toBeTrue();

    $this->put("/users/{$u->id}", us_data(['email' => 'p@school.test', 'password' => 'short']))->assertSessionHasErrors('password');
});

// ---------------- lock-out safety ----------------

test('an admin cannot change their own role or deactivate themselves', function () {
    User::factory()->create(['role' => 'admin']);   // doosra admin maujood ho tab bhi

    $this->put("/users/{$this->admin->id}", us_data(['email' => 'boss@school.test', 'role' => 'teacher']))->assertSessionHasErrors('role');
    $this->put("/users/{$this->admin->id}", us_data(['email' => 'boss@school.test', 'role' => 'admin', 'is_active' => 0]))->assertSessionHasErrors('is_active');

    expect($this->admin->refresh()->role)->toBe('admin')->and($this->admin->is_active)->toBeTrue();
});

test('an admin can still edit their own name and password', function () {
    $this->put("/users/{$this->admin->id}", us_data(['name' => 'Boss Renamed', 'email' => 'boss@school.test', 'role' => 'admin', 'password' => 'newpass789']))
        ->assertSessionHasNoErrors();
    expect($this->admin->refresh()->name)->toBe('Boss Renamed')->and(Hash::check('newpass789', $this->admin->password))->toBeTrue();
});

test('the only active admin cannot be demoted, deactivated or deleted even by someone allowed to manage users', function () {
    // Normal me sirf admin users.manage rakhta hai, isliye yahan receptionist ko ye ijazat de kar
    // (config se) check karte hain: actor aur target alag hon, is liye "apna role" wala rule beech me nahi aata.
    config(['roles.permissions.receptionist' => ['users.manage']]);
    $helper = User::factory()->create(['role' => 'receptionist']);
    User::factory()->create(['role' => 'admin', 'is_active' => false]);   // inactive admin ginti me nahi aata
    $this->actingAs($helper);

    // $this->admin akela active admin hai
    $this->put("/users/{$this->admin->id}", us_data(['email' => 'boss@school.test', 'role' => 'teacher']))->assertSessionHasErrors('role');
    $this->put("/users/{$this->admin->id}", us_data(['email' => 'boss@school.test', 'role' => 'admin', 'is_active' => 0]))->assertSessionHasErrors('role');
    $this->delete("/users/{$this->admin->id}")->assertSessionHas('error');
    expect($this->admin->refresh()->role)->toBe('admin')->and($this->admin->is_active)->toBeTrue()
        ->and(User::find($this->admin->id))->not->toBeNull();

    // Ek aur ACTIVE admin ban jaye to wahi kaam ab theek hain
    User::factory()->create(['role' => 'admin']);
    $this->put("/users/{$this->admin->id}", us_data(['email' => 'boss@school.test', 'role' => 'teacher']))->assertSessionHasNoErrors();
    expect($this->admin->refresh()->role)->toBe('teacher');
});

test('a user cannot delete their own account from the users list', function () {
    $this->delete("/users/{$this->admin->id}")->assertSessionHas('error');
    expect(User::find($this->admin->id))->not->toBeNull();
});

test('another user can be deleted', function () {
    $u = User::factory()->create(['role' => 'teacher']);
    $this->delete("/users/{$u->id}")->assertRedirect('/users')->assertSessionHas('success');
    expect(User::find($u->id))->toBeNull();
});

test('a second admin can be deleted when another active admin remains', function () {
    $second = User::factory()->create(['role' => 'admin']);
    $this->delete("/users/{$second->id}")->assertSessionHas('success');
    expect(User::find($second->id))->toBeNull();
});

test('a user who received fee payments cannot be deleted but can be deactivated', function () {
    $accountant = User::factory()->create(['role' => 'accountant']);
    $class = SchoolClass::create(['name' => 'C']);
    $student = Student::create(['admission_no' => 'U1', 'first_name' => 'A', 'gender' => 'male', 'guardian_name' => 'G', 'guardian_phone' => '1', 'school_class_id' => $class->id]);
    $fee = Fee::create(['voucher_no' => 'UV1', 'student_id' => $student->id, 'fee_type_id' => FeeType::create(['name' => 'T', 'default_amount' => 1])->id, 'amount' => 10]);
    FeePayment::create(['receipt_no' => 'UR1', 'fee_id' => $fee->id, 'amount' => 5, 'payment_date' => '2026-10-01', 'received_by' => $accountant->id]);

    $this->delete("/users/{$accountant->id}")->assertSessionHas('error');
    expect(User::find($accountant->id))->not->toBeNull();

    $this->put("/users/{$accountant->id}", us_data(['email' => $accountant->email, 'role' => 'accountant', 'is_active' => 0]))->assertSessionHasNoErrors();
    expect($accountant->refresh()->is_active)->toBeFalse();
});

test('deleting a user linked to a teacher keeps the teacher record', function () {
    $u = User::factory()->create(['role' => 'teacher']);
    $t = Teacher::create(['name' => 'T', 'email' => $u->email, 'user_id' => $u->id]);

    $this->delete("/users/{$u->id}")->assertSessionHas('success');
    expect(Teacher::find($t->id))->not->toBeNull()->and($t->refresh()->user_id)->toBeNull();
});

test('the edit page explains a linked teacher record and locks own role and status', function () {
    $u = User::factory()->create(['role' => 'teacher']);
    Teacher::create(['name' => 'Linked Teacher', 'email' => $u->email, 'user_id' => $u->id]);

    $this->get("/users/{$u->id}/edit")->assertSee('Linked Teacher')->assertSee('Teacher record');

    $mine = $this->get("/users/{$this->admin->id}/edit")->getContent();
    expect($mine)->toMatch('/<select[^>]*id="role"[^>]*disabled/')->and($mine)->toContain('role="switch"');
});

// ---------------- list ----------------

test('search, role and status filters work on the users list', function () {
    User::factory()->create(['name' => 'Alice Teacher', 'email' => 'alice@x.com', 'role' => 'teacher']);
    User::factory()->create(['name' => 'Bob Accountant', 'email' => 'bob@x.com', 'role' => 'accountant']);
    User::factory()->create(['name' => 'Carl Gone', 'email' => 'carl@x.com', 'role' => 'teacher', 'is_active' => false]);

    $this->get('/users?q=Alice')->assertSee('Alice Teacher')->assertDontSee('Bob Accountant');
    $this->get('/users?q=bob@x')->assertSee('Bob Accountant')->assertDontSee('Alice Teacher');
    $this->get('/users?role=accountant')->assertSee('Bob Accountant')->assertDontSee('Alice Teacher');
    $this->get('/users?status=inactive')->assertSee('Carl Gone')->assertDontSee('Alice Teacher');
    $this->get('/users?status=active')->assertSee('Alice Teacher')->assertDontSee('Carl Gone');
    $this->get('/users?q=zzz')->assertSee('koi user nahi mila');
});

test('the users list is paginated and marks the current user', function () {
    foreach (range(1, 12) as $i) {
        User::factory()->create(['name' => sprintf('Person %02d', $i)]);
    }
    $this->get('/users')->assertOk()->assertSee('You');
    $this->get('/users?page=2')->assertOk();
});

test('the sidebar shows Users to admin only', function () {
    $this->get('/dashboard')->assertSee('>Users<', false);
    $this->actingAs(User::factory()->create(['role' => 'accountant']))->get('/dashboard')->assertDontSee('>Users<', false);
});
