import { ref, computed } from 'vue';
import { openDB } from 'idb';

// Cola de cambios hechos sin conexión, en IndexedDB para que sobreviva a
// cerrar la app. Cada operación es lo que después se manda a POST /sync:
//
//   { op_id, entity, action: create|update|delete, id, temp_id, data, file }
//
// más lo que necesita la app mientras espera:
//
//   view    la fila tal como la muestra su listado (para dibujarla ya)
//   before  cómo era antes de editarla o borrarla (para corregir totales)
//   status  pending | error, y errors con los mensajes del server
//
// Lo creado sin conexión lleva un id temporal "tmp:<uuid>" que el server
// traduce al real (ver SyncController).

const dbPromise =
    typeof indexedDB === 'undefined'
        ? null
        : openDB('mibilletera-offline', 1, {
              upgrade(db) {
                  db.createObjectStore('outbox', { keyPath: 'op_id' });
              },
          });

let userId = null;
let counter = 0;

// Las operaciones del usuario actual, en el orden en que se hicieron.
export const ops = ref([]);
export const failedOps = computed(() => ops.value.filter((o) => o.status === 'error'));

export const newTempId = () => `tmp:${crypto.randomUUID()}`;
export const isTempId = (id) => typeof id === 'string' && id.startsWith('tmp:');

export async function loadOutbox(id) {
    userId = id ?? null;
    await refresh();
}

async function refresh() {
    if (!dbPromise || !userId) {
        ops.value = [];
        return;
    }
    const all = await (await dbPromise).getAll('outbox');
    ops.value = all.filter((o) => o.user_id === userId).sort((a, b) => a.seq - b.seq);
}

const sameTarget = (a, b) => a.entity === b.entity && String(a.id) === String(b.id);
const references = (op, tempId) => op.id === tempId || Object.values(op.data ?? {}).includes(tempId);

/**
 * Agrega un cambio a la cola. Si ya hay uno pendiente sobre lo mismo, lo
 * fusiona: editar algo creado sin conexión cambia el alta, y editar dos veces
 * deja una sola edición. Así la cola no crece con cada toque.
 */
// IndexedDB no puede guardar los proxies reactivos de Vue (las filas de las
// props lo son, también los objetos anidados): se guarda una copia plana. El
// archivo adjunto va aparte, porque un File no pasa por JSON.
const plain = (value) => (value == null ? value : JSON.parse(JSON.stringify(value)));

export async function enqueue(input) {
    const db = await dbPromise;
    let op = { ...input, data: plain(input.data), view: plain(input.view), before: plain(input.before) };
    const queue = ops.value;
    const fresh = { status: 'pending', errors: null };

    if (op.action !== 'create' && isTempId(op.id)) {
        const create = queue.find((o) => o.action === 'create' && o.temp_id === op.id);
        if (create && op.action === 'update') {
            await db.put('outbox', {
                ...create,
                ...fresh,
                data: op.data,
                view: op.view ?? create.view,
                file: op.file ?? create.file,
                fileName: op.fileName ?? create.fileName,
            });
            return refresh();
        }
        // Borrar algo que nunca llegó al server: alcanza con olvidarlo, salvo que
        // otro cambio pendiente lo use (un movimiento en esa categoría nueva).
        if (create && op.action === 'delete' && !queue.some((o) => o !== create && references(o, op.id))) {
            await db.delete('outbox', create.op_id);
            return refresh();
        }
    }

    if (op.action === 'update') {
        const previous = queue.find((o) => o.action === 'update' && sameTarget(o, op));
        if (previous) {
            // Se conserva el `before` original: es el estado que tiene el server.
            await db.put('outbox', {
                ...previous,
                ...fresh,
                data: op.data,
                view: op.view,
                file: op.file ?? previous.file,
                fileName: op.fileName ?? previous.fileName,
            });
            return refresh();
        }
    }

    if (op.action === 'delete') {
        // Las ediciones pendientes de algo que se borra ya no importan, pero el
        // `before` del borrado tiene que ser el del server, no el editado.
        const edits = queue.filter((o) => o.action === 'update' && sameTarget(o, op));
        if (edits.length) op = { ...op, before: edits[0].before ?? op.before };
        for (const edit of edits) await db.delete('outbox', edit.op_id);
    }

    await db.put('outbox', {
        id: null,
        temp_id: null,
        data: {},
        file: null,
        fileName: null,
        view: null,
        before: null,
        ...op,
        ...fresh,
        op_id: crypto.randomUUID(),
        user_id: userId,
        seq: Date.now() * 1000 + (counter++ % 1000),
        createdAt: new Date().toISOString(),
    });
    await refresh();
}

export async function removeOps(opIds) {
    if (!opIds.length) return;
    const db = await dbPromise;
    for (const id of opIds) await db.delete('outbox', id);
    await refresh();
}

export async function markFailed(results) {
    if (!results.length) return;
    const db = await dbPromise;
    for (const { op_id, errors } of results) {
        const op = await db.get('outbox', op_id);
        if (op) await db.put('outbox', { ...op, status: 'error', errors });
    }
    await refresh();
}

// "Reintentar" desde Ajustes: vuelve a la cola normal.
export async function retryOp(opId) {
    const db = await dbPromise;
    const op = await db.get('outbox', opId);
    if (op) await db.put('outbox', { ...op, status: 'pending', errors: null });
    await refresh();
}

// "Descartar": si era un alta, también se van los cambios que dependían de ella
// (sin la categoría nueva, el movimiento cargado en ella no tiene dónde ir).
export function dependentsOf(opId) {
    const out = [];
    const walk = (op) => {
        out.push(op);
        if (!op.temp_id) return;
        ops.value.filter((o) => !out.includes(o) && references(o, op.temp_id)).forEach(walk);
    };
    const op = ops.value.find((o) => o.op_id === opId);
    if (op) walk(op);
    return out;
}

export async function discardOp(opId) {
    await removeOps(dependentsOf(opId).map((o) => o.op_id));
}
