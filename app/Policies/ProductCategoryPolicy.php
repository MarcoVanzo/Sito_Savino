<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    /**
     * Determina se l'utente può visualizzare l'elenco dei modelli.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    /**
     * Determina se l'utente può visualizzare il modello.
     */
    public function view(User $user, ProductCategory $model): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    /**
     * Determina se l'utente può creare modelli.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determina se l'utente può aggiornare il modello.
     */
    public function update(User $user, ProductCategory $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determina se l'utente può eliminare il modello.
     */
    public function delete(User $user, ProductCategory $model): bool
    {
        return $user->role === UserRole::Admin;
    }
}
