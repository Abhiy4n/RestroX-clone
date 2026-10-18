<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limits stop attackers from trying passwords or signing up in bulk.
        // Routes use them by name, e.g. ->middleware('throttle:login').

        // Sign-up: 5 attempts per minute per IP address.
        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Login: 5 attempts per minute for one email from one IP, and 30 per minute per IP
        // overall (stops one IP trying many different emails).
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::transliterate(Str::lower($request->string('email')->toString()));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        // Forgot/reset password: 3 attempts per minute per email and IP, so nobody can flood
        // someone's inbox with reset emails.
        RateLimiter::for('password-recovery', function (Request $request): Limit {
            return Limit::perMinute(3)->by(Str::lower($request->string('email')->toString()).'|'.$request->ip());
        });

        // Point the link in the reset email at the React frontend instead of a backend page.
        ResetPassword::createUrlUsing(function ($user, string $token): string {
            return rtrim((string) config('services.frontend_url'), '/').'/'.'?'.http_build_query([
                'reset_token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }
}
