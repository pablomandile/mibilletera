// Formato monetario único para toda la app.
//
// Siempre 2 decimales: sin `minimumFractionDigits` un importe de 30.000,30 se
// mostraba como "30.000,3" porque el 0 final se pierde al pasar por Number().
const AMOUNT_OPTIONS = {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
};

export const formatAmount = (value) => Number(value ?? 0).toLocaleString('es-AR', AMOUNT_OPTIONS);

export const formatMoney = (value, symbol = '$') => symbol + ' ' + formatAmount(value);

// Agrupa la parte entera sin tocar los decimales (para el display de la calculadora,
// donde hay que respetar exactamente lo que se está tecleando).
export const formatInteger = (value) =>
    Number(value || 0).toLocaleString('es-AR', { maximumFractionDigits: 0 });
