<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { IconX, IconTrash, IconPlus, IconBell } from '@tabler/icons-vue';
import { confirmAction } from '@/composables/useConfirm';

const props = defineProps({
    reminders: { type: Array, default: () => [] },
});

const DAYS = [
    { v: 1, l: 'Lu' }, { v: 2, l: 'Ma' }, { v: 3, l: 'Mi' }, { v: 4, l: 'Ju' },
    { v: 5, l: 'Vi' }, { v: 6, l: 'Sa' }, { v: 0, l: 'Do' },
];
const daysLabel = (days) => {
    if (!days || days.length === 0 || days.length === 7) return 'Todos los días';
    return DAYS.filter((d) => days.includes(d.v)).map((d) => d.l).join(', ');
};

const showDialog = ref(false);
const editingId = ref(null);
const form = useForm({ title: '', time: '20:00', days: [], enabled: true });

const openNew = () => { editingId.value = null; form.reset(); form.time = '20:00'; form.clearErrors(); showDialog.value = true; };
const openEdit = (r) => {
    editingId.value = r.id;
    form.title = r.title; form.time = r.time; form.days = [...(r.days ?? [])]; form.enabled = r.enabled;
    form.clearErrors(); showDialog.value = true;
};
const close = () => (showDialog.value = false);

const toggleDay = (v) => {
    const i = form.days.indexOf(v);
    if (i === -1) form.days.push(v); else form.days.splice(i, 1);
};

const save = () => {
    const opts = { preserveScroll: true, onSuccess: () => close() };
    if (editingId.value) form.patch(route('reminders.update', editingId.value), opts);
    else form.post(route('reminders.store'), opts);
};
const remove = async () => {
    if (!editingId.value) return;
    const ok = await confirmAction({ title: '¿Eliminar este recordatorio?', message: 'Esta acción no se puede deshacer.', confirmLabel: 'Eliminar', danger: true });
    if (!ok) return;
    router.delete(route('reminders.destroy', editingId.value), { preserveScroll: true, onSuccess: () => close() });
};
const toggleEnabled = (r) => {
    router.patch(route('reminders.update', r.id), { title: r.title, time: r.time, days: r.days, enabled: !r.enabled }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Recordatorios" />

    <AppLayout title="Recordatorios">
        <div class="mx-auto max-w-2xl space-y-4">
            <p class="text-sm text-surface-500">
                Programá avisos para acordarte de cargar tus gastos. (La entrega por notificación push
                se agrega más adelante; por ahora quedan guardados y configurables.)
            </p>

            <div v-if="reminders.length" class="space-y-2">
                <div v-for="r in reminders" :key="r.id"
                    class="flex items-center gap-3 rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-4"
                    :class="!r.enabled ? 'opacity-60' : ''">
                    <span class="grid place-items-center h-11 w-11 rounded-full bg-primary/10 text-primary shrink-0"><IconBell :size="22" /></span>
                    <button type="button" class="min-w-0 flex-1 text-left" @click="openEdit(r)">
                        <p class="font-medium truncate">{{ r.title }}</p>
                        <p class="text-xs text-surface-500">{{ r.time }} · {{ daysLabel(r.days) }}</p>
                    </button>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" class="sr-only peer" :checked="r.enabled" @change="toggleEnabled(r)" />
                        <div class="w-10 h-6 bg-surface-300 dark:bg-surface-700 rounded-full peer peer-checked:bg-primary transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-4"></div>
                    </label>
                </div>
            </div>
            <div v-else class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-8 text-center text-surface-500">
                <IconBell :size="32" class="mx-auto mb-2 text-surface-400" />
                No tenés recordatorios.
            </div>

            <button type="button" class="w-full flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-surface-300 dark:border-surface-700 py-4 text-surface-500 hover:border-primary hover:text-primary transition" @click="openNew">
                <IconPlus :size="20" /> Nuevo recordatorio
            </button>
        </div>

        <Teleport to="body">
            <div v-if="showDialog" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 sm:p-4" @click.self="close">
                <div class="w-full sm:max-w-md bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800">
                    <div class="flex items-center justify-between px-5 h-14 border-b border-surface-200 dark:border-surface-800">
                        <button type="button" class="text-surface-500" @click="close"><IconX :size="22" /></button>
                        <h2 class="font-semibold">{{ editingId ? 'Editar recordatorio' : 'Nuevo recordatorio' }}</h2>
                        <span class="w-6"></span>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="text-xs text-surface-500">Título</label>
                            <input v-model="form.title" type="text" placeholder="Ej. Cargar gastos del día" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 focus:border-primary focus:ring-primary" />
                            <p v-if="form.errors.title" class="mt-1 text-sm text-rose-500">{{ form.errors.title }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-surface-500">Hora</label>
                            <input v-model="form.time" type="time" class="mt-1 w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800" />
                            <p v-if="form.errors.time" class="mt-1 text-sm text-rose-500">{{ form.errors.time }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-surface-500">Días (vacío = todos)</label>
                            <div class="mt-2 flex gap-1.5">
                                <button v-for="d in DAYS" :key="d.v" type="button"
                                    class="h-9 w-9 rounded-full text-sm font-medium transition"
                                    :class="form.days.includes(d.v) ? 'bg-primary text-primary-contrast' : 'bg-surface-100 dark:bg-surface-800 text-surface-500'"
                                    @click="toggleDay(d.v)">{{ d.l }}</button>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <button v-if="editingId" type="button" class="grid place-items-center h-11 w-11 rounded-xl border border-rose-500/30 text-rose-500 hover:bg-rose-500/10" @click="remove"><IconTrash :size="20" /></button>
                            <button type="button" class="flex-1 rounded-xl bg-primary py-3 font-semibold text-primary-contrast disabled:opacity-50" :disabled="form.processing" @click="save">{{ editingId ? 'Guardar' : 'Crear' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
