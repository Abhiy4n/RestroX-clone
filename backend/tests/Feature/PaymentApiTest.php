<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantApiKey;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_create_and_retrieve_a_pending_payment(): void
    {
        [$merchant, $secret] = $this->merchantCredentials();
        $this->travelTo('2026-10-23 14:00:00');

        $created = $this->withToken($secret)
            ->withHeader('Idempotency-Key', 'order-123-payment')
            ->postJson('/api/v1/payments', $this->paymentPayload())
            ->assertCreated()
            ->assertJsonPath('merchant_reference', 'ORDER-123')
            ->assertJsonPath('status', Payment::STATUS_PENDING)
            ->assertJsonPath('amount_minor', 150000)
            ->assertJsonPath('currency', 'NPR')
            ->assertJsonPath('expires_at', now()->addMinutes(30)->toISOString());

        $paymentId = $created->json('payment_id');
        $checkoutUrl = $created->json('checkout_url');
        $this->assertMatchesRegularExpression('/^pay_[0-9a-z]{26}$/', $paymentId);
        $this->assertStringContainsString('/checkout/chk_', $checkoutUrl);
        $this->assertDatabaseHas('payments', [
            'merchant_id' => $merchant->id,
            'public_id' => $paymentId,
            'checkout_token_hash' => hash('sha256', basename($checkoutUrl)),
            'idempotency_key_hash' => hash('sha256', 'order-123-payment'),
        ]);

        $this->withToken($secret)
            ->getJson("/api/v1/payments/{$paymentId}")
            ->assertOk()
            ->assertJsonPath('payment_id', $paymentId)
            ->assertJsonPath('merchant_reference', 'ORDER-123')
            ->assertJsonMissingPath('checkout_url');
    }

    public function test_payment_endpoints_require_a_valid_merchant_key(): void
    {
        $payment = Payment::factory()->create();

        $this->postJson('/api/v1/payments', $this->paymentPayload())->assertUnauthorized();
        $this->withToken('rsk_test_invalid')->getJson("/api/v1/payments/{$payment->public_id}")->assertUnauthorized();
    }

    public function test_payment_creation_validates_input_and_idempotency_header(): void
    {
        [, $secret] = $this->merchantCredentials();

        $this->withToken($secret)->postJson('/api/v1/payments', [
            'merchant_reference' => ' ',
            'amount_minor' => 0,
            'currency' => 'USD',
            'return_url' => 'javascript:alert(1)',
            'cancel_url' => 'not-a-url',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'merchant_reference',
                'amount_minor',
                'currency',
                'return_url',
                'cancel_url',
                'idempotency_key',
            ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_retrieval_is_isolated_to_the_authenticated_merchant(): void
    {
        [$owner, $ownerSecret] = $this->merchantCredentials();
        [, $otherSecret] = $this->merchantCredentials();
        $payment = Payment::factory()->for($owner)->create();

        $this->withToken($ownerSecret)->getJson("/api/v1/payments/{$payment->public_id}")->assertOk();
        $this->withToken($otherSecret)->getJson("/api/v1/payments/{$payment->public_id}")->assertNotFound();
    }

    public function test_identical_retry_returns_the_same_payment_and_checkout_url(): void
    {
        [, $secret] = $this->merchantCredentials();
        $request = fn () => $this->withToken($secret)
            ->withHeader('Idempotency-Key', 'retry-once')
            ->postJson('/api/v1/payments', $this->paymentPayload());

        $first = $request()->assertCreated();
        $second = $request()->assertOk();

        $this->assertSame($first->json('payment_id'), $second->json('payment_id'));
        $this->assertSame($first->json('checkout_url'), $second->json('checkout_url'));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_reusing_an_idempotency_key_for_different_payment_returns_conflict(): void
    {
        [, $secret] = $this->merchantCredentials();

        $this->withToken($secret)
            ->withHeader('Idempotency-Key', 'conflicting-key')
            ->postJson('/api/v1/payments', $this->paymentPayload())
            ->assertCreated();

        $this->withToken($secret)
            ->withHeader('Idempotency-Key', 'conflicting-key')
            ->postJson('/api/v1/payments', [
                ...$this->paymentPayload(),
                'amount_minor' => 200000,
            ])->assertConflict();

        $this->assertDatabaseCount('payments', 1);
    }

    /** @return array{0: Merchant, 1: string} */
    private function merchantCredentials(): array
    {
        $merchant = Merchant::factory()->create();
        $secret = 'rsk_test_'.fake()->unique()->regexify('[A-Za-z0-9]{48}');
        MerchantApiKey::factory()->for($merchant)->create([
            'secret_hash' => hash('sha256', $secret),
            'secret_hint' => substr($secret, 0, 13).'…'.substr($secret, -4),
        ]);

        return [$merchant, $secret];
    }

    /** @return array<string, int|string> */
    private function paymentPayload(): array
    {
        return [
            'merchant_reference' => 'ORDER-123',
            'amount_minor' => 150000,
            'currency' => 'NPR',
            'description' => 'Order #123',
            'return_url' => 'https://merchant.example/orders/123/complete',
            'cancel_url' => 'https://merchant.example/orders/123',
        ];
    }
}
