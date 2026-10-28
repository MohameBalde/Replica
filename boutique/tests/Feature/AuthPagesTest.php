<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Inscription : un commerçant qui a déjà un compte trouve « Se connecter » sans faire défiler la page. */
class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    private function signup(string $phone): \Illuminate\Testing\TestResponse
    {
        return $this->from('/inscription')->post('/inscription', [
            'name' => 'Mohamed', 'phone' => $phone,
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
    }

    public function test_signup_page_shows_login_link_above_the_form(): void
    {
        $this->get('/inscription')->assertOk()
            ->assertSeeInOrder(['href="'.route('login').'"', 'id="name"'], false);
    }

    public function test_taken_phone_offers_login_in_the_error(): void
    {
        User::factory()->create(['phone' => '224620112233']);

        $this->signup('620 11 22 33')->assertRedirect('/inscription')
            ->assertSessionHasErrors(['phone' => RegisterController::PHONE_TAKEN]);
        $this->assertGuest();

        // Le lien est dans le message d'erreur, avant le champ suivant ; le numéro revient sans le 224 déjà affiché devant.
        $this->get('/inscription')
            ->assertSeeInOrder([RegisterController::PHONE_TAKEN, 'href="'.route('login').'"', 'id="password"'], false)
            ->assertSee('value="620112233"', false)
            ->assertDontSee('224620112233');
    }

    public function test_other_phone_errors_have_no_login_link(): void
    {
        $this->signup('12')->assertSessionHasErrors('phone');

        // Seulement le lien du haut et celui du bas de la page.
        $page = $this->get('/inscription')->assertSee('Entrez un numéro guinéen valable')->getContent();
        $this->assertSame(2, substr_count($page, 'href="'.route('login').'"'));
    }
}
