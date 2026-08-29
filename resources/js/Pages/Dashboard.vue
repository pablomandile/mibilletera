<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TransactionRow from '@/Components/TransactionRow.vue';
import TransactionDetail from '@/Components/TransactionDetail.vue';
import { Head, router } from '@inertiajs/vue3';
import { IconChevronLeft, IconChevronRight, IconX } from '@tabler/icons-vue';
import { formatMoney } from '@/money';

const props = defineProps({
    transactions: { type: Array, default: () => [] },
    period: { type: Object, required: true },
    totals: { type: Object, required: true },
    dayTotals: { type: Object, default: () => ({}) }, // { 'Y-m-d': { income, expense } }
    filter: { type: String, default: null }, // income | expense | transfer | null
    baseSymbol: { type: String, default: '$' },
    symbols: { type: Object, default: () => ({}) },
});

const moneyBase = (v) => formatMoney(v, props.baseSymbol);

const visit = (params) => router.get(route('dashboard'), params, { preserveScroll: true });

const goMonth = (m) => visit({ month: m, ...(props.filter ? { type: props.filter } : {}) });

// Tocar la tarjeta de Ingresos/Gastos filtra el listado; volver a tocarla lo limpia.
const toggleFilter = (type) =>
    visit({ month: props.period.month, ...(props.filter === type ? {} : { type }) });

const clearFilter = () => visit({ month: props.period.month });

const FILTER_LABELS = { income: 'ingresos', expense: 'gastos', transfer: 'transferencias' };
const filterLabel = computed(() => FILTER_LABELS[props.filter] ?? '');

const MONTHS_SHORT = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const WEEKDAYS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// Comparar por componentes locales: toISOString() pasa a UTC y corre el día en los
// husos con offset positivo, con lo que "Hoy" / "Ayer" caían en el día equivocado.
const localKey = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;

// Etiqueta del separador, en dos partes para poder darles distinto peso visual.
function dateLabel(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (localKey(d) === localKey(today)) return { day: 'Hoy', weekday: '' };
    if (localKey(d) === localKey(yesterday)) return { day: 'Ayer', weekday: '' };
    return { day: `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]}`, weekday: WEEKDAYS[d.getDay()] };
}

const groups = computed(() => {
    const map = new Map();
    for (const t of props.transactions) {
        if (!map.has(t.date)) map.set(t.date, []);
        map.get(t.date).push(t);
    }
    return Array.from(map.entries()).map(([date, items]) => ({
        date,
        ...dateLabel(date),
        totals: props.dayTotals[date] ?? { income: 0, expense: 0 },
        items,
    }));
});

// ---- Ficha de detalle + borrado ----
const selectedTransaction = ref(null);
const openDetail = (t) => (selectedTransaction.value = t);
const closeDetail = () => (selectedTransaction.value = null);
const deleteTransaction = (id) => {
    router.delete(route('transactions.destroy', id), {
        preserveScroll: true,
        onSuccess: () => (selectedTransaction.value = null),
    });
};
</script>

<template>
    <Head title="Inicio" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between w-full max-w-3xl mx-auto">
                <button type="button" class="grid place-items-center h-9 w-9 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800" @click="goMonth(period.prev)">
                    <IconChevronLeft :size="20" />
                </button>
                <h1 class="text-base md:text-lg font-semibold">{{ period.label }}</h1>
                <button type="button" class="grid place-items-center h-9 w-9 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 disabled:opacity-30" :disabled="period.isCurrent" @click="goMonth(period.next)">
                    <IconChevronRight :size="20" />
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-4">
            <!-- Resumen del período (en moneda base). Ingresos y Gastos filtran el listado. -->
            <div class="grid grid-cols-3 gap-3">
                <button
                    type="button"
                    class="text-left min-w-0 rounded-2xl bg-white dark:bg-surface-900 border p-3 md:p-4 transition"
                    :class="filter === 'income' ? 'border-emerald-500 ring-1 ring-emerald-500' : 'border-surface-200 dark:border-surface-800 hover:border-emerald-500/50'"
                    :aria-pressed="filter === 'income'"
                    @click="toggleFilter('income')"
                >
                    <p class="text-xs text-surface-500">Ingresos</p>
                    <p class="mt-1 text-sm md:text-lg font-semibold text-emerald-500 truncate">{{ moneyBase(totals.income) }}</p>
                </button>
                <button
                    type="button"
                    class="text-left min-w-0 rounded-2xl bg-white dark:bg-surface-900 border p-3 md:p-4 transition"
                    :class="filter === 'expense' ? 'border-rose-500 ring-1 ring-rose-500' : 'border-surface-200 dark:border-surface-800 hover:border-rose-500/50'"
                    :aria-pressed="filter === 'expense'"
                    @click="toggleFilter('expense')"
                >
                    <p class="text-xs text-surface-500">Gastos</p>
                    <p class="mt-1 text-sm md:text-lg font-semibold text-rose-500 truncate">{{ moneyBase(totals.expense) }}</p>
                </button>
                <div class="min-w-0 rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-3 md:p-4">
                    <p class="text-xs text-surface-500">Balance</p>
                    <p class="mt-1 text-sm md:text-lg font-semibold truncate" :class="totals.balance >= 0 ? 'text-surface-900 dark:text-surface-100' : 'text-rose-500'">
                        {{ moneyBase(totals.balance) }}
                    </p>
                </div>
            </div>

            <!-- Chip de filtro activo -->
            <div v-if="filter" class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 text-primary text-xs font-medium pl-3 pr-1.5 py-1.5">
                    Mostrando solo {{ filterLabel }}
                    <button type="button" class="grid place-items-center h-5 w-5 rounded-full hover:bg-primary/20" aria-label="Quitar filtro" @click="clearFilter">
                        <IconX :size="14" />
                    </button>
                </span>
            </div>

            <div v-if="!groups.length" class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-10 text-center">
                <p v-if="filter" class="text-surface-500">
                    No hay {{ filterLabel }} en este período.
                    <button type="button" class="text-primary font-semibold" @click="clearFilter">Ver todos los movimientos</button>
                </p>
                <p v-else class="text-surface-500">
                    No hay movimientos en este período. Tocá el botón
                    <span class="text-primary font-semibold">+</span> para cargar uno.
                </p>
            </div>

            <div v-for="group in groups" :key="group.date" class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 overflow-hidden">
                <!-- Separador del día: fecha a la izquierda, subtotales a la derecha. -->
                <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 px-4 py-2 border-b border-surface-100 dark:border-surface-800 bg-surface-50/50 dark:bg-surface-800/30 text-[11px] text-surface-500">
                    <span class="flex items-center gap-2 shrink-0 whitespace-nowrap">
                        <span class="font-medium text-surface-600 dark:text-surface-400">{{ group.day }}</span>
                        <span v-if="group.weekday">{{ group.weekday }}</span>
                    </span>
                    <span class="flex flex-wrap items-center justify-end gap-x-3 ml-auto tabular-nums">
                        <span v-if="group.totals.expense" class="whitespace-nowrap">Gastos: {{ moneyBase(group.totals.expense) }}</span>
                        <span v-if="group.totals.income" class="whitespace-nowrap">Ingresos: {{ moneyBase(group.totals.income) }}</span>
                    </span>
                </div>

                <div class="divide-y divide-surface-100 dark:divide-surface-800">
                    <TransactionRow
                        v-for="t in group.items"
                        :key="t.id"
                        :transaction="t"
                        :symbols="symbols"
                        :base-symbol="baseSymbol"
                        @open="openDetail"
                        @delete="deleteTransaction"
                    />
                </div>
            </div>
        </div>

        <TransactionDetail
            :transaction="selectedTransaction"
            :symbols="symbols"
            :base-symbol="baseSymbol"
            @close="closeDetail"
            @delete="deleteTransaction"
        />
    </AppLayout>
</template>
