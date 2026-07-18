<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage, router, useForm } from '@inertiajs/vue3';
import {
    IconUser, IconCategory, IconWallet, IconCoin, IconChartHistogram,
    IconClockDollar, IconBell, IconMoon, IconSun, IconChevronRight,
    IconLogout, IconShieldCheck, IconX,
} from '@tabler/icons-vue';

const props = defineProps({
    currencies: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const isAdmin = computed(() => user.value.role === 'admin');
const currentCurrency = computed(() => user.value.default_currency ?? 'ARS');

// --- Tema ---
const isDark = ref(true);
onMounted(() => { isDark.value = document.documentElement.classList.contains('dark'); });
const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    try { localStorage.setItem('theme', isDark.value ? 'dark' : 'light'); } catch (e) { /* noop */ }
};

// --- Moneda ---
const showCurrency = ref(false);
const currencyForm = useForm({ default_currency: currentCurrency.value });
const openCurrency = () => { currencyForm.default_currency = currentCurrency.value; showCurrency.value = true; };
const saveCurrency = () => currencyForm.patch(route('preferences.update'), { preserveScroll: true, onSuccess: () => (showCurrency.value = false) });

const groups = [
    {
        title: 'Cuenta',
        items: [
            { label: 'Perfil', icon: IconUser, href: '/profile' },
            { label: 'Categorías', icon: IconCategory, href: '/categories' },
            { label: 'Cuentas', icon: IconWallet, href: '/accounts' },
        ],
    },
    {
        title: 'Planificación',
        items: [
            { label: 'Presupuestos', icon: IconChartHistogram, href: '/budgets' },
            { label: 'Transacciones recurrentes', icon: IconClockDollar, href: '/recurring' },
            { label: 'Recordatorios', icon: IconBell, href: '/reminders' },
        ],
    },
];

const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="Ajustes" />

    <AppLayout title="Ajustes">
        <div class="mx-auto max-w-2xl space-y-6">
            <!-- Perfil -->
            <div class="flex items-center gap-4 rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <span class="grid place-items-center h-14 w-14 rounded-full bg-primary text-primary-contrast text-xl font-semibold overflow-hidden">
                    <img v-if="user.avatar" :src="user.avatar" alt="" class="h-full w-full object-cover" />
                    <template v-else>{{ (user.name ?? '?').charAt(0).toUpperCase() }}</template>
                </span>
                <div class="min-w-0">
                    <p class="font-semibold truncate">{{ user.name }}</p>
                    <p class="text-sm text-surface-500 truncate">{{ user.email }}</p>
                </div>
            </div>

            <!-- Grupos -->
            <div v-for="group in groups" :key="group.title" class="space-y-2">
                <p class="px-1 text-xs font-medium uppercase tracking-wide text-surface-500">{{ group.title }}</p>
                <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 divide-y divide-surface-200 dark:divide-surface-800 overflow-hidden">
                    <Link
                        v-for="item in group.items"
                        :key="item.label"
                        :href="item.href"
                        class="flex items-center gap-3 px-4 py-3.5 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                    >
                        <span class="grid place-items-center h-9 w-9 rounded-lg bg-surface-100 dark:bg-surface-800 text-primary">
                            <component :is="item.icon" :size="20" :stroke="1.8" />
                        </span>
                        <span class="flex-1 text-sm font-medium">{{ item.label }}</span>
                        <IconChevronRight :size="18" class="text-surface-400" />
                    </Link>
                </div>
            </div>

            <!-- Preferencias -->
            <div class="space-y-2">
                <p class="px-1 text-xs font-medium uppercase tracking-wide text-surface-500">Preferencias</p>
                <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 divide-y divide-surface-200 dark:divide-surface-800 overflow-hidden">
                    <button type="button" class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors" @click="openCurrency">
                        <span class="grid place-items-center h-9 w-9 rounded-lg bg-surface-100 dark:bg-surface-800 text-primary"><IconCoin :size="20" :stroke="1.8" /></span>
                        <span class="flex-1 text-sm font-medium text-left">Moneda predeterminada</span>
                        <span class="text-sm text-surface-500">{{ currentCurrency }}</span>
                        <IconChevronRight :size="18" class="text-surface-400" />
                    </button>
                    <button type="button" class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors" @click="toggleTheme">
                        <span class="grid place-items-center h-9 w-9 rounded-lg bg-surface-100 dark:bg-surface-800 text-primary">
                            <IconMoon v-if="isDark" :size="20" :stroke="1.8" />
                            <IconSun v-else :size="20" :stroke="1.8" />
                        </span>
                        <span class="flex-1 text-sm font-medium text-left">Tema {{ isDark ? 'oscuro' : 'claro' }}</span>
                        <span class="text-xs text-surface-500">Cambiar</span>
                    </button>
                </div>
            </div>

            <!-- Administración (solo admin) -->
            <div v-if="isAdmin" class="space-y-2">
                <p class="px-1 text-xs font-medium uppercase tracking-wide text-surface-500">Administración</p>
                <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 overflow-hidden">
                    <Link href="/admin/users" class="flex items-center gap-3 px-4 py-3.5 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors">
                        <span class="grid place-items-center h-9 w-9 rounded-lg bg-primary/10 text-primary"><IconShieldCheck :size="20" :stroke="1.8" /></span>
                        <span class="flex-1 text-sm font-medium">Usuarios y roles</span>
                        <IconChevronRight :size="18" class="text-surface-400" />
                    </Link>
                </div>
            </div>

            <!-- Cerrar sesión -->
            <button type="button" class="w-full flex items-center justify-center gap-2 rounded-2xl border border-rose-500/30 text-rose-500 px-4 py-3.5 font-medium hover:bg-rose-500/10 transition-colors" @click="logout">
                <IconLogout :size="20" :stroke="1.8" /> Cerrar sesión
            </button>
        </div>

        <!-- Diálogo moneda -->
        <Teleport to="body">
            <div v-if="showCurrency" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 sm:p-4" @click.self="showCurrency = false">
                <div class="w-full sm:max-w-sm bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800">
                    <div class="flex items-center justify-between px-5 h-14 border-b border-surface-200 dark:border-surface-800">
                        <button type="button" class="text-surface-500" @click="showCurrency = false"><IconX :size="22" /></button>
                        <h2 class="font-semibold">Moneda predeterminada</h2>
                        <span class="w-6"></span>
                    </div>
                    <div class="p-5 space-y-4">
                        <p class="text-xs text-surface-500">Es la moneda base para los totales de Inicio, Gráficos e Informes.</p>
                        <select v-model="currencyForm.default_currency" class="w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }} ({{ c.symbol }})</option>
                        </select>
                        <button type="button" class="w-full rounded-xl bg-primary py-3 font-semibold text-primary-contrast disabled:opacity-50" :disabled="currencyForm.processing" @click="saveCurrency">Guardar</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
