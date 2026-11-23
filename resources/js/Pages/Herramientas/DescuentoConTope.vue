<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { descuentoConTope, reintegroDe } from '@/calc/descuentos';
import { formatMoney, formatPercent } from '@/money';
import { IconChevronDown } from '@tabler/icons-vue';

// Portada de CalculAR. Se calcula en vivo: sin botón, mientras escribís.
const porcentaje = ref('');
const tope = ref('');
const monto = ref('');

const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v));

const calculo = computed(() => {
    const p = num(porcentaje.value);
    const t = num(tope.value);
    if (p === null || t === null) return { resultado: null, error: null };

    try {
        const r = descuentoConTope(p / 100, t, num(monto.value));
        return { resultado: { ...r, porcentaje: p / 100, tope: t }, error: null };
    } catch (e) {
        return { resultado: null, error: e.message };
    }
});
const resultado = computed(() => calculo.value.resultado);

/** Qué descuento real queda según cuánto se gaste. */
const escenarios = computed(() => {
    const r = resultado.value;
    if (!r) return [];

    return [0.5, 1, 1.5, 2].map((veces) => {
        const m = r.compraMaxima * veces;
        const reintegro = reintegroDe(m, r.porcentaje, r.tope);
        return { veces, monto: m, reintegro, efectivo: reintegro / m };
    });
});

const inputClass =
    'mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 focus:border-primary focus:ring-primary';
const card = 'rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800';
</script>

<template>
    <Head title="Descuento con tope" />

    <AppLayout title="Descuento con tope">
        <div class="mx-auto max-w-4xl space-y-6">
            <p class="text-sm text-surface-500">
                Cuánto gastar para aprovechar entero un descuento o reintegro con tope, y cuánto pagás de verdad.
            </p>

            <div class="grid items-start gap-6 lg:grid-cols-2">
                <form :class="card" class="p-4 space-y-4" @submit.prevent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium">Descuento o reintegro (%)</span>
                            <input v-model="porcentaje" type="number" step="any" min="0" max="100" inputmode="decimal" placeholder="Por ejemplo: 20" :class="inputClass" />
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium">Tope ($)</span>
                            <input v-model="tope" type="number" step="0.01" min="0" inputmode="decimal" placeholder="Por ejemplo: 6000" :class="inputClass" />
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-sm font-medium">Lo que pensás gastar ($, opcional)</span>
                        <input v-model="monto" type="number" step="0.01" min="0" inputmode="decimal" placeholder="Por ejemplo: 45000" :class="inputClass" />
                        <span class="mt-1 block text-xs text-surface-500">Para ver cuánto te devuelven y cuánto pagás de verdad.</span>
                    </label>
                </form>

                <section :class="card" class="p-4" aria-live="polite">
                    <p class="text-sm text-surface-500">Para aprovechar el tope entero, gastá hasta</p>
                    <p v-if="resultado" class="mt-1 text-3xl font-semibold tabular-nums break-words">{{ formatMoney(resultado.compraMaxima) }}</p>
                    <p v-else-if="calculo.error" class="mt-1 text-sm text-rose-500">{{ calculo.error }}</p>
                    <p v-else class="mt-1 text-sm text-surface-400">Completá el porcentaje y el tope.</p>

                    <template v-if="resultado">
                        <dl class="mt-4 divide-y divide-surface-200 dark:divide-surface-800 text-sm">
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-surface-500">Te devuelven, como máximo</dt>
                                <dd class="font-medium tabular-nums text-right">{{ formatMoney(resultado.tope) }}</dd>
                            </div>
                            <template v-if="resultado.compra">
                                <div class="flex justify-between gap-3 py-2">
                                    <dt class="text-surface-500">Con una compra de {{ formatMoney(resultado.compra.monto) }}, te devuelven</dt>
                                    <dd class="font-medium tabular-nums text-right">{{ formatMoney(resultado.compra.reintegro) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 py-2">
                                    <dt class="text-surface-500">Pagás de verdad</dt>
                                    <dd class="font-medium tabular-nums text-right">{{ formatMoney(resultado.compra.pagas) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 py-2">
                                    <dt class="text-surface-500">Descuento real</dt>
                                    <dd class="font-medium tabular-nums text-right">{{ formatPercent(resultado.compra.descuentoEfectivo) }}</dd>
                                </div>
                            </template>
                        </dl>

                        <p v-if="resultado.compra && resultado.compra.sinDescuento > 0" class="mt-3 rounded-lg bg-primary/10 px-3 py-2 text-sm">
                            Te pasás del tope por {{ formatMoney(resultado.compra.sinDescuento) }}: esa parte la pagás sin descuento, y el
                            descuento real baja al {{ formatPercent(resultado.compra.descuentoEfectivo) }}.
                        </p>
                        <p v-else-if="resultado.compra && !resultado.compra.llegaAlTope" class="mt-3 rounded-lg bg-primary/10 px-3 py-2 text-sm">
                            Te quedan {{ formatMoney(resultado.compraMaxima - resultado.compra.monto) }} de compra con descuento antes de
                            llegar al tope.
                        </p>

                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-sm tabular-nums [&_td]:whitespace-nowrap">
                                <caption class="mb-1 text-left text-sm font-medium">Según cuánto gastes</caption>
                                <thead>
                                    <tr class="text-left text-surface-500">
                                        <th class="px-2 py-1.5 font-medium">Gastás</th>
                                        <th class="px-2 py-1.5 text-right font-medium">Te devuelven</th>
                                        <th class="px-2 py-1.5 text-right font-medium">Descuento real</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="e in escenarios"
                                        :key="e.veces"
                                        class="border-t border-surface-200 dark:border-surface-800"
                                        :class="e.veces === 1 && 'font-semibold'"
                                    >
                                        <td class="px-2 py-1.5">{{ formatMoney(e.monto) }}</td>
                                        <td class="px-2 py-1.5 text-right">{{ formatMoney(e.reintegro) }}</td>
                                        <td class="px-2 py-1.5 text-right">{{ formatPercent(e.efectivo, 1) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </section>
            </div>

            <!-- Explicación -->
            <div :class="card" class="divide-y divide-surface-200 dark:divide-surface-800 overflow-hidden text-sm">
                <details class="group px-4 py-3" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between font-medium [&::-webkit-details-marker]:hidden">
                        Qué calcula <IconChevronDown :size="18" class="text-surface-400 transition group-open:rotate-180" />
                    </summary>
                    <p class="mt-2 text-surface-600 dark:text-surface-300">
                        Cuánto podés gastar como máximo para aprovechar entero un descuento o reintegro con tope, como los de bancos,
                        billeteras virtuales o Cuenta DNI. Si cargás lo que pensás gastar, también te dice cuánto te devuelven,
                        cuánto pagás de verdad y qué descuento real te queda.
                    </p>
                </details>
                <details class="group px-4 py-3">
                    <summary class="flex cursor-pointer list-none items-center justify-between font-medium [&::-webkit-details-marker]:hidden">
                        Fórmula <IconChevronDown :size="18" class="text-surface-400 transition group-open:rotate-180" />
                    </summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-surface-600 dark:text-surface-300">
                        <li>Compra máxima = tope / porcentaje</li>
                        <li>Reintegro = el menor entre compra × porcentaje y el tope</li>
                        <li>Descuento real = reintegro / compra</li>
                    </ul>
                </details>
                <details class="group px-4 py-3">
                    <summary class="flex cursor-pointer list-none items-center justify-between font-medium [&::-webkit-details-marker]:hidden">
                        Ejemplo <IconChevronDown :size="18" class="text-surface-400 transition group-open:rotate-180" />
                    </summary>
                    <p class="mt-2 text-surface-600 dark:text-surface-300">
                        20 % de reintegro con tope de {{ formatMoney(6000) }}: 6.000 / 0,20 = {{ formatMoney(30000) }}. Hasta ahí
                        tenés el 20 % entero. Si gastás {{ formatMoney(60000) }}, te devuelven igual {{ formatMoney(6000) }}: el
                        descuento real es del 10 %.
                    </p>
                </details>
                <details class="group px-4 py-3">
                    <summary class="flex cursor-pointer list-none items-center justify-between font-medium [&::-webkit-details-marker]:hidden">
                        Para tener en cuenta <IconChevronDown :size="18" class="text-surface-400 transition group-open:rotate-180" />
                    </summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-surface-600 dark:text-surface-300">
                        <li>
                            Fijate si el tope es por compra, por día, por semana o por mes: si es por mes y ya usaste una parte,
                            cargá como tope lo que te queda.
                        </li>
                        <li>
                            Si el tope es por compra y te pasás, a veces conviene dividirla en dos (si el comercio y la promoción lo
                            permiten).
                        </li>
                        <li>El reintegro suele llegar días o semanas después: mientras tanto, pagás el total.</li>
                    </ul>
                </details>
            </div>
        </div>
    </AppLayout>
</template>
