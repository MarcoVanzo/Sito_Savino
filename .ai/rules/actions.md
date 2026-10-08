---
paths:
  - 'app/Filament/Actions/**'
---

# Actions

## Pezzi Filament riusabili come classi a factory statica
Un'azione, colonna o campo Filament riusabile è una classe semplice il cui `static make()` (o un builder statico con nome) restituisce il componente configurato. Non estendere il componente Filament.
