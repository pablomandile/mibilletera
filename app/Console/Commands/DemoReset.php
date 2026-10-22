<?php

namespace App\Console\Commands;

use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Vacía la base y vuelve a sembrar la demo. Corre todas las noches en la copia
 * de pablomandile (ver routes/console.php).
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Borra todos los datos y resiembra el usuario de la demo (solo con APP_DEMO=true)';

    public function handle(): int
    {
        // Sin el flag no se toca nada: este comando borra la base entera.
        if (! config('demo.enabled')) {
            $this->error('La demo no está activa (APP_DEMO). No se borró nada.');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);

        // Lo que subieron los visitantes (íconos de categorías y fotos de comprobantes).
        foreach (['categories', 'transactions'] as $dir) {
            Storage::disk('public')->deleteDirectory($dir);
        }

        $this->call('db:seed', ['--class' => CurrencySeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
