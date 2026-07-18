<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el usuario administrador con una clave genérica.
     * ⚠️ Cambiar la contraseña después del primer acceso.
     */
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => 'admin@mibilletera.test']);

        if (! $admin->exists) {
            $admin->name = 'Administrador';
            $admin->password = 'admin1234'; // el cast 'hashed' la encripta al guardar
            $admin->email_verified_at = now();
            $admin->default_currency = 'ARS';
        }

        $admin->role = User::ROLE_ADMIN;
        $admin->save();
    }
}
