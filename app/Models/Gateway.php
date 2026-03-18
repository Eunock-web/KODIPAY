<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Gateway extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'public_key',
        'private_key',
        'gateway_type',
        'is_live'
    ];

   protected $casts = [
        'public_key' => 'encrypted',
        'private_key' => 'encrypted',
        'is_live' => 'boolean'
    ];
}
