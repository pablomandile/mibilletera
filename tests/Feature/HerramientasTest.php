<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Herramientas: Descuento con tope y Rendimiento de tu cuenta (portadas de
 * CalculAR). La inflación para comparar sale de la API de CalculAR.
 */
class HerramientasTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://calcular.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.calcular.url' => self::API]);
        $this->seed(CurrencySeeder::class);
    }

    private function fakeIpc(array $valores): void
    {
        Http::fake([self::API.'/api/indices/ipc' => Http::response([
            'serie' => 'ipc',
            'mensual' => true,
            'valores' => $valores,
        ])]);
    }

    public function test_descuento_con_tope_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/herramientas/descuento-con-tope')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Herramientas/DescuentoConTope'));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/herramientas/descuento-con-tope')->assertRedirect('/login');
        $this->get('/herramientas/rendimiento-cuenta')->assertRedirect('/login');
    }

    public function test_rendimiento_offers_only_the_users_active_peso_accounts(): void
    {
        $this->fakeIpc([['2026-09-01', '100'], ['2026-10-01', '102']]);
        $user = User::factory()->create(['default_currency' => 'ARS']); // trae "Efectivo" en ARS
        $user->accounts()->create(['name' => 'Mercado Pago', 'type' => 'bank', 'currency_code' => 'ARS', 'initial_balance' => 150000, 'sort_order' => 1]);
        $user->accounts()->create(['name' => 'Dólares', 'type' => 'cash', 'currency_code' => 'USD', 'initial_balance' => 500, 'sort_order' => 2]);
        $user->accounts()->create(['name' => 'Vieja', 'type' => 'bank', 'currency_code' => 'ARS', 'initial_balance' => 10, 'is_archived' => true, 'sort_order' => 3]);
        User::factory()->create()->accounts()->create(['name' => 'Ajena', 'type' => 'bank', 'currency_code' => 'ARS', 'initial_balance' => 99]);

        $this->actingAs($user)
            ->get('/herramientas/rendimiento-cuenta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Herramientas/RendimientoCuenta')
                ->has('accounts', 2)
                ->where('accounts.0.name', 'Efectivo')
                ->where('accounts.1.name', 'Mercado Pago')
                ->where('accounts.1.balance', 150000)
                ->where('calcularUrl', self::API));
    }

    public function test_the_inflation_is_the_ratio_of_the_last_two_months(): void
    {
        $this->fakeIpc([['2026-08-01', '9500'], ['2026-09-01', '10000'], ['2026-10-01', '10230']]);

        $this->actingAs(User::factory()->create())
            ->get('/herramientas/rendimiento-cuenta')
            ->assertInertia(fn (Assert $page) => $page
                ->where('inflacion.mes', '2026-10')
                ->where('inflacion.valor', fn ($v) => abs($v - 0.023) < 1e-9));
    }

    public function test_months_that_are_not_consecutive_give_no_inflation(): void
    {
        $this->fakeIpc([['2026-08-01', '100'], ['2026-10-01', '102']]);

        $this->actingAs(User::factory()->create())
            ->get('/herramientas/rendimiento-cuenta')
            ->assertInertia(fn (Assert $page) => $page->where('inflacion', null));
    }

    public function test_without_the_api_the_page_still_renders_and_the_failure_is_remembered(): void
    {
        Http::fake([self::API.'/*' => Http::response(['message' => 'error'], 500)]);
        $user = User::factory()->create();

        foreach ([1, 2] as $_) {
            $this->actingAs($user)
                ->get('/herramientas/rendimiento-cuenta')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('inflacion', null));
        }

        Http::assertSentCount(1);
    }

    public function test_a_connection_error_gives_no_inflation(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs(User::factory()->create())
            ->get('/herramientas/rendimiento-cuenta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('inflacion', null));
    }
}
