<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /** Le numéro a déjà un compte : la page d'inscription ajoute un lien « Se connecter » à ce message. */
    public const PHONE_TAKEN = 'Ce numéro a déjà un compte Wanllan.';

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^2246\d{8}$/', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone.regex' => 'Entrez un numéro guinéen valable, par exemple 620 00 00 00.',
            'phone.unique' => self::PHONE_TAKEN,
        ]);

        $user = User::create($data);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('admin.store.create');
    }
}
