<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WhatsAppMessage;
use App\Support\Phone;
use Illuminate\Console\Command;

/**
 * Lien « nouveau mot de passe » pour un compte que l'espace Gestion ne montre pas :
 * compte sans boutique, ou membre de l'équipe qui ne peut plus se connecter.
 *
 *   php artisan wanllan:mot-de-passe 620 00 00 00
 */
class PasswordLink extends Command
{
    protected $signature = 'wanllan:mot-de-passe {telephone* : numéro du compte (avec ou sans espaces)}';

    protected $description = 'Crée un lien pour choisir un nouveau mot de passe (valable 1 heure)';

    public function handle(): int
    {
        $phone = Phone::normalize(implode('', $this->argument('telephone')));
        $user = $phone ? User::where('phone', $phone)->first() : null;
        if (! $user) {
            $this->error('Aucun compte avec ce numéro.');

            return self::FAILURE;
        }

        $url = $user->createPasswordResetUrl();

        $this->info('Lien pour '.$user->name.' ('.phone_display($phone).'), valable 1 heure, une seule fois :');
        $this->line($url);
        $this->line('À envoyer seulement au WhatsApp de ce numéro :');
        $this->line(WhatsAppMessage::url($phone, WhatsAppMessage::passwordReset($user, $url)));

        return self::SUCCESS;
    }
}
