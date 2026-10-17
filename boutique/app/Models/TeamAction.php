<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Trace d'une action de l'équipe Wanllan sur une boutique (paiement, suspension…). */
class TeamAction extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'paiement' => 'Paiement enregistré',
        'paiement_annule' => 'Paiement annulé',
        'suspension' => 'Boutique suspendue',
        'reactivation' => 'Boutique réactivée',
    ];

    protected $fillable = ['user_id', 'store_id', 'store_name', 'action', 'details'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function record(User $by, Store $store, string $action, ?string $details = null): self
    {
        return self::create([
            'user_id' => $by->id, 'store_id' => $store->id, 'store_name' => $store->name,
            'action' => $action, 'details' => $details ? mb_substr($details, 0, 255) : null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
