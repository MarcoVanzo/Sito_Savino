---
paths:
  - 'database/migrations/**'
---

# Migrations

## Enum salvati come stringa e castati
Stati, tipi e ruoli stanno in una colonna `string` castata a un backed enum di `App\Enums` sul modello. Mai colonne `enum()` nel DB.
