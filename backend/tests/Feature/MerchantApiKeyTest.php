<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_management_endpoints_return_401_without_user_authentication(): void
    {
        $merchant = Merchant::factory()->create();
        $apiKey = MerchantApiKey::factory()->for($merchant)->create();

        $this->getJson("/api/merchants/{$merchant->code}/api-keys")->assertUnauthorized();
        $this->postJson("/api/merchants/{$merchant->code}/api-keys", [])->assertUnauthorized();
        $this->deleteJson("/api/merchants/{$merchant->code}/api-keys/{$apiKey->key_id}")->assertUnauthorized();
    }

    public function test_owner_can_generate_a_hashed_secret_that_is_returned_only_once(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/merchants/{$merchant->code}/api-keys", [
            'name' => 'HMS Development',
            'expires_at' => now()->addMonth()->toISOString(),
        ]);

        $response->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertJsonPath('api_key.merchant_id', $merchant->code)
            ->assertJsonPath('api_key.name', 'HMS Development')
            ->assertJsonPath('api_key.status', 'active');
        $secret = $response->json('secret');
        $this->assertMatchesRegularExpression('/^rsk_test_[A-Za-z0-9]{48}$/', $secret);
        $this->assertDatabaseHas('merchant_api_keys', [
            'merchant_id' => $merchant->id,
            'name' => 'HMS Development',
            'secret_hash' => hash('sha256', $secret),
        ]);
        $this->assertDatabaseMissing('merchant_api_keys', ['secret_hash' => $secret]);

        $this->actingAs($user)->getJson("/api/merchants/{$merchant->code}/api-keys")
            ->assertOk()
            ->assertJsonCount(1, 'api_keys')
            ->assertJsonPath('api_keys.0.id', $response->json('api_key.id'))
            ->assertJsonMissingPath('api_keys.0.secret')
            ->assertJsonMissingPath('api_keys.0.secret_hash');
    }

    public function test_key_creation_rejects_invalid_name_and_expiration(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user)->create();

        $this->actingAs($user)->postJson("/api/merchants/{$merchant->code}/api-keys", [
            'name' => '',
            'expires_at' => now()->subMinute()->toISOString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'expires_at']);

        $this->assertDatabaseCount('merchant_api_keys', 0);
    }

    public function test_disabled_merchant_cannot_generate_a_key(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->disabled()->for($user)->create();

        $this->actingAs($user)->postJson("/api/merchants/{$merchant->code}/api-keys", [
            'name' => 'Disabled merchant key',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['merchant_id']);

        $this->assertDatabaseCount('merchant_api_keys', 0);
    }

    public function test_global_key_endpoints_generate_and_list_only_owned_credentials(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user)->create(['name' => 'Owned Merchant']);
        $otherKey = MerchantApiKey::factory()->create(['name' => 'Other User Key']);

        $created = $this->actingAs($user)->postJson('/api/api-keys', [
            'merchant_id' => $merchant->code,
            'name' => 'Automation',
        ])->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($user)->getJson('/api/api-keys?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'api_keys')
            ->assertJsonPath('api_keys.0.id', $created->json('api_key.id'))
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.active', 1)
            ->assertJsonPath('summary.inactive', 0)
            ->assertJsonMissing(['id' => $otherKey->key_id]);
    }

    public function test_other_users_receive_404_for_key_management(): void
    {
        $merchant = Merchant::factory()->create();
        $apiKey = MerchantApiKey::factory()->for($merchant)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->getJson("/api/merchants/{$merchant->code}/api-keys")
            ->assertNotFound();
        $this->actingAs($otherUser)
            ->postJson("/api/merchants/{$merchant->code}/api-keys", ['name' => 'Unauthorized'])
            ->assertNotFound();
        $this->actingAs($otherUser)
            ->deleteJson("/api/merchants/{$merchant->code}/api-keys/{$apiKey->key_id}")
            ->assertNotFound();

        $this->assertNull($apiKey->refresh()->revoked_at);
    }

    public function test_valid_key_authenticates_merchant_and_updates_last_used_time(): void
    {
        $merchant = Merchant::factory()->create(['name' => 'Hotel Booking System']);
        $secret = 'rsk_test_validCredentialForMerchantIdentity123456789012';
        $apiKey = $this->createKey($merchant, $secret);
        $this->travelTo('2026-10-21 12:00:00');

        $this->withToken($secret)->getJson('/api/v1/merchant')
            ->assertOk()
            ->assertJsonPath('merchant.merchant_id', $merchant->code)
            ->assertJsonPath('merchant.name', 'Hotel Booking System');

        $this->assertSame(now()->toISOString(), $apiKey->refresh()->last_used_at?->toISOString());
    }

    public function test_revoked_key_stops_authenticating_immediately(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user)->create();
        $secret = 'rsk_test_revokableCredential123456789012345678901234';
        $apiKey = $this->createKey($merchant, $secret);

        $this->actingAs($user)
            ->deleteJson("/api/merchants/{$merchant->code}/api-keys/{$apiKey->key_id}")
            ->assertNoContent();

        $this->assertNotNull($apiKey->refresh()->revoked_at);
        $this->withToken($secret)->getJson('/api/v1/merchant')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid API key.');

        $this->actingAs($user)
            ->patchJson("/api/merchants/{$merchant->code}", ['status' => Merchant::STATUS_DISABLED])
            ->assertOk();
        $this->actingAs($user)
            ->patchJson("/api/merchants/{$merchant->code}", ['status' => Merchant::STATUS_ACTIVE])
            ->assertOk();
        $this->withToken($secret)->getJson('/api/v1/merchant')->assertUnauthorized();
    }

    public function test_reenabled_merchant_accepts_an_otherwise_valid_key(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->disabled()->for($user)->create();
        $secret = 'rsk_test_reenabledCredential12345678901234567890123';
        $this->createKey($merchant, $secret);

        $this->withToken($secret)->getJson('/api/v1/merchant')->assertUnauthorized();

        $this->actingAs($user)->patchJson("/api/merchants/{$merchant->code}", [
            'status' => Merchant::STATUS_ACTIVE,
        ])->assertOk();

        $this->withToken($secret)->getJson('/api/v1/merchant')
            ->assertOk()
            ->assertJsonPath('merchant.merchant_id', $merchant->code);
    }

    public function test_invalid_expired_and_disabled_merchant_keys_return_401(): void
    {
        $expiredMerchant = Merchant::factory()->create();
        $expiredSecret = 'rsk_test_expiredCredential1234567890123456789012345';
        $this->createKey($expiredMerchant, $expiredSecret, ['expires_at' => now()->subMinute()]);
        $disabledMerchant = Merchant::factory()->disabled()->create();
        $disabledSecret = 'rsk_test_disabledCredential123456789012345678901234';
        $this->createKey($disabledMerchant, $disabledSecret);

        $this->withToken('rsk_test_unknown')->getJson('/api/v1/merchant')->assertUnauthorized();
        $this->withToken($expiredSecret)->getJson('/api/v1/merchant')->assertUnauthorized();
        $this->withToken($disabledSecret)->getJson('/api/v1/merchant')->assertUnauthorized();
    }

    public function test_merchant_key_cannot_authenticate_user_wallet_endpoints(): void
    {
        $merchant = Merchant::factory()->create();
        $secret = 'rsk_test_merchantOnlyCredential123456789012345678901';
        $this->createKey($merchant, $secret);

        $this->withToken($secret)->getJson('/api/wallet')->assertUnauthorized();
    }

    public function test_key_from_another_merchant_cannot_be_revoked_through_nested_route(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user)->create();
        $otherMerchant = Merchant::factory()->for($user)->create();
        $apiKey = MerchantApiKey::factory()->for($otherMerchant)->create();

        $this->actingAs($user)
            ->deleteJson("/api/merchants/{$merchant->code}/api-keys/{$apiKey->key_id}")
            ->assertNotFound();

        $this->assertNull($apiKey->refresh()->revoked_at);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createKey(Merchant $merchant, string $secret, array $attributes = []): MerchantApiKey
    {
        return MerchantApiKey::factory()->for($merchant)->create([
            'secret_hash' => hash('sha256', $secret),
            'secret_hint' => substr($secret, 0, 13).'…'.substr($secret, -4),
            ...$attributes,
        ]);
    }
}
