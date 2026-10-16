<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    /** Liens réservés : ils ne peuvent pas devenir le lien d'une boutique. */
    public const RESERVED_SLUGS = [
        'admin', 'c', 'connexion', 'inscription', 'deconnexion', 'api', 'up', 'storage',
        'build', 'login', 'register', 'logout', 'aide', 'support', 'contact', 'tarifs',
    ];

    protected $fillable = ['name', 'slug', 'whatsapp_number', 'city', 'description', 'is_open', 'welcome_message'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'next_order_number' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('position');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deliveryMethods(): HasMany
    {
        return $this->hasMany(DeliveryMethod::class)->orderBy('position');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class)->orderBy('position');
    }

    public function dailyVisits(): HasMany
    {
        return $this->hasMany(DailyVisit::class);
    }

    /** Message d'accueil prêt à coller, avec le lien de la boutique à jour. */
    public function welcomeMessageText(): string
    {
        return str_replace('{lien}', route('shop.show', $this).'?src=whatsapp', (string) $this->welcome_message);
    }

    public function cashOnDelivery(): ?PaymentMethod
    {
        return $this->paymentMethods()->where('type', PaymentMethod::CASH_ON_DELIVERY)->first();
    }
}
