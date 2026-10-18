<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Checks an email and password, then logs the user in.
 */
class AuthenticateUser
{
    /**
     * Log the user in, or throw a validation error if they aren't allowed to.
     *
     * Login is refused when the email doesn't exist, the password is wrong, the account
     * has no password (Google-only accounts), the account is deactivated, or the email
     * isn't verified yet.
     */
    public function handle(string $email, string $password, bool $remember = false): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password) || ! $user->is_active || ! $user->hasVerifiedEmail()) {
            Log::notice('Authentication attempt rejected.', [
                'email_hash' => hash('sha256', $email),
                'user_id' => $user?->getKey(),
                'ip' => request()->ip(),
            ]);

            // Always show the same message, so attackers can't tell which emails have accounts.
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        Auth::guard('web')->login($user, $remember);
        Log::info('User authenticated.', ['user_id' => $user->getKey()]);

        return $user;
    }
}
