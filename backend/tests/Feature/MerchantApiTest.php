<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_endpoints_return_401_without_authentication(): void
    {
        $merchant = Merchant::factory()->create();

        $this->getJson('/api/merchants')->assertUnauthorized();
        $this->postJson('/api/merchants', [])->assertUnauthorized();
        $this->getJson("/api/merchants/{$merchant->code}")->assertUnauthorized();
        $this->patchJson("/api/merchants/{$merchant->code}", [])->assertUnauthorized();
    }

    public function test_active_user_can_create_a_sandbox_merchant_with_a_generated_public_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/merchants', [
            'name' => '  Hotel Booking System  ',
            'website_url' => '  https://hotel.example.com  ',
            'integration_type' => Merchant::INTEGRATION_WEBSITE,
        ]);

        $response->assertCreated()
            ->assertJsonPath('merchant.name', 'Hotel Booking System')
            ->assertJsonPath('merchant.website_url', 'https://hotel.example.com')
            ->assertJsonPath('merchant.integration_type', Merchant::INTEGRATION_WEBSITE)
            ->assertJsonPath('merchant.status', Merchant::STATUS_ACTIVE)
            ->assertJsonPath('merchant.environment', 'sandbox');
        $this->assertMatchesRegularExpression('/^mer_[0-9a-z]{26}$/', $response->json('merchant.merchant_id'));
        $this->assertDatabaseHas('merchants', [
            'user_id' => $user->id,
            'code' => $response->json('merchant.merchant_id'),
            'name' => 'Hotel Booking System',
            'integration_type' => Merchant::INTEGRATION_WEBSITE,
            'status' => Merchant::STATUS_ACTIVE,
            'mode' => 'sandbox',
        ]);
    }

    public function test_create_rejects_missing_or_invalid_merchant_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/merchants', [
            'website_url' => 'javascript:alert(1)',
            'integration_type' => 'desktop',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'website_url', 'integration_type']);

        $this->assertDatabaseCount('merchants', 0);
    }

    public function test_user_lists_only_their_merchants_and_can_open_and_update_one(): void
    {
        $user = User::factory()->create();
        $olderMerchant = Merchant::factory()->for($user)->create(['name' => 'Older']);
        $newerMerchant = Merchant::factory()->for($user)->create(['name' => 'Newer']);
        Merchant::factory()->create(['name' => 'Someone Else']);

        $this->actingAs($user)->getJson('/api/merchants')
            ->assertOk()
            ->assertJsonCount(2, 'merchants')
            ->assertJsonPath('merchants.0.merchant_id', $newerMerchant->code)
            ->assertJsonMissing(['name' => 'Someone Else']);

        $this->actingAs($user)->getJson("/api/merchants/{$olderMerchant->code}")
            ->assertOk()
            ->assertJsonPath('merchant.merchant_id', $olderMerchant->code);

        $this->actingAs($user)->patchJson("/api/merchants/{$olderMerchant->code}", [
            'name' => 'Renamed Merchant',
            'website_url' => null,
            'integration_type' => Merchant::INTEGRATION_MOBILE_APP,
            'status' => Merchant::STATUS_DISABLED,
        ])->assertOk()
            ->assertJsonPath('merchant.name', 'Renamed Merchant')
            ->assertJsonPath('merchant.integration_type', Merchant::INTEGRATION_MOBILE_APP)
            ->assertJsonPath('merchant.status', Merchant::STATUS_DISABLED);

        $this->actingAs($user)->patchJson("/api/merchants/{$olderMerchant->code}", [
            'status' => Merchant::STATUS_ACTIVE,
        ])->assertOk()
            ->assertJsonPath('merchant.status', Merchant::STATUS_ACTIVE);

        $this->assertDatabaseHas('merchants', [
            'id' => $olderMerchant->id,
            'name' => 'Renamed Merchant',
            'website_url' => null,
            'integration_type' => Merchant::INTEGRATION_MOBILE_APP,
            'status' => Merchant::STATUS_ACTIVE,
        ]);
    }

    public function test_merchant_identifiers_are_unique_and_immutable_fields_are_rejected(): void
    {
        $user = User::factory()->create();
        $firstMerchant = Merchant::factory()->for($user)->create();
        $secondMerchant = Merchant::factory()->for($user)->create();

        $this->assertNotSame($firstMerchant->code, $secondMerchant->code);
        $this->actingAs($user)->patchJson("/api/merchants/{$firstMerchant->code}", [
            'user_id' => User::factory()->create()->id,
            'merchant_id' => 'mer_replaced',
            'environment' => 'production',
        ])->assertUnprocessable();

        $firstMerchant->refresh();
        $this->assertSame($user->id, $firstMerchant->user_id);
        $this->assertNotSame('mer_replaced', $firstMerchant->code);
        $this->assertSame('sandbox', $firstMerchant->mode);
    }

    public function test_merchant_list_is_paginated_with_backend_summary_counts(): void
    {
        $user = User::factory()->create();
        Merchant::factory()->count(11)->for($user)->create();
        Merchant::factory()->disabled()->for($user)->create(['name' => 'Disabled Store']);

        $this->actingAs($user)->getJson('/api/merchants?status=disabled')
            ->assertOk()
            ->assertJsonCount(1, 'merchants')
            ->assertJsonPath('summary.total', 12)
            ->assertJsonPath('summary.active', 11)
            ->assertJsonPath('summary.disabled', 1)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_permanent_merchant_delete_endpoint_does_not_exist(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user)->create();

        $this->actingAs($user)
            ->deleteJson("/api/merchants/{$merchant->code}")
            ->assertMethodNotAllowed();

        $this->assertModelExists($merchant);
    }

    public function test_other_users_receive_404_for_a_merchant_they_do_not_own(): void
    {
        $merchant = Merchant::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->getJson("/api/merchants/{$merchant->code}")
            ->assertNotFound();
        $this->actingAs($otherUser)
            ->patchJson("/api/merchants/{$merchant->code}", ['name' => 'Stolen'])
            ->assertNotFound();

        $this->assertSame($merchant->name, $merchant->refresh()->name);
    }

    public function test_inactive_user_cannot_manage_merchants(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $merchant = Merchant::factory()->for($user)->create();

        $this->actingAs($user)->getJson('/api/merchants')->assertForbidden();
        $this->actingAs($user)->postJson('/api/merchants', [
            'name' => 'Blocked',
            'integration_type' => Merchant::INTEGRATION_SERVER_API,
        ])->assertForbidden();
        $this->actingAs($user)->patchJson("/api/merchants/{$merchant->code}", [
            'status' => Merchant::STATUS_DISABLED,
        ])->assertForbidden();
    }
}
