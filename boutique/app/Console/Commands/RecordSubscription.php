<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Support\Plans;
use Illuminate\Console\Command;

/**
 * Enregistre un abonnement payé par Orange Money / MTN, après vérification du
 * paiement sur le téléphone de l'équipe Wanllan.
 *
 *   php artisan wanllan:abonnement nimstore merchant 1 --montant=50000 --ref=MP2610.1234
 *   php artisan wanllan:abonnement nimstore pro 12 --montant=1000000
 */
class RecordSubscription extends Command
{
    protected $signature = 'wanllan:abonnement
        {boutique : lien de la boutique (slug)}
        {formule : merchant ou pro}
        {mois=1 : nombre de mois payés}
        {--montant= : montant reçu en GNF (par défaut : le prix de la formule)}
        {--moyen=orange_money : orange_money, mtn_momo ou cash}
        {--ref= : numéro de transaction}';

    protected $description = 'Enregistre un paiement d\'abonnement Wanllan (aucun prélèvement automatique)';

    public function handle(): int
    {
        $store = Store::where('slug', $this->argument('boutique'))->first();
        if (! $store) {
            $this->error('Boutique introuvable : '.$this->argument('boutique'));

            return self::FAILURE;
        }

        $plan = $this->argument('formule');
        if (! in_array($plan, Plans::paid(), true)) {
            $this->error('Formule inconnue. Utilisez merchant ou pro.');

            return self::FAILURE;
        }

        $months = max(1, (int) $this->argument('mois'));
        $expected = Plans::price($plan, $months);
        $amount = $this->option('montant') !== null ? (int) preg_replace('/\D+/', '', $this->option('montant')) : $expected;

        if ($amount < $expected && ! $this->confirm('Montant reçu ('.gnf($amount).') inférieur au prix ('.gnf($expected).'). Enregistrer quand même ?')) {
            return self::FAILURE;
        }

        $payment = $store->recordSubscription($plan, $months, $amount, (string) $this->option('moyen'), $this->option('ref'));

        $this->info(sprintf('%s : formule %s jusqu\'au %s (%s reçus).', $store->name, Plans::get($plan)['name'],
            $payment->period_end->timezone('Africa/Conakry')->format('d/m/Y'), gnf($amount)));

        return self::SUCCESS;
    }
}
