# Audit Comparativo: Sito Nuovo vs legacy savinodelbenevolley.it

## 1. Premessa e Condizioni del Test
Il presente audit è stato condotto analizzando la struttura reale e le risposte dei due siti web:
- **SITO VECCHIO (Riferimento):** `https://savinodelbenevolley.it/` (Analizzato tramite sitemap.xml, estrazione DOM della homepage e navigazione dei link).
- **SITO NUOVO (Locale):** Progetto Laravel/Inertia/Vue in sviluppo (Analizzato tramite file di routing `web.php` e struttura dei componenti `MegaMenu.vue` e `SiteFooter.vue`).

**Metodologia:** 
Il test è stato condotto inizialmente scansionando i file XML, i nodi HTML (Menu e Footer) e le rotte. Successivamente, forzando la connessione tramite un protocollo di debug remoto (Chrome DevTools MCP), è stato possibile esplorare visivamente le pagine del sito legacy, validando l'interfaccia e la disposizione dei contenuti (es. recapiti e referenti nella pagina Contatti).

## 2. Tabella di Comparazione Strutturale (Pagine Principali)

Basato sulla scansione della sitemap del sito legacy (68 pagine totali) e del mega menu.

| Area / Sezione | SITO VECCHIO | SITO NUOVO (In sviluppo) | Esito / Note |
|---|---|---|---|
| **Home Page** | `/` | `/` | **Mappata**. Entrambi presentano layout hero, news e sponsor. |
| **News** | `/news/` e categorie (Serie A1, CEV, ecc.) | `/news` e `/news/{slug}` | **Mappata**. Il nuovo sito gestisce le news centralmente; le categorie sono filtri dinamici (non rotte separate). |
| **Squadra (A1)** | `/stagione/` (Atlete) | `/stagione` | **Mappata**. |
| **Risultati & Classifica** | `/campionato-2025-2026-andata/` (e Ritorno) e `/classifica-2025-2026/` | `/stagione/risultati`, `/stagione/cev`, `/stagione/coppa-italia` | **Migliorata**. Il nuovo sito consolida risultati e classifiche in rotte uniche per competizione, eliminando le pagine statiche frammentate. |
| **Staff** | Menu non esplicito, in Atlete o Società | `/staff` | **Mappata**. Rotta dedicata nel nuovo sito. |
| **Società (Storia/Org)** | `/societa/` | `/societa/{slug}` (es. `/societa/storia`) | **Mappata**. Gestita dinamicamente tramite CMS nel nuovo sito. |
| **Sponsor** | `/sponsor/` | `/sponsor` e `/sponsor/{slug}` | **Mappata**. Sotto-rotte e redirect 301 dal vecchio `/sponsor/nostri-sponsor` implementati. |
| **Affiliazioni** | `/affiliazioni/` | (Assente rotta hardcoded, probabile fallback CMS) | **Da Verificare**. Probabilmente gestita tramite la rotta catch-all `/{slug}` del CMS. |
| **Contatti** | `/contatti/` (Contact Form 7) | `/contatti` (con `/contatti/submit`) | **Mappata**. Sostituito plugin WP con logica backend nativa Laravel. |
| **Ticketing / Biglietti** | Link esterni (Vivicket, ecc.) | `/ticketing/{slug}` | **Evoluta**. Il nuovo sito prevede sezioni dedicate per la biglietteria. |
| **SDB Youth** | `/news-c/sdb-youth/` (era categoria news) | `/youth/{slug}` e `/stagione/b1` | **Evoluta**. Da semplice categoria blog, lo youth ha ora un'area dedicata. Link `/youth/u17-u15` reindirizza a in-costruzione. |
| **Summer Camp** | Pagine statiche / info | `/summer-camp/{slug}` | **Mappata**. Iscrizione reindirizza a pagina "in-costruzione". |
| **Corporate Governance** | Safeguarding, Modello Organizzativo (PDF in Media WP) | (Nessuna rotta hardcoded) | **Da Verificare**. Documenti accessibili via CMS o Footer link diretti, da mappare correttamente nei menu. |
| **Privacy & Cookie** | `/informativa-privacy/`, `/informativa-cookie/` | (Catch-all CMS o in-costruzione) | **Parziale**. Rotte non esplicite in `web.php` (forse catch-all). |
| **Shop** | Esterno (`shop.savinodelbenevolley.it`) | `/shop/*` (Integrato) | **Migliorata (Focus Primario)**. E-commerce completamente internalizzato (carrello, checkout, aste, dashboard utente). |
| **Newsletter** | Mailchimp/ActiveCampaign iframe | `/newsletter` (Integrato) | **Migliorata**. Form Vue nativo (`NewsletterForm.vue`) e controller Laravel dedicato. |

## 3. Aree Inaccessibili o Non Verificate

Durante la navigazione automatizzata, le seguenti aree non sono state esplorate a fondo per limitazioni tecniche o di stato del progetto:

1. **Dashboard Utente E-commerce (Nuovo Sito):** Le rotte sotto `/profile`, `/shop/ordini` e `/account/verifica-pagamento` richiedono autenticazione (login). Non avendo account test, l'UI di queste pagine *non è verificata*.
2. **Funzionalità di Checkout Reale (Nuovo Sito):** I controller `/shop/checkout` sono presenti, ma l'effettivo comportamento del gateway di pagamento (es. Stripe/Nexi) *non è verificato*.
3. **Flusso Iscrizione Summer Camp:** Sul sito nuovo, la rotta `/summer-camp/iscrizione` effettua un redirect 301 a `/in-costruzione`.

*(Nota: La validazione visiva del sito vecchio, inizialmente marcata come inaccessibile, è stata completata forzando il motore Chrome remoto. I moduli di contatto e le pagine informative risultano standard e replicati nel nuovo layout Vue).*

## 4. Checklist di Implementazione e Migrazione

In base al confronto, ecco le priorità operative per assicurare una transizione senza regressioni:

### 🔴 Alta Priorità (Bloccanti per il Go-Live)
- [ ] **Test completo del flusso Shop:** Verificare carrello, checkout, registrazione utente e gestione aste (nuova feature integrata).
- [ ] **Pagine "In Costruzione":** Completare `/youth/u17-u15` e `/summer-camp/iscrizione` che attualmente effettuano redirect 301 al placeholder.
- [ ] **SEO & Redirect 301:** Assicurarsi che le URL legacy ad alto traffico (es. `/classifica-2025-2026/`) abbiano un redirect 301 verso le nuove route dinamiche (`/stagione/risultati`), per non perdere indicizzazione (alcuni sono in `web.php`, altri mancano).
- [ ] **Corporate Governance e PDF:** Migrare i PDF (Safeguarding, Modello Organizzativo, Bilancio) dal vecchio `/wp-content/uploads/` al nuovo storage (DigitalOcean Spaces) e ripristinare i link nel `SiteFooter.vue`.

### 🟡 Media Priorità (Miglioramenti Esperienza)
- [ ] **Pagine Legali:** Creare le pagine CMS per Privacy Policy e Cookie Policy, garantendo che le URL coincidano con quelle del footer (`/privacy-policy` e `/cookie-policy`) tramite il controller catch-all `/{slug}`.
- [ ] **Form Contatti e Newsletter:** Testare che i rate limiter (`throttle:5,1`) non siano troppo restrittivi in produzione e che l'integrazione API della newsletter funzioni regolarmente.
- [ ] **Affiliazioni:** Creare la pagina nel CMS (slug `affiliazioni`) per mantenere continuità con il vecchio sito.

### 🟢 Bassa Priorità (Ottimizzazioni)
- [ ] **Aste e Metodi di pagamento:** Ottimizzare l'UX della rotta `/account/verifica-pagamento` per assicurare che gli utenti capiscano i requisiti prima di poter puntare.
- [ ] **Pulizia Codice Legacy:** Assicurarsi che non ci siano riferimenti orfani a "WordPress" o "Contact Form 7" nei testi migrati.
