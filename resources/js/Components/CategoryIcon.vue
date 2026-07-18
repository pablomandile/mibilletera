<script setup>
import { computed } from 'vue';
import { resolveIcon } from '@/tablerIcons';

const props = defineProps({
    // Acepta un objeto categoría o props sueltas
    iconType: { type: String, default: 'preset' },
    iconValue: { type: String, default: 'category' },
    iconUrl: { type: String, default: null },
    color: { type: String, default: '#f59e0b' },
    size: { type: Number, default: 44 },
    iconSize: { type: Number, default: 24 },
});

const isImage = computed(() => props.iconType === 'image' && props.iconUrl);
const component = computed(() => resolveIcon(props.iconValue));
</script>

<template>
    <span
        class="grid place-items-center rounded-full overflow-hidden shrink-0"
        :style="{ width: size + 'px', height: size + 'px', backgroundColor: isImage ? undefined : color }"
    >
        <img v-if="isImage" :src="iconUrl" alt="" class="h-full w-full object-cover" />
        <component v-else :is="component" :size="iconSize" :stroke="1.9" color="#ffffff" />
    </span>
</template>
