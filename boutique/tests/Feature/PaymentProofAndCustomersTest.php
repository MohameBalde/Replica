<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofAndCustomersTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->merchant = User::factory()->create();
        $this->actingAs($this->merchant)->post('/admin/boutique/creer', [
            'name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620000000', 'city' => 'Conakry',
        ]);
        $this->post('/admin/produits', ['name' => 'Baskets', 'price' => '200 000']);
        $this->put('/admin/paiement/orange_money', ['is_active' => '1', 'account_number' => '622000000', 'account_name' => 'Nimaga']);
        $this->store = Store::firstOrFail();
        auth()->logout();
    }

    private function order(string $type, string $phone = '621000001'): Order
    {
        $variant = $this->store->products()->first()->variants()->first();
        $this->post('/nimstore/panier', ['variant_id' => $variant->id]);
        $this->post('/nimstore/commande', [
            'name' => 'Mamadou', 'phone' => $phone,
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->first()->id,
            'payment_method_id' => $this->store->paymentMethods()->where('type', $type)->first()->id,
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    public function test_customer_uploads_proof_and_only_the_merchant_can_see_it(): void
    {
        $order = $this->order(PaymentMethod::ORANGE_MONEY);
        $this->get(route('invoice.show', $order))->assertSee('Capture de votre paiement')->assertSee('+224 622 00 00 00');

        $this->post(route('invoice.proof', $order), [
            'proof' => UploadedFile::fake()->image('recu.jpg', 1080, 2400), 'reference' => 'MP2610.1234',
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('confirming', $order->payment_status);
        $proof = PaymentProof::firstOrFail();
        Storage::disk('local')->assertExists($proof->path);
        Storage::disk('public')->assertMissing($proof->path); // jamais dans le dossier public

        $this->get(route('invoice.show', $order))->assertSee('Capture reçue');

        // Le commerçant voit la capture et la commande dans « À vérifier ».
        $this->actingAs($this->merchant)->get('/admin/commandes?paiement=confirming')->assertSee('#1001')->assertSee('À vérifier (1)');
        $this->actingAs($this->merchant)->get("/admin/commandes/{$order->id}")->assertSee('Captures de paiement')->assertSee('MP2610.1234');
        $this->actingAs($this->merchant)->get("/admin/commandes/{$order->id}/preuves/{$proof->id}")->assertOk();

        // Un autre commerçant, ou un visiteur, ne peut pas.
        $other = User::factory()->create();
        (new Store(['name' => 'B', 'slug' => 'b-shop', 'whatsapp_number' => '224621111111']))->user()->associate($other)->save();
        $this->actingAs($other)->get("/admin/commandes/{$order->id}/preuves/{$proof->id}")->assertNotFound();
        auth()->logout();
        $this->get("/admin/commandes/{$order->id}/preuves/{$proof->id}")->assertRedirect(route('login'));
    }

    public function test_no_proof_for_cash_on_delivery_and_files_must_be_images(): void
    {
        $cod = $this->order(PaymentMethod::CASH_ON_DELIVERY);
        $this->get(route('invoice.show', $cod))->assertDontSee('Capture de votre paiement');
        $this->post(route('invoice.proof', $cod), ['proof' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();

        $om = $this->order(PaymentMethod::ORANGE_MONEY, '621000002');
        $this->post(route('invoice.proof', $om), ['proof' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])
            ->assertSessionHasErrors('proof');
        $this->assertSame('unpaid', $om->fresh()->payment_status);
    }

    public function test_merchant_blocks_cash_on_delivery_for_a_customer_from_their_page(): void
    {
        $this->order(PaymentMethod::CASH_ON_DELIVERY);
        $customer = $this->store->customers()->firstOrFail();

        $this->actingAs($this->merchant)->get('/admin/clients?q=621')->assertSee('Mamadou')->assertSee('1 commande');
        $this->actingAs($this->merchant)->get("/admin/clients/{$customer->id}")->assertSee('Autorisé');
        $this->actingAs($this->merchant)->post("/admin/clients/{$customer->id}/paiement-livraison")->assertSessionHas('status');
        $this->assertTrue($customer->fresh()->cash_on_delivery_blocked);
        auth()->logout();

        // Sa prochaine commande en paiement à la livraison est refusée ; Orange Money passe.
        $variant = $this->store->products()->first()->variants()->first();
        $this->post('/nimstore/panier', ['variant_id' => $variant->id]);
        $base = ['name' => 'Mamadou', 'phone' => '621 00 00 01', 'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->first()->id];
        $this->post('/nimstore/commande', $base + ['payment_method_id' => $this->store->cashOnDelivery()->id])->assertSessionHasErrors('payment_method_id');
        $om = $this->store->paymentMethods()->where('type', PaymentMethod::ORANGE_MONEY)->first();
        $this->post('/nimstore/commande', $base + ['payment_method_id' => $om->id])->assertSessionHasNoErrors();
    }
}
