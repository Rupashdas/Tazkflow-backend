<?php

use App\Models\User;

it('accepts the older timezone names browsers still list', function (string $zone) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/preferences', ['timezone' => $zone])
        ->assertOk()
        ->assertJsonPath('data.timezone', $zone);
})->with(['Asia/Calcutta', 'Asia/Katmandu', 'Europe/Kiev', 'Asia/Dhaka', 'UTC']);

it('refuses a timezone that does not exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/preferences', ['timezone' => 'Mars/Olympus'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('timezone');
});

it('gives a new account an accent the app knows', function () {
    $user = User::factory()->create();

    expect($user->preference->fresh()->palette)->toBe('blue');
});

it('refuses an accent the app does not have', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/preferences', ['palette' => 'sakura'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('palette');
});
