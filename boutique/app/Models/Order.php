<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Non payée',
        'confirming' => 'Paiement à vérifier',
        'partially_paid' => 'Partiellement payée',
        'paid' => 'Payée',
        'refunded' => 'Remboursée',
    ];

    public const FULFILLMENT_STATUSES = [
        'unfulfilled' => 'Non préparée',
        'ready' => 'Prête',
        'out_for_delivery' => 'En livraison',
        'fulfilled' => 'Livrée',
    ];

    /** Origines d'une commande. Toutes sont acceptées dans ?src= sauf « manual » ; une autre valeur devient « direct ». */
    public const SOURCES = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
        'messenger' => 'Messenger',
        'qr' => 'QR code',
        'direct' => 'Direct',
        'manual' => 'Saisie à la main',
    ];

    /** Commande saisie par le commerçant (pas une source de ?src=). */
    public const MANUAL = 'manual';

    /**
     * Ce qui compte dans les chiffres de ventes (Accueil, Partager) : les commandes
     * ni annulées ni remboursées, payées ou non. Montant compté : total, livraison comprise.
     */
    public const COUNTED_STATUSES = ['pending', 'confirmed', 'completed'];

    public const COUNTED_PAYMENTS = ['unpaid', 'confirming', 'partially_paid', 'paid'];

    protected $fillable = [
        'customer_id', 'number', 'public_token', 'status', 'payment_status', 'fulfillment_status',
        'customer_name', 'customer_phone', 'address', 'note', 'answers', 'delivery_method_id', 'delivery_method_name',
        'delivery_fee', 'payment_method_id', 'payment_method_type', 'subtotal', 'total', 'currency',
        'source', 'campaign', 'internal_note', 'whatsapp_sent_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'subtotal' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'answers' => 'array',
            'whatsapp_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class)->latest();
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function scopeCounted(Builder $query): void
    {
        $query->whereIn('status', self::COUNTED_STATUSES)->whereIn('payment_status', self::COUNTED_PAYMENTS);
    }

    public function isCashOnDelivery(): bool
    {
        return $this->payment_method_type === PaymentMethod::CASH_ON_DELIVERY;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** « via TikTok », « saisie à la main », ou rien pour une visite directe. */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            'direct' => '',
            self::MANUAL => 'saisie à la main',
            default => 'via '.(self::SOURCES[$this->source] ?? $this->source),
        };
    }

    public function paymentLabel(): string
    {
        return PaymentMethod::LABELS[$this->payment_method_type] ?? $this->payment_method_type;
    }
}
