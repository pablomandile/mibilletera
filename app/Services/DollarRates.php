<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cotización del dólar por tipo y fecha, desde la API de bambudev. La dirección
 * y la clave las carga un admin en Ajustes (si no, las del .env:
 * config/services.php → bambudev). Devuelve la venta o null, nunca una
 * excepción: sin cotización, el movimiento se carga a mano como siempre.
 */
class DollarRates
{
    // El registro de la API arranca este día: antes, siempre 404.
    public const FIRST_DAY = '2026-11-13';

    // Los tipos que informa la API (por si no responde al armar la lista de Ajustes).
    public const KNOWN_TYPES = [
        'oficial' => 'Oficial',
        'tarjeta' => 'Tarjeta',
        'mayorista' => 'Mayorista',
        'blue' => 'Blue',
        'blue_ambito' => 'Blue (Ámbito)',
        'mep' => 'MEP',
        'ccl' => 'Contado con liquidación',
        'cripto' => 'Cripto',
    ];

    public const DEFAULT_EXPENSE = 'tarjeta';

    public const DEFAULT_INCOME = 'blue';

    public const SETTING_URL = 'dollar_api_url';

    public const SETTING_KEY = 'dollar_api_key';

    public function url(): string
    {
        return rtrim(AppSetting::read(self::SETTING_URL) ?: (string) config('services.bambudev.url'), '/');
    }

    public function key(): ?string
    {
        return AppSetting::readSecret(self::SETTING_KEY) ?: config('services.bambudev.key');
    }

    /**
     * El dólar que usa un usuario para un gasto o un ingreso (lo elige en Ajustes).
     */
    public static function typeFor(string $transactionType, ?User $user = null): string
    {
        return $transactionType === 'income'
            ? ($user?->dollar_type_income ?: self::DEFAULT_INCOME)
            : ($user?->dollar_type_expense ?: self::DEFAULT_EXPENSE);
    }

    /**
     * @return array{venta: float, fecha: string, nombre: string}|null
     */
    public function rate(string $type, Carbon|string $date): ?array
    {
        $key = $this->key();
        $day = Carbon::parse($date)->startOfDay();
        $today = today();

        // Las repeticiones de un gasto fijo se generan hasta fin de mes: para una
        // fecha que todavía no llegó, la cotización de hoy.
        if ($day->gt($today)) {
            $day = $today->copy();
        }
        $fecha = $day->toDateString();

        if (! $key || ! preg_match('/^[a-z_]{1,30}$/', $type) || $fecha < self::FIRST_DAY) {
            return null;
        }

        return $this->remember("$type:$fecha", $fecha === $today->toDateString() ? 900 : 86400, function () use ($type, $fecha, $key) {
            $response = $this->client($key)->get("dolares/$type/$fecha");
            $venta = $response->successful() ? $response->json('venta') : null;

            return is_numeric($venta) && $venta > 0
                ? [
                    'venta' => (float) $venta,
                    'fecha' => (string) ($response->json('fecha') ?? $fecha),
                    'nombre' => (string) ($response->json('nombre') ?? self::KNOWN_TYPES[$type] ?? $type),
                ]
                : null;
        });
    }

    /**
     * Todos los tipos que informa la API, con su venta de hoy, para elegir el de
     * cada usuario en Ajustes. Sin API, null.
     *
     * @return list<array{tipo: string, nombre: string, venta: ?float}>|null
     */
    public function types(): ?array
    {
        $key = $this->key();
        if (! $key) {
            return null;
        }

        return $this->remember('tipos', 900, function () use ($key) {
            $response = $this->client($key)->get('dolares');
            $rows = $response->successful() ? $response->json('cotizaciones') : null;
            if (! is_array($rows)) {
                return null;
            }

            $types = [];
            foreach ($rows as $row) {
                if (is_array($row) && is_string($row['tipo'] ?? null) && preg_match('/^[a-z_]{1,30}$/', $row['tipo'])) {
                    $types[] = [
                        'tipo' => $row['tipo'],
                        'nombre' => (string) ($row['nombre'] ?? self::KNOWN_TYPES[$row['tipo']] ?? $row['tipo']),
                        'venta' => is_numeric($row['venta'] ?? null) ? (float) $row['venta'] : null,
                    ];
                }
            }

            return $types ?: null;
        });
    }

    /**
     * Prueba una dirección y una clave antes de guardarlas: null si anda, o el
     * motivo si no.
     */
    public function check(string $url, string $key): ?string
    {
        try {
            $response = Http::baseUrl(rtrim($url, '/'))->withHeaders(['X-Api-Key' => $key])
                ->timeout(5)->acceptJson()->get('dolares');
        } catch (\Throwable) {
            return 'No se pudo conectar con la API.';
        }

        return match (true) {
            $response->status() === 401 => 'La API rechazó la clave.',
            ! $response->successful() => 'La API respondió con un error ('.$response->status().').',
            ! is_array($response->json('cotizaciones')) => 'La dirección no parece ser la API de cotizaciones.',
            default => null,
        };
    }

    private function client(string $key)
    {
        return Http::baseUrl($this->url())->withHeaders(['X-Api-Key' => $key])->timeout(3)->acceptJson();
    }

    /**
     * Cachea la respuesta. La clave lleva la dirección de la API, así cambiarla
     * en Ajustes no sirve cotizaciones de la anterior. Una falla se recuerda un
     * minuto, para no gastar el tope de 60 consultas por minuto reintentando.
     */
    private function remember(string $suffix, int $ttl, \Closure $fetch): mixed
    {
        $cacheKey = 'dolar:'.md5($this->url()).':'.$suffix;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey) ?: null;
        }

        try {
            $value = $fetch();
        } catch (\Throwable) {
            $value = null;
        }
        Cache::put($cacheKey, $value ?? false, $value === null ? 60 : $ttl);

        return $value;
    }
}
