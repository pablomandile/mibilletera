<script setup>
import { computed, ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import NotificationBell from '@/Components/NotificationBell.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import AvisoCookies from '@/Components/AvisoCookies.vue';
import {
    IconHome2,
    IconChartPie,
    IconReportAnalytics,
    IconUser,
    IconPlus,
    IconLogout,
    IconInfoCircle,
    IconCloudOff,
    IconChartHistogram,
    IconClockDollar,
    IconBell,
    IconSearch,
    IconX,
} from '@tabler/icons-vue';
import { offline, cachedAt } from '@/offline/connection';
import { ops } from '@/offline/outbox';
import { syncing, needsLogin } from '@/offline/sync';
import { notices } from '@/offline/notices';
import { confirmAction } from '@/composables/useConfirm';

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

// Solo en la barra lateral: la inferior de mobile no tiene lugar, ahí se llega desde Ajustes.
const planItems = [
    { label: 'Presupuestos', href: '/budgets', icon: IconChartHistogram },
    { label: 'Transacciones recurrentes', href: '/recurring', icon: IconClockDollar },
    { label: 'Recordatorios', href: '/reminders', icon: IconBell },
];

// ---- Búsqueda (mobile) ----
// La lupa del header ocupa el lugar del switch de tema (que sigue en Ajustes) y
// despliega el campo debajo; otro toque lo oculta.
const onSearchPage = computed(() => currentUrl.value.split('?')[0] === '/transactions/search');
const searchOpen = ref(false);
const searchTerm = ref('');
const searchInput = ref(null);

const toggleSearch = async () => {
    // En la pantalla de búsqueda ya hay un campo: la lupa lleva a ese.
    if (onSearchPage.value) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        document.querySelector('main [role="search"] input')?.focus();
        return;
    }
    searchOpen.value = !searchOpen.value;
    if (searchOpen.value) {
        await nextTick();
        searchInput.value?.focus();
    }
};

const submitSearch = () => {
    const q = searchTerm.value.trim();
    if (!q) return;
    router.get(route('transactions.search'), { q });
};

const logout = async () => {
    // La cola espera al mismo usuario, pero si cierra sesión sin conexión los
    // cambios no se pueden mandar hasta que vuelva a entrar.
    if (ops.value.length) {
        const ok = await confirmAction({
            title: '¿Cerrar sesión?',
            message: `Tenés ${ops.value.length === 1 ? '1 cambio' : `${ops.value.length} cambios`} sin sincronizar. Quedan guardados en este dispositivo y se mandan cuando vuelvas a iniciar sesión.`,
            confirmLabel: 'Cerrar sesión',
        });
        if (!ok) return;
    }
    router.post(route('logout'));
};

// Toast de confirmación para los mensajes flash de éxito.
const toast = useToast();
watch(
    () => page.props.flash?.success,
    (msg) => { if (msg) toast.add({ severity: 'success', summary: msg, life: 2500 }); }
);
watch(
    () => page.props.flash?.error,
    (msg) => { if (msg) toast.add({ severity: 'warn', summary: msg, life: 4000 }); }
);

// ---- Sin conexión ----
// Avisos de la sincronización y de pantallas sin AppLayout (ver offline/notices).
watch(
    notices,
    (list) => {
        if (!list.length) return;
        list.forEach((n) => toast.add({ ...n, life: n.severity === 'warn' ? 5000 : 2500 }));
        notices.value = [];
    },
    { immediate: true }
);

const pendingCount = computed(() => ops.value.filter((o) => o.status !== 'error').length);
const failedCount = computed(() => ops.value.filter((o) => o.status === 'error').length);
const pendingLabel = computed(() => {
    const n = pendingCount.value;
    if (!n) return '';
    return n === 1 ? '1 cambio sin sincronizar' : `${n} cambios sin sincronizar`;
});

const cachedLabel = computed(() => {
    if (!cachedAt.value) return '';
    const d = new Date(cachedAt.value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
});

// Pantalla que no se llegó a guardar (ver startOffline en app.js).
const onOfflineMiss = () =>
    toast.add({
        severity: 'warn',
        summary: 'Sin conexión',
        detail: 'Esta pantalla todavía no está guardada. Abrila una vez con conexión.',
        life: 4000,
    });
onMounted(() => window.addEventListener('offline:miss', onOfflineMiss));
onUnmounted(() => window.removeEventListener('offline:miss', onOfflineMiss));
</script>

<template>
    <div class="piggy-backdrop min-h-screen bg-surface-50 dark:bg-surface-950 text-surface-900 dark:text-surface-100">
        <Toast position="top-center" />
        <ConfirmDialog />

        <!-- ===================== Sidebar (desktop) ===================== -->
        <aside
            class="hidden md:flex md:flex-col fixed inset-y-0 left-0 w-64 z-30 border-r border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900"
        >
            <div class="flex items-center px-3 h-16 border-b border-surface-200 dark:border-surface-800">
                <!-- El logo con texto trae el "Mi" en azul oscuro, así que va siempre sobre
                     una pastilla blanca: sin ella, en tema oscuro esa palabra se pierde
                     contra el fondo y la bajada queda ilegible. -->
                <span class="inline-flex items-center rounded-xl bg-white px-2 py-0.5 ring-1 ring-black/5 dark:ring-white/10">
                    <img src="/icons/mibilletera_texto.png" alt="Mi Billetera" class="h-14 w-auto" />
                </span>
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

                <!-- Fuera de navItems por lo mismo que Acerca de: en mobile va como lupa en el header. -->
                <Link
                    href="/transactions/search"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                    :class="onSearchPage
                        ? 'bg-primary/10 text-primary'
                        : 'text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'"
                >
                    <IconSearch :size="22" :stroke="1.8" />
                    Buscar
                </Link>

                <Link
                    href="/transactions/create"
                    class="mt-4 flex items-center justify-center gap-2 px-3 py-3 rounded-xl bg-primary text-primary-contrast font-semibold shadow-sm hover:brightness-95 transition"
                >
                    <IconPlus :size="20" :stroke="2.4" />
                    Agregar
                </Link>

                <p class="px-3 pt-5 pb-1 text-xs font-medium uppercase tracking-wide text-surface-500">Planificación</p>
                <Link
                    v-for="item in planItems"
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
            </nav>

            <div class="p-3 border-t border-surface-200 dark:border-surface-800">
                <!-- Acerca de va acá y no en navItems: navItems también alimenta la barra
                     inferior de mobile (2 + botón + 2 en una grilla de 5), y un ítem más
                     la desarma. En mobile se llega desde Ajustes. -->
                <Link
                    href="/about"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors"
                    :class="isActive('/about')
                        ? 'bg-primary/10 text-primary'
                        : 'text-surface-500 dark:text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800'"
                >
                    <IconInfoCircle :size="20" :stroke="1.8" />
                    Acerca de
                </Link>

                <div class="mt-1 flex items-center gap-3 px-2 py-2">
                    <span class="grid place-items-center h-9 w-9 rounded-full bg-surface-200 dark:bg-surface-700 text-sm font-semibold">
                        {{ (user.name ?? '?').charAt(0).toUpperCase() }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium break-words hyphens-auto">{{ user.name }}</p>
                        <p class="text-xs text-surface-500 break-all">{{ user.email }}</p>
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
                class="font-scale-capped md:hidden sticky top-0 z-20 flex items-center gap-2 h-14 px-4 border-b border-surface-200 dark:border-surface-800 bg-white/80 dark:bg-surface-900/80 backdrop-blur"
            >
                <Link href="/dashboard" class="shrink-0" aria-label="Inicio">
                    <img src="/icons/mibilletera-logo.png" alt="Mi Billetera" class="h-8 w-auto" />
                </Link>
                <div class="flex-1 min-w-0">
                    <slot name="header">
                        <h1 class="text-base font-semibold truncate">{{ title }}</h1>
                    </slot>
                </div>
                <button
                    type="button"
                    class="grid place-items-center h-9 w-9 rounded-lg text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800"
                    :class="searchOpen ? 'bg-primary/10 text-primary' : ''"
                    :aria-label="searchOpen ? 'Ocultar búsqueda' : 'Buscar'"
                    :aria-expanded="searchOpen"
                    aria-controls="mobile-search"
                    @click="toggleSearch"
                >
                    <IconX v-if="searchOpen" :size="22" :stroke="1.8" />
                    <IconSearch v-else :size="22" :stroke="1.8" />
                </button>
                <NotificationBell />
            </header>

            <form
                v-if="searchOpen"
                id="mobile-search"
                role="search"
                class="md:hidden sticky top-14 z-20 px-4 py-2 border-b border-surface-200 dark:border-surface-800 bg-white/90 dark:bg-surface-900/90 backdrop-blur"
                @submit.prevent="submitSearch"
            >
                <div class="relative">
                    <IconSearch :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 pointer-events-none" />
                    <input
                        ref="searchInput"
                        v-model="searchTerm"
                        type="search"
                        enterkeyhint="search"
                        placeholder="Buscar en las notas"
                        aria-label="Buscar en las notas de ingresos y gastos"
                        class="w-full rounded-xl border border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 pl-9 pr-3 py-2 text-sm focus:border-primary focus:ring-primary"
                    />
                </div>
            </form>

            <!-- Header desktop -->
            <header
                class="hidden md:flex items-center gap-2 h-16 px-6 border-b border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900"
            >
                <div class="flex-1 min-w-0">
                    <slot name="header">
                        <h1 class="text-xl font-semibold">{{ title }}</h1>
                    </slot>
                </div>
                <ThemeToggle />
                <NotificationBell />
            </header>

            <div
                v-if="offline || pendingCount || failedCount"
                class="flex items-center gap-2 px-4 md:px-6 py-2 text-xs bg-amber-500/15 text-amber-700 dark:text-amber-300"
                role="status"
            >
                <IconCloudOff :size="16" class="shrink-0" />
                <span class="min-w-0">
                    <template v-if="offline">Sin conexión<template v-if="cachedLabel"> · datos guardados el {{ cachedLabel }}</template></template>
                    <template v-else-if="needsLogin">Iniciá sesión de nuevo para sincronizar</template>
                    <template v-else-if="syncing">Sincronizando…</template>
                    <template v-if="pendingLabel">{{ offline || needsLogin || syncing ? ' · ' : '' }}{{ pendingLabel }}</template>
                    <Link v-if="failedCount" href="/settings" class="underline">
                        {{ offline || pendingLabel ? ' · ' : '' }}{{ failedCount === 1 ? '1 cambio con error' : `${failedCount} cambios con error` }}
                    </Link>
                </span>
            </div>

            <main class="px-4 md:px-6 py-4 md:py-6 pb-28 md:pb-8">
                <slot />
            </main>
        </div>

        <!-- ===================== Bottom nav (mobile) ===================== -->
        <!-- El aviso de cookies va adentro del contenedor fijo de la barra, arriba de
             ella: con su propio bottom-0 la taparía. Desde md no hay barra y se
             muestra la otra instancia, flotante. -->
        <div class="md:hidden fixed bottom-0 inset-x-0 z-30">
        <AvisoCookies variante="apilado" />
        <nav
            class="relative h-16 border-t border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900"
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

        <div class="hidden md:block">
            <AvisoCookies variante="flotante" />
        </div>
    </div>
</template>
