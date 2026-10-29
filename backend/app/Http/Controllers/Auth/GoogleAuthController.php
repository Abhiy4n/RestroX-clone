<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\LinkGoogleAccount;
use App\Actions\SignInWithGoogle;
use App\Enums\GoogleAuthOutcome;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Handles "Continue with Google" and linking Google to an existing account.
 *
 * Flow: redirect() or link() sends the user to Google. Google sends them back to callback(),
 * which finishes the job and redirects to the frontend with ?oauth=<result>.
 */
class GoogleAuthController extends Controller
{
    /**
     * Send the user to Google's sign-in page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put('google_oauth_remember', $request->boolean('remember'));

        return Socialite::driver('google')->redirect();
    }

    /**
     * Send a logged-in user to Google so they can link their Google account.
     *
     * We remember in the session that this is a "link" request, so callback() knows what to do.
     */
    public function link(Request $request): RedirectResponse
    {
        $request->session()->put('google_oauth_intent', 'link');

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the user coming back from Google.
     *
     * Gets the Google profile, checks that Google has verified the email, then either signs
     * the user in or links Google to their account.
     */
    public function callback(
        Request $request,
        SignInWithGoogle $signInWithGoogle,
        LinkGoogleAccount $linkGoogleAccount,
    ): RedirectResponse {
        $intent = $request->session()->pull('google_oauth_intent', 'signin');
        $remember = $request->session()->pull('google_oauth_remember', false);

        // This throws if the user clicked "Cancel" on Google's page or the request was tampered with.
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->frontendRedirect(GoogleAuthOutcome::Failed);
        }

        $providerUserId = (string) $googleUser->getId();
        $email = mb_strtolower((string) $googleUser->getEmail());
        $googleEmailVerified = $googleUser->getRaw()['email_verified'] ?? false;

        if ($providerUserId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! in_array($googleEmailVerified, [true, 1, '1', 'true'], true)) {
            return $this->frontendRedirect(GoogleAuthOutcome::UnverifiedEmail);
        }

        $outcome = $intent === 'link'
            ? $linkGoogleAccount->handle($request->user(), $providerUserId, $email)
            : $signInWithGoogle->handle($providerUserId, $email, $googleUser->getName(), $remember);

        // Same reason as LoginController: a fresh session ID after logging in.
        if (in_array($outcome, [GoogleAuthOutcome::Success, GoogleAuthOutcome::Linked], true)) {
            $request->session()->regenerate();
        }

        return $this->frontendRedirect($outcome);
    }

    /**
     * Send the user back to the frontend login page with the result, e.g. /login?oauth=success.
     */
    private function frontendRedirect(GoogleAuthOutcome $outcome): RedirectResponse
    {
        return redirect()->away(rtrim((string) config('services.frontend_url'), '/').'/login?oauth='.$outcome->value);
    }
}
