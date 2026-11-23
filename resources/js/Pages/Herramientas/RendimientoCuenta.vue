<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { contraInflacion, rendimientoCuenta } from '@/calc/rendimiento';
import { formatMoney, formatPercent } from '@/money';
import { IconChevronDown, IconTrendingDown, IconTrendingUp } from '@tabler/icons-vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] }, // [{ id, name, balance }] en ARS
    inflacion: { type: Object, default: null }, // { mes: 'AAAA-MM', valor } (fracción) o null
    calcularUrl: { type: String, default: '' },
});

// Portada de CalculAR. Se calcula en vivo: sin botón, mientras escribís.
const saldo = ref('');
const tasa = ref('');
const tipo = ref('tna');
const tope = ref('');

// Elegir una cuenta completa el saldo con el suyo (si tiene plata). Igual se puede editar.
const cuenta = ref('');
const elegirCuenta = () => {
    const a = props.accounts.find((x) => String(x.id) === String(cuenta.value));
    if (a && a.balance > 0) saldo.value = Math.round(a.balance * 100) / 100;
};

const TIPOS = [
    { value: 'tna', label: 'TNA' },
    { value: 'tea', label: 'TEA' },
];

const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v));

const calculo = computed(() => {
    const s = num(saldo.value);
    const t = num(tasa.value);
    if (s === null || t === null) return { resultado: null, error: null };

    try {
        return { resultado: { ...rendimientoCuenta(s, t / 100, tipo.value, num(tope.value)), saldo: s }, error: null };
    } catch (e) {
        return { resultado: null, error: e.message };
    }
});
const resultado = computed(() => calculo.value.resultado);

const comparacion = computed(() =>
    resultado.value && props.inflacion ? contraInflacion(resultado.value.saldo, resultado.value.mensual, props.inflacion.valor) : null
);

// "2026-10" → "octubre de 2026"
const mesInflacion = computed(() => {
    if (!props.inflacion) return '';
    const [y, m] = props.inflacion.mes.split('-').map(Number);
    return new Date(y, m - 1, 15).toLocaleDateString('es-AR', { month: 'long', year: 'numeric' });
});

const inputClass =
    'mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 focus:border-primary focus:ring-primary';
const card = 'rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800';
</script>

<template>
    <Head title="Rendimiento de tu cuenta" />

    <AppLayout title="Rendimiento de tu cuenta">
        <div class="mx-auto max-w-4xl space-y-6">
            <p class="text-sm text-surface-500">Cuánto te rinde por día la plata en Mercado Pago o tu banco.</p>

            <div class="grid items-start gap-6 lg:grid-cols-2">
                <form :class="card" class="p-4 space-y-4" @submit.prevent>
                    <label v-if="accounts.length" class="block">
                        <span class="text-sm font-medium">Usar el saldo de una cuenta (opcional)</span>
                        <select v-model="cuenta" :class="inputClass" @change="elegirCuenta">
                            <option value="">Elegí una cuenta</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }} · {{ formatMoney(a.balance) }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Saldo en la cuenta ($)</span>
                        <input v-model="saldo" type="number" step="0.01" min="0" inputmode="decimal" placeholder="Por ejemplo: 1000000" :class="inputClass" />
                    </label>
                    <div class="space-y-2">
                        <label class="block">
                            <span class="text-sm font-medium">Rendimiento que informa la cuenta (%)</span>
                            <input v-model="tasa" type="number" step="any" inputmode="decimal" placeholder="Por ejemplo: 18,8" :class="inputClass" />
                        </label>
                        <p id="tipo-tasa" class="text-sm font-medium">Esa tasa es</p>
                        <div class="grid grid-cols-2 gap-1 rounded-xl bg-surface-100 dark:bg-surface-800 p-1" role="radiogroup" aria-labelledby="tipo-tasa">
                            <button
                                v-for="opt in TIPOS"
                                :key="opt.value"
                                type="button"
                                role="radio"
                                :aria-checked="tipo === opt.value"
                                class="rounded-lg py-2 text-sm font-medium transition"
                                :class="tipo === opt.value ? 'bg-white dark:bg-surface-900 text-primary shadow-sm' : 'text-surface-500'"
                                @click="tipo = opt.value"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                        <p class="text-xs text-surface-500">
                            Las billeteras y los bancos suelen informar la TNA. Si no dice, buscá la sigla al lado del porcentaje.
                        </p>
                    </div>
                    <label class="block">
                        <span class="text-sm font-medium">Tope de saldo que rinde ($, opcional)</span>
                        <input v-model="tope" type="number" step="0.01" min="0" inputmode="decimal" placeholder="Sin tope" :class="inputClass" />
                        <span class="mt-1 block text-xs text-surface-500">Si la cuenta paga solo hasta cierto monto.</span>
                    </label>
                </form>

                <section :class="card" class="p-4" aria-live="polite">
                    <p class="text-sm text-surface-500">Te rinde por día</p>
                    <p v-if="resultado" class="mt-1 text-3xl font-semibold tabular-nums break-words">{{ formatMoney(resultado.porDia) }}</p>
                    <p v-else-if="calculo.error" class="mt-1 text-sm text-rose-500">{{ calculo.error }}</p>
                    <p v-else class="mt-1 text-sm text-surface-400">Completá el saldo y la tasa.</p>

                    <template v-if="resultado">
                        <dl class="mt-4 divide-y divide-surface-200 dark:divide-surface-800 text-sm">
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-surface-500">Por semana</dt>
                                <dd class="font-medium tabular-nums text-right">{{ formatMoney(resultado.porSemana) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-surface-500">Por mes (30 días)</dt>
                                <dd class="font-medium tabular-nums text-right">{{ formatMoney(resultado.porMes) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-surface-500">Por año, sin tocarla</dt>
                                <dd class="font-medium tabular-nums text-right">{{ formatMoney(resultado.porAnio) }}</dd>
                            </div>
                            <div v-if="tipo === 'tna'" class="flex justify-between gap-3 py-2">
                                <dt class="text-surface-500">
                                    TEA
                                    <span class="block text-xs text-surface-400">Lo que rinde de verdad en un año, con la reinversión diaria</span>
                                </dt>
                                <dd class="font-medium tabular-nums text-right">{{ formatPercent(resultado.tea) }}</dd>
                            </div>
                            <div v-else class="flex justify-between gap-3 py-2">
                                <dt class="text-surface-500">TNA equivalente</dt>
                                <dd class="font-medium tabular-nums text-right">{{ formatPercent(resultado.tna) }}</dd>
                            </div>
                            <div v-if="resultado.noRinde > 0" class="flex justify-between gap-3 py-2">
                                <dt class="text-surface-500">Saldo que no rinde (pasa el tope)</dt>
                                <dd class="font-medium tabular-nums text-right">{{ formatMoney(resultado.noRinde) }}</dd>
                            </div>
                        </dl>

                        <div v-if="comparacion" class="mt-4 rounded-xl border border-surface-200 dark:border-surface-800 p-4 text-sm">
                            <p class="flex items-center gap-2 font-medium">
                                <IconTrendingUp v-if="comparacion.leGana" :size="18" class="shrink-0 text-emerald-500" aria-hidden="true" />
                                <IconTrendingDown v-else :size="18" class="shrink-0 text-rose-500" aria-hidden="true" />
                                {{ comparacion.leGana ? 'Le gana a la inflación' : 'Pierde contra la inflación' }}
                            </p>
                            <p class="mt-1 text-surface-500">
                                En 30 días rinde {{ formatPercent(resultado.mensual) }}; la inflación de {{ mesInflacion }} fue de
                                {{ formatPercent(inflacion.valor, 1) }} (INDEC). Con ese ritmo, en un mes
                                {{ comparacion.leGana ? 'ganás' : 'perdés' }}
                                <span class="font-medium tabular-nums text-surface-900 dark:text-surface-100">{{ formatMoney(Math.abs(comparacion.enPesos)) }}</span>
                                de poder de compra.
                            </p>
                            <a
                                v-if="calcularUrl"
                                :href="`${calcularUrl}/inflacion/serie-mensual`"
                                target="_blank"
                                rel="noopener"
                                class="mt-2 inline-block text-primary underline underline-offset-2"
                            >Ver la inflación mes a mes en CalculAR</a>
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
                        Cuánto te rinde la plata que tenés en una cuenta remunerada o una billetera virtual (Mercado Pago, Ualá,
                        Naranja X, cuentas de bancos): por día, por semana, por mes y por año. Los intereses se acreditan todos los
                        días y al día siguiente rinden también.
                    </p>
                </details>
                <details class="group px-4 py-3">
                    <summary class="flex cursor-pointer list-none items-center justify-between font-medium [&::-webkit-details-marker]:hidden">
                        Fórmulas <IconChevronDown :size="18" class="text-surface-400 transition group-open:rotate-180" />
                    </summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-surface-600 dark:text-surface-300">
                        <li>Tasa diaria = TNA / 365 (o (1 + TEA)^(1/365) − 1)</li>
                        <li>Por día = saldo × tasa diaria</li>
                        <li>En n días = saldo × ((1 + tasa diaria)ⁿ − 1)</li>
                        <li>TEA = (1 + TNA / 365)³⁶⁵ − 1</li>
                    </ul>
                </details>
                <details class="group px-4 py-3">
                    <summary class="flex cursor-pointer list-none items-center justify-between font-medium [&::-webkit-details-marker]:hidden">
                        Ejemplo <IconChevronDown :size="18" class="text-surface-400 transition group-open:rotate-180" />
                    </summary>
                    <p class="mt-2 text-surface-600 dark:text-surface-300">
                        {{ formatMoney(1000000) }} al 18,8 % TNA: 1.000.000 × 0,188 / 365 = {{ formatMoney(515.07) }} por día. En 30
                        días, con la reinversión, unos {{ formatMoney(15568) }}. La TEA es 20,68 %.
                    </p>
                    <p class="mt-2 text-surface-600 dark:text-surface-300">
                        Si ese 18,8 % fuera TEA, serían {{ formatMoney(472.09) }} por día: por eso importa saber cuál es.
                    </p>
                </details>
                <details class="group px-4 py-3">
                    <summary class="flex cursor-pointer list-none items-center justify-between font-medium [&::-webkit-details-marker]:hidden">
                        Para tener en cuenta <IconChevronDown :size="18" class="text-surface-400 transition group-open:rotate-180" />
                    </summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-surface-600 dark:text-surface-300">
                        <li>Las cuentas cambian la tasa seguido: el cálculo vale para la tasa de hoy.</li>
                        <li>
                            Supone que no sacás ni ponés plata y que la acreditación es diaria, todos los días (también fines de
                            semana).
                        </li>
                        <li>
                            La comparación con la inflación usa el último mes que publicó el INDEC: la de los próximos meses puede ser
                            otra.
                        </li>
                    </ul>
                </details>
            </div>
        </div>
    </AppLayout>
</template>
