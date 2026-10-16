<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

/*
 * Qué guarda Mi Billetera en el navegador y para qué. Es el inventario
 * completo: `AvisoCookiesTest` busca en el código cada clave que se escribe en
 * el navegador y exige que aparezca acá, así que una clave nueva sin declarar
 * rompe la suite.
 *
 * Se abre con sesión y sin ella.
 */

const GRUPOS = [
    {
        titulo: 'Cookies',
        descripcion: 'Pequeños datos que el navegador manda en cada pedido a Mi Billetera.',
        datos: [
            {
                nombre: 'mi-billetera-session',
                para: 'Mantener tu sesión iniciada mientras usás la app.',
                dura: '2 horas sin actividad',
            },
            {
                nombre: 'XSRF-TOKEN',
                para: 'Comprobar que cada formulario lo enviaste vos desde Mi Billetera y no otro sitio en tu nombre.',
                dura: '2 horas sin actividad',
            },
            {
                nombre: 'remember_web_…',
                para: 'Solo si marcás «Recordarme» o entrás con Google: que no tengas que volver a iniciar sesión en este dispositivo.',
                dura: 'Hasta que salgas de la cuenta',
            },
        ],
    },
    {
        titulo: 'Guardado en este dispositivo',
        descripcion: 'Datos que quedan en el navegador y nunca se mandan solos a ningún lado.',
        datos: [
            {
                nombre: 'theme',
                para: 'El tema, claro u oscuro, que elegiste en este dispositivo.',
                dura: 'Hasta que lo cambies',
            },
            {
                nombre: 'fontSize',
                para: 'El tamaño de letra que elegiste en Ajustes.',
                dura: 'Hasta que lo cambies',
            },
            {
                nombre: 'haptics',
                para: 'Si dejaste activada o no la vibración al tocar.',
                dura: 'Hasta que lo cambies',
            },
            {
                nombre: 'mibilletera:aviso-cookies',
                para: 'Que ya leíste el aviso de cookies, para no volver a mostrártelo.',
                dura: 'Hasta que borres los datos del navegador',
            },
            {
                nombre: 'offline:warmup-at · offline:user',
                para: 'Cuándo se guardaron por última vez las pantallas para usarlas sin conexión, y de qué cuenta son.',
                dura: 'Hasta que salgas de la cuenta',
            },
            {
                nombre: 'mibilletera-offline',
                para: 'Los cambios que hiciste sin conexión (gastos, ingresos, etc.), hasta que se envían al volver la señal. Si salís de la cuenta antes, esperan a que vuelvas a entrar.',
                dura: 'Hasta que se sincronizan',
            },
            {
                nombre: 'mibilletera-v…',
                para: 'La app en sí —diseño, íconos, código—, para que abra rápido y también sin señal. No tiene datos tuyos.',
                dura: 'Hasta la próxima versión de la app',
            },
            {
                nombre: 'mibilletera-data-v…',
                para: 'Las pantallas que ya viste (con tus cuentas y movimientos), para poder consultarlas sin señal.',
                dura: 'Hasta que salgas de la cuenta',
            },
        ],
    },
];

const page = usePage();
const Layout = computed(() => (page.props.auth?.user ? AppLayout : PublicLayout));
</script>

<template>
    <Head title="Cookies y datos guardados" />

    <component :is="Layout" title="Cookies">
        <div class="mx-auto flex max-w-2xl flex-col gap-5">
            <h1 class="text-xl font-semibold hidden md:block">Cookies y datos guardados</h1>

            <div class="rounded-2xl border border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900 p-5">
                <p>
                    Todo lo que Mi Billetera guarda en tu navegador es necesario para que
                    funcione. No hay publicidad, ni estadísticas de uso, ni nada de terceros
                    que siga lo que hacés.
                </p>
            </div>

            <section v-for="grupo in GRUPOS" :key="grupo.titulo" class="flex flex-col gap-3">
                <div>
                    <h2 class="font-semibold">{{ grupo.titulo }}</h2>
                    <p class="text-sm text-surface-500">{{ grupo.descripcion }}</p>
                </div>

                <!-- Tarjetas apiladas y no una tabla: en el celular y con letra grande,
                     tres columnas se irían de ancho. -->
                <dl class="divide-y divide-surface-200 dark:divide-surface-800 rounded-2xl border border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900">
                    <div v-for="dato in grupo.datos" :key="dato.nombre" class="flex flex-col gap-1 p-4">
                        <dt class="font-mono text-sm break-all">{{ dato.nombre }}</dt>
                        <dd class="text-sm">{{ dato.para }}</dd>
                        <dd class="text-xs text-surface-500">{{ dato.dura }}</dd>
                    </div>
                </dl>
            </section>

            <section class="flex flex-col gap-2">
                <h2 class="font-semibold">Terceros</h2>
                <p class="text-sm">
                    <strong>Tipografía.</strong> La letra de la app se pide a Bunny Fonts
                    (fonts.bunny.net). Su servidor ve tu dirección IP, como en cualquier pedido
                    por internet, pero no recibe nada de lo que cargás ni de tu cuenta, y no
                    guardamos nada de eso en tu navegador.
                </p>
                <p class="text-sm">
                    <strong>Si entrás con Google.</strong> Google pone sus propias cookies, en
                    su sitio, mientras iniciás sesión con tu cuenta de Google. Si entrás con
                    email y contraseña no interviene.
                </p>
            </section>

            <section class="flex flex-col gap-2">
                <h2 class="font-semibold">Cómo borrarlo</h2>
                <p class="text-sm">
                    Al salir de tu cuenta se borran las pantallas guardadas para usar la app
                    sin conexión, que incluyen tus movimientos: por eso conviene salir siempre
                    en un dispositivo compartido. Solo quedan los cambios hechos sin señal que
                    todavía no se enviaron. Todo lo demás lo podés borrar cuando quieras desde
                    la configuración de tu navegador; si borrás las cookies vas a tener que
                    volver a iniciar sesión.
                </p>
            </section>
        </div>
    </component>
</template>
