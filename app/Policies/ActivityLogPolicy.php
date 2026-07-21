<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Il registro attività è un'area di sistema: lo vede solo chi amministra.
 * Senza policy Filament considera la risorsa accessibile a chiunque entri
 * nel pannello, esponendo lo storico delle azioni di tutti gli utenti.
 */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageSystem();
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $user->role->canManageSystem();
    }

    // Il registro è immutabile: nessuna scrittura da pannello.

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
