<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un gasto fijo puede estar pago o impago. Lo que carga el usuario nace pago;
 * las repeticiones que genera el sistema, impagas hasta que se marquen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_paid')->default(true)->after('installments_total');
        });

        // Las repeticiones de gastos fijos ya generadas (todas menos la primera
        // de cada serie, que la cargó el usuario) quedan impagas.
        $series = DB::table('recurring_transactions')->where('kind', 'fixed')->pluck('id');
        foreach ($series as $id) {
            $origin = DB::table('transactions')->where('recurring_transaction_id', $id)->min('id');
            DB::table('transactions')
                ->where('recurring_transaction_id', $id)
                ->where('id', '!=', $origin)
                ->update(['is_paid' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('is_paid');
        });
    }
};
