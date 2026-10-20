<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\WalletOperationException;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

class LedgerService
{
    private const CURRENCY = 'NPR';

    private const MAX_MINOR = 999_999_999_999_999;

    public function __construct(
        private readonly WalletSetup $setup
    ) {}

    // 1. Add virtual money to a user's wallet.
    public function addMoney(
        User $admin,
        Wallet $wallet,
        int $amountMinor,
        string $requestId
    ): LedgerTransaction {
        Gate::forUser($admin)->authorize('issue-test-money');

        return $this->processMoney(
            'funding',
            null,
            $wallet,
            $amountMinor,
            $requestId,
            $admin->id
        );
    }

    // 2. Send money from one wallet to another.
    public function sendMoney(
        User $user,
        Wallet $sender,
        Wallet $receiver,
        int $amountMinor,
        string $requestId
    ): LedgerTransaction {
        Gate::forUser($user)->authorize(
            'spend-wallet',
            $sender
        );

        if ($sender->id === $receiver->id) {
            throw WalletOperationException::selfTransfer();
        }

        return $this->processMoney(
            'transfer',
            $sender,
            $receiver,
            $amountMinor,
            $requestId,
            $user->id
        );
    }

    // 3. Coordinate one complete money movement.
    private function processMoney(
        string $type,
        ?Wallet $sender,
        Wallet $receiver,
        int $amountMinor,
        string $requestId,
        int $actorId
    ): LedgerTransaction {
        $this->checkAmount($amountMinor);
        $this->checkRequestId($requestId);
        $idempotencyScope = $this->idempotencyScope($actorId, $type);
        $idempotencyKeyHash = hash('sha256', $requestId);

        return DB::transaction(function () use (
            $type,
            $sender,
            $receiver,
            $amountMinor,
            $actorId,
            $idempotencyScope,
            $idempotencyKeyHash,
        ) {
            $this->lockRequest($idempotencyScope, $idempotencyKeyHash);

            $wallets = $this->getWallets(
                $sender?->id,
                $receiver->id
            );

            $fromWallet = $sender
                ? $wallets->get($sender->id)
                : null;

            $toWallet = $wallets->get($receiver->id);

            if (! $toWallet || ($sender && ! $fromWallet)) {
                throw WalletOperationException::walletNotFound();
            }

            $this->checkCurrencies($wallets);

            $fromAccount = $fromWallet
                ? $this->setup->forWallet($fromWallet)
                : $this->setup->platformIssuance();

            $toAccount = $this->setup->forWallet($toWallet);

            $this->checkAccounts(
                $type,
                $fromAccount,
                $toAccount,
                $fromWallet,
                $toWallet
            );

            // A retry must not move the money again.
            $existing = LedgerTransaction::query()
                ->with('entries')
                ->where('idempotency_scope', $idempotencyScope)
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->first();

            if ($existing) {
                if (! $this->sameRequest(
                    $existing,
                    $type,
                    $amountMinor,
                    $actorId,
                    $fromAccount,
                    $toAccount
                )) {
                    throw WalletOperationException::idempotencyConflict();
                }

                return $existing;
            }

            $this->checkBalance(
                $fromWallet,
                $toWallet,
                $amountMinor
            );

            $transaction = $this->saveTransaction(
                $type,
                $fromAccount,
                $toAccount,
                $amountMinor,
                $idempotencyScope,
                $idempotencyKeyHash,
                $actorId
            );

            $this->updateBalances(
                $fromWallet,
                $toWallet,
                $amountMinor
            );

            return $transaction;
        }, 3);
    }

    // 4. Validate an amount expressed in paisa.
    private function checkAmount(int $amount): void
    {
        if ($amount <= 0 || $amount > self::MAX_MINOR) {
            throw WalletOperationException::invalidAmount();
        }
    }

    // 5. Validate the unique request ID.
    private function checkRequestId(string $requestId): void
    {
        if (
            trim($requestId) === ''
            || strlen($requestId) > 255
        ) {
            throw WalletOperationException::invalidIdempotencyKey();
        }
    }

    // 6. Serialize requests with the same ID.
    private function lockRequest(string $scope, string $keyHash): void
    {
        $now = now();

        DB::table('idempotency_keys')->insertOrIgnore([
            'scope' => $scope,
            'key_hash' => $keyHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $key = DB::table('idempotency_keys')
            ->where('scope', $scope)
            ->where('key_hash', $keyHash)
            ->lockForUpdate()
            ->first();

        if ($key === null) {
            throw new RuntimeException('Unable to acquire the idempotency lock.');
        }
    }

    // 7. Fetch and lock affected wallets in ID order.
    private function getWallets(
        ?int $senderId,
        int $receiverId
    ): Collection {
        $ids = array_values(array_filter(
            [$senderId, $receiverId],
            fn ($id) => $id !== null
        ));

        return Wallet::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    // 8. Prevent transactions between mismatched currencies.
    private function checkCurrencies(Collection $wallets): void
    {
        foreach ($wallets as $wallet) {
            if ($wallet->currency !== self::CURRENCY) {
                throw WalletOperationException::unsupportedCurrency();
            }
        }
    }

    // 9. Check that accounts belong to the correct wallets.
    private function checkAccounts(
        string $type,
        LedgerAccount $from,
        LedgerAccount $to,
        ?Wallet $sender,
        Wallet $receiver
    ): void {
        if (
            $from->id === $to->id
            || $from->currency !== self::CURRENCY
            || $to->currency !== self::CURRENCY
        ) {
            throw new RuntimeException('Invalid ledger accounts.');
        }

        if (
            $to->type !== 'wallet_liability'
            || $to->normal_side !== 'credit'
            || (int) $to->wallet_id !== (int) $receiver->id
        ) {
            throw new RuntimeException(
                'Invalid destination account.'
            );
        }

        if ($type === 'funding') {
            $valid = $sender === null
                && $from->code === 'platform:issuance:NPR'
                && $from->type === 'platform_issuance'
                && $from->normal_side === 'debit'
                && $from->wallet_id === null;
        } elseif ($type === 'transfer') {
            $valid = $sender !== null
                && $from->type === 'wallet_liability'
                && $from->normal_side === 'credit'
                && (int) $from->wallet_id === (int) $sender->id;
        } else {
            $valid = false;
        }

        if (! $valid) {
            throw new RuntimeException(
                'Invalid source account for this operation.'
            );
        }
    }

    // 10. Ensure the sender has enough money
    // and the receiver will not exceed the balance limit.
    private function checkBalance(
        ?Wallet $sender,
        Wallet $receiver,
        int $amount
    ): void {
        if (
            $sender
            && $this->toMinor($sender->balance) < $amount
        ) {
            throw WalletOperationException::insufficientBalance();
        }

        $receiverBalance = $this->toMinor($receiver->balance);

        if ($receiverBalance > self::MAX_MINOR - $amount) {
            throw WalletOperationException::balanceLimitExceeded();
        }
    }

    // 11. Create the transaction and its two ledger entries.
    private function saveTransaction(
        string $type,
        LedgerAccount $from,
        LedgerAccount $to,
        int $amount,
        string $idempotencyScope,
        string $idempotencyKeyHash,
        int $actorId
    ): LedgerTransaction {
        $transaction = LedgerTransaction::create([
            'reference' => (string) Str::uuid(),
            'type' => $type,
            'status' => 'posted',
            'amount_minor' => $amount,
            'currency' => self::CURRENCY,
            'idempotency_scope' => $idempotencyScope,
            'idempotency_key_hash' => $idempotencyKeyHash,
            'initiated_by' => $actorId,
            'posted_at' => now(),
        ]);

        $transaction->entries()->createMany([
            [
                'ledger_account_id' => $from->id,
                'direction' => 'debit',
                'amount_minor' => $amount,
                'currency' => self::CURRENCY,
            ],
            [
                'ledger_account_id' => $to->id,
                'direction' => 'credit',
                'amount_minor' => $amount,
                'currency' => self::CURRENCY,
            ],
        ]);

        return $transaction;
    }

    // 12. Update wallet balance caches after ledger posting.
    private function updateBalances(
        ?Wallet $sender,
        Wallet $receiver,
        int $amount
    ): void {
        if ($sender) {
            $oldBalance = $this->toMinor($sender->balance);

            $sender->forceFill([
                'balance' => $this->toDecimal(
                    $oldBalance - $amount
                ),
            ])->save();
        }

        $oldBalance = $this->toMinor($receiver->balance);

        $receiver->forceFill([
            'balance' => $this->toDecimal(
                $oldBalance + $amount
            ),
        ])->save();
    }

    // 13. Check whether a request was already processed.
    private function sameRequest(
        LedgerTransaction $transaction,
        string $type,
        int $amount,
        int $actorId,
        LedgerAccount $from,
        LedgerAccount $to
    ): bool {
        if (
            $transaction->type !== $type
            || $transaction->status !== 'posted'
            || $transaction->currency !== self::CURRENCY
            || (int) $transaction->amount_minor !== $amount
            || (int) $transaction->initiated_by !== $actorId
        ) {
            return false;
        }

        $entries = $transaction->entries;

        if ($entries->count() !== 2) {
            return false;
        }

        $debits = $entries->where('direction', 'debit');
        $credits = $entries->where('direction', 'credit');

        if ($debits->count() !== 1 || $credits->count() !== 1) {
            return false;
        }

        $debit = $debits->first();
        $credit = $credits->first();

        return (int) $debit->ledger_account_id === (int) $from->id
            && (int) $credit->ledger_account_id === (int) $to->id
            && (int) $debit->amount_minor === $amount
            && (int) $credit->amount_minor === $amount
            && $debit->currency === self::CURRENCY
            && $credit->currency === self::CURRENCY;
    }

    // 14. Convert an exact NPR decimal balance into paisa.
    private function toMinor(string $balance): int
    {
        if (! preg_match('/^\d+\.\d{2}$/', $balance)) {
            throw new RuntimeException('Invalid wallet balance.');
        }

        $maximum = '9999999999999.99';

        if (
            strlen($balance) > strlen($maximum)
            || (
                strlen($balance) === strlen($maximum)
                && strcmp($balance, $maximum) > 0
            )
        ) {
            throw new RuntimeException(
                'Wallet balance limit exceeded.'
            );
        }

        [$rupees, $paisa] = explode('.', $balance);

        return (int) $rupees * 100 + (int) $paisa;
    }

    // 15. Convert paisa into an exact decimal string.
    private function toDecimal(int $minor): string
    {
        return intdiv($minor, 100)
            .'.'
            .str_pad(
                (string) ($minor % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }

    private function idempotencyScope(int $actorId, string $type): string
    {
        return 'user:'.$actorId.':'.$type;
    }
}
