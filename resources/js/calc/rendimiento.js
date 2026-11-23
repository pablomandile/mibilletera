// Cuentas remuneradas y billeteras (Mercado Pago, Ualá, Naranja X, bancos):
// pagan intereses todos los días sobre el saldo, y esos intereses se suman al
// saldo y al otro día rinden también. Tasas como FRACCIONES (0.188 = 18,8 %).
// Portado de CalculAR (resources/js/lib/calc/rendimiento.ts).

/** Tasa de un día: TNA / 365, o la raíz 365 de la TEA. tipo: 'tna' | 'tea'. */
export function tasaDiaria(tasa, tipo) {
    if (!(tasa > -1)) {
        throw new Error('La tasa tiene que ser mayor a −100 %.');
    }

    return tipo === 'tna' ? tasa / 365 : Math.pow(1 + tasa, 1 / 365) - 1;
}

/**
 * Lo que se gana en `dias` días, con los intereses acreditados cada día. Si
 * hay tope, rinde solo el saldo hasta el tope: lo que lo supera (incluidos los
 * intereses que se van sumando) no gana nada.
 */
export function gananciaEn(saldo, diaria, dias, tope = null) {
    if (tope === null) {
        return saldo * (Math.pow(1 + diaria, dias) - 1);
    }

    let actual = saldo;
    for (let i = 0; i < dias; i++) {
        actual += Math.min(actual, tope) * diaria;
    }

    return actual - saldo;
}

/**
 * @returns {{
 *   diaria: number, porDia: number, porSemana: number,
 *   porMes: number,    // 30 días
 *   porAnio: number,   // 365 días, sin tocar la plata
 *   tna: number, tea: number,
 *   mensual: number,   // lo que rinde en 30 días, como fracción del saldo
 *   noRinde: number,   // parte del saldo que supera el tope y no rinde (0 sin tope)
 * }}
 */
export function rendimientoCuenta(saldo, tasa, tipo, tope = null) {
    if (!(saldo > 0)) {
        throw new Error('El saldo tiene que ser mayor a cero.');
    }
    if (tope !== null && !(tope > 0)) {
        throw new Error('El tope tiene que ser mayor a cero.');
    }

    const diaria = tasaDiaria(tasa, tipo);
    const porMes = gananciaEn(saldo, diaria, 30, tope);

    return {
        diaria,
        porDia: gananciaEn(saldo, diaria, 1, tope),
        porSemana: gananciaEn(saldo, diaria, 7, tope),
        porMes,
        porAnio: gananciaEn(saldo, diaria, 365, tope),
        tna: diaria * 365,
        tea: Math.pow(1 + diaria, 365) - 1,
        mensual: porMes / saldo,
        noRinde: tope === null ? 0 : Math.max(saldo - tope, 0),
    };
}

/**
 * El rendimiento de 30 días contra la inflación de un mes.
 * real = (1 + rendimiento) / (1 + inflación) − 1; enPesos = poder de compra
 * que se gana (o se pierde, si es negativo) en un mes.
 */
export function contraInflacion(saldo, rendimientoMensual, inflacionMensual) {
    const factor = 1 + rendimientoMensual;
    const inflacion = 1 + inflacionMensual;
    const real = factor / inflacion - 1;

    return {
        real,
        enPesos: (saldo * factor) / inflacion - saldo,
        leGana: real > 0,
    };
}
