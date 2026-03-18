<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Gateway extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'public_key',
        'private_key',
        'gateway_type',
        'is_live',
        'webhook_secret'
    ];

    protected $casts = [
        'public_key' => 'encrypted',
        'private_key' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'is_live' => 'boolean',
    ];
}
