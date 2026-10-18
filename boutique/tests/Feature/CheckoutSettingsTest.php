<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\WhatsAppMessage;
use App\Support\OpeningHours;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    private Product $product;

    /** Lundi à samedi 8 h – 20 h, dimanche fermé. */
    private const WEEK = [
        1 => ['open_day' => '1', 'open' => '08:00', 'close' => '20:00'], 2 => ['open_day' => '1', 'open' => '08:00', 'close' => '20:00'],
        3 => ['open_day' => '1', 'open' => '08:00', 'close' => '20:00'], 4 => ['open_day' => '1', 'open' => '08:00', 'close' => '20:00'],
        5 => ['open_day' => '1', 'open' => '08:00', 'close' => '20:00'], 6 => ['open_day' => '1', 'open' => '08:30', 'close' => '20:00'],
        7 => ['open' => '08:00', 'close' => '20:00'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00', 'Africa/Conakry')); // un lundi

        $this->post('/inscription', [
            'name' => 'Aïssatou', 'phone' => '620 11 22 33',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620 11 22 33', 'city' => 'Conakry']);
        $this->merchant = User::where('phone', '224620112233')->firstOrFail();
        $this->store = Store::where('slug', 'nimstore')->firstOrFail();
        $this->actingAs($this->merchant)->post('/admin/produits', ['name' => 'Baskets', 'price' => '150000'])->assertSessionHasNoErrors();
        $this->product = $this->store->products()->with('variants')->firstOrFail();
    }

    private function settings(array $data)
    {
        return $this->actingAs($this->merchant)->from('/admin/reglages/commande')->put('/admin/reglages/commande', $data);
    }

    private function checkout(array $extra = [])
    {
        auth()->logout();
        $this->post('/nimstore/panier', ['variant_id' => $this->product->variants->first()->id, 'quantity' => 1]);

        $labels = array_column($this->store->fresh()->checkoutQuestions(), 'label');

        return $this->from('/nimstore/commande')->post('/nimstore/commande', $extra + ['answers_q' => $labels] + [
            'name' => 'Mariama Bah', 'phone' => '622 33 44 55',
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $this->store->cashOnDelivery()->id,
        ]);
    }

    public function test_questions_are_saved_from_the_new_settings_tab(): void
    {
        $this->actingAs($this->merchant)->get('/admin/reglages')->assertSee(route('admin.checkout.edit'), false);
        $this->actingAs($this->merchant)->get('/admin/reglages/commande')->assertOk()->assertSee('Questions au client')->assertSee('Point de repère');

        $this->settings(['questions' => [
            ['label' => 'Point de repère', 'required' => '1'],
            ['label' => '  '],
            ['label' => 'Taille du maillot'],
        ]])->assertSessionHasNoErrors()->assertSessionHas('status', 'Réglages de commande enregistrés.');

        $this->assertSame([['label' => 'Point de repère', 'required' => true], ['label' => 'Taille du maillot', 'required' => false]], $this->store->fresh()->checkoutQuestions());
        $this->assertNull($this->store->fresh()->opening_hours);

        $this->settings(['questions' => [['label' => str_repeat('x', 81)]]])->assertSessionHasErrors(['questions.0.label' => 'Une question fait au plus 80 caractères.']);
    }

    public function test_customer_answers_travel_with_the_order_everywhere(): void
    {
        $this->settings(['questions' => [['label' => 'Point de repère', 'required' => '1'], ['label' => 'Taille du maillot']]]);

        auth()->logout();
        $this->post('/nimstore/panier', ['variant_id' => $this->product->variants->first()->id, 'quantity' => 1]);
        $this->get('/nimstore/commande')->assertSee('Point de repère')->assertSeeInOrder(['Taille du maillot', '(facultatif)']);

        $this->checkout(['answers' => ['', 'XL']])->assertSessionHasErrors(['answers.0' => 'Répondez à « Point de repère ».']);
        $this->assertSame(0, Order::count());

        $this->checkout(['answers' => ['Près de la mosquée de Kipé', '']])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame([['q' => 'Point de repère', 'a' => 'Près de la mosquée de Kipé']], $order->answers); // réponse vide non gardée

        $this->assertStringContainsString("Point de repère : Près de la mosquée de Kipé\n", WhatsAppMessage::forOrder($order));
        $this->get(route('invoice.show', $order))->assertSee('Point de repère :')->assertSee('Près de la mosquée de Kipé');
        $this->actingAs($this->merchant)->get("/admin/commandes/{$order->id}")->assertSee('Près de la mosquée de Kipé');
        $this->actingAs($this->merchant)->get("/admin/commandes/{$order->id}/facture")->assertSee('Près de la mosquée de Kipé');

        // La question change ensuite : la commande garde la question posée à l'époque.
        $this->settings(['questions' => [['label' => 'Quartier']]]);
        $this->assertSame('Point de repère', $order->fresh()->answers[0]['q']);
    }

    public function test_hours_are_saved_checked_and_can_be_switched_off(): void
    {
        $this->settings(['use_hours' => '1', 'hours' => self::WEEK])->assertSessionHasNoErrors();
        $hours = $this->store->fresh()->openingHours();
        $this->assertSame(['open' => '08:30', 'close' => '20:00'], $hours->slot(6));
        $this->assertNull($hours->slot(7));
        $this->assertSame('Samedi : 8 h 30 – 20 h', $hours->lines()[5]);
        $this->assertSame('Dimanche : fermé', $hours->lines()[6]);

        $bad = self::WEEK;
        $bad[2]['close'] = '07:00';
        $this->settings(['use_hours' => '1', 'hours' => $bad])->assertSessionHasErrors(['hours.2' => 'Mardi : l\'heure de fermeture doit venir après l\'ouverture.']);
        $this->settings(['use_hours' => '1', 'hours' => [1 => ['open' => '08:00', 'close' => '20:00']]])->assertSessionHasErrors('hours');

        $this->settings(['closed_outside_hours' => '1']); // horaires désactivés : rien n'est bloqué
        $this->assertNull($this->store->fresh()->opening_hours);
        $this->assertFalse($this->store->fresh()->closed_outside_hours);
    }

    public function test_status_tells_when_the_store_opens_next(): void
    {
        $hours = new OpeningHours(collect(self::WEEK)->map(fn ($d) => isset($d['open_day']) ? ['open' => $d['open'], 'close' => $d['close']] : null)->all());
        $at = fn (string $when) => $hours->status(CarbonImmutable::parse($when, 'Africa/Conakry'))['label'];

        $this->assertSame('Ouvert jusqu\'à 20 h', $at('2026-10-19 10:00'));        // lundi
        $this->assertSame('Fermé · ouvre aujourd\'hui à 8 h', $at('2026-10-19 07:15'));
        $this->assertSame('Fermé · ouvre demain à 8 h 30', $at('2026-10-23 20:00')); // vendredi soir, samedi 8 h 30
        $this->assertSame('Fermé · ouvre lundi à 8 h', $at('2026-10-24 21:00'));   // samedi soir, dimanche fermé
        $this->assertNull((new OpeningHours(null))->status(CarbonImmutable::now()));
        $this->assertTrue((new OpeningHours(null))->isOpenAt(CarbonImmutable::parse('2026-10-25 03:00')));
    }

    public function test_outside_hours_orders_are_taken_with_a_note_unless_the_merchant_refuses_them(): void
    {
        $this->settings(['use_hours' => '1', 'hours' => self::WEEK]);
        $this->travelTo(CarbonImmutable::parse('2026-10-19 21:00', 'Africa/Conakry'));

        $this->get('/nimstore')->assertSee('Fermé · ouvre demain à 8 h.')->assertSee('Vous pouvez commander : la boutique vous répondra à l\'ouverture.');
        auth()->logout();
        $this->post('/nimstore/panier', ['variant_id' => $this->product->variants->first()->id, 'quantity' => 1]);
        $this->get('/nimstore/commande')->assertSee('NimStore est fermée en ce moment. Vous pouvez commander : la boutique vous répondra demain à 8 h.');
        $this->checkout()->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());

        $this->settings(['use_hours' => '1', 'hours' => self::WEEK, 'closed_outside_hours' => '1']);
        auth()->logout();
        $this->get('/nimstore')->assertSee('Les commandes reprendront à l\'ouverture.');
        $this->get(route('shop.product', [$this->store, $this->product]))->assertSee('Boutique fermée');
        $this->post('/nimstore/panier', ['variant_id' => $this->product->variants->first()->id, 'quantity' => 1]); // ajouté plus tôt dans la journée
        $this->get('/nimstore/panier')->assertSee('La boutique est fermée : elle ouvre demain à 8 h.')->assertDontSee(route('shop.checkout', $this->store).'"', false);
        $this->get('/nimstore/commande')->assertSee('La boutique est fermée : elle ouvre demain à 8 h.');
        $this->checkout()->assertSessionHasErrors(['cart' => 'La boutique est fermée : elle ouvre demain à 8 h. Revenez à ce moment-là pour commander.']);
        $this->assertSame(1, Order::count());

        $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00', 'Africa/Conakry'));
        $this->get('/nimstore')->assertSee('>Ouvert</span>', false)->assertSee('Horaires · ouvert jusqu\'à 20 h')->assertDontSee('Les commandes reprendront');
        $this->checkout()->assertSessionHasNoErrors();
        $this->assertSame(2, Order::count());
    }

    public function test_answers_are_never_filed_under_a_question_that_changed(): void
    {
        $this->settings(['questions' => [['label' => 'Point de repère', 'required' => '1'], ['label' => 'Pointure']]]);
        auth()->logout();
        $this->post('/nimstore/panier', ['variant_id' => $this->product->variants->first()->id, 'quantity' => 1]);
        $page = $this->get('/nimstore/commande')->assertSee('name="answers_q[1]" value="Pointure"', false);

        // Le commerçant retire la première question pendant que le client remplit.
        $this->settings(['questions' => [['label' => 'Pointure']]]);
        auth()->logout();
        $this->from('/nimstore/commande')->post('/nimstore/commande', [
            'name' => 'Mariama Bah', 'phone' => '622 33 44 55',
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $this->store->cashOnDelivery()->id,
            'answers' => ['Près de la mosquée', '42'], 'answers_q' => ['Point de repère', 'Pointure'],
        ])->assertSessionHasErrors(['cart' => 'Les questions de la boutique ont changé : vérifiez vos réponses puis validez à nouveau.']);
        $this->assertSame(0, Order::count());
    }

    public function test_unchecked_days_stay_unchecked_after_an_error(): void
    {
        $week = self::WEEK;
        unset($week[6]['open_day']);          // samedi décoché
        $week[5]['close'] = '07:00';          // erreur sur vendredi
        $this->settings(['use_hours' => '1', 'hours' => $week])->assertSessionHasErrors('hours.5');

        $html = $this->actingAs($this->merchant)->get('/admin/reglages/commande')->getContent();
        $this->assertMatchesRegularExpression('/name="hours\[5\]\[open_day\]" value="1" class="[^"]*" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="hours\[6\]\[open_day\]" value="1" class="[^"]*" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="closed_outside_hours" value="1" checked/', $html);
    }

    public function test_settings_pages_belong_to_the_merchant(): void
    {
        auth()->logout();
        $this->get('/admin/reglages/commande')->assertRedirect('/connexion');
        $this->put('/admin/reglages/commande', ['questions' => [['label' => 'X']]])->assertRedirect('/connexion');
        $this->assertNull($this->store->fresh()->checkout_questions);
    }
}
