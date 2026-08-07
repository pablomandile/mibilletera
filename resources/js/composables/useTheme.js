import { ref } from 'vue';

// Estado singleton del tema, compartido por el botón del header y la fila de Ajustes.
// El valor inicial lo pone el script anti-parpadeo del <head>, que ya aplicó la
// clase antes del primer paint; acá solo lo leemos.
const isDark = ref(
    typeof document !== 'undefined' ? document.documentElement.classList.contains('dark') : true
);

function apply(dark) {
    isDark.value = dark;
    document.documentElement.classList.toggle('dark', dark);

    // La barra del navegador (y de la PWA instalada) sigue al tema.
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', dark ? '#0d0d0f' : '#ffffff');

    try {
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    } catch (e) {
        /* modo privado: se pierde la preferencia, no es crítico */
    }
}

export function useTheme() {
    return {
        isDark,
        toggleTheme: () => apply(!isDark.value),
        setTheme: (value) => apply(value),
    };
}
