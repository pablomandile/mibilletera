import { reactive } from 'vue';

// Estado singleton compartido por toda la app.
const state = reactive({
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Confirmar',
    cancelLabel: 'Cancelar',
    danger: false,
});

let resolver = null;

/**
 * Abre el diálogo de confirmación y devuelve una promesa que resuelve
 * `true` (confirmó) o `false` (canceló).
 *
 * @param {{title?: string, message?: string, confirmLabel?: string, cancelLabel?: string, danger?: boolean}} options
 * @returns {Promise<boolean>}
 */
export function confirmAction(options = {}) {
    state.title = options.title ?? '¿Confirmás la acción?';
    state.message = options.message ?? '';
    state.confirmLabel = options.confirmLabel ?? 'Confirmar';
    state.cancelLabel = options.cancelLabel ?? 'Cancelar';
    state.danger = options.danger ?? false;
    state.open = true;

    return new Promise((resolve) => {
        resolver = resolve;
    });
}

// Usado por el componente <ConfirmDialog> para leer el estado y responder.
export function useConfirmDialog() {
    const respond = (value) => {
        state.open = false;
        const r = resolver;
        resolver = null;
        if (r) r(value);
    };

    return { state, respond };
}
