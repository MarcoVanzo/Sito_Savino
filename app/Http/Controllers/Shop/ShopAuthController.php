<?php

namespace App\Http\Controllers\Shop;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ShopAuthController extends Controller
{
    public function __construct(
        protected CartService $cartService,
    ) {}

    /**
     * Mostra il form di registrazione dello shop.
     */
    public function showRegister(Request $request): Response
    {
        return Inertia::render('Public/Shop/ShopRegister');
    }

    /**
     * Registra un nuovo utente cliente.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'privacy_accepted' => ['required', 'accepted'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::Customer,
        ]);

        Auth::login($user);

        // Merge carrello guest nel carrello utente
        $this->cartService->mergeOnLogin($user);

        return redirect()->route('shop')
            ->with('success', 'Registrazione completata! Benvenuto nello shop.');
    }
}
