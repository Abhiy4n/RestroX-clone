<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GoogleAuthOutcome;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Logs a user in with their Google account, creating a new account if needed.
 */
class SignInWithGoogle
{
    public function __construct(
        private readonly ProvisionWallet $provisionWallet,
    ) {}

    /**
     * Log in the user who owns this Google account, or create a new user for it.
     *
     * If someone already signed up with this email using a password, we don't log in
     * automatically. That would let anyone with a Google account for the email take over
     * the account. The owner has to log in normally and link Google from their settings.
     */
    public function handle(string $providerUserId, string $email, ?string $name): GoogleAuthOutcome
    {
        $socialAccount = SocialAccount::query()
            ->with('user')
            ->where('provider', 'google')
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($socialAccount !== null) {
            return $this->signInLinkedUser($socialAccount->user);
        }

        if (User::query()->where('email', $email)->exists()) {
            return GoogleAuthOutcome::LinkRequired;
        }

        // The transaction makes sure the user and their Google link are saved together.
        // If either insert fails, neither is saved.
        try {
            $user = DB::transaction(function () use ($providerUserId, $email, $name): ?User {
                $user = User::query()->firstOrCreate(
                    ['email' => $email],
                    ['name' => $name ?: Str::before($email, '@')],
                );

                // Another request may have created this email a moment ago. Treat it like
                // an existing account rather than linking Google to it automatically.
                if (! $user->wasRecentlyCreated) {
                    return null;
                }

                // Google has already confirmed the user owns this email.
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->socialAccounts()->create([
                    'provider' => 'google',
                    'provider_user_id' => $providerUserId,
                ]);

                $this->provisionWallet->handle($user);

                return $user;
            });
        } catch (QueryException $exception) {
            report($exception);

            return GoogleAuthOutcome::Failed;
        }

        if ($user === null) {
            return GoogleAuthOutcome::LinkRequired;
        }

        Auth::guard('web')->login($user);
        Log::info('User registered and authenticated with Google.', ['user_id' => $user->getKey()]);

        return GoogleAuthOutcome::Success;
    }

    /**
     * Log in a user who already has this Google account linked, unless they are deactivated.
     */
    private function signInLinkedUser(User $user): GoogleAuthOutcome
    {
        if (! $user->is_active) {
            Log::notice('Disabled account rejected during Google sign-in.', ['user_id' => $user->getKey()]);

            return GoogleAuthOutcome::Failed;
        }

        Auth::guard('web')->login($user);
        Log::info('User authenticated with Google.', ['user_id' => $user->getKey()]);

        return GoogleAuthOutcome::Success;
    }
}
