<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

/*
 * Términos y condiciones, y política de privacidad: la misma página con otro
 * texto. Los textos viven en `resources/legales/*.md` y llegan ya convertidos
 * a HTML desde `LegalesController`; son del repo, no de un usuario.
 *
 * Se abre con sesión y sin ella, igual que Cookies.
 */
defineProps({
    titulo: { type: String, required: true },
    version: { type: String, required: true },
    html: { type: String, required: true },
});

const page = usePage();
const Layout = computed(() => (page.props.auth?.user ? AppLayout : PublicLayout));
</script>

<template>
    <Head :title="titulo" />

    <component :is="Layout" :title="titulo">
        <div class="mx-auto max-w-2xl">
            <h1 class="text-xl font-semibold hidden md:block">{{ titulo }}</h1>
            <p class="text-sm text-surface-500">Versión del {{ version }}</p>

            <!-- Texto propio del repo, ver LegalesController -->
            <article
                class="mt-4 rounded-2xl border border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900 p-5 sm:p-6 break-words text-surface-700 dark:text-surface-200 [&_a]:underline [&_a]:underline-offset-4 [&_a:hover]:text-primary [&_blockquote]:mt-4 [&_blockquote]:border-l-4 [&_blockquote]:border-surface-300 [&_blockquote]:pl-4 [&_blockquote]:text-sm [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-surface-900 dark:[&_h2]:text-surface-100 [&_h2:first-child]:mt-0 [&_li]:mt-1.5 [&_p]:mt-3 [&_p:first-child]:mt-0 [&_strong]:font-semibold [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5"
                v-html="html"
            />
        </div>
    </component>
</template>
