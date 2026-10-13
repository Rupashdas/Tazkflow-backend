<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

it('cancels a pending email change', function () {
    $user = User::factory()->create(['pending_email' => 'new@example.com']);

    // Successful, not Ok: a user made in this same test counts as "recently
    // created", and a JsonResource answers 201 for that.
    $this->actingAs($user)
        ->deleteJson('/api/user/pending-email')
        ->assertSuccessful()
        ->assertJsonPath('data.pending_email', null)
        ->assertJsonPath('data.email', $user->email);

    expect($user->fresh()->pending_email)->toBeNull();
});

it('stops the link already sent to the new address from working', function () {
    $user = User::factory()->create(['pending_email' => 'new@example.com']);
    $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id'   => $user->id,
        'hash' => sha1('new@example.com'),
    ]);

    $this->actingAs($user)->deleteJson('/api/user/pending-email')->assertSuccessful();

    $this->get($link)->assertRedirect(rtrim(config('app.frontend_url'), '/') . '/email-verified?error=link_invalid');
    expect($user->fresh()->email)->not->toBe('new@example.com');
});

it('answers the same when nothing is pending', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson('/api/user/pending-email')
        ->assertSuccessful()
        ->assertJsonPath('data.pending_email', null);
});

it('turns guests away', function () {
    $this->deleteJson('/api/user/pending-email')->assertUnauthorized();
});
