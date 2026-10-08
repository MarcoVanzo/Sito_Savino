---
paths:
  - 'app/**'
---

# App

## Facade per Cache/Log/DB, helper per il resto
Usa le facade `Cache::`, `Log::`, `DB::` (mai `cache()` o `logger()`); per config, tempo, risposte, redirect e sessione gli helper `config()`, `now()`, `response()`, `redirect()`, `session()`.

## Classi nuove con nomi italiani
Le classi nuove si chiamano con una frase italiana descrittiva senza suffisso di tipo (es. `StagioniDelleAtlete`, `RicostruisciLaCacheDellaGallery`). Eccezioni: le famiglie delle integrazioni esterne (`<Fonte>Client/Parser/SyncService`) e i suffissi che Filament richiede (`…Resource`, `…Action`, `…Column`). Le classi esistenti non si rinominano.
