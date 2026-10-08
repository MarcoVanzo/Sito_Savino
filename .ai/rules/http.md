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

## Form Request per moduli pubblici e checkout
Moduli pubblici e checkout (shop e aste) si validano con un Form Request in `app/Http/Requests`, con le regole condivise fra i due checkout. `$request->validate()` in linea solo per azioni piccole: carrello, coupon, account.
