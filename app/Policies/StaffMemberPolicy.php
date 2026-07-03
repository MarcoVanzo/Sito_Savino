<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StaffMember;
use App\Models\User;

class StaffMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function view(User $user, StaffMember $staffMember): bool
    {
        return $user->role->canManageSport();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function update(User $user, StaffMember $staffMember): bool
    {
        return $user->role->canManageSport();
    }

    public function delete(User $user, StaffMember $staffMember): bool
    {
        return $user->role->isSuperAdmin();
    }
}
