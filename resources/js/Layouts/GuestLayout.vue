<script setup>
import { Link } from '@inertiajs/vue3';
import { IconWallet } from '@tabler/icons-vue';
import AvisoCookies from '@/Components/AvisoCookies.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-surface-50 dark:bg-surface-950 px-4 py-8">
        <Link href="/" class="flex items-center gap-2.5 mb-6">
            <span class="grid place-items-center h-11 w-11 rounded-2xl bg-primary text-primary-contrast">
                <IconWallet :size="26" :stroke="2" />
            </span>
            <span class="text-2xl font-semibold text-surface-900 dark:text-surface-100">Mi Billetera</span>
        </Link>

        <div class="w-full sm:max-w-md rounded-2xl border border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900 px-6 py-7 shadow-sm">
            <slot />
        </div>

        <p class="mt-5 flex flex-wrap justify-center gap-x-4 text-xs text-surface-500">
            <Link href="/terminos" class="inline-flex min-h-11 items-center hover:text-primary">Términos</Link>
            <Link href="/privacidad" class="inline-flex min-h-11 items-center hover:text-primary">Privacidad</Link>
            <Link href="/cookies" class="inline-flex min-h-11 items-center hover:text-primary">Cookies</Link>
        </p>

        <AvisoCookies />
    </div>
</template>
