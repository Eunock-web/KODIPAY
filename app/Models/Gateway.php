<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Gateway extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'settings',
        'gateway_type',
        'api_key',
        'is_live'
    ];

   protected $casts = [
        'api_key' => 'encrypted',
        'settings' =>'json',
        'is_live' => 'boolean'
    ];
}
