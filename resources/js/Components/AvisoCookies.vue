<script setup>
import { Link } from '@inertiajs/vue3';
import { IconCookie } from '@tabler/icons-vue';
import { useAvisoCookies } from '@/composables/useAvisoCookies';

/*
 * Aviso de cookies: informativo, con un solo botón.
 *
 * Todo lo que Mi Billetera guarda en el navegador es necesario para que
 * funcione —sesión, protección de formularios, preferencias, modo sin
 * conexión—, así que no hay nada que rechazar. Un «Rechazar» sería de adorno:
 * lo único que podría apagar es el login. El detalle está en /cookies.
 *
 * `apilado` va adentro del contenedor fijo de la barra inferior del celular,
 * arriba de ella, en vez de pelearle el bottom-0. `flotante` es para todo lo
 * demás: fijo abajo en el celular, y desde `md` una tarjeta en la esquina
 * inferior derecha (los toasts de la app salen arriba al centro).
 */
defineProps({
    variante: { type: String, default: 'flotante' },
});

const { visible, cerrar } = useAvisoCookies();
</script>

<template>
    <div
        v-if="visible"
        role="region"
        aria-label="Aviso de cookies"
        class="border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900 px-4 shadow-lg"
        :class="
            variante === 'apilado'
                ? 'border-t pb-6'
                : 'fixed inset-x-0 bottom-0 z-50 border-t pb-[env(safe-area-inset-bottom)] md:inset-x-auto md:bottom-4 md:right-4 md:w-96 md:rounded-xl md:border md:pb-0'
        "
    >
        <div class="flex flex-col gap-3 py-3">
            <div class="flex gap-3">
                <IconCookie :size="22" :stroke="1.8" class="mt-0.5 shrink-0 text-primary" aria-hidden="true" />
                <p class="text-sm break-words text-surface-700 dark:text-surface-200">
                    Mi Billetera usa cookies y guarda datos en tu dispositivo solo para
                    funcionar: mantener tu sesión, recordar tus preferencias y dejarte
                    usarla sin conexión. Nada de publicidad ni seguimiento.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2">
                <Link
                    href="/cookies"
                    class="inline-flex min-h-11 items-center px-2 text-sm text-surface-500 underline-offset-4 hover:text-primary hover:underline"
                >
                    Más información
                </Link>
                <button
                    type="button"
                    class="min-h-11 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-contrast hover:brightness-95"
                    @click="cerrar"
                >
                    Entendido
                </button>
            </div>
        </div>
    </div>
</template>
