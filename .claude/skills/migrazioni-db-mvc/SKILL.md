---
name: migrazioni-db-mvc
description: Procedura per modificare lo schema MySQL del Sito Savino (migrazioni Laravel). Usala quando serve creare o modificare una tabella o una colonna, aggiungere un indice o una foreign key, scrivere o lanciare una migrazione, capire quali migrazioni sono già applicate, o valutare il rischio di una modifica allo schema in produzione.
---

# Migrazioni DB — Sito Savino

> Copia di progetto della skill globale `migrazioni-db-mvc`, ridotta al Sito Savino (le
> sessioni cloud non vedono `~/.claude`). Se cambi la procedura, aggiorna anche l'originale.
> Vincoli di schema del progetto (colonne translatable, MySQL 8.4…): `CLAUDE.md` §9.

## Regola zero: prima il backup

Prima di una migrazione rischiosa in produzione, verifica che esista un backup **recente**.
Il backup giornaliero (`backup-db.yml`, 03:00 UTC: `mysqldump` → gzip → GPG AES-256 → DO Spaces,
con verifica) può avere fino a ~24h: **non è un backup pre-deploy**. Per una migrazione
distruttiva lancia prima `backup-db.yml` a mano (`workflow_dispatch`).

## Regola uno: idempotenza

Le migrazioni girano **a ogni avvio del container** (`start.sh` → `php artisan migrate --force`),
inclusi restart e scale. Devono poter girare due volte senza rompersi.

## Regola due: mai una migrazione distruttiva in un colpo solo

Rinominare o eliminare una colonna usata dal codice rompe la prod nella finestra tra migrazione e
codice nuovo. Tre passi separati: aggiungi il nuovo → deploya il codice che scrive su entrambi →
rimuovi il vecchio in una migrazione successiva.

## Come sono fatte

- `database/migrations/`, naming Laravel standard, classi anonime, `down()` implementato
  (le migrazioni allarganti hanno `down()` no-op documentato, vedi `CLAUDE.md` §9).
- Registro: tabella `migrations` di Laravel, con batch.
- Comprendono anche migrazioni "dati" (correzioni a guardie dei contenuti CMS), non solo schema.

Riferimento per una migrazione ben scritta (backfill a chunk + down completa):
`database/migrations/2026_07_21_180000_add_password_policy_to_users_and_create_password_histories.php`.

Vincoli: **prod è MySQL 8.4** — niente feature 9.x. Il repo è pubblico.

## Checklist prima di applicare in produzione

1. Backup recente verificato (o lanciato a mano).
2. La migrazione è idempotente.
3. Non è distruttiva, oppure è il terzo passo di una sequenza add → dual-write → drop.
4. Indici e FK espliciti, `DECIMAL` per il denaro, colonne translatable `text`.
5. Testata in locale su un DB con dati realistici, non vuoto.
6. Sai come si torna indietro. Se non lo sai, non applicarla.
