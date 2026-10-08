---
paths:
  - 'app/Observers/**'
---

# Observers

## Observer registrati in AppServiceProvider
Registra gli observer con `Model::observe()` in `AppServiceProvider::boot`, non con `#[ObservedBy]`. Per svuotare la cache aggancia il modello all'observer di invalidazione esistente invece di scriverne uno nuovo.
