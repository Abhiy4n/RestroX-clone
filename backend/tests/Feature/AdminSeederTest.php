<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_credentials_are_required(): void
    {
        config()->set('admin.email');
        config()->set('admin.password');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ADMIN_EMAIL and ADMIN_PASSWORD are required.');

        $this->seed(AdminSeeder::class);
    }

    public function test_admin_password_must_have_at_least_twelve_characters(): void
    {
        $this->configureAdmin(password: 'short');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD must contain at least 12 characters.');

        $this->seed(AdminSeeder::class);
    }

    public function test_seeder_refuses_to_promote_an_existing_developer(): void
    {
        $this->configureAdmin();
        $developer = User::factory()->create(['email' => 'admin@example.com']);

        try {
            $this->seed(AdminSeeder::class);
            $this->fail('Expected the seeder to refuse promotion.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Refusing to promote an existing non-admin account.', $exception->getMessage());
        }

        $this->assertSame(User::ROLE_DEVELOPER, $developer->refresh()->role);
        $this->assertNull($developer->wallet);
    }

    public function test_seeder_creates_an_admin_wallet_and_ledger_account_without_funds(): void
    {
        $this->configureAdmin();

        $this->seed(AdminSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('StrongAdminPassword12', $admin->password));
        $this->assertSame('0.00', $admin->wallet->balance);
        $this->assertDatabaseHas('ledger_accounts', [
            'wallet_id' => $admin->wallet->id,
            'code' => 'wallet:'.$admin->wallet->id,
        ]);
        $this->assertDatabaseHas('ledger_accounts', [
            'wallet_id' => null,
            'code' => 'platform:issuance:NPR',
        ]);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_rerunning_seeder_keeps_an_existing_admin_password(): void
    {
        $this->configureAdmin();
        $this->seed(AdminSeeder::class);
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $originalPassword = $admin->password;

        $this->configureAdmin(password: 'DifferentAdminPassword34');
        $this->seed(AdminSeeder::class);

        $this->assertSame($originalPassword, $admin->refresh()->password);
        $this->assertSame(1, $admin->wallet()->count());
        $this->assertDatabaseCount('ledger_accounts', 2);
    }

    private function configureAdmin(string $password = 'StrongAdminPassword12'): void
    {
        config()->set('admin.name', 'Primary Admin');
        config()->set('admin.email', 'admin@example.com');
        config()->set('admin.password', $password);
    }
}
