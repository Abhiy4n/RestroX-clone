<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->unique()->constrained('ledger_transactions')->nullOnDelete();
            $table->string('public_id')->unique();
            $table->string('merchant_reference', 100);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('NPR');
            $table->string('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->char('checkout_token_hash', 64)->unique();
            $table->char('idempotency_key_hash', 64);
            $table->string('return_url', 2048);
            $table->string('cancel_url', 2048)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'idempotency_key_hash'], 'payment_idempotency_unique');
            $table->index(['merchant_id', 'created_at']);
            $table->index(['merchant_id', 'merchant_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
