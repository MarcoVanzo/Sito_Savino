# Savino Del Bene Volley — Sito Ufficiale

Sito web ufficiale della **Savino Del Bene Volley Scandicci**, squadra di Serie A1 femminile.

## Tech Stack

| Componente | Tecnologia |
|---|---|
| **Backend** | Laravel 13.x, PHP ≥ 8.4 |
| **Admin** | Filament v3 |
| **Frontend** | Vue 3 + Inertia.js 2 (rendering client-side, SSR non attivo) |
| **Styling** | Tailwind CSS v3 |
| **Media** | Spatie Media Library su DigitalOcean Spaces (S3) |
| **Deploy** | DigitalOcean App Platform + MySQL 8.4 managed |

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
├── Enums/                        — 13 enum PHP (PostStatus, OrderStatus, GameStatus, UserRole, AuctionStatus…)
├── Filament/Resources/           — Pannello admin Filament (32 resource)
├── Models/                       — Modelli Eloquent
├── Observers/                    — Event observer (Order, Product, StockMovement, User, CacheInvalidation)
├── Policies/                     — Autorizzazione (31 policy)
├── Services/Lvf/                 — Sincronizzazione dati dalla Lega Volley Femminile
resources/
├── js/
│   ├── Layouts/                  — Layout (PublicLayout, AuthenticatedLayout)
│   └── Pages/Public/             — Pagine Vue pubbliche
```

## Pannello Admin

Accessibile su `/admin`. Richiede un utente attivo con ruolo amministrativo
(`super_admin`, `communication_manager`, `shop_manager`, `sport_coordinator` —
vedi `UserRole::canAccessPanel()`; i ruoli `user` e `customer` non accedono).

Gestisce: articoli, pagine, prodotti, ordini, aste, categorie, tag, partite, giocatrici, staff, sponsor, media, utenti e altro.

## Enums

Il progetto utilizza PHP Enums nativi per gestire gli status in modo type-safe. I principali:

- **`PostStatus`** — `draft`, `publish`
- **`OrderStatus`** — `pending`, `processing`, `paid`, `shipped`, `delivered`, `cancelled`, `refunded`
- **`GameStatus`** — `scheduled`, `in_progress`, `completed`, `postponed`
- **`StockMovementType`** — `Vendita`, `Acquisto`, `Rettifica`
- **`UserRole`** — `super_admin`, `communication_manager`, `shop_manager`, `sport_coordinator`, `user`, `customer`

Gli altri (vedi `app/Enums/`): `AuctionStatus`, `CompetitionType`, `CouponType`,
`PaymentGateway`, `PlayerPosition`, `ProductType`, `SponsorTier`, `StaffType`.
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

Configurato per **DigitalOcean App Platform** (3 componenti: `web`, `worker`, `scheduler`).
Lo spec è `.do/app.yaml` ed è la **fonte autorevole** della configurazione: il deploy
lo riapplica a ogni rilascio, sovrascrivendo le modifiche fatte a mano dal pannello DO.

Il deploy parte dal push su `main` ma è **gated dai test**: il job `deploy` in
`.github/workflows/ci.yml` gira solo se lint, PHPStan e test passano
(`deploy_on_push: false` nello spec).

### Variabili d'ambiente principali

| Variabile | Descrizione |
|---|---|
| `APP_KEY` | Chiave di crittografia Laravel |
| `DB_*` | Connessione MySQL (iniettate da DO: `${sito-savino-db.*}`) |
| `AWS_*` | Credenziali e bucket DigitalOcean Spaces |
| `PREVIEW_AUTH_USER` / `PREVIEW_AUTH_PASS` | Basic auth di pre-lancio su tutto il sito |
| `COMPREFACE_HOST` / `COMPREFACE_KEY` | Riconoscimento facciale (droplet dedicato) |
| `ACTIVECAMPAIGN_*` | Sincronizzazione newsletter |
| `SENTRY_LARAVEL_DSN` | Error tracking (vuoto = disattivato) |

L'elenco completo e i valori sono in `.do/app.yaml`; dettagli in
[`docs/INFRASTRUCTURE.md`](docs/INFRASTRUCTURE.md) §5.

## Documentazione

| Documento | Descrizione |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | Regole di progetto (DB, sicurezza, sync Lega, vincoli MySQL 8.4) |
| [`docs/INFRASTRUCTURE.md`](docs/INFRASTRUCTURE.md) | Architettura DigitalOcean: componenti, env, build/deploy, costi |
| [`docs/BRAND_GUIDELINES.md`](docs/BRAND_GUIDELINES.md) | Linee guida brand: palette colori, varianti logo, regole di utilizzo LVF/SDB |
| [`docs/DB_READONLY_SETUP.md`](docs/DB_READONLY_SETUP.md) | Setup utente MySQL read-only per query e connettore MCP |
| [`BACKUP.md`](BACKUP.md) | Architettura backup: MySQL dump + media sync su DigitalOcean Spaces |
| [`AUDIT_COMPARATIVO.md`](AUDIT_COMPARATIVO.md) | Confronto strutturale con il sito legacy e checklist di migrazione |

## Licenza

Progetto proprietario — Tutti i diritti riservati.
