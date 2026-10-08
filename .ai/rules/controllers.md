---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Controlli di proprietà in linea nei controller pubblici
Nei controller del sito e dello shop autorizza con controlli di proprietà o ruolo in linea più `abort(403)`/`abort_unless(…, 403)`, non `$this->authorize()` né Policy: le Policy servono al pannello Filament.

## Binding implicito, token segreti a mano
Modelli nelle rotte con binding implicito (`{product:slug}`, `{auction}`). Fanno eccezione le risorse raggiunte con un token segreto (`order_token`, `winner_checkout_token`): parametro `string` e ricerca con `where(...)->firstOrFail()`.
