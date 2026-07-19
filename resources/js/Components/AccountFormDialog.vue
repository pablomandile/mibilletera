<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { IconX, IconTrash } from '@tabler/icons-vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import { confirmAction } from '@/composables/useConfirm';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    account: { type: Object, default: null },
    currencies: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

const COLORS = ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#64748b'];
const ICONS = ['cash', 'wallet', 'building-bank', 'credit-card', 'coin', 'pig-money', 'briefcase'];
const TYPES = [
    { value: 'cash', label: 'Efectivo' },
    { value: 'bank', label: 'Banco' },
    { value: 'card', label: 'Tarjeta' },
    { value: 'other', label: 'Otro' },
];

const isEdit = computed(() => !!props.account);

const form = useForm({
    name: '',
    type: 'cash',
    currency_code: 'ARS',
    initial_balance: 0,
    color: '#22c55e',
    icon: 'cash',
    is_archived: false,
});

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        if (props.account) {
            form.name = props.account.name;
            form.type = props.account.type;
            form.currency_code = props.account.currency_code;
            form.initial_balance = props.account.initial_balance;
            form.color = props.account.color;
            form.icon = props.account.icon;
            form.is_archived = props.account.is_archived;
        } else {
            form.reset();
        }
        form.clearErrors();
    }
);

const close = () => emit('update:modelValue', false);

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => close() };
    if (isEdit.value) {
        form.patch(route('accounts.update', props.account.id), opts);
    } else {
        form.post(route('accounts.store'), opts);
    }
};

const remove = async () => {
    const ok = await confirmAction({ title: '¿Eliminar esta cuenta?', message: 'Solo se puede eliminar una cuenta sin movimientos.', confirmLabel: 'Eliminar', danger: true });
    if (!ok) return;
    router.delete(route('accounts.destroy', props.account.id), { preserveScroll: true, onSuccess: () => close() });
};
</script>

<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 sm:p-4" @click.self="close">
            <div class="w-full sm:max-w-md bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-5 h-14 border-b border-surface-200 dark:border-surface-800 sticky top-0 bg-white dark:bg-surface-900">
                    <button type="button" class="text-surface-500" @click="close"><IconX :size="22" /></button>
                    <h2 class="font-semibold">{{ isEdit ? 'Editar cuenta' : 'Nueva cuenta' }}</h2>
                    <span class="w-6"></span>
                </div>

                <div class="p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <CategoryIcon :icon-value="form.icon" :color="form.color" :size="52" :icon-size="28" />
                        <div class="flex-1">
                            <input v-model="form.name" type="text" placeholder="Nombre (ej. Banco Galicia)"
                                class="w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 focus:border-primary focus:ring-primary" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-rose-500">{{ form.errors.name }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-surface-500">Tipo</label>
                            <select v-model="form.type" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800">
                                <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-surface-500">Moneda</label>
                            <select v-model="form.currency_code" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800">
                                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} ({{ c.symbol }})</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs text-surface-500">Saldo inicial</label>
                        <input v-model="form.initial_balance" type="number" step="0.01"
                            class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 focus:border-primary focus:ring-primary" />
                        <p v-if="form.errors.initial_balance" class="mt-1 text-sm text-rose-500">{{ form.errors.initial_balance }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-2">Ícono</p>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="ic in ICONS" :key="ic" type="button"
                                class="rounded-full transition"
                                :class="form.icon === ic ? 'ring-2 ring-primary ring-offset-2 ring-offset-white dark:ring-offset-surface-900' : ''"
                                @click="form.icon = ic">
                                <CategoryIcon :icon-value="ic" :color="form.color" :size="40" :icon-size="22" />
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs text-surface-500 mb-2">Color</p>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="c in COLORS" :key="c" type="button"
                                class="h-8 w-8 rounded-full border-2 transition"
                                :class="form.color === c ? 'border-white ring-2 ring-primary scale-110' : 'border-transparent'"
                                :style="{ backgroundColor: c }" @click="form.color = c" />
                        </div>
                    </div>

                    <label v-if="isEdit" class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_archived" class="rounded border-surface-300 dark:border-surface-700 text-primary focus:ring-primary" />
                        Archivar cuenta (oculta de las listas)
                    </label>

                    <p v-if="form.errors.account" class="text-sm text-rose-500">{{ form.errors.account }}</p>

                    <div class="flex gap-2 pt-1">
                        <button v-if="isEdit" type="button" class="grid place-items-center h-11 w-11 rounded-xl border border-rose-500/30 text-rose-500 hover:bg-rose-500/10" @click="remove">
                            <IconTrash :size="20" />
                        </button>
                        <button type="button" class="flex-1 rounded-xl bg-primary py-3 font-semibold text-primary-contrast hover:brightness-95 disabled:opacity-50"
                            :disabled="form.processing" @click="submit">
                            {{ isEdit ? 'Guardar cambios' : 'Crear cuenta' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
