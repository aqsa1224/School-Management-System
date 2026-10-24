<?php

use App\Models\User;

// Public registration jaan-boojh ke band hai: naye users sirf Admin banata hai (Users menu).

test('the registration page does not exist', function () {
    $this->get('/register')->assertNotFound();
});

test('nobody can register through a direct request', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('the login page has no register link', function () {
    $this->get('/login')->assertOk()->assertDontSee('/register', false);
});
