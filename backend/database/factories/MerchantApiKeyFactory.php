<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantApiKey>
 */
class MerchantApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $secret = 'rsk_test_'.Str::random(48);

        return [
            'merchant_id' => Merchant::factory(),
            'key_id' => 'key_'.Str::lower((string) Str::ulid()),
            'name' => fake()->words(2, true),
            'secret_hash' => hash('sha256', $secret),
            'secret_hint' => Str::substr($secret, 0, 13).'…'.Str::substr($secret, -4),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
