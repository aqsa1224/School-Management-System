<?php

use App\Models\User;

test('an inactive user is logged out and sent to login with a clear message', function () {
    $u = User::factory()->create(['role' => 'admin', 'is_active' => false]);

    $this->actingAs($u)->get('/dashboard')->assertRedirect('/login')
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('band');
    $this->assertGuest();
});

test('an inactive user cannot get into the system by logging in', function () {
    User::factory()->create(['email' => 'gone@school.test', 'password' => 'secret123', 'role' => 'teacher', 'is_active' => false]);

    $this->post('/login', ['email' => 'gone@school.test', 'password' => 'secret123']);
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/students')->assertRedirect('/login');
    $this->assertGuest();
});

test('an account that gets deactivated while logged in is cut off on the next click', function () {
    $u = User::factory()->create(['role' => 'accountant']);
    $this->actingAs($u)->get('/fees')->assertOk();

    // Admin ne account band kar diya
    User::whereKey($u->id)->update(['is_active' => false]);
    $this->actingAs(User::find($u->id))->get('/fees')->assertRedirect('/login');
    $this->assertGuest();
});

test('every protected area checks for an inactive account', function () {
    $u = User::factory()->create(['role' => 'admin', 'is_active' => false]);

    foreach (['/dashboard', '/profile', '/students', '/fees', '/users', '/classes', '/attendances', '/marks'] as $url) {
        $this->actingAs($u)->get($url)->assertRedirect('/login');
    }
});

test('a reactivated user can log in and work again', function () {
    $u = User::factory()->create(['email' => 'back@school.test', 'password' => 'secret123', 'role' => 'accountant', 'is_active' => false]);
    $u->update(['is_active' => true]);

    $this->post('/login', ['email' => 'back@school.test', 'password' => 'secret123'])->assertRedirect('/dashboard');
    $this->get('/fees')->assertOk();
    $this->assertAuthenticatedAs($u);
});
