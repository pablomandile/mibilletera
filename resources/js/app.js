import '../css/app.css';
import './bootstrap';
import 'primeicons/primeicons.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';
import MiBilleteraPreset from './theme';
import { initConnection, offline } from './offline/connection';
import { warmup, syncCachedUser } from './offline/warmup';
import { loadOutbox } from './offline/outbox';
import { initSync, syncNow } from './offline/sync';

const appName = import.meta.env.VITE_APP_NAME || 'Mi Billetera';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        startOffline(props.initialPage);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: MiBilleteraPreset,
                    options: {
                        darkModeSelector: '.dark',
                        cssLayer: {
                            name: 'primevue',
                            order: 'tailwind-base, primevue, tailwind-utilities',
                        },
                    },
                },
                locale: {
                    dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                    dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                    dayNamesMin: ['D', 'L', 'M', 'M', 'J', 'V', 'S'],
                    monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                    monthNamesShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    today: 'Hoy',
                    clear: 'Limpiar',
                    weekHeader: 'Sm',
                    firstDayOfWeek: 1,
                },
            })
            .use(ToastService)
            .use(ConfirmationService)
            .directive('tooltip', Tooltip)
            .mount(el);
    },
    progress: {
        color: '#f59e0b',
    },
});

// ---- Sin conexión ----
// Ver public/sw.js: el SW guarda el JSON de cada pantalla y lo sirve sin red.
function startOffline(initialPage) {
    initConnection();

    // Pantalla que nunca se guardó y sin red: el SW contesta 503 con este
    // header. Un toast en vez del modal de error de Inertia.
    router.on('invalid', (event) => {
        if (event.detail.response?.headers?.['x-offline-miss']) {
            event.preventDefault();
            window.dispatchEvent(new CustomEvent('offline:miss'));
        }
    });

    // Versión de assets vigente: hace falta para que el server no conteste 409
    // a los pedidos del precalentado.
    let currentVersion = initialPage.version;
    let currentUser = initialPage.props.auth?.user?.id ?? null;
    router.on('navigate', (event) => {
        currentVersion = event.detail.page.version;
        // El logout (y el login) llegan por XHR, sin recargar la página: acá es
        // donde se nota que cambió el usuario y hay que borrar lo guardado.
        const user = event.detail.page.props.auth?.user?.id ?? null;
        syncCachedUser(user);
        if (user !== currentUser) {
            currentUser = user;
            // La cola no se borra al salir: es de cada usuario y espera a que
            // vuelva a entrar (por ejemplo, si la sesión venció sin conexión).
            loadOutbox(user).then(() => syncNow());
        }
    });
    const version = () => currentVersion;

    // Cola de cambios hechos sin conexión. Al sincronizar algo se recarga la
    // pantalla con los datos del server y se refrescan las copias guardadas.
    loadOutbox(currentUser).then(() =>
        initSync({
            onApplied: () => {
                router.reload();
                warmup(version(), { force: true });
            },
        })
    );

    // Al abrir la app en frío sin red, el HTML guardado trae los datos de cuando
    // se guardó ese HTML; el JSON de la pantalla puede ser más nuevo.
    // Después del mount: antes, el router todavía no tiene la página inicial.
    if (offline.value) setTimeout(() => router.reload(), 0);

    window.addEventListener('online', () => warmup(version()));

    // Con el SW ya controlando la pestaña (la primera visita todavía no).
    navigator.serviceWorker?.ready.then(async () => {
        await syncCachedUser(initialPage.props.auth?.user?.id ?? null);
        if (initialPage.props.auth?.user) warmup(version());
    });
}

// Registro del service worker (PWA instalable).
//
// El ?v= no es decorativo: el CDN de Hostinger tenía cacheado /sw.js a secas por
// 7 días y servía una versión vieja, así que las actualizaciones del SW no
// llegaban. public/.htaccess ya manda no-cache para sw.js, pero eso solo aplica a
// entradas nuevas del borde; con la URL versionada salteamos la vieja.
// De acá en más no hace falta tocar este número: con el no-cache puesto, el
// navegador detecta los cambios del archivo en el chequeo normal de update.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js?v=2').catch(() => {});
    });
}
