<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderBulkTest extends TestCase
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

        $this->actingAs($this->merchant)->post('/admin/produits', ['name' => 'Baskets', 'price' => '150000', 'stock' => '10'])
            ->assertSessionHasNoErrors();
        $this->product = $this->store->products()->with('variants')->firstOrFail();
    }

    private function order(int $qty = 1, string $phone = '621 00 00 01'): Order
    {
        $this->post('/nimstore/panier', ['variant_id' => $this->product->variants->first()->id, 'quantity' => $qty]);
        $this->post('/nimstore/commande', [
            'name' => 'Client '.$phone, 'phone' => $phone,
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $this->store->cashOnDelivery()->id,
        ])->assertSessionHasNoErrors();

        return $this->store->orders()->latest('id')->firstOrFail();
    }

    private function bulk(string $action, array $ids)
    {
        return $this->actingAs($this->merchant)->from('/admin/commandes?statut=pending')
            ->post('/admin/commandes/actions', ['action' => $action, 'orders' => $ids]);
    }

    private function stock(): int
    {
        return (int) $this->product->variants->first()->fresh()->stock;
    }

    public function test_list_has_a_checkbox_per_order_and_the_actions_bar(): void
    {
        $a = $this->order();
        $b = $this->order(phone: '621 00 00 02');

        $this->actingAs($this->merchant)->get('/admin/commandes')->assertOk()
            ->assertSee('form="bulk" name="orders[]" value="'.$a->id.'"', false)   // les cases sont hors du <form> : form="bulk" les y rattache
            ->assertSee('<form id="bulk"', false)
            ->assertSee('name="orders[]" value="'.$b->id.'"', false)
            ->assertSee('data-bulk-all', false)
            ->assertSee('action="'.route('admin.orders.bulk').'"', false)
            ->assertSeeInOrder(['Confirmer', 'Terminer', 'Annuler (le stock est remis)', 'Marquer « Payée »', 'Prête', 'En livraison', 'Livrée']);

        // L'Accueil montre les mêmes commandes, sans case à cocher.
        $this->actingAs($this->merchant)->get('/admin')->assertOk()->assertDontSee('name="orders[]"', false);
    }

    public function test_confirm_several_orders_and_keep_the_list_filters(): void
    {
        $a = $this->order();
        $b = $this->order(phone: '621 00 00 02');
        $c = $this->order(phone: '621 00 00 03');

        $this->bulk('confirmed', [$a->id, $b->id])
            ->assertRedirect('/admin/commandes?statut=pending')
            ->assertSessionHas('status', '2 commandes confirmées.');

        $this->assertSame(['confirmed', 'confirmed', 'pending'], [$a->fresh()->status, $b->fresh()->status, $c->fresh()->status]);
    }

    public function test_each_action_sets_its_own_field(): void
    {
        $order = $this->order();

        $this->bulk('paid', [$order->id])->assertSessionHas('status', '1 commande marquée « Payée ».');
        $this->bulk('out_for_delivery', [$order->id]);
        $this->bulk('completed', [$order->id]);

        $order->refresh();
        $this->assertSame(['completed', 'paid', 'out_for_delivery'], [$order->status, $order->payment_status, $order->fulfillment_status]);
    }

    public function test_bulk_cancel_puts_the_stock_back_for_each_order(): void
    {
        $a = $this->order(2);
        $b = $this->order(3, '621 00 00 02');
        $this->assertSame(5, $this->stock());

        $this->bulk('cancel', [$a->id, $b->id])->assertSessionHas('status', '2 commandes annulées, stock remis.');

        $this->assertSame(10, $this->stock());
        $this->assertTrue($a->fresh()->isCancelled() && $b->fresh()->isCancelled());
        $this->assertSame(2, \App\Models\StockMovement::where('store_id', $this->store->id)->where('reason', 'cancel')->count());
    }

    public function test_cancelled_orders_are_left_alone_and_counted_in_the_message(): void
    {
        $live = $this->order();
        $cancelled = $this->order(phone: '621 00 00 02');
        $this->actingAs($this->merchant)->post("/admin/commandes/{$cancelled->id}/annuler");

        $this->bulk('paid', [$live->id, $cancelled->id])
            ->assertSessionHas('status', '1 commande marquée « Payée ». 1 commande n\'a pas changé (déjà à cette étape ou plus loin, ou annulée).');
        $this->assertSame('unpaid', $cancelled->fresh()->payment_status);

        // Annuler deux fois ne remet pas le stock deux fois.
        $this->bulk('cancel', [$cancelled->id])->assertSessionHasErrors(['orders' => 'Aucune commande modifiée : les commandes choisies sont déjà à cette étape (ou plus loin), ou annulées.']);
        $this->assertSame(9, $this->stock());
    }

    public function test_orders_of_another_store_are_never_touched(): void
    {
        $mine = $this->order();
        $other = User::factory()->create();
        $otherStore = Store::forceCreate(['user_id' => $other->id, 'name' => 'Autre', 'slug' => 'autre', 'whatsapp_number' => '224622000000', 'city' => 'Conakry']);
        $theirs = $otherStore->orders()->create([
            'number' => 5001, 'public_token' => (string) Str::ulid(), 'customer_name' => 'Client', 'customer_phone' => '224622111111',
            'delivery_method_name' => 'Retrait', 'payment_method_type' => 'cash_on_delivery', 'subtotal' => 1000, 'total' => 1000,
        ]);

        $this->bulk('cancel', [$mine->id, $theirs->id])->assertSessionHas('status', '1 commande annulée, stock remis.');
        $this->assertSame('pending', $theirs->fresh()->status);

        $this->bulk('paid', [$theirs->id])->assertSessionHasErrors(['orders' => 'Aucune commande modifiée.']);
        $this->assertSame('unpaid', $theirs->fresh()->payment_status);
    }

    public function test_bulk_actions_never_move_an_order_backwards(): void
    {
        $done = $this->order();
        $new = $this->order(phone: '621 00 00 02');
        $refunded = $this->order(phone: '621 00 00 03');
        Order::whereKey($done->id)->update(['status' => 'completed', 'fulfillment_status' => 'fulfilled']);
        Order::whereKey($refunded->id)->update(['payment_status' => 'refunded']);

        $this->bulk('confirmed', [$done->id, $new->id])->assertSessionHas('status', '1 commande confirmée. 1 commande n\'a pas changé (déjà à cette étape ou plus loin, ou annulée).');
        $this->assertSame(['completed', 'confirmed'], [$done->fresh()->status, $new->fresh()->status]);

        $this->bulk('ready', [$done->id, $new->id]);
        $this->assertSame(['fulfilled', 'ready'], [$done->fresh()->fulfillment_status, $new->fresh()->fulfillment_status]);

        $this->bulk('paid', [$refunded->id])->assertSessionHasErrors('orders');
        $this->assertSame('refunded', $refunded->fresh()->payment_status);
    }

    public function test_after_an_action_the_list_keeps_its_filters_but_goes_back_to_page_one(): void
    {
        $order = $this->order();
        $this->actingAs($this->merchant)->from('/admin/commandes?statut=pending&page=2')
            ->post('/admin/commandes/actions', ['action' => 'confirmed', 'orders' => [$order->id]])
            ->assertRedirect('/admin/commandes?statut=pending');
        $this->actingAs($this->merchant)->from('/admin/commandes?page=3&paiement=cod')
            ->post('/admin/commandes/actions', ['action' => 'completed', 'orders' => [$order->id]])
            ->assertRedirect('/admin/commandes?paiement=cod');
        $this->assertSame('Page suivante »', __('pagination.next'));   // et non « pagination.next » en français
    }

    public function test_bad_requests_are_refused_with_a_clear_message(): void
    {
        $order = $this->order();

        $this->bulk('', [$order->id])->assertSessionHasErrors(['action' => 'Choisissez une action.']);
        $this->bulk('delete', [$order->id])->assertSessionHasErrors(['action' => 'Choisissez une action.']);
        $this->bulk('confirmed', [])->assertSessionHasErrors(['orders' => 'Cochez au moins une commande.']);
        $this->bulk('confirmed', range(1, 101))->assertSessionHasErrors(['orders' => 'Au plus 100 commandes à la fois.']);
        $this->bulk('confirmed', ['abc'])->assertSessionHasErrors('orders.0');
        $this->assertSame('pending', $order->fresh()->status);

        auth()->logout();
        $this->post('/admin/commandes/actions', ['action' => 'cancel', 'orders' => [$order->id]])->assertRedirect('/connexion');
        $this->assertSame('pending', $order->fresh()->status);
    }
}
