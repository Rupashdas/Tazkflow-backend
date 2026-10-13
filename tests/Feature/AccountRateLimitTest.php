<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('stops guessing the current password when changing it', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $_) {
        $this->actingAs($user)
            ->putJson('/api/user/password', ['current_password' => 'wrong', 'password' => 'NewPass123#', 'password_confirmation' => 'NewPass123#'])
            ->assertUnprocessable();
    }

    $this->actingAs($user)
        ->putJson('/api/user/password', ['current_password' => 'wrong', 'password' => 'NewPass123#', 'password_confirmation' => 'NewPass123#'])
        ->assertTooManyRequests();
});

it('stops guessing the current password through an email change', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $_) {
        $this->actingAs($user)
            ->patchJson('/api/user', ['email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertUnprocessable();
    }

    $this->actingAs($user)
        ->patchJson('/api/user', ['email' => 'new@example.com', 'current_password' => 'wrong'])
        ->assertTooManyRequests();
});

it('does not count a plain profile save', function () {
    $user = User::factory()->create();

    foreach (range(1, 10) as $n) {
        $this->actingAs($user)->patchJson('/api/user', ['name' => "Name {$n}"])->assertSuccessful();
    }
});

it('limits how many verification emails one person can send an hour', function () {
    $user = User::factory()->create(['pending_email' => 'someone@example.com']);

    // Resends spread over the hour, so only the hourly limit can stop them.
    foreach (range(1, 10) as $n) {
        $this->travel(5)->minutes();
        $this->actingAs($user)->postJson('/api/email/verification-notification')->assertAccepted();
    }

    $this->travel(5)->minutes();
    $this->actingAs($user)->postJson('/api/email/verification-notification')->assertTooManyRequests();
});

it('counts email changes and resends against the same hourly limit', function () {
    $user = User::factory()->create();

    // Changing back and forth sends a link to a new inbox every time.
    foreach (range(1, 10) as $n) {
        $this->travel(5)->minutes();
        $this->actingAs($user)
            ->patchJson('/api/user', ['email' => "target{$n}@example.com", 'current_password' => 'password'])
            ->assertSuccessful();
    }

    $this->travel(5)->minutes();
    $this->actingAs($user)->postJson('/api/email/verification-notification')->assertTooManyRequests();
});
