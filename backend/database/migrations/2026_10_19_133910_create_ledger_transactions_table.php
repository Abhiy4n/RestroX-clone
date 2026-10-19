
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();

            $table->uuid('reference')->unique();

            $table->string('type', 30);
            $table->string('status', 20)->default('posted');

            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('NPR');

            $table->string('idempotency_key')->unique();

            $table->foreignId('initiated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('description')->nullable();
            $table->timestamp('posted_at');

            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
