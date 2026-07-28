<?php

namespace App\Policies;

use App\Models\GamePlayerStat;
use App\Models\User;

/**
 * Il tabellino di una gara arriva dal sito della Lega e viene riscritto a ogni
 * sincronizzazione: dal CMS si consulta soltanto.
 *
 * Come per StandingPolicy, i permessi di scrittura sono dichiarati
 * esplicitamente a `false`: senza il metodo, Filament considererebbe l'azione
 * permessa e mostrerebbe i pulsanti di modifica/eliminazione nel tabellino.
 */
class GamePlayerStatPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canViewSport();
    }

    public function view(User $user, GamePlayerStat $gamePlayerStat): bool
    {
        return $user->role->canViewSport();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, GamePlayerStat $gamePlayerStat): bool
    {
        return false;
    }

    public function delete(User $user, GamePlayerStat $gamePlayerStat): bool
    {
        return false;
    }

    /**
     * Cancellazione in blocco (DeleteBulkAction).
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, GamePlayerStat $gamePlayerStat): bool
    {
        return false;
    }

    public function forceDelete(User $user, GamePlayerStat $gamePlayerStat): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Associazione/dissociazione di righe dalla relazione (RelationManager).
     */
    public function attachAny(User $user): bool
    {
        return false;
    }

    public function detachAny(User $user): bool
    {
        return false;
    }
}
