<?php

namespace App\Models;

use App\Support\Plans;
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
        return ['is_open' => 'boolean', 'next_order_number' => 'integer', 'plan_expires_at' => 'datetime'];
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

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->latest();
    }

    /** Formule en vigueur : une formule payante expirée redevient Découverte, sans rien supprimer. */
    public function activePlan(): string
    {
        if ($this->plan !== Plans::FREE && $this->plan_expires_at && $this->plan_expires_at->isFuture()) {
            return $this->plan;
        }

        return Plans::FREE;
    }

    public function maxProducts(): ?int
    {
        return Plans::get($this->activePlan())['max_products'];
    }

    public function canAddProduct(): bool
    {
        $max = $this->maxProducts();

        return $max === null || $this->products()->count() < $max;
    }

    public function showsBadge(): bool
    {
        return Plans::get($this->activePlan())['badge'];
    }

    /** Jours restants avant la fin d'une formule payante (null si Découverte). */
    public function planDaysLeft(): ?int
    {
        return $this->activePlan() === Plans::FREE ? null : (int) ceil(now()->diffInHours($this->plan_expires_at) / 24);
    }

    /**
     * Enregistre un paiement d'abonnement et repousse la date de fin.
     * Même formule encore active : on ajoute à la suite. Sinon : on part d'aujourd'hui.
     */
    public function recordSubscription(string $plan, int $months, int $amount, string $method, ?string $reference = null): SubscriptionPayment
    {
        $start = ($this->activePlan() === $plan) ? $this->plan_expires_at : now();
        $end = $start->copy()->addMonthsNoOverflow($months);

        $payment = $this->subscriptionPayments()->create([
            'plan' => $plan, 'months' => $months, 'amount' => $amount, 'method' => $method,
            'reference' => $reference, 'period_start' => $start, 'period_end' => $end,
        ]);
        $this->forceFill(['plan' => $plan, 'plan_expires_at' => $end])->save();

        return $payment;
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
