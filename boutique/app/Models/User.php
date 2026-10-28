<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // is_team n'est pas modifiable par formulaire : seulement par `artisan wanllan:equipe`.
    protected $fillable = ['name', 'phone', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'password_reset_hash'];

    /** Durée de validité du lien « nouveau mot de passe ». */
    public const RESET_MINUTES = 60;

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_team' => 'boolean', 'password_reset_expires_at' => 'datetime'];
    }

    /**
     * Lien « nouveau mot de passe » : valable 1 heure, une seule fois. Un nouveau lien annule le précédent.
     * Seule l'empreinte est enregistrée : le lien lui-même n'existe que dans le message WhatsApp.
     */
    public function createPasswordResetUrl(): string
    {
        $token = Str::random(48);
        $this->forceFill([
            'password_reset_hash' => hash('sha256', $token),
            'password_reset_expires_at' => now()->addMinutes(self::RESET_MINUTES),
        ])->save();

        return route('password.reset', $token);
    }

    public static function findByResetToken(string $token): ?self
    {
        return self::where('password_reset_hash', hash('sha256', $token))
            ->where('password_reset_expires_at', '>', now())
            ->first();
    }

    /** Nouveau mot de passe : le lien ne sert plus, et les « rester connecté » des autres appareils tombent. */
    public function resetPassword(string $password): void
    {
        $this->forceFill([
            'password' => $password,
            'password_reset_hash' => null,
            'password_reset_expires_at' => null,
            'remember_token' => Str::random(60),
        ])->save();
    }

    /** V1 : un commerçant = une boutique. */
    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    /** Appareils qui reçoivent l'alerte « nouvelle commande ». */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
