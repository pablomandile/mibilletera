<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supercategorías: agrupan categorías de gasto para el gráfico "A vuelo de
     * pájaro" (Comida = Supermercado + Verdura + ...). Cada categoría pertenece
     * como mucho a una; al borrar la supercategoría, sus categorías quedan sueltas.
     */
    public function up(): void
    {
        Schema::create('supercategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#6366f1');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('supercategory_id')->nullable()->after('parent_id')
                ->constrained('supercategories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supercategory_id');
        });

        Schema::dropIfExists('supercategories');
    }
};
