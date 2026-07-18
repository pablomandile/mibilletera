<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoryIcon from '@/Components/CategoryIcon.vue';
import CategoryFormDialog from '@/Components/CategoryFormDialog.vue';
import { Head } from '@inertiajs/vue3';
import { IconPlus, IconPencil, IconCornerDownRight } from '@tabler/icons-vue';

const props = defineProps({
    categories: { type: Array, default: () => [] }, // top-level con children
});

const type = ref('expense');
const list = computed(() => props.categories.filter((c) => c.type === type.value));

const showDialog = ref(false);
const dialogCategory = ref(null);
const dialogParentId = ref(null);

const newCategory = () => { dialogCategory.value = null; dialogParentId.value = null; showDialog.value = true; };
const editCategory = (c) => { dialogCategory.value = c; dialogParentId.value = null; showDialog.value = true; };
const newSub = (parent) => { dialogCategory.value = null; dialogParentId.value = parent.id; showDialog.value = true; };
</script>

<template>
    <Head title="Categorías" />

    <AppLayout title="Categorías">
        <div class="mx-auto max-w-2xl space-y-4">
            <!-- Tabs -->
            <div class="grid grid-cols-2 rounded-xl bg-surface-200/60 dark:bg-surface-800 p-1">
                <button type="button" class="py-2 rounded-lg text-sm font-semibold transition" :class="type === 'expense' ? 'bg-white dark:bg-surface-900 text-rose-500 shadow' : 'text-surface-500'" @click="type = 'expense'">Gasto</button>
                <button type="button" class="py-2 rounded-lg text-sm font-semibold transition" :class="type === 'income' ? 'bg-white dark:bg-surface-900 text-emerald-500 shadow' : 'text-surface-500'" @click="type = 'income'">Ingreso</button>
            </div>

            <div class="space-y-2">
                <div v-for="c in list" :key="c.id" class="rounded-2xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-3">
                        <CategoryIcon :icon-type="c.icon_type" :icon-value="c.icon_value" :icon-url="c.icon_url" :color="c.color" :size="42" :icon-size="22" />
                        <span class="flex-1 font-medium truncate">{{ c.name }}</span>
                        <button type="button" class="grid place-items-center h-8 w-8 rounded-lg text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800" title="Subcategoría" @click="newSub(c)"><IconCornerDownRight :size="18" /></button>
                        <button type="button" class="grid place-items-center h-8 w-8 rounded-lg text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800" title="Editar" @click="editCategory(c)"><IconPencil :size="18" /></button>
                    </div>

                    <!-- Subcategorías -->
                    <div v-if="c.children && c.children.length" class="border-t border-surface-100 dark:border-surface-800 divide-y divide-surface-100 dark:divide-surface-800 bg-surface-50/40 dark:bg-surface-800/20">
                        <button v-for="sub in c.children" :key="sub.id" type="button" class="w-full flex items-center gap-3 pl-8 pr-4 py-2.5 text-left hover:bg-surface-100 dark:hover:bg-surface-800" @click="editCategory(sub)">
                            <CategoryIcon :icon-type="sub.icon_type" :icon-value="sub.icon_value" :icon-url="sub.icon_url" :color="sub.color" :size="30" :icon-size="16" />
                            <span class="flex-1 text-sm truncate">{{ sub.name }}</span>
                            <IconPencil :size="16" class="text-surface-400" />
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" class="w-full flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-surface-300 dark:border-surface-700 py-4 text-surface-500 hover:border-primary hover:text-primary transition" @click="newCategory">
                <IconPlus :size="20" /> Nueva categoría
            </button>
        </div>

        <CategoryFormDialog
            v-model="showDialog"
            :type="type"
            :category="dialogCategory"
            :parent-id="dialogParentId"
            @saved="showDialog = false"
        />
    </AppLayout>
</template>
