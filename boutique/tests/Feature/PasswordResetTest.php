<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\TeamAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mot de passe oublié : le commerçant écrit au support, l'équipe crée un lien dans Gestion
 * et l'envoie sur le WhatsApp du numéro du compte ; le lien sert une fois, pendant 1 heure.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post('/inscription', [
            'name' => 'Aïssatou', 'phone' => '620 11 22 33',
            'password' => 'ancienmotdepasse', 'password_confirmation' => 'ancienmotdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620 11 22 33', 'city' => 'Conakry']);
        auth()->logout();
        $this->merchant = User::where('phone', '224620112233')->firstOrFail();
        $this->store = Store::where('slug', 'nimstore')->firstOrFail();
    }

    private function team(): User
    {
        $team = User::factory()->create(['phone' => '224629000000']);
        $team->forceFill(['is_team' => true])->save();

        return $team;
    }

    /** Extrait le lien du message WhatsApp préparé pour l'équipe. */
    private function linkFrom(string $waUrl): string
    {
        preg_match('#(https?://\S+/nouveau-mot-de-passe/\w+)#', rawurldecode($waUrl), $m);

        return $m[1];
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('href="'.route('password.forgot').'"', false);
        $this->get('/mot-de-passe-oublie')->assertOk()->assertSee('Demander le lien sur WhatsApp');
    }

    public function test_request_opens_whatsapp_to_support_without_revealing_accounts(): void
    {
        $support = 'https://wa.me/'.config('wanllan.support_whatsapp').'?text=';

        $known = $this->post('/mot-de-passe-oublie', ['phone' => '620 11 22 33']);
        $known->assertRedirect();
        $this->assertStringStartsWith($support, $known->headers->get('Location'));
        $this->assertStringContainsString('+224 620 11 22 33', rawurldecode($known->headers->get('Location')));

        // Numéro sans compte : même réponse.
        $this->assertStringStartsWith($support, $this->post('/mot-de-passe-oublie', ['phone' => '620 99 99 99'])->headers->get('Location'));

        $this->from('/mot-de-passe-oublie')->post('/mot-de-passe-oublie', ['phone' => '12'])
            ->assertRedirect('/mot-de-passe-oublie')->assertSessionHasErrors('phone');
    }

    public function test_team_sends_a_link_to_the_account_whatsapp_and_merchant_resets(): void
    {
        $response = $this->actingAs($this->team())->post(route('team.stores.password', $this->store->id));
        $response->assertRedirect(route('team.stores.show', $this->store->id));
        $waUrl = session('password_url');
        $this->assertStringStartsWith('https://wa.me/224620112233?text=', $waUrl); // le numéro du compte, pas un autre
        $this->assertSame('mot_de_passe', TeamAction::latest('id')->value('action'));
        $this->get(route('team.stores.show', $this->store->id))->assertSee('Envoyer le lien de mot de passe');

        $link = $this->linkFrom($waUrl);
        auth()->logout();

        // L'aperçu de WhatsApp visite le lien : il reste valable.
        $this->get($link)->assertOk()->assertSee('Nouveau mot de passe');
        $this->get($link)->assertOk()->assertSee('Aïssatou');

        $this->from($link)->post($link, ['password' => 'court', 'password_confirmation' => 'court'])
            ->assertRedirect($link)->assertSessionHasErrors('password');

        $this->post($link, ['password' => 'nouveaumotdepasse', 'password_confirmation' => 'nouveaumotdepasse'])
            ->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($this->merchant);
        $this->merchant->refresh();
        $this->assertTrue(Hash::check('nouveaumotdepasse', $this->merchant->password));
        $this->assertNull($this->merchant->password_reset_hash);

        // Une seule fois.
        auth()->logout();
        $this->get($link)->assertOk()->assertSee('Ce lien ne marche plus');
        $this->post($link, ['password' => 'autremotdepasse', 'password_confirmation' => 'autremotdepasse'])->assertRedirect($link);
        $this->assertGuest();

        $this->post('/connexion', ['phone' => '620 11 22 33', 'password' => 'nouveaumotdepasse'])->assertRedirect();
        $this->assertAuthenticatedAs($this->merchant);
    }

    public function test_link_expires_after_one_hour_and_a_new_one_replaces_it(): void
    {
        $first = $this->merchant->createPasswordResetUrl();
        $second = $this->merchant->fresh()->createPasswordResetUrl();
        $this->get($first)->assertSee('Ce lien ne marche plus');
        $this->get($second)->assertSee('Nouveau mot de passe');

        $this->travel(User::RESET_MINUTES + 1)->minutes();
        $this->get($second)->assertSee('Ce lien ne marche plus');
    }

    public function test_reset_logs_out_remembered_devices(): void
    {
        $before = $this->merchant->getRememberToken();
        $this->merchant->resetPassword('nouveaumotdepasse');
        $this->assertNotSame($before, $this->merchant->fresh()->getRememberToken());
    }

    public function test_merchants_cannot_create_links(): void
    {
        $this->actingAs($this->merchant)->post(route('team.stores.password', $this->store->id))->assertNotFound();
        $this->assertNull($this->merchant->fresh()->password_reset_hash);
    }

    public function test_command_prints_a_link_for_any_account(): void
    {
        $this->artisan('wanllan:mot-de-passe', ['telephone' => ['620', '11', '22', '33']])
            ->expectsOutputToContain('/nouveau-mot-de-passe/')
            ->assertSuccessful();
        $this->assertNotNull($this->merchant->fresh()->password_reset_hash);

        $this->artisan('wanllan:mot-de-passe', ['telephone' => ['620999999']])->assertFailed();
    }

    public function test_reset_paths_cannot_become_store_links(): void
    {
        $this->assertContains('mot-de-passe-oublie', Store::RESERVED_SLUGS);
        $this->assertContains('nouveau-mot-de-passe', Store::RESERVED_SLUGS);
    }
}
