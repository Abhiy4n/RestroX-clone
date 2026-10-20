<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_redirect_remembers_the_link_intent(): void
    {
        Socialite::fake('google');

        $this->actingAs(User::factory()->create())
            ->get('/auth/google/link')
            ->assertRedirect('https://socialite.fake/google/authorize')
            ->assertSessionHas('google_oauth_intent', 'link');
    }

    public function test_new_google_user_is_registered_verified_and_signed_in(): void
    {
        $this->fakeGoogleUser(email: 'Diner@Example.com');

        $this->get('/auth/google/callback')->assertRedirect($this->frontendUrl('success'));

        $user = User::query()->where('email', 'diner@example.com')->firstOrFail();
        $this->assertSame('Google Diner', $user->name);
        $this->assertSame(User::ROLE_DEVELOPER, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->wallet);
        $this->assertDatabaseHas('ledger_accounts', [
            'wallet_id' => $user->wallet->id,
            'code' => 'wallet:'.$user->wallet->id,
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
        ]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_linked_google_user_is_signed_in(): void
    {
        $user = User::factory()->create();
        $this->linkGoogle($user);
        $this->fakeGoogleUser(email: $user->email);

        $this->get('/auth/google/callback')->assertRedirect($this->frontendUrl('success'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_disabled_account_is_rejected_during_google_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->linkGoogle($user);
        $this->fakeGoogleUser(email: $user->email);

        $this->get('/auth/google/callback')->assertRedirect($this->frontendUrl('failed'));

        $this->assertGuest();
    }

    public function test_existing_email_without_a_link_requires_explicit_linking(): void
    {
        $user = User::factory()->create(['email' => 'diner@example.com']);
        $this->fakeGoogleUser(email: 'diner@example.com');

        $this->get('/auth/google/callback')->assertRedirect($this->frontendUrl('link_required'));

        $this->assertGuest();
        $this->assertSame(0, $user->socialAccounts()->count());
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->fakeGoogleUser(emailVerified: false);

        $this->get('/auth/google/callback')->assertRedirect($this->frontendUrl('unverified_email'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_provider_failure_redirects_with_failed_outcome(): void
    {
        Socialite::fake('google', fn () => throw new RuntimeException('Access denied.'));

        $this->get('/auth/google/callback')->assertRedirect($this->frontendUrl('failed'));

        $this->assertGuest();
    }

    public function test_authenticated_user_can_link_google_and_matching_email_is_verified(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'diner@example.com']);
        $this->fakeGoogleUser(email: 'diner@example.com');

        $this->actingAs($user)
            ->withSession(['google_oauth_intent' => 'link'])
            ->get('/auth/google/callback')
            ->assertRedirect($this->frontendUrl('linked'));

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
        ]);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_linking_after_the_session_expired_requires_login(): void
    {
        $this->fakeGoogleUser();

        $this->withSession(['google_oauth_intent' => 'link'])
            ->get('/auth/google/callback')
            ->assertRedirect($this->frontendUrl('login_required'));

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_google_account_linked_to_another_user_cannot_be_linked_again(): void
    {
        $owner = User::factory()->create();
        $this->linkGoogle($owner);
        $user = User::factory()->create();
        $this->fakeGoogleUser(email: $user->email);

        $this->actingAs($user)
            ->withSession(['google_oauth_intent' => 'link'])
            ->get('/auth/google/callback')
            ->assertRedirect($this->frontendUrl('account_already_linked'));

        $this->assertSame(0, $user->socialAccounts()->count());
    }

    public function test_google_email_owned_by_another_user_cannot_be_linked(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();
        $this->fakeGoogleUser(email: 'taken@example.com');

        $this->actingAs($user)
            ->withSession(['google_oauth_intent' => 'link'])
            ->get('/auth/google/callback')
            ->assertRedirect($this->frontendUrl('email_in_use'));

        $this->assertSame(0, $user->socialAccounts()->count());
    }

    public function test_user_with_a_different_google_account_linked_cannot_link_another(): void
    {
        $user = User::factory()->create();
        $this->linkGoogle($user, 'google-original');
        $this->fakeGoogleUser(id: 'google-other', email: $user->email);

        $this->actingAs($user)
            ->withSession(['google_oauth_intent' => 'link'])
            ->get('/auth/google/callback')
            ->assertRedirect($this->frontendUrl('provider_already_linked'));

        $this->assertSame(1, $user->socialAccounts()->count());
    }

    private function fakeGoogleUser(
        string $id = 'google-123',
        string $email = 'diner@example.com',
        bool $emailVerified = true,
    ): void {
        $googleUser = (new SocialiteUser)
            ->map(['id' => $id, 'name' => 'Google Diner', 'email' => $email])
            ->setRaw(['email_verified' => $emailVerified]);

        Socialite::fake('google', $googleUser);
    }

    private function linkGoogle(User $user, string $providerUserId = 'google-123'): SocialAccount
    {
        return $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => $providerUserId,
        ]);
    }

    private function frontendUrl(string $outcome): string
    {
        return rtrim((string) config('services.frontend_url'), '/').'/login?oauth='.$outcome;
    }
}
