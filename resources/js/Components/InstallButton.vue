<script setup>
import { ref } from 'vue';
import { IconDownload, IconX, IconShare, IconSquarePlus, IconDotsVertical } from '@tabler/icons-vue';
import { usePwaInstall } from '@/composables/usePwaInstall';

const { canInstall, isIos, install } = usePwaInstall();

const showHelp = ref(false);
const busy = ref(false);

async function onClick() {
    if (busy.value) return;
    busy.value = true;
    try {
        // 'manual' = el navegador no ofrece el diálogo nativo (iOS, o el prompt
        // ya se usó y se descartó): explicamos los pasos a mano.
        if ((await install()) === 'manual') showHelp.value = true;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <button
        v-if="canInstall"
        type="button"
        class="shrink-0 grid place-items-center h-9 w-9 rounded-lg text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 disabled:opacity-50"
        title="Instalar app"
        aria-label="Instalar app"
        :disabled="busy"
        @click="onClick"
    >
        <IconDownload :size="22" :stroke="1.8" />
    </button>

    <!-- Instrucciones para instalar a mano -->
    <Teleport to="body">
        <div
            v-if="showHelp"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 p-0 sm:p-4"
            @click.self="showHelp = false"
        >
            <div class="w-full sm:max-w-sm bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800 max-h-[90vh] overflow-y-auto overflow-x-hidden">
                <div class="flex items-center justify-between px-5 h-14 border-b border-surface-200 dark:border-surface-800">
                    <h2 class="font-semibold">Instalar Mi Billetera</h2>
                    <button type="button" class="text-surface-500" aria-label="Cerrar" @click="showHelp = false">
                        <IconX :size="22" />
                    </button>
                </div>

                <div class="p-5 space-y-4 text-sm">
                    <p class="text-surface-500">
                        Agregá la app a tu pantalla de inicio para abrirla como una aplicación,
                        sin la barra del navegador.
                    </p>

                    <!-- iOS: Safari no ofrece diálogo de instalación -->
                    <ol v-if="isIos" class="space-y-3">
                        <li class="flex items-center gap-3">
                            <span class="grid place-items-center h-8 w-8 rounded-lg bg-surface-100 dark:bg-surface-800 text-primary shrink-0">
                                <IconShare :size="18" />
                            </span>
                            <span>Tocá <strong>Compartir</strong> en la barra de Safari.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="grid place-items-center h-8 w-8 rounded-lg bg-surface-100 dark:bg-surface-800 text-primary shrink-0">
                                <IconSquarePlus :size="18" />
                            </span>
                            <span>Elegí <strong>Agregar a inicio</strong>.</span>
                        </li>
                    </ol>

                    <!-- Resto de navegadores: el prompt nativo ya se usó o se descartó -->
                    <ol v-else class="space-y-3">
                        <li class="flex items-center gap-3">
                            <span class="grid place-items-center h-8 w-8 rounded-lg bg-surface-100 dark:bg-surface-800 text-primary shrink-0">
                                <IconDotsVertical :size="18" />
                            </span>
                            <span>Abrí el menú del navegador.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="grid place-items-center h-8 w-8 rounded-lg bg-surface-100 dark:bg-surface-800 text-primary shrink-0">
                                <IconDownload :size="18" />
                            </span>
                            <span>Elegí <strong>Instalar aplicación</strong> o <strong>Agregar a pantalla de inicio</strong>.</span>
                        </li>
                    </ol>

                    <button
                        type="button"
                        class="w-full rounded-lg bg-primary py-2.5 font-semibold text-primary-contrast hover:brightness-95"
                        @click="showHelp = false"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
