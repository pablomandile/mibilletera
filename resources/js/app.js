import '../css/app.css';
import './bootstrap';
import 'primeicons/primeicons.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';
import MiBilleteraPreset from './theme';

const appName = import.meta.env.VITE_APP_NAME || 'Mi Billetera';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
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
