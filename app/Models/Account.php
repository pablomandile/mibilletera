<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'user_id', 'name', 'type', 'currency_code',
        'initial_balance', 'color', 'icon', 'is_archived', 'sort_order',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'is_archived' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Saldo actual en la moneda de la cuenta:
     * saldo inicial + ingresos - gastos - transferencias salientes + transferencias entrantes.
     */
    public function currentBalance(): float
    {
        $income = (float) $this->transactions()->where('type', 'income')->sum('amount');
        $expense = (float) $this->transactions()->where('type', 'expense')->sum('amount');
        $transferOut = (float) $this->transactions()->where('type', 'transfer')->sum('amount');
        $transferIn = (float) Transaction::where('to_account_id', $this->id)
            ->where('type', 'transfer')
            ->sum('transfer_amount');

        return (float) $this->initial_balance + $income - $expense - $transferOut + $transferIn;
    }
}
