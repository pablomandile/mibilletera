<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10); // expense | income
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3)->default('ARS');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->string('note')->nullable();
            $table->string('frequency', 10); // daily | weekly | monthly | yearly
            $table->unsignedInteger('interval')->default(1);
            $table->date('next_run_date');
            $table->date('end_date')->nullable();
            $table->date('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('next_run_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
