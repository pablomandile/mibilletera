<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use App\Services\InflacionMensual;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calculadoras sueltas (portadas de CalculAR). Las cuentas viven en
 * resources/js/calc; el servidor solo pasa los datos que las completan.
 */
class HerramientasController extends Controller
{
    public function descuento(): Response
    {
        return Inertia::render('Herramientas/DescuentoConTope');
    }

    public function rendimiento(InflacionMensual $inflacion): Response
    {
        /** @var User $user */
        $user = Auth::user();

        // Las tasas son en pesos: solo las cuentas en ARS, para completar el saldo.
        $accounts = $user->accounts()
            ->where('currency_code', 'ARS')
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'balance' => $a->currentBalance(),
            ]);

        return Inertia::render('Herramientas/RendimientoCuenta', [
            'accounts' => $accounts,
            'inflacion' => $inflacion->ultima(),
            'calcularUrl' => $inflacion->url(),
        ]);
    }
}
