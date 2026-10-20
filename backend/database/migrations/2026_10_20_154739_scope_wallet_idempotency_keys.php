<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->string('idempotency_scope', 100)->nullable()->after('currency');
            $table->char('idempotency_key_hash', 64)->nullable()->after('idempotency_scope');
        });

        DB::table('ledger_transactions')
            ->select(['id', 'type', 'initiated_by', 'idempotency_key'])
            ->orderBy('id')
            ->chunkById(100, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    $actor = $transaction->initiated_by === null
                        ? 'system'
                        : 'user:'.$transaction->initiated_by;

                    DB::table('ledger_transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'idempotency_scope' => $actor.':'.$transaction->type,
                            'idempotency_key_hash' => hash('sha256', $transaction->idempotency_key),
                        ]);
                }
            });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropUnique('ledger_transactions_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->string('idempotency_scope', 100)->nullable(false)->change();
            $table->char('idempotency_key_hash', 64)->nullable(false)->change();
            $table->unique(
                ['idempotency_scope', 'idempotency_key_hash'],
                'ledger_tx_idempotency_unique',
            );
        });

        Schema::drop('idempotency_keys');
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('scope', 100);
            $table->char('key_hash', 64);
            $table->timestamps();

            $table->primary(['scope', 'key_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('currency');
        });

        DB::table('ledger_transactions')
            ->select(['id', 'idempotency_key_hash'])
            ->orderBy('id')
            ->chunkById(100, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    DB::table('ledger_transactions')
                        ->where('id', $transaction->id)
                        ->update(['idempotency_key' => $transaction->idempotency_key_hash]);
                }
            });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropUnique('ledger_tx_idempotency_unique');
            $table->dropColumn(['idempotency_scope', 'idempotency_key_hash']);
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable(false)->change();
            $table->unique('idempotency_key');
        });

        Schema::drop('idempotency_keys');
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->timestamps();
        });
    }
};
