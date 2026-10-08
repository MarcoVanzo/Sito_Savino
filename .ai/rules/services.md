---
paths:
  - 'app/Services/**'
---

# Services

## Servizi, niente Actions né repository
La logica di dominio a più passi (shop, aste, sync) sta in una classe d'istanza in `app/Services`, iniettata dal costruttore. Le letture interrogano Eloquent direttamente da controller e servizi: non aggiungere Actions, repository o query object.

## Lock delle righe dentro le transazioni di ordini, giacenze e offerte
Le scritture su ordini, giacenze, pagamenti e offerte stanno in una closure `DB::transaction()` e rileggono le righe da cambiare con `lockForUpdate()` dentro la transazione.

## Integrazioni esterne: cartella Client/Parser/SyncService
Ogni fonte esterna ha `app/Services/<Fonte>/` con `<Fonte>Client` (`static fromConfig()`), classi `<Fonte>…Parser`, `<Fonte>SyncService` (`static make()`), `<Fonte>Exception extends RuntimeException` accanto (non in `app/Exceptions`) e DTO in `Data/`.

## DTO come classi semplici con proprietà public readonly
Un DTO è una classe semplice con proprietà `public readonly` promosse nel costruttore, nella cartella `Data/` dell'integrazione. Niente `readonly class` né spatie/laravel-data; altrove i dati viaggiano come array.

## Nessuna spina di eventi di dominio
Gli effetti collaterali partono chiamando il servizio, da un observer o con `Job::dispatch()`. Eventi dell'app solo per il broadcasting, listener solo per eventi del framework.
