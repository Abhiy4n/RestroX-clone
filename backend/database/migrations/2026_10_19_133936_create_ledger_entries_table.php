<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ledger_transaction_id')
                ->constrained('ledger_transactions')
                ->restrictOnDelete();

            $table->foreignId('ledger_account_id')
                ->constrained('ledger_accounts')
                ->restrictOnDelete();

            $table->string('direction', 6);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);

            $table->timestamps();

            $table->index([
                'ledger_account_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
