# Savino Del Bene Volley — Sito Ufficiale (Release 3.4)

Sito web ufficiale della **Savino Del Bene Volley Scandicci**, squadra di Serie A1 femminile.

## Tech Stack

| Componente | Tecnologia |
|---|---|
| **Backend** | Laravel 13.x, PHP 8.4 |
| **Admin** | Filament v3 |
| **Frontend** | Vue 3 + Inertia.js |
| **Styling** | Tailwind CSS v3 |
| **Media** | Spatie Media Library |
| **Deploy** | DigitalOcean App Platform + MySQL 8 |

## Setup Locale

```bash
# Clona il repository
git clone <repo-url> savino-del-bene
cd savino-del-bene

# Installa le dipendenze
composer install
npm install

# Configura l'ambiente
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed

# Avvia il frontend (dev server con HMR)
npm run dev

# In un altro terminale, avvia il backend
php artisan serve
```

L'applicazione sarà disponibile su `http://localhost:8000`.

## Struttura Progetto

```
app/
├── Enums/                        — Enum PHP (PostStatus, OrderStatus, GameStatus, StockMovementType)
├── Filament/Resources/           — Pannello admin Filament (16 resource)
├── Models/                       — Modelli Eloquent
├── Observers/                    — Event observer (OrderObserver, StockMovementObserver)
├── Policies/                     — Autorizzazione (12 policy)
resources/
├── js/
│   ├── Layouts/                  — Layout (PublicLayout, AuthenticatedLayout)
│   └── Pages/Public/             — Pagine Vue pubbliche
```

## Pannello Admin

Accessibile su `/admin`. Richiede ruolo `admin` o `editor`.

Gestisce: articoli, pagine, prodotti, ordini, categorie, tag, partite, giocatrici, staff, sponsor, media, utenti e altro.

## Enums

Il progetto utilizza PHP Enums nativi (PHP 8.1+) per gestire gli status in modo type-safe:

- **`PostStatus`** — `draft`, `publish`
- **`OrderStatus`** — `pending`, `paid`, `shipped`, `cancelled`
- **`GameStatus`** — `scheduled`, `in_progress`, `completed`, `postponed`
- **`StockMovementType`** — `Vendita`, `Acquisto`, `Rettifica`

Ogni enum espone un metodo `label()` che restituisce l'etichetta localizzata in italiano.

## Query Scopes

I modelli Eloquent espongono scope dedicati per le query più comuni:

```php
Post::published()->get();        // Post con status 'publish'
Page::published()->get();        // Pagine con status 'publish'
Product::active()->get();        // Prodotti attivi (is_active = true)
Order::paid()->get();            // Ordini con status 'paid'
```

## Deploy

Configurato per **DigitalOcean App Platform**. Il file di configurazione si trova in `.do/app.yaml`.

### Variabili d'ambiente richieste

| Variabile | Descrizione |
|---|---|
| `APP_KEY` | Chiave di crittografia Laravel |
| `DB_HOST` | Host del database MySQL |
| `DB_DATABASE` | Nome del database |
| `DB_USERNAME` | Utente del database |
| `DB_PASSWORD` | Password del database |
| `PREVIEW_AUTH_USER` | Username per preview environment |
| `PREVIEW_AUTH_PASSWORD` | Password per preview environment |

## Licenza

Progetto proprietario — Tutti i diritti riservati.
