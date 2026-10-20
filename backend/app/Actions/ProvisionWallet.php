<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletSetup;
use Illuminate\Support\Facades\DB;

class ProvisionWallet
{
    public function __construct(
        private readonly WalletSetup $walletSetup,
    ) {}

    public function handle(User $user): Wallet
    {
        return DB::transaction(function () use ($user): Wallet {
            $wallet = $user->wallet()->firstOrCreate([], [
                'currency' => 'NPR',
            ]);

            $this->walletSetup->forWallet($wallet);

            return $wallet;
        });
    }
}
