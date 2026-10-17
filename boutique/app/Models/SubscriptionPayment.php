<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    public const METHODS = ['orange_money' => 'Orange Money', 'mtn_momo' => 'MTN MoMo', 'cash' => 'Espèces'];

    protected $fillable = ['plan', 'months', 'amount', 'method', 'reference', 'period_start', 'period_end'];

    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'period_end' => 'datetime', 'amount' => 'integer', 'months' => 'integer'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
