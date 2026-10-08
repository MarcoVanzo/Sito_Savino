---
paths:
  - 'tests/**'
---

# Tests

## Nomi dei metodi di test: frasi italiane sul comportamento
I metodi di test sono frasi italiane in snake_case che descrivono il comportamento osservabile (es. `una_foto_senza_volti_lo_dice`), non etichette inglesi. Mantieni il meccanismo già usato nel file (`#[Test]` oppure prefisso `test_`).

## Parser testati su pagine vere salvate
I parser di pagine esterne si testano su pagine reali salvate in `tests/Fixtures/<Fonte>/`, caricate da un helper privato `fixture()`. Mai rete né HTML sintetico scritto a mano.
