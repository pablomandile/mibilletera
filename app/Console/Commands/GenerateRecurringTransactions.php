<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Services\RecurringGenerator;
use Illuminate\Console\Command;

class GenerateRecurringTransactions extends Command
{
    protected $signature = 'recurring:run';

    protected $description = 'Genera las transacciones recurrentes que estén pendientes hasta hoy.';

    public function handle(RecurringGenerator $generator): int
    {
        $today = now()->startOfDay();

        // Fijos y cuotas se generan hasta fin de mes; las manuales, hasta hoy
        // (el generador decide el tope de cada una).
        $due = RecurringTransaction::where('is_active', true)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('kind', 'manual')->whereDate('next_run_date', '<=', $today))
                ->orWhere(fn ($q) => $q->where('kind', '!=', 'manual')->whereDate('next_run_date', '<=', $today->copy()->endOfMonth())))
            ->get();

        $created = 0;
        foreach ($due as $rec) {
            $created += $generator->process($rec, $today);
        }

        $this->info("Recurrentes procesadas: {$due->count()}. Transacciones generadas: {$created}.");

        return self::SUCCESS;
    }
}
