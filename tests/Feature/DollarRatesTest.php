<?php

namespace Tests\Feature;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DollarRates;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gastos e ingresos en dólares con la cotización precargada desde la API de
 * bambudev: dólar tarjeta para gastos, blue para ingresos.
 */
class DollarRatesTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.test/v1';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 12:00:00');
        config(['services.bambudev.url' => self::API, 'services.bambudev.key' => 'clave-de-prueba']);
        $this->seed(CurrencySeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Responde una venta por tipo y fecha: tarjeta 2000 + día, blue 1400 + día. */
    private function fakeApi(): void
    {
        Http::fake([
            self::API.'/dolares/*' => function (Request $request) {
                [, $tipo, $fecha] = array_slice(explode('/', parse_url($request->url(), PHP_URL_PATH)), -3);
                $day = (int) substr($fecha, 8, 2);

                return Http::response(['tipo' => $tipo, 'venta' => ($tipo === 'blue' ? 1400 : 2000) + $day, 'fecha' => $fecha]);
            },
        ]);
    }

    private function rates(): DollarRates
    {
        return app(DollarRates::class);
    }

    private function expense(User $user, array $overrides = []): array
    {
        return [
            'type' => 'expense',
            'amount' => 10,
            'account_id' => $user->accounts()->first()->id,
            'category_id' => $user->categories()->where('type', 'expense')->first()->id,
            'transaction_date' => '2026-11-18',
            ...$overrides,
        ];
    }

    // ---- El servicio ----

    public function test_returns_the_sale_and_sends_the_key(): void
    {
        $this->fakeApi();

        $this->assertSame(['venta' => 2018.0, 'fecha' => '2026-11-18'], $this->rates()->rate('tarjeta', '2026-11-18'));

        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Api-Key', 'clave-de-prueba')
            && $r->url() === self::API.'/dolares/tarjeta/2026-11-18');
    }

    public function test_answers_are_cached(): void
    {
        $this->fakeApi();

        $this->rates()->rate('blue', '2026-11-18');
        $this->rates()->rate('blue', '2026-11-18');

        Http::assertSentCount(1);
    }

    public function test_errors_give_null_and_never_throw(): void
    {
        foreach ([404, 429, 401, 500] as $i => $status) {
            Http::fake([self::API.'/*' => Http::response(['message' => 'no'], $status)]);
            $this->assertNull($this->rates()->rate('tarjeta', '2026-11-1'.$i), "HTTP $status");
        }

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->assertNull($this->rates()->rate('tarjeta', '2026-11-15'));
    }

    public function test_without_key_or_before_the_record_it_does_not_call(): void
    {
        Http::fake();

        $this->assertNull($this->rates()->rate('tarjeta', '2026-11-12'));
        config(['services.bambudev.key' => null]);
        $this->assertNull($this->rates()->rate('tarjeta', '2026-11-18'));

        Http::assertNothingSent();
    }

    public function test_a_future_date_asks_for_today(): void
    {
        $this->fakeApi();

        $this->assertSame('2026-11-20', $this->rates()->rate('tarjeta', '2026-11-28')['fecha']);
    }

    // ---- La ruta del formulario ----

    public function test_the_form_route_returns_the_rate(): void
    {
        $this->fakeApi();
        $this->getJson('/cotizacion?tipo=blue&fecha=2026-11-18')->assertUnauthorized();
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/cotizacion?tipo=blue&fecha=2026-11-18')
            ->assertOk()->assertJson(['tipo' => 'blue', 'venta' => 1418, 'fecha' => '2026-11-18']);

        $this->actingAs($user)->getJson('/cotizacion?tipo=cripto&fecha=2026-11-18')->assertUnprocessable();
    }

    public function test_the_form_route_without_rate_answers_null(): void
    {
        Http::fake([self::API.'/*' => Http::response([], 500)]);

        $this->actingAs(User::factory()->create())->getJson('/cotizacion?tipo=tarjeta&fecha=2026-11-19')
            ->assertOk()->assertJson(['venta' => null]);
    }

    // ---- Movimientos en dólares ----

    public function test_a_dollar_expense_on_a_peso_account_keeps_its_currency_and_moves_the_balance_converted(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->first();

        $this->actingAs($user)->post('/transactions', $this->expense($user, [
            'currency_code' => 'USD', 'exchange_rate' => 2041,
        ]))->assertSessionHasNoErrors();

        $t = Transaction::sole();
        $this->assertSame('USD', $t->currency_code);
        $this->assertEquals(2041, $t->exchange_rate);
        $this->assertSame(20410.0, $t->base_amount);
        $this->assertSame(-20410.0, $account->fresh()->currentBalance());
    }

    public function test_without_a_rate_the_server_fills_it_tarjeta_for_expenses_blue_for_income(): void
    {
        $this->fakeApi();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/transactions', $this->expense($user, ['currency_code' => 'USD']))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/transactions', $this->expense($user, [
            'type' => 'income', 'currency_code' => 'USD',
            'category_id' => $user->categories()->where('type', 'income')->first()->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame([2018.0, 1418.0], Transaction::orderBy('id')->pluck('exchange_rate')->map(fn ($r) => (float) $r)->all());
    }

    public function test_without_api_and_without_rate_it_asks_for_it(): void
    {
        Http::fake([self::API.'/*' => Http::response([], 500)]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/transactions', $this->expense($user, ['currency_code' => 'USD']))
            ->assertSessionHasErrors('exchange_rate');
        $this->assertSame(0, Transaction::count());
    }

    public function test_pesos_is_the_default_and_a_dollar_account_imposes_dollars(): void
    {
        $user = User::factory()->create();
        $usd = $user->accounts()->create(['name' => 'Ahorro', 'type' => 'bank', 'currency_code' => 'USD', 'initial_balance' => 100]);

        $this->actingAs($user)->post('/transactions', $this->expense($user))->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/transactions', $this->expense($user, [
            'account_id' => $usd->id, 'currency_code' => 'ARS', 'exchange_rate' => 1450,
        ]))->assertSessionHasNoErrors();

        [$pesos, $dollars] = Transaction::orderBy('id')->get();
        $this->assertSame('ARS', $pesos->currency_code);
        $this->assertEquals(1, $pesos->exchange_rate);
        $this->assertSame('USD', $dollars->currency_code);
        $this->assertSame(90.0, $usd->fresh()->currentBalance());
    }

    public function test_editing_without_currency_keeps_the_one_it_had(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/transactions', $this->expense($user, ['currency_code' => 'USD', 'exchange_rate' => 2041]));
        $t = Transaction::sole();

        // Un cliente viejo no manda ni la moneda ni la cotización.
        $this->actingAs($user)->post("/transactions/{$t->id}", $this->expense($user, ['amount' => 12]))
            ->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('USD', $t->currency_code);
        $this->assertEquals(2041, $t->exchange_rate);
    }

    public function test_a_dollar_fixed_expense_repeats_with_each_days_rate(): void
    {
        $this->fakeApi();
        Carbon::setTestNow('2026-11-14 10:00');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/transactions', $this->expense($user, [
            'currency_code' => 'USD', 'exchange_rate' => 2014, 'is_fixed' => 1, 'transaction_date' => '2026-11-14',
        ]))->assertSessionHasNoErrors();

        // Diciembre: la repetición del 14 toma la del 14/12.
        Carbon::setTestNow('2026-12-01 08:00');
        $this->artisan('recurring:run')->assertSuccessful();

        $december = Transaction::whereDate('transaction_date', '2026-12-14')->sole();
        $this->assertSame('USD', $december->currency_code);
        // Generada por adelantado (el 14 todavía no llegó): la cotización de hoy, 1/12.
        $this->assertEquals(2001, $december->exchange_rate);
        $this->assertEquals(2001, RecurringTransaction::sole()->exchange_rate);
    }

    public function test_when_the_api_fails_the_repetition_keeps_the_series_rate(): void
    {
        Carbon::setTestNow('2026-11-14 10:00');
        $user = User::factory()->create();
        Http::fake([self::API.'/*' => Http::response([], 500)]);

        $this->actingAs($user)->post('/transactions', $this->expense($user, [
            'currency_code' => 'USD', 'exchange_rate' => 2014, 'is_fixed' => 1, 'transaction_date' => '2026-11-14',
        ]))->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-12-01 08:00');
        $this->artisan('recurring:run');

        $this->assertEquals(2014, Transaction::whereDate('transaction_date', '2026-12-14')->sole()->exchange_rate);
    }
}
