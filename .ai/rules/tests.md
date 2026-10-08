---
paths:
  - 'tests/**'
---

# Tests

## Nomi dei metodi di test: frasi italiane sul comportamento
I metodi di test sono frasi italiane in snake_case che descrivono il comportamento osservabile (es. `una_foto_senza_volti_lo_dice`), non etichette inglesi. Marca ogni test con l'attributo `#[Test]` (`PHPUnit\Framework\Attributes\Test`), senza prefisso `test_` né `@test` nel docblock.

## File di test con nomi italiani
I nuovi file di test si chiamano con una frase italiana sul comportamento (es. `PagineDelleSquadreYouthTest`). Quelli esistenti in inglese non si rinominano.

## Parser testati su pagine vere salvate
I parser di pagine esterne si testano su pagine reali salvate in `tests/Fixtures/<Fonte>/`, caricate da un helper privato `fixture()`. Mai rete né HTML sintetico scritto a mano.
