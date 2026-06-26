<?php

namespace App\Observers;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Notifications\Notification;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        if (! $user->is_active) {
            $admins = User::where('role', UserRole::Admin)->where('is_active', true)->get();
            
            if ($admins->isNotEmpty()) {
                Notification::make()
                    ->title('Nuovo utente in attesa')
                    ->body("L'utente {$user->name} ({$user->email}) si è registrato ed è in attesa di abilitazione.")
                    ->warning()
                    ->sendToDatabase($admins);
            }
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
