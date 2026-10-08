---
paths:
  - 'app/Policies/**'
---

# Policies

## Policy con AuthorizesByRole
Le policy usano `AuthorizesByRole` e implementano `manageAbility()` (opz. `viewAbility()`/`deleteAbility()`) che restituisce il metodo di `UserRole`. Le risorse in sola lettura saltano il trait e restituiscono `false` da ogni scrittura: Filament considera permesso un metodo mancante. Un divieto che deve valere anche per il super admin va in `DIVIETI_DI_PRINCIPIO` di `AppServiceProvider`.
