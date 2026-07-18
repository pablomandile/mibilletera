<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['code', 'name', 'symbol', 'decimal_places'];

    protected $casts = [
        'decimal_places' => 'integer',
    ];
}
