<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chart from 'primevue/chart';
import { Head, router } from '@inertiajs/vue3';
import { formatMoney } from '@/money';
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-vue';

const props = defineProps({
    range: { type: String, default: 'month' },
    periodLabel: { type: String, default: '' },
    period: { type: Object, default: () => ({ date: null, prev: null, next: null, isCurrent: true }) },
    byCategory: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ income: 0, expense: 0 }) },
    trend: { type: Object, default: () => ({ labels: [], expense: [], income: [] }) },
    currencySymbol: { type: String, default: '$' },
});

const money = (v) => formatMoney(v, props.currencySymbol);

const ranges = [
    { key: 'week', label: 'Semana' },
    { key: 'month', label: 'Mes' },
    { key: 'year', label: 'Año' },
];
// Al cambiar de rango se queda en el período que se estaba mirando (marzo 2025 → año 2025).
const setRange = (r) =>
    router.get(route('charts.index'), { range: r, ...(props.period.isCurrent ? {} : { date: props.period.date }) }, { preserveScroll: true });

const goPeriod = (date) => router.get(route('charts.index'), { range: props.range, date }, { preserveScroll: true });

const totalExpense = computed(() => props.byCategory.reduce((s, c) => s + c.total, 0));

const withPct = computed(() =>
    props.byCategory.map((c) => ({
        ...c,
        pct: totalExpense.value ? Math.round((c.total / totalExpense.value) * 1000) / 10 : 0,
    }))
);

// ---- Datos de los gráficos ----
const doughnutData = computed(() => ({
    labels: props.byCategory.map((c) => c.name),
    datasets: [
        {
            data: props.byCategory.map((c) => c.total),
            backgroundColor: props.byCategory.map((c) => c.color),
            borderWidth: 0,
        },
    ],
}));

const barData = computed(() => ({
    labels: props.trend.labels,
    datasets: [
        { label: 'Ingresos', data: props.trend.income, backgroundColor: '#22c55e', borderRadius: 4 },
        { label: 'Gastos', data: props.trend.expense, backgroundColor: '#f43f5e', borderRadius: 4 },
    ],
}));

const lineData = computed(() => ({
    labels: props.trend.labels,
    datasets: [
        {
            label: 'Gastos',
            data: props.trend.expense,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245,158,11,0.15)',
            tension: 0.35,
            fill: true,
            pointRadius: 2,
        },
    ],
}));

const tick = '#a1a1aa';
const grid = 'rgba(148,163,184,0.15)';
const baseOptions = {
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: tick } } },
};
const doughnutOptions = {
    maintainAspectRatio: false,
    cutout: '62%',
    plugins: { legend: { display: false } },
};
const axisOptions = {
    ...baseOptions,
    scales: {
        x: { ticks: { color: tick }, grid: { color: grid } },
        y: { ticks: { color: tick }, grid: { color: grid }, beginAtZero: true },
    },
};

const hasExpenses = computed(() => props.byCategory.length > 0);
</script>

<template>
    <Head title="Gráficos" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-2 w-full max-w-3xl mx-auto">
                <button type="button" class="shrink-0 grid place-items-center h-9 w-9 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800" aria-label="Período anterior" @click="goPeriod(period.prev)">
                    <IconChevronLeft :size="20" />
                </button>
                <h1 class="min-w-0 text-base md:text-lg font-semibold text-center truncate">{{ periodLabel }}</h1>
                <button type="button" class="shrink-0 grid place-items-center h-9 w-9 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 disabled:opacity-30" aria-label="Período siguiente" :disabled="period.isCurrent" @click="goPeriod(period.next)">
                    <IconChevronRight :size="20" />
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-4">
            <!-- Selector de rango -->
            <div class="grid grid-cols-3 rounded-xl bg-surface-200/60 dark:bg-surface-800 p-1">
                <button
                    v-for="r in ranges"
                    :key="r.key"
                    type="button"
                    class="py-2 rounded-lg text-sm font-semibold transition"
                    :class="range === r.key ? 'bg-white dark:bg-surface-900 text-primary shadow' : 'text-surface-500'"
                    @click="setRange(r.key)"
                >
                    {{ r.label }}
                </button>
            </div>

            <!-- Torta: gasto por categoría -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Gastos por categoría</h2>
                    <span class="text-sm font-semibold text-rose-500">{{ money(totalExpense) }}</span>
                </div>

                <div v-if="hasExpenses" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <div class="relative h-56">
                        <Chart type="doughnut" :data="doughnutData" :options="doughnutOptions" class="h-56" />
                    </div>
                    <div class="space-y-2 max-h-56 overflow-y-auto">
                        <div v-for="c in withPct" :key="c.name" class="flex items-center gap-2 text-sm">
                            <span class="h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: c.color }"></span>
                            <span class="flex-1 min-w-0 break-words hyphens-auto">{{ c.name }}</span>
                            <span class="shrink-0 text-surface-500">{{ c.pct }}%</span>
                            <span class="shrink-0 whitespace-nowrap font-medium tabular-nums min-w-24 text-right">{{ money(c.total) }}</span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-surface-500 py-10">Sin gastos en este período.</p>
            </div>

            <!-- Barras: ingresos vs gastos -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <h2 class="font-semibold mb-3">Ingresos vs gastos</h2>
                <div class="h-56">
                    <Chart type="bar" :data="barData" :options="axisOptions" class="h-56" />
                </div>
            </div>

            <!-- Línea: tendencia de gasto -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <h2 class="font-semibold mb-3">Tendencia de gasto</h2>
                <div class="h-56">
                    <Chart type="line" :data="lineData" :options="axisOptions" class="h-56" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
