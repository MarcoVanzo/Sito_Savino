---
paths:
  - 'app/Http/**'
---

# Http

## Servizi, niente Actions né repository
I controller interrogano Eloquent direttamente per le letture; la logica a più passi (shop, aste, sync) va in una classe di `app/Services` iniettata dal costruttore. Non aggiungere Actions, repository o query object.

## Lock delle righe dentro le transazioni di ordini, giacenze e offerte
Le scritture su ordini, giacenze, pagamenti e offerte stanno in una closure `DB::transaction()` e rileggono le righe da cambiare con `lockForUpdate()` dentro la transazione.

## Niente API Resource
Le props Inertia sono array costruiti nel controller (mapper privati `xxxToArray()`, `->through()` sui paginatori); gli endpoint AJAX e i webhook rispondono con `response()->json([...])`. Non aggiungere classi JsonResource.
