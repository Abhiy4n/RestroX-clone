<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_normalizes_values_and_sends_verification_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Restro User',
            'email' => '  USER@EXAMPLE.COM ',
            'phone_country_iso2' => 'np',
            'phone_e164' => '+977 981-234-5678',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'user@example.com')
            ->assertJsonPath('user.phone_country_iso2', 'NP')
            ->assertJsonPath('user.phone_e164', '+9779812345678')
            ->assertJsonMissingPath('user.password');

        $user = User::query()->where('email', 'user@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_DEVELOPER, $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->wallet);
        $this->assertDatabaseHas('ledger_accounts', [
            'wallet_id' => $user->wallet->id,
            'code' => 'wallet:'.$user->wallet->id,
        ]);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_rejects_weak_password_and_duplicate_phone(): void
    {
        $user = User::factory()->create(['phone_e164' => '+9779812345678']);

        $this->postJson('/api/register', [
            'name' => 'First User',
            'email' => 'first@example.com',
            'phone_country_iso2' => 'NP',
            'phone_e164' => '+9779812345678',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password', 'phone_e164']);

        $this->assertModelExists($user);
    }

    public function test_signed_email_verification_enables_login(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $response = $this->getJson($url)->assertOk();
        $this->assertNotNull($response->json('user.email_verified_at'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_rejects_bad_credentials_unverified_and_inactive_accounts(): void
    {
        $verifiedUser = User::factory()->create(['password' => 'StrongPass123']);
        $unverifiedUser = User::factory()->unverified()->create(['password' => 'StrongPass123']);
        $inactiveUser = User::factory()->create(['password' => 'StrongPass123', 'is_active' => false]);

        $this->postJson('/api/login', ['email' => $verifiedUser->email, 'password' => 'WrongPass123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
        $this->postJson('/api/login', ['email' => $unverifiedUser->email, 'password' => 'StrongPass123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
        $this->postJson('/api/login', ['email' => $inactiveUser->email, 'password' => 'StrongPass123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_returns_an_allowlisted_user_resource_and_logout_invalidates_session(): void
    {
        $user = User::factory()->create(['password' => 'StrongPass123']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', ['email' => mb_strtoupper($user->email), 'password' => 'StrongPass123'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.is_active');

        $this->postJson('/api/logout')->assertOk()->assertJsonPath('user', null);
        Auth::forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_password_reset_request_uses_an_enumeration_safe_response(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $response->assertAccepted();
        Notification::assertSentTo($user, ResetPassword::class);

        $this->postJson('/api/forgot-password', ['email' => 'missing@example.com'])
            ->assertAccepted()
            ->assertJsonPath('message', $response->json('message'));
    }

    public function test_valid_password_reset_token_updates_the_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ChangedPass123',
            'password_confirmation' => 'ChangedPass123',
        ])->assertOk();

        $this->assertTrue(Hash::check('ChangedPass123', $user->fresh()->password));
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'WrongPass123']);
        }

        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'WrongPass123'])
            ->assertTooManyRequests();
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/register', []);
        }

        $this->postJson('/api/register', [])->assertTooManyRequests();
    }
}
