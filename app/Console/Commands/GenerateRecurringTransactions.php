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

        $due = RecurringTransaction::where('is_active', true)
            ->whereDate('next_run_date', '<=', $today)
            ->get();

        $created = 0;
        foreach ($due as $rec) {
            $created += $generator->process($rec, $today);
        }

        $this->info("Recurrentes procesadas: {$due->count()}. Transacciones generadas: {$created}.");

        return self::SUCCESS;
    }
}
