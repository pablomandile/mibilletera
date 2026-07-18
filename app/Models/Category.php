<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = [
        'user_id', 'parent_id', 'type', 'name',
        'icon_type', 'icon_value', 'color', 'sort_order', 'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['icon_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * URL pública de la imagen cuando la categoría usa un ícono propio.
     */
    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_type === 'image'
            ? Storage::disk('public')->url($this->icon_value)
            : null;
    }
}
