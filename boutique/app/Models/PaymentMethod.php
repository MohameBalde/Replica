<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    public const CASH_ON_DELIVERY = 'cash_on_delivery';

    public const ORANGE_MONEY = 'orange_money';

    public const MTN_MOMO = 'mtn_momo';

    public const BANK_TRANSFER = 'bank_transfer';

    public const LABELS = [
        self::CASH_ON_DELIVERY => 'Paiement à la livraison',
        self::ORANGE_MONEY => 'Orange Money',
        self::MTN_MOMO => 'MTN MoMo',
        self::BANK_TRANSFER => 'Virement bancaire',
    ];

    protected $fillable = ['type', 'account_number', 'account_name', 'instructions', 'is_active', 'max_order_total', 'position'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'max_order_total' => 'integer'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }

    public function isCashOnDelivery(): bool
    {
        return $this->type === self::CASH_ON_DELIVERY;
    }

    /** Un moyen mobile money ou virement sans numéro ne peut pas être proposé au client. */
    public function isComplete(): bool
    {
        return $this->isCashOnDelivery() || filled($this->account_number);
    }
}
