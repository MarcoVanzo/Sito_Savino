---
paths:
  - 'resources/js/**'
  - 'resources/js/**/*.test.js'
---

# Js

## JavaScript semplice, niente TypeScript
Componenti Vue in `<script setup>` JS e helper `.js`. Non introdurre TypeScript né `lang="ts"`.

## Testi Vue con $t e resources/js/i18n
Le etichette dell'interfaccia stanno in `resources/js/i18n/it.json` e `en.json` (chiavi a gruppi) e si leggono con `const $t = useTranslations()` da `@/Composables/useTranslations.js`. Non usare `lang/` del backend né le shared props per i testi Vue.

## Logica fuori dai .vue, in moduli testati
La logica e la preparazione dei dati non banali escono dai `.vue` e vanno in `resources/js/Support/*.js` (o in un composable `use*`), con un test Vitest `*.test.js` accanto.

## Test Vitest accanto al sorgente
Il test sta accanto al sorgente come `<nome>.test.js`, importa `describe/it/expect` da `'vitest'` e descrive il comportamento con frasi italiane in `it()`. Per i componenti Vue usa `inertiaFinto()` e `opzioniGlobali()` di `@/testing/paginaDiProva.js`.
