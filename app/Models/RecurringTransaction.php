<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RecurringTransaction extends Model
{
    protected $fillable = [
        'user_id', 'account_id', 'category_id', 'type', 'kind', 'amount', 'currency_code',
        'exchange_rate', 'note', 'frequency', 'interval', 'next_run_date',
        'end_date', 'last_generated_at', 'is_active', 'anchor_day',
        'installments_total', 'next_installment',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'interval' => 'integer',
        'anchor_day' => 'integer',
        'installments_total' => 'integer',
        'next_installment' => 'integer',
        'next_run_date' => 'date',
        'end_date' => 'date',
        'last_generated_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Las transacciones que generó (y, en fijos y cuotas, el gasto que le dio origen).
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Devuelve la fecha siguiente según la frecuencia e intervalo.
     */
    public function advance(Carbon $date): Carbon
    {
        $n = max(1, (int) $this->interval);

        return match ($this->frequency) {
            'daily' => $date->copy()->addDays($n),
            'weekly' => $date->copy()->addWeeks($n),
            'yearly' => $date->copy()->addYears($n),
            default => $this->monthly($date, $n),
        };
    }

    // Suma meses sin desbordar y, si hay día ancla, vuelve a él cuando el mes lo
    // tiene: 31/01 → 28/02 → 31/03.
    private function monthly(Carbon $date, int $n): Carbon
    {
        $next = $date->copy()->addMonthsNoOverflow($n);

        if ($this->anchor_day) {
            $next->day(min($this->anchor_day, $next->daysInMonth));
        }

        return $next;
    }
}
