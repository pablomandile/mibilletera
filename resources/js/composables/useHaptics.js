import { ref } from 'vue';

// La API de vibración existe en Android (Chrome, Firefox, Samsung Internet) y no
// en iOS: Safari no expone ninguna equivalente, ni en la PWA instalada. Ahí todo
// esto queda en nada, y por eso la fila de Ajustes se esconde: no tiene sentido
// ofrecer un interruptor que no hace nada. Pedimos además pantalla táctil para
// no mostrarlo en la versión de escritorio, donde el navegador acepta la llamada
// pero no hay motor que vibre.
const supported =
    typeof navigator !== 'undefined' &&
    typeof navigator.vibrate === 'function' &&
    navigator.maxTouchPoints > 0;

const STORAGE_KEY = 'haptics';

function readPreference() {
    try {
        return localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch (e) {
        return true; // modo privado: queda activada, no es crítico
    }
}

const enabled = ref(readPreference());

// Duraciones en ms, cortas a propósito: un teclado que vibra fuerte cansa la mano.
const PATTERNS = {
    key: 10, // dígito, punto u operador
    back: 18, // borrar, un poco más marcado
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
    if (enabled.value) vibrate('back'); // que se sienta al prenderla
}

export function useHaptics() {
    return { supported, enabled, vibrate, toggle };
}
