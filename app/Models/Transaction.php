<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'account_id', 'category_id', 'recurring_transaction_id',
        'installment_number', 'installments_total', 'type', 'amount',
        'currency_code', 'exchange_rate', 'to_account_id', 'transfer_amount',
        'transfer_currency', 'note', 'photo_path', 'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'transfer_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    protected $appends = ['photo_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Serie (gasto fijo, cuotas o recurrente manual) a la que pertenece.
    public function recurring(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_transaction_id');
    }

    // Es parte de un gasto fijo que sigue vigente: el form muestra el tilde marcado.
    public function isFixed(): bool
    {
        $rec = $this->recurring;

        return $rec !== null && $rec->kind === 'fixed' && $rec->is_active;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    // Importe convertido a la moneda base del usuario. Fuera de $appends: es para
    // sumar en PHP, no para serializar.
    public function getBaseAmountAttribute(): float
    {
        return (float) $this->amount * (float) $this->exchange_rate;
    }

    // Fila de los listados (Inicio y Buscar). Espera cargadas category, account, toAccount y recurring.
    public function toListItem(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'currency_code' => $this->currency_code,
            'note' => $this->note,
            'date' => $this->transaction_date->format('Y-m-d'),
            'category' => $this->category ? [
                'name' => $this->category->name,
                'icon_type' => $this->category->icon_type,
                'icon_value' => $this->category->icon_value,
                'icon_url' => $this->category->icon_url,
                'color' => $this->category->color,
            ] : null,
            'from_account' => $this->account?->name,
            'to_account' => $this->toAccount?->name,
            'transfer_amount' => $this->transfer_amount !== null ? (float) $this->transfer_amount : null,
            'transfer_currency' => $this->transfer_currency,
            // Para poder abrir la edición sin conexión, armada desde esta fila.
            'account_id' => $this->account_id,
            'to_account_id' => $this->to_account_id,
            'category_id' => $this->category_id,
            'exchange_rate' => (float) $this->exchange_rate,
            'photo_url' => $this->photo_url,
            'is_fixed' => $this->isFixed(),
            'installment_number' => $this->installment_number,
            'installments_total' => $this->installments_total,
        ];
    }
}
