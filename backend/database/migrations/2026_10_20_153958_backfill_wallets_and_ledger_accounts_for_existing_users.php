<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::transaction(function () use ($user): void {
                        $wallet = DB::table('wallets')->where('user_id', $user->id)->first();

                        if ($wallet === null) {
                            $walletId = DB::table('wallets')->insertGetId([
                                'user_id' => $user->id,
                                'balance' => '0.00',
                                'currency' => 'NPR',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            $walletId = $wallet->id;
                        }

                        DB::table('ledger_accounts')->insertOrIgnore([
                            'code' => 'wallet:'.$walletId,
                            'wallet_id' => $walletId,
                            'name' => 'Wallet '.$walletId,
                            'type' => 'wallet_liability',
                            'normal_side' => 'credit',
                            'currency' => 'NPR',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    });
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing user funds and accounting records must not be deleted during rollback.
    }
};
