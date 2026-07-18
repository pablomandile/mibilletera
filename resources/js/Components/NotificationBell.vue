<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { IconBell } from '@tabler/icons-vue';
import AlertItem from '@/Components/AlertItem.vue';

const page = usePage();
const notifications = computed(() => page.props.notifications ?? { unread: 0, items: [] });
const unread = computed(() => notifications.value.unread ?? 0);
const items = computed(() => notifications.value.items ?? []);

const open = ref(false);

const toggle = () => {
    open.value = !open.value;
    if (open.value && unread.value > 0) {
        router.post(route('notifications.read'), {}, { preserveScroll: true, preserveState: true });
    }
};

const remove = (id) => {
    router.delete(route('notifications.destroy', id), { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <div class="relative shrink-0">
        <button
            type="button"
            class="relative grid place-items-center h-9 w-9 rounded-lg text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800"
            aria-label="Notificaciones"
            @click="toggle"
        >
            <IconBell :size="22" :stroke="1.8" />
            <span
                v-if="unread > 0"
                class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 grid place-items-center rounded-full bg-rose-500 text-white text-[10px] font-bold leading-none"
            >
                {{ unread > 99 ? '99+' : unread }}
            </span>
        </button>

        <!-- Overlay para cerrar -->
        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>

        <!-- Panel -->
        <div
            v-if="open"
            class="absolute right-0 mt-2 w-80 max-w-[90vw] z-50 rounded-2xl border border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900 shadow-xl overflow-hidden"
        >
            <div class="flex items-center justify-between px-4 h-12 border-b border-surface-200 dark:border-surface-800">
                <span class="font-semibold text-sm">Notificaciones</span>
                <span v-if="items.length" class="text-xs text-surface-400">Deslizá para borrar</span>
            </div>

            <div v-if="items.length" class="max-h-96 overflow-y-auto divide-y divide-surface-100 dark:divide-surface-800">
                <AlertItem v-for="a in items" :key="a.id" :alert="a" @delete="remove" />
            </div>
            <div v-else class="px-4 py-10 text-center text-sm text-surface-500">
                <IconBell :size="28" class="mx-auto mb-2 text-surface-400" />
                No tenés notificaciones.
            </div>
        </div>
    </div>
</template>
