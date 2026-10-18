<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post('/inscription', [
            'name' => 'Aïssatou', 'phone' => '620 11 22 33',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620 11 22 33', 'city' => 'Conakry']);
        $this->merchant = User::where('phone', '224620112233')->firstOrFail();
        $this->store = Store::where('slug', 'nimstore')->firstOrFail();

        $this->actingAs($this->merchant)->post('/admin/produits', ['name' => 'Baskets', 'price' => '150000'])->assertSessionHasNoErrors();
        $this->product = $this->store->products()->with('variants')->firstOrFail();
        $this->actingAs($this->merchant)->put('/admin/paiement/orange_money', [
            'account_number' => '620 99 88 77', 'account_name' => 'Aïssatou Diallo', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
    }

    private function order(string $payment = 'cash_on_delivery', string $delivery = 'pickup'): Order
    {
        $deliveryId = $delivery === 'pickup'
            ? $this->store->deliveryMethods()->where('type', 'pickup')->value('id')
            : $this->store->deliveryMethods()->create(['name' => 'Livraison Conakry', 'type' => 'delivery', 'fee' => 25000, 'is_active' => true])->id;
        $this->post('/nimstore/panier', ['variant_id' => $this->product->variants->first()->id, 'quantity' => 2]);
        $this->post('/nimstore/commande', [
            'name' => 'Mamadou Saliou Diallo', 'phone' => '621 00 00 01', 'address' => $delivery === 'pickup' ? null : 'Kipé, près de la pharmacie',
            'delivery_method_id' => $deliveryId,
            'payment_method_id' => $this->store->paymentMethods()->where('type', $payment)->value('id'),
        ])->assertSessionHasNoErrors();

        return $this->store->orders()->latest('id')->firstOrFail();
    }

    private function messages(Order $order): array
    {
        return WhatsAppMessage::toCustomer($order->fresh());
    }

    public function test_new_mobile_money_order_offers_confirmation_with_how_to_pay(): void
    {
        $order = $this->order('orange_money');
        $m = $this->messages($order);

        $this->assertSame(['confirm', 'payment', 'ready', 'invoice'], array_keys($m));
        $this->assertStringStartsWith('Bonjour Mamadou, ici NimStore', $m['confirm'][1]);
        $this->assertStringContainsString('#'.$order->number.' (300 000 GNF)', $m['confirm'][1]);
        $this->assertStringContainsString('par Orange Money au +224 620 99 88 77 (Aïssatou Diallo)', $m['confirm'][1]);
        $this->assertStringContainsString(route('invoice.show', $order), $m['confirm'][1]);
        $this->assertSame('Commande prête', $m['ready'][0]);
    }

    public function test_cash_on_delivery_with_delivery_says_on_the_way_and_amount_to_prepare(): void
    {
        $order = $this->order('cash_on_delivery', 'delivery');
        $m = $this->messages($order);

        $this->assertArrayNotHasKey('payment', $m);
        $this->assertStringContainsString('Paiement à la livraison : préparez 325 000 GNF.', $m['confirm'][1]);
        $this->assertSame('Commande en route', $m['ready'][0]);
        $this->assertStringContainsString('Livraison à : Kipé, près de la pharmacie.', $m['ready'][1]);
        $this->assertStringContainsString('Merci de préparer 325 000 GNF.', $m['ready'][1]);
    }

    public function test_messages_follow_the_order_through_payment_and_delivery(): void
    {
        $order = $this->order('orange_money');
        $order->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->assertSame(['paid', 'ready', 'invoice'], array_keys($this->messages($order)));

        $order->update(['status' => 'completed', 'fulfillment_status' => 'fulfilled']);
        $m = $this->messages($order);
        $this->assertSame(['paid', 'thanks', 'invoice'], array_keys($m));
        $this->assertStringContainsString(route('shop.show', $this->store), $m['thanks'][1]);

        $this->actingAs($this->merchant)->post("/admin/commandes/{$order->id}/annuler");
        $this->assertSame(['cancelled'], array_keys($this->messages($order)));
    }

    public function test_order_page_links_each_message_to_the_customer_whatsapp(): void
    {
        $order = $this->order('orange_money');
        $text = WhatsAppMessage::toCustomer($order->fresh())['confirm'][1];

        $this->actingAs($this->merchant)->get("/admin/commandes/{$order->id}")->assertOk()
            ->assertSeeInOrder(['Écrire au client sur WhatsApp', 'Confirmer la commande', 'Rappeler le paiement', 'Commande prête', 'Envoyer la facture'])
            ->assertSee(e('https://wa.me/224621000001?text='.rawurlencode($text)), false)
            ->assertSee('Message libre')
            ->assertSee(route('admin.orders.print', $order->id), false);
    }

    public function test_printable_invoice_shows_store_customer_lines_and_what_is_left_to_pay(): void
    {
        $order = $this->order('cash_on_delivery', 'delivery');

        $this->actingAs($this->merchant)->get("/admin/commandes/{$order->id}/facture")->assertOk()
            ->assertSeeInOrder(['NimStore', 'Conakry', 'FACTURE', 'N° '.$order->number])
            ->assertSeeInOrder(['Mamadou Saliou Diallo', '+224 621 00 00 01', 'Kipé, près de la pharmacie'])
            ->assertSeeInOrder(['Baskets', '2', '150 000 GNF', '300 000 GNF'])
            ->assertSeeInOrder(['Livraison Conakry', '25 000 GNF', 'Total', '325 000 GNF'])
            ->assertSee('À payer à la livraison : 325 000 GNF')
            ->assertSee('window.print()', false)
            ->assertSee('Facture créée avec Wanllan')
            ->assertSee('src="data:image/svg+xml;base64,', false)       // QR code de suivi
            ->assertSee('Scannez pour suivre la commande : '.route('invoice.show', $order))
            ->assertDontSee('aria-label="Menu"', false);

        $order->update(['payment_status' => 'paid']);
        $this->actingAs($this->merchant)->get("/admin/commandes/{$order->id}/facture")->assertSee('Payée')->assertDontSee('À payer');
    }

    public function test_invoice_of_another_store_is_not_found_and_guests_must_log_in(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create())->get("/admin/commandes/{$order->id}/facture")->assertRedirect(route('admin.store.create'));

        $this->post('/inscription', ['name' => 'Autre', 'phone' => '622 00 00 00', 'password' => 'motdepasse', 'password_confirmation' => 'motdepasse']);
        $this->post('/admin/boutique/creer', ['name' => 'Autre', 'slug' => 'autre', 'whatsapp_number' => '622 00 00 00', 'city' => 'Labé']);
        $this->get("/admin/commandes/{$order->id}/facture")->assertNotFound();

        auth()->logout();
        $this->get("/admin/commandes/{$order->id}/facture")->assertRedirect('/connexion');
    }
}
