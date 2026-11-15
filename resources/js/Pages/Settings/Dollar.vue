<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { formatMoney } from '@/money';
import { IconCreditCard, IconCashBanknote, IconPlugConnected, IconAlertTriangle } from '@tabler/icons-vue';

const props = defineProps({
    types: { type: Array, default: () => [] }, // [{ tipo, nombre, venta }]
    live: { type: Boolean, default: false }, // la lista vino de la API (si no, la conocida)
    configured: { type: Boolean, default: false }, // hay clave cargada
    expense: { type: String, default: 'tarjeta' },
    income: { type: String, default: 'blue' },
    isAdmin: { type: Boolean, default: false },
    api: { type: Object, default: null }, // solo admin
});

// ---- El dólar de cada uno ----
const prefs = useForm({ dollar_type_expense: props.expense, dollar_type_income: props.income });
const savePrefs = () => prefs.patch(route('settings.dollar.update'), { preserveScroll: true });
const prefsDirty = computed(() => prefs.dollar_type_expense !== props.expense || prefs.dollar_type_income !== props.income);

// Si el elegido ya no viene en la API, igual se muestra (para no perderlo).
const options = computed(() => {
    const list = [...props.types];
    for (const t of [props.expense, props.income]) {
        if (!list.some((o) => o.tipo === t)) list.push({ tipo: t, nombre: t, venta: null });
    }
    return list;
});
const label = (o) => (o.venta ? `${o.nombre} · ${formatMoney(o.venta)}` : o.nombre);

// ---- La API (solo admin) ----
const apiForm = useForm({ url: props.api?.url ?? '', key: '', clear_key: false });
const saveApi = () =>
    apiForm.put(route('settings.dollar.api'), {
        preserveScroll: true,
        onSuccess: () => apiForm.reset('key', 'clear_key'),
    });
const removeKey = () => {
    apiForm.key = '';
    apiForm.clear_key = true;
    saveApi();
};
</script>

<template>
    <Head title="Cotización del dólar" />

    <AppLayout title="Cotización del dólar">
        <div class="mx-auto max-w-2xl space-y-6">
            <p class="text-sm text-surface-500">
                Cuando cargás un gasto o un ingreso en dólares, la cotización del día se completa sola con el dólar que elijas acá.
                Siempre la podés corregir a mano.
            </p>

            <!-- El dólar por defecto de cada uno -->
            <form class="space-y-2" @submit.prevent="savePrefs">
                <p class="px-1 text-xs font-medium uppercase tracking-wide text-surface-500">Tu dólar por defecto</p>
                <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4 space-y-4">
                    <label class="block">
                        <span class="flex items-center gap-2 text-sm font-medium"><IconCreditCard :size="18" class="text-rose-500" />Para los gastos</span>
                        <select v-model="prefs.dollar_type_expense" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800">
                            <option v-for="o in options" :key="o.tipo" :value="o.tipo">{{ label(o) }}</option>
                        </select>
                        <span v-if="prefs.errors.dollar_type_expense" class="text-xs text-rose-500">{{ prefs.errors.dollar_type_expense }}</span>
                    </label>
                    <label class="block">
                        <span class="flex items-center gap-2 text-sm font-medium"><IconCashBanknote :size="18" class="text-emerald-500" />Para los ingresos</span>
                        <select v-model="prefs.dollar_type_income" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800">
                            <option v-for="o in options" :key="o.tipo" :value="o.tipo">{{ label(o) }}</option>
                        </select>
                        <span v-if="prefs.errors.dollar_type_income" class="text-xs text-rose-500">{{ prefs.errors.dollar_type_income }}</span>
                    </label>
                    <p v-if="!live" class="flex items-start gap-2 text-xs text-amber-600 dark:text-amber-400">
                        <IconAlertTriangle :size="16" class="shrink-0 mt-px" />
                        <span v-if="configured">No se pudo consultar la API: la lista es la conocida y la cotización se va a cargar a mano hasta que vuelva.</span>
                        <span v-else>Todavía no hay una API de cotizaciones cargada: la cotización se carga a mano.{{ isAdmin ? ' Cargala abajo.' : '' }}</span>
                    </p>
                    <button
                        type="submit"
                        class="w-full rounded-xl bg-primary py-2.5 text-sm font-semibold text-primary-contrast disabled:opacity-50"
                        :disabled="!prefsDirty || prefs.processing"
                    >
                        Guardar
                    </button>
                </div>
            </form>

            <!-- La API (una para toda la app; solo admin) -->
            <form v-if="isAdmin && api" class="space-y-2" @submit.prevent="saveApi">
                <p class="px-1 text-xs font-medium uppercase tracking-wide text-surface-500">API de cotizaciones · administración</p>
                <div class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4 space-y-4">
                    <p class="flex items-start gap-2 text-xs text-surface-500">
                        <IconPlugConnected :size="16" class="shrink-0 mt-px" />
                        <span>Es una sola para toda la app: la usan todos los usuarios. Se prueba la conexión antes de guardar.</span>
                    </p>
                    <label class="block">
                        <span class="text-sm font-medium">Dirección</span>
                        <input
                            v-model="apiForm.url"
                            type="url"
                            inputmode="url"
                            :placeholder="api.defaultUrl"
                            class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800"
                        />
                        <span class="block text-xs text-surface-500 mt-1">Vacía: {{ api.defaultUrl }}</span>
                        <span v-if="apiForm.errors.url" class="block text-xs text-rose-500">{{ apiForm.errors.url }}</span>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Clave</span>
                        <input
                            v-model="apiForm.key"
                            type="password"
                            autocomplete="off"
                            :placeholder="api.hasKey ? `Cargada · termina en ${api.keyEnd}` : 'Pegá la clave de MiBilletera'"
                            class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800"
                        />
                        <span v-if="api.hasKey" class="block text-xs mt-1 text-surface-500">Para cambiarla, escribí la nueva; vacía, queda la que está.</span>
                        <span v-if="!api.hasKey" class="block text-xs mt-1" :class="api.envKey ? 'text-surface-500' : 'text-amber-600 dark:text-amber-400'">
                            {{ api.envKey ? 'Hoy se usa la clave del servidor (.env).' : 'Sin clave: la cotización se carga a mano.' }}
                        </span>
                        <span v-if="apiForm.errors.key" class="block text-xs text-rose-500">{{ apiForm.errors.key }}</span>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="submit"
                            class="flex-1 min-w-[10rem] rounded-xl bg-primary py-2.5 text-sm font-semibold text-primary-contrast disabled:opacity-50"
                            :disabled="apiForm.processing"
                        >
                            {{ apiForm.processing ? 'Probando…' : 'Probar y guardar' }}
                        </button>
                        <button
                            v-if="api.hasKey"
                            type="button"
                            class="rounded-xl border border-rose-500/30 px-4 py-2.5 text-sm font-medium text-rose-500 disabled:opacity-50"
                            :disabled="apiForm.processing"
                            @click="removeKey"
                        >
                            Quitar clave
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
