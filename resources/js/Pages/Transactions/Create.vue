<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import {
    IconChevronLeft, IconPhoto, IconCheck, IconBackspace, IconPlus, IconCalendarEvent, IconArrowRight, IconTrash,
} from '@tabler/icons-vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import CategoryFormDialog from '@/Components/CategoryFormDialog.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { confirmAction } from '@/composables/useConfirm';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    defaultCurrency: { type: String, default: 'ARS' },
    transaction: { type: Object, default: null }, // si viene, es edición
});

const page = usePage();
const tx = props.transaction;
const isEdit = computed(() => !!props.transaction);

const type = ref(tx?.type ?? 'expense'); // expense | income | transfer
const selectedCategoryId = ref(tx?.category_id ?? null);
const selectedAccountId = ref(tx?.account_id ?? props.accounts[0]?.id ?? null);
const toAccountId = ref(tx?.to_account_id ?? props.accounts[1]?.id ?? props.accounts[0]?.id ?? null);
const expression = ref(tx ? String(tx.amount) : '');
const exchangeRate = ref(tx?.exchange_rate ?? 1);
const transferAmount = ref(tx?.transfer_amount ?? null); // monto recibido (transferencia entre monedas distintas)
const note = ref(tx?.note ?? '');
const photo = ref(null);
const photoPreview = ref(tx?.photo_url ?? null);
const today = new Date();
const transactionDate = ref(
    tx?.transaction_date ??
    `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
);
const showNewCategory = ref(false);

const symbolOf = (code) => props.currencies.find((c) => c.code === code)?.symbol ?? '$';
const baseSymbol = computed(() => symbolOf(props.defaultCurrency));

const filteredCategories = computed(() => props.categories.filter((c) => c.type === type.value));
const selectedAccount = computed(() => props.accounts.find((a) => a.id === selectedAccountId.value) ?? null);
const toAccount = computed(() => props.accounts.find((a) => a.id === toAccountId.value) ?? null);
const currencySymbol = computed(() => symbolOf(selectedAccount.value?.currency_code ?? props.defaultCurrency));

const needsRate = computed(() =>
    type.value !== 'transfer' &&
    selectedAccount.value &&
    selectedAccount.value.currency_code !== props.defaultCurrency
);
const crossCurrency = computed(() =>
    type.value === 'transfer' &&
    selectedAccount.value && toAccount.value &&
    selectedAccount.value.currency_code !== toAccount.value.currency_code
);

// ---- Calculadora (evaluación de izquierda a derecha) ----
function evaluate(expr) {
    if (!expr) return 0;
    const tokens = expr.replace(/×/g, '*').replace(/÷/g, '/').match(/(\d+\.?\d*|\.\d+|[+\-*/])/g);
    if (!tokens) return 0;
    let result = parseFloat(tokens[0]);
    if (isNaN(result)) result = 0;
    let i = 1;
    while (i < tokens.length - 1) {
        const op = tokens[i];
        const next = parseFloat(tokens[i + 1]);
        if (isNaN(next)) break;
        if (op === '+') result += next;
        else if (op === '-') result -= next;
        else if (op === '*') result *= next;
        else if (op === '/') result = next === 0 ? result : result / next;
        i += 2;
    }
    return Math.round(result * 100) / 100;
}

const amount = computed(() => evaluate(expression.value));
const hasOperator = computed(() => /[+\-×÷]/.test(expression.value.slice(1)));
const displayAmount = computed(() => amount.value.toLocaleString('en-US', { maximumFractionDigits: 2 }));
const baseConverted = computed(() => (amount.value * (Number(exchangeRate.value) || 0)).toLocaleString('es-AR', { maximumFractionDigits: 2 }));

const isOperator = (ch) => ['+', '-', '×', '÷'].includes(ch);

function press(key) {
    const expr = expression.value;
    const last = expr.slice(-1);
    if (isOperator(key)) {
        if (expr === '') return;
        expression.value = isOperator(last) ? expr.slice(0, -1) + key : expr + key;
        return;
    }
    if (key === '.') {
        const segment = expr.split(/[+\-×÷]/).pop();
        if (segment.includes('.')) return;
        expression.value = segment === '' ? expr + '0.' : expr + '.';
        return;
    }
    expression.value = expr + key;
}
const backspace = () => (expression.value = expression.value.slice(0, -1));

function onPhoto(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    photo.value = file;
    photoPreview.value = URL.createObjectURL(file);
}

const canSave = computed(() => {
    if (amount.value <= 0) return false;
    if (type.value === 'transfer') {
        return selectedAccountId.value && toAccountId.value && selectedAccountId.value !== toAccountId.value
            && (!crossCurrency.value || Number(transferAmount.value) > 0);
    }
    return selectedCategoryId.value && selectedAccountId.value && Number(exchangeRate.value) > 0;
});

const form = useForm({
    type: 'expense',
    amount: 0,
    account_id: null,
    to_account_id: null,
    category_id: null,
    exchange_rate: 1,
    transfer_amount: null,
    note: '',
    transaction_date: '',
    photo: null,
});

function save() {
    if (!canSave.value || form.processing) return;

    form.type = type.value;
    form.amount = amount.value;
    form.account_id = selectedAccountId.value;
    form.note = note.value;
    form.transaction_date = transactionDate.value;

    if (type.value === 'transfer') {
        form.to_account_id = toAccountId.value;
        form.transfer_amount = crossCurrency.value ? Number(transferAmount.value) : amount.value;
        form.category_id = null;
        form.exchange_rate = 1;
        form.photo = null;
    } else {
        form.category_id = selectedCategoryId.value;
        form.exchange_rate = needsRate.value ? Number(exchangeRate.value) : 1;
        form.to_account_id = null;
        form.transfer_amount = null;
        form.photo = photo.value;
    }

    const url = isEdit.value
        ? route('transactions.update', props.transaction.id)
        : route('transactions.store');

    form.post(url, { forceFormData: true });
}

const close = () => router.visit(route('dashboard'));

const remove = async () => {
    if (!isEdit.value) return;
    const ok = await confirmAction({ title: '¿Eliminar este movimiento?', message: 'Esta acción no se puede deshacer.', confirmLabel: 'Eliminar', danger: true });
    if (!ok) return;
    router.delete(route('transactions.destroy', props.transaction.id));
};

watch(
    () => page.props.flash?.created_category_id,
    (id) => { if (id) selectedCategoryId.value = id; },
    { immediate: true }
);

const keypad = [
    ['7', '8', '9', '÷'],
    ['4', '5', '6', '×'],
    ['1', '2', '3', '-'],
    ['.', '0', 'back', '+'],
];

const tabClass = (t, activeColor) =>
    type.value === t ? `bg-white dark:bg-surface-900 ${activeColor} shadow` : 'text-surface-500';
</script>

<template>
    <Head :title="isEdit ? 'Editar' : 'Agregar'" />

    <div class="flex flex-col h-screen bg-surface-50 dark:bg-surface-950 text-surface-900 dark:text-surface-100">
        <header class="flex items-center justify-between h-14 px-3 shrink-0">
            <button type="button" class="flex items-center gap-1 text-surface-500 w-20" @click="close">
                <IconChevronLeft :size="22" /> Cancelar
            </button>
            <h1 class="font-semibold">{{ isEdit ? 'Editar' : 'Agregar' }}</h1>
            <button v-if="isEdit" type="button" class="flex items-center justify-end w-20 text-rose-500" @click="remove">
                <IconTrash :size="20" />
            </button>
            <span v-else class="w-20"></span>
        </header>

        <!-- Tabs -->
        <div class="px-3 shrink-0">
            <div class="grid grid-cols-3 rounded-xl bg-surface-200/60 dark:bg-surface-800 p-1 max-w-md mx-auto">
                <button type="button" class="py-2 rounded-lg text-sm font-semibold transition" :class="tabClass('expense', 'text-rose-500')" @click="type = 'expense'; selectedCategoryId = null">Gasto</button>
                <button type="button" class="py-2 rounded-lg text-sm font-semibold transition" :class="tabClass('income', 'text-emerald-500')" @click="type = 'income'; selectedCategoryId = null">Ingreso</button>
                <button type="button" class="py-2 rounded-lg text-sm font-semibold transition" :class="tabClass('transfer', 'text-primary')" @click="type = 'transfer'">Transferencia</button>
            </div>
        </div>

        <!-- Área superior: categorías (gasto/ingreso) o cuentas (transferencia) -->
        <div class="flex-1 overflow-y-auto px-3 py-4">
            <div v-if="type !== 'transfer'" class="grid grid-cols-4 gap-y-4 gap-x-2 max-w-md mx-auto">
                <button v-for="cat in filteredCategories" :key="cat.id" type="button" class="flex flex-col items-center gap-1.5" @click="selectedCategoryId = cat.id">
                    <span class="rounded-full transition" :class="selectedCategoryId === cat.id ? 'ring-2 ring-primary ring-offset-2 ring-offset-surface-50 dark:ring-offset-surface-950' : ''">
                        <CategoryIcon :icon-type="cat.icon_type" :icon-value="cat.icon_value" :icon-url="cat.icon_url" :color="cat.color" :size="52" :icon-size="26" />
                    </span>
                    <span class="text-xs text-center leading-tight" :class="selectedCategoryId === cat.id ? 'text-primary font-medium' : 'text-surface-600 dark:text-surface-300'">{{ cat.name }}</span>
                </button>
                <button type="button" class="flex flex-col items-center gap-1.5" @click="showNewCategory = true">
                    <span class="grid place-items-center h-[52px] w-[52px] rounded-full border-2 border-dashed border-surface-300 dark:border-surface-700 text-surface-400"><IconPlus :size="24" /></span>
                    <span class="text-xs text-surface-500">Nueva</span>
                </button>
            </div>

            <!-- Transferencia: cuentas origen/destino -->
            <div v-else class="max-w-md mx-auto flex items-center gap-2 pt-4">
                <div class="flex-1">
                    <label class="text-xs text-surface-500">De</label>
                    <select v-model="selectedAccountId" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-900 py-2">
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.name }} ({{ acc.currency_code }})</option>
                    </select>
                </div>
                <IconArrowRight :size="22" class="text-surface-400 mt-5 shrink-0" />
                <div class="flex-1">
                    <label class="text-xs text-surface-500">A</label>
                    <select v-model="toAccountId" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-900 py-2">
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.name }} ({{ acc.currency_code }})</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Panel inferior -->
        <div class="shrink-0 border-t border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900">
            <div class="max-w-md mx-auto">
                <div class="flex items-center justify-between px-4 pt-3 gap-3">
                    <select v-if="type !== 'transfer'" v-model="selectedAccountId" class="text-sm rounded-lg border-surface-300 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 py-1.5 pr-8 max-w-[45%]">
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                    </select>
                    <span v-else class="text-sm text-surface-500">Monto a transferir</span>
                    <div class="text-right flex-1">
                        <div class="text-2xl font-bold tabular-nums">
                            <span class="text-surface-400 text-lg mr-1">{{ currencySymbol }}</span>{{ displayAmount }}
                        </div>
                        <div v-if="hasOperator" class="text-xs text-surface-400">{{ expression }}</div>
                        <div v-else-if="needsRate" class="text-xs text-surface-400">≈ {{ baseSymbol }} {{ baseConverted }}</div>
                    </div>
                </div>

                <!-- Tipo de cambio (cuenta en moneda no-base) -->
                <div v-if="needsRate" class="flex items-center gap-2 px-4 pt-2 text-sm">
                    <span class="text-surface-500">Cotización: 1 {{ selectedAccount.currency_code }} =</span>
                    <input v-model="exchangeRate" type="number" step="0.0001" class="w-28 rounded-lg border-surface-300 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 py-1 text-sm" />
                    <span class="text-surface-500">{{ defaultCurrency }}</span>
                </div>

                <!-- Monto recibido (transferencia entre monedas) -->
                <div v-if="crossCurrency" class="flex items-center gap-2 px-4 pt-2 text-sm">
                    <span class="text-surface-500">Recibís en {{ toAccount.currency_code }}:</span>
                    <input v-model="transferAmount" type="number" step="0.01" class="w-32 rounded-lg border-surface-300 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 py-1 text-sm" />
                </div>

                <!-- Nota + foto + fecha -->
                <div class="flex items-center gap-2 px-4 py-2">
                    <input v-model="note" type="text" placeholder="Nota…" class="flex-1 text-sm rounded-lg border-surface-300 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 py-1.5" />
                    <label v-if="type !== 'transfer'" class="grid place-items-center h-9 w-9 rounded-lg bg-surface-100 dark:bg-surface-800 text-surface-500 cursor-pointer shrink-0 overflow-hidden">
                        <img v-if="photoPreview" :src="photoPreview" class="h-full w-full object-cover" alt="" />
                        <IconPhoto v-else :size="20" />
                        <input type="file" accept="image/*" class="hidden" @change="onPhoto" />
                    </label>
                    <label class="grid place-items-center h-9 px-2 rounded-lg bg-surface-100 dark:bg-surface-800 text-surface-500 cursor-pointer shrink-0 relative">
                        <IconCalendarEvent :size="20" />
                        <input type="date" v-model="transactionDate" class="absolute inset-0 opacity-0 cursor-pointer" />
                    </label>
                </div>

                <!-- Teclado -->
                <div class="grid grid-cols-4 gap-px bg-surface-200 dark:bg-surface-800 select-none">
                    <template v-for="row in keypad" :key="row.join()">
                        <button v-for="k in row" :key="k" type="button" class="h-14 text-xl font-medium bg-white dark:bg-surface-900 active:bg-surface-100 dark:active:bg-surface-800" :class="isOperator(k) ? 'text-primary' : 'text-surface-800 dark:text-surface-100'" @click="k === 'back' ? backspace() : press(k)">
                            <IconBackspace v-if="k === 'back'" :size="22" class="mx-auto" />
                            <span v-else>{{ k }}</span>
                        </button>
                    </template>
                </div>

                <div class="p-3" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" class="w-full flex items-center justify-center gap-2 rounded-xl bg-primary py-3 font-semibold text-primary-contrast disabled:opacity-40" :disabled="!canSave || form.processing" @click="save">
                        <IconCheck :size="20" /> Guardar
                    </button>
                </div>
            </div>
        </div>

        <CategoryFormDialog v-model="showNewCategory" :type="type === 'income' ? 'income' : 'expense'" @saved="showNewCategory = false" />
        <ConfirmDialog />
    </div>
</template>
