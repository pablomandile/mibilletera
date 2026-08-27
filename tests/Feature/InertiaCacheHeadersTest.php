<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sin esta versión el middleware contesta 409 en vez de la página, y el test
     * parece roto cuando no lo está. Requiere que exista public/build/manifest.json.
     */
    private function versionDeInertia(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(request());
    }

    public function test_la_respuesta_xhr_de_inertia_no_se_puede_guardar(): void
    {
        $respuesta = $this->get('/login', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $this->versionDeInertia(),
        ]);

        $respuesta->assertOk();
        $this->assertStringContainsString('application/json', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
    }

    /** El documento HTML sí queda cacheable, o se pierde el back/forward cache de Chrome. */
    public function test_el_documento_html_sigue_siendo_cacheable(): void
    {
        $respuesta = $this->get('/login');

        $respuesta->assertOk();
        $this->assertStringContainsString('text/html', $respuesta->headers->get('Content-Type'));
        $this->assertStringNotContainsString('no-store', $respuesta->headers->get('Cache-Control'));
    }

    public function test_declara_el_vary_que_distingue_las_dos_respuestas(): void
    {
        $this->get('/login')->assertHeader('Vary', 'X-Inertia, Accept-Encoding');
    }
}
