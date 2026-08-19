import { ref } from 'vue';

// Píxeles que tiene que recorrer el dedo para que sepamos hacia dónde va.
const SLOP = 10;
// Tolerancia para considerar que no hubo desplazamiento y fue un toque.
const TAP = 6;

/**
 * Gesto de una fila de listado: deslizar a la izquierda para una acción y
 * tocar para otra, sin pelearse con el scroll vertical del listado.
 *
 * La clave es no decidir nada hasta saber la dirección del gesto: si el dedo
 * arranca hacia abajo (o el navegador se queda con el gesto para scrollear),
 * la fila se desentiende y no cuenta ni como deslizamiento ni como toque.
 *
 * @param {{min?: number, onTap?: () => void, onRelease?: (dx: number) => void}} options
 */
export function useSwipeRow({ min = -160, onTap = null, onRelease = null } = {}) {
    const dx = ref(0);
    const dragging = ref(false); // hay un dedo apoyado y el gesto sigue vivo
    const swiping = ref(false); // ya sabemos que el gesto es horizontal

    let startX = 0;
    let startY = 0;
    let moved = 0;

    const onDown = (e) => {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        dragging.value = true;
        swiping.value = false;
        startX = e.clientX;
        startY = e.clientY;
        moved = 0;
    };

    const onMove = (e) => {
        if (!dragging.value) return;

        const d = e.clientX - startX;
        const dy = e.clientY - startY;
        moved = Math.max(moved, Math.hypot(d, dy));

        if (!swiping.value) {
            // Va a lo largo: es scroll del listado, soltamos el gesto.
            if (Math.abs(dy) > SLOP && Math.abs(dy) >= Math.abs(d)) {
                dragging.value = false;
                dx.value = 0;
                return;
            }
            // Todavía no se define: esperamos sin mover la fila.
            if (Math.abs(d) <= SLOP) return;

            swiping.value = true;
            e.currentTarget.setPointerCapture?.(e.pointerId);
        }

        dx.value = Math.max(min, Math.min(0, d)); // solo hacia la izquierda
    };

    const onUp = () => {
        if (!dragging.value) return;
        dragging.value = false;

        if (swiping.value) {
            swiping.value = false;
            onRelease?.(dx.value);
            return;
        }

        if (moved < TAP) onTap?.();
        dx.value = 0;
    };

    // El navegador se quedó con el gesto (típicamente para scrollear):
    // no fue toque ni deslizamiento, la fila vuelve a su lugar.
    const onCancel = () => {
        dragging.value = false;
        swiping.value = false;
        dx.value = 0;
    };

    return { dx, dragging, swiping, onDown, onMove, onUp, onCancel };
}
