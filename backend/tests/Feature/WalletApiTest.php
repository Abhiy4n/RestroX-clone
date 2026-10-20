<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_endpoints_require_authentication(): void
    {
        $this->getJson('/api/wallet')->assertUnauthorized();
        $this->getJson('/api/wallet/transactions')->assertUnauthorized();
        $this->postJson('/api/wallet/add-money')->assertUnauthorized();
        $this->postJson('/api/wallet/send-money')->assertUnauthorized();
    }

    public function test_active_user_can_view_an_exact_decimal_balance(): void
    {
        [$user, $wallet] = $this->userWithWallet('1234.50');

        $this->actingAs($user)
            ->getJson('/api/wallet')
            ->assertOk()
            ->assertExactJson([
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => '1234.50',
                    'currency' => 'NPR',
                ],
            ]);
    }

    public function test_inactive_user_cannot_view_or_spend_a_wallet(): void
    {
        [$user] = $this->userWithWallet();
        [, $recipient] = $this->userWithWallet();
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->getJson('/api/wallet')->assertForbidden();
        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'inactive-transfer')
            ->postJson('/api/wallet/send-money', [
                'recipient_wallet_id' => $recipient->id,
                'amount_minor' => 100,
            ])->assertForbidden();
    }

    public function test_gates_enforce_role_activity_and_wallet_ownership(): void
    {
        [$admin, $adminWallet] = $this->userWithWallet(role: User::ROLE_ADMIN);
        [$developer, $developerWallet] = $this->userWithWallet();
        [$inactiveAdmin] = $this->userWithWallet(role: User::ROLE_ADMIN, active: false);

        $this->assertTrue(Gate::forUser($admin)->allows('issue-test-money'));
        $this->assertFalse(Gate::forUser($developer)->allows('issue-test-money'));
        $this->assertFalse(Gate::forUser($inactiveAdmin)->allows('issue-test-money'));
        $this->assertTrue(Gate::forUser($developer)->allows('spend-wallet', $developerWallet));
        $this->assertFalse(Gate::forUser($developer)->allows('spend-wallet', $adminWallet));
    }

    public function test_admin_can_issue_money_and_an_exact_retry_does_not_credit_twice(): void
    {
        [$admin] = $this->userWithWallet(role: User::ROLE_ADMIN);
        [, $recipient] = $this->userWithWallet();
        $payload = ['wallet_id' => $recipient->id, 'amount_minor' => 1234];

        $first = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'fund-recipient-once')
            ->postJson('/api/wallet/add-money', $payload)
            ->assertOk()
            ->assertJsonPath('wallet.balance', '12.34')
            ->assertJsonPath('transaction.amount_minor', 1234);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'fund-recipient-once')
            ->postJson('/api/wallet/add-money', $payload)
            ->assertOk()
            ->assertJsonPath('transaction.reference', $first->json('transaction.reference'))
            ->assertJsonPath('wallet.balance', '12.34');

        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame('12.34', $recipient->refresh()->balance);
    }

    public function test_developer_cannot_issue_money(): void
    {
        [$developer] = $this->userWithWallet();
        [, $recipient] = $this->userWithWallet();

        $this->actingAs($developer)
            ->withHeader('Idempotency-Key', 'developer-funding')
            ->postJson('/api/wallet/add-money', [
                'wallet_id' => $recipient->id,
                'amount_minor' => 100,
            ])->assertForbidden();

        $this->assertSame('0.00', $recipient->refresh()->balance);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_wallet_mutations_require_a_valid_idempotency_key_and_amount(): void
    {
        [$admin] = $this->userWithWallet(role: User::ROLE_ADMIN);
        [, $recipient] = $this->userWithWallet();

        $this->actingAs($admin)
            ->postJson('/api/wallet/add-money', [
                'wallet_id' => $recipient->id,
                'amount_minor' => 0,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_minor', 'idempotency_key']);

        $this->assertSame('0.00', $recipient->refresh()->balance);
    }

    public function test_missing_target_wallet_returns_not_found(): void
    {
        [$admin] = $this->userWithWallet(role: User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'missing-wallet')
            ->postJson('/api/wallet/add-money', [
                'wallet_id' => 999999,
                'amount_minor' => 100,
            ])->assertNotFound();
    }

    public function test_missing_recipient_wallet_returns_not_found(): void
    {
        [$sender] = $this->userWithWallet();

        $this->actingAs($sender)
            ->withHeader('Idempotency-Key', 'missing-recipient')
            ->postJson('/api/wallet/send-money', [
                'recipient_wallet_id' => 999999,
                'amount_minor' => 100,
            ])->assertNotFound();
    }

    public function test_user_can_transfer_money_from_their_own_wallet(): void
    {
        [$admin] = $this->userWithWallet(role: User::ROLE_ADMIN);
        [$sender, $senderWallet] = $this->userWithWallet();
        [, $recipient] = $this->userWithWallet();
        $this->fundWallet($admin, $senderWallet, 5000, 'initial-sender-funds');

        $first = $this->actingAs($sender)
            ->withHeader('Idempotency-Key', 'send-to-recipient')
            ->postJson('/api/wallet/send-money', [
                'recipient_wallet_id' => $recipient->id,
                'amount_minor' => 1250,
            ])->assertOk()
            ->assertJsonPath('wallet.balance', '37.50')
            ->assertJsonMissingPath('recipient_wallet')
            ->assertJsonPath('transaction.type', 'transfer');

        $this->actingAs($sender)
            ->withHeader('Idempotency-Key', 'send-to-recipient')
            ->postJson('/api/wallet/send-money', [
                'recipient_wallet_id' => $recipient->id,
                'amount_minor' => 1250,
            ])->assertOk()
            ->assertJsonPath('transaction.reference', $first->json('transaction.reference'))
            ->assertJsonPath('wallet.balance', '37.50')
            ->assertJsonMissingPath('recipient_wallet');

        $this->assertSame('37.50', $senderWallet->refresh()->balance);
        $this->assertSame('12.50', $recipient->refresh()->balance);
    }

    public function test_transfer_rejects_insufficient_funds_and_self_transfer(): void
    {
        [$sender, $senderWallet] = $this->userWithWallet();
        [, $recipient] = $this->userWithWallet();

        $this->actingAs($sender)
            ->withHeader('Idempotency-Key', 'too-much')
            ->postJson('/api/wallet/send-money', [
                'recipient_wallet_id' => $recipient->id,
                'amount_minor' => 1,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_minor']);

        $this->actingAs($sender)
            ->withHeader('Idempotency-Key', 'self-transfer')
            ->postJson('/api/wallet/send-money', [
                'recipient_wallet_id' => $senderWallet->id,
                'amount_minor' => 1,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_wallet_id']);

        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_reusing_an_idempotency_key_for_different_details_returns_conflict(): void
    {
        [$admin] = $this->userWithWallet(role: User::ROLE_ADMIN);
        [, $recipient] = $this->userWithWallet();
        $this->fundWallet($admin, $recipient, 100, 'shared-operation-key');

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'shared-operation-key')
            ->postJson('/api/wallet/add-money', [
                'wallet_id' => $recipient->id,
                'amount_minor' => 200,
            ])->assertConflict();

        $this->assertSame('1.00', $recipient->refresh()->balance);
        $this->assertSame(1, LedgerTransaction::query()->count());
    }

    public function test_different_users_can_reuse_the_same_idempotency_key(): void
    {
        [$firstAdmin] = $this->userWithWallet(role: User::ROLE_ADMIN);
        [$secondAdmin] = $this->userWithWallet(role: User::ROLE_ADMIN);
        [, $firstRecipient] = $this->userWithWallet();
        [, $secondRecipient] = $this->userWithWallet();

        $this->fundWallet($firstAdmin, $firstRecipient, 100, 'shared-client-key');
        $this->fundWallet($secondAdmin, $secondRecipient, 200, 'shared-client-key');

        $this->assertSame('1.00', $firstRecipient->refresh()->balance);
        $this->assertSame('2.00', $secondRecipient->refresh()->balance);
        $this->assertDatabaseCount('ledger_transactions', 2);
    }

    public function test_user_can_list_only_their_wallet_transactions_newest_first(): void
    {
        [$admin] = $this->userWithWallet(role: User::ROLE_ADMIN);
        [$user, $wallet] = $this->userWithWallet();
        [, $recipient] = $this->userWithWallet();
        [, $unrelatedWallet] = $this->userWithWallet();
        $this->fundWallet($admin, $wallet, 5000, 'history-funding');
        $this->fundWallet($admin, $unrelatedWallet, 9000, 'unrelated-funding');

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'history-transfer')
            ->postJson('/api/wallet/send-money', [
                'recipient_wallet_id' => $recipient->id,
                'amount_minor' => 1250,
            ])->assertOk();

        $this->actingAs($user)
            ->getJson('/api/wallet/transactions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'transfer')
            ->assertJsonPath('data.0.direction', 'debit')
            ->assertJsonPath('data.0.amount_minor', 1250)
            ->assertJsonPath('data.1.type', 'funding')
            ->assertJsonPath('data.1.direction', 'credit')
            ->assertJsonPath('data.1.amount_minor', 5000)
            ->assertJsonMissingPath('data.2');
    }

    public function test_wallet_mutations_are_rate_limited_per_user(): void
    {
        [$admin] = $this->userWithWallet(role: User::ROLE_ADMIN);
        [, $recipient] = $this->userWithWallet();
        $payload = ['wallet_id' => $recipient->id, 'amount_minor' => 100];

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($admin)
                ->withHeader('Idempotency-Key', 'rate-limited-funding')
                ->postJson('/api/wallet/add-money', $payload)
                ->assertOk();
        }

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'rate-limited-funding')
            ->postJson('/api/wallet/add-money', $payload)
            ->assertTooManyRequests();

        $this->assertSame('1.00', $recipient->refresh()->balance);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    /**
     * @return array{User, Wallet}
     */
    private function userWithWallet(
        string $balance = '0.00',
        string $role = User::ROLE_DEVELOPER,
        bool $active = true,
    ): array {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => $active])->save();
        $wallet = $user->wallet()->create(['currency' => 'NPR']);
        $wallet->forceFill(['balance' => $balance])->save();

        return [$user, $wallet];
    }

    private function fundWallet(User $admin, Wallet $wallet, int $amountMinor, string $key): void
    {
        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/wallet/add-money', [
                'wallet_id' => $wallet->id,
                'amount_minor' => $amountMinor,
            ])->assertOk();
    }
}
