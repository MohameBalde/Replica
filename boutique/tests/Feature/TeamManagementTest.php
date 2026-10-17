<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\TeamAction;
use App\Models\User;
use App\Services\WhatsAppMessage;
use App\Support\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $team;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Un commerçant inscrit par les vrais écrans, avec un produit.
        $this->post('/inscription', [
            'name' => 'Aïssatou', 'phone' => '620 11 22 33',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620 11 22 33', 'city' => 'Conakry']);
        $this->post('/admin/produits', ['name' => 'Baskets blanches', 'price' => '150000']);
        auth()->logout();

        $this->merchant = User::where('phone', '224620112233')->firstOrFail();
        $this->store = Store::where('slug', 'nimstore')->firstOrFail();

        // Le compte de l'équipe : l'accès se donne seulement en ligne de commande.
        $this->team = User::factory()->create(['name' => 'Mohamed', 'phone' => '224626000000']);
        $this->artisan('wanllan:equipe', ['telephone' => ['626', '00', '00', '00']])->assertSuccessful();
        $this->team->refresh();
    }

    private function pay(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->team)->post(route('team.stores.payments', $this->store->id), $data + [
            'plan' => Plans::MERCHANT, 'months' => 1, 'method' => 'orange_money', 'reference' => 'mp261018.1234.a1',
        ]);
    }

    public function test_gestion_is_only_for_the_team_and_access_is_given_by_command(): void
    {
        $this->get('/gestion')->assertRedirect(route('login'));
        $this->actingAs($this->merchant)->get('/gestion')->assertNotFound();
        $this->actingAs($this->merchant)->post(route('team.stores.suspend', $this->store->id), ['reason' => 'x'])->assertNotFound();
        $this->actingAs($this->merchant)->get('/admin/commandes')->assertDontSee('Gestion');

        $this->actingAs($this->team)->get('/gestion')->assertOk()->assertSee('NimStore');
        $this->actingAs($this->team)->get('/admin/boutique/creer')->assertOk(); // le compte équipe peut aussi avoir sa boutique

        $this->artisan('wanllan:equipe', ['telephone' => ['626000000'], '--retirer' => true])->assertSuccessful();
        $this->actingAs($this->team->fresh())->get('/gestion')->assertNotFound();
        $this->artisan('wanllan:equipe', ['telephone' => ['629 99 99 99']])->assertFailed();

        // Le lien /gestion ne peut pas devenir une boutique.
        $this->actingAs($this->team)->post('/admin/boutique/creer', ['name' => 'Gestion', 'slug' => 'gestion', 'whatsapp_number' => '626000000', 'city' => 'Conakry'])
            ->assertSessionHasErrors('slug');
    }

    public function test_dashboard_counts_searches_and_filters_stores(): void
    {
        $other = Store::forceCreate(['user_id' => User::factory()->create(['name' => 'Nimaga'])->id, 'name' => 'Chaussures Nimaga', 'slug' => 'nimaga', 'whatsapp_number' => '224621445566', 'city' => 'Kankan']);
        $other->recordSubscription(Plans::PRO, 1, 100000, 'orange_money');

        $this->actingAs($this->team)->get('/gestion')->assertOk()
            ->assertSee('NimStore')->assertSee('Chaussures Nimaga')
            ->assertSee('100 000 GNF')            // encaissé ce mois-ci
            ->assertSee('Payantes (1)');

        $this->get('/gestion?q=621 44')->assertSee('Chaussures Nimaga')->assertDontSee('NimStore');
        $this->get('/gestion?q=aïssatou')->assertSee('NimStore')->assertDontSee('Chaussures Nimaga');
        $this->get('/gestion?filtre=payantes')->assertSee('Chaussures Nimaga')->assertDontSee('NimStore');
        $this->get('/gestion?filtre=inconnu')->assertOk()->assertSee('NimStore');
    }

    public function test_payment_from_the_phone_activates_the_plan_logs_it_and_prepares_the_confirmation(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(10, 0));

        $this->pay()->assertRedirect(route('team.stores.show', $this->store->id))
            ->assertSessionHas('status', 'Formule Commerçant active jusqu\'au 18/11/2026.');

        $this->store->refresh();
        $this->assertSame(Plans::MERCHANT, $this->store->activePlan());
        $payment = $this->store->subscriptionPayments()->firstOrFail();
        $this->assertSame(config('wanllan.prices.merchant'), $payment->amount); // montant vide = prix de la formule
        $this->assertSame('MP261018.1234.A1', $payment->reference);

        $action = TeamAction::firstOrFail();
        $this->assertSame([$this->team->id, $this->store->id, 'paiement'], [$action->user_id, $action->store_id, $action->action]);
        $this->assertStringContainsString('réf. MP261018.1234.A1', $action->details);

        // Le bouton « Envoyer la confirmation » écrit au WhatsApp de la boutique, avec la date de fin.
        $confirm = session('confirm_url');
        $this->assertStringStartsWith('https://wa.me/224620112233?text=', $confirm);
        $this->assertStringContainsString('active jusqu\'au 18/11/2026', rawurldecode($confirm));
        $this->get(route('team.stores.show', $this->store->id))->assertOk()->assertSee('Envoyer la confirmation');

        // Un deuxième mois payé en avance s'ajoute à la suite.
        $this->pay(['reference' => 'MP261018.9999.B2'])->assertSessionHas('status', 'Formule Commerçant active jusqu\'au 18/12/2026.');
    }

    public function test_lower_amount_needs_a_tick_and_a_reference_cannot_pay_twice(): void
    {
        $this->pay(['amount' => '30 000'])->assertSessionHasErrors('amount');
        $this->assertSame(0, SubscriptionPayment::count());

        $this->pay(['amount' => '30 000', 'accept_lower' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(30000, SubscriptionPayment::firstOrFail()->amount);

        // Même capture renvoyée (même référence, casse différente) : refusée.
        $this->pay(['reference' => 'MP261018.1234.a1'])->assertSessionHasErrors('reference');
        $this->assertSame(1, SubscriptionPayment::count());

        // Un mois offert : 0 GNF, coché.
        $this->pay(['amount' => '0', 'accept_lower' => '1', 'reference' => ''])->assertSessionHasNoErrors();
        $this->assertSame(0, SubscriptionPayment::latest('id')->firstOrFail()->amount);

        $this->pay(['plan' => 'gold'])->assertSessionHasErrors('plan');
        $this->pay(['months' => 0])->assertSessionHasErrors('months');
    }

    public function test_a_double_tap_on_a_slow_network_does_not_pay_twice(): void
    {
        $this->pay(['reference' => ''])->assertSessionHasNoErrors();
        $this->pay(['reference' => ''])->assertSessionHasErrors('duplicate');
        $this->assertSame(1, SubscriptionPayment::count());

        // Un vrai deuxième paiement identique, plus tard : accepté.
        $this->travel(3)->minutes();
        $this->pay(['reference' => ''])->assertSessionHasNoErrors();
        $this->assertSame(2, SubscriptionPayment::count());

        // La commande du terminal écrit les n° de transaction comme le formulaire.
        $this->artisan('wanllan:abonnement', ['boutique' => 'nimstore', 'formule' => 'merchant', '--ref' => ' mp261018.5555.z9 '])->assertSuccessful();
        $this->assertSame('MP261018.5555.Z9', SubscriptionPayment::latest('id')->value('reference'));
        $this->pay(['reference' => 'MP261018.5555.Z9'])->assertSessionHasErrors('reference');
    }

    public function test_cancelling_the_latest_payment_puts_the_previous_end_date_back(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 18)->startOfDay());
        $this->pay(['reference' => 'A']);
        $this->pay(['reference' => 'B', 'months' => 12]); // erreur : 12 mois au lieu de 1
        $this->assertSame('2027-11-18', $this->store->fresh()->plan_expires_at->toDateString());

        $this->delete(route('team.stores.payments.cancel', $this->store->id))
            ->assertSessionHas('status', 'Dernier paiement annulé. Formule actuelle : Commerçant jusqu\'au 18/11/2026.');
        $this->assertSame('2026-11-18', $this->store->fresh()->plan_expires_at->toDateString());

        $this->delete(route('team.stores.payments.cancel', $this->store->id))
            ->assertSessionHas('status', 'Dernier paiement annulé. Formule actuelle : Découverte.');
        $this->assertSame(Plans::FREE, $this->store->fresh()->activePlan());
        $this->delete(route('team.stores.payments.cancel', $this->store->id))->assertSessionHasErrors('payment');

        $this->assertSame(2, TeamAction::where('action', 'paiement_annule')->count());
    }

    public function test_suspension_closes_the_shop_and_the_merchant_space_without_deleting_anything(): void
    {
        // Une commande passée avant la suspension.
        $variant = $this->store->products()->firstOrFail()->variants()->firstOrFail();
        $this->post('/nimstore/panier', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post('/nimstore/commande', [
            'name' => 'Mamadou Diallo', 'phone' => '621 00 00 01',
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $this->store->cashOnDelivery()->id,
        ]);
        $order = Order::firstOrFail();

        $this->actingAs($this->team)->post(route('team.stores.suspend', $this->store->id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('team.stores.suspend', $this->store->id), ['reason' => 'Plaintes de clients non livrés'])
            ->assertSessionHas('status');
        $this->assertTrue($this->store->fresh()->isSuspended());

        auth()->logout();
        $this->get('/nimstore')->assertForbidden()->assertSee('Boutique momentanément indisponible')->assertDontSee('Baskets blanches');
        $this->get('/nimstore/p/'.$variant->product->slug)->assertForbidden();
        $this->post('/nimstore/panier', ['variant_id' => $variant->id, 'quantity' => 1])->assertForbidden();
        $this->get(route('invoice.show', $order))->assertOk(); // les clients qui ont commandé gardent leur facture

        $this->actingAs($this->merchant)->get('/admin/commandes')->assertForbidden()
            ->assertSee('Plaintes de clients non livrés')->assertSee('Rien n\'est effacé', false)->assertDontSee('Mamadou Diallo');
        $this->get('/admin/produits')->assertForbidden();
        $this->assertSame(1, $this->store->products()->count());
        $this->assertSame(1, $this->store->orders()->count());

        $this->actingAs($this->team)->get('/gestion?filtre=suspendues')->assertSee('NimStore');
        $this->post(route('team.stores.reactivate', $this->store->id))->assertSessionHas('status', 'NimStore est de nouveau en ligne.');
        auth()->logout();
        $this->get('/nimstore')->assertOk()->assertSee('Baskets blanches');
        $this->actingAs($this->merchant)->get('/admin/commandes')->assertOk()->assertSee('Mamadou Diallo');

        $this->assertSame(['suspension', 'reactivation'], TeamAction::orderBy('id')->pluck('action')->all());
    }

    public function test_reminders_cover_one_week_before_to_one_month_after_the_end(): void
    {
        $make = function (string $slug, int $daysFromNow) {
            $store = Store::forceCreate(['user_id' => User::factory()->create()->id, 'name' => 'Boutique '.$slug, 'slug' => $slug, 'whatsapp_number' => '224622000000', 'city' => 'Conakry']);
            $store->forceFill(['plan' => Plans::MERCHANT, 'plan_expires_at' => now()->addDays($daysFromNow)])->save();

            return $store;
        };
        $soon = $make('bientot', 3);
        $ended = $make('terminee', -10);
        $later = $make('plus-tard', 20);
        $make('ancienne', -40);

        $this->actingAs($this->team)->get('/gestion?filtre=relancer')->assertOk()
            ->assertSeeInOrder(['Boutique terminee', 'Boutique bientot'])
            ->assertDontSee('Boutique plus-tard')->assertDontSee('Boutique ancienne')->assertDontSee('NimStore');

        // Le message prêt à envoyer dépend de l'abonnement.
        $this->assertStringContainsString('se termine le', WhatsAppMessage::toMerchant($soon));
        $this->assertStringContainsString('est terminée depuis le', WhatsAppMessage::toMerchant($ended));
        $this->assertStringContainsString(phone_display(config('wanllan.pay_orange_money')), WhatsAppMessage::toMerchant($ended));
        $this->assertStringContainsString('Je vous écris à propos de votre boutique', WhatsAppMessage::toMerchant($later));
        $this->assertStringContainsString('Je vous écris à propos de votre boutique', WhatsAppMessage::toMerchant($this->store));

        $this->get(route('team.stores.show', $soon->id))->assertOk()->assertSee('Relancer sur WhatsApp')->assertSee('wa.me/224622000000', false);
        $this->get(route('team.stores.show', $later->id))->assertOk()->assertSee('Écrire sur WhatsApp')->assertDontSee('Relancer sur WhatsApp');
    }
}
