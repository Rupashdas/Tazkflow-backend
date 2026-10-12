<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;

it('registers an account, signs the user in and sends a verification email', function () {
    Notification::fake();

    $this->postJson('/api/register', [
        'name'                  => 'Rupa',
        'email'                 => 'Rupa@Example.com',
        'password'              => 'correct-horse-9',
        'password_confirmation' => 'correct-horse-9',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'rupa@example.com')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonPath('data.preferences.timezone', 'UTC');

    $user = User::sole();
    Notification::assertSentTo($user, QueuedVerifyEmail::class);

    $this->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('rejects a taken email, a short password and a mismatched confirmation', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/register', [
        'name'                  => 'X',
        'email'                 => 'taken@example.com',
        'password'              => 'short',
        'password_confirmation' => 'different',
    ])->assertJsonValidationErrors(['email', 'password']);
});

it('logs in with the right password and gives one answer for any wrong one', function () {
    $user = User::factory()->create(['email' => 'a@example.com', 'password' => 'secret-pass-1']);
    $same = 'These credentials do not match our records.';

    // 401 with no field errors: the failure belongs to the email and password
    // together, so the form shows it once, not under either field.
    $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'wrong'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'invalid_credentials')
        ->assertJsonPath('message', $same)
        ->assertJsonMissingPath('errors');

    // An unknown address gets the identical answer, so the form never
    // confirms which emails have accounts.
    $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'invalid_credentials')
        ->assertJsonPath('message', $same)
        ->assertJsonMissingPath('errors');

    $this->postJson('/api/login', ['email' => 'A@Example.com', 'password' => 'secret-pass-1'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('refuses a deactivated account at login and on every request', function () {
    $user = User::factory()->inactive()->create(['password' => 'secret-pass-1']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
        ->assertForbidden()
        ->assertJsonPath('code', 'account_deactivated');

    $this->actingAs($user)->getJson('/api/user')
        ->assertForbidden()
        ->assertJsonPath('code', 'account_deactivated');
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/logout')->assertNoContent();

    $this->assertGuest('web');
});

it('turns guests away', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});
