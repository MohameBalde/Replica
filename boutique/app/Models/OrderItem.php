<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['product_id', 'variant_id', 'product_name', 'variant_name', 'unit_price', 'quantity', 'line_total'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'quantity' => 'integer', 'line_total' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /** "Baskets (42 / Noir)" ou "Baskets" pour la variante Standard. */
    public function label(): string
    {
        return $this->variant_name === 'Standard'
            ? $this->product_name
            : $this->product_name.' ('.$this->variant_name.')';
    }
}
