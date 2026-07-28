<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

class PostPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageEditorial';
    }

    /**
     * Un articolo lo modifica chi l'ha scritto: gli altri redattori lo vedono
     * ma non lo toccano. Il super admin non ha questo vincolo.
     */
    public function update(User $user, Post $model): bool
    {
        return $user->role->isSuperAdmin()
            || ($user->role->canManageEditorial() && $model->author_id === $user->id);
    }
}
