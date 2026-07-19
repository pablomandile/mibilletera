<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TransactionRow from '@/Components/TransactionRow.vue';
import TransactionDetail from '@/Components/TransactionDetail.vue';
import { Head, router } from '@inertiajs/vue3';
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-vue';

const props = defineProps({
    transactions: { type: Array, default: () => [] },
    period: { type: Object, required: true },
    totals: { type: Object, required: true },
    baseSymbol: { type: String, default: '$' },
    symbols: { type: Object, default: () => ({}) },
});

const fmt = (v) => Number(v).toLocaleString('es-AR', { maximumFractionDigits: 2 });
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
