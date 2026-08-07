<script setup>
import { IconMoon, IconSun } from '@tabler/icons-vue';
import { useTheme } from '@/composables/useTheme';

const { isDark, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        class="relative grid place-items-center h-9 w-9 rounded-lg text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800"
        :aria-label="isDark ? 'Activar modo claro' : 'Activar modo oscuro'"
        :title="isDark ? 'Modo claro' : 'Modo oscuro'"
        @click="toggleTheme"
    >
        <IconMoon v-if="isDark" :size="22" :stroke="1.8" />
        <IconSun v-else :size="22" :stroke="1.8" />
    </button>
</template>
