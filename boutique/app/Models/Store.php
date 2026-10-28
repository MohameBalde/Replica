<?php

namespace App\Models;

use App\Support\OpeningHours;
use App\Support\Plans;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Store extends Model
{
    use HasFactory;

    /** Liens réservés : ils ne peuvent pas devenir le lien d'une boutique. */
    public const RESERVED_SLUGS = [
        'admin', 'c', 'connexion', 'inscription', 'deconnexion', 'api', 'up', 'storage',
        'build', 'login', 'register', 'logout', 'aide', 'support', 'contact', 'tarifs',
        'confidentialite', 'conditions', 'mentions-legales', 'cgu', 'gestion',
        'mot-de-passe-oublie', 'nouveau-mot-de-passe',
    ];

    /** Espace Gestion : on relance de 7 jours avant la fin jusqu'à 30 jours après. */
    public const REMIND_BEFORE_DAYS = 7;

    public const REMIND_AFTER_DAYS = 30;

    protected $fillable = ['name', 'slug', 'whatsapp_number', 'city', 'description', 'is_open', 'welcome_message',
        'checkout_questions', 'opening_hours', 'closed_outside_hours'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'next_order_number' => 'integer', 'plan_expires_at' => 'datetime', 'suspended_at' => 'datetime',
            'checkout_questions' => 'array', 'opening_hours' => 'array', 'closed_outside_hours' => 'boolean'];
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

    public function openingHours(): OpeningHours
    {
        return new OpeningHours($this->opening_hours);
    }

    /** Ouverte (interrupteur) et, si le commerçant l'a choisi, dans ses horaires. */
    public function acceptsOrdersNow(): bool
    {
        return $this->is_open && (! $this->closed_outside_hours || $this->openingHours()->isOpenAt(CarbonImmutable::now()));
    }

    /** Pourquoi on ne peut pas commander maintenant (null si on peut) : vitrine, panier et commande disent la même chose. */
    public function closedNotice(): ?string
    {
        if ($this->acceptsOrdersNow()) {
            return null;
        }
        $next = $this->is_open ? ($this->openingHours()->status(CarbonImmutable::now())['next'] ?? null) : null;

        return $next
            ? 'La boutique est fermée : elle ouvre '.$next.'. Revenez à ce moment-là pour commander.'
            : 'La boutique est fermée pour le moment.';
    }

    /** @return list<array{label: string, required: bool}> */
    public function checkoutQuestions(): array
    {
        return array_values($this->checkout_questions ?? []);
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
        $reference = mb_strtoupper(trim((string) $reference)) ?: null; // même écriture partout, pour repérer les doublons

        $payment = $this->subscriptionPayments()->create([
            'plan' => $plan, 'months' => $months, 'amount' => $amount, 'method' => $method,
            'reference' => $reference, 'period_start' => $start, 'period_end' => $end,
        ]);
        $this->forceFill(['plan' => $plan, 'plan_expires_at' => $end])->save();

        return $payment;
    }

    /**
     * Annule le dernier paiement (erreur de saisie) : la boutique revient à
     * l'état d'avant, c'est-à-dire la fin du paiement précédent, ou Découverte.
     */
    public function cancelLatestSubscription(): ?SubscriptionPayment
    {
        return DB::transaction(function () {
            $payments = $this->subscriptionPayments()->reorder()->orderByDesc('id')->limit(2)->get();
            $latest = $payments->first();
            if (! $latest) {
                return null;
            }

            $previous = $payments->get(1);
            $latest->delete();
            $this->forceFill([
                'plan' => $previous?->plan ?? Plans::FREE,
                'plan_expires_at' => $previous?->period_end,
            ])->save();

            return $latest;
        });
    }

    /** Boutiques dont une formule payante est en cours. */
    public function scopePaid(Builder $query): void
    {
        $query->where('plan', '!=', Plans::FREE)->where('plan_expires_at', '>', now());
    }

    /** Formule payante qui se termine bientôt ou vient de se terminer. */
    public function scopeToRemind(Builder $query): void
    {
        $query->where('plan', '!=', Plans::FREE)
            ->whereBetween('plan_expires_at', [now()->subDays(self::REMIND_AFTER_DAYS), now()->addDays(self::REMIND_BEFORE_DAYS)]);
    }

    /** Formule payante qui finit dans moins de 7 jours, ou déjà terminée : à relancer. */
    public function needsReminder(): bool
    {
        return $this->plan !== Plans::FREE && $this->plan_expires_at
            && $this->plan_expires_at->lte(now()->addDays(self::REMIND_BEFORE_DAYS));
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** Suspendue : vitrine et espace commerçant fermés, mais rien n'est effacé. */
    public function suspend(string $reason): void
    {
        $this->forceFill(['suspended_at' => now(), 'suspension_reason' => $reason])->save();
    }

    public function reactivate(): void
    {
        $this->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
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
