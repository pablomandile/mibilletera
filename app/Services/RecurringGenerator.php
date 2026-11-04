<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Genera las transacciones pendientes de una recurrente hasta una fecha, con
 * catch-up. La usan el comando diario y el alta de un gasto fijo o en cuotas
 * (para que uno cargado con fecha vieja se ponga al día en el acto).
 */
class RecurringGenerator
{
    public function process(RecurringTransaction $rec, ?Carbon $today = null): int
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $count = 0;
        $next = $rec->next_run_date->copy();

        while (
            $rec->is_active
            && $next->lte($today)
            && (! $rec->end_date || $next->lte($rec->end_date))
            && $count < 366
        ) {
            $installment = $rec->kind === 'installments' ? (int) $rec->next_installment : null;

            Transaction::create([
                'user_id' => $rec->user_id,
                'account_id' => $rec->account_id,
                'category_id' => $rec->category_id,
                'recurring_transaction_id' => $rec->id,
                'installment_number' => $installment,
                'installments_total' => $installment ? $rec->installments_total : null,
                'type' => $rec->type,
                'amount' => $rec->amount,
                'currency_code' => $rec->currency_code,
                'exchange_rate' => $rec->exchange_rate,
                'note' => $rec->note,
                'transaction_date' => $next->copy(),
            ]);

            if ($installment) {
                $rec->next_installment = $installment + 1;
            }
            $rec->last_generated_at = $next->copy();
            $next = $rec->advance($next);
            $count++;
        }

        $rec->next_run_date = $next;
        if ($rec->end_date && $next->gt($rec->end_date)) {
            $rec->is_active = false;
        }
        $rec->save();

        return $count;
    }
}
