<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RecurringTransaction extends Model
{
    protected $fillable = [
        'user_id', 'account_id', 'category_id', 'type', 'amount', 'currency_code',
        'exchange_rate', 'note', 'frequency', 'interval', 'next_run_date',
        'end_date', 'last_generated_at', 'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'interval' => 'integer',
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
            default => $date->copy()->addMonthsNoOverflow($n), // monthly
        };
    }
}
