<script setup>
import { computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import NotificationBell from '@/Components/NotificationBell.vue';
import {
    IconHome2,
    IconChartPie,
    IconReportAnalytics,
    IconUser,
    IconPlus,
    IconWallet,
    IconLogout,
} from '@tabler/icons-vue';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const currentUrl = computed(() => page.url);

const isActive = (href) =>
    currentUrl.value === href || currentUrl.value.startsWith(href + '/');

// Ítems de navegación (mismo orden que la app: Inicio · Gráficos · (+) · Informes · Yo)
const navItems = [
    { label: 'Inicio', href: '/dashboard', icon: IconHome2 },
    { label: 'Gráficos', href: '/charts', icon: IconChartPie },
    { label: 'Informes', href: '/reports', icon: IconReportAnalytics },
    { label: 'Ajustes', href: '/settings', icon: IconUser },
];

const logout = () => router.post(route('logout'));

// Toast de confirmación para los mensajes flash de éxito.
const toast = useToast();
watch(
    () => page.props.flash?.success,
    (msg) => { if (msg) toast.add({ severity: 'success', summary: msg, life: 2500 }); }
);
</script>

<template>
    <div class="min-h-screen bg-surface-50 dark:bg-surface-950 text-surface-900 dark:text-surface-100">
        <Toast position="top-center" />

        <!-- ===================== Sidebar (desktop) ===================== -->
        <aside
            class="hidden md:flex md:flex-col fixed inset-y-0 left-0 w-64 z-30 border-r border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900"
        >
            <div class="flex items-center gap-2 px-5 h-16 border-b border-surface-200 dark:border-surface-800">
                <span class="grid place-items-center h-9 w-9 rounded-xl bg-primary text-primary-contrast">
                    <IconWallet :size="22" :stroke="2" />
                </span>
                <span class="font-semibold text-lg">Mi Billetera</span>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                    :class="isActive(item.href)
                        ? 'bg-primary/10 text-primary'
                        : 'text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'"
                >
                    <component :is="item.icon" :size="22" :stroke="1.8" />
                    {{ item.label }}
                </Link>

                <Link
                    href="/transactions/create"
                    class="mt-4 flex items-center justify-center gap-2 px-3 py-3 rounded-xl bg-primary text-primary-contrast font-semibold shadow-sm hover:brightness-95 transition"
                >
                    <IconPlus :size="20" :stroke="2.4" />
                    Agregar
                </Link>
            </nav>

            <div class="p-3 border-t border-surface-200 dark:border-surface-800">
                <div class="flex items-center gap-3 px-2 py-2">
                    <span class="grid place-items-center h-9 w-9 rounded-full bg-surface-200 dark:bg-surface-700 text-sm font-semibold">
                        {{ (user.name ?? '?').charAt(0).toUpperCase() }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium truncate">{{ user.name }}</p>
                        <p class="text-xs text-surface-500 truncate">{{ user.email }}</p>
                    </div>
                    <button
                        type="button"
                        class="grid place-items-center h-8 w-8 rounded-lg text-surface-500 hover:bg-surface-100 dark:hover:bg-surface-800"
                        title="Cerrar sesión"
                        @click="logout"
                    >
                        <IconLogout :size="18" :stroke="1.8" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- ===================== Contenido ===================== -->
        <div class="md:pl-64">
            <!-- Top bar (mobile) -->
            <header
                class="md:hidden sticky top-0 z-20 flex items-center gap-2 h-14 px-4 border-b border-surface-200 dark:border-surface-800 bg-white/80 dark:bg-surface-900/80 backdrop-blur"
            >
                <div class="flex-1 min-w-0">
                    <slot name="header">
                        <h1 class="text-base font-semibold truncate">{{ title }}</h1>
                    </slot>
                </div>
                <NotificationBell />
            </header>

            <!-- Header desktop -->
            <header
                class="hidden md:flex items-center gap-2 h-16 px-6 border-b border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900"
            >
                <div class="flex-1 min-w-0">
                    <slot name="header">
                        <h1 class="text-xl font-semibold">{{ title }}</h1>
                    </slot>
                </div>
                <NotificationBell />
            </header>

            <main class="px-4 md:px-6 py-4 md:py-6 pb-28 md:pb-8">
                <slot />
            </main>
        </div>

        <!-- ===================== Bottom nav (mobile) ===================== -->
        <nav
            class="md:hidden fixed bottom-0 inset-x-0 z-30 h-16 border-t border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900"
            style="padding-bottom: env(safe-area-inset-bottom);"
        >
            <div class="relative grid grid-cols-5 h-16">
                <!-- Inicio · Gráficos -->
                <Link
                    v-for="item in navItems.slice(0, 2)"
                    :key="item.href"
                    :href="item.href"
                    class="flex flex-col items-center justify-center gap-0.5 text-[11px]"
                    :class="isActive(item.href) ? 'text-primary' : 'text-surface-500 dark:text-surface-400'"
                >
                    <component :is="item.icon" :size="24" :stroke="1.8" />
                    {{ item.label }}
                </Link>

                <!-- Botón + grande y redondo (centro) -->
                <div class="flex items-start justify-center">
                    <Link
                        href="/transactions/create"
                        class="-mt-6 grid place-items-center h-16 w-16 rounded-full bg-primary text-primary-contrast shadow-lg ring-4 ring-white dark:ring-surface-950 active:scale-95 transition"
                        aria-label="Agregar transacción"
                    >
                        <IconPlus :size="30" :stroke="2.4" />
                    </Link>
                </div>

                <!-- Informes · Ajustes -->
                <Link
                    v-for="item in navItems.slice(2)"
                    :key="item.href"
                    :href="item.href"
                    class="flex flex-col items-center justify-center gap-0.5 text-[11px]"
                    :class="isActive(item.href) ? 'text-primary' : 'text-surface-500 dark:text-surface-400'"
                >
                    <component :is="item.icon" :size="24" :stroke="1.8" />
                    {{ item.label }}
                </Link>
            </div>
        </nav>
    </div>
</template>
