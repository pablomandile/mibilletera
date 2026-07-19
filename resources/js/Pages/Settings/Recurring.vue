<script setup>
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { IconX, IconTrash, IconPlus, IconRepeat } from '@tabler/icons-vue';
import { confirmAction } from '@/composables/useConfirm';

const props = defineProps({
    items: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    symbols: { type: Object, default: () => ({}) },
});

const money = (v, code) => (props.symbols[code] ?? '$') + ' ' + Number(v).toLocaleString('es-AR', { maximumFractionDigits: 2 });

const FREQ = [
    { value: 'daily', label: 'Día' },
    { value: 'weekly', label: 'Semana' },
    { value: 'monthly', label: 'Mes' },
    { value: 'yearly', label: 'Año' },
];
const freqLabel = (f, n) => {
    const plural = { daily: ['día', 'días'], weekly: ['semana', 'semanas'], monthly: ['mes', 'meses'], yearly: ['año', 'años'] }[f];
    return n === 1 ? `Cada ${plural[0]}` : `Cada ${n} ${plural[1]}`;
};

const today = new Date();
const isoToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

const showDialog = ref(false);
const editingId = ref(null);
const form = useForm({
    type: 'expense', account_id: props.accounts[0]?.id ?? null, category_id: null,
    amount: null, frequency: 'monthly', interval: 1, next_run_date: isoToday, end_date: '', note: '', is_active: true,
});

const formCategories = computed(() => props.categories.filter((c) => c.type === form.type));

const openNew = () => {
    editingId.value = null;
    form.reset();
    form.account_id = props.accounts[0]?.id ?? null;
    form.next_run_date = isoToday;
    form.clearErrors();
    showDialog.value = true;
};
const openEdit = (r) => {
    editingId.value = r.id;
    form.type = r.type;
    form.account_id = props.accounts.find((a) => a.name === r.account)?.id ?? props.accounts[0]?.id;
    form.category_id = null; // se re-selecciona abajo
    form.amount = r.amount;
    form.frequency = r.frequency;
    form.interval = r.interval;
    form.next_run_date = r.next_run_date;
    form.end_date = r.end_date ?? '';
    form.note = r.note ?? '';
    form.is_active = r.is_active;
    // buscar la categoría por nombre (el índice manda íconos)
    const cat = props.categories.find((c) => c.type === r.type && c.name === r.category?.name);
    form.category_id = cat?.id ?? null;
    form.clearErrors();
    showDialog.value = true;
};
const close = () => (showDialog.value = false);

watch(() => form.type, () => {
    if (!formCategories.value.some((c) => c.id === form.category_id)) form.category_id = null;
});

const save = () => {
    const opts = { preserveScroll: true, onSuccess: () => close() };
    if (editingId.value) form.patch(route('recurring.update', editingId.value), opts);
    else form.post(route('recurring.store'), opts);
};
const remove = async () => {
    if (!editingId.value) return;
    const ok = await confirmAction({ title: '¿Eliminar esta recurrente?', message: 'Esta acción no se puede deshacer.', confirmLabel: 'Eliminar', danger: true });
    if (!ok) return;
    router.delete(route('recurring.destroy', editingId.value), { preserveScroll: true, onSuccess: () => close() });
};
</script>

<template>
    <Head title="Recurrentes" />

    <AppLayout title="Transacciones recurrentes">
        <div class="mx-auto max-w-2xl space-y-4">
            <p class="text-sm text-surface-500">
                Se generan automáticamente cada día mediante una tarea programada
                (<code class="text-xs">php artisan schedule:work</code>).
            </p>

            <div v-if="items.length" class="space-y-2">
                <button v-for="r in items" :key="r.id" type="button"
                    class="w-full flex items-center gap-3 rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4 text-left hover:bg-surface-50 dark:hover:bg-surface-800"
                    :class="!r.is_active ? 'opacity-60' : ''"
                    @click="openEdit(r)">
                    <CategoryIcon :icon-type="r.category?.icon_type ?? 'preset'" :icon-value="r.category?.icon_value ?? 'category'" :icon-url="r.category?.icon_url" :color="r.category?.color ?? '#9ca3af'" :size="42" :icon-size="22" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium truncate">{{ r.category?.name ?? 'Sin categoría' }}</p>
                        <p class="text-xs text-surface-500">{{ freqLabel(r.frequency, r.interval) }} · próx. {{ r.next_run_date }}<span v-if="!r.is_active"> · pausada</span></p>
                    </div>
                    <span class="text-sm font-semibold tabular-nums" :class="r.type === 'income' ? 'text-emerald-500' : 'text-rose-500'">
                        {{ r.type === 'income' ? '+' : '-' }}{{ money(r.amount, r.currency_code) }}
                    </span>
                </button>
            </div>
            <div v-else class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-8 text-center text-surface-500">
                <IconRepeat :size="32" class="mx-auto mb-2 text-surface-400" />
                Todavía no tenés transacciones recurrentes.
            </div>

            <button type="button" class="w-full flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-surface-300 dark:border-surface-700 py-4 text-surface-500 hover:border-primary hover:text-primary transition" @click="openNew">
                <IconPlus :size="20" /> Nueva recurrente
            </button>
        </div>

        <!-- Diálogo -->
        <Teleport to="body">
            <div v-if="showDialog" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 sm:p-4" @click.self="close">
                <div class="w-full sm:max-w-md bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800 max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-5 h-14 border-b border-surface-200 dark:border-surface-800 sticky top-0 bg-white dark:bg-surface-900">
                        <button type="button" class="text-surface-500" @click="close"><IconX :size="22" /></button>
                        <h2 class="font-semibold">{{ editingId ? 'Editar recurrente' : 'Nueva recurrente' }}</h2>
                        <span class="w-6"></span>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 rounded-xl bg-surface-200/60 dark:bg-surface-800 p-1">
                            <button type="button" class="py-2 rounded-lg text-sm font-semibold" :class="form.type === 'expense' ? 'bg-white dark:bg-surface-900 text-rose-500 shadow' : 'text-surface-500'" @click="form.type = 'expense'">Gasto</button>
                            <button type="button" class="py-2 rounded-lg text-sm font-semibold" :class="form.type === 'income' ? 'bg-white dark:bg-surface-900 text-emerald-500 shadow' : 'text-surface-500'" @click="form.type = 'income'">Ingreso</button>
                        </div>

                        <div>
                            <label class="text-xs text-surface-500">Categoría</label>
                            <select v-model="form.category_id" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800">
                                <option :value="null" disabled>Elegí una categoría</option>
                                <option v-for="c in formCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="mt-1 text-sm text-rose-500">{{ form.errors.category_id }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-surface-500">Cuenta</label>
                                <select v-model="form.account_id" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800">
                                    <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-surface-500">Monto</label>
                                <input v-model="form.amount" type="number" step="0.01" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800" />
                                <p v-if="form.errors.amount" class="mt-1 text-sm text-rose-500">{{ form.errors.amount }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 items-end">
                            <div>
                                <label class="text-xs text-surface-500">Frecuencia</label>
                                <select v-model="form.frequency" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800">
                                    <option v-for="f in FREQ" :key="f.value" :value="f.value">{{ f.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-surface-500">Cada</label>
                                <input v-model="form.interval" type="number" min="1" max="365" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-surface-500">Desde</label>
                                <input v-model="form.next_run_date" type="date" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800" />
                            </div>
                            <div>
                                <label class="text-xs text-surface-500">Hasta (opcional)</label>
                                <input v-model="form.end_date" type="date" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800" />
                                <p v-if="form.errors.end_date" class="mt-1 text-sm text-rose-500">{{ form.errors.end_date }}</p>
                            </div>
                        </div>

                        <input v-model="form.note" type="text" placeholder="Nota (opcional)" class="w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 text-sm" />

                        <label v-if="editingId" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="form.is_active" class="rounded border-surface-300 dark:border-surface-700 text-primary focus:ring-primary" />
                            Activa
                        </label>

                        <div class="flex gap-2 pt-1">
                            <button v-if="editingId" type="button" class="grid place-items-center h-11 w-11 rounded-xl border border-rose-500/30 text-rose-500 hover:bg-rose-500/10" @click="remove"><IconTrash :size="20" /></button>
                            <button type="button" class="flex-1 rounded-xl bg-primary py-3 font-semibold text-primary-contrast disabled:opacity-50" :disabled="form.processing" @click="save">{{ editingId ? 'Guardar' : 'Crear' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
