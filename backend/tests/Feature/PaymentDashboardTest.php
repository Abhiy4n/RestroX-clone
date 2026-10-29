<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_lists_only_payments_for_their_projects(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::factory()->for($user)->create(['name' => 'Owned Project']);
        $succeeded = Payment::factory()->for($merchant)->create([
            'merchant_reference' => 'ORDER-PAID',
            'status' => Payment::STATUS_SUCCEEDED,
            'amount_minor' => 2500,
        ]);
        Payment::factory()->for($merchant)->create(['status' => Payment::STATUS_PENDING]);
        Payment::factory()->create(['merchant_reference' => 'OTHER-ORDER']);

        $this->actingAs($user)->getJson('/api/payments')
            ->assertOk()
            ->assertJsonCount(2, 'payments')
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.succeeded', 1)
            ->assertJsonPath('summary.volume_minor', 2500)
            ->assertJsonPath('merchants.0.merchant_id', $merchant->code)
            ->assertJsonFragment(['payment_id' => $succeeded->public_id])
            ->assertJsonMissing(['merchant_reference' => 'OTHER-ORDER']);
    }

    public function test_user_can_filter_and_search_payments(): void
    {
        $user = User::factory()->create();
        $firstMerchant = Merchant::factory()->for($user)->create(['name' => 'First Project']);
        $secondMerchant = Merchant::factory()->for($user)->create(['name' => 'Second Project']);
        Payment::factory()->for($firstMerchant)->create([
            'merchant_reference' => 'BOOKING-42',
            'status' => Payment::STATUS_FAILED,
        ]);
        Payment::factory()->for($secondMerchant)->create([
            'merchant_reference' => 'ORDER-99',
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->getJson("/api/payments?merchant_id={$firstMerchant->code}&status=failed&search=BOOKING")
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.merchant_reference', 'BOOKING-42')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_payment_dashboard_requires_an_active_authenticated_user(): void
    {
        $this->getJson('/api/payments')->assertUnauthorized();

        $inactiveUser = User::factory()->create(['is_active' => false]);
        $this->actingAs($inactiveUser)->getJson('/api/payments')->assertForbidden();
    }
}
