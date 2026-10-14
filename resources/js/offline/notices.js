import { ref } from 'vue';

// Avisos para mostrar en un toast desde código que no es un componente (la
// sincronización) o desde pantallas sin AppLayout (la de Agregar). AppLayout
// los consume al montarse, así no se pierden al cambiar de pantalla.
export const notices = ref([]);

export function notify(summary, severity = 'success', detail = undefined) {
    notices.value = [...notices.value, { summary, severity, detail }];
}
