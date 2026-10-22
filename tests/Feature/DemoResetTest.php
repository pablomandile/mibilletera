<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * demo:reset corre migrate:fresh, que en SQLite no puede ir dentro de la
 * transacción de RefreshDatabase. Sin traits: la base en memoria arranca vacía,
 * se migra a mano y se descarta al terminar (sin rollback).
 */
class DemoResetTest extends TestCase
{
    public function test_reset_wipes_data_and_uploads_and_reseeds(): void
    {
        $this->artisan('migrate')->assertSuccessful();
        config(['demo.enabled' => true]);
        Storage::fake('public');
        Storage::disk('public')->put('categories/3/icono.jpg', 'x');
        Storage::disk('public')->put('transactions/3/ticket.jpg', 'x');
        User::factory()->create(['email' => 'visitante@x.com']);

        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame(['demo@pablomandile.com.ar'], User::pluck('email')->all());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
