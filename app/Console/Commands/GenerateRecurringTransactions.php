<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateRecurringTransactions extends Command
{
    protected $signature = 'recurring:run';

    protected $description = 'Genera las transacciones recurrentes que estén pendientes hasta hoy.';

    public function handle(): int
    {
        $today = now()->startOfDay();

        $due = RecurringTransaction::where('is_active', true)
            ->whereDate('next_run_date', '<=', $today)
            ->get();

        $created = 0;
        foreach ($due as $rec) {
            $created += $this->process($rec, $today);
        }

        $this->info("Recurrentes procesadas: {$due->count()}. Transacciones generadas: {$created}.");

        return self::SUCCESS;
    }

    private function process(RecurringTransaction $rec, Carbon $today): int
    {
        $count = 0;
        $guard = 0;
        $next = $rec->next_run_date->copy();

        while (
            $next->lte($today)
            && (! $rec->end_date || $next->lte($rec->end_date))
            && $guard < 366
        ) {
            Transaction::create([
                'user_id' => $rec->user_id,
                'account_id' => $rec->account_id,
                'category_id' => $rec->category_id,
                'type' => $rec->type,
                'amount' => $rec->amount,
                'currency_code' => $rec->currency_code,
                'exchange_rate' => $rec->exchange_rate,
                'note' => $rec->note,
                'transaction_date' => $next->copy(),
            ]);

            $rec->last_generated_at = $next->copy();
            $next = $rec->advance($next);
            $count++;
            $guard++;
        }

        $rec->next_run_date = $next;
        if ($rec->end_date && $next->gt($rec->end_date)) {
            $rec->is_active = false;
        }
        $rec->save();

        return $count;
    }
}
