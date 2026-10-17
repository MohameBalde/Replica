<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryMethod extends Model
{
    public const PICKUP = 'pickup';

    public const DELIVERY = 'delivery';

    protected $fillable = ['name', 'type', 'fee', 'min_order', 'allows_cash_on_delivery', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'fee' => 'integer',
            'min_order' => 'integer',
            'allows_cash_on_delivery' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function needsAddress(): bool
    {
        return $this->type === self::DELIVERY;
    }
}
