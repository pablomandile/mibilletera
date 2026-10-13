import { ref } from 'vue';

// Tamaño de letra: "small", "normal" (por defecto) o "large". El script
// anti-parpadeo del <head> ya puso data-font-size antes del primer paint (sin
// atributo = normal); acá solo lo leemos. Las escalas están en app.css.
const STORAGE_KEY = 'fontSize';

export const FONT_SIZES = [
    { value: 'small', label: 'Pequeña' },
    { value: 'normal', label: 'Normal' },
    { value: 'large', label: 'Grande' },
];

const fontSize = ref(
    (typeof document !== 'undefined' && document.documentElement.dataset.fontSize) || 'normal'
);

function setFontSize(value) {
    fontSize.value = value;
    if (value === 'normal') delete document.documentElement.dataset.fontSize;
    else document.documentElement.dataset.fontSize = value;

    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch (e) {
        /* modo privado: se pierde la preferencia, no es crítico */
    }
}

export function useFontSize() {
    return { fontSize, setFontSize };
}
