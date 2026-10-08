---
paths:
  - 'app/**'
---

# App

## Facade per Cache/Log/DB, helper per il resto
Usa le facade `Cache::`, `Log::`, `DB::` (mai `cache()` o `logger()`); per config, tempo, risposte, redirect e sessione gli helper `config()`, `now()`, `response()`, `redirect()`, `session()`.
