<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $user->role->canManageShop();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->role->canManageShop();
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->role->isSuperAdmin();
    }
}
