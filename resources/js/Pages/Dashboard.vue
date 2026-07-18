<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import { Head, router } from '@inertiajs/vue3';
import { IconChevronLeft, IconChevronRight, IconArrowsExchange } from '@tabler/icons-vue';

const props = defineProps({
    transactions: { type: Array, default: () => [] },
    period: { type: Object, required: true },
    totals: { type: Object, required: true },
    baseSymbol: { type: String, default: '$' },
    symbols: { type: Object, default: () => ({}) },
});

const fmt = (v) => Number(v).toLocaleString('es-AR', { maximumFractionDigits: 2 });
const money = (v, code) => (props.symbols[code] ?? props.baseSymbol) + ' ' + fmt(v);
const moneyBase = (v) => props.baseSymbol + ' ' + fmt(v);

const goMonth = (m) => router.get(route('dashboard'), { month: m }, { preserveScroll: true });

function dateLabel(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    const today = new Date();
    const yest = new Date();
    yest.setDate(today.getDate() - 1);
    const iso = (x) => x.toISOString().slice(0, 10);
    if (iso(d) === iso(today)) return 'Hoy';
    if (iso(d) === iso(yest)) return 'Ayer';
    return d.toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' });
}

const groups = computed(() => {
    const map = new Map();
    for (const t of props.transactions) {
        if (!map.has(t.date)) map.set(t.date, []);
        map.get(t.date).push(t);
    }
    return Array.from(map.entries()).map(([date, items]) => ({
        date,
        label: dateLabel(date),
        items,
        income: items.filter((i) => i.type === 'income').reduce((s, i) => s + i.amount * 1, 0),
        expense: items.filter((i) => i.type === 'expense').reduce((s, i) => s + i.amount * 1, 0),
    }));
});
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
            <!-- Resumen del período (en moneda base) -->
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-3 md:p-4">
                    <p class="text-xs text-surface-500">Ingresos</p>
                    <p class="mt-1 text-sm md:text-lg font-semibold text-emerald-500 truncate">{{ moneyBase(totals.income) }}</p>
                </div>
                <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-3 md:p-4">
                    <p class="text-xs text-surface-500">Gastos</p>
                    <p class="mt-1 text-sm md:text-lg font-semibold text-rose-500 truncate">{{ moneyBase(totals.expense) }}</p>
                </div>
                <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-3 md:p-4">
                    <p class="text-xs text-surface-500">Balance</p>
                    <p class="mt-1 text-sm md:text-lg font-semibold truncate" :class="totals.balance >= 0 ? 'text-surface-900 dark:text-surface-100' : 'text-rose-500'">
                        {{ moneyBase(totals.balance) }}
                    </p>
                </div>
            </div>

            <div v-if="!groups.length" class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-10 text-center">
                <p class="text-surface-500">
                    No hay movimientos en este período. Tocá el botón
                    <span class="text-primary font-semibold">+</span> para cargar uno.
                </p>
            </div>

            <div v-for="group in groups" :key="group.date" class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2.5 border-b border-surface-100 dark:border-surface-800 bg-surface-50/50 dark:bg-surface-800/30">
                    <span class="text-sm font-medium capitalize">{{ group.label }}</span>
                </div>

                <div class="divide-y divide-surface-100 dark:divide-surface-800">
                    <div v-for="t in group.items" :key="t.id" class="flex items-center gap-3 px-4 py-3">
                        <!-- Transferencia -->
                        <template v-if="t.type === 'transfer'">
                            <span class="grid place-items-center h-[42px] w-[42px] rounded-full bg-surface-200 dark:bg-surface-700 text-surface-500 shrink-0">
                                <IconArrowsExchange :size="22" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium truncate">Transferencia</p>
                                <p class="text-xs text-surface-500 truncate">{{ t.from_account }} → {{ t.to_account }}</p>
                            </div>
                            <span class="text-sm font-semibold tabular-nums text-surface-500">{{ money(t.amount, t.currency_code) }}</span>
                        </template>

                        <!-- Gasto / ingreso -->
                        <template v-else>
                            <CategoryIcon
                                :icon-type="t.category?.icon_type ?? 'preset'"
                                :icon-value="t.category?.icon_value ?? 'category'"
                                :icon-url="t.category?.icon_url"
                                :color="t.category?.color ?? '#9ca3af'"
                                :size="42"
                                :icon-size="22"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium truncate">{{ t.category?.name ?? 'Sin categoría' }}</p>
                                <p v-if="t.note" class="text-xs text-surface-500 truncate">{{ t.note }}</p>
                            </div>
                            <span class="text-sm font-semibold tabular-nums" :class="t.type === 'income' ? 'text-emerald-500' : 'text-rose-500'">
                                {{ t.type === 'income' ? '+' : '-' }}{{ money(t.amount, t.currency_code) }}
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
