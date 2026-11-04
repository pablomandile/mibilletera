<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Genera las transacciones pendientes de una recurrente, con catch-up. La usan
 * el comando diario y el alta/edición de un gasto fijo o en cuotas.
 *
 * Hasta dónde: las recurrentes manuales, hasta hoy. Los gastos fijos y las
 * cuotas, hasta fin del mes en curso: la del mes aparece apenas se cargan (y el
 * 1° de cada mes la de ese mes), aunque su día todavía no haya llegado.
 */
class RecurringGenerator
{
    public function process(RecurringTransaction $rec, ?Carbon $today = null): int
    {
        $until = self::horizon($rec, $today);
        $count = 0;
        $next = $rec->next_run_date->copy();

        while (
            $rec->is_active
            && $next->lte($until)
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
                // Una repetición de gasto fijo arranca impaga hasta que se marque.
                'is_paid' => $rec->kind !== 'fixed',
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

    public static function horizon(RecurringTransaction $rec, ?Carbon $today = null): Carbon
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return $rec->kind === 'manual' ? $today : $today->endOfMonth()->startOfDay();
    }
}
