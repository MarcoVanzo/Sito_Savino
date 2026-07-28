<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

class PagePolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageEditorial';
    }

    /**
     * Una pagina la modifica chi l'ha scritta: gli altri redattori la vedono
     * ma non la toccano. Il super admin non ha questo vincolo.
     */
    public function update(User $user, Page $model): bool
    {
        return $user->role->isSuperAdmin()
            || ($user->role->canManageEditorial() && $model->author_id === $user->id);
    }
}
