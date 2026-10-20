<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_own_multiple_merchants(): void
    {
        $owner = User::factory()->create();

        $firstMerchant = $owner->merchants()->create([
            'name' => 'Himalayan Coffee',
            'code' => 'himalayan-coffee',
        ]);
        $secondMerchant = $owner->merchants()->create([
            'name' => 'Everest Kitchen',
            'code' => 'everest-kitchen',
        ]);

        $this->assertCount(2, $owner->merchants);
        $this->assertTrue($firstMerchant->owner->is($owner));
        $this->assertTrue($secondMerchant->owner->is($owner));
    }

    public function test_merchant_settles_into_its_owners_wallet(): void
    {
        $owner = User::factory()->create();
        $ownerWallet = $owner->wallet()->create(['currency' => 'NPR']);
        $otherWallet = User::factory()->create()->wallet()->create(['currency' => 'NPR']);
        $merchant = $owner->merchants()->create([
            'name' => 'Himalayan Coffee',
            'code' => 'himalayan-coffee',
        ]);

        $this->assertTrue($merchant->settlementWallet->is($ownerWallet));
        $this->assertFalse($merchant->settlementWallet->is($otherWallet));
    }
}
