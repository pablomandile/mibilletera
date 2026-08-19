import { ref } from 'vue';

// La API de vibración existe en Android (Chrome, Firefox, Samsung Internet) y no
// en iOS: Safari no expone ninguna equivalente, ni en la PWA instalada. La fila
// de Ajustes se muestra igual y avisa "No disponible", porque si no el teclado
// se queda mudo sin explicación y no hay forma de saber desde el teléfono si el
// problema es el dispositivo o la preferencia.
//
// El chequeo de puntero grueso es para no ofrecerlo en escritorio, donde el
// navegador acepta la llamada pero no hay motor que vibre. Va con OR porque
// algunos navegadores Android reportan maxTouchPoints = 0.
const isTouch =
    typeof navigator !== 'undefined' &&
    (navigator.maxTouchPoints > 0 ||
        (typeof matchMedia === 'function' && matchMedia('(any-pointer: coarse)').matches));

const supported =
    typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function' && isTouch;

const STORAGE_KEY = 'haptics';

function readPreference() {
    try {
        return localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch (e) {
        return true; // modo privado: queda activada, no es crítico
    }
}

const enabled = ref(readPreference());

// Duraciones en ms. Cortas para que no cansen la mano, pero no tanto como para
// que el motor no llegue a moverse: abajo de ~15 ms muchos teléfonos (sobre todo
// los de motor rotativo, no los lineales) no alcanzan a producir nada que se
// sienta, y el teclado parece no vibrar.
const PATTERNS = {
    key: 20, // dígito, punto u operador
    back: 35, // borrar, un poco más marcado
    test: 60, // prueba al prender la opción: tiene que sentirse sin dudas
};

function vibrate(pattern = 'key') {
    if (!supported || !enabled.value) return;
    try {
        navigator.vibrate(PATTERNS[pattern] ?? pattern);
    } catch (e) {
        /* algunos navegadores tiran si la pestaña dejó de estar visible */
    }
}

function toggle() {
    enabled.value = !enabled.value;
    try {
        localStorage.setItem(STORAGE_KEY, enabled.value ? 'on' : 'off');
    } catch (e) {
        /* modo privado: se pierde la preferencia */
    }
    if (enabled.value) vibrate('test'); // que se sienta sin dudas al prenderla
}

export function useHaptics() {
    return { supported, enabled, vibrate, toggle };
}
