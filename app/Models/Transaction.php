<?php

namespace App\Models;

use App\Models\Gateway;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'gateway_id',
        'amount',
        'currency',
        'status',
        'escrow_duration',
        'metadata',
        'callback_url',
        'transaction_type'
    ];

    protected $casts = [
        'metadata' => 'json',
        'amount' => 'integer',
        'escrow_duration' => 'integer'
    ];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function scopeHeld($query)
    {
        return $query->where('status', 'held');
    }
}
