<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * «Descargar mis datos»: todo lo que la app guarda de la cuenta, en un JSON.
 *
 * La política de privacidad promete exactamente esto (derecho de acceso).
 * Si se agrega una tabla con datos del usuario, tiene que entrar acá.
 */
class ExportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $datos = [
            'exportado_en' => now()->toIso8601String(),
            'cuenta' => $user->only([
                'name', 'email', 'default_currency', 'locale', 'timezone',
                'month_start_day', 'theme', 'role', 'terminos_version',
                'terminos_aceptados_en', 'created_at',
            ]),
            'cuentas' => $user->accounts()->get()->toArray(),
            'categorias' => $user->categories()->get()->toArray(),
            'transacciones' => $user->transactions()->orderBy('transaction_date')->get()->toArray(),
            'presupuestos' => $user->budgets()->get()->toArray(),
            'recurrentes' => $user->recurringTransactions()->get()->toArray(),
            'recordatorios' => $user->reminders()->get()->toArray(),
            'alertas' => $user->alerts()->get()->toArray(),
        ];

        return response()->json($datos, 200, [
            'Content-Disposition' => 'attachment; filename="mi-billetera-datos.json"',
            'Cache-Control' => 'no-store, private',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
