import { router } from '@inertiajs/vue3';
import { ops, enqueue, isTempId } from './outbox';
import { offline } from './connection';
import { syncNow } from './sync';
import { notify } from './notices';

/**
 * Único camino para escribir datos, con o sin conexión.
 *
 *   const result = await mutate(
 *       { entity: 'reminder', action: 'update', id, data, view },
 *       (extra) => form.patch(url, { ...opts, ...extra }),
 *   );
 *
 * Con conexión y la cola vacía corre `online`, la llamada Inertia de siempre
 * (mensajes, errores de validación y redirects como antes) y devuelve
 * 'online'. Si no hay conexión, si la llamada se cae por la red, o si todavía
 * quedan cambios en la cola (para no adelantarse a ellos: un movimiento no
 * puede llegar antes que la categoría nueva en la que se cargó), encola y
 * devuelve 'queued'; ahí la pantalla tiene que cerrar el diálogo o volver,
 * como si hubiera salido bien.
 */
export async function mutate(op, online) {
    const canGoOnline =
        online && !offline.value && !ops.value.length && !isTempId(op.id);

    if (canGoOnline) {
        const result = await new Promise((resolve) => {
            let networkError = false;
            // Sin respuesta del server = error de red. Se cancela el evento para
            // que Inertia no lo trate como un error de la página.
            const off = router.on('exception', (event) => {
                if (!event.detail.exception?.response) {
                    event.preventDefault();
                    networkError = true;
                }
            });
            online({
                onFinish: () => {
                    off();
                    resolve(networkError ? 'failed' : 'online');
                },
            });
        });
        if (result === 'online') return 'online';
        offline.value = true;
    }

    await enqueue(op);

    if (offline.value) {
        notify('Guardado sin conexión', 'info', 'Se sincroniza solo cuando vuelva la conexión.');
    } else {
        syncNow();
    }
    return 'queued';
}
