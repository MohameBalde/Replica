<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Le commerçant supprime son compte : boutique, produits, photos, commandes,
 * clients et captures. Les clés étrangères en cascade effacent les lignes,
 * les fichiers sont effacés ici.
 */
class AccountController extends Controller
{
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.required' => 'Entrez votre mot de passe pour confirmer.',
            'password.current_password' => 'Mot de passe incorrect.',
        ]);

        $user = $request->user();
        $store = $user->store()->first();

        // Déconnexion d'abord : avec « rester connecté », la déconnexion réenregistre
        // l'utilisateur, ce qui le recréerait s'il était déjà supprimé.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->delete();

        if ($store) {
            Storage::disk('public')->deleteDirectory('stores/'.$store->id);
            Storage::disk(PaymentProof::DISK)->deleteDirectory('proofs/'.$store->id);
        }

        return redirect()->route('home')->with('status', 'Votre compte et votre boutique ont été supprimés.');
    }
}
