<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\Wallet;
use InvalidArgumentException;

class WalletSetup
{
    // Create the accounting account for a wallet.
    public function forWallet(Wallet $wallet): LedgerAccount
    {
        if ($wallet->currency !== 'NPR') {
            throw new InvalidArgumentException(
                'Only NPR wallets are supported.'
            );
        }

        return LedgerAccount::query()->firstOrCreate(
            ['wallet_id' => $wallet->id],
            [
                'code' => 'wallet:'.$wallet->id,
                'name' => 'Wallet '.$wallet->id,
                'type' => 'wallet_liability',
                'normal_side' => 'credit',
                'currency' => 'NPR',
            ],
        );
    }

    // Create the platform account for issuing test money.
    public function platformIssuance(): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => 'platform:issuance:NPR'],
            [
                'name' => 'Virtual Money Issuance',
                'type' => 'platform_issuance',
                'normal_side' => 'debit',
                'currency' => 'NPR',
            ],
        );
    }
}
