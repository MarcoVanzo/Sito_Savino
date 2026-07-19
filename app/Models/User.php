<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Traits\LogsActivity;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property UserRole $role
 */
#[Fillable(['name', 'email', 'password', 'phone', 'address', 'is_active', 'role', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /** Campi esclusi dal log (dati sensibili). */
    protected array $logExclude = ['password', 'remember_token'];

    protected static function booted(): void
    {
        // Ogni utente con accesso al pannello di amministrazione deve cambiare
        // la password al primo accesso. La regola è imposta qui a livello di
        // model (non solo nella UI Filament) così vale per qualsiasi canale di
        // creazione: pannello admin, seeder, tinker, ecc.
        static::creating(function (User $user): void {
            if ($user->role instanceof UserRole && $user->role->canAccessPanel()) {
                $user->must_change_password = true;
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->role->canAccessPanel();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'role' => UserRole::class,
            'address' => 'array',
            'has_verified_payment_method' => 'boolean',
            'payment_method_verified_at' => 'datetime',
        ];
    }

    // --- Relazioni ---

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class, 'author_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function wonAuctions(): HasMany
    {
        return $this->hasMany(Auction::class, 'winner_user_id');
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }
}
