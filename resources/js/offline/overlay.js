import { ops } from './outbox';

// Cambios pendientes aplicados sobre lo que mandó el server (o la copia
// guardada), para que la pantalla los muestre ya, sin esperar a sincronizar.
// Cada operación de la cola trae su `view`: la fila tal como la dibuja el
// listado. Lo pendiente lleva `_pending: true` para marcarlo.
//
// Al sincronizar la cola se vacía y la página se recarga del server, así que
// esto nunca reemplaza al cálculo de verdad: solo lo adelanta.

// Lo que el server rechazó no se muestra como hecho: se ve la verdad del server
// y el cambio queda en Ajustes → "Cambios sin sincronizar".
const opsOf = (entity) => ops.value.filter((o) => o.entity === entity && o.status !== 'error');
const sameId = (a, b) => a != null && b != null && String(a) === String(b);

/**
 * Altas, ediciones y bajas pendientes sobre una lista plana. `accept` decide si
 * una fila corresponde a esta lista (otro mes, otro filtro, archivada…).
 */
export function applyList(items, entity, { accept = () => true } = {}) {
    let list = [...items];
    for (const op of opsOf(entity)) {
        if (op.action === 'create') {
            const row = { ...op.view, id: op.temp_id, _pending: true };
            if (op.view && accept(row)) list.push(row);
        } else if (op.action === 'update') {
            const i = list.findIndex((x) => sameId(x.id, op.id));
            const row = { ...(i >= 0 ? list[i] : {}), ...op.view, id: op.id, _pending: true };
            if (i >= 0) list.splice(i, 1);
            // Una edición puede traerla a esta lista (le cambiaron la fecha al mes
            // que se está viendo) o sacarla.
            if (op.view && accept(row)) list.splice(i >= 0 ? i : list.length, 0, row);
        } else if (op.action === 'delete') {
            list = list.filter((x) => !sameId(x.id, op.id));
        }
    }
    return list;
}

// ---- Movimientos ----

const baseAmount = (t) => Number(t.amount) * Number(t.exchange_rate ?? 1);

/** Inicio: listado del mes, totales del mes y subtotales de cada día. */
export function overlayDashboard({ transactions, totals, dayTotals, period, filter }) {
    const inMonth = (t) => typeof t?.date === 'string' && t.date.startsWith(period.month);
    const nextTotals = { ...totals };
    const nextDays = Object.fromEntries(Object.entries(dayTotals ?? {}).map(([d, v]) => [d, { ...v }]));

    // Los totales no dependen del filtro del listado, igual que en el server.
    const count = (t, sign) => {
        if (!t || !inMonth(t) || t.type === 'transfer') return;
        const value = sign * baseAmount(t);
        nextTotals[t.type] += value;
        nextDays[t.date] ??= { income: 0, expense: 0 };
        nextDays[t.date][t.type] += value;
    };
    for (const op of opsOf('transaction')) {
        if (op.action !== 'create') count(op.before, -1);
        if (op.action !== 'delete') count(op.view, +1);
    }
    nextTotals.balance = nextTotals.income - nextTotals.expense;

    const list = applyList(transactions, 'transaction', {
        accept: (t) => inMonth(t) && (!filter || t.type === filter),
    });
    // Más nuevo primero, como el server; dentro del mismo día, lo pendiente arriba.
    list.sort((a, b) => b.date.localeCompare(a.date) || Number(!!b._pending) - Number(!!a._pending));

    return { transactions: list, totals: nextTotals, dayTotals: nextDays };
}

// Cuánto mueve un movimiento el saldo de cada cuenta.
function moveBalances(t, sign, add) {
    if (!t) return;
    const amount = Number(t.amount);
    if (t.type === 'expense') add(t.account_id, -sign * amount);
    else if (t.type === 'income') add(t.account_id, sign * amount);
    else if (t.type === 'transfer') {
        add(t.account_id, -sign * amount);
        add(t.to_account_id, sign * Number(t.transfer_amount ?? amount));
    }
}

// ---- Cuentas ----

/** Pantalla Cuentas: altas/ediciones y el saldo corregido por los movimientos pendientes. */
export function overlayAccounts(accounts) {
    const original = new Map(accounts.map((a) => [String(a.id), a]));
    const deltas = {};
    const add = (id, value) => {
        if (id != null) deltas[String(id)] = (deltas[String(id)] ?? 0) + value;
    };
    for (const op of opsOf('transaction')) {
        if (op.action !== 'create') moveBalances(op.before, -1, add);
        if (op.action !== 'delete') moveBalances(op.view, +1, add);
    }

    return applyList(accounts, 'account').map((a) => {
        const before = original.get(String(a.id));
        // Alta: arranca en el saldo inicial. Edición: el saldo se corre lo mismo
        // que el saldo inicial.
        let balance = before
            ? before.balance + (Number(a.initial_balance) - Number(before.initial_balance))
            : Number(a.initial_balance ?? 0);
        balance += deltas[String(a.id)] ?? 0;
        return { ...a, balance };
    });
}

/** Cuentas para elegir en un formulario (sin las archivadas). */
export const overlayAccountOptions = (accounts) =>
    applyList(accounts, 'account', { accept: (a) => !a.is_archived });

// ---- Categorías ----

/** Pantalla Categorías: árbol de categorías con sus subcategorías. */
export function overlayCategoryTree(categories) {
    let tree = categories.map((c) => ({ ...c, children: [...(c.children ?? [])] }));
    for (const op of opsOf('category')) {
        if (op.action === 'create') {
            const node = { ...op.view, id: op.temp_id, children: [], _pending: true };
            if (op.view?.parent_id) tree.find((c) => sameId(c.id, op.view.parent_id))?.children.push(node);
            else tree.push(node);
        } else if (op.action === 'update') {
            for (const list of [tree, ...tree.map((c) => c.children)]) {
                const i = list.findIndex((x) => sameId(x.id, op.id));
                if (i >= 0) list[i] = { ...list[i], ...op.view, id: op.id, _pending: true };
            }
        } else if (op.action === 'delete') {
            tree = tree.filter((c) => !sameId(c.id, op.id));
            tree.forEach((c) => (c.children = c.children.filter((x) => !sameId(x.id, op.id))));
        }
    }
    return tree;
}

/** Categorías principales para elegir en un formulario. */
export const overlayCategoryOptions = (categories) =>
    applyList(categories, 'category', { accept: (c) => !c.parent_id });

// ---- Presupuestos ----

export function overlayBudgets(categories, global) {
    let list = applyList(categories, 'category', {
        accept: (c) => !c.parent_id && c.type === 'expense',
    }).map((c) => ({ budget: null, budget_id: null, spent: 0, ...c }));
    let nextGlobal = { ...global };

    for (const op of opsOf('budget')) {
        if (op.action === 'create') {
            const amount = Number(op.data.amount);
            if (op.data.category_id == null) {
                nextGlobal = { ...nextGlobal, amount, id: nextGlobal.id ?? op.temp_id, _pending: true };
            } else {
                list = list.map((c) =>
                    sameId(c.id, op.data.category_id)
                        ? { ...c, budget: amount, budget_id: c.budget_id ?? op.temp_id, _pending: true }
                        : c
                );
            }
        } else if (op.action === 'delete') {
            if (sameId(nextGlobal.id, op.id)) nextGlobal = { ...nextGlobal, amount: null, id: null };
            list = list.map((c) => (sameId(c.budget_id, op.id) ? { ...c, budget: null, budget_id: null } : c));
        }
    }
    return { categories: list, global: nextGlobal };
}

// ---- Preferencias ----

/** Último valor pendiente de una preferencia (o undefined si no hay). */
export function pendingPreference(key) {
    const op = opsOf('preferences').findLast((o) => o.data?.[key] !== undefined);
    return op?.data[key];
}
