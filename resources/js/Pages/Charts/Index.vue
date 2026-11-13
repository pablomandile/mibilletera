<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chart from 'primevue/chart';
import { Head, Link, router } from '@inertiajs/vue3';
import { formatMoney } from '@/money';
import DoughnutChart from '@/Components/DoughnutChart.vue';
import { FIXED, VARIABLE, fixedColor, fixedSlices } from '@/chartColors';
import { IconChevronLeft, IconChevronRight, IconFileDownload, IconRepeat } from '@tabler/icons-vue';

const props = defineProps({
    range: { type: String, default: 'month' },
    periodLabel: { type: String, default: '' },
    period: { type: Object, default: () => ({ date: null, prev: null, next: null, isCurrent: true }) },
    byCategory: { type: Array, default: () => [] },
    bySupercategory: { type: Array, default: () => [] },
    hasSupercategories: { type: Boolean, default: false },
    fixed: { type: Object, default: () => ({ total: 0, variable: 0, paid: 0, pending: 0, pct: 0, items: [] }) },
    totals: { type: Object, default: () => ({ income: 0, expense: 0 }) },
    trend: { type: Object, default: () => ({ labels: [], expense: [], income: [] }) },
    yearly: { type: Object, default: () => ({ labels: [], current: [], previous: [], fixed: [], variable: [], year: null }) },
    savings: { type: Object, default: () => ({ monthly: [], accumulated: [] }) },
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

const addPct = (slices) =>
    slices.map((c) => ({
        ...c,
        pct: totalExpense.value ? Math.round((c.total / totalExpense.value) * 1000) / 10 : 0,
    }));
const withPct = computed(() => addPct(props.byCategory));
const superWithPct = computed(() => addPct(props.bySupercategory));

// ---- Datos de los gráficos ----
const balance = computed(() => props.totals.income - props.totals.expense);

// Barras horizontales: total del período, ingresos contra gastos.
const totalsData = computed(() => ({
    labels: ['Ingresos', 'Gastos'],
    datasets: [
        {
            data: [props.totals.income, props.totals.expense],
            backgroundColor: ['#22c55e', '#f43f5e'],
            borderRadius: 4,
            maxBarThickness: 28,
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

// Gasto de cada mes del año del período, contra el año anterior.
const yearlyData = computed(() => ({
    labels: props.yearly.labels,
    datasets: [
        {
            label: String(props.yearly.year),
            data: props.yearly.current,
            borderColor: '#f43f5e',
            backgroundColor: 'rgba(244,63,94,0.12)',
            tension: 0.35,
            fill: true,
            pointRadius: 3,
        },
        {
            label: String(props.yearly.year - 1),
            data: props.yearly.previous,
            borderColor: '#a1a1aa',
            borderDash: [5, 4],
            tension: 0.35,
            pointRadius: 2,
        },
    ],
}));

const sum = (values) => values.reduce((s, v) => s + (v ?? 0), 0);
const yearlyTotal = computed(() => sum(props.yearly.current));

// ---- Gastos fijos ----
// Ancho del tramo de fijos en la barra fijos | variables del período.
const fixedShare = computed(() => {
    const all = props.fixed.total + props.fixed.variable;
    return all > 0 ? (props.fixed.total / all) * 100 : 0;
});
// En un mes o una semana cada fijo aparece una vez (con su día); en el año, sumado.
const fixedDetail = (f) =>
    props.range === 'year'
        ? `${f.category} · ${f.count} ${f.count === 1 ? 'mes' : 'meses'}`
        : `${f.category} · día ${Number(f.date.slice(8, 10))}`;
const fixedPct = (f) => (props.fixed.total ? Math.round((f.total / props.fixed.total) * 1000) / 10 : 0);
const fixedStatus = (f) => (f.isPaid ? 'Pagado' : props.range === 'year' ? `Falta ${money(f.pending)}` : 'Impago');

// Barras apiladas: cuánto de lo gastado cada mes del año fue fijo. El tramo de
// variables arranca 2px más arriba, así se separa del de fijos.
const fixedYearData = computed(() => ({
    labels: props.yearly.labels,
    datasets: [
        { label: 'Fijos', data: props.yearly.fixed, backgroundColor: FIXED, borderRadius: 4, maxBarThickness: 28 },
        {
            label: 'Variables',
            data: props.yearly.variable,
            backgroundColor: VARIABLE,
            borderColor: 'transparent',
            borderWidth: { bottom: 2 },
            borderSkipped: false,
            borderRadius: 4,
            maxBarThickness: 28,
        },
    ],
}));
const fixedYearTotal = computed(() => sum(props.yearly.fixed ?? []));
const hasFixedYear = computed(() => fixedYearTotal.value > 0);

// Ahorro: barras con lo que sobró cada mes y línea con el acumulado del año.
const savingsData = computed(() => ({
    labels: props.yearly.labels,
    datasets: [
        {
            type: 'line',
            label: 'Acumulado',
            data: props.savings.accumulated,
            borderColor: '#0ea5e9',
            backgroundColor: '#0ea5e9',
            tension: 0.35,
            pointRadius: 3,
            order: 0,
        },
        {
            type: 'bar',
            label: 'Del mes',
            data: props.savings.monthly,
            backgroundColor: 'rgba(34,197,94,0.55)',
            borderRadius: 4,
            order: 1,
        },
    ],
}));
const savingsTotal = computed(() => sum(props.savings.monthly));

const tick = '#a1a1aa';
const grid = 'rgba(148,163,184,0.15)';
const baseOptions = {
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: tick } } },
};
const axisOptions = {
    ...baseOptions,
    scales: {
        x: { ticks: { color: tick }, grid: { color: grid } },
        y: { ticks: { color: tick }, grid: { color: grid }, beginAtZero: true },
    },
};

const totalsOptions = {
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => money(ctx.parsed.x) } },
    },
    scales: {
        x: { ticks: { color: tick }, grid: { color: grid }, beginAtZero: true },
        y: { ticks: { color: tick }, grid: { display: false } },
    },
};

const yearlyOptions = {
    ...axisOptions,
    plugins: {
        ...baseOptions.plugins,
        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.parsed.y)}` } },
    },
};

const fixedYearOptions = {
    ...yearlyOptions,
    scales: {
        x: { ...axisOptions.scales.x, stacked: true },
        y: { ...axisOptions.scales.y, stacked: true },
    },
    plugins: {
        ...yearlyOptions.plugins,
        tooltip: {
            mode: 'index',
            callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${money(ctx.parsed.y)}`,
                footer: (items) => `Total: ${money(items.reduce((s, i) => s + i.parsed.y, 0))}`,
            },
        },
    },
};

const hasExpenses = computed(() => props.byCategory.length > 0);

// El PDF es de un mes: con el rango Mes, el que se está mirando.
const pdfUrl = computed(() => route('reports.pdf', { month: props.period.date?.slice(0, 7) }));
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

            <!-- Barras horizontales: total de ingresos vs total de gastos -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold">Ingresos vs gastos</h2>
                    <span class="text-sm font-semibold whitespace-nowrap tabular-nums" :class="balance < 0 ? 'text-rose-500' : 'text-emerald-500'">{{ money(balance) }}</span>
                </div>
                <div class="h-32">
                    <Chart type="bar" :data="totalsData" :options="totalsOptions" class="h-32" />
                </div>
            </div>

            <!-- Torta: gasto por categoría -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Gastos por categoría</h2>
                    <span class="text-sm font-semibold text-rose-500">{{ money(totalExpense) }}</span>
                </div>

                <div v-if="hasExpenses" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <DoughnutChart :slices="byCategory" :symbol="currencySymbol" />
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

            <!-- Torta: gasto por supercategoría -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold">A vuelo de pájaro</h2>
                    <Link v-if="hasSupercategories" href="/supercategories" class="text-xs font-medium text-primary">Editar grupos</Link>
                </div>

                <div v-if="!hasSupercategories" class="py-8 text-center space-y-3">
                    <p class="text-surface-500">
                        Agrupá tus categorías en supercategorías (por ejemplo, Comida = Supermercado + Verdura + Bebidas)
                        para ver tus gastos de un vistazo.
                    </p>
                    <Link href="/supercategories" class="inline-block rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-contrast">Crear supercategorías</Link>
                </div>
                <div v-else-if="hasExpenses" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <DoughnutChart :slices="bySupercategory" :symbol="currencySymbol" />
                    <div class="space-y-2 max-h-56 overflow-y-auto">
                        <div v-for="c in superWithPct" :key="c.name" class="flex items-center gap-2 text-sm">
                            <span class="h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: c.color }"></span>
                            <span class="flex-1 min-w-0 break-words hyphens-auto">{{ c.name }}</span>
                            <span class="shrink-0 text-surface-500">{{ c.pct }}%</span>
                            <span class="shrink-0 whitespace-nowrap font-medium tabular-nums min-w-24 text-right">{{ money(c.total) }}</span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-surface-500 py-10">Sin gastos en este período.</p>
            </div>

            <!-- Gastos fijos del período -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold">Gastos fijos</h2>
                    <span v-if="fixed.items.length" class="text-sm font-semibold whitespace-nowrap tabular-nums text-violet-600 dark:text-violet-400">{{ money(fixed.total) }}</span>
                </div>

                <template v-if="fixed.items.length">
                    <p class="text-sm text-surface-500 mb-2">
                        Son el <span class="font-semibold text-surface-900 dark:text-surface-100">{{ fixed.pct }}%</span> de tus gastos<template v-if="fixed.pending > 0"> · falta pagar <span class="font-semibold text-surface-900 dark:text-surface-100 whitespace-nowrap">{{ money(fixed.pending) }}</span></template>.
                    </p>
                    <div class="flex h-2 gap-0.5" role="img" :aria-label="`Fijos ${fixed.pct}%, variables ${Math.round((100 - fixed.pct) * 10) / 10}%`">
                        <div v-if="fixed.total > 0" class="h-full rounded-full" :style="{ width: fixedShare + '%', backgroundColor: FIXED }"></div>
                        <div v-if="fixed.variable > 0" class="h-full flex-1 rounded-full" :style="{ backgroundColor: VARIABLE }"></div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-surface-500">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: FIXED }"></span>Fijos {{ money(fixed.total) }}</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: VARIABLE }"></span>Variables {{ money(fixed.variable) }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <!-- Torta: solo los fijos, cada uno con su color -->
                    <DoughnutChart :slices="fixedSlices(fixed.items)" :symbol="currencySymbol" />
                    <ul class="max-h-72 overflow-y-auto divide-y divide-surface-200 dark:divide-surface-800">
                        <li v-for="f in fixed.items" :key="f.id" class="flex items-center gap-3 py-2">
                            <IconRepeat
                                :size="18"
                                class="shrink-0"
                                :class="f.isPaid ? 'text-emerald-500' : 'text-surface-400'"
                                :aria-label="f.isPaid ? 'Pagado' : 'Impago'"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium break-words hyphens-auto">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full mr-1.5 align-[1px]" :style="{ backgroundColor: fixedColor(f.id) }"></span>{{ f.name }}
                                    <span class="text-xs font-normal text-surface-500">{{ fixedPct(f) }}%</span>
                                </p>
                                <p class="text-xs text-surface-500 break-words hyphens-auto">{{ fixedDetail(f) }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-medium whitespace-nowrap tabular-nums">{{ money(f.total) }}</p>
                                <p class="text-2xs whitespace-nowrap" :class="f.isPaid ? 'text-emerald-600 dark:text-emerald-400' : 'text-surface-500'">{{ fixedStatus(f) }}</p>
                            </div>
                        </li>
                    </ul>
                    </div>
                </template>
                <div v-else class="py-8 text-center space-y-1">
                    <p class="text-surface-500">Sin gastos fijos en este período.</p>
                    <p class="text-xs text-surface-500">Al cargar un gasto que se repite (alquiler, servicios, suscripciones), tildá «Gasto fijo».</p>
                </div>
            </div>

            <!-- Barras: ingresos vs gastos -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <h2 class="font-semibold mb-3">Ingresos vs gastos por {{ range === 'year' ? 'mes' : 'día' }}</h2>
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

            <!-- Línea: tendencia anual de gastos, contra el año anterior -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold">Tendencia anual de gastos</h2>
                    <span class="text-sm font-semibold whitespace-nowrap tabular-nums text-rose-500">{{ money(yearlyTotal) }}</span>
                </div>
                <div class="h-56">
                    <Chart type="line" :data="yearlyData" :options="yearlyOptions" class="h-56" />
                </div>
            </div>

            <!-- Barras apiladas: fijos y variables de cada mes del año -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h2 class="font-semibold">Fijos y variables {{ yearly.year }}</h2>
                    <span v-if="hasFixedYear" class="text-sm font-semibold whitespace-nowrap tabular-nums text-violet-600 dark:text-violet-400">{{ money(fixedYearTotal) }}</span>
                </div>
                <p class="text-xs text-surface-500 mb-3">Cuánto de lo que gastaste cada mes fue fijo.</p>
                <div v-if="hasFixedYear" class="h-56">
                    <Chart type="bar" :data="fixedYearData" :options="fixedYearOptions" class="h-56" />
                </div>
                <p v-else class="text-center text-surface-500 py-10">Sin gastos fijos en {{ yearly.year }}.</p>
            </div>

            <!-- Ahorro acumulado del año -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h2 class="font-semibold">Ahorro acumulado {{ yearly.year }}</h2>
                    <span class="text-sm font-semibold whitespace-nowrap tabular-nums text-sky-500">{{ money(savingsTotal) }}</span>
                </div>
                <p class="text-xs text-surface-500 mb-3">Lo que sobra cada mes entre ingresos y gastos. Un mes en rojo suma 0.</p>
                <div class="h-56">
                    <Chart type="bar" :data="savingsData" :options="yearlyOptions" class="h-56" />
                </div>
            </div>

            <a
                v-if="range === 'month'"
                :href="pdfUrl"
                class="flex items-center justify-center gap-2 w-full rounded-xl border border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900 py-3 text-sm font-semibold text-primary hover:bg-surface-100 dark:hover:bg-surface-800"
            >
                <IconFileDownload :size="18" />
                Descargar PDF del mes
            </a>
        </div>
    </AppLayout>
</template>
