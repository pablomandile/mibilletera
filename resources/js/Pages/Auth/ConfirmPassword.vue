<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });

const inputClass =
    'mt-1 block w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-surface-100 focus:border-primary focus:ring-primary';

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirmar contraseña" />

        <h1 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Confirmá tu contraseña</h1>
        <p class="mt-1 text-sm text-surface-500">Esta es un área segura. Por favor confirmá tu contraseña para continuar.</p>

        <form @submit.prevent="submit" class="mt-5 space-y-4">
            <div>
                <label for="password" class="text-sm font-medium text-surface-700 dark:text-surface-200">Contraseña</label>
                <input id="password" type="password" :class="inputClass" v-model="form.password" required autocomplete="current-password" autofocus />
                <p v-if="form.errors.password" class="mt-1 text-sm text-rose-500">{{ form.errors.password }}</p>
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-primary py-2.5 font-semibold text-primary-contrast hover:brightness-95 transition disabled:opacity-50"
                :disabled="form.processing"
            >
                Confirmar
            </button>
        </form>
    </GuestLayout>
</template>
