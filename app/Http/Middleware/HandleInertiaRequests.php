<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Una misma URL contesta el HTML de arranque o el JSON de la página según el
     * header X-Inertia, y lo único que se lo dice a una caché es el Vary que pone
     * Inertia. El CDN de Hostinger lo borra al comprimir con brotli, así que para
     * el navegador las dos respuestas comparten la misma clave: la URL pelada.
     * Si guarda la variante JSON, al restaurar una pestaña descartada aparece el
     * JSON crudo en pantalla.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        // El CDN lo borra, pero se declara igual: es lo correcto y sirve en
        // cualquier intermediario que sí lo respete.
        $response->headers->set('Vary', Header::INERTIA.', Accept-Encoding');

        // `no-store`, no `no-cache`: `no-cache` permite guardar y solo obliga a
        // revalidar, y restaurar una pestaña es una navegación de historial, que
        // saltea la revalidación.
        //
        // Y solo sobre la respuesta XHR, nunca sobre el HTML: `no-store` en el
        // documento principal desactiva el back/forward cache de Chrome y
        // convierte cada "atrás" en una ida completa a la red.
        if ($request->header(Header::INERTIA)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'created_category_id' => fn () => $request->session()->get('created_category_id'),
            ],
            'notifications' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return ['unread' => 0, 'items' => []];
                }

                $items = $user->alerts()->latest()->limit(20)->get();

                return [
                    'unread' => $items->whereNull('read_at')->count(),
                    'items' => $items->map(fn ($a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'body' => $a->body,
                        'read' => $a->read_at !== null,
                        'time_ago' => $a->created_at->diffForHumans(),
                    ])->values(),
                ];
            },
        ];
    }
}
