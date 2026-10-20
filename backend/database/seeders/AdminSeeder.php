<?php

namespace Database\Seeders;

use App\Actions\ProvisionWallet;
use App\Models\User;
use App\Services\WalletSetup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(ProvisionWallet $provisionWallet, WalletSetup $walletSetup): void
    {
        $name = trim((string) config('admin.name'));
        $email = mb_strtolower(trim((string) config('admin.email')));
        $password = (string) config('admin.password');

        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('ADMIN_EMAIL and ADMIN_PASSWORD are required.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('ADMIN_EMAIL must be a valid email address.');
        }

        if (mb_strlen($password) < 12) {
            throw new InvalidArgumentException('ADMIN_PASSWORD must contain at least 12 characters.');
        }

        DB::transaction(function () use ($provisionWallet, $walletSetup, $name, $email, $password): void {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user !== null && ! $user->isAdmin()) {
                throw new RuntimeException('Refusing to promote an existing non-admin account.');
            }

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $name !== '' ? $name : 'Administrator',
                    'email' => $email,
                    'password' => $password,
                ]);
                $user->forceFill([
                    'role' => User::ROLE_ADMIN,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ])->save();
            }

            $provisionWallet->handle($user);
            $walletSetup->platformIssuance();
        });
    }
}
