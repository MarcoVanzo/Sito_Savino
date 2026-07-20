<?php

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Pages\Auth\Login as BaseLogin;

/**
 * Pagina di login del pannello Filament.
 *
 * Il progetto ha un'unica pagina di login brandizzata (Inertia, rotta "login").
 * Per evitare di mostrare all'utente due schermate di login diverse — quella del
 * sito e quella di Filament — questa pagina non renderizza il form di Filament
 * ma reindirizza a quella del sito. Manteniamo comunque abilitato ->login() nel
 * pannello così che restino registrate le rotte di autenticazione/logout usate
 * da Filament.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        // Un utente già autenticato che arriva qui va mandato al pannello,
        // preservando l'eventuale destinazione richiesta.
        if (Filament::auth()->check()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->redirect(route('login'));
    }
}
