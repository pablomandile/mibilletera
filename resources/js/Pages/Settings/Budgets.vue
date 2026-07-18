<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { IconX, IconTrash } from '@tabler/icons-vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    global: { type: Object, default: () => ({ amount: null, spent: 0, id: null }) },
    baseSymbol: { type: String, default: '$' },
    periodLabel: { type: String, default: '' },
});

const money = (v) => props.baseSymbol + ' ' + Number(v).toLocaleString('es-AR', { maximumFractionDigits: 2 });

const pct = (spent, budget) => (budget > 0 ? Math.min(100, Math.round((spent / budget) * 100)) : 0);
const ratio = (spent, budget) => (budget > 0 ? spent / budget : 0);
const barColor = (spent, budget) => {
    const r = ratio(spent, budget);
    if (r > 1) return 'bg-rose-500';
    if (r >= 0.8) return 'bg-amber-500';
    return 'bg-emerald-500';
};

// --- Diálogo ---
const editing = ref(null); // { categoryId, budgetId, name }
const form = useForm({ category_id: null, amount: null });

const openEdit = (item) => {
    editing.value = {
        categoryId: item.id ?? null,       // null = global
        budgetId: item.budget_id ?? item.budgetGlobalId ?? null,
        name: item.name,
    };
    form.category_id = item.id ?? null;
    form.amount = item.budget ?? null;
    form.clearErrors();
};

const openGlobal = () => {
    editing.value = { categoryId: null, budgetId: props.global.id, name: 'Presupuesto total del mes' };
    form.category_id = null;
    form.amount = props.global.amount;
    form.clearErrors();
};

const close = () => (editing.value = null);

const save = () => {
    form.post(route('budgets.store'), { preserveScroll: true, onSuccess: () => close() });
};

const remove = () => {
    if (!editing.value?.budgetId) return close();
    router.delete(route('budgets.destroy', editing.value.budgetId), { preserveScroll: true, onSuccess: () => close() });
};

const withBudget = computed(() => props.categories.filter((c) => c.budget !== null));
const withoutBudget = computed(() => props.categories.filter((c) => c.budget === null));
</script>

<template>
    <Head title="Presupuestos" />

    <AppLayout title="Presupuestos">
        <div class="mx-auto max-w-2xl space-y-5">
            <p class="text-sm text-surface-500 capitalize">{{ periodLabel }}</p>

            <!-- Presupuesto global -->
            <button type="button" class="w-full text-left rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4" @click="openGlobal">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-semibold">Presupuesto total</span>
                    <span class="text-sm" :class="global.amount && global.spent > global.amount ? 'text-rose-500' : 'text-surface-500'">
                        {{ money(global.spent) }} <span v-if="global.amount">/ {{ money(global.amount) }}</span>
                    </span>
                </div>
                <div v-if="global.amount" class="h-2 rounded-full bg-surface-200 dark:bg-surface-800 overflow-hidden">
                    <div class="h-full rounded-full transition-all" :class="barColor(global.spent, global.amount)" :style="{ width: pct(global.spent, global.amount) + '%' }"></div>
                </div>
                <p v-else class="text-sm text-primary">Definir presupuesto total →</p>
            </button>

            <!-- Categorías con presupuesto -->
            <div v-if="withBudget.length" class="space-y-2">
                <p class="px-1 text-xs font-medium uppercase tracking-wide text-surface-500">Con presupuesto</p>
                <button v-for="c in withBudget" :key="c.id" type="button" class="w-full text-left rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4" @click="openEdit(c)">
                    <div class="flex items-center gap-3 mb-2">
                        <CategoryIcon :icon-type="c.icon_type" :icon-value="c.icon_value" :color="c.color" :size="38" :icon-size="20" />
                        <span class="flex-1 font-medium">{{ c.name }}</span>
                        <span class="text-sm" :class="c.spent > c.budget ? 'text-rose-500 font-medium' : 'text-surface-500'">
                            {{ money(c.spent) }} / {{ money(c.budget) }}
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-surface-200 dark:bg-surface-800 overflow-hidden">
                        <div class="h-full rounded-full transition-all" :class="barColor(c.spent, c.budget)" :style="{ width: pct(c.spent, c.budget) + '%' }"></div>
                    </div>
                    <p v-if="c.spent > c.budget" class="mt-1 text-xs text-rose-500">Excedido por {{ money(c.spent - c.budget) }}</p>
                </button>
            </div>

            <!-- Categorías sin presupuesto -->
            <div v-if="withoutBudget.length" class="space-y-2">
                <p class="px-1 text-xs font-medium uppercase tracking-wide text-surface-500">Sin presupuesto</p>
                <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 divide-y divide-surface-100 dark:divide-surface-800 overflow-hidden">
                    <button v-for="c in withoutBudget" :key="c.id" type="button" class="w-full flex items-center gap-3 px-4 py-3 hover:bg-surface-50 dark:hover:bg-surface-800 text-left" @click="openEdit(c)">
                        <CategoryIcon :icon-type="c.icon_type" :icon-value="c.icon_value" :color="c.color" :size="34" :icon-size="18" />
                        <span class="flex-1 text-sm font-medium">{{ c.name }}</span>
                        <span class="text-xs text-surface-500">{{ money(c.spent) }} gastados</span>
                        <span class="text-xs text-primary">Definir</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Diálogo definir presupuesto -->
        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 sm:p-4" @click.self="close">
                <div class="w-full sm:max-w-sm bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800">
                    <div class="flex items-center justify-between px-5 h-14 border-b border-surface-200 dark:border-surface-800">
                        <button type="button" class="text-surface-500" @click="close"><IconX :size="22" /></button>
                        <h2 class="font-semibold truncate px-2">{{ editing.name }}</h2>
                        <span class="w-6"></span>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="text-xs text-surface-500">Límite mensual ({{ baseSymbol }})</label>
                            <input v-model="form.amount" type="number" step="0.01" min="0" autofocus
                                class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 text-lg focus:border-primary focus:ring-primary" />
                            <p v-if="form.errors.amount" class="mt-1 text-sm text-rose-500">{{ form.errors.amount }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button v-if="editing.budgetId" type="button" class="grid place-items-center h-11 w-11 rounded-xl border border-rose-500/30 text-rose-500 hover:bg-rose-500/10" @click="remove">
                                <IconTrash :size="20" />
                            </button>
                            <button type="button" class="flex-1 rounded-xl bg-primary py-3 font-semibold text-primary-contrast disabled:opacity-50" :disabled="form.processing" @click="save">
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
