<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function link()
    {
        request()->session()->put('google_oauth_intent', 'link');

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        $intent = $request->session()->pull('google_oauth_intent', 'signin');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->frontendRedirect('oauth=failed');
        }

        $providerUserId = $googleUser->getId();
        $email = mb_strtolower((string) $googleUser->getEmail());
        $googleEmailVerified = $googleUser->getRaw()['email_verified'] ?? false;

        if (! $providerUserId || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! in_array($googleEmailVerified, [true, 1, '1', 'true'], true)) {
            return $this->frontendRedirect('oauth=unverified_email');
        }

        if ($intent === 'link') {
            return $this->linkAccount($request, $providerUserId, $email);
        }

        $socialAccount = SocialAccount::query()
            ->with('user')
            ->where('provider', 'google')
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($socialAccount) {
            Auth::guard('web')->login($socialAccount->user);
            $request->session()->regenerate();

            return $this->frontendRedirect('oauth=success');
        }

        if (User::query()->where('email', $email)->exists()) {
            return $this->frontendRedirect('oauth=link_required');
        }

        try {
            $user = DB::transaction(function () use ($email, $googleUser, $providerUserId): ?User {
                $user = User::query()->firstOrCreate(
                    ['email' => $email],
                    ['name' => $googleUser->getName() ?: Str::before($email, '@')],
                );

                if (! $user->wasRecentlyCreated) {
                    return null;
                }

                $user->forceFill(['email_verified_at' => now()])->save();
                $user->socialAccounts()->create([
                    'provider' => 'google',
                    'provider_user_id' => $providerUserId,
                ]);

                return $user;
            });
        } catch (QueryException $exception) {
            report($exception);

            return $this->frontendRedirect('oauth=failed');
        }

        if (! $user) {
            return $this->frontendRedirect('oauth=link_required');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->frontendRedirect('oauth=success');
    }

    private function linkAccount(Request $request, string $providerUserId, string $email)
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return $this->frontendRedirect('oauth=login_required');
        }

        if (User::query()
            ->where('email', $email)
            ->whereKeyNot($user->getKey())
            ->exists()) {
            return $this->frontendRedirect('oauth=email_in_use');
        }

        $socialAccount = SocialAccount::query()
            ->where('provider', 'google')
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($socialAccount && $socialAccount->user_id !== $user->getKey()) {
            return $this->frontendRedirect('oauth=account_already_linked');
        }

        $existingProviderLink = $user->socialAccounts()
            ->where('provider', 'google')
            ->first();

        if ($existingProviderLink && $existingProviderLink->provider_user_id !== $providerUserId) {
            return $this->frontendRedirect('oauth=provider_already_linked');
        }

        try {
            if (! $socialAccount) {
                $user->socialAccounts()->create([
                    'provider' => 'google',
                    'provider_user_id' => $providerUserId,
                ]);
            }
        } catch (QueryException $exception) {
            report($exception);

            return $this->frontendRedirect('oauth=account_already_linked');
        }

        if (! $user->email_verified_at && $user->email === $email) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $request->session()->regenerate();

        return $this->frontendRedirect('oauth=linked');
    }

    private function frontendRedirect(string $query)
    {
        return redirect()->away(rtrim(config('services.frontend_url'), '/').'/login?'.$query);
    }
}
