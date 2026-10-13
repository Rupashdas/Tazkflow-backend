<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

function verificationLink(User $user, $expires = null, ?string $hash = null): string {
    return URL::temporarySignedRoute('verification.verify', $expires ?? now()->addHour(), [
        'id'   => $user->id,
        'hash' => $hash ?? sha1($user->getEmailForVerification()),
    ]);
}

function emailVerifiedScreen(string $query = ''): string {
    return rtrim(config('app.frontend_url'), '/') . '/email-verified' . $query;
}

it('confirms the address and sends the person to the frontend', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationLink($user))->assertRedirect(emailVerifiedScreen());

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('sends an expired link to the frontend instead of a JSON 403', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationLink($user, now()->subMinute()))
        ->assertRedirect(emailVerifiedScreen('?error=link_invalid'));

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('sends a link with an edited hash to the frontend', function () {
    $user = User::factory()->unverified()->create();
    $tampered = str_replace(sha1($user->email), sha1('someone@else.com'), verificationLink($user));

    $this->get($tampered)->assertRedirect(emailVerifiedScreen('?error=link_invalid'));
});

it('sends a signed link for an older address to the frontend', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationLink($user, hash: sha1('old@example.com')))
        ->assertRedirect(emailVerifiedScreen('?error=link_invalid'));
});

it('sends a link for a deleted account to the frontend', function () {
    $user = User::factory()->unverified()->create();
    $link = verificationLink($user);
    $user->delete();

    $this->get($link)->assertRedirect(emailVerifiedScreen('?error=link_invalid'));
});

it('switches a pending change to the new address and says so', function () {
    $user = User::factory()->create(['email' => 'old@example.com', 'pending_email' => 'new@example.com']);

    // verificationLink hashes getEmailForVerification(), which is the pending address.
    $this->get(verificationLink($user))->assertRedirect(emailVerifiedScreen('?changed=1'));

    expect($user->fresh())
        ->email->toBe('new@example.com')
        ->pending_email->toBeNull();
});
