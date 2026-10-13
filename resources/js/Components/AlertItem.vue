<script setup>
import { ref } from 'vue';
import { IconTrash, IconBell } from '@tabler/icons-vue';
import { useSwipeRow } from '@/composables/useSwipeRow';

const props = defineProps({
    alert: { type: Object, required: true },
});
const emit = defineEmits(['delete']);

const removing = ref(false);

const { dx, swiping, onDown, onMove, onUp, onCancel } = useSwipeRow({
    onRelease: (offset) => {
        if (offset > -80) {
            dx.value = 0;
            return;
        }
        removing.value = true;
        dx.value = -400;
        setTimeout(() => emit('delete', props.alert.id), 150);
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
            class="relative flex items-start gap-3 bg-white dark:bg-surface-900 px-4 py-3 select-none touch-pan-y"
            :class="swiping && !removing ? '' : 'transition-transform duration-150'"
            :style="{ transform: `translateX(${dx}px)` }"
            @pointerdown="onDown"
            @pointermove="onMove"
            @pointerup="onUp"
            @pointercancel="onCancel"
        >
            <span
                class="grid place-items-center h-9 w-9 rounded-full shrink-0"
                :class="alert.read ? 'bg-surface-100 dark:bg-surface-800 text-surface-400' : 'bg-primary/10 text-primary'"
            >
                <IconBell :size="18" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium break-words hyphens-auto" :class="alert.read ? 'text-surface-500' : ''">{{ alert.title }}</p>
                <p v-if="alert.body" class="text-xs text-surface-500 break-words hyphens-auto">{{ alert.body }}</p>
                <p class="text-2xs text-surface-400 mt-0.5">{{ alert.time_ago }}</p>
            </div>
            <span v-if="!alert.read" class="mt-1.5 h-2 w-2 rounded-full bg-primary shrink-0"></span>
        </div>
    </div>
</template>
