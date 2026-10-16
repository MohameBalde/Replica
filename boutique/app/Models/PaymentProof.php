<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    /** Disque privé : jamais servi directement, seulement via le contrôleur admin. */
    public const DISK = 'local';

    protected $fillable = ['path', 'reference'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
