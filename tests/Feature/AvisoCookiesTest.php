<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * El aviso de cookies es informativo porque todo lo que la app guarda en el
 * navegador es necesario. Esa afirmación solo es cierta mientras la página de
 * cookies liste todo: una clave nueva en localStorage que nadie declara la
 * vuelve falsa sin ningún síntoma. Este test busca en el código cada lugar
 * donde se escribe en el navegador y exige que su clave aparezca en la página.
 */
class AvisoCookiesTest extends TestCase
{
    use RefreshDatabase;

    private function fuente(string $ruta): string
    {
        return (string) file_get_contents(base_path($ruta));
    }

    /**
     * Cada nombre que el código usa para guardar algo en el navegador. Las
     * claves armadas con un template literal se toman hasta el primer `${`, y
     * los nombres de caché del service worker sin su número de versión.
     *
     * @return array<string, string> clave => archivo donde se escribe
     */
    private function clavesGuardadasEnElNavegador(): array
    {
        $claves = [];

        $archivos = collect(File::allFiles(resource_path('js')))
            ->filter(fn ($f) => in_array($f->getExtension(), ['js', 'ts', 'vue'], true))
            ->map(fn ($f) => [$f->getRelativePathname(), $f->getContents()])
            ->push(['views/app.blade.php', $this->fuente('resources/views/app.blade.php')]);

        foreach ($archivos as [$nombre, $codigo]) {
            // Constantes del archivo, para resolver `setItem(CLAVE, …)`.
            preg_match_all('/const (\w+)\s*=\s*[\'"`]([^\'"`$]+)/', $codigo, $m, PREG_SET_ORDER);
            $constantes = collect($m)->mapWithKeys(fn ($c) => [$c[1] => $c[2]]);

            preg_match_all('/(?:local|session)Storage\.setItem\(\s*(?:[\'"`]([^\'"`$]+)|(\w+))/', $codigo, $m, PREG_SET_ORDER);
            foreach ($m as $uso) {
                $variable = $uso[2] ?? '';
                $clave = ($uso[1] ?? '') !== '' ? $uso[1] : $constantes->get($variable);
                $this->assertNotNull($clave, "No se pudo resolver la clave {$variable} en {$nombre}");
                $claves[$clave] = $nombre;
            }

            preg_match_all('/setCookie\(\s*[\'"]([\w-]+)[\'"]/', $codigo, $m1);
            preg_match_all('/document\.cookie\s*=\s*[\'"`]([\w-]+)=/', $codigo, $m2);
            preg_match_all('/_COOKIE_NAME\s*=\s*[\'"]([\w-]+)[\'"]/', $codigo, $m3);
            preg_match_all('/openDB\(\s*[\'"]([^\'"]+)[\'"]/', $codigo, $m4);
            foreach ([...$m1[1], ...$m2[1], ...$m3[1], ...$m4[1]] as $clave) {
                $claves[$clave] = $nombre;
            }
        }

        preg_match_all("/const \w+ = '([a-z-]+?)-v\d+';/", $this->fuente('public/sw.js'), $m);
        foreach ($m[1] as $cache) {
            $claves["{$cache}-v"] = 'public/sw.js';
        }

        return $claves;
    }

    public function test_la_pagina_de_cookies_declara_todo_lo_que_el_codigo_guarda_en_el_navegador(): void
    {
        $claves = $this->clavesGuardadasEnElNavegador();

        // Que la búsqueda encuentre lo que ya se sabe que existe: si un cambio
        // de estilo la dejara sin encontrar nada, el test pasaría sin probar
        // nada.
        foreach ([
            'theme', 'fontSize', 'haptics', 'mibilletera:aviso-cookies',
            'offline:warmup-at', 'offline:user', 'mibilletera-offline',
            'mibilletera-v', 'mibilletera-data-v',
        ] as $conocida) {
            $this->assertArrayHasKey($conocida, $claves, "La búsqueda no encontró «{$conocida}»");
        }

        $pagina = $this->fuente('resources/js/Pages/Cookies.vue');

        foreach ($claves as $clave => $archivo) {
            $this->assertTrue(
                str_contains($pagina, $clave),
                "«{$clave}» se guarda en {$archivo} y no figura en la página de cookies",
            );
        }
    }

    public function test_la_pagina_declara_las_cookies_del_servidor(): void
    {
        $pagina = $this->fuente('resources/js/Pages/Cookies.vue');

        $this->assertStringContainsString(config('session.cookie'), $pagina);
        $this->assertStringContainsString('XSRF-TOKEN', $pagina);
        $this->assertStringContainsString('remember_web_', $pagina);
    }

    public function test_la_pagina_declara_los_terceros_que_se_cargan(): void
    {
        $head = $this->fuente('resources/views/app.blade.php');
        preg_match_all('#https://([a-z0-9.-]+)#', $head, $m);

        $pagina = $this->fuente('resources/js/Pages/Cookies.vue');
        $privacidad = $this->fuente('resources/legales/privacidad.md');

        foreach (array_unique($m[1]) as $host) {
            $this->assertStringContainsString($host, $pagina, "{$host} se carga en el <head> y no figura en Cookies");
            $this->assertStringContainsString($host, $privacidad, "{$host} se carga en el <head> y no figura en la política");
        }
    }

    public function test_el_aviso_esta_en_todas_las_puertas_de_entrada(): void
    {
        $this->assertStringContainsString('<AvisoCookies', $this->fuente('resources/js/Layouts/PublicLayout.vue'));
        $this->assertStringContainsString('<AvisoCookies', $this->fuente('resources/js/Layouts/GuestLayout.vue'));
        $this->assertStringContainsString('<AvisoCookies', $this->fuente('resources/js/Layouts/AppLayout.vue'));
    }

    public function test_se_abre_sin_cuenta(): void
    {
        $this->get(route('cookies'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cookies')
                ->where('auth.user', null));
    }

    public function test_se_abre_con_sesion(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('cookies'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Cookies'));
    }
}
