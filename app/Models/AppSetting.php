<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Ajustes de toda la app (no de un usuario), clave → valor. Hoy: la API de
 * cotización del dólar. Los secretos se guardan cifrados con la APP_KEY.
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function read(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('app_settings', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function write(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            static::query()->whereKey($key)->delete();
        } else {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('app_settings');
    }

    public static function readSecret(string $key): ?string
    {
        $value = static::read($key);
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null; // cifrado con otra APP_KEY: como si no estuviera
        }
    }

    public static function writeSecret(string $key, ?string $value): void
    {
        static::write($key, $value === null || $value === '' ? null : Crypt::encryptString($value));
    }
}
