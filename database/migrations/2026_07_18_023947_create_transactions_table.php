<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 10); // expense | income | transfer
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3)->default('ARS');
            $table->decimal('exchange_rate', 18, 8)->default(1); // a la moneda base del usuario

            // Solo para transferencias
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('transfer_amount', 15, 2)->nullable();
            $table->char('transfer_currency', 3)->nullable();

            $table->string('note')->nullable();
            $table->string('photo_path')->nullable();
            $table->dateTime('transaction_date');
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type']);
            $table->index('account_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
