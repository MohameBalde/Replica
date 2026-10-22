<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['product_id', 'variant_id', 'product_name', 'variant_name', 'options', 'unit_price', 'quantity', 'line_total'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'quantity' => 'integer', 'line_total' => 'integer', 'options' => 'array'];
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

    /** Suppléments choisis : « Flocage « DIALLO 10 » (+50 000 GNF) · Emballage cadeau », ou ''. Prix unitaires. */
    public function optionsLabel(): string
    {
        return self::describeOptions($this->options ?? []);
    }

    /** @param  list<array{name:string, price:int, text?:?string}>  $options */
    public static function describeOptions(array $options): string
    {
        return collect($options)->map(fn ($o) => $o['name']
            .(filled($o['text'] ?? null) ? ' « '.$o['text'].' »' : '')
            .((int) $o['price'] > 0 ? ' (+'.gnf((int) $o['price']).')' : ''))->implode(' · ');
    }
}
