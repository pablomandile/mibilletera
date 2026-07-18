<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { IconX, IconPhoto, IconCheck, IconTrash } from '@tabler/icons-vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import { pickerIcons } from '@/tablerIcons';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    type: { type: String, default: 'expense' },
    category: { type: Object, default: null }, // si viene, es edición
    parentId: { type: Number, default: null }, // si viene, crea una subcategoría
});

const emit = defineEmits(['update:modelValue', 'saved']);

const COLORS = [
    '#ef4444', '#f97316', '#f59e0b', '#eab308', '#22c55e', '#14b8a6',
    '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#64748b',
];

const isEdit = computed(() => !!props.category);
const iconMode = ref('preset'); // preset | image
const imagePreview = ref(null);

const form = useForm({
    type: props.type,
    name: '',
    icon_type: 'preset',
    icon_value: 'shopping-cart',
    image: null,
    color: '#f59e0b',
    parent_id: null,
    _method: 'POST',
});

// Reinicia el formulario cada vez que se abre
watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        if (props.category) {
            form.type = props.category.type;
            form.name = props.category.name;
            form.icon_type = props.category.icon_type;
            form.icon_value = props.category.icon_value;
            form.color = props.category.color;
            form.parent_id = props.category.parent_id ?? null;
            iconMode.value = props.category.icon_type === 'image' ? 'image' : 'preset';
            imagePreview.value = props.category.icon_url ?? null;
        } else {
            form.reset();
            form.type = props.type;
            form.parent_id = props.parentId;
            form.icon_value = props.type === 'income' ? 'cash' : 'shopping-cart';
            iconMode.value = 'preset';
            imagePreview.value = null;
        }
        form.clearErrors();
    }
);

const close = () => emit('update:modelValue', false);

const setIconMode = (mode) => {
    iconMode.value = mode;
    form.icon_type = mode;
};

const onImage = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    form.image = file;
    form.icon_type = 'image';
    iconMode.value = 'image';
    imagePreview.value = URL.createObjectURL(file);
};

const submit = () => {
    const url = isEdit.value
        ? route('categories.update', props.category.id)
        : route('categories.store');

    form
        .transform((data) => ({
            ...data,
            icon_type: iconMode.value,
        }))
        .post(url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                emit('saved');
                close();
            },
        });
};

const remove = () => {
    if (!isEdit.value || !confirm('¿Eliminar esta categoría?')) return;
    router.delete(route('categories.destroy', props.category.id), {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); close(); },
    });
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 p-0 sm:p-4"
            @click.self="close"
        >
            <div class="w-full sm:max-w-md bg-white dark:bg-surface-900 rounded-t-3xl sm:rounded-2xl border border-surface-200 dark:border-surface-800 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-5 h-14 border-b border-surface-200 dark:border-surface-800 sticky top-0 bg-white dark:bg-surface-900">
                    <button type="button" class="text-surface-500" @click="close"><IconX :size="22" /></button>
                    <h2 class="font-semibold">{{ isEdit ? 'Editar categoría' : 'Nueva categoría' }}</h2>
                    <button type="button" class="text-primary" @click="submit"><IconCheck :size="22" /></button>
                </div>

                <div class="p-5 space-y-5">
                    <!-- Vista previa + nombre -->
                    <div class="flex items-center gap-3">
                        <CategoryIcon
                            :icon-type="iconMode"
                            :icon-value="form.icon_value"
                            :icon-url="imagePreview"
                            :color="form.color"
                            :size="52"
                            :icon-size="28"
                        />
                        <div class="flex-1">
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Nombre de la categoría"
                                class="w-full rounded-lg border-surface-300 dark:border-surface-700 bg-white dark:bg-surface-800 text-surface-900 dark:text-surface-100 focus:border-primary focus:ring-primary"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-rose-500">{{ form.errors.name }}</p>
                        </div>
                    </div>

                    <!-- Tipo de ícono -->
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-lg py-2 text-sm font-medium border"
                            :class="iconMode === 'preset' ? 'border-primary text-primary bg-primary/10' : 'border-surface-300 dark:border-surface-700 text-surface-500'"
                            @click="setIconMode('preset')"
                        >
                            Ícono
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg py-2 text-sm font-medium border"
                            :class="iconMode === 'image' ? 'border-primary text-primary bg-primary/10' : 'border-surface-300 dark:border-surface-700 text-surface-500'"
                            @click="setIconMode('image')"
                        >
                            Imagen propia
                        </button>
                    </div>

                    <!-- Selector de ícono -->
                    <div v-show="iconMode === 'preset'">
                        <div class="grid grid-cols-6 gap-2 max-h-52 overflow-y-auto p-1">
                            <button
                                v-for="key in pickerIcons"
                                :key="key"
                                type="button"
                                class="grid place-items-center aspect-square rounded-lg border transition"
                                :class="form.icon_value === key ? 'border-primary ring-2 ring-primary/40' : 'border-surface-200 dark:border-surface-800'"
                                @click="form.icon_value = key"
                            >
                                <CategoryIcon :icon-value="key" :color="form.color" :size="34" :icon-size="20" />
                            </button>
                        </div>
                    </div>

                    <!-- Subida de imagen -->
                    <div v-show="iconMode === 'image'">
                        <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-surface-300 dark:border-surface-700 py-6 cursor-pointer text-surface-500 hover:border-primary">
                            <IconPhoto :size="28" />
                            <span class="text-sm">Subí una imagen (ej. logo de un comercio)</span>
                            <input type="file" accept="image/*" class="hidden" @change="onImage" />
                        </label>
                        <p v-if="form.errors.image" class="mt-1 text-sm text-rose-500">{{ form.errors.image }}</p>
                    </div>

                    <!-- Color -->
                    <div>
                        <p class="text-sm text-surface-500 mb-2">Color</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="c in COLORS"
                                :key="c"
                                type="button"
                                class="h-8 w-8 rounded-full border-2 transition"
                                :class="form.color === c ? 'border-white ring-2 ring-primary scale-110' : 'border-transparent'"
                                :style="{ backgroundColor: c }"
                                @click="form.color = c"
                            />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button
                            v-if="isEdit"
                            type="button"
                            class="grid place-items-center h-11 w-11 rounded-lg border border-rose-500/30 text-rose-500 hover:bg-rose-500/10 shrink-0"
                            @click="remove"
                        >
                            <IconTrash :size="20" />
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg bg-primary py-2.5 font-semibold text-primary-contrast hover:brightness-95 disabled:opacity-50"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            {{ isEdit ? 'Guardar cambios' : 'Crear categoría' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
