<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotización del dólar configurable: la API (dirección y clave) es de toda la app
 * y la carga un admin en Ajustes; el dólar que se usa por defecto lo elige cada
 * usuario, uno para gastos y otro para ingresos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ajustes de toda la app, clave → valor. Los secretos van cifrados.
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('dollar_type_expense', 30)->default('tarjeta')->after('default_currency');
            $table->string('dollar_type_income', 30)->default('blue')->after('dollar_type_expense');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dollar_type_expense', 'dollar_type_income']);
        });

        Schema::dropIfExists('app_settings');
    }
};
