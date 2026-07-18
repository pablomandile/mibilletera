<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'ARS', 'name' => 'Peso argentino',       'symbol' => '$',   'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'Dólar estadounidense', 'symbol' => 'US$', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro',                 'symbol' => '€',   'decimal_places' => 2],
            ['code' => 'BRL', 'name' => 'Real brasileño',       'symbol' => 'R$',  'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
