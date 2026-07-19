<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import { IconX, IconPencil, IconTrash, IconArrowsExchange } from '@tabler/icons-vue';
import { confirmAction } from '@/composables/useConfirm';

const props = defineProps({
    transaction: { type: Object, default: null },
    symbols: { type: Object, default: () => ({}) },
    baseSymbol: { type: String, default: '$' },
});
const emit = defineEmits(['close', 'delete']);

const t = computed(() => props.transaction);
const isTransfer = computed(() => t.value?.type === 'transfer');

const fmt = (v) => Number(v).toLocaleString('es-AR', { maximumFractionDigits: 2 });
const money = (v, code) => (props.symbols[code] ?? props.baseSymbol) + ' ' + fmt(v);

const dateLabel = computed(() => {
    if (!t.value) return '';
    const d = new Date(t.value.date + 'T00:00:00');
    return d.toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
});

const typeLabel = computed(() => ({ expense: 'Gasto', income: 'Ingreso', transfer: 'Transferencia' }[t.value?.type] ?? ''));
const amountColor = computed(() =>
    isTransfer.value ? 'text-surface-900 dark:text-surface-100' : (t.value?.type === 'income' ? 'text-emerald-500' : 'text-rose-500')
);

const remove = async () => {
    if (!t.value) return;
    const ok = await confirmAction({ title: '¿Eliminar este movimiento?', message: 'Esta acción no se puede deshacer.', confirmLabel: 'Eliminar', danger: true });
    if (ok) emit('delete', t.value.id);
};
</script>

<template>
    <Teleport to="body">
        <div v-if="t" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 sm:p-4" @click.self="emit('close')">
            <div class="w-full sm:max-w-sm bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800">
                <div class="flex items-center justify-between px-5 h-14 border-b border-surface-200 dark:border-surface-800">
                    <button type="button" class="text-surface-500" @click="emit('close')"><IconX :size="22" /></button>
                    <h2 class="font-semibold">{{ typeLabel }}</h2>
                    <span class="w-6"></span>
                </div>

                <div class="p-5">
                    <!-- Encabezado: ícono + monto -->
                    <div class="flex flex-col items-center text-center gap-2 pb-4">
                        <template v-if="isTransfer">
                            <span class="grid place-items-center h-16 w-16 rounded-full bg-surface-200 dark:bg-surface-700 text-surface-500">
                                <IconArrowsExchange :size="30" />
                            </span>
                        </template>
                        <CategoryIcon
                            v-else
                            :icon-type="t.category?.icon_type ?? 'preset'"
                            :icon-value="t.category?.icon_value ?? 'category'"
                            :icon-url="t.category?.icon_url"
                            :color="t.category?.color ?? '#9ca3af'"
                            :size="64"
                            :icon-size="32"
                        />
                        <p class="text-sm text-surface-500">{{ isTransfer ? 'Transferencia' : (t.category?.name ?? 'Sin categoría') }}</p>
                        <p class="text-3xl font-bold tabular-nums" :class="amountColor">
                            {{ t.type === 'income' ? '+' : (t.type === 'expense' ? '-' : '') }}{{ money(t.amount, t.currency_code) }}
                        </p>
                    </div>

                    <!-- Detalle -->
                    <div class="divide-y divide-surface-100 dark:divide-surface-800 text-sm border-y border-surface-100 dark:border-surface-800">
                        <div v-if="isTransfer" class="flex justify-between py-2.5">
                            <span class="text-surface-500">Cuentas</span>
                            <span class="font-medium text-right">{{ t.from_account }} → {{ t.to_account }}</span>
                        </div>
                        <div v-else class="flex justify-between py-2.5">
                            <span class="text-surface-500">Cuenta</span>
                            <span class="font-medium">{{ t.from_account }}</span>
                        </div>
                        <div v-if="isTransfer && t.transfer_amount" class="flex justify-between py-2.5">
                            <span class="text-surface-500">Recibido</span>
                            <span class="font-medium">{{ money(t.transfer_amount, t.transfer_currency) }}</span>
                        </div>
                        <div class="flex justify-between py-2.5">
                            <span class="text-surface-500">Fecha</span>
                            <span class="font-medium capitalize">{{ dateLabel }}</span>
                        </div>
                        <div v-if="t.note" class="flex justify-between py-2.5 gap-4">
                            <span class="text-surface-500 shrink-0">Nota</span>
                            <span class="font-medium text-right">{{ t.note }}</span>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="flex gap-2 pt-5">
                        <button type="button" class="grid place-items-center h-11 w-11 rounded-xl border border-rose-500/30 text-rose-500 hover:bg-rose-500/10 shrink-0" @click="remove">
                            <IconTrash :size="20" />
                        </button>
                        <Link
                            :href="route('transactions.edit', t.id)"
                            class="flex-1 flex items-center justify-center gap-2 rounded-xl bg-primary py-3 font-semibold text-primary-contrast hover:brightness-95"
                        >
                            <IconPencil :size="20" /> Editar
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
