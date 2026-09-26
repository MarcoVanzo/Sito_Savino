<?php

namespace App\Policies;

use App\Enums\UserRole;
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
     * Nessuno può cancellare sé stesso né l'ultimo super admin attivo: è la
     * difesa contro il pannello che resta senza amministratori. Vale anche per
     * il super admin, perché Gate::before lascia decidere questa policy
     * (AppServiceProvider::DIVIETI_DI_PRINCIPIO).
     */
    public function delete(User $user, User $model): bool
    {
        return $user->role->canManageSystem()
            && $user->id !== $model->id
            && ! $this->isLastActiveSuperAdmin($model);
    }

    private function isLastActiveSuperAdmin(User $model): bool
    {
        if (! $model->exists || ! $model->role->isSuperAdmin() || ! $model->is_active) {
            return false;
        }

        return ! User::query()
            ->where('role', UserRole::SuperAdmin)
            ->where('is_active', true)
            ->whereKeyNot($model->getKey())
            ->exists();
    }
}
