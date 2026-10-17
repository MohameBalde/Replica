<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'slug', 'description', 'is_visible', 'track_stock', 'position'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'track_stock' => 'boolean'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class)->orderBy('position');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function firstImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('position');
    }

    /** Prix affiché sur la carte : le moins cher des variantes. */
    public function fromPrice(): int
    {
        return (int) $this->variants->min('price');
    }

    /** Stock total, ou null si le produit ne suit pas le stock. */
    public function totalStock(): ?int
    {
        return $this->track_stock ? (int) $this->variants->sum('stock') : null;
    }

    public function isSoldOut(): bool
    {
        return $this->track_stock && $this->totalStock() <= 0;
    }
}
