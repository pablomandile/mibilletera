<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconMail, IconWorld } from '@tabler/icons-vue';

const EMAIL = 'pablo.mandile@gmail.com';
const WEBSITE = 'https://bioinfo.pablomandile.com.ar/pablo';
</script>

<template>
    <Head title="Acerca de" />

    <AppLayout title="Acerca de">
        <div class="mx-auto max-w-2xl">
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-6 sm:p-8">
                <!-- El logo con texto trae el "Mi" en azul oscuro, así que va siempre sobre
                     una pastilla blanca: sin ella, en tema oscuro esa palabra se pierde
                     contra el fondo. -->
                <span class="inline-flex items-center rounded-xl bg-white px-2 py-0.5 ring-1 ring-black/5 dark:ring-white/10">
                    <img src="/icons/mibilletera_texto.png" alt="Mi Billetera" class="h-14 w-auto" />
                </span>

                <p class="mt-6 text-lg leading-relaxed text-surface-700 dark:text-surface-200">
                    Administrador de gastos personales: gastos, ingresos y transferencias entre
                    cuentas, con varias monedas, presupuestos, recordatorios e informes.
                </p>

                <div class="mt-8 border-t border-surface-200 dark:border-surface-800 pt-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-surface-500">
                        Creado por
                    </p>
                    <p class="mt-1 text-lg font-semibold">Pablo Mandile</p>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:gap-6">
                        <a
                            :href="`mailto:${EMAIL}`"
                            class="inline-flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300 hover:text-primary transition-colors"
                        >
                            <IconMail :size="18" :stroke="1.8" class="shrink-0" />
                            <span class="break-all">{{ EMAIL }}</span>
                        </a>

                        <a
                            :href="WEBSITE"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300 hover:text-primary transition-colors"
                        >
                            <IconWorld :size="18" :stroke="1.8" class="shrink-0" />
                            <span>Bioinfo</span>
                        </a>
                    </div>
                </div>

                <p class="mt-6 flex flex-wrap gap-x-5 text-sm text-surface-500">
                    <Link href="/terminos" class="inline-flex min-h-11 items-center hover:text-primary">Términos y condiciones</Link>
                    <Link href="/privacidad" class="inline-flex min-h-11 items-center hover:text-primary">Privacidad</Link>
                    <Link href="/cookies" class="inline-flex min-h-11 items-center hover:text-primary">Cookies</Link>
                </p>
            </div>
        </div>
    </AppLayout>
</template>
