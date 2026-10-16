<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

/*
 * «Aceptar y continuar». La muestra el middleware `AceptoTerminos` a quien no
 * aceptó la versión vigente: las cuentas de antes de que existieran los
 * textos, las altas con Google y todos cuando se sube la versión.
 *
 * Sin aceptar igual se puede salir, llevarse los datos o borrar la cuenta: son
 * derechos que no dependen de aceptar nada.
 */
defineProps({
    primeraVez: { type: Boolean, required: true },
    version: { type: String, required: true },
});

const form = useForm({ terminos: false });

const submit = () => form.post(route('terminos.aceptar.store'));
</script>

<template>
    <GuestLayout>
        <Head title="Términos y privacidad" />

        <h1 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Antes de seguir</h1>

        <form @submit.prevent="submit" class="mt-3 space-y-5">
            <p class="text-sm text-surface-600 dark:text-surface-300">
                <template v-if="primeraVez">Mi Billetera ahora tiene</template>
                <template v-else>Actualizamos</template>
                los
                <a :href="route('terminos')" target="_blank" class="underline underline-offset-4 hover:text-primary">términos y condiciones</a>
                y la
                <a :href="route('privacidad')" target="_blank" class="underline underline-offset-4 hover:text-primary">política de privacidad</a>
                (versión del {{ version }}). Explican qué datos guardamos, para qué, y qué podés
                hacer con ellos. Leelos antes de seguir.
            </p>

            <div>
                <label class="flex min-h-11 cursor-pointer items-start gap-3 text-sm text-surface-700 dark:text-surface-200">
                    <input
                        v-model="form.terminos"
                        type="checkbox"
                        required
                        class="mt-0.5 h-5 w-5 shrink-0 rounded border-surface-300 dark:border-surface-700 text-primary focus:ring-primary"
                    />
                    <span>Leí y acepto los términos y la política de privacidad.</span>
                </label>
                <p v-if="form.errors.terminos" class="mt-1 text-sm text-rose-500">{{ form.errors.terminos }}</p>
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-primary py-2.5 font-semibold text-primary-contrast hover:brightness-95 transition disabled:opacity-50"
                :disabled="form.processing"
            >
                Aceptar y continuar
            </button>

            <p class="text-center text-sm text-surface-500">
                Si no estás de acuerdo podés
                <a :href="route('exportacion.datos')" class="underline underline-offset-4 hover:text-primary">descargar tus datos</a>
                o
                <Link :href="route('logout')" method="post" as="button" class="underline underline-offset-4 hover:text-primary">salir</Link>.
            </p>
        </form>
    </GuestLayout>
</template>
