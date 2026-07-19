<script setup>
import { ref } from 'vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import { IconArrowsExchange, IconTrash } from '@tabler/icons-vue';
import { confirmAction } from '@/composables/useConfirm';

const props = defineProps({
    transaction: { type: Object, required: true },
    symbols: { type: Object, default: () => ({}) },
    baseSymbol: { type: String, default: '$' },
});
const emit = defineEmits(['open', 'delete']);

const fmt = (v) => Number(v).toLocaleString('es-AR', { maximumFractionDigits: 2 });
const money = (v, code) => (props.symbols[code] ?? props.baseSymbol) + ' ' + fmt(v);

// ---- Swipe a la izquierda para borrar ----
const dx = ref(0);
const dragging = ref(false);
let startX = 0;
let moved = 0;

const onDown = (e) => {
    dragging.value = true;
    startX = e.clientX;
    moved = 0;
    e.currentTarget.setPointerCapture?.(e.pointerId);
};
const onMove = (e) => {
    if (!dragging.value) return;
    const d = e.clientX - startX;
    moved = Math.max(moved, Math.abs(d));
    dx.value = Math.max(-160, Math.min(0, d)); // solo hacia la izquierda
};
const onUp = async () => {
    if (!dragging.value) return;
    dragging.value = false;

    if (dx.value <= -80) {
        dx.value = -100;
        const ok = await confirmAction({
            title: '¿Eliminar este movimiento?',
            message: 'Esta acción no se puede deshacer.',
            confirmLabel: 'Eliminar',
            danger: true,
        });
        if (ok) emit('delete', props.transaction.id);
        else dx.value = 0;
        return;
    }

    // Tap (sin desplazamiento real) → abrir ficha
    if (moved < 6) emit('open', props.transaction);
    dx.value = 0;
};
</script>

<template>
    <div class="relative overflow-hidden">
        <!-- Fondo de borrado -->
        <div class="absolute inset-0 flex items-center justify-end bg-rose-500 pr-5 text-white">
            <IconTrash :size="20" />
        </div>

        <!-- Contenido deslizable -->
        <div
            class="relative flex items-center gap-3 px-4 py-3 bg-white dark:bg-surface-900 cursor-pointer select-none touch-pan-y"
            :class="dragging ? '' : 'transition-transform duration-150'"
            :style="{ transform: `translateX(${dx}px)` }"
            @pointerdown="onDown"
            @pointermove="onMove"
            @pointerup="onUp"
            @pointercancel="onUp"
        >
            <!-- Transferencia -->
            <template v-if="transaction.type === 'transfer'">
                <span class="grid place-items-center h-[42px] w-[42px] rounded-full bg-surface-200 dark:bg-surface-700 text-surface-500 shrink-0">
                    <IconArrowsExchange :size="22" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium truncate">Transferencia</p>
                    <p class="text-xs text-surface-500 truncate">{{ transaction.from_account }} → {{ transaction.to_account }}</p>
                </div>
                <span class="text-sm font-semibold tabular-nums text-surface-500">{{ money(transaction.amount, transaction.currency_code) }}</span>
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
                    <p class="text-sm font-medium truncate">{{ transaction.category?.name ?? 'Sin categoría' }}</p>
                    <p v-if="transaction.note" class="text-xs text-surface-500 truncate">{{ transaction.note }}</p>
                </div>
                <span class="text-sm font-semibold tabular-nums" :class="transaction.type === 'income' ? 'text-emerald-500' : 'text-rose-500'">
                    {{ transaction.type === 'income' ? '+' : '-' }}{{ money(transaction.amount, transaction.currency_code) }}
                </span>
            </template>
        </div>
    </div>
</template>
