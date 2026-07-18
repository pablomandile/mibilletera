<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const inputClass = 'mt-1 block w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-surface-100 focus:border-primary focus:ring-primary';

// Datos de perfil
const profileForm = useForm({ name: user.value.name, email: user.value.email });
const saveProfile = () => profileForm.patch(route('profile.update'), { preserveScroll: true });

// Contraseña
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () => passwordForm.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
    onError: () => passwordForm.reset('password', 'password_confirmation', 'current_password'),
});

// Eliminar cuenta
const showDelete = ref(false);
const deleteForm = useForm({ password: '' });
const deleteAccount = () => deleteForm.delete(route('profile.destroy'), {
    preserveScroll: true,
    onSuccess: () => (showDelete.value = false),
    onError: () => {},
});
</script>

<template>
    <Head title="Perfil" />

    <AppLayout title="Perfil">
        <div class="mx-auto max-w-2xl space-y-5">
            <!-- Datos -->
            <section class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-5">
                <h2 class="font-semibold">Información del perfil</h2>
                <p class="text-sm text-surface-500 mb-4">Actualizá tu nombre y correo.</p>

                <form @submit.prevent="saveProfile" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Nombre</label>
                        <input v-model="profileForm.name" type="text" :class="inputClass" required autocomplete="name" />
                        <p v-if="profileForm.errors.name" class="mt-1 text-sm text-rose-500">{{ profileForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Email</label>
                        <input v-model="profileForm.email" type="email" :class="inputClass" required autocomplete="username" />
                        <p v-if="profileForm.errors.email" class="mt-1 text-sm text-rose-500">{{ profileForm.errors.email }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-lg bg-primary px-5 py-2 font-semibold text-primary-contrast hover:brightness-95 disabled:opacity-50" :disabled="profileForm.processing">Guardar</button>
                        <transition enter-from-class="opacity-0" leave-to-class="opacity-0">
                            <span v-if="profileForm.recentlySuccessful" class="text-sm text-emerald-500">Guardado.</span>
                        </transition>
                    </div>
                </form>
            </section>

            <!-- Contraseña -->
            <section class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-5">
                <h2 class="font-semibold">Contraseña</h2>
                <p class="text-sm text-surface-500 mb-4">Usá una contraseña larga y segura.</p>

                <form @submit.prevent="savePassword" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Contraseña actual</label>
                        <input v-model="passwordForm.current_password" type="password" :class="inputClass" autocomplete="current-password" />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-rose-500">{{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Nueva contraseña</label>
                        <input v-model="passwordForm.password" type="password" :class="inputClass" autocomplete="new-password" />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-rose-500">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Confirmar contraseña</label>
                        <input v-model="passwordForm.password_confirmation" type="password" :class="inputClass" autocomplete="new-password" />
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-lg bg-primary px-5 py-2 font-semibold text-primary-contrast hover:brightness-95 disabled:opacity-50" :disabled="passwordForm.processing">Cambiar contraseña</button>
                        <transition enter-from-class="opacity-0" leave-to-class="opacity-0">
                            <span v-if="passwordForm.recentlySuccessful" class="text-sm text-emerald-500">Actualizada.</span>
                        </transition>
                    </div>
                </form>
            </section>

            <!-- Eliminar cuenta -->
            <section class="rounded-2xl bg-white dark:bg-surface-900 border border-rose-500/30 p-5">
                <h2 class="font-semibold text-rose-500">Eliminar cuenta</h2>
                <p class="text-sm text-surface-500 mb-4">Se borrarán todos tus datos de forma permanente.</p>
                <button type="button" class="rounded-lg border border-rose-500/40 text-rose-500 px-5 py-2 font-medium hover:bg-rose-500/10" @click="showDelete = true">Eliminar mi cuenta</button>
            </section>
        </div>

        <!-- Confirmación de borrado -->
        <Teleport to="body">
            <div v-if="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="showDelete = false">
                <div class="w-full max-w-sm bg-white dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-800 p-5">
                    <h2 class="font-semibold">¿Eliminar tu cuenta?</h2>
                    <p class="text-sm text-surface-500 mt-1 mb-4">Ingresá tu contraseña para confirmar. Esta acción no se puede deshacer.</p>
                    <input v-model="deleteForm.password" type="password" placeholder="Contraseña" :class="inputClass" @keyup.enter="deleteAccount" />
                    <p v-if="deleteForm.errors.password" class="mt-1 text-sm text-rose-500">{{ deleteForm.errors.password }}</p>
                    <div class="mt-4 flex gap-2 justify-end">
                        <button type="button" class="rounded-lg px-4 py-2 text-sm text-surface-500 hover:bg-surface-100 dark:hover:bg-surface-800" @click="showDelete = false">Cancelar</button>
                        <button type="button" class="rounded-lg bg-rose-500 px-4 py-2 text-sm font-semibold text-white hover:brightness-95 disabled:opacity-50" :disabled="deleteForm.processing" @click="deleteAccount">Eliminar</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
