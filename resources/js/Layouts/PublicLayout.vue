<script setup>
import { Link } from '@inertiajs/vue3';
import { IconArrowLeft } from '@tabler/icons-vue';
import AvisoCookies from '@/Components/AvisoCookies.vue';

/*
 * Para las páginas que se abren sin sesión y se dibujan solas (los textos
 * legales y Cookies): un encabezado mínimo y el aviso de cookies, que tiene
 * que verse en la primera visita.
 */
defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="min-h-screen bg-surface-50 dark:bg-surface-950 text-surface-900 dark:text-surface-100">
        <header class="mx-auto w-full max-w-2xl px-4 pt-4 sm:px-6 sm:pt-6">
            <Link
                href="/"
                class="inline-flex min-h-11 items-center gap-2 text-sm text-surface-500 hover:text-primary transition-colors"
            >
                <IconArrowLeft :size="18" :stroke="1.8" class="shrink-0" />
                Volver al inicio
            </Link>
        </header>

        <main class="mx-auto w-full max-w-2xl px-4 pb-12 pt-2 sm:px-6">
            <slot />
        </main>

        <AvisoCookies />
    </div>
</template>
