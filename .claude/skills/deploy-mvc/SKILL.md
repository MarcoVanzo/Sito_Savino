---
name: deploy-mvc
description: Procedura di deploy in produzione del Sito Savino (Laravel su DigitalOcean App Platform). Usala quando Marco chiede di deployare, pubblicare, mandare in produzione, rilasciare una versione, seguire o controllare una run di deploy, o fare rollback. Il deploy è un job di ci.yml gated dai test, non un workflow da lanciare a mano.
---

# Deploy Sito Savino

> Copia di progetto della skill globale `deploy-mvc`, ridotta al Sito Savino (le sessioni
> cloud non vedono `~/.claude`). Se cambi la procedura, aggiorna anche l'originale.

## «Deploy» = pipeline completa, senza domande

Quando Marco dice «deploy» (o «pubblica», «manda in prod»), è **l'autorizzazione esplicita
all'intera catena fino alla produzione verificata**: vale anche come richiesta di commit/push.
Non elencare i passi da fargli lanciare: **eseguili tu**.

1. `git status` + `git branch --show-current`.
2. Working tree sporco → **committa tu** le modifiche del lavoro in corso (Conventional Commit
   in italiano). Mai `.env*` non-example, dump o file che sembrano segreti.
3. Sei su un branch feature → portalo su `main` tu: push del branch, `gh pr create` + `gh pr merge`.
4. Segui la CI e il job di deploy **fino alla fine** (`gh run watch`, in background) e avvisa.
5. **Verifica del sintomo**: apri in produzione la pagina o l'endpoint toccato
   (`https://savinodelbenevolley.it/...`, browser o `curl`) e controlla che il sintomo non ci
   sia più. «La run è verde» non basta.
6. Un solo messaggio finale: esito, commit andato in prod, prova della verifica al punto 5.

Fermati e chiedi **solo** se: rischi di committare un segreto; nel working tree ci sono modifiche
chiaramente estranee al lavoro corrente (proponi cosa includere); CI o deploy falliscono — lì
riporta l'errore reale e non ri-lanciare da solo.

## Regole

- **Non deployare senza richiesta esplicita di Marco.** Quando c'è, copre tutta la catena.
- Il branch di produzione è `main`. Nessun environment protetto né approvazione.
- **Mai stampare valori di secret.** Solo i nomi.
- **Il repo è pubblico**: mai hardcodare host o credenziali DO.

## Come si deploya

Il deploy è un job dentro `ci.yml`, gated da `build-and-test` (test, Pint, PHPStan, PHPUnit).
Non c'è `workflow_dispatch`.

Prima del push: `scripts/test-toccati.sh` (test che nominano le classi cambiate; la CI
costa ~14 min a giro). Worktree nuovo: `scripts/prepara-worktree.sh <branch>`.
```bash
git push origin main
gh run list --workflow ci.yml --limit 1 && gh run watch <id>
```

`.do/app.yaml` ha `deploy_on_push: false` **intenzionalmente**, per forzare il passaggio dal gate:
non riabilitarlo mai. La spec in `.do/app.yaml` è autorevole: ciò che si aggiunge dal pannello DO
sparisce al deploy successivo.

Post-deploy: `start.sh` gira a ogni avvio del container e fa `php artisan migrate --force`,
seeder, `storage:link`, cache di config/rotte/viste/eventi, `filament:optimize`, `schedule:work`.

Trabocchetti:
- **Le migrazioni girano a ogni start del container**, anche su restart o scale: devono essere idempotenti.
- Non c'è backup DB subito prima del deploy: l'ultimo è il cron delle 03:00 UTC (`backup-db.yml`,
  lanciabile a mano prima di una migrazione rischiosa).
- Prod è MySQL 8.4: niente feature 9.x.
- Query di verifica in prod solo con l'utente `readonly_savino`, mai `doadmin`.

## Se un deploy fallisce

1. `gh run view <id> --log-failed` per il job rosso.
2. Rollback: dal pannello App Platform (deployment precedente) o revert su `main`. Lo schema DB
   già migrato non torna indietro.
3. Riporta l'errore reale a Marco con il log, senza edulcorare. Non ri-lanciare il deploy da solo.
