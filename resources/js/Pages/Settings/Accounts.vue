<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import AccountFormDialog from '@/Components/AccountFormDialog.vue';
import { Head } from '@inertiajs/vue3';
import { IconPlus } from '@tabler/icons-vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
});

const showDialog = ref(false);
const editing = ref(null);

const TYPE_LABELS = { cash: 'Efectivo', bank: 'Banco', card: 'Tarjeta', other: 'Otro' };
const symbolFor = (code) => props.currencies.find((c) => c.code === code)?.symbol ?? '$';
const money = (v, code) => symbolFor(code) + ' ' + Number(v).toLocaleString('es-AR', { maximumFractionDigits: 2 });

const active = computed(() => props.accounts.filter((a) => !a.is_archived));
const archived = computed(() => props.accounts.filter((a) => a.is_archived));

const openNew = () => { editing.value = null; showDialog.value = true; };
const openEdit = (acc) => { editing.value = acc; showDialog.value = true; };
</script>

<template>
    <Head title="Cuentas" />

    <AppLayout title="Cuentas">
        <div class="mx-auto max-w-2xl space-y-4">
            <div class="space-y-2">
                <button
                    v-for="acc in active"
                    :key="acc.id"
                    type="button"
                    class="w-full flex items-center gap-3 rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors text-left"
                    @click="openEdit(acc)"
                >
                    <CategoryIcon :icon-value="acc.icon" :color="acc.color" :size="44" :icon-size="24" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium truncate">{{ acc.name }}</p>
                        <p class="text-xs text-surface-500">{{ TYPE_LABELS[acc.type] }} · {{ acc.currency_code }}</p>
                    </div>
                    <span class="font-semibold tabular-nums" :class="acc.balance >= 0 ? 'text-surface-900 dark:text-surface-100' : 'text-rose-500'">
                        {{ money(acc.balance, acc.currency_code) }}
                    </span>
                </button>
            </div>

            <button
                type="button"
                class="w-full flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-surface-300 dark:border-surface-700 py-4 text-surface-500 hover:border-primary hover:text-primary transition"
                @click="openNew"
            >
                <IconPlus :size="20" /> Agregar cuenta
            </button>

            <div v-if="archived.length" class="space-y-2">
                <p class="px-1 text-xs font-medium uppercase tracking-wide text-surface-500">Archivadas</p>
                <button
                    v-for="acc in archived"
                    :key="acc.id"
                    type="button"
                    class="w-full flex items-center gap-3 rounded-2xl bg-white/60 dark:bg-surface-900/60 border border-surface-200 dark:border-surface-800 p-4 opacity-70 text-left"
                    @click="openEdit(acc)"
                >
                    <CategoryIcon :icon-value="acc.icon" :color="acc.color" :size="40" :icon-size="22" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium truncate">{{ acc.name }}</p>
                        <p class="text-xs text-surface-500">{{ TYPE_LABELS[acc.type] }} · {{ acc.currency_code }}</p>
                    </div>
                    <span class="text-sm tabular-nums text-surface-500">{{ money(acc.balance, acc.currency_code) }}</span>
                </button>
            </div>
        </div>

        <AccountFormDialog v-model="showDialog" :account="editing" :currencies="currencies" />
    </AppLayout>
</template>
