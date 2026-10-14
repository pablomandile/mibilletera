<script setup>
import CategoryIcon from '@/Components/CategoryIcon.vue';
import { IconArrowsExchange, IconTrash, IconCloudOff } from '@tabler/icons-vue';
import { confirmAction } from '@/composables/useConfirm';
import { useSwipeRow } from '@/composables/useSwipeRow';
import { formatMoney } from '@/money';

const props = defineProps({
    transaction: { type: Object, required: true },
    symbols: { type: Object, default: () => ({}) },
    baseSymbol: { type: String, default: '$' },
});
const emit = defineEmits(['open', 'delete']);

const money = (v, code) => formatMoney(v, props.symbols[code] ?? props.baseSymbol);

// ---- Swipe a la izquierda para borrar, toque para abrir la ficha ----
const { dx, swiping, onDown, onMove, onUp, onCancel } = useSwipeRow({
    onTap: () => emit('open', props.transaction),
    onRelease: async (offset) => {
        if (offset > -80) {
            dx.value = 0;
            return;
        }
        dx.value = -100;
        const ok = await confirmAction({
            title: '¿Eliminar este movimiento?',
            message: 'Esta acción no se puede deshacer.',
            confirmLabel: 'Eliminar',
            danger: true,
        });
        if (ok) emit('delete', props.transaction.id);
        else dx.value = 0;
    },
});
</script>

<template>
    <div class="relative overflow-hidden">
        <!-- Fondo de borrado -->
        <div class="absolute inset-0 flex items-center justify-end bg-rose-500 pr-5 text-white">
            <IconTrash :size="20" />
        </div>

        <!-- Contenido deslizable -->
        <div
            class="piggy-surface relative flex items-center gap-3 px-4 py-3 bg-white dark:bg-surface-900 cursor-pointer select-none touch-pan-y"
            :class="swiping ? '' : 'transition-transform duration-150'"
            :style="{ transform: `translateX(${dx}px)` }"
            @pointerdown="onDown"
            @pointermove="onMove"
            @pointerup="onUp"
            @pointercancel="onCancel"
        >
            <!-- Transferencia -->
            <template v-if="transaction.type === 'transfer'">
                <span class="grid place-items-center h-[42px] w-[42px] rounded-full bg-surface-200 dark:bg-surface-700 text-surface-500 shrink-0">
                    <IconArrowsExchange :size="22" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium break-words hyphens-auto">Transferencia</p>
                    <p class="text-xs text-surface-500 break-words hyphens-auto">{{ transaction.from_account }} → {{ transaction.to_account }}</p>
                </div>
                <span class="shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums text-surface-500">{{ money(transaction.amount, transaction.currency_code) }}</span>
                <IconCloudOff v-if="transaction._pending" :size="16" class="shrink-0 text-amber-500" aria-label="Sin sincronizar" />
            </template>

            <!-- Gasto / ingreso -->
            <template v-else>
                <CategoryIcon
                    :icon-type="transaction.category?.icon_type ?? 'preset'"
                    :icon-value="transaction.category?.icon_value ?? 'category'"
                    :icon-url="transaction.category?.icon_url"
                    :color="transaction.category?.color ?? '#9ca3af'"
                    :size="42"
                    :icon-size="22"
                />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium break-words hyphens-auto">{{ transaction.category?.name ?? 'Sin categoría' }}</p>
                    <p v-if="transaction.note" class="text-xs text-surface-500 break-words hyphens-auto">{{ transaction.note }}</p>
                </div>
                <span class="shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums" :class="transaction.type === 'income' ? 'text-emerald-500' : 'text-rose-500'">
                    {{ transaction.type === 'income' ? '+' : '-' }}{{ money(transaction.amount, transaction.currency_code) }}
                </span>
                <IconCloudOff v-if="transaction._pending" :size="16" class="shrink-0 text-amber-500" aria-label="Sin sincronizar" />
            </template>
        </div>
    </div>
</template>
