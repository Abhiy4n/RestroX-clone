<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GoogleAuthOutcome;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Connects a Google account to a user who is already logged in.
 */
class LinkGoogleAccount
{
    /**
     * Link the Google account to the user, after checking it's safe to do so.
     *
     * $user is null when the user's session expired while they were on Google's page.
     * Each check below returns early, so nothing is saved unless every check passes.
     */
    public function handle(?User $user, string $providerUserId, string $email): GoogleAuthOutcome
    {
        if ($user === null) {
            return GoogleAuthOutcome::LoginRequired;
        }

        if (! $user->is_active) {
            return GoogleAuthOutcome::Failed;
        }

        // The Google email belongs to a different user.
        if (User::query()->where('email', $email)->whereKeyNot($user->getKey())->exists()) {
            return GoogleAuthOutcome::EmailInUse;
        }

        $socialAccount = SocialAccount::query()
            ->where('provider', 'google')
            ->where('provider_user_id', $providerUserId)
            ->first();

        // This Google account is already linked to someone else.
        if ($socialAccount !== null && $socialAccount->user_id !== $user->getKey()) {
            return GoogleAuthOutcome::AccountAlreadyLinked;
        }

        $existingProviderLink = $user->socialAccounts()->where('provider', 'google')->first();

        // This user already has a different Google account linked.
        if ($existingProviderLink !== null && $existingProviderLink->provider_user_id !== $providerUserId) {
            return GoogleAuthOutcome::ProviderAlreadyLinked;
        }

        if ($socialAccount === null) {
            try {
                $user->socialAccounts()->create([
                    'provider' => 'google',
                    'provider_user_id' => $providerUserId,
                ]);
            } catch (QueryException $exception) {
                report($exception);

                return GoogleAuthOutcome::AccountAlreadyLinked;
            }
        }

        // Google has confirmed this email, so if it matches the account's email, mark it verified.
        if ($user->email_verified_at === null && $user->email === $email) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Log::info('Google account linked.', ['user_id' => $user->getKey()]);

        return GoogleAuthOutcome::Linked;
    }
}
