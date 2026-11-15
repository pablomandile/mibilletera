<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DollarRates;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Ajustes → Cotización del dólar: cada usuario elige su dólar para gastos e
 * ingresos; la API (dirección y clave) es una sola y la carga un admin.
 */
class DollarSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.test/v1';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 12:00:00');
        config(['services.bambudev.url' => self::API, 'services.bambudev.key' => 'clave-env']);
        $this->seed(CurrencySeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => User::ROLE_ADMIN])->save();

        return $user;
    }

    /** /dolares con todos los tipos; /dolares/{tipo}/{fecha} con una venta por tipo. */
    private function fakeApi(string $base = self::API, string $key = 'clave-env'): void
    {
        $sales = ['oficial' => 1500, 'tarjeta' => 1950, 'blue' => 1430, 'mep' => 1480];
        Http::fake([
            $base.'/*' => function (Request $request) use ($sales, $key) {
                if ($request->header('X-Api-Key')[0] !== $key) {
                    return Http::response(['message' => 'clave'], 401);
                }
                $path = parse_url($request->url(), PHP_URL_PATH);
                if (str_ends_with($path, '/dolares')) {
                    return Http::response(['cotizaciones' => collect($sales)
                        ->map(fn ($venta, $tipo) => ['tipo' => $tipo, 'nombre' => strtoupper($tipo), 'venta' => $venta])
                        ->values()->all()]);
                }
                [, $tipo, $fecha] = array_slice(explode('/', $path), -3);

                return Http::response(['tipo' => $tipo, 'nombre' => strtoupper($tipo), 'venta' => $sales[$tipo] ?? 1, 'fecha' => $fecha]);
            },
        ]);
    }

    public function test_the_page_lists_the_types_from_the_api_and_hides_the_api_from_users(): void
    {
        $this->fakeApi();

        $this->actingAs(User::factory()->create())->get('/settings/dollar')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Dollar')
                ->where('live', true)
                ->has('types', 4)
                ->where('types.1', ['tipo' => 'tarjeta', 'nombre' => 'TARJETA', 'venta' => 1950])
                ->where('expense', 'tarjeta')
                ->where('income', 'blue')
                ->where('isAdmin', false)
                ->missing('api'));
    }

    public function test_without_api_the_known_types_are_offered(): void
    {
        Http::fake([self::API.'/*' => Http::response([], 500)]);

        $this->actingAs(User::factory()->create())->get('/settings/dollar')
            ->assertInertia(fn (Assert $page) => $page->where('live', false)->has('types', count(DollarRates::KNOWN_TYPES)));
    }

    public function test_each_user_picks_their_dollar_and_the_form_uses_it(): void
    {
        $this->fakeApi();
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/dollar', ['dollar_type_expense' => 'mep', 'dollar_type_income' => 'oficial'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['mep', 'oficial'], [$user->fresh()->dollar_type_expense, $user->fresh()->dollar_type_income]);

        $this->actingAs($user)->get('/transactions/create')
            ->assertInertia(fn (Assert $page) => $page->where('dollarTypes', ['expense' => 'mep', 'income' => 'oficial']));

        // El server, al completar una cotización que no vino, también usa el elegido.
        $this->actingAs($user)->post('/transactions', [
            'type' => 'expense', 'amount' => 10, 'currency_code' => 'USD', 'transaction_date' => '2026-11-18',
            'account_id' => $user->accounts()->first()->id,
            'category_id' => $user->categories()->where('type', 'expense')->first()->id,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(1480, Transaction::sole()->exchange_rate);

        $this->actingAs($user)->patch('/settings/dollar', ['dollar_type_expense' => 'No válido', 'dollar_type_income' => 'blue'])
            ->assertSessionHasErrors('dollar_type_expense');
    }

    public function test_the_admin_loads_the_api_and_it_replaces_the_env_one(): void
    {
        $this->fakeApi('https://otra.test/api', 'clave-nueva');
        $admin = $this->admin();

        $this->actingAs($admin)->put('/settings/dollar/api', ['url' => 'https://otra.test/api', 'key' => 'clave-nueva'])
            ->assertSessionHasNoErrors();

        // Cifrada en la base, y la API nueva es la que se usa.
        $this->assertNotSame('clave-nueva', AppSetting::read(DollarRates::SETTING_KEY));
        $this->assertSame('clave-nueva', AppSetting::readSecret(DollarRates::SETTING_KEY));
        $this->assertSame(1950.0, app(DollarRates::class)->rate('tarjeta', '2026-11-18')['venta']);

        // La página muestra cómo termina la clave, nunca la clave.
        $this->actingAs($admin)->get('/settings/dollar')
            ->assertInertia(fn (Assert $page) => $page
                ->where('api.url', 'https://otra.test/api')
                ->where('api.hasKey', true)
                ->where('api.keyEnd', 'ueva'))
            ->assertDontSee('clave-nueva');
    }

    public function test_a_key_that_does_not_work_is_not_saved(): void
    {
        $this->fakeApi();

        $this->actingAs($this->admin())->put('/settings/dollar/api', ['url' => '', 'key' => 'mala'])
            ->assertSessionHasErrors(['key' => 'La API rechazó la clave.']);

        $this->assertNull(AppSetting::read(DollarRates::SETTING_KEY));
    }

    public function test_saving_the_url_keeps_the_key_and_the_key_can_be_removed(): void
    {
        $this->fakeApi(self::API, 'guardada');
        $admin = $this->admin();
        AppSetting::writeSecret(DollarRates::SETTING_KEY, 'guardada');

        $this->actingAs($admin)->put('/settings/dollar/api', ['url' => self::API, 'key' => ''])->assertSessionHasNoErrors();
        $this->assertSame('guardada', AppSetting::readSecret(DollarRates::SETTING_KEY));

        $this->actingAs($admin)->put('/settings/dollar/api', ['url' => '', 'key' => '', 'clear_key' => true])->assertSessionHasNoErrors();
        $this->assertNull(AppSetting::read(DollarRates::SETTING_KEY));
        $this->assertNull(AppSetting::read(DollarRates::SETTING_URL));
    }

    public function test_only_an_admin_can_change_the_api(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())->put('/settings/dollar/api', ['url' => 'https://malo.test', 'key' => 'x'])
            ->assertForbidden();

        $this->assertNull(AppSetting::read(DollarRates::SETTING_URL));
        Http::assertNothingSent();
    }
}
