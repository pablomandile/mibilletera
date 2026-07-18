<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: String },
});

const form = useForm({});
const submit = () => form.post(route('verification.send'));

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="Verificación de email" />

        <h1 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Verificá tu email</h1>
        <p class="mt-1 text-sm text-surface-500">
            ¡Gracias por registrarte! Antes de empezar, verificá tu correo haciendo clic en el enlace que te enviamos.
            Si no lo recibiste, te mandamos otro.
        </p>

        <div v-if="verificationLinkSent" class="mt-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-500">
            Te enviamos un nuevo enlace de verificación al correo con el que te registraste.
        </div>

        <form @submit.prevent="submit" class="mt-5 space-y-3">
            <button
                type="submit"
                class="w-full rounded-lg bg-primary py-2.5 font-semibold text-primary-contrast hover:brightness-95 transition disabled:opacity-50"
                :disabled="form.processing"
            >
                Reenviar email de verificación
            </button>

            <Link :href="route('logout')" method="post" as="button" class="w-full text-center text-sm text-surface-500 hover:text-primary">
                Cerrar sesión
            </Link>
        </form>
    </GuestLayout>
</template>
