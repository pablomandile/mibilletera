<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gastos fijos y compras en cuotas. Los dos son recurrentes mensuales que nacen
 * al cargar un gasto: el "kind" las distingue de las armadas a mano en Ajustes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('accepts_installments')->default(false)->after('icon');
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->string('kind', 15)->default('manual')->after('type'); // manual | fixed | installments
            // Día del mes del primer gasto: así 31/01 → 28/02 → 31/03 y no se corre a 28.
            $table->unsignedTinyInteger('anchor_day')->nullable()->after('interval');
            $table->unsignedSmallInteger('installments_total')->nullable()->after('anchor_day');
            $table->unsignedSmallInteger('next_installment')->nullable()->after('installments_total');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_transaction_id')->nullable()->after('category_id')
                ->constrained('recurring_transactions')->nullOnDelete();
            $table->unsignedSmallInteger('installment_number')->nullable()->after('recurring_transaction_id');
            $table->unsignedSmallInteger('installments_total')->nullable()->after('installment_number');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_transaction_id');
            $table->dropColumn(['installment_number', 'installments_total']);
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropColumn(['kind', 'anchor_day', 'installments_total', 'next_installment']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('accepts_installments');
        });
    }
};
