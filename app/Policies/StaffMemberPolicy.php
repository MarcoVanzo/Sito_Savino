<?php

namespace App\Policies;

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

    /**
     * Riordino della tabella (StaffMemberResource, ManagementResource e
     * YouthStaffResource usano `reorderable('sort_order')`). Senza questo
     * metodo Filament dà il riordino per permesso a chiunque veda la lista:
     * l'ordine di visualizzazione è contenuto pubblicato, quindi va legato al
     * permesso di modifica, non a quello di lettura.
     */
    public function reorder(User $user): bool
    {
        return $user->role->canManageSport();
    }

    /**
     * Cancellazione in blocco (DeleteBulkAction): senza questo metodo
     * Filament considera l'azione permessa a chiunque veda la lista.
     */
    public function deleteAny(User $user): bool
    {
        return $user->role->isSuperAdmin();
    }
}
