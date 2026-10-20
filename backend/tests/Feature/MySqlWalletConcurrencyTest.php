<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ProvisionWallet;
use App\Exceptions\WalletOperationException;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class MySqlWalletConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'mysql') {
            $database = (string) DB::connection()->getDatabaseName();

            if ($database !== 'testing' && ! str_ends_with($database, '_test')) {
                throw new RuntimeException('MySQL concurrency tests require a dedicated test database.');
            }

            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    public function test_concurrent_transfers_cannot_overdraw_a_wallet(): void
    {
        $this->requireMySql();

        [$admin, $adminWallet] = $this->userWithWallet(User::ROLE_ADMIN);
        [$sender, $senderWallet] = $this->userWithWallet();
        [, $firstRecipient] = $this->userWithWallet();
        [, $secondRecipient] = $this->userWithWallet();
        app(LedgerService::class)->addMoney($admin, $senderWallet, 1000, 'concurrency-funding');

        $results = Concurrency::run([
            fn (): string => self::attemptTransfer($sender->id, $senderWallet->id, $firstRecipient->id, 'concurrent-transfer-one'),
            fn (): string => self::attemptTransfer($sender->id, $senderWallet->id, $secondRecipient->id, 'concurrent-transfer-two'),
        ], timeout: 30);

        $postedCount = count(array_filter($results, fn (string $result): bool => $result !== 'insufficient'));

        $this->assertSame(1, $postedCount);
        $this->assertContains('insufficient', $results);
        $this->assertSame('2.00', $senderWallet->refresh()->balance);
        $this->assertSame(
            800,
            $this->minorBalance($firstRecipient->refresh()) + $this->minorBalance($secondRecipient->refresh()),
        );
        $this->assertSame(2, LedgerTransaction::query()->count());
        $this->assertDatabaseCount('ledger_entries', 4);
        $this->assertSame('0.00', $adminWallet->refresh()->balance);
    }

    public function test_concurrent_retries_post_only_one_money_movement(): void
    {
        $this->requireMySql();

        [$admin] = $this->userWithWallet(User::ROLE_ADMIN);
        [$sender, $senderWallet] = $this->userWithWallet();
        [, $recipient] = $this->userWithWallet();
        app(LedgerService::class)->addMoney($admin, $senderWallet, 1000, 'retry-funding');

        $results = Concurrency::run([
            fn (): string => self::attemptTransfer($sender->id, $senderWallet->id, $recipient->id, 'same-concurrent-key'),
            fn (): string => self::attemptTransfer($sender->id, $senderWallet->id, $recipient->id, 'same-concurrent-key'),
        ], timeout: 30);

        $this->assertSame($results[0], $results[1]);
        $this->assertSame('2.00', $senderWallet->refresh()->balance);
        $this->assertSame('8.00', $recipient->refresh()->balance);
        $this->assertSame(2, LedgerTransaction::query()->count());
        $this->assertDatabaseCount('ledger_entries', 4);
    }

    public function test_funding_rejects_a_wallet_balance_overflow(): void
    {
        $this->requireMySql();

        [$admin] = $this->userWithWallet(User::ROLE_ADMIN);
        [, $recipient] = $this->userWithWallet();
        $recipient->forceFill(['balance' => '9999999999999.99'])->save();

        try {
            app(LedgerService::class)->addMoney($admin, $recipient, 1, 'balance-overflow');
            $this->fail('Expected the wallet balance limit to reject the funding operation.');
        } catch (WalletOperationException $exception) {
            $this->assertSame('The recipient wallet balance limit would be exceeded.', $exception->getMessage());
        }

        $this->assertSame('9999999999999.99', $recipient->refresh()->balance);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    /**
     * @return array{User, Wallet}
     */
    private function userWithWallet(string $role = User::ROLE_DEVELOPER): array
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        $wallet = app(ProvisionWallet::class)->handle($user);

        return [$user, $wallet];
    }

    private static function attemptTransfer(
        int $userId,
        int $senderWalletId,
        int $recipientWalletId,
        string $idempotencyKey,
    ): string {
        DB::purge();

        try {
            $transaction = app(LedgerService::class)->sendMoney(
                User::query()->findOrFail($userId),
                Wallet::query()->findOrFail($senderWalletId),
                Wallet::query()->findOrFail($recipientWalletId),
                800,
                $idempotencyKey,
            );

            return $transaction->reference;
        } catch (WalletOperationException $exception) {
            if ($exception->getMessage() === 'The wallet has insufficient balance.') {
                return 'insufficient';
            }

            throw $exception;
        }
    }

    private function requireMySql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This test verifies MySQL row-lock behavior.');
        }
    }

    private function minorBalance(Wallet $wallet): int
    {
        [$rupees, $paisa] = explode('.', $wallet->balance);

        return ((int) $rupees * 100) + (int) $paisa;
    }
}
