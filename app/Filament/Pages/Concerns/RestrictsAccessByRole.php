<?php

namespace App\Filament\Pages\Concerns;

/**
 * Limita l'accesso a una pagina del pannello in base al ruolo.
 *
 * Le pagine autonome non hanno una policy: se non dichiarano `canAccess()`
 * Filament le mostra a chiunque entri nel pannello. Il controllo va quindi
 * scritto pagina per pagina, ed era ripetuto identico su ognuna cambiando solo
 * il nome del permesso.
 *
 * Il progetto aveva già centralizzato questo controllo in
 * `Settings\BaseSettingsPage` tramite una classe base; qui serve un trait,
 * perché queste pagine estendono già classi diverse di Filament.
 */
trait RestrictsAccessByRole
{
    /**
     * Nome del metodo di `UserRole` che concede l'accesso.
     */
    abstract protected static function requiredAbility(): string;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $ability = static::requiredAbility();

        if (! method_exists($user->role, $ability)) {
            throw new \LogicException(sprintf(
                'La pagina %s richiede il permesso "%s", che non esiste su %s.',
                static::class,
                $ability,
                $user->role::class,
            ));
        }

        return (bool) $user->role->{$ability}();
    }
}
