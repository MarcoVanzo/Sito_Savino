<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    /**
     * I log sono immutabili — usiamo solo created_at.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'model_label',
        'changes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // --- Relazioni ---

    /**
     * L'utente che ha eseguito l'azione.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Il modello su cui è stata eseguita l'azione (polimorfico).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }

    // --- Accessor ---

    /**
     * Label leggibile dell'azione in italiano.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Creazione',
            'updated' => 'Modifica',
            'deleted' => 'Eliminazione',
            'restored' => 'Ripristino',
            'force_deleted' => 'Eliminazione definitiva',
            default => $this->action,
        };
    }

    /**
     * Icona Heroicon per l'azione.
     */
    public function getActionIconAttribute(): string
    {
        return match ($this->action) {
            'created' => 'heroicon-o-plus-circle',
            'updated' => 'heroicon-o-pencil-square',
            'deleted' => 'heroicon-o-trash',
            'restored' => 'heroicon-o-arrow-uturn-left',
            'force_deleted' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Colore per il badge dell'azione.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            'restored' => 'info',
            'force_deleted' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Nome leggibile del tipo di modello (senza namespace).
     */
    public function getModelTypeLabelAttribute(): string
    {
        $labels = [
            'App\\Models\\Post' => 'Notizia',
            'App\\Models\\Page' => 'Pagina',
            'App\\Models\\Product' => 'Prodotto',
            'App\\Models\\Order' => 'Ordine',
            'App\\Models\\Player' => 'Giocatore',
            'App\\Models\\Game' => 'Partita',
            'App\\Models\\Category' => 'Categoria',
            'App\\Models\\Team' => 'Squadra',
            'App\\Models\\Season' => 'Stagione',
            'App\\Models\\Roster' => 'Rosa',
            'App\\Models\\Sponsor' => 'Sponsor',
            'App\\Models\\User' => 'Utente',
            'App\\Models\\ProductVariant' => 'Variante Prodotto',
            'App\\Models\\ProductCategory' => 'Categoria Prodotto',
            'App\\Models\\Option' => 'Opzione',
        ];

        return $labels[$this->model_type] ?? class_basename($this->model_type);
    }
}
