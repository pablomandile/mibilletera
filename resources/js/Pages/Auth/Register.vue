<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import GoogleButton from '@/Components/GoogleButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terminos: false,
});

const inputClass =
    'mt-1 block w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-surface-100 focus:border-primary focus:ring-primary';

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Crear cuenta" />

        <h1 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Crear cuenta</h1>
        <p class="mt-1 text-sm text-surface-500">Empezá a controlar tus gastos en un minuto.</p>

        <div class="mt-5">
            <GoogleButton label="Registrarme con Google" />
        </div>

        <div class="my-5 flex items-center gap-3 text-xs text-surface-400">
            <span class="h-px flex-1 bg-surface-200 dark:bg-surface-800"></span>
            o con tu email
            <span class="h-px flex-1 bg-surface-200 dark:bg-surface-800"></span>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="name" class="text-sm font-medium text-surface-700 dark:text-surface-200">Nombre</label>
                <input id="name" type="text" :class="inputClass" v-model="form.name" required autofocus autocomplete="name" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-rose-500">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="text-sm font-medium text-surface-700 dark:text-surface-200">Email</label>
                <input id="email" type="email" :class="inputClass" v-model="form.email" required autocomplete="username" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-rose-500">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="text-sm font-medium text-surface-700 dark:text-surface-200">Contraseña</label>
                <input id="password" type="password" :class="inputClass" v-model="form.password" required autocomplete="new-password" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-rose-500">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="text-sm font-medium text-surface-700 dark:text-surface-200">Confirmar contraseña</label>
                <input id="password_confirmation" type="password" :class="inputClass" v-model="form.password_confirmation" required autocomplete="new-password" />
                <p v-if="form.errors.password_confirmation" class="mt-1 text-sm text-rose-500">{{ form.errors.password_confirmation }}</p>
            </div>

            <div>
                <label class="flex min-h-11 cursor-pointer items-start gap-3 text-sm text-surface-600 dark:text-surface-300">
                    <input
                        v-model="form.terminos"
                        type="checkbox"
                        required
                        class="mt-0.5 h-5 w-5 shrink-0 rounded border-surface-300 dark:border-surface-700 text-primary focus:ring-primary"
                    />
                    <span>
                        Leí y acepto los
                        <a href="/terminos" target="_blank" class="underline underline-offset-4 hover:text-primary">términos y condiciones</a>
                        y la
                        <a href="/privacidad" target="_blank" class="underline underline-offset-4 hover:text-primary">política de privacidad</a>.
                    </span>
                </label>
                <p v-if="form.errors.terminos" class="mt-1 text-sm text-rose-500">{{ form.errors.terminos }}</p>
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-primary py-2.5 font-semibold text-primary-contrast hover:brightness-95 transition disabled:opacity-50"
                :disabled="form.processing"
            >
                Crear cuenta
            </button>

            <p class="text-center text-sm text-surface-500">
                ¿Ya tenés cuenta?
                <Link :href="route('login')" class="text-primary font-medium hover:underline">Iniciar sesión</Link>
            </p>
        </form>
    </GuestLayout>
</template>
