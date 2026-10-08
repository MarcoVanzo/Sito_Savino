---
paths:
  - 'app/Models/**'
---

# Models

## Accessor con i metodi magici legacy
Scrivi gli accessor come `getXxxAttribute()` (esposti con `$appends` se servono al frontend), non con la classe `Attribute`.

## Enum salvati come stringa e castati
Stati, tipi e ruoli stanno in una colonna `string` castata a un backed enum di `App\Enums` sul modello. Mai colonne `enum()` nel DB.

## Modelli con media usano HasOptimizedMedia
Ogni modello `HasMedia` usa anche `App\Models\Traits\HasOptimizedMedia` e chiama per primo `registerStandardConversions()` in `registerMediaConversions()`.

## Modelli modificati dal pannello usano LogsActivity
Aggiungi il trait interno `App\Models\Traits\LogsActivity` (non spatie/activitylog) ai modelli che la redazione modifica in Filament, configurandolo con `$logExclude`, `$logHeavyFields`, `$logLabelField`, `$logSoloDalPannello`. Lascialo fuori da log, analytics, consensi, carrelli, offerte e dati sincronizzati.
