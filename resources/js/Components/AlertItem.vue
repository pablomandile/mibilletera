<script setup>
import { ref } from 'vue';
import { IconTrash, IconBell } from '@tabler/icons-vue';

const props = defineProps({
    alert: { type: Object, required: true },
});
const emit = defineEmits(['delete']);

const dx = ref(0);
const dragging = ref(false);
const removing = ref(false);
let startX = 0;

const onDown = (e) => {
    dragging.value = true;
    startX = e.clientX;
    e.target.setPointerCapture?.(e.pointerId);
};
const onMove = (e) => {
    if (!dragging.value) return;
    const d = e.clientX - startX;
    dx.value = Math.max(-160, Math.min(0, d)); // solo hacia la izquierda
};
const onUp = () => {
    if (!dragging.value) return;
    dragging.value = false;
    if (dx.value < -80) {
        removing.value = true;
        dx.value = -400;
        setTimeout(() => emit('delete', props.alert.id), 150);
    } else {
        dx.value = 0;
    }
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
            class="relative flex items-start gap-3 bg-white dark:bg-surface-900 px-4 py-3 select-none touch-pan-y"
            :class="[dragging ? '' : 'transition-transform duration-150', removing ? 'transition-transform duration-150' : '']"
            :style="{ transform: `translateX(${dx}px)` }"
            @pointerdown="onDown"
            @pointermove="onMove"
            @pointerup="onUp"
            @pointercancel="onUp"
        >
            <span
                class="grid place-items-center h-9 w-9 rounded-full shrink-0"
                :class="alert.read ? 'bg-surface-100 dark:bg-surface-800 text-surface-400' : 'bg-primary/10 text-primary'"
            >
                <IconBell :size="18" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium truncate" :class="alert.read ? 'text-surface-500' : ''">{{ alert.title }}</p>
                <p v-if="alert.body" class="text-xs text-surface-500 truncate">{{ alert.body }}</p>
                <p class="text-[11px] text-surface-400 mt-0.5">{{ alert.time_ago }}</p>
            </div>
            <span v-if="!alert.read" class="mt-1.5 h-2 w-2 rounded-full bg-primary shrink-0"></span>
        </div>
    </div>
</template>
