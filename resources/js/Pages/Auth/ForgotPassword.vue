<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String },
});

const form = useForm({ email: '' });

const inputClass =
    'mt-1 block w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-surface-100 focus:border-primary focus:ring-primary';

const submit = () => form.post(route('password.email'));
</script>

<template>
    <GuestLayout>
        <Head title="Recuperar contraseña" />

        <h1 class="text-lg font-semibold text-surface-900 dark:text-surface-100">¿Olvidaste tu contraseña?</h1>
        <p class="mt-1 text-sm text-surface-500">
            Ingresá tu email y te enviamos un enlace para elegir una nueva contraseña.
        </p>

        <div v-if="status" class="mt-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-500">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="mt-5 space-y-4">
            <div>
                <label for="email" class="text-sm font-medium text-surface-700 dark:text-surface-200">Email</label>
                <input id="email" type="email" :class="inputClass" v-model="form.email" required autofocus autocomplete="username" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-rose-500">{{ form.errors.email }}</p>
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-primary py-2.5 font-semibold text-primary-contrast hover:brightness-95 transition disabled:opacity-50"
                :disabled="form.processing"
            >
                Enviar enlace de recuperación
            </button>

            <p class="text-center text-sm">
                <Link :href="route('login')" class="text-primary font-medium hover:underline">Volver a iniciar sesión</Link>
            </p>
        </form>
    </GuestLayout>
</template>
