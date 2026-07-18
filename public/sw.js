// Service worker mínimo de "Mi Billetera".
// Estrategia: network-first para navegaciones (la app necesita el servidor),
// con caché de respaldo para recursos estáticos y una página offline básica.

const CACHE = 'mibilletera-v1';
const PRECACHE = [
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/manifest.webmanifest',
];

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

    // Recursos compilados / estáticos: cache-first
    if (/\/(build|icons)\//.test(request.url)) {
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
