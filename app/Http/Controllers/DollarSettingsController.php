<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\DollarRates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajustes → Cotización del dólar. Cada usuario elige qué dólar se precarga en
 * sus gastos y en sus ingresos; un admin carga la API (dirección y clave), que
 * es una sola para toda la app.
 */
class DollarSettingsController extends Controller
{
    public function index(Request $request, DollarRates $rates): Response
    {
        $user = $request->user();
        $live = $rates->types();
        $types = $live ?? collect(DollarRates::KNOWN_TYPES)
            ->map(fn ($nombre, $tipo) => ['tipo' => $tipo, 'nombre' => $nombre, 'venta' => null])
            ->values()->all();

        $props = [
            'types' => $types,
            'live' => $live !== null,
            'expense' => DollarRates::typeFor('expense', $user),
            'income' => DollarRates::typeFor('income', $user),
            'isAdmin' => $user->isAdmin(),
        ];

        if ($user->isAdmin()) {
            $key = AppSetting::readSecret(DollarRates::SETTING_KEY);
            $props['api'] = [
                'url' => AppSetting::read(DollarRates::SETTING_URL) ?? '',
                'defaultUrl' => (string) config('services.bambudev.url'),
                // La clave nunca vuelve al navegador: solo si hay una y cómo termina.
                'hasKey' => (bool) $key,
                'keyEnd' => $key ? mb_substr($key, -4) : null,
                'envKey' => ! $key && (bool) config('services.bambudev.key'),
            ];
        }

        return Inertia::render('Settings/Dollar', $props);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $type = ['required', 'string', 'regex:/^[a-z_]{1,30}$/'];
        $data = $request->validate([
            'dollar_type_expense' => $type,
            'dollar_type_income' => $type,
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Dólar por defecto guardado.');
    }

    public function updateApi(Request $request, DollarRates $rates): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['nullable', 'url:https,http', 'max:255'],
            'key' => ['nullable', 'string', 'max:255'],
            'clear_key' => ['boolean'],
        ]);

        $url = trim((string) ($data['url'] ?? ''));
        $newKey = trim((string) ($data['key'] ?? ''));
        $clear = (bool) ($data['clear_key'] ?? false);

        // La clave que queda: la nueva, o la que ya estaba (salvo que se quite).
        $key = $newKey ?: ($clear ? null : AppSetting::readSecret(DollarRates::SETTING_KEY));

        // Antes de guardar se prueba: una dirección o una clave que no andan dejarían
        // a todos cargando la cotización a mano sin saber por qué.
        if ($key) {
            $problem = $rates->check($url ?: (string) config('services.bambudev.url'), $key);
            if ($problem) {
                throw ValidationException::withMessages([$newKey ? 'key' : 'url' => $problem]);
            }
        }

        AppSetting::write(DollarRates::SETTING_URL, $url ?: null);
        if ($newKey || $clear) {
            AppSetting::writeSecret(DollarRates::SETTING_KEY, $newKey ?: null);
        }

        return back()->with('success', $key ? 'API guardada: la conexión anda.' : 'API guardada, sin clave: la cotización se carga a mano.');
    }
}
