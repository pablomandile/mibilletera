<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0d0d0f">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Mi Billetera">

        {{-- El ?v= es obligatorio al cambiar un ícono: la URL es fija y el navegador
             (sobre todo Chrome mobile) la cachea por días. Subilo junto con el número
             de CACHE en public/sw.js y con el ?v= del manifest. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png?v=2">
        <link rel="icon" type="image/png" href="/icons/icon-192.png?v=2">
        <link rel="shortcut icon" href="/favicon.ico?v=2">

        <title inertia>{{ config('app.name', 'Mi Billetera') }}</title>

        {{-- Aplica el tema (oscuro por defecto) y el tamaño de letra antes del primer paint para evitar el parpadeo --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('theme');
                    if (t === 'light') {
                        document.documentElement.classList.remove('dark');
                    } else {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {
                    document.documentElement.classList.add('dark');
                }
                try {
                    if (localStorage.getItem('fontSize') === 'large') {
                        document.documentElement.dataset.fontSize = 'large';
                    }
                } catch (e) {
                    /* modo privado: queda el tamaño normal */
                }
            })();
        </script>

        {{-- Chrome dispara `beforeinstallprompt` antes de que monte Vue, así que hay que
             capturarlo acá o el botón de instalar se pierde el evento. --}}
        <script>
            (function () {
                window.__pwaInstall = { prompt: null, installed: false };

                window.addEventListener('beforeinstallprompt', function (e) {
                    e.preventDefault(); // evita el banner automático: lo disparamos desde el botón
                    window.__pwaInstall.prompt = e;
                    window.dispatchEvent(new CustomEvent('pwa:installable'));
                });

                window.addEventListener('appinstalled', function () {
                    window.__pwaInstall.prompt = null;
                    window.__pwaInstall.installed = true;
                    window.dispatchEvent(new CustomEvent('pwa:installed'));
                });
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-surface-50 dark:bg-surface-950 text-surface-900 dark:text-surface-100">
        @inertia
    </body>
</html>
