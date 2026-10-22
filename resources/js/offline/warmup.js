import { offline } from './connection';

// Precalentado: con conexión, se piden en segundo plano las pantallas de los
// menús para que el service worker las guarde y se puedan abrir sin red aunque
// nunca se hayan visitado. Como mucho cada 10 minutos, para no cargar el server.
const EVERY_MS = 10 * 60 * 1000;
const STAMP_KEY = 'offline:warmup-at';

const PAGES = [
    '/dashboard',
    '/charts',
    '/reports',
    '/settings',
    '/transactions/create',
    '/accounts',
    '/categories',
    '/supercategories',
    '/budgets',
    '/recurring',
    '/reminders',
];

// En hora local: toISOString() es UTC y a la noche del último día ya da el mes siguiente.
const monthParam = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;

function pageUrls() {
    const now = new Date();
    const prev = new Date(now.getFullYear(), now.getMonth() - 1, 15);
    // El selector de mes del Inicio navega con ?month=, que es otra clave de caché.
    return [...PAGES, `/dashboard?month=${monthParam(now)}`, `/dashboard?month=${monthParam(prev)}`];
}

function due() {
    try {
        return Date.now() - Number(localStorage.getItem(STAMP_KEY) || 0) > EVERY_MS;
    } catch (e) {
        return true;
    }
}

export async function warmup(version, { force = false } = {}) {
    if (offline.value || !navigator.serviceWorker?.controller) return;

    // El bundle nuevo de un deploy: el SW guarda los chunks que le falten.
    navigator.serviceWorker.controller.postMessage({ type: 'precache' });

    if (!force && !due()) return;
    try {
        localStorage.setItem(STAMP_KEY, String(Date.now()));
    } catch (e) {
        /* modo privado: precalienta en cada arranque, no es grave */
    }

    // El HTML de arranque, para poder abrir la app en frío sin red.
    await fetch('/dashboard', { headers: { Accept: 'text/html' }, credentials: 'same-origin' }).catch(() => {});

    // De a una: son pocas y así no compiten con lo que el usuario esté haciendo.
    for (const url of pageUrls()) {
        const ok = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': version ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                // Para que AceptoTerminos conteste 409 sin pisar a dónde vuelve el usuario.
                'X-Precalentado': '1',
                Accept: 'text/html, application/xhtml+xml',
            },
        })
            .then((r) => r.ok)
            .catch(() => false);
        // 409 = hubo un deploy y esta pestaña tiene el bundle viejo; se reintenta
        // en el próximo arranque. Sin red, no tiene sentido seguir.
        if (!ok) break;
    }
}

// Logout o cambio de usuario: se borran los datos guardados del anterior.
const USER_KEY = 'offline:user';

export async function syncCachedUser(userId) {
    let previous = null;
    try {
        previous = localStorage.getItem(USER_KEY);
        if (userId) localStorage.setItem(USER_KEY, String(userId));
        else localStorage.removeItem(USER_KEY);
    } catch (e) {
        /* sin localStorage no se puede saber: se limpia por las dudas */
    }
    if (previous !== (userId ? String(userId) : null)) await clearCachedData();
}

// Espera a que el SW termine de borrar: si no, el precalentado que viene después
// podría guardar algo y que el borrado se lo lleve.
export function clearCachedData() {
    try {
        localStorage.removeItem(STAMP_KEY);
    } catch (e) {
        /* nada */
    }
    const sw = navigator.serviceWorker?.controller;
    if (!sw) return Promise.resolve();

    return new Promise((resolve) => {
        const channel = new MessageChannel();
        channel.port1.onmessage = () => resolve();
        sw.postMessage({ type: 'clear-data' }, [channel.port2]);
        setTimeout(resolve, 3000); // un SW colgado no traba el arranque
    });
}
