<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Variant extends Model
{
    protected $fillable = ['name', 'price', 'stock', 'sku', 'position'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'stock' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isAvailable(int $quantity = 1): bool
    {
        return $this->stock === null || $this->stock >= $quantity;
    }
}
