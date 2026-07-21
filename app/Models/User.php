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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property UserRole $role
 * @property ?Carbon $password_changed_at
 * @property ?Carbon $password_expiry_notified_at
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
            // Leggiamo il valore grezzo dell'attributo (stringa) per evitare che
            // il PHPDoc @property UserRole $role induca ad assumere il tipo certo.
            $role = UserRole::tryFrom((string) ($user->getAttributes()['role'] ?? ''));

            if ($role?->canAccessPanel()) {
                $user->must_change_password = true;
            }

            // Da qui parte il conteggio dei 6 mesi di validità.
            $user->password_changed_at ??= now();
        });

        // Lo storico e la data di cambio sono gestiti a livello di model, non nei
        // singoli controller: i punti che scrivono una password sono molti
        // (cambio forzato, profilo, reset dal sito, reset di Filament, creazione
        // e modifica da UserResource, registrazione shop, seeder). Agganciandoci
        // qui la policy non può avere buchi.
        static::updating(function (User $user): void {
            if (! $user->isDirty('password')) {
                return;
            }

            $user->password_changed_at = now();
            // Nuova password ⇒ il preavviso di scadenza riparte da zero.
            $user->password_expiry_notified_at = null;
        });

        // Nota: si usano `created`/`updated` e non `saved`. Su `saved` la
        // proprietà wasRecentlyCreated resta true anche nei salvataggi
        // successivi della stessa istanza, duplicando le voci di storico.
        static::created(function (User $user): void {
            $user->recordPasswordInHistory();
        });

        static::updated(function (User $user): void {
            if ($user->wasChanged('password')) {
                $user->recordPasswordInHistory();
            }
        });
    }

    /**
     * Registra la password attuale nello storico e pota le voci eccedenti.
     *
     * Salviamo l'hash già calcolato dal cast 'hashed', mai il valore in chiaro.
     */
    public function recordPasswordInHistory(): void
    {
        $hash = $this->getAttributes()['password'] ?? null;

        if (! is_string($hash) || $hash === '') {
            return;
        }

        $this->passwordHistories()->create(['password' => $hash]);

        $keep = max(1, (int) config('password_policy.history_size', 6));

        $obsolete = $this->passwordHistories()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($obsolete->isNotEmpty()) {
            PasswordHistory::whereIn('id', $obsolete)->delete();
        }
    }

    /**
     * Le ultime N password usate (hash), la più recente per prima.
     *
     * @return Collection<int, string>
     */
    public function recentPasswordHashes(): Collection
    {
        return $this->passwordHistories()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(max(1, (int) config('password_policy.history_size', 6)))
            ->pluck('password');
    }

    // --- Scadenza password ---

    /** Momento in cui la password attuale scade (null se non determinabile). */
    public function passwordExpiresAt(): ?Carbon
    {
        $changedAt = $this->password_changed_at;

        if (! $changedAt) {
            return null;
        }

        return $changedAt->copy()->addMonths(
            max(1, (int) config('password_policy.expires_after_months', 6))
        );
    }

    public function passwordHasExpired(): bool
    {
        $expiresAt = $this->passwordExpiresAt();

        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * Giorni mancanti alla scadenza (0 se già scaduta, null se non applicabile).
     */
    public function daysUntilPasswordExpires(): ?int
    {
        $expiresAt = $this->passwordExpiresAt();

        if ($expiresAt === null) {
            return null;
        }

        // Carbon 3: diffInDays restituisce un float; il secondo argomento a false
        // mantiene il segno, così una scadenza già passata dà un valore negativo.
        return max(0, (int) ceil(now()->diffInDays($expiresAt, false)));
    }

    /** La password sta per scadere ed è ora di avvisare l'utente? */
    public function passwordIsExpiringSoon(): bool
    {
        $days = $this->daysUntilPasswordExpires();

        return $days !== null
            && ! $this->passwordHasExpired()
            && $days <= max(1, (int) config('password_policy.warn_before_days', 10));
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
            'password_changed_at' => 'datetime',
            'password_expiry_notified_at' => 'datetime',
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

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
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
