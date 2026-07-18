<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('cash'); // cash, bank, card, other
            $table->char('currency_code', 3)->default('ARS');
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->string('color', 20)->default('#f59e0b');
            $table->string('icon', 40)->default('wallet');
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
