<script setup>
// Torta (dona) de Gráficos e Informes: porciones { name, total, color }.
import { computed } from 'vue';
import Chart from 'primevue/chart';
import { formatMoney } from '@/money';

const props = defineProps({
    slices: { type: Array, required: true },
    symbol: { type: String, default: '$' },
});

const data = computed(() => ({
    labels: props.slices.map((s) => s.name),
    datasets: [
        {
            data: props.slices.map((s) => s.total),
            backgroundColor: props.slices.map((s) => s.color),
            borderWidth: 0,
        },
    ],
}));

const options = computed(() => ({
    maintainAspectRatio: false,
    cutout: '62%',
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${formatMoney(ctx.parsed, props.symbol)}` } },
    },
}));
</script>

<template>
    <div class="relative h-56">
        <Chart type="doughnut" :data="data" :options="options" class="h-56" />
    </div>
</template>
