<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * La inflación del último mes que publicó el INDEC (IPC nacional, nivel
 * general), para comparar el rendimiento de una cuenta. La serie sale de la API
 * pública de CalculAR (config/services.php → calcular), que guarda el número
 * índice: la inflación del mes es el cociente entre los dos últimos.
 * Devuelve null, nunca una excepción: sin dato, la comparación no se muestra.
 */
class InflacionMensual
{
    public function url(): string
    {
        return rtrim((string) config('services.calcular.url'), '/');
    }

    /**
     * @return array{mes: string, valor: float}|null mes `AAAA-MM`; valor como fracción (0,027 = 2,7 %)
     */
    public function ultima(): ?array
    {
        $cacheKey = 'inflacion:'.md5($this->url());
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey) ?: null;
        }

        try {
            $value = $this->fetch();
        } catch (\Throwable) {
            $value = null;
        }
        // Una falla se recuerda una hora, para no esperar el timeout en cada visita.
        Cache::put($cacheKey, $value ?? false, $value === null ? 3600 : 12 * 3600);

        return $value;
    }

    /**
     * @return array{mes: string, valor: float}|null
     */
    private function fetch(): ?array
    {
        $response = Http::timeout(5)->acceptJson()->get($this->url().'/api/indices/ipc');
        $valores = $response->successful() ? $response->json('valores') : null;
        if (! is_array($valores) || count($valores) < 2) {
            return null;
        }

        [$anterior, $ultimo] = array_slice(array_values($valores), -2);
        if (! $this->esPunto($anterior) || ! $this->esPunto($ultimo)) {
            return null;
        }

        $mesUltimo = Carbon::parse($ultimo[0])->startOfMonth();
        $mesAnterior = Carbon::parse($anterior[0])->startOfMonth();
        if (! $mesAnterior->copy()->addMonth()->equalTo($mesUltimo)) {
            return null;
        }

        return [
            'mes' => $mesUltimo->format('Y-m'),
            'valor' => (float) $ultimo[1] / (float) $anterior[1] - 1,
        ];
    }

    private function esPunto(mixed $punto): bool
    {
        return is_array($punto)
            && is_string($punto[0] ?? null)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $punto[0])
            && is_numeric($punto[1] ?? null)
            && (float) $punto[1] > 0;
    }
}
