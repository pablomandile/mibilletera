<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('default_currency', 3)->default('ARS')->after('avatar');
            $table->string('locale', 10)->default('es-AR')->after('default_currency');
            $table->string('timezone')->nullable()->after('locale');
            $table->unsignedTinyInteger('month_start_day')->default(1)->after('timezone');
            $table->string('theme', 10)->default('dark')->after('month_start_day');
        });

        // Los usuarios que entran con Google no tienen contraseña local.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_id', 'avatar', 'default_currency',
                'locale', 'timezone', 'month_start_day', 'theme',
            ]);
        });
    }
};
