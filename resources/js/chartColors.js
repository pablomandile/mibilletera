// Colores de los gráficos que comparten Gráficos e Informes.

export const FIXED = '#8b5cf6';
export const VARIABLE = '#f43f5e';

// Cada gasto fijo lleva un color "al azar" que sale del id de su serie (ángulo
// de oro sobre el círculo de tonos): distintos entre sí, pero siempre el mismo
// para el mismo fijo, así no cambia de color cada vez que se abre la página.
export const fixedColor = (id) => `hsl(${Math.round((id * 137.508) % 360)}, 68%, 55%)`;

// Los fijos como porciones de torta, cada uno con su color.
export const fixedSlices = (items) => items.map((f) => ({ name: f.name, total: f.total, color: fixedColor(f.id) }));
