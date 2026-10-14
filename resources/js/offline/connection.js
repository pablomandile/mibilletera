import { ref } from 'vue';

// Estado de conexión que ve la app. `navigator.onLine` solo sabe si hay una red
// conectada, no si llega al servidor (wifi sin internet, datos agotados), así
// que también cuenta lo que avisa el service worker: si la última pantalla
// salió de la caché, se está sin conexión aunque el sistema diga que no.
export const offline = ref(typeof navigator !== 'undefined' && navigator.onLine === false);

// Fecha (ISO) de la copia guardada que se está mostrando, si se sabe.
export const cachedAt = ref(null);

export function initConnection() {
    window.addEventListener('online', () => {
        offline.value = false;
        cachedAt.value = null;
    });
    window.addEventListener('offline', () => (offline.value = true));

    navigator.serviceWorker?.addEventListener('message', (event) => {
        if (event.data?.type !== 'data-source') return;
        offline.value = event.data.offline;
        cachedAt.value = event.data.offline ? event.data.cachedAt ?? null : null;
    });
    // Con addEventListener (a diferencia de onmessage) los mensajes del SW
    // quedan en cola hasta que se pide explícitamente que empiecen a llegar.
    navigator.serviceWorker?.startMessages();
}
