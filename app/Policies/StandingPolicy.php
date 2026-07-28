<?php

namespace App\Policies;

use App\Models\Standing;
use App\Models\User;

/**
 * La classifica è generata dalla sincronizzazione con il sito della Lega e
 * viene riscritta a ogni giro: dal CMS è di sola lettura.
 *
 * Tutti i metodi di scrittura sono dichiarati esplicitamente a `false` perché
 * Filament considera permessa un'azione la cui policy non definisce il metodo:
 * ometterli lascerebbe visibili i pulsanti di modifica ed eliminazione.
 */
class StandingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canViewSport();
    }

    public function view(User $user, Standing $standing): bool
    {
        return $user->role->canViewSport();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Standing $standing): bool
    {
        return false;
    }

    public function delete(User $user, Standing $standing): bool
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

    public function restore(User $user, Standing $standing): bool
    {
        return false;
    }

    public function forceDelete(User $user, Standing $standing): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
