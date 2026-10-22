<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * El usuario de la demo con un año de movimientos inventados pero coherentes:
 * sueldo, alquiler, supermercado, servicios, salidas, vacaciones, compra de
 * dólares… con inflación mensual, así los gráficos e informes muestran algo.
 *
 * La semilla es fija (cada reset da lo mismo) y las fechas son relativas a hoy:
 * los últimos 12 meses completos más el mes en curso hasta hoy.
 */
class DemoSeeder extends Seeder
{
    private const INFLATION = 0.025;      // por mes

    private const USD_RATE_NOW = 1450.0;  // pesos por dólar hoy

    private const USD_DRIFT = 0.015;      // suba mensual del dólar

    private User $user;

    private Carbon $today;

    /** @var array<string, int> */
    private array $expense = [];

    /** @var array<string, int> */
    private array $income = [];

    private Account $cash;

    private Account $bank;

    private Account $card;

    private Account $usd;

    private array $rows = [];

    public function run(): void
    {
        mt_srand(2026);
        $this->today = now()->startOfDay();

        // El UserObserver le crea la cuenta Efectivo y las categorías por defecto.
        $this->user = User::forceCreate([
            'name' => 'Usuario Demo',
            'email' => config('demo.email'),
            'password' => config('demo.password'),
            'email_verified_at' => now(),
            'default_currency' => 'ARS',
            'terminos_version' => config('legales.version'),
            'terminos_aceptados_en' => now(),
        ]);

        $categories = $this->user->categories()->get(['id', 'name', 'type']);
        $this->expense = $categories->where('type', 'expense')->pluck('id', 'name')->all();
        $this->income = $categories->where('type', 'income')->pluck('id', 'name')->all();

        $this->accounts();
        $this->movements();
        $this->supercategories();
        $this->budgets();
        $this->recurring();
        $this->reminder();
    }

    private function accounts(): void
    {
        $this->cash = $this->user->accounts()->first();
        $this->cash->update(['initial_balance' => 60000]);

        $this->bank = $this->user->accounts()->create([
            'name' => 'Cuenta sueldo', 'type' => 'bank', 'currency_code' => 'ARS',
            'initial_balance' => 850000, 'color' => '#3b82f6', 'icon' => 'building-bank', 'sort_order' => 1,
        ]);
        $this->card = $this->user->accounts()->create([
            'name' => 'Tarjeta de crédito', 'type' => 'card', 'currency_code' => 'ARS',
            'initial_balance' => 0, 'color' => '#a855f7', 'icon' => 'credit-card', 'sort_order' => 2,
        ]);
        $this->usd = $this->user->accounts()->create([
            'name' => 'Ahorro en dólares', 'type' => 'bank', 'currency_code' => 'USD',
            'initial_balance' => 1500, 'color' => '#10b981', 'icon' => 'pig-money', 'sort_order' => 3,
        ]);
    }

    private function movements(): void
    {
        $first = $this->today->copy()->startOfMonth()->subMonths(12);
        $cardDebt = 0.0; // gastos con tarjeta del mes anterior, que se pagan este mes

        for ($i = 0; $i <= 12; $i++) {
            $month = $first->copy()->addMonths($i);
            $f = (1 + self::INFLATION) ** ($i - 12);       // precios de ese mes (hoy = 1)
            $rate = self::USD_RATE_NOW * (1 + self::USD_DRIFT) ** ($i - 12);
            $name = $month->translatedFormat('F');
            $n = (int) $month->format('n');
            $cardBefore = $this->cardTotal();

            // ---- Ingresos ----
            $this->income('Sueldo', $this->bank, 2400000 * $f, $month, mt_rand(1, 4), "Sueldo de $name", jitter: 0);
            if ($n === 6 || $n === 12) {
                $this->income('Bonos', $this->bank, 1200000 * $f, $month, 18, 'Aguinaldo', jitter: 0);
            }
            if (mt_rand(1, 3) === 1) {
                $this->income('Ventas', $this->bank, mt_rand(250, 450) * 1000 * $f, $month, mt_rand(10, 25), 'Trabajo freelance');
            }
            $this->income('Intereses', $this->bank, 18000 * $f, $month, 28, 'Rendimiento de la cuenta');

            // ---- Fijos ----
            $this->expense('Vivienda', $this->bank, 650000 * $f, $month, 5, "Alquiler de $name", jitter: 0);
            $this->expense('Salud', $this->bank, 220000 * $f, $month, 3, 'Prepaga', jitter: 0);
            $this->expense('Hogar', $this->bank, mt_rand(35, 55) * 1000 * $f, $month, 12, 'Luz');
            $winter = in_array($n, [6, 7, 8], true) ? 2.2 : 1.0;
            $this->expense('Hogar', $this->bank, mt_rand(18, 26) * 1000 * $winter * $f, $month, 15, 'Gas');
            $this->expense('Hogar', $this->card, 28000 * $f, $month, 10, 'Internet', jitter: 0);
            $this->expense('Teléfono', $this->card, 30000 * $f, $month, 8, 'Plan del celular', jitter: 0);
            $this->expense('Auto', $this->bank, 85000 * $f, $month, 10, 'Seguro del auto', jitter: 0);
            $this->expense('Deportes', $this->card, 50000 * $f, $month, 2, 'Gimnasio', jitter: 0);
            $this->expense('Entretenimiento', $this->card, 12000 * $f, $month, 6, 'Netflix', jitter: 0);
            $this->expense('Entretenimiento', $this->card, 9000 * $f, $month, 14, 'Spotify', jitter: 0);
            $this->expense('Mascotas', $this->card, 38000 * $f, $month, 20, 'Alimento del perro');

            // ---- Del día a día ----
            foreach ([2, 9, 16, 23, 30] as $day) {
                $this->expense('Alimentos', $this->card, mt_rand(90, 140) * 1000 * $f, $month, $day, 'Supermercado');
            }
            foreach ([4, 11, 18, 25] as $day) {
                $this->expense('Verduras', $this->cash, mt_rand(14, 24) * 1000 * $f, $month, $day, 'Verdulería');
                $this->expense('Frutas', $this->cash, mt_rand(7, 13) * 1000 * $f, $month, $day, 'Verdulería');
            }
            foreach ([7, 21] as $day) {
                $this->expense('Transporte', $this->cash, 15000 * $f, $month, $day, 'Carga SUBE');
                $this->expense('Auto', $this->card, mt_rand(55, 70) * 1000 * $f, $month, $day + 1, 'Nafta');
            }
            for ($k = 0; $k < 3; $k++) {
                $this->expense(mt_rand(0, 1) ? 'Bebidas' : 'Snacks', $this->cash, mt_rand(5, 14) * 1000 * $f, $month, mt_rand(1, 28), 'Kiosco');
                $this->expense('Social', mt_rand(0, 1) ? $this->cash : $this->card, mt_rand(25, 45) * 1000 * $f, $month, mt_rand(1, 28),
                    ['Cena con amigos', 'Salida', 'Asado', 'Cumpleaños de un amigo'][mt_rand(0, 3)]);
            }
            $this->expense('Entretenimiento', $this->card, mt_rand(25, 35) * 1000 * $f, $month, mt_rand(15, 28), 'Cine');
            $this->expense('Salud', $this->cash, mt_rand(15, 35) * 1000 * $f, $month, mt_rand(1, 28), 'Farmacia');

            // ---- Ocasionales ----
            if (mt_rand(1, 2) === 1) {
                $this->expense('Ropa', $this->card, mt_rand(60, 180) * 1000 * $f, $month, mt_rand(5, 25), 'Ropa');
            }
            if ($i % 4 === 1) {
                $this->expense('Mascotas', $this->cash, 45000 * $f, $month, mt_rand(5, 25), 'Veterinaria');
            }
            if ($n === 12) {
                foreach ([12, 18, 22] as $day) {
                    $this->expense('Regalos', $this->card, mt_rand(60, 140) * 1000 * $f, $month, $day, 'Regalos de Navidad');
                }
            }
            if ($n === 1) { // vacaciones: el mes queda en rojo
                $this->expense('Viajes', $this->card, 620000 * $f, $month, 8, 'Pasajes a Bariloche');
                $this->expense('Viajes', $this->bank, 540000 * $f, $month, 15, 'Alojamiento');
                $this->expense('Viajes', $this->usd, 300, $month, 18, 'Excursiones (USD)', rate: $rate);
            }
            if ($i === 4) { // un imprevisto: otro mes en rojo
                $this->expense('Reparaciones', $this->bank, 780000 * $f, $month, 17, 'Service y frenos del auto');
            }

            // ---- Transferencias ----
            $this->transfer($this->bank, $this->usd, 150 * $rate, 150, $month, 6, 'Compra de dólares');
            foreach ([3, 17] as $day) { // de acá sale lo que se gasta en efectivo
                $this->transfer($this->bank, $this->cash, 140000 * $f, 140000 * $f, $month, $day, 'Extracción del cajero');
            }
            if ($cardDebt > 0) {
                $this->transfer($this->bank, $this->card, $cardDebt, $cardDebt, $month, 7, 'Pago de la tarjeta');
            }
            $cardDebt = round($this->cardTotal() - $cardBefore, 2);
        }

        $now = now();
        foreach (array_chunk($this->rows, 200) as $chunk) {
            Transaction::insert(array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $chunk));
        }
    }

    private function supercategories(): void
    {
        $groups = [
            ['Comida', '#f97316', ['Alimentos', 'Verduras', 'Frutas', 'Bebidas', 'Snacks']],
            ['Casa', '#3b82f6', ['Vivienda', 'Hogar', 'Teléfono']],
            ['Movilidad', '#eab308', ['Transporte', 'Auto', 'Reparaciones']],
            ['Ocio', '#a855f7', ['Entretenimiento', 'Social', 'Viajes', 'Deportes']],
            ['Salud y cuidado', '#14b8a6', ['Salud', 'Belleza']],
        ];

        foreach ($groups as $order => [$name, $color, $cats]) {
            $super = $this->user->supercategories()->create(['name' => $name, 'color' => $color, 'sort_order' => $order]);
            $this->user->categories()->whereIn('name', $cats)->where('type', 'expense')
                ->update(['supercategory_id' => $super->id]);
        }
    }

    private function budgets(): void
    {
        $budgets = [
            null => 2700000,
            'Alimentos' => 520000,
            'Social' => 100000,
            'Entretenimiento' => 70000,
            'Auto' => 220000,
            'Salud' => 280000,
        ];

        foreach ($budgets as $category => $amount) {
            $this->user->budgets()->create([
                'category_id' => $category === '' ? null : $this->expense[$category],
                'amount' => $amount,
                'currency_code' => 'ARS',
                'period' => 'monthly',
            ]);
        }
    }

    private function recurring(): void
    {
        // Próxima vez que toca: este mes si ese día todavía no llegó (el seeder no
        // carga nada después de hoy), si no el mes que viene.
        $next = function (int $day) {
            $date = $this->today->copy()->day($day);

            return $date->gt($this->today) ? $date : $this->today->copy()->startOfMonth()->addMonth()->day($day);
        };

        $items = [
            ['income', 'Sueldo', $this->bank, 2400000, 'Sueldo', 1],
            ['expense', 'Vivienda', $this->bank, 650000, 'Alquiler', 5],
            ['expense', 'Salud', $this->bank, 220000, 'Prepaga', 3],
            ['expense', 'Deportes', $this->card, 50000, 'Gimnasio', 2],
            ['expense', 'Entretenimiento', $this->card, 12000, 'Netflix', 6],
        ];

        foreach ($items as [$type, $category, $account, $amount, $note, $day]) {
            $this->user->recurringTransactions()->create([
                'account_id' => $account->id,
                'category_id' => $type === 'income' ? $this->income[$category] : $this->expense[$category],
                'type' => $type,
                'amount' => $amount,
                'currency_code' => 'ARS',
                'exchange_rate' => 1,
                'note' => $note,
                'frequency' => 'monthly',
                'interval' => 1,
                'next_run_date' => $next($day)->toDateString(),
                'is_active' => true,
            ]);
        }
    }

    private function reminder(): void
    {
        $reminder = $this->user->reminders()->create([
            'title' => 'Cargar los gastos del día',
            'time' => '21:00',
            'days' => [],
            'enabled' => true,
        ]);

        $alert = Alert::create([
            'user_id' => $this->user->id,
            'reminder_id' => $reminder->id,
            'title' => $reminder->title,
            'body' => 'Es hora de registrar tus movimientos.',
        ]);
        $alert->forceFill(['created_at' => now()->subHours(3)])->save();
    }

    // ---- Ayudantes ----

    private function expense(string $category, Account $account, float $amount, Carbon $month, int $day, string $note, int $jitter = 10, float $rate = 1.0): void
    {
        $this->add('expense', $this->expense[$category], $account, $amount, $month, $day, $note, $jitter, $rate);
    }

    private function income(string $category, Account $account, float $amount, Carbon $month, int $day, string $note, int $jitter = 10): void
    {
        $this->add('income', $this->income[$category], $account, $amount, $month, $day, $note, $jitter, 1.0);
    }

    private function add(string $type, int $categoryId, Account $account, float $amount, Carbon $month, int $day, string $note, int $jitter, float $rate): void
    {
        $date = $this->dateIn($month, $day);
        if (! $date) {
            return;
        }

        $amount *= 1 + mt_rand(-$jitter, $jitter) / 100;

        $this->rows[] = [
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $categoryId,
            'type' => $type,
            'amount' => $this->round($amount),
            'currency_code' => $account->currency_code,
            'exchange_rate' => $rate,
            'to_account_id' => null,
            'transfer_amount' => null,
            'transfer_currency' => null,
            'note' => $note,
            'photo_path' => null,
            'transaction_date' => $date,
        ];
    }

    private function transfer(Account $from, Account $to, float $amount, float $toAmount, Carbon $month, int $day, string $note): void
    {
        $date = $this->dateIn($month, $day);
        if (! $date) {
            return;
        }

        $this->rows[] = [
            'user_id' => $this->user->id,
            'account_id' => $from->id,
            'category_id' => null,
            'type' => 'transfer',
            'amount' => $this->round($amount),
            'currency_code' => $from->currency_code,
            'exchange_rate' => 1,
            'to_account_id' => $to->id,
            'transfer_amount' => $to->currency_code === 'USD' ? $toAmount : $this->round($toAmount),
            'transfer_currency' => $to->currency_code,
            'note' => $note,
            'photo_path' => null,
            'transaction_date' => $date,
        ];
    }

    // El día pedido dentro del mes (los meses cortos lo recortan). Nada después de hoy.
    private function dateIn(Carbon $month, int $day): ?string
    {
        $date = $month->copy()->day(min($day, $month->daysInMonth));

        return $date->gt($this->today) ? null : $date->format('Y-m-d 12:00:00');
    }

    // Montos "de ticket": a la centena en pesos, con centavos en dólares.
    private function round(float $amount): float
    {
        return $amount >= 1000 ? round($amount, -2) : round($amount, 2);
    }

    // Gastos con tarjeta cargados hasta ahora (para el pago del mes siguiente).
    private function cardTotal(): float
    {
        return array_sum(array_map(
            fn ($r) => $r['type'] === 'expense' && $r['account_id'] === $this->card->id ? $r['amount'] : 0,
            $this->rows
        ));
    }
}
