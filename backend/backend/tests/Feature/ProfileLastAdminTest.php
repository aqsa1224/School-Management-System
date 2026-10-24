<?php

use App\Models\User;

test('the only active admin cannot delete their own account from the profile page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->delete('/profile', ['password' => 'password'])
        ->assertSessionHasErrorsIn('userDeletion', 'password');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('an admin can delete their own account when another active admin exists', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)
        ->delete('/profile', ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertDatabaseMissing('users', ['id' => $admin->id]);
});

test('an inactive second admin does not count as a replacement', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => false]);

    $this->actingAs($admin)
        ->delete('/profile', ['password' => 'password'])
        ->assertSessionHasErrorsIn('userDeletion', 'password');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});
