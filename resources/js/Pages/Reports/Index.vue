<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DoughnutChart from '@/Components/DoughnutChart.vue';
import { fixedColor, fixedSlices } from '@/chartColors';
import { IconChevronLeft, IconChevronRight, IconTrendingUp, IconTrendingDown, IconFileDownload, IconRepeat } from '@tabler/icons-vue';
import { computed } from 'vue';
import { formatMoney, formatMoneyWrappable } from '@/money';

const props = defineProps({
    period: { type: Object, required: true },
    totals: { type: Object, required: true },
    fixed: { type: Object, default: () => ({ total: 0, variable: 0, paid: 0, pending: 0, pct: 0, items: [], prevTotal: 0, delta: null }) },
    expenseByCategory: { type: Array, default: () => [] },
    incomeByCategory: { type: Array, default: () => [] },
    bySupercategory: { type: Array, default: () => [] },
    hasSupercategories: { type: Boolean, default: false },
    baseSymbol: { type: String, default: '$' },
});

const money = (v) => formatMoney(v, props.baseSymbol);
const moneyTotal = (v) => formatMoneyWrappable(v, props.baseSymbol);
const goMonth = (m) => router.get(route('reports.index'), { month: m }, { preserveScroll: true });

const delta = (current, prev) => {
    if (!prev) return null;
    return Math.round(((current - prev) / prev) * 1000) / 10;
};
const expenseDelta = computed(() => delta(props.totals.expense, props.totals.prevExpense));
const incomeDelta = computed(() => delta(props.totals.income, props.totals.prevIncome));

// Ancho de cada tramo de la barra fijos | variables (la suma es el gasto del mes).
const fixedShare = computed(() => {
    const all = props.fixed.total + props.fixed.variable;
    return all > 0 ? (props.fixed.total / all) * 100 : 0;
});
const day = (date) => Number(date.slice(8, 10));
const fixedPct = (f) => (props.fixed.total ? Math.round((f.total / props.fixed.total) * 1000) / 10 : 0);
</script>

<template>
    <Head title="Informes" />

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
            <!-- Resumen -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div>
                        <p class="text-xs text-surface-500">Ingresos</p>
                        <p class="mt-1 font-semibold text-emerald-500 break-words hyphens-auto">{{ moneyTotal(totals.income) }}</p>
                        <p v-if="incomeDelta !== null" class="text-2xs mt-0.5" :class="incomeDelta >= 0 ? 'text-emerald-500' : 'text-rose-500'">
                            {{ incomeDelta >= 0 ? '▲' : '▼' }} {{ Math.abs(incomeDelta) }}%
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-surface-500">Gastos</p>
                        <p class="mt-1 font-semibold text-rose-500 break-words hyphens-auto">{{ moneyTotal(totals.expense) }}</p>
                        <p v-if="expenseDelta !== null" class="text-2xs mt-0.5" :class="expenseDelta <= 0 ? 'text-emerald-500' : 'text-rose-500'">
                            {{ expenseDelta >= 0 ? '▲' : '▼' }} {{ Math.abs(expenseDelta) }}%
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-surface-500">Balance</p>
                        <p class="mt-1 font-semibold break-words hyphens-auto" :class="totals.balance >= 0 ? 'text-surface-900 dark:text-surface-100' : 'text-rose-500'">{{ moneyTotal(totals.balance) }}</p>
                    </div>
                </div>
            </div>

            <!-- Gastos fijos -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2">
                        <IconRepeat :size="18" class="text-violet-500" />
                        <h2 class="font-semibold">Gastos fijos</h2>
                    </div>
                    <div v-if="fixed.items.length" class="text-right">
                        <p class="font-semibold whitespace-nowrap tabular-nums">{{ money(fixed.total) }}</p>
                        <p v-if="fixed.delta !== null" class="text-2xs" :class="fixed.delta <= 0 ? 'text-emerald-500' : 'text-rose-500'">
                            {{ fixed.delta >= 0 ? '▲' : '▼' }} {{ Math.abs(fixed.delta) }}% vs mes anterior
                        </p>
                    </div>
                </div>

                <template v-if="fixed.items.length">
                    <!-- Qué parte del gasto del mes es fija -->
                    <p class="text-sm text-surface-500 mb-2">Son el <span class="font-semibold text-surface-900 dark:text-surface-100">{{ fixed.pct }}%</span> de lo que gastaste en el mes.</p>
                    <div class="flex h-2 gap-0.5" role="img" :aria-label="`Fijos ${fixed.pct}%, variables ${Math.round((100 - fixed.pct) * 10) / 10}%`">
                        <div v-if="fixed.total > 0" class="h-full rounded-full bg-violet-500" :style="{ width: fixedShare + '%' }"></div>
                        <div v-if="fixed.variable > 0" class="h-full flex-1 rounded-full bg-rose-500"></div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-surface-500">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-violet-500"></span>Fijos {{ money(fixed.total) }}</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>Variables {{ money(fixed.variable) }}</span>
                    </div>

                    <!-- Pagado / pendiente -->
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-surface-100 dark:bg-surface-800 px-3 py-2">
                            <p class="text-xs text-surface-500">Pagado</p>
                            <p class="font-semibold break-words hyphens-auto">{{ moneyTotal(fixed.paid) }}</p>
                        </div>
                        <div class="rounded-xl bg-surface-100 dark:bg-surface-800 px-3 py-2">
                            <p class="text-xs text-surface-500">Pendiente</p>
                            <p class="font-semibold break-words hyphens-auto" :class="fixed.pending > 0 ? 'text-amber-600 dark:text-amber-400' : ''">{{ moneyTotal(fixed.pending) }}</p>
                        </div>
                    </div>

                    <!-- Torta: solo los fijos, cada uno con su color -->
                    <div class="mt-4">
                        <DoughnutChart :slices="fixedSlices(fixed.items)" :symbol="baseSymbol" />
                    </div>

                    <!-- Uno por uno -->
                    <ul class="mt-4 divide-y divide-surface-200 dark:divide-surface-800">
                        <li v-for="f in fixed.items" :key="f.id" class="flex items-center gap-3 py-2.5">
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
                                <p class="text-xs text-surface-500 break-words hyphens-auto">
                                    {{ f.category }} · día {{ day(f.date) }}<template v-if="!f.active"> · ya no se repite</template>
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-semibold whitespace-nowrap tabular-nums">{{ money(f.total) }}</p>
                                <p class="text-2xs" :class="f.isPaid ? 'text-emerald-600 dark:text-emerald-400' : 'text-surface-500'">{{ f.isPaid ? 'Pagado' : 'Impago' }}</p>
                            </div>
                        </li>
                    </ul>
                </template>
                <div v-else class="py-6 text-center space-y-1">
                    <p class="text-surface-500">Sin gastos fijos este mes.</p>
                    <p class="text-xs text-surface-500">Al cargar un gasto que se repite (alquiler, servicios, suscripciones), tildá «Gasto fijo».</p>
                </div>
            </div>

            <!-- Desglose de gastos -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <IconTrendingDown :size="18" class="text-rose-500" />
                    <h2 class="font-semibold">Gastos por categoría</h2>
                </div>
                <DoughnutChart v-if="expenseByCategory.length" :slices="expenseByCategory" :symbol="baseSymbol" class="mb-4" />
                <div v-if="expenseByCategory.length" class="space-y-3">
                    <div v-for="c in expenseByCategory" :key="c.name">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" :style="{ backgroundColor: c.color }"></span>{{ c.name }}</span>
                            <span class="text-surface-500">{{ c.pct }}% · <span class="font-medium text-surface-900 dark:text-surface-100">{{ money(c.total) }}</span></span>
                        </div>
                        <div class="h-2 rounded-full bg-surface-200 dark:bg-surface-800 overflow-hidden">
                            <div class="h-full rounded-full" :style="{ width: c.pct + '%', backgroundColor: c.color }"></div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-surface-500 py-6">Sin gastos este mes.</p>
            </div>

            <!-- A vuelo de pájaro: gastos por supercategoría -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold">A vuelo de pájaro</h2>
                    <Link v-if="hasSupercategories" href="/supercategories" class="text-xs font-medium text-primary">Editar grupos</Link>
                </div>
                <div v-if="!hasSupercategories" class="py-6 text-center space-y-3">
                    <p class="text-surface-500">
                        Agrupá tus categorías en supercategorías (por ejemplo, Comida = Supermercado + Verdura + Bebidas)
                        para ver tus gastos de un vistazo.
                    </p>
                    <Link href="/supercategories" class="inline-block rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-contrast">Crear supercategorías</Link>
                </div>
                <template v-else-if="bySupercategory.length">
                    <DoughnutChart :slices="bySupercategory" :symbol="baseSymbol" class="mb-4" />
                    <div class="space-y-3">
                        <div v-for="c in bySupercategory" :key="c.name">
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" :style="{ backgroundColor: c.color }"></span>{{ c.name }}</span>
                                <span class="text-surface-500">{{ c.pct }}% · <span class="font-medium text-surface-900 dark:text-surface-100">{{ money(c.total) }}</span></span>
                            </div>
                            <div class="h-2 rounded-full bg-surface-200 dark:bg-surface-800 overflow-hidden">
                                <div class="h-full rounded-full" :style="{ width: c.pct + '%', backgroundColor: c.color }"></div>
                            </div>
                        </div>
                    </div>
                </template>
                <p v-else class="text-center text-surface-500 py-6">Sin gastos este mes.</p>
            </div>

            <!-- Desglose de ingresos -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <IconTrendingUp :size="18" class="text-emerald-500" />
                    <h2 class="font-semibold">Ingresos por categoría</h2>
                </div>
                <div v-if="incomeByCategory.length" class="space-y-3">
                    <div v-for="c in incomeByCategory" :key="c.name">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" :style="{ backgroundColor: c.color }"></span>{{ c.name }}</span>
                            <span class="text-surface-500">{{ c.pct }}% · <span class="font-medium text-surface-900 dark:text-surface-100">{{ money(c.total) }}</span></span>
                        </div>
                        <div class="h-2 rounded-full bg-surface-200 dark:bg-surface-800 overflow-hidden">
                            <div class="h-full rounded-full" :style="{ width: c.pct + '%', backgroundColor: c.color }"></div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-surface-500 py-6">Sin ingresos este mes.</p>
            </div>

            <a
                :href="route('reports.pdf', { month: period.month })"
                class="flex items-center justify-center gap-2 w-full rounded-xl border border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900 py-3 text-sm font-semibold text-primary hover:bg-surface-100 dark:hover:bg-surface-800"
            >
                <IconFileDownload :size="18" />
                Descargar PDF del mes
            </a>
        </div>
    </AppLayout>
</template>
