<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambios hechos sin conexión que ya se aplicaron (ver SyncController).
 *
 * Sirve para dos cosas: que reenviar la misma operación no la duplique (si se
 * corta la red justo después de mandarla, la app la vuelve a mandar), y para
 * traducir los ids temporales ("tmp:<uuid>") de lo creado sin conexión al id
 * real, aunque la creación se haya sincronizado en un envío anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('op_id');
            $table->string('entity', 40);
            $table->string('action', 20);
            $table->string('temp_id', 60)->nullable();
            $table->unsignedBigInteger('real_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'op_id']);
            $table->index(['user_id', 'temp_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
