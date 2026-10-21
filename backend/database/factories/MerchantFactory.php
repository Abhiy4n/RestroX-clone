<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'website_url' => fake()->url(),
            'integration_type' => Merchant::INTEGRATION_SERVER_API,
            'status' => Merchant::STATUS_ACTIVE,
            'mode' => 'sandbox',
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'status' => Merchant::STATUS_DISABLED,
        ]);
    }
}
