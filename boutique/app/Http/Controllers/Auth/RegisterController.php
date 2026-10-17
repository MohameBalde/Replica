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
            'phone.unique' => 'Ce numéro a déjà un compte. Connectez-vous.',
        ]);

        $user = User::create($data);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('admin.store.create');
    }
}
