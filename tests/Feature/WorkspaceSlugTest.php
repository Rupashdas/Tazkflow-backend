<?php

use App\Models\User;

// Top-level paths of the frontend. A workspace with one of these slugs could never be opened.
dataset('frontend paths', [
    ['Welcome', 'welcome'],
    ['Workspaces', 'workspaces'],
    ['Verify email', 'verify-email'],
    ['Email verified', 'email-verified'],
    ['Forgot password', 'forgot-password'],
    ['Reset password', 'reset-password'],
]);

it('never derives a slug that is a frontend path', function (string $name, string $path) {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/workspaces', ['name' => $name])
        ->assertCreated()
        ->assertJsonPath('data.slug', "{$path}-2");
})->with('frontend paths');

it('rejects a typed slug that is a frontend path', function (string $name, string $path) {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/workspaces', ['name' => $name, 'slug' => $path])
        ->assertJsonValidationErrors(['slug']);
})->with('frontend paths');
