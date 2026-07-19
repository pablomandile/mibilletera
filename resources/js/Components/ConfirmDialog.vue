<script setup>
import { useConfirmDialog } from '@/composables/useConfirm';
import { IconAlertTriangle } from '@tabler/icons-vue';

const { state, respond } = useConfirmDialog();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="state.open"
                class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-black/60 sm:p-4"
                @click.self="respond(false)"
            >
                <div class="w-full sm:max-w-sm bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800 p-5 shadow-xl">
                    <div class="flex items-start gap-3">
                        <span
                            v-if="state.danger"
                            class="grid place-items-center h-10 w-10 rounded-full bg-rose-500/10 text-rose-500 shrink-0"
                        >
                            <IconAlertTriangle :size="22" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-semibold leading-snug">{{ state.title }}</h2>
                            <p v-if="state.message" class="mt-1 text-sm text-surface-500">{{ state.message }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex gap-2 justify-end">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2.5 text-sm font-medium text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800"
                            @click="respond(false)"
                        >
                            {{ state.cancelLabel }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-5 py-2.5 text-sm font-semibold hover:brightness-95"
                            :class="state.danger ? 'bg-rose-500 text-white' : 'bg-primary text-primary-contrast'"
                            @click="respond(true)"
                        >
                            {{ state.confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
