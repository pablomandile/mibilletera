import { ref } from 'vue';

// Tamaño de letra: "normal" o "large". El script anti-parpadeo del <head> ya puso
// el atributo data-font-size antes del primer paint; acá solo lo leemos.
const STORAGE_KEY = 'fontSize';

const isLarge = ref(
    typeof document !== 'undefined' && document.documentElement.dataset.fontSize === 'large'
);

function apply(large) {
    isLarge.value = large;
    if (large) document.documentElement.dataset.fontSize = 'large';
    else delete document.documentElement.dataset.fontSize;

    try {
        localStorage.setItem(STORAGE_KEY, large ? 'large' : 'normal');
    } catch (e) {
        /* modo privado: se pierde la preferencia, no es crítico */
    }
}

export function useFontSize() {
    return {
        isLarge,
        toggleFontSize: () => apply(!isLarge.value),
    };
}
