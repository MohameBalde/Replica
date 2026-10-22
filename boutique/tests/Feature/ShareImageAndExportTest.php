<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\StatusImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Lot 3 : application installable, image pour le statut, export des commandes. */
class ShareImageAndExportTest extends TestCase
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

        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'Baskets', 'price' => '150000', 'is_visible' => '1',
            'variants' => [['name' => '42', 'price' => '', 'stock' => '10'], ['name' => '43', 'price' => '160000', 'stock' => '10']],
        ])->assertSessionHasNoErrors();
        $this->product = $this->store->products()->with('variants')->firstOrFail();
    }

    private function order(string $name = 'Mariama', string $phone = '621 00 00 01', int $qty = 2): Order
    {
        $this->post('/nimstore/panier', ['variant_id' => $this->product->variants->first()->id, 'quantity' => $qty]);
        $this->post('/nimstore/commande', [
            'name' => $name, 'phone' => $phone,
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $this->store->cashOnDelivery()->id,
        ])->assertSessionHasNoErrors();

        return $this->store->orders()->latest('id')->firstOrFail();
    }

    /** Une autre boutique, avec son produit et sa commande. */
    private function otherStore(): array
    {
        auth()->logout();
        $this->post('/inscription', [
            'name' => 'Nimaga', 'phone' => '622 33 44 55',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'Autre', 'slug' => 'autre', 'whatsapp_number' => '622 33 44 55', 'city' => 'Kankan']);
        $other = User::where('phone', '224622334455')->firstOrFail();
        $store = Store::where('slug', 'autre')->firstOrFail();
        $this->actingAs($other)->post('/admin/produits', ['name' => 'Sac secret', 'price' => '90000', 'is_visible' => '1', 'stock' => '5'])
            ->assertSessionHasNoErrors();
        $product = $store->products()->with('variants')->firstOrFail();
        $this->post('/autre/panier', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);
        $this->post('/autre/commande', [
            'name' => 'Client secret', 'phone' => '623 00 00 09',
            'delivery_method_id' => $store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $store->cashOnDelivery()->id,
        ])->assertSessionHasNoErrors();

        return [$store, $product];
    }

    /** Lignes du CSV, sans le BOM, découpées comme le ferait un tableur. */
    private function csv($response): array
    {
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}", $body, 'BOM UTF-8 pour Excel');
        $lines = preg_split('/\r?\n/', trim(substr($body, 3)));

        return array_map(fn ($l) => str_getcsv($l, ';', '"', ''), $lines);
    }

    // ------------------------------------------------------- application installable

    public function test_manifest_describes_an_installable_app_with_existing_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Wanllan', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('fr', $manifest['lang']);
        $this->assertStringStartsWith('/admin', $manifest['start_url']);
        $this->assertSame('#3730a3', $manifest['theme_color']);
        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path);
            [$w, $h] = getimagesize($path);
            $this->assertSame($icon['sizes'], "{$w}x{$h}");
            $sizes[] = $icon['sizes'].' '.($icon['purpose'] ?? 'any');
        }
        // Chrome exige au moins une icône 192 et une 512 pour proposer l'installation.
        $this->assertContains('192x192 any', $sizes);
        $this->assertContains('512x512 any', $sizes);
        $this->assertContains('512x512 maskable', $sizes);

        $this->assertFileExists(public_path('sw.js'));
        $this->assertStringContainsString('/offline.html', file_get_contents(public_path('sw.js')));
        $this->assertStringContainsString('Réessayer', file_get_contents(public_path('offline.html')));
    }

    public function test_merchant_pages_link_the_manifest_but_the_shop_does_not(): void
    {
        $admin = $this->actingAs($this->merchant)->get('/admin')->assertOk();
        $admin->assertSee('rel="manifest"', false);
        $admin->assertSee("serviceWorker.register('/sw.js')", false);
        $admin->assertSee('data-install', false);

        // La vitrine est celle du commerçant, pas l'app Wanllan : pas d'invitation à installer Wanllan aux clients.
        // (Laravel donne une variable $app à toutes les vues : l'option s'appelle « installable » pour ne pas la croiser.)
        foreach (['/nimstore', '/nimstore/panier', '/connexion'] as $url) {
            auth()->logout();
            $page = $this->get($url)->assertOk();
            $page->assertDontSee('rel="manifest"', false);
            $page->assertDontSee('serviceWorker', false);
        }
        $shop = $this->get('/nimstore')->assertOk();
        $shop->assertDontSee('rel="manifest"', false);
        $shop->assertDontSee('serviceWorker', false);
    }

    // ------------------------------------------------------------- image de statut

    public function test_store_status_image_is_a_vertical_jpeg(): void
    {
        $response = $this->actingAs($this->merchant)->get('/admin/partager/image')->assertOk();

        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        [$w, $h, $type] = getimagesizefromstring($response->getContent());
        $this->assertSame([1080, 1920, IMAGETYPE_JPEG], [$w, $h, $type]);

        $download = $this->get('/admin/partager/image?download=1')->assertOk();
        $this->assertSame('attachment; filename="statut-nimstore.jpg"', $download->headers->get('Content-Disposition'));

        $this->get('/admin/partager')->assertOk()
            ->assertSee(route('admin.share.image', ['download' => 1]), false)
            ->assertSee(route('admin.share.image', ['product' => $this->product->id, 'download' => 1]), false);
    }

    public function test_product_status_image_uses_the_product_photo(): void
    {
        Storage::fake('public');
        $this->actingAs($this->merchant)->put('/admin/produits/'.$this->product->id, [
            'name' => 'Baskets', 'price' => '150000', 'is_visible' => '1',
            'variants' => $this->product->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'stock' => $v->stock])->all(),
            'photos' => [UploadedFile::fake()->image('baskets.jpg', 1200, 800)],
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($this->product->fresh()->firstImage, 'la photo est enregistrée');

        $response = $this->get('/admin/partager/image/'.$this->product->id.'?download=1')->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('attachment; filename="statut-nimstore-'.$this->product->slug.'.jpg"', $response->headers->get('Content-Disposition'));
        [$w, $h] = getimagesizefromstring($response->getContent());
        $this->assertSame([1080, 1920], [$w, $h]);
    }

    public function test_status_image_drops_characters_the_font_cannot_draw(): void
    {
        // Sinon GD dessine des carrés « NO GLYPH » à la place des emoji et des drapeaux.
        $this->assertSame('NimStore', StatusImage::drawable('Nim🔥Store 🇬🇳'));
        $this->assertSame('Chaussures et parfums', StatusImage::drawable('Chaussures 👟 et parfums ✨'));
        $this->assertSame('N° 1 · Été « promo » … ©', StatusImage::drawable('N° 1 · Été « promo » … ©'));

        $this->store->update(['name' => '🔥🔥']);
        $response = $this->actingAs($this->merchant)->get('/admin/partager/image')->assertOk();
        [$w, $h] = getimagesizefromstring($response->getContent());
        $this->assertSame([1080, 1920], [$w, $h]);
    }

    public function test_status_image_works_without_any_product(): void
    {
        $this->product->delete();

        $response = $this->actingAs($this->merchant)->get('/admin/partager/image')->assertOk();
        [$w, $h] = getimagesizefromstring($response->getContent());
        $this->assertSame([1080, 1920], [$w, $h]);
    }

    public function test_status_image_is_private_and_limited_to_visible_products_of_the_store(): void
    {
        auth()->logout();
        $this->get('/admin/partager/image')->assertRedirect('/connexion');

        [, $foreign] = $this->otherStore();
        $this->actingAs($this->merchant)->get('/admin/partager/image/'.$foreign->id)->assertNotFound();

        $this->product->update(['is_visible' => false]);
        $this->get('/admin/partager/image/'.$this->product->id)->assertNotFound();
    }

    // ------------------------------------------------------------ export des commandes

    public function test_export_lists_the_orders_for_a_spreadsheet(): void
    {
        $order = $this->order('Mariama', '621 00 00 01', 2);
        $this->actingAs($this->merchant)->patch('/admin/commandes/'.$order->id.'/statuts', [
            'status' => 'confirmed', 'payment_status' => 'unpaid', 'fulfillment_status' => 'unfulfilled',
        ])->assertSessionHasNoErrors();
        $order->update(['internal_note' => 'Rappeler demain']);

        $response = $this->actingAs($this->merchant)->get('/admin/commandes/export')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=commandes-nimstore-', $response->headers->get('Content-Disposition'));

        $rows = $this->csv($response);
        $this->assertCount(2, $rows);
        $head = array_combine($rows[0], $rows[1]);
        $this->assertSame((string) $order->number, $head['N°']);
        $this->assertSame($order->created_at->timezone('Africa/Conakry')->format('d/m/Y H:i'), $head['Date']);
        $this->assertSame('Mariama', $head['Client']);
        $this->assertSame('224 621 00 00 01', $head['Téléphone']);
        $this->assertSame('Confirmée', $head['Commande']);
        $this->assertSame('Non payée', $head['Paiement']);
        $this->assertSame('2 × Baskets (42)', $head['Articles']);
        // Montants en nombres entiers : le tableur peut les additionner.
        $this->assertSame('300000', $head['Sous-total (GNF)']);
        $this->assertSame((string) $order->fresh()->total, $head['Total (GNF)']);
        $this->assertSame('Direct', $head['Source']);
        $this->assertSame('Rappeler demain', $head['Note interne']);
    }

    public function test_export_follows_the_list_filters_and_stays_in_the_store(): void
    {
        $kept = $this->order('Mariama');
        $cancelled = $this->order('Fanta', '621 00 00 02');
        $this->actingAs($this->merchant)->post('/admin/commandes/'.$cancelled->id.'/annuler');
        $this->otherStore();

        $numbers = fn ($url) => array_column(array_slice($this->csv($this->actingAs($this->merchant)->get($url)->assertOk()), 1), 0);

        $this->assertEqualsCanonicalizing([(string) $kept->number, (string) $cancelled->number], $numbers('/admin/commandes/export'));
        $this->assertSame([(string) $cancelled->number], $numbers('/admin/commandes/export?statut=cancelled'));
        // « À la livraison » exclut les annulées, comme la liste.
        $this->assertSame([(string) $kept->number], $numbers('/admin/commandes/export?paiement=cod'));

        $all = $this->actingAs($this->merchant)->get('/admin/commandes/export')->streamedContent();
        $this->assertStringNotContainsString('Client secret', $all);
        $this->assertStringNotContainsString('Sac secret', $all);
    }

    public function test_export_neutralises_spreadsheet_formulas(): void
    {
        $this->order('=HYPERLINK("http://example.test","clic")');

        $rows = $this->csv($this->actingAs($this->merchant)->get('/admin/commandes/export')->assertOk());
        $head = array_combine($rows[0], $rows[1]);
        $this->assertSame('\'=HYPERLINK("http://example.test","clic")', $head['Client']);
    }

    public function test_export_button_keeps_the_filters_and_guests_are_sent_to_login(): void
    {
        $this->actingAs($this->merchant)->get('/admin/commandes')->assertOk()->assertDontSee('/admin/commandes/export', false);

        $this->order();
        $this->actingAs($this->merchant)->get('/admin/commandes?statut=pending&paiement=cod')->assertOk()
            ->assertSee(e(route('admin.orders.export', ['statut' => 'pending', 'paiement' => 'cod'])), false);

        auth()->logout();
        $this->get('/admin/commandes/export')->assertRedirect('/connexion');
    }
}
