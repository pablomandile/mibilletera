import { computed, onMounted, onUnmounted, ref } from 'vue';

const STANDALONE = '(display-mode: standalone)';

/**
 * iOS/iPadOS Safari nunca dispara `beforeinstallprompt`: ahí la instalación es
 * manual (Compartir → Agregar a inicio), así que hay que detectarlo aparte.
 */
function detectIosSafari() {
    const ua = navigator.userAgent;
    const isIosDevice =
        /iPad|iPhone|iPod/.test(ua) ||
        // iPadOS se reporta como Mac; se distingue por el touch.
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // Chrome/Firefox/Edge en iOS usan WebKit pero no pueden instalar PWAs.
    return isIosDevice && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);
}

function detectInstalled() {
    return (
        window.matchMedia?.(STANDALONE).matches === true ||
        window.navigator.standalone === true || // iOS
        window.__pwaInstall?.installed === true
    );
}

/**
 * Estado del botón "Instalar app".
 *
 * El evento `beforeinstallprompt` lo captura un script en el <head> (dispara antes
 * de que monte Vue) y lo deja en `window.__pwaInstall`.
 */
export function usePwaInstall() {
    const isInstalled = ref(detectInstalled());
    const isIos = detectIosSafari();

    // El navegador ofreció instalar en algún momento. Se mantiene en true aunque
    // el prompt ya se haya consumido, para poder caer en las instrucciones manuales.
    const offered = ref(!!window.__pwaInstall?.prompt);

    const canInstall = computed(() => !isInstalled.value && (offered.value || isIos));

    const onInstallable = () => (offered.value = true);
    const onInstalled = () => (isInstalled.value = true);
    const onDisplayModeChange = (e) => (isInstalled.value = e.matches);

    const mq = window.matchMedia?.(STANDALONE);

    onMounted(() => {
        window.addEventListener('pwa:installable', onInstallable);
        window.addEventListener('pwa:installed', onInstalled);
        mq?.addEventListener?.('change', onDisplayModeChange);
    });

    onUnmounted(() => {
        window.removeEventListener('pwa:installable', onInstallable);
        window.removeEventListener('pwa:installed', onInstalled);
        mq?.removeEventListener?.('change', onDisplayModeChange);
    });

    /**
     * Lanza el diálogo nativo de instalación.
     * @returns {Promise<'accepted'|'dismissed'|'manual'>} 'manual' si el navegador
     *          no ofrece instalación automática y hay que explicar los pasos.
     */
    async function install() {
        const deferred = window.__pwaInstall?.prompt;
        if (!deferred) return 'manual';

        // El evento sirve una sola vez.
        window.__pwaInstall.prompt = null;

        deferred.prompt();
        const { outcome } = await deferred.userChoice;
        return outcome;
    }

    return { canInstall, isInstalled, isIos, install };
}
