<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const inputClass =
    'mt-1 block w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-surface-100 focus:border-primary focus:ring-primary';

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Restablecer contraseña" />

        <h1 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Nueva contraseña</h1>
        <p class="mt-1 text-sm text-surface-500">Elegí una contraseña nueva para tu cuenta.</p>

        <form @submit.prevent="submit" class="mt-5 space-y-4">
            <div>
                <label for="email" class="text-sm font-medium text-surface-700 dark:text-surface-200">Email</label>
                <input id="email" type="email" :class="inputClass" v-model="form.email" required autofocus autocomplete="username" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-rose-500">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="text-sm font-medium text-surface-700 dark:text-surface-200">Nueva contraseña</label>
                <input id="password" type="password" :class="inputClass" v-model="form.password" required autocomplete="new-password" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-rose-500">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="text-sm font-medium text-surface-700 dark:text-surface-200">Confirmar contraseña</label>
                <input id="password_confirmation" type="password" :class="inputClass" v-model="form.password_confirmation" required autocomplete="new-password" />
                <p v-if="form.errors.password_confirmation" class="mt-1 text-sm text-rose-500">{{ form.errors.password_confirmation }}</p>
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-primary py-2.5 font-semibold text-primary-contrast hover:brightness-95 transition disabled:opacity-50"
                :disabled="form.processing"
            >
                Restablecer contraseña
            </button>
        </form>
    </GuestLayout>
</template>
