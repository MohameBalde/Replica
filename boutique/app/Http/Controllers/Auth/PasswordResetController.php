<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsAppMessage;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Mot de passe oublié, sans SMS ni e-mail : le commerçant écrit au support sur WhatsApp,
 * l'équipe crée un lien dans l'espace Gestion et l'envoie sur le WhatsApp du numéro du compte.
 * Ainsi, seul le propriétaire du numéro reçoit le lien.
 */
class PasswordResetController extends Controller
{
    public function forgot(): View
    {
        return view('auth.forgot');
    }

    /** Ouvre WhatsApp vers le support. Même réponse que le numéro ait un compte ou non. */
    public function requestLink(Request $request): RedirectResponse
    {
        $phone = Phone::normalize($request->input('phone'));
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => 'Entrez le numéro de votre compte, par exemple 620 00 00 00.']);
        }

        return redirect()->away(WhatsAppMessage::url(config('wanllan.support_whatsapp'), WhatsAppMessage::forgotPassword($phone)));
    }

    /** Ouvrir le lien ne l'use pas : WhatsApp le visite déjà pour faire l'aperçu. */
    public function edit(string $token): View
    {
        return view('auth.reset', ['token' => $token, 'user' => User::findByResetToken($token)]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $user = User::findByResetToken($token);
        if (! $user) {
            return redirect()->route('password.reset', $token);
        }

        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']], [
            'password.min' => 'Au moins 8 caractères.',
            'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
            'password.required' => 'Choisissez un mot de passe.',
        ]);

        $user->resetPassword($data['password']);

        // Les sessions ouvertes ailleurs (téléphone perdu, ancien appareil) sont fermées.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('admin.home')->with('status', 'Mot de passe changé. Vous êtes connecté.');
    }
}
