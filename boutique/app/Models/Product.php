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

    /** « Plus que N » et alerte de l'Accueil à partir de ce stock. */
    public const LOW_STOCK = 3;

    /** Suppléments payants par produit (ex. flocage), et longueur du texte demandé au client. */
    public const MAX_ADDONS = 10;

    public const ADDON_TEXT_MAX = 60;

    protected $fillable = ['category_id', 'name', 'slug', 'description', 'is_visible', 'track_stock', 'addons', 'position'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'track_stock' => 'boolean', 'addons' => 'array'];
    }

    /**
     * Suppléments du produit, indexés par leur clé (stable quand le commerçant les renomme).
     * text = consigne du texte demandé au client (ex. « Nom et numéro »), ou null.
     *
     * @return \Illuminate\Support\Collection<string, array{key:string, name:string, price:int, text:?string}>
     */
    public function addonList(): \Illuminate\Support\Collection
    {
        return collect($this->addons ?? [])
            ->filter(fn ($a) => is_array($a) && filled($a['key'] ?? null) && filled($a['name'] ?? null))
            ->map(fn ($a) => ['key' => (string) $a['key'], 'name' => (string) $a['name'], 'price' => (int) ($a['price'] ?? 0), 'text' => filled($a['text'] ?? null) ? (string) $a['text'] : null])
            ->keyBy('key');
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
