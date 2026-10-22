<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TransactionRow from '@/Components/TransactionRow.vue';
import TransactionDetail from '@/Components/TransactionDetail.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { mutate } from '@/offline/mutate';
import { openTransactionEdit } from '@/offline/pages';
import { IconSearch, IconX } from '@tabler/icons-vue';

const props = defineProps({
    query: { type: String, default: '' },
    transactions: { type: Array, default: () => [] },
    truncated: { type: Boolean, default: false },
    baseSymbol: { type: String, default: '$' },
    symbols: { type: Object, default: () => ({}) },
});

// ---- Campo de búsqueda: busca mientras se escribe, con una pausa corta ----
const term = ref(props.query);
const input = ref(null);
let timer = null;

const search = () => {
    clearTimeout(timer);
    router.get(route('transactions.search'), term.value.trim() ? { q: term.value.trim() } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

watch(term, (value) => {
    clearTimeout(timer);
    if (value.trim() === props.query) return;
    timer = setTimeout(search, 350);
});

const clear = () => {
    term.value = '';
    input.value?.focus();
};

onMounted(() => { if (!props.query) input.value?.focus(); });

// Borrados encolados sin conexión: se ocultan hasta que vuelva la señal.
const removed = ref(new Set());
const shown = computed(() => props.transactions.filter((t) => !removed.value.has(String(t.id))));

const MONTHS_SHORT = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const WEEKDAYS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// Los resultados cruzan meses y años: el separador lleva el año completo.
function dateLabel(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return { day: `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}`, weekday: WEEKDAYS[d.getDay()] };
}

const groups = computed(() => {
    const map = new Map();
    for (const t of shown.value) {
        if (!map.has(t.date)) map.set(t.date, []);
        map.get(t.date).push(t);
    }
    return Array.from(map.entries()).map(([date, items]) => ({ date, ...dateLabel(date), items }));
});

const countLabel = computed(() => {
    const n = shown.value.length;
    if (props.truncated) return `Mostrando los ${n} más recientes`;
    return n === 1 ? '1 movimiento' : `${n} movimientos`;
});

// ---- Ficha de detalle + borrado ----
const selectedTransaction = ref(null);
const openDetail = (t) => (selectedTransaction.value = t);
const closeDetail = () => (selectedTransaction.value = null);
const deleteTransaction = async (id) => {
    const row = shown.value.find((t) => String(t.id) === String(id));
    const result = await mutate(
        { entity: 'transaction', action: 'delete', id, before: row ?? null },
        (extra) =>
            router.delete(route('transactions.destroy', id), {
                preserveScroll: true,
                onSuccess: () => (selectedTransaction.value = null),
                ...extra,
            })
    );
    if (result === 'queued') {
        removed.value = new Set([...removed.value, String(id)]);
        selectedTransaction.value = null;
    }
};

const page = usePage();
const editTransaction = (row) => openTransactionEdit(row, page.version);
</script>

<template>
    <Head title="Buscar" />

    <AppLayout title="Buscar">
        <div class="mx-auto max-w-3xl space-y-4">
            <form class="relative" role="search" @submit.prevent="search">
                <IconSearch :size="20" class="absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 pointer-events-none" />
                <input
                    ref="input"
                    v-model="term"
                    type="search"
                    enterkeyhint="search"
                    placeholder="Buscar en las notas de ingresos y gastos"
                    aria-label="Buscar en las notas"
                    class="w-full rounded-2xl border border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900 pl-10 pr-10 py-3 text-sm focus:border-primary focus:ring-primary [&::-webkit-search-cancel-button]:hidden"
                />
                <button
                    v-if="term"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 grid place-items-center h-8 w-8 rounded-lg text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800"
                    aria-label="Borrar búsqueda"
                    @click="clear"
                >
                    <IconX :size="18" />
                </button>
            </form>

            <p v-if="query && groups.length" class="px-1 text-xs text-surface-500">{{ countLabel }}</p>

            <div v-if="!query" class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-10 text-center">
                <p class="text-surface-500">Escribí una palabra de la nota para encontrar ingresos y gastos de cualquier fecha.</p>
            </div>

            <div v-else-if="!groups.length" class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-10 text-center">
                <p class="text-surface-500 break-words">No hay ingresos ni gastos con «{{ query }}» en la nota.</p>
            </div>

            <div v-for="group in groups" :key="group.date" class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-2 border-b border-surface-100 dark:border-surface-800 bg-surface-50/50 dark:bg-surface-800/30 text-2xs text-surface-500">
                    <span class="font-medium text-surface-600 dark:text-surface-400 whitespace-nowrap">{{ group.day }}</span>
                    <span>{{ group.weekday }}</span>
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
            @edit="editTransaction"
        />
    </AppLayout>
</template>
