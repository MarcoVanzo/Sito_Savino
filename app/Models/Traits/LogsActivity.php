<?php

namespace App\Models\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Trait per abilitare il logging automatico delle azioni CRUD su un modello.
 *
 * Uso: aggiungere `use LogsActivity;` nella classe del modello.
 *
 * Proprietà opzionali nel modello:
 *   - protected array $logExclude = ['campo1', 'campo2'];  // campi da escludere completamente dal log
 *   - protected array $logHeavyFields = ['content', 'body']; // campi grandi: logga solo "modificato" senza diff
 *   - protected string $logLabelField = 'title';  // campo da usare come label leggibile
 */
trait LogsActivity
{
    /**
     * Boot automatico del trait — aggancia i model events.
     */
    public static function bootLogsActivity(): void
    {
        // Campi sempre esclusi dal diff (cambiano ad ogni salvataggio)
        $alwaysExclude = ['updated_at', 'created_at', 'remember_token'];

        static::created(function ($model) {
            static::logAction($model, 'created');
        });

        static::updated(function ($model) {
            static::logAction($model, 'updated');
        });

        static::deleted(function ($model) {
            static::logAction($model, 'deleted');
        });

        // Supporto soft-delete: restored e forceDeleted
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                static::logAction($model, 'restored');
            });
        }

        if (method_exists(static::class, 'forceDeleted')) {
            static::forceDeleted(function ($model) {
                static::logAction($model, 'force_deleted');
            });
        }
    }

    /**
     * Registra un'azione nel log.
     */
    protected static function logAction($model, string $action): void
    {
        $changes = null;

        if ($action === 'updated') {
            $changes = static::buildChanges($model);

            // Se non ci sono modifiche significative, non loggare
            if ($changes === null) {
                return;
            }
        } elseif ($action === 'created') {
            $changes = static::buildCreatedSnapshot($model);
        }

        ActivityLog::create([
            'user_id' => Auth::id(), // null se azione da CLI/queue
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'model_label' => static::extractLabel($model),
            'changes' => $changes,
            'ip_address' => Request::ip(), // null-safe in CLI
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Calcola il diff delle modifiche (old → new) escludendo campi sensibili/pesanti.
     */
    protected static function buildChanges($model): ?array
    {
        $dirty = $model->getDirty();
        $original = $model->getOriginal();

        $exclude = static::getExcludedFields($model);
        $heavyFields = property_exists($model, 'logHeavyFields')
            ? $model->logHeavyFields
            : ['content', 'body']; // default: campi RichEditor

        $old = [];
        $new = [];

        foreach ($dirty as $key => $value) {
            // Escludi campi che non devono essere loggati
            if (in_array($key, $exclude)) {
                continue;
            }

            // Per campi pesanti: logga solo che è stato modificato, senza valore
            if (in_array($key, $heavyFields)) {
                $old[$key] = '[contenuto precedente]';
                $new[$key] = '[contenuto aggiornato]';
                continue;
            }

            $old[$key] = $original[$key] ?? null;
            $new[$key] = $value;
        }

        if (empty($old) && empty($new)) {
            return null;
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Snapshot dei campi alla creazione (senza "old").
     */
    protected static function buildCreatedSnapshot($model): ?array
    {
        $attributes = $model->getAttributes();
        $exclude = static::getExcludedFields($model);
        $heavyFields = property_exists($model, 'logHeavyFields')
            ? $model->logHeavyFields
            : ['content', 'body'];

        $snapshot = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, $exclude)) {
                continue;
            }
            if (in_array($key, $heavyFields)) {
                $snapshot[$key] = '[contenuto]';
                continue;
            }
            $snapshot[$key] = $value;
        }

        return empty($snapshot) ? null : ['new' => $snapshot];
    }

    /**
     * Lista completa dei campi da escludere.
     */
    protected static function getExcludedFields($model): array
    {
        $always = ['updated_at', 'created_at', 'remember_token'];

        $modelExclude = property_exists($model, 'logExclude')
            ? $model->logExclude
            : [];

        return array_merge($always, $modelExclude);
    }

    /**
     * Cerca un campo leggibile da usare come label del record nel log.
     */
    protected static function extractLabel($model): ?string
    {
        // Campo esplicito definito nel modello
        if (property_exists($model, 'logLabelField')) {
            return $model->{$model->logLabelField} ?? null;
        }

        // Fallback: prova campi comuni
        foreach (['title', 'name', 'slug', 'email'] as $field) {
            if (isset($model->{$field})) {
                return (string) $model->{$field};
            }
        }

        return null;
    }
}
