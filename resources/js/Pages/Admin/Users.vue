<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { IconShieldCheck, IconUser } from '@tabler/icons-vue';
import { computed } from 'vue';

defineProps({
    users: { type: Array, default: () => [] },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

const changeRole = (user, role) => {
    if (role === user.role) return;
    router.patch(route('admin.users.role', user.id), { role }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Usuarios" />

    <AppLayout title="Usuarios">
        <div class="mx-auto max-w-3xl space-y-4">
            <p class="text-sm text-surface-500">
                Los usuarios nuevos entran como <span class="font-medium">Usuario</span>. Asigná el rol
                <span class="font-medium">Admin</span> para dar acceso al panel de administración.
            </p>

            <p v-if="errors.role" class="rounded-lg bg-rose-500/10 px-3 py-2 text-sm text-rose-500">
                {{ errors.role }}
            </p>

            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 divide-y divide-surface-100 dark:divide-surface-800 overflow-hidden">
                <div v-for="u in users" :key="u.id" class="flex items-center gap-3 px-4 py-3">
                    <span class="grid place-items-center h-10 w-10 rounded-full overflow-hidden shrink-0"
                        :class="u.role === 'admin' ? 'bg-primary text-primary-contrast' : 'bg-surface-200 dark:bg-surface-700'">
                        <img v-if="u.avatar" :src="u.avatar" alt="" class="h-full w-full object-cover" />
                        <IconShieldCheck v-else-if="u.role === 'admin'" :size="20" />
                        <IconUser v-else :size="20" class="text-surface-500" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium truncate">
                            {{ u.name }}
                            <span v-if="u.is_self" class="ml-1 text-xs text-primary">(vos)</span>
                        </p>
                        <p class="text-xs text-surface-500 truncate">{{ u.email }} · {{ u.created_at }}</p>
                    </div>

                    <select
                        :value="u.role"
                        class="text-sm rounded-lg border-surface-300 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 py-1.5 pr-8"
                        @change="changeRole(u, $event.target.value)"
                    >
                        <option value="user">Usuario</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
