<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller {
    /**
     * The link in the verification email.
     *
     * The signature means the id and hash cannot be forged or edited. It does
     * not require a session: people often open email on a different device
     * from the one they signed up on.
     *
     * The person arrived from their inbox, not from the app, so every failure
     * goes to a frontend screen that can explain it, never to a JSON error.
     */
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse {
        $to = rtrim(config('app.frontend_url'), '/') . '/email-verified';
        $user = User::find($id);

        // Expired, edited, for an account that is gone, or for an address the
        // account no longer has: all the same to the person — ask for a new link.
        if (! $request->hasValidSignature() || ! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->away($to . '?error=link_invalid');
        }

        if ($user->pending_email) {
            // Someone may have signed up with it while the link waited in the inbox.
            if (User::where('email', $user->pending_email)->exists()) {
                $user->forceFill(['pending_email' => null])->save();

                return redirect()->away($to . '?error=email_taken');
            }

            $user->forceFill(['email' => $user->pending_email, 'pending_email' => null, 'email_verified_at' => now()])->save();

            // Its own screen: "confirmed" alone would not tell them the address changed.
            return redirect()->away($to . '?changed=1');
        } elseif (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->away($to);
    }
}
