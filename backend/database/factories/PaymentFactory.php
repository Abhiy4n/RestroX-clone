<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'merchant_reference' => 'ORDER-'.fake()->unique()->numerify('######'),
            'amount_minor' => fake()->numberBetween(100, 1_000_000),
            'currency' => 'NPR',
            'description' => fake()->sentence(),
            'status' => Payment::STATUS_PENDING,
            'checkout_token_hash' => hash('sha256', fake()->unique()->uuid()),
            'idempotency_key_hash' => hash('sha256', fake()->unique()->uuid()),
            'return_url' => fake()->url(),
            'cancel_url' => fake()->url(),
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
