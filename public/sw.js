// Service worker de "Mi Billetera".
//
// La app tiene que poder abrirse y navegarse sin conexión, con los últimos
// datos que se vieron. Para eso hay tres cachés:
//
//  - CACHE: el bundle de /build/ (precacheado entero, también los chunks de
//    pantallas que todavía no se abrieron), el HTML de arranque e íconos.
//  - DATA_CACHE: el JSON de cada pantalla que manda Inertia. Va aparte del HTML
//    a propósito: es la misma URL con otro cuerpo, y si compartieran caché una
//    pisaría a la otra (ver la nota sobre el CDN más abajo).
//
// La estrategia es siempre network-first para lo que no es /build/: con red se
// ve lo último y se guarda; sin red se sirve lo guardado.

// Subí este número cada vez que cambien los íconos o el manifest: al activarse,
// el SW borra las caches con otro nombre y así se tiran los archivos viejos.
const CACHE = 'mibilletera-v4';
const DATA_CACHE = 'mibilletera-data-v2';
const KEEP = [CACHE, DATA_CACHE];

// Pantalla a la que se cae si se navega sin red a una URL que nunca se guardó.
const FALLBACK_PAGE = '/dashboard';

self.addEventListener('install', (event) => {
    event.waitUntil(precacheBuild().catch(() => {}));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => !KEEP.includes(k)).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('message', (event) => {
    const type = event.data?.type;

    // La app lo pide en cada arranque con red: después de un deploy el bundle
    // trae chunks nuevos, y el SW no se reinstala si sw.js no cambió.
    if (type === 'precache') event.waitUntil(precacheBuild().catch(() => {}));

    // Logout o cambio de usuario: los datos de la pantalla son de la persona,
    // no pueden quedar a mano del próximo que use el teléfono.
    if (type === 'clear-data') {
        event.waitUntil(clearUserData().finally(() => event.ports[0]?.postMessage('done')));
    }
});

// Guarda todos los archivos que lista el manifest de Vite y tira los de builds
// anteriores, que si no se acumulan para siempre.
async function precacheBuild() {
    // El manifest tiene URL fija: se pide siempre a la red, nunca de caché.
    const resp = await fetch('/build/manifest.json', { cache: 'no-store' });
    if (!resp.ok) return;
    const manifest = await resp.json();

    const files = new Set();
    for (const entry of Object.values(manifest)) {
        files.add(`/build/${entry.file}`);
        (entry.css ?? []).forEach((f) => files.add(`/build/${f}`));
        (entry.assets ?? []).forEach((f) => files.add(`/build/${f}`));
    }

    const cache = await caches.open(CACHE);
    const cached = await cache.keys();
    const cachedPaths = new Set(cached.map((r) => new URL(r.url).pathname));

    await Promise.all(
        [...files].filter((f) => !cachedPaths.has(f)).map((f) => cache.add(f).catch(() => {}))
    );

    await Promise.all(
        cached
            .filter((r) => {
                const path = new URL(r.url).pathname;
                return path.startsWith('/build/') && !files.has(path);
            })
            .map((r) => cache.delete(r))
    );
}

async function clearUserData() {
    await caches.delete(DATA_CACHE);
    // El HTML de arranque también lleva los datos de la página embebidos.
    const cache = await caches.open(CACHE);
    const keys = await cache.keys();
    await Promise.all(
        keys.filter((r) => isHtmlEntry(r)).map((r) => cache.delete(r))
    );
}

// Lo que no es bundle, íconos ni manifest es HTML de alguna pantalla.
function isHtmlEntry(request) {
    const path = new URL(request.url).pathname;
    return !/^\/(build|icons)\//.test(path) && !/\.(png|ico|svg|webmanifest|jpe?g|webp)$/.test(path);
}

// Avisa a la pestaña si lo que está viendo es del servidor o de la caché, para
// que muestre (o saque) la franja de "Sin conexión".
async function notify(clientId, message) {
    if (!clientId) return;
    const client = await self.clients.get(clientId);
    client?.postMessage(message);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Solo GET del mismo origen
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    // La descarga de datos personales ("Descargar mis datos") no se guarda nunca:
    // es todo el contenido de la cuenta y no es una pantalla.
    if (new URL(request.url).pathname.startsWith('/exportacion/')) {
        return;
    }

    // /build/ (salvo el manifest, que tiene URL fija): los nombres llevan hash de
    // contenido, así que un archivo dado nunca cambia y se sirve de caché.
    //
    // Los íconos NO van acá: su URL es fija (/icons/icon-192.png), así que
    // cache-first los dejaba congelados aunque se subieran nuevos. Van por la
    // rama network-first de abajo.
    if (/\/build\//.test(request.url) && !request.url.endsWith('/manifest.json')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((resp) => {
                        const copy = resp.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy));
                        return resp;
                    })
            )
        );
        return;
    }

    // XHR de Inertia (cambio de pantalla dentro de la app): el JSON de la página.
    // `mode !== 'navigate'` no sobra: una navegación de verdad nunca debe
    // recibir este JSON (ver más abajo).
    if (request.headers.get('x-inertia') && request.mode !== 'navigate') {
        event.respondWith(inertiaData(request, event.clientId));
        return;
    }

    // Navegaciones y demás GET: network-first con respaldo de caché
    event.respondWith(
        fetch(request)
            .then((resp) => {
                // Respuesta armada para un XHR de Inertia: es el JSON de una
                // página, no un documento. No se guarda nunca en esta caché,
                // porque el CDN borra el `Vary: X-Inertia` y entonces quedaría
                // bajo la misma clave que el HTML de esa URL.
                if (resp.headers.get('x-inertia')) {
                    // Y si además llegó como respuesta a una navegación de verdad
                    // (restaurar una pestaña descartada, el botón "atrás"), salió
                    // de la caché HTTP del navegador envenenada de antes del
                    // parche del middleware: el usuario vería el JSON crudo.
                    // Se pide el HTML de nuevo, saltando esa caché.
                    //
                    // El guard de `mode` no sobra: el router de Inertia manda
                    // `Accept: text/html` en sus XHR, así que sin él esta rama
                    // les devolvería el HTML de arranque y la app dejaría de
                    // navegar.
                    if (request.mode === 'navigate') {
                        return fetch(request.url, {
                            cache: 'reload',
                            headers: { Accept: 'text/html' },
                        }).then((recuperada) =>
                            // Si la sesión venció, esa URL redirige al login. Una
                            // respuesta ya redirigida no se le puede entregar a
                            // una navegación, así que se le pasa el redirect y lo
                            // sigue el navegador.
                            recuperada.redirected
                                ? Response.redirect(recuperada.url, 302)
                                : recuperada
                        );
                    }

                    return resp;
                }

                // Solo respuestas sanas y sin redirect: guardar el login al que
                // redirige una sesión vencida dejaría esa pantalla como la
                // versión "sin conexión" de la página.
                if (resp.ok && !resp.redirected) {
                    const copy = resp.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }
                return resp;
            })
            .catch(async () => {
                const cached = await caches.match(request, { cacheName: CACHE });
                if (cached) return cached;
                // Navegación sin red a una pantalla que nunca se guardó: mejor
                // el Inicio guardado que el dinosaurio de Chrome.
                if (request.mode === 'navigate' && (await caches.match(FALLBACK_PAGE, { cacheName: CACHE }))) {
                    return Response.redirect(FALLBACK_PAGE, 302);
                }
                return Response.error();
            })
    );
});

async function inertiaData(request, clientId) {
    try {
        const resp = await fetch(request);

        // Solo páginas Inertia completas (200 con el header). Un 409 de versión,
        // un 302 al login o un error del server no son "la pantalla". Tampoco un
        // 200 al que se llegó por un redirect: `fetch` sigue el 302 y entrega la
        // pantalla de destino (p. ej. «Aceptar términos») como si fuera la pedida,
        // y sin conexión el inicio mostraría eso.
        if (resp.status === 200 && !resp.redirected && resp.headers.get('x-inertia')) {
            const headers = new Headers(resp.headers);
            headers.set('X-Cached-At', new Date().toISOString());
            const body = await resp.clone().blob();
            const cache = await caches.open(DATA_CACHE);
            await cache.put(request.url, new Response(body, { status: 200, headers }));
        }

        notify(clientId, { type: 'data-source', offline: false });
        return resp;
    } catch (e) {
        const cached = await caches.match(request.url, { cacheName: DATA_CACHE });
        if (cached) {
            notify(clientId, {
                type: 'data-source',
                offline: true,
                cachedAt: cached.headers.get('X-Cached-At'),
            });
            return cached;
        }

        // Pantalla nunca guardada. El header lo reconoce la app (evento
        // `invalid` de Inertia) para mostrar un toast en vez del modal de error.
        notify(clientId, { type: 'data-source', offline: true });
        return new Response('Sin conexión', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8', 'X-Offline-Miss': '1' },
        });
    }
}
