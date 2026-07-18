<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import GoogleButton from '@/Components/GoogleButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const inputClass =
    'mt-1 block w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-surface-100 focus:border-primary focus:ring-primary';

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Iniciar sesión" />

        <h1 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Iniciar sesión</h1>
        <p class="mt-1 text-sm text-surface-500">Bienvenido de vuelta a Mi Billetera.</p>

        <div v-if="status" class="mt-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm font-medium text-emerald-500">
            {{ status }}
        </div>

        <div class="mt-5">
            <GoogleButton label="Iniciar sesión con Google" />
        </div>

        <div class="my-5 flex items-center gap-3 text-xs text-surface-400">
            <span class="h-px flex-1 bg-surface-200 dark:bg-surface-800"></span>
            o con tu email
            <span class="h-px flex-1 bg-surface-200 dark:bg-surface-800"></span>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="text-sm font-medium text-surface-700 dark:text-surface-200">Email</label>
                <input
                    id="email"
                    type="email"
                    :class="inputClass"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-rose-500">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="text-sm font-medium text-surface-700 dark:text-surface-200">Contraseña</label>
                <input
                    id="password"
                    type="password"
                    :class="inputClass"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-rose-500">{{ form.errors.password }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                <input
                    type="checkbox"
                    v-model="form.remember"
                    class="rounded border-surface-300 dark:border-surface-700 text-primary focus:ring-primary"
                />
                Recordarme
            </label>

            <button
                type="submit"
                class="w-full rounded-lg bg-primary py-2.5 font-semibold text-primary-contrast hover:brightness-95 transition disabled:opacity-50"
                :disabled="form.processing"
            >
                Iniciar sesión
            </button>

            <div class="flex items-center justify-between text-sm">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-surface-500 hover:text-primary"
                >
                    ¿Olvidaste tu contraseña?
                </Link>
                <Link :href="route('register')" class="text-primary font-medium hover:underline">
                    Crear cuenta
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
