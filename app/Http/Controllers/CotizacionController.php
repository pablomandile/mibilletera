<?php

namespace App\Http\Controllers;

use App\Services\DollarRates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La cotización que precarga el formulario de un movimiento en dólares. La
 * consulta a bambudev la hace el servidor: la clave nunca llega al navegador.
 */
class CotizacionController extends Controller
{
    public function __invoke(Request $request, DollarRates $rates): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'regex:/^[a-z_]{1,30}$/'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $rate = $rates->rate($data['tipo'], $data['fecha']);

        return response()->json([
            'tipo' => $data['tipo'],
            'nombre' => $rate['nombre'] ?? null,
            'venta' => $rate['venta'] ?? null,
            // Puede ser anterior a la pedida (fin de semana, feriado).
            'fecha' => $rate['fecha'] ?? null,
        ])->header('Cache-Control', 'no-store, private');
    }
}
