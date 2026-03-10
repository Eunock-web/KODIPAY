<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Gateway;

class Transaction extends Model
{

    use HasUuids;

    protected $fillable = [
        'gateway_id',
        'amount',
        'currency',
        'status',
        'escrow_duration',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'json',
        'amount' => 'integer',
        'escrow_duration' => 'integer'
    ];

    public function gateway(): BelongsTo{
        return $this->belongsTo(Gateway::class);
    }

    public function scopeHeld($query){
        return $query->where('statuts', 'held');
    }

}
