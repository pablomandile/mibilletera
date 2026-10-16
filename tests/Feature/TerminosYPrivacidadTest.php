<?php

namespace Tests\Feature;

use App\Http\Middleware\AceptoTerminos;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TerminosYPrivacidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_registro_exige_aceptar_los_terminos(): void
    {
        $datos = [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $this->post('/register', $datos)->assertSessionHasErrors('terminos');
        $this->assertGuest();

        $this->post('/register', $datos + ['terminos' => '1'])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame(config('legales.version'), $user->terminos_version);
        $this->assertNotNull($user->terminos_aceptados_en);
        $this->assertTrue($user->aceptoTerminosVigentes());
    }

    public function test_una_cuenta_sin_aceptar_es_redirigida_acepta_y_vuelve_a_donde_iba(): void
    {
        $user = User::factory()->sinAceptarTerminos()->create();

        $this->actingAs($user)->get('/accounts')->assertRedirect(route('terminos.aceptar'));

        $this->get(route('terminos.aceptar'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Terminos/Aceptar')
                ->where('primeraVez', true));

        // Sin la casilla no avanza.
        $this->post(route('terminos.aceptar.store'))->assertSessionHasErrors('terminos');

        $this->post(route('terminos.aceptar.store'), ['terminos' => '1'])
            ->assertRedirect('/accounts');

        $this->assertTrue($user->fresh()->aceptoTerminosVigentes());
        $this->get('/accounts')->assertOk();
    }

    public function test_subir_la_version_vuelve_a_preguntar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        config(['legales.version' => '2099-01-01']);

        $this->get('/dashboard')->assertRedirect(route('terminos.aceptar'));
        $this->get(route('terminos.aceptar'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('primeraVez', false));
    }

    public function test_sin_aceptar_igual_se_puede_leer_salir_descargar_borrar_y_sincronizar(): void
    {
        $user = User::factory()->sinAceptarTerminos()->create();
        $this->actingAs($user);

        $this->get(route('terminos'))->assertOk();
        $this->get(route('privacidad'))->assertOk();
        $this->get(route('cookies'))->assertOk();
        $this->get(route('exportacion.datos'))->assertOk();
        $this->post(route('sync'), ['ops' => '[]'])->assertOk();

        $this->post(route('logout'))->assertRedirect('/');
    }

    public function test_sin_aceptar_puede_borrar_la_cuenta(): void
    {
        $user = User::factory()->sinAceptarTerminos()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_un_json_sin_aceptar_recibe_409(): void
    {
        $user = User::factory()->sinAceptarTerminos()->create();

        $this->actingAs($user)
            ->getJson('/accounts')
            ->assertStatus(409);
    }

    public function test_el_precalentado_recibe_409_y_no_pisa_la_vuelta(): void
    {
        $user = User::factory()->sinAceptarTerminos()->create();

        $this->actingAs($user)
            ->withSession(['url.intended' => '/budgets'])
            ->get('/reports', ['X-Precalentado' => '1', 'X-Inertia' => 'true'])
            ->assertStatus(409);

        $this->assertSame('/budgets', session('url.intended'));
    }

    public function test_las_exentas_con_sesion_son_exactamente_las_listadas(): void
    {
        // Todas las rutas con nombre que atiende una cuenta sin aceptar, salvo
        // las que ya son solo para invitados. Si la lista crece sin querer,
        // este test lo dice.
        $exentas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->getName() !== null)
            ->filter(fn ($r) => ! in_array('guest', $r->gatherMiddleware(), true))
            ->map(fn ($r) => $r->getName())
            ->filter(fn ($n) => \Illuminate\Support\Str::is(AceptoTerminos::EXENTAS, $n))
            ->sort()->values()->all();

        $this->assertSame([
            'about',
            'cookies',
            'exportacion.datos',
            'logout',
            'privacidad',
            'profile.destroy',
            'sync',
            'terminos',
            'terminos.aceptar',
            'terminos.aceptar.store',
            'verification.notice',
            'verification.send',
            'verification.verify',
        ], $exentas);
    }

    public function test_una_cuenta_nueva_por_google_cae_en_la_pantalla_de_aceptacion(): void
    {
        // El alta con Google crea el usuario sin versión aceptada.
        $user = User::create([
            'name' => 'Con Google',
            'email' => 'google@example.com',
            'google_id' => '123',
            'password' => bcrypt('x'),
            'email_verified_at' => now(),
        ]);

        $this->assertNull($user->terminos_version);
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('terminos.aceptar'));
    }

    public function test_eliminar_la_cuenta_borra_todo_incluidos_los_archivos(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $cuenta = $user->accounts()->first();

        Storage::disk('public')->put("transactions/{$user->id}/recibo.jpg", 'x');
        Storage::disk('public')->put("categories/{$user->id}/icono.png", 'x');
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $cuenta->id, 'type' => 'expense',
            'amount' => 10, 'currency_code' => 'ARS', 'exchange_rate' => 1,
            'photo_path' => "transactions/{$user->id}/recibo.jpg", 'transaction_date' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('categories', 0);
        Storage::disk('public')->assertMissing("transactions/{$user->id}/recibo.jpg");
        Storage::disk('public')->assertMissing("categories/{$user->id}/icono.png");
    }

    public function test_la_descarga_trae_los_datos_de_la_cuenta_y_no_los_de_otras(): void
    {
        $user = User::factory()->create(['name' => 'Yo']);
        $otro = User::factory()->create(['name' => 'Otro']);

        $respuesta = $this->actingAs($user)->get(route('exportacion.datos'))->assertOk();

        $respuesta->assertHeader('Content-Disposition', 'attachment; filename="mi-billetera-datos.json"');
        $datos = $respuesta->json();

        $this->assertSame('Yo', $datos['cuenta']['name']);
        $this->assertNotEmpty($datos['categorias']);
        $this->assertSame([$user->id], collect($datos['categorias'])->pluck('user_id')->unique()->values()->all());
        $this->assertStringNotContainsString($otro->email, $respuesta->getContent());
        $this->assertArrayNotHasKey('password', $datos['cuenta']);
    }

    public function test_los_textos_cumplen_lo_que_prometen(): void
    {
        $privacidad = (string) file_get_contents(resource_path('legales/privacidad.md'));

        // Lo que la política dice que se puede hacer desde la app existe.
        $this->assertStringContainsString('Descargar mis datos', $privacidad);
        $this->assertStringContainsString('Eliminar mi cuenta', $privacidad);
        $perfil = (string) file_get_contents(resource_path('js/Pages/Profile/Edit.vue'));
        $this->assertStringContainsString('Descargar mis datos', $perfil);
        $this->assertStringContainsString('Eliminar mi cuenta', $perfil);

        // Y la obligación de la AAIP (Disposición 10/2008).
        $this->assertStringContainsString('AGENCIA DE ACCESO A LA INFORMACIÓN PÚBLICA', $privacidad);
        $this->assertStringContainsString('Ley 25.326', $privacidad);
    }

    public function test_el_service_worker_no_guarda_respuestas_redirigidas(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("resp.status === 200 && !resp.redirected && resp.headers.get('x-inertia')", $sw);
        $this->assertStringContainsString("pathname.startsWith('/exportacion/')", $sw);
    }

    public function test_los_textos_se_leen_sin_cuenta(): void
    {
        foreach (['terminos', 'privacidad'] as $ruta) {
            $this->get(route($ruta))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Legal')
                    ->where('auth.user', null)
                    ->has('html'));
        }
    }
}
