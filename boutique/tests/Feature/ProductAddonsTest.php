<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Suppléments payants d'un produit (ex. flocage) : réglage, fiche, panier, commande, factures. */
class ProductAddonsTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    private Product $jersey;

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

        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'Maillot Syli 2026', 'price' => '250 000',
            'variants' => [['name' => 'M', 'price' => '', 'stock' => '5'], ['name' => 'L', 'price' => '', 'stock' => '2']],
            'addons' => [
                ['key' => '', 'name' => 'Flocage', 'price' => '50 000', 'text' => 'Nom et numéro au dos'],
                ['key' => '', 'name' => 'Emballage cadeau', 'price' => '', 'text' => ''],
                ['key' => '', 'name' => '', 'price' => '9', 'text' => ''], // ligne vide : ignorée
            ],
        ])->assertSessionHasNoErrors();
        $this->jersey = $this->store->products()->with('variants')->firstOrFail();
        auth()->logout();
    }

    private function addon(string $name): array
    {
        return $this->jersey->fresh()->addonList()->firstWhere('name', $name);
    }

    private function variant(string $name)
    {
        return $this->jersey->variants->firstWhere('name', $name);
    }

    private function addToCart(string $size, array $addons = [], array $texts = [], int $qty = 1)
    {
        return $this->from('/nimstore/p/'.$this->jersey->slug)->post('/nimstore/panier', [
            'variant_id' => $this->variant($size)->id,
            'quantity' => $qty,
            'addons' => array_map(fn ($n) => $this->addon($n)['key'], $addons),
            'addon_text' => collect($texts)->mapWithKeys(fn ($t, $n) => [$this->addon($n)['key'] => $t])->all(),
        ]);
    }

    private function checkout(): Order
    {
        $this->post('/nimstore/commande', [
            'name' => 'Mamadou Diallo', 'phone' => '621 00 00 01',
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $this->store->cashOnDelivery()->id,
        ])->assertSessionHasNoErrors();

        return $this->store->orders()->latest('id')->with('items')->firstOrFail();
    }

    // ------------------------------------------------------------- réglage

    public function test_merchant_sets_up_addons_and_keys_survive_edits(): void
    {
        $addons = $this->jersey->addonList()->values();
        $this->assertCount(2, $addons);
        $this->assertSame(['name' => 'Flocage', 'price' => 50000, 'text' => 'Nom et numéro au dos'], collect($addons[0])->except('key')->all());
        $this->assertSame(['name' => 'Emballage cadeau', 'price' => 0, 'text' => null], collect($addons[1])->except('key')->all());
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $addons[0]['key']);
        $this->assertNotSame($addons[0]['key'], $addons[1]['key']);

        // Renommer le flocage garde sa clé (les paniers en cours y font référence) ; vider un nom retire le supplément.
        $this->actingAs($this->merchant)->get('/admin/produits/'.$this->jersey->id)->assertOk()
            ->assertSee('Suppléments payants')->assertSee('value="Flocage"', false)->assertSee('value="50 000"', false);
        $this->put('/admin/produits/'.$this->jersey->id, [
            'name' => 'Maillot Syli 2026', 'price' => '250000', 'is_visible' => '1',
            'variants' => $this->jersey->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'stock' => $v->stock])->all(),
            'addons' => [
                ['key' => $addons[0]['key'], 'name' => 'Flocage officiel', 'price' => '60000', 'text' => 'Nom et numéro'],
                ['key' => $addons[1]['key'], 'name' => '', 'price' => '', 'text' => ''],
                ['key' => 'inventee', 'name' => 'Patch Ligue', 'price' => '15000', 'text' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $after = $this->jersey->fresh()->addonList()->values();
        $this->assertSame([$addons[0]['key'], 'Flocage officiel', 60000], [$after[0]['key'], $after[0]['name'], $after[0]['price']]);
        $this->assertSame('Patch Ligue', $after[1]['name']);
        $this->assertNotSame('inventee', $after[1]['key'], 'une clé inconnue envoyée par le formulaire est remplacée');
        $this->assertCount(2, $after);
    }

    public function test_addon_rows_are_validated(): void
    {
        $base = ['name' => 'Maillot Syli 2026', 'price' => '250000', 'is_visible' => '1',
            'variants' => $this->jersey->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'stock' => $v->stock])->all()];
        $this->actingAs($this->merchant);

        $this->put('/admin/produits/'.$this->jersey->id, $base + ['addons' => [
            ['name' => 'Flocage', 'price' => '1'], ['name' => 'flocage', 'price' => '2'],
        ]])->assertSessionHasErrors(['addon_rows.1.name' => 'Deux suppléments portent le même nom.']);

        $this->put('/admin/produits/'.$this->jersey->id, $base + ['addons' => array_map(fn ($i) => ['name' => 'Option '.$i, 'price' => '1'], range(1, 11))])
            ->assertSessionHasErrors('addon_rows');

        $this->put('/admin/produits/'.$this->jersey->id, $base + ['addons' => [['name' => 'Flocage', 'price' => '1', 'text' => str_repeat('x', 61)]]])
            ->assertSessionHasErrors('addon_rows.0.text');

        $this->assertSame('Flocage', $this->jersey->fresh()->addonList()->first()['name'], 'rien n\'a changé après une erreur');
    }

    // ------------------------------------------------------------- vitrine et panier

    public function test_product_page_offers_the_addons(): void
    {
        $page = $this->get('/nimstore/p/'.$this->jersey->slug)->assertOk();
        $page->assertSee('Suppléments');
        $page->assertSee('Flocage');
        $page->assertSee('+ 50 000 GNF');
        $page->assertSee('Nom et numéro au dos');
        $page->assertSee('name="addon_text['.$this->addon('Flocage')['key'].']"', false);
        $page->assertSee('Gratuit');
    }

    public function test_addon_price_and_text_follow_the_line_to_the_order(): void
    {
        $this->addToCart('L', ['Flocage'], ['Flocage' => "  DIALLO\n 10 "])->assertSessionHasNoErrors();

        $this->get('/nimstore/panier')->assertOk()
            ->assertSee('Flocage « DIALLO 10 » (+50 000 GNF)')
            ->assertSee("300 000 GNF l'unité", false);

        $order = $this->checkout();
        $item = $order->items->sole();
        $this->assertSame(300000, $item->unit_price);
        $this->assertSame(300000, $item->line_total);
        $this->assertSame([['name' => 'Flocage', 'price' => 50000, 'text' => 'DIALLO 10']], $item->options);
        $this->assertSame(300000, $order->subtotal);
        $this->assertSame(1, (int) $this->variant('L')->fresh()->stock, 'le stock suit la taille, pas le supplément');

        $message = WhatsAppMessage::forOrder($order);
        $this->assertStringContainsString('1 × Maillot Syli 2026 (L) + Flocage « DIALLO 10 » (+50 000 GNF) — 300 000 GNF', $message);

        $this->get(route('invoice.show', $order))->assertOk()->assertSee('Flocage « DIALLO 10 » (+50 000 GNF)');
        $this->actingAs($this->merchant)->get('/admin/commandes/'.$order->id)->assertOk()->assertSee('Flocage « DIALLO 10 »');
        $this->get('/admin/commandes/'.$order->id.'/facture')->assertOk()->assertSee('Flocage « DIALLO 10 »');
        $this->assertStringContainsString('1 × Maillot Syli 2026 (L) + Flocage « DIALLO 10 » (+50 000 GNF)', $this->get('/admin/commandes/export')->streamedContent());

        // Un changement de prix plus tard ne touche pas la commande passée.
        $this->put('/admin/produits/'.$this->jersey->id, [
            'name' => 'Maillot Syli 2026', 'price' => '250000', 'is_visible' => '1',
            'variants' => $this->jersey->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'stock' => $v->fresh()->stock])->all(),
            'addons' => [['key' => $this->addon('Flocage')['key'], 'name' => 'Flocage', 'price' => '80000', 'text' => 'Nom']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(300000, $order->items()->sole()->unit_price);
    }

    public function test_different_texts_make_different_lines_and_same_text_adds_up(): void
    {
        $this->addToCart('M', ['Flocage'], ['Flocage' => 'CAMARA 9']);
        $this->addToCart('M', ['Flocage'], ['Flocage' => 'SOW 7']);
        $this->addToCart('M', ['Flocage'], ['Flocage' => 'CAMARA 9']);
        $this->addToCart('M');
        $this->addToCart('M', ['Emballage cadeau']);

        $order = $this->checkout();
        $lines = $order->items->map(fn ($i) => [$i->quantity, $i->optionsLabel(), $i->unit_price])->all();
        $this->assertEqualsCanonicalizing([
            [2, 'Flocage « CAMARA 9 » (+50 000 GNF)', 300000],
            [1, 'Flocage « SOW 7 » (+50 000 GNF)', 300000],
            [1, '', 250000],
            [1, 'Emballage cadeau', 250000],
        ], $lines);
        $this->assertSame(1400000, $order->subtotal);
        $this->assertSame(0, (int) $this->variant('M')->fresh()->stock);
    }

    public function test_text_is_required_and_addons_must_belong_to_the_product(): void
    {
        $this->addToCart('L', ['Flocage'], ['Flocage' => '   '])
            ->assertSessionHasErrors(['addon_text.'.$this->addon('Flocage')['key'] => 'Indiquez le texte pour « Flocage » : Nom et numéro au dos.']);
        $this->addToCart('L', ['Flocage'], ['Flocage' => str_repeat('A', 61)])->assertSessionHasErrors();

        // Clé d'un supplément d'un autre produit (ou inventée) : refusée.
        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'Ballon', 'price' => '90000', 'addons' => [['name' => 'Gonflage', 'price' => '5000']],
        ])->assertSessionHasNoErrors();
        auth()->logout();
        $foreignKey = $this->store->products()->where('name', 'Ballon')->firstOrFail()->addonList()->first()['key'];
        $this->from('/nimstore')->post('/nimstore/panier', ['variant_id' => $this->variant('L')->id, 'addons' => [$foreignKey]])
            ->assertSessionHasErrors('addons');

        $this->assertSame([], session('cart.'.$this->store->id, []), 'rien n\'est ajouté au panier');

        // Sans JavaScript, un texte tapé sans cocher la case n'est pas perdu en silence.
        $this->addToCart('L', [], ['Flocage' => 'BAH 4'])
            ->assertSessionHasErrors(['addon_text.'.$this->addon('Flocage')['key'] => 'Cochez « Flocage » pour l\'ajouter, ou videz le texte.']);
        $this->addToCart('L', [], ['Flocage' => '  '])->assertSessionHasNoErrors();
        $this->assertSame('', $this->checkout()->items->sole()->optionsLabel());
    }

    public function test_stock_counts_every_line_of_the_same_size(): void
    {
        $this->addToCart('L', ['Flocage'], ['Flocage' => 'A 1'])->assertSessionHasNoErrors();
        $this->addToCart('L', ['Flocage'], ['Flocage' => 'B 2'])->assertSessionHasNoErrors();
        $this->addToCart('L')->assertSessionHasErrors(['quantity' => 'Plus que 2 en stock.']);

        // Le bouton « + » est grisé sur les deux lignes : le stock de L est atteint.
        $cart = $this->get('/nimstore/panier')->assertOk()->getContent();
        $this->assertSame(2, preg_match_all('/aria-label="Ajouter un"\s+disabled/', $cart));
        $this->assertSame(2, $this->checkout()->items->sum('quantity'));
    }

    public function test_cart_lines_change_by_line_and_follow_the_merchant_changes(): void
    {
        $this->addToCart('M', ['Flocage'], ['Flocage' => 'KEITA 11'], 2);
        $this->addToCart('M');
        $raw = session('cart.'.$this->store->id);
        $flocked = collect(array_keys($raw))->first(fn ($k) => str_contains($k, '-'));

        $this->patch('/nimstore/panier', ['line' => $flocked, 'quantity' => 1])->assertRedirect('/nimstore/panier');
        $this->patch('/nimstore/panier', ['variant_id' => $this->variant('M')->id, 'quantity' => 0]); // ancienne page du panier
        $this->assertSame([$flocked => 1], array_map(fn ($l) => $l['qty'], session('cart.'.$this->store->id)));

        // Le commerçant retire le flocage : la ligne reste, sans le supplément ni son prix.
        $this->actingAs($this->merchant)->put('/admin/produits/'.$this->jersey->id, [
            'name' => 'Maillot Syli 2026', 'price' => '250000', 'is_visible' => '1',
            'variants' => $this->jersey->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'stock' => $v->stock])->all(),
            'addons' => [],
        ])->assertSessionHasNoErrors();
        auth()->logout();
        $this->assertNull($this->jersey->fresh()->addons);
        $item = $this->checkout()->items->sole();
        $this->assertSame([250000, null], [$item->unit_price, $item->options]);
    }

    public function test_an_addon_whose_text_became_required_leaves_the_line(): void
    {
        // Le flocage ne demandait pas de texte au moment de l'ajout…
        $key = $this->addon('Flocage')['key'];
        $this->jersey->update(['addons' => [['key' => $key, 'name' => 'Flocage', 'price' => 50000, 'text' => null]]]);
        $this->addToCart('M', ['Flocage'])->assertSessionHasNoErrors();

        // … puis le commerçant demande « Nom et numéro » : pas de flocage payé sans nom.
        $this->jersey->update(['addons' => [['key' => $key, 'name' => 'Flocage', 'price' => 50000, 'text' => 'Nom et numéro']]]);
        $this->get('/nimstore/panier')->assertOk()->assertDontSee('Flocage')->assertSee('250 000 GNF');
        $item = $this->checkout()->items->sole();
        $this->assertSame([250000, null], [$item->unit_price, $item->options]);
    }

    public function test_after_an_error_the_page_shows_the_chosen_size(): void
    {
        // L : 2 en stock. 3 demandés → erreur ; la page revient sur L avec son stock, pas celui de M.
        $this->addToCart('L', [], [], 3)->assertSessionHasErrors(['quantity' => 'Plus que 2 en stock.']);
        $page = $this->get('/nimstore/p/'.$this->jersey->slug)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="'.$this->variant('L')->id.'"[^>]*checked/s', $page);
        $this->assertStringContainsString('max="2"', $page);
        $this->assertMatchesRegularExpression('/id="low-stock"\s*>Plus que&nbsp;<span>2</', $page);
    }

    public function test_a_cart_saved_before_the_addons_still_works(): void
    {
        // Ancien format de session : [variant_id => quantité].
        $this->withSession(['cart.'.$this->store->id => [$this->variant('M')->id => 2]]);

        $this->get('/nimstore/panier')->assertOk()->assertSee('500 000 GNF');
        $this->patch('/nimstore/panier', ['variant_id' => $this->variant('M')->id, 'quantity' => 3]);
        $this->assertSame(750000, $this->checkout()->subtotal);
    }

    public function test_orders_without_addons_look_the_same(): void
    {
        $this->addToCart('M');
        $order = $this->checkout();
        $this->assertNull($order->items->sole()->options);
        $this->assertStringContainsString('1 × Maillot Syli 2026 (M) — 250 000 GNF', WhatsAppMessage::forOrder($order));
        $this->get(route('invoice.show', $order))->assertOk()->assertSee('Maillot Syli 2026 (M)')->assertDontSee('text-text-muted">+ ', false);
    }
}
