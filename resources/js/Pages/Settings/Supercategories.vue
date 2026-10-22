<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { IconX, IconTrash, IconPlus, IconFolders, IconCheck } from '@tabler/icons-vue';
import { confirmAction } from '@/composables/useConfirm';
import { mutate } from '@/offline/mutate';
import { newTempId } from '@/offline/outbox';
import { applyList } from '@/offline/overlay';

const props = defineProps({
    supercategories: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] }, // de gasto, principales
});

const COLORS = [
    '#ef4444', '#f97316', '#f59e0b', '#eab308', '#22c55e', '#14b8a6',
    '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#64748b',
];

// Con lo hecho sin conexión.
const shown = computed(() => applyList(props.supercategories, 'supercategory'));

// A qué supercategoría pertenece cada categoría. Una categoría está en una sola:
// si un cambio pendiente la mudó, manda ese (va después en la lista).
const ownerOf = computed(() => {
    const map = new Map();
    const ordered = [...shown.value.filter((s) => !s._pending), ...shown.value.filter((s) => s._pending)];
    for (const s of ordered) {
        for (const id of s.category_ids ?? []) map.set(String(id), s);
    }
    return map;
});
const membersOf = (s) => props.categories.filter((c) => ownerOf.value.get(String(c.id))?.id === s.id);
const looseCount = computed(() => props.categories.filter((c) => !ownerOf.value.has(String(c.id))).length);

// ---- Diálogo ----
const showDialog = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', color: '#6366f1', category_ids: [] });

const openNew = () => {
    editingId.value = null;
    form.reset();
    form.color = COLORS[shown.value.length % COLORS.length];
    form.clearErrors();
    showDialog.value = true;
};
const openEdit = (s) => {
    editingId.value = s.id;
    form.name = s.name;
    form.color = s.color;
    form.category_ids = membersOf(s).map((c) => c.id);
    form.clearErrors();
    showDialog.value = true;
};
const close = () => (showDialog.value = false);

// Llegan como category_ids o category_ids.<n>.
const categoriesError = computed(() =>
    Object.entries(form.errors).find(([key]) => key.startsWith('category_ids'))?.[1] ?? null
);

const isPicked = (c) => form.category_ids.some((id) => String(id) === String(c.id));
const togglePick = (c) => {
    form.category_ids = isPicked(c)
        ? form.category_ids.filter((id) => String(id) !== String(c.id))
        : [...form.category_ids, c.id];
};
// La que ya está en otra se muestra con su nombre: elegirla la muda a esta.
const otherOwner = (c) => {
    const owner = ownerOf.value.get(String(c.id));
    return owner && owner.id !== editingId.value ? owner.name : null;
};

const save = async () => {
    const id = editingId.value;
    const data = form.data();
    const opts = { preserveScroll: true, onSuccess: () => close() };
    const result = await mutate(
        { entity: 'supercategory', action: id ? 'update' : 'create', id, temp_id: id ? null : newTempId(), data, view: data },
        (extra) =>
            id
                ? form.patch(route('supercategories.update', id), { ...opts, ...extra })
                : form.post(route('supercategories.store'), { ...opts, ...extra })
    );
    if (result === 'queued') close();
};

const remove = async () => {
    const id = editingId.value;
    if (!id) return;
    const ok = await confirmAction({
        title: '¿Eliminar esta supercategoría?',
        message: 'Sus categorías no se borran: quedan sin agrupar.',
        confirmLabel: 'Eliminar',
        danger: true,
    });
    if (!ok) return;
    const result = await mutate({ entity: 'supercategory', action: 'delete', id }, (extra) =>
        router.delete(route('supercategories.destroy', id), { preserveScroll: true, onSuccess: () => close(), ...extra })
    );
    if (result === 'queued') close();
};
</script>

<template>
    <Head title="Supercategorías" />

    <AppLayout title="Supercategorías">
        <div class="mx-auto max-w-2xl space-y-4">
            <p class="text-sm text-surface-500">
                Agrupan categorías de gasto para el gráfico «A vuelo de pájaro». Por ejemplo, Comida puede juntar
                Supermercado, Verdura, Fruta y Bebidas. Cada categoría va en una sola supercategoría.
            </p>

            <div v-if="shown.length" class="space-y-2">
                <button
                    v-for="s in shown"
                    :key="s.id"
                    type="button"
                    class="w-full text-left rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4 hover:border-primary/50 transition"
                    @click="openEdit(s)"
                >
                    <div class="flex items-center gap-3">
                        <span class="h-4 w-4 rounded-full shrink-0" :style="{ backgroundColor: s.color }"></span>
                        <span class="flex-1 min-w-0 font-medium break-words hyphens-auto">{{ s.name }}</span>
                        <span class="shrink-0 text-xs text-surface-500">
                            {{ membersOf(s).length === 1 ? '1 categoría' : `${membersOf(s).length} categorías` }}
                        </span>
                    </div>
                    <div v-if="membersOf(s).length" class="mt-3 flex flex-wrap gap-1.5">
                        <span
                            v-for="c in membersOf(s)"
                            :key="c.id"
                            class="inline-flex items-center gap-1.5 rounded-full bg-surface-100 dark:bg-surface-800 pl-1 pr-2.5 py-0.5 text-xs"
                        >
                            <CategoryIcon :icon-type="c.icon_type" :icon-value="c.icon_value" :icon-url="c.icon_url" :color="c.color" :size="20" :icon-size="12" />
                            <span class="break-words">{{ c.name }}</span>
                        </span>
                    </div>
                    <p v-else class="mt-2 text-xs text-surface-500">Todavía no tiene categorías. Tocá para elegirlas.</p>
                </button>
            </div>
            <div v-else class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-8 text-center text-surface-500">
                <IconFolders :size="32" class="mx-auto mb-2 text-surface-400" />
                No tenés supercategorías.
            </div>

            <p v-if="shown.length && looseCount" class="px-1 text-xs text-surface-500">
                {{ looseCount === 1 ? '1 categoría queda' : `${looseCount} categorías quedan` }} sin agrupar: en el gráfico van juntas como «Sin supercategoría».
            </p>

            <button type="button" class="w-full flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-surface-300 dark:border-surface-700 py-4 text-surface-500 hover:border-primary hover:text-primary transition" @click="openNew">
                <IconPlus :size="20" /> Nueva supercategoría
            </button>
        </div>

        <Teleport to="body">
            <div v-if="showDialog" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 sm:p-4" @click.self="close">
                <div class="w-full sm:max-w-md max-h-[90vh] flex flex-col bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800">
                    <div class="flex items-center justify-between px-5 h-14 shrink-0 border-b border-surface-200 dark:border-surface-800">
                        <button type="button" class="text-surface-500" aria-label="Cerrar" @click="close"><IconX :size="22" /></button>
                        <h2 class="font-semibold">{{ editingId ? 'Editar supercategoría' : 'Nueva supercategoría' }}</h2>
                        <span class="w-6"></span>
                    </div>
                    <div class="p-5 space-y-4 overflow-y-auto">
                        <div>
                            <label class="text-xs text-surface-500" for="super-name">Nombre</label>
                            <input id="super-name" v-model="form.name" type="text" maxlength="60" placeholder="Ej. Comida" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 focus:border-primary focus:ring-primary" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-rose-500">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-2">Color</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="c in COLORS"
                                    :key="c"
                                    type="button"
                                    class="h-8 w-8 rounded-full border-2 transition"
                                    :class="form.color === c ? 'border-white ring-2 ring-primary scale-110' : 'border-transparent'"
                                    :style="{ backgroundColor: c }"
                                    :aria-label="`Color ${c}`"
                                    :aria-pressed="form.color === c"
                                    @click="form.color = c"
                                ></button>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs text-surface-500 mb-2">Categorías que agrupa</p>
                            <div class="rounded-xl border border-surface-200 dark:border-surface-800 divide-y divide-surface-100 dark:divide-surface-800">
                                <button
                                    v-for="c in categories"
                                    :key="c.id"
                                    type="button"
                                    role="checkbox"
                                    :aria-checked="isPicked(c)"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 text-left"
                                    @click="togglePick(c)"
                                >
                                    <CategoryIcon :icon-type="c.icon_type" :icon-value="c.icon_value" :icon-url="c.icon_url" :color="c.color" :size="30" :icon-size="16" />
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-sm break-words hyphens-auto">{{ c.name }}</span>
                                        <span v-if="otherOwner(c)" class="block text-xs text-surface-500 break-words">
                                            {{ isPicked(c) ? 'Se muda desde' : 'Ahora en' }} {{ otherOwner(c) }}
                                        </span>
                                    </span>
                                    <span
                                        class="grid place-items-center h-5 w-5 shrink-0 rounded-md border transition"
                                        :class="isPicked(c) ? 'bg-primary border-primary text-primary-contrast' : 'border-surface-300 dark:border-surface-600'"
                                    >
                                        <IconCheck v-if="isPicked(c)" :size="14" :stroke="3" />
                                    </span>
                                </button>
                                <p v-if="!categories.length" class="px-3 py-4 text-sm text-surface-500">No tenés categorías de gasto.</p>
                            </div>
                            <p v-if="categoriesError" class="mt-1 text-sm text-rose-500">{{ categoriesError }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2 p-5 pt-0 shrink-0">
                        <button v-if="editingId" type="button" class="grid place-items-center h-11 w-11 rounded-xl border border-rose-500/30 text-rose-500 hover:bg-rose-500/10" aria-label="Eliminar" @click="remove"><IconTrash :size="20" /></button>
                        <button type="button" class="flex-1 rounded-xl bg-primary py-3 font-semibold text-primary-contrast disabled:opacity-50" :disabled="form.processing" @click="save">{{ editingId ? 'Guardar' : 'Crear' }}</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
