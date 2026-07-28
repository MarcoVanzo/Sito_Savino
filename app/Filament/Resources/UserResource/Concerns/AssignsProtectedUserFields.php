<?php

namespace App\Filament\Resources\UserResource\Concerns;

use App\Models\User;
use Illuminate\Support\Arr;

/**
 * `role`, `is_active` e `must_change_password` non sono assegnabili in massa:
 * decidono chi entra nel pannello e con quali permessi, quindi non devono poter
 * essere impostati da un campo inatteso arrivato in una request.
 *
 * Il pannello ha però bisogno legittimo di scriverli, e i valori arrivano qui
 * già filtrati dallo schema del form. Si assegnano perciò in modo esplicito con
 * `forceFill`, che aggira il fillable senza allentarlo per il resto dell'app.
 */
trait AssignsProtectedUserFields
{
    /**
     * @var list<string>
     */
    private array $protectedUserFields = ['role', 'is_active', 'must_change_password'];

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} dati assegnabili, dati protetti
     */
    private function splitProtectedFields(array $data): array
    {
        return [
            Arr::except($data, $this->protectedUserFields),
            Arr::only($data, $this->protectedUserFields),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistUser(User $user, array $data): User
    {
        [$fillable, $protected] = $this->splitProtectedFields($data);

        $user->fill($fillable);

        if ($protected !== []) {
            $user->forceFill($protected);
        }

        $user->save();

        return $user;
    }
}
