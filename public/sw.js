// Service worker mínimo de "Mi Billetera".
// Estrategia: network-first para navegaciones (la app necesita el servidor),
// con caché de respaldo para recursos estáticos y una página offline básica.

// Subí este número cada vez que cambien los íconos o el manifest: al activarse,
// el SW borra las caches con otro nombre y así se tiran los archivos viejos.
const CACHE = 'mibilletera-v2';
// Los íconos no se precachean: se guardan solos la primera vez que se piden y
// así no hay que mantener la versión de su URL en dos lugares.
const PRECACHE = ['/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).catch(() => {})
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Solo GET del mismo origen
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    // Solo /build/: los nombres llevan hash de contenido, así que un archivo dado
    // nunca cambia y es seguro servirlo de caché para siempre.
    //
    // Los íconos NO van acá: su URL es fija (/icons/icon-192.png), así que
    // cache-first los dejaba congelados aunque se subieran nuevos. Van por la
    // rama network-first de abajo.
    if (/\/build\//.test(request.url)) {
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

    // Navegaciones y demás GET: network-first con respaldo de caché
    event.respondWith(
        fetch(request)
            .then((resp) => {
                const copy = resp.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy));
                return resp;
            })
            .catch(() => caches.match(request))
    );
});
