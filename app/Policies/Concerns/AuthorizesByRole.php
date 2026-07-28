<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Autorizzazione basata sui permessi del ruolo, uguale per quasi tutte le
 * risorse del CMS.
 *
 * Le policy del progetto ripetevano tutte lo stesso schema: vedere richiede un
 * permesso, creare e modificare un altro, cancellare di norma il super admin.
 * Trentuno copie della stessa struttura significano che cambiare una regola
 * richiede trentuno modifiche, e che basta dimenticarne una perché un permesso
 * resti indietro senza che nessuno se ne accorga.
 *
 * Si usa un trait e non una classe base per un motivo tecnico preciso: le policy
 * dichiarano il secondo parametro con il tipo del proprio modello
 * (`view(User $user, Game $game)`), e per una sottoclasse restringere il tipo di
 * un parametro violerebbe la covarianza. Un trait invece copia i metodi nella
 * classe, che può quindi sovrascriverli con la firma che preferisce.
 *
 * Il trait definisce SEMPRE l'insieme completo dei permessi, anche quelli che le
 * singole policy non dichiaravano: Filament considera permessa un'azione quando
 * la policy non ne definisce il metodo, quindi un metodo mancante è una porta
 * aperta, non un'assenza di opinione.
 */
trait AuthorizesByRole
{
    /**
     * Permesso richiesto per creare e modificare. È l'unico che ogni policy
     * deve dichiarare: tutti gli altri hanno un default sensato.
     */
    abstract protected function manageAbility(): string;

    /**
     * Permesso richiesto per consultare. Per default chi può modificare può
     * anche vedere; si sovrascrive quando esiste un ruolo di sola lettura.
     */
    protected function viewAbility(): string
    {
        return $this->manageAbility();
    }

    /**
     * Permesso richiesto per cancellare. La cancellazione è distruttiva e per
     * default resta al super admin, anche dove la modifica è più aperta.
     */
    protected function deleteAbility(): string
    {
        return 'isSuperAdmin';
    }

    /**
     * Permesso richiesto per riordinare. Riordinare è a tutti gli effetti una
     * scrittura: senza questo metodo un ruolo di sola lettura potrebbe
     * cambiare l'ordine dei record che non può modificare.
     */
    protected function reorderAbility(): string
    {
        return $this->manageAbility();
    }

    public function viewAny(User $user): bool
    {
        return $this->roleAllows($user, $this->viewAbility());
    }

    public function view(User $user, mixed $record = null): bool
    {
        return $this->roleAllows($user, $this->viewAbility());
    }

    public function create(User $user): bool
    {
        return $this->roleAllows($user, $this->manageAbility());
    }

    public function update(User $user, mixed $record = null): bool
    {
        return $this->roleAllows($user, $this->manageAbility());
    }

    public function reorder(User $user): bool
    {
        return $this->roleAllows($user, $this->reorderAbility());
    }

    public function delete(User $user, mixed $record = null): bool
    {
        return $this->roleAllows($user, $this->deleteAbility());
    }

    /**
     * Cancellazione in blocco: Filament la considera permessa a chiunque veda
     * la lista se la policy non dichiara questo metodo.
     */
    public function deleteAny(User $user): bool
    {
        return $this->roleAllows($user, $this->deleteAbility());
    }

    public function restore(User $user, mixed $record = null): bool
    {
        return $this->roleAllows($user, $this->deleteAbility());
    }

    public function restoreAny(User $user): bool
    {
        return $this->roleAllows($user, $this->deleteAbility());
    }

    /**
     * La cancellazione definitiva non è reversibile e resta al super admin
     * indipendentemente da come sia configurato il resto.
     */
    public function forceDelete(User $user, mixed $record = null): bool
    {
        return $user->role->isSuperAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Interroga il ruolo dell'utente.
     *
     * Un permesso scritto male sarebbe altrimenti un errore silenzioso: PHP
     * restituirebbe null su un metodo inesistente solo con il nullsafe, mentre
     * qui una policy mal configurata deve farsi sentire subito in sviluppo.
     */
    private function roleAllows(User $user, string $ability): bool
    {
        $role = $user->role;

        if (! method_exists($role, $ability)) {
            throw new \LogicException(sprintf(
                'La policy %s richiede il permesso "%s", che non esiste su %s.',
                static::class,
                $ability,
                $role::class,
            ));
        }

        return (bool) $role->{$ability}();
    }
}
