<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { IconChevronLeft, IconChevronRight, IconTrendingUp, IconTrendingDown } from '@tabler/icons-vue';
import { computed } from 'vue';

const props = defineProps({
    period: { type: Object, required: true },
    totals: { type: Object, required: true },
    expenseByCategory: { type: Array, default: () => [] },
    incomeByCategory: { type: Array, default: () => [] },
    baseSymbol: { type: String, default: '$' },
});

const money = (v) => props.baseSymbol + ' ' + Number(v).toLocaleString('es-AR', { maximumFractionDigits: 2 });
const goMonth = (m) => router.get(route('reports.index'), { month: m }, { preserveScroll: true });

const delta = (current, prev) => {
    if (!prev) return null;
    return Math.round(((current - prev) / prev) * 1000) / 10;
};
const expenseDelta = computed(() => delta(props.totals.expense, props.totals.prevExpense));
const incomeDelta = computed(() => delta(props.totals.income, props.totals.prevIncome));
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
                        <p class="mt-1 font-semibold text-emerald-500 truncate">{{ money(totals.income) }}</p>
                        <p v-if="incomeDelta !== null" class="text-[11px] mt-0.5" :class="incomeDelta >= 0 ? 'text-emerald-500' : 'text-rose-500'">
                            {{ incomeDelta >= 0 ? '▲' : '▼' }} {{ Math.abs(incomeDelta) }}%
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-surface-500">Gastos</p>
                        <p class="mt-1 font-semibold text-rose-500 truncate">{{ money(totals.expense) }}</p>
                        <p v-if="expenseDelta !== null" class="text-[11px] mt-0.5" :class="expenseDelta <= 0 ? 'text-emerald-500' : 'text-rose-500'">
                            {{ expenseDelta >= 0 ? '▲' : '▼' }} {{ Math.abs(expenseDelta) }}%
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-surface-500">Balance</p>
                        <p class="mt-1 font-semibold truncate" :class="totals.balance >= 0 ? 'text-surface-900 dark:text-surface-100' : 'text-rose-500'">{{ money(totals.balance) }}</p>
                    </div>
                </div>
            </div>

            <!-- Desglose de gastos -->
            <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <IconTrendingDown :size="18" class="text-rose-500" />
                    <h2 class="font-semibold">Gastos por categoría</h2>
                </div>
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
        </div>
    </AppLayout>
</template>
