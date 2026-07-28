<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

class UserPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSystem';
    }

    /**
     * Gestire gli utenti include cancellarli: non ha senso riservarlo a un
     * permesso più alto, visto che canManageSystem() è già il super admin.
     */
    protected function deleteAbility(): string
    {
        return 'canManageSystem';
    }

    /**
     * Nessuno può cancellare sé stesso: è la difesa contro il pannello che
     * resta senza amministratori.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->role->canManageSystem() && $user->id !== $model->id;
    }
}
