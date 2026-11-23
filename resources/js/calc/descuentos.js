// Descuentos y reintegros con tope (los de bancos, billeteras, Cuenta DNI):
// "20 % de reintegro, tope $ 6.000". El porcentaje va como FRACCIÓN
// (0.2 = 20 %). Portado de CalculAR (resources/js/lib/calc/descuentos.ts).

export function reintegroDe(monto, porcentaje, tope) {
    return Math.min(monto * porcentaje, tope);
}

/**
 * @returns {{
 *   compraMaxima: number,            // la compra que aprovecha el tope entero: tope / porcentaje
 *   compra: null | {                 // solo si se indicó cuánto se va a gastar
 *     monto: number,
 *     reintegro: number,
 *     pagas: number,                 // lo que sale de verdad: monto − reintegro
 *     descuentoEfectivo: number,     // reintegro / monto: baja cuando se pasa el tope
 *     sinDescuento: number,          // la parte de la compra sin descuento (0 si no se pasa)
 *     llegaAlTope: boolean,
 *   },
 * }}
 */
export function descuentoConTope(porcentaje, tope, monto = null) {
    if (!(porcentaje > 0) || porcentaje > 1) {
        throw new Error('El porcentaje tiene que estar entre 0 % y 100 %.');
    }
    if (!(tope > 0)) {
        throw new Error('El tope tiene que ser mayor a cero.');
    }
    if (monto !== null && !(monto > 0)) {
        throw new Error('El monto de la compra tiene que ser mayor a cero.');
    }

    const compraMaxima = tope / porcentaje;

    if (monto === null) {
        return { compraMaxima, compra: null };
    }

    const reintegro = reintegroDe(monto, porcentaje, tope);

    return {
        compraMaxima,
        compra: {
            monto,
            reintegro,
            pagas: monto - reintegro,
            descuentoEfectivo: reintegro / monto,
            sinDescuento: Math.max(monto - compraMaxima, 0),
            llegaAlTope: monto >= compraMaxima,
        },
    };
}
