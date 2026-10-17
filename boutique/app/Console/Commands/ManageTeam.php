<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Command;

/**
 * Donne (ou retire) l'accès à l'espace « Gestion » à un compte déjà inscrit.
 * Seul moyen de le faire : aucun formulaire du site ne peut changer ce droit.
 *
 *   php artisan wanllan:equipe 620 00 00 00
 *   php artisan wanllan:equipe 620000000 --retirer
 */
class ManageTeam extends Command
{
    protected $signature = 'wanllan:equipe
        {telephone* : numéro du compte (avec ou sans espaces)}
        {--retirer : retire l\'accès au lieu de le donner}';

    protected $description = 'Donne ou retire l\'accès à l\'espace Gestion de Wanllan';

    public function handle(): int
    {
        $phone = Phone::normalize(implode('', $this->argument('telephone')));
        $user = $phone ? User::where('phone', $phone)->first() : null;
        if (! $user) {
            $this->error('Aucun compte avec ce numéro. Inscrivez-vous d\'abord sur le site avec ce numéro.');

            return self::FAILURE;
        }

        $grant = ! $this->option('retirer');
        $user->forceFill(['is_team' => $grant])->save();

        $this->info($grant
            ? $user->name.' ('.phone_display($phone).') a accès à '.route('team.dashboard')
            : $user->name.' ('.phone_display($phone).') n\'a plus accès à l\'espace Gestion.');

        return self::SUCCESS;
    }
}
