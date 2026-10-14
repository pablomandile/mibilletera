import { ref } from 'vue';
import axios from 'axios';
import { ops, removeOps, markFailed } from './outbox';
import { offline } from './connection';
import { notify } from './notices';

// Manda la cola a POST /sync. Se dispara al arrancar, al volver la conexión, al
// volver la app a primer plano, cada 30 s mientras haya pendientes y apenas se
// encola algo. iOS no tiene Background Sync, así que solo corre con la app
// abierta (en Android tampoco se usa, para que funcione igual en los dos).

const BATCH = 50;
const EVERY_MS = 30 * 1000;

export const syncing = ref(false);
// Sesión vencida: los cambios se guardan hasta que vuelva a iniciar sesión.
export const needsLogin = ref(false);

let running = false;
let again = false;
let onApplied = () => {};

export function initSync(options = {}) {
    onApplied = options.onApplied ?? onApplied;

    window.addEventListener('online', () => syncNow());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') syncNow();
    });
    setInterval(() => {
        if (ops.value.some((o) => o.status !== 'error')) syncNow();
    }, EVERY_MS);

    syncNow();
}

export async function syncNow() {
    if (running) {
        again = true;
        return;
    }
    const queue = ops.value.filter((o) => o.status !== 'error');
    if (!queue.length) return;

    running = true;
    syncing.value = true;
    let applied = 0;
    let failed = 0;

    try {
        for (let i = 0; i < queue.length; i += BATCH) {
            const results = await send(queue.slice(i, i + BATCH));
            const ok = results.filter((r) => r.status === 'ok').map((r) => r.op_id);
            const errors = results.filter((r) => r.status === 'error');
            // `blocked` (depende de un alta que todavía no entró) queda pendiente.
            await removeOps(ok);
            await markFailed(errors);
            applied += ok.length;
            failed += errors.length;
        }
        offline.value = false;
        needsLogin.value = false;
    } catch (e) {
        if (!e.response) {
            offline.value = true;
        } else if ([401, 419].includes(e.response.status)) {
            if (!needsLogin.value) notify('Iniciá sesión para sincronizar', 'warn', 'Tus cambios sin conexión siguen guardados.');
            needsLogin.value = true;
        }
    } finally {
        running = false;
        syncing.value = false;
    }

    if (applied) {
        notify(applied === 1 ? '1 cambio sincronizado' : `${applied} cambios sincronizados`);
        onApplied();
    }
    if (failed) {
        notify(
            failed === 1 ? 'Un cambio no se pudo aplicar' : `${failed} cambios no se pudieron aplicar`,
            'warn',
            'Revisalos en Ajustes → Cambios sin sincronizar.'
        );
    }
    if (again) {
        again = false;
        syncNow();
    }
}

async function send(batch) {
    const form = new FormData();
    form.append(
        'ops',
        JSON.stringify(batch.map(({ op_id, entity, action, id, temp_id, data }) => ({ op_id, entity, action, id, temp_id, data })))
    );
    for (const op of batch) {
        if (op.file) form.append(`files[${op.op_id}]`, op.file, op.fileName || 'archivo');
    }

    try {
        return (await axios.post('/sync', form)).data.results;
    } catch (e) {
        // 419: el token CSRF venció (la app estuvo mucho tiempo abierta). Pedir
        // cualquier página renueva la cookie XSRF-TOKEN que lee axios.
        if (e.response?.status !== 419) throw e;
        await fetch('/settings', { credentials: 'same-origin', headers: { Accept: 'text/html' } });
        return (await axios.post('/sync', form)).data.results;
    }
}
