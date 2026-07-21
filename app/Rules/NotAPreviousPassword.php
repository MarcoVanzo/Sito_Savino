<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Vieta il riuso delle ultime N password dell'utente (config
 * `password_policy.history_size`), inclusa quella attualmente in uso.
 */
class NotAPreviousPassword implements ValidationRule
{
    public function __construct(private readonly ?User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->user === null || ! is_string($value) || $value === '') {
            return;
        }

        $hashes = $this->user->recentPasswordHashes();

        // La password in uso può non essere ancora nello storico (utenti creati
        // prima dell'introduzione della policy): la controlliamo comunque.
        $current = $this->user->getAttributes()['password'] ?? null;
        if (is_string($current) && $current !== '') {
            $hashes = $hashes->push($current)->unique();
        }

        foreach ($hashes as $hash) {
            if (Hash::check($value, $hash)) {
                $count = max(1, (int) config('password_policy.history_size', 6));

                $fail("La nuova password non può essere una delle ultime {$count} già utilizzate. Scegline una diversa.");

                return;
            }
        }
    }
}
