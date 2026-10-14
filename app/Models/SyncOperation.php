<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncOperation extends Model
{
    protected $fillable = ['user_id', 'op_id', 'entity', 'action', 'temp_id', 'real_id'];
}
