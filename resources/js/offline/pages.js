import { router } from '@inertiajs/vue3';
import { offline } from './connection';
import { ops, isTempId } from './outbox';

/**
 * Las props de una pantalla Inertia sin navegar a ella. Va por el service
 * worker, así que sin red sale de la copia guardada.
 */
export async function loadPageProps(url, version) {
    const resp = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            'X-Inertia': 'true',
            'X-Inertia-Version': version ?? '',
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'text/html, application/xhtml+xml',
        },
    });
    if (!resp.ok) throw new Error(`No se pudo cargar ${url}`);
    return (await resp.json()).props;
}

/**
 * Abre la edición de un movimiento del Inicio. Con conexión es la visita de
 * siempre; sin conexión, o si el movimiento tiene cambios sin sincronizar (el
 * server todavía no los conoce), la pantalla se arma en el cliente con la fila
 * del listado y los datos guardados de "Agregar".
 */
export async function openTransactionEdit(row, version) {
    const hasPending = ops.value.some((o) => o.entity === 'transaction' && String(o.id ?? o.temp_id) === String(row.id));
    if (!offline.value && !isTempId(row.id) && !hasPending) {
        router.visit(route('transactions.edit', row.id));
        return;
    }

    let createProps;
    try {
        createProps = await loadPageProps('/transactions/create', version);
    } catch (e) {
        window.dispatchEvent(new CustomEvent('offline:miss'));
        return;
    }

    router.push({
        url: `/transactions/${row.id}/edit`,
        component: 'Transactions/Create',
        props: (current) => ({
            ...current,
            ...createProps,
            transaction: {
                id: row.id,
                type: row.type,
                amount: row.amount,
                account_id: row.account_id,
                category_id: row.category_id,
                to_account_id: row.to_account_id,
                exchange_rate: row.exchange_rate ?? 1,
                transfer_amount: row.transfer_amount,
                note: row.note,
                transaction_date: row.date,
                photo_url: row.photo_url ?? null,
                currency_code: row.currency_code,
                transfer_currency: row.transfer_currency,
            },
        }),
    });
}
