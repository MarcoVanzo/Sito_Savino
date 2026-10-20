#!/bin/bash
set -e

echo "=== Savino Del Bene Volley — Container Start ==="

# 1. Migrazioni
# Non deve avere `|| true`: uno schema disallineato dal codice appena rilasciato
# produce errori peggiori e più difficili da diagnosticare di un avvio fallito.
echo "[1/7] Migrazioni..."
php artisan migrate --force

# 2. Seeder di configurazione
# Sono idempotenti e servono a garantire che le impostazioni di base esistano.
# Il `|| true` che avevano copriva anche i fallimenti veri: un seeder rotto
# passava inosservato per settimane, e le impostazioni mancanti si
# manifestavano poi come valori di default sbagliati in pagina. Ora un
# fallimento ferma l'avvio, che è l'unico modo per accorgersene.
echo "[2/7] Seeder di configurazione..."
php artisan db:seed --class=SiteSettingSeeder --force
php artisan db:seed --class=CorporateGovernanceSeeder --force

# 3. Storage link
# Qui il `|| true` resta legittimo: il link può già esistere da un avvio
# precedente, e in quel caso il comando esce in errore senza che ci sia un
# problema da segnalare.
echo "[3/7] Storage link..."
php artisan storage:link 2>/dev/null || true

# 4. Cache applicativa
# ATTENZIONE: questo cancella anche il battito del pianificatore, che vive
# nella cache condivisa fra i container. L'health check ne tiene conto con
# initial_delay_seconds: 120 (vedi .do/app.yaml). Non ridurre quel valore.
# Svuota solo lo store predefinito: silenziatori e stato degli avvisi stanno
# nello store `persistente` (config/cache.php) e sopravvivono al rilascio.
echo "[4/7] Pulizia cache..."
php artisan cache:clear
# La gallery (12.000 foto) si ricostruisce in coda invece di farlo pagare
# al primo visitatore: il job e' unico, un avvio doppio non accoda doppioni.
php artisan gallery:riscalda-cache || echo "  ⚠️  gallery:riscalda-cache fallito, la cache si costruira' al primo accesso"

# 5. Cache di configurazione, rotte, viste ed eventi
echo "[5/7] Cache di config, rotte, viste ed eventi..."
if [ -n "$AWS_ACCESS_KEY_ID" ] && [ -n "$AWS_SECRET_ACCESS_KEY" ]; then
    echo "  ✅ Credenziali AWS presenti"
    php artisan config:cache
else
    # Senza credenziali, config:cache congelerebbe valori S3 vuoti: meglio
    # leggere env() a ogni richiesta (più lento) che servire media rotti.
    echo "  ⚠️  Credenziali AWS assenti — config:cache saltato"
    php artisan config:clear
fi

php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Filament
echo "[6/7] Ottimizzazione Filament..."
php artisan filament:optimize

# Il pianificatore NON gira più qui: ha un componente dedicato in .do/app.yaml.
# Era avviato in background con `&` prima di exec Apache, e questo lo rendeva
# invisibile quando moriva — il container restava "healthy" perché Apache era
# vivo, mentre aste, sblocco stock e sincronizzazione Lega si erano fermati.

# 7. Server SSR di Inertia (127.0.0.1:13714), nello stesso container: rende
# l'HTML iniziale delle pagine. Si riavvia da solo se esce. Se non parte o cade,
# Laravel serve la pagina senza SSR (il JavaScript la disegna come prima):
# un guasto qui rallenta il primo disegno, non spegne il sito. Controllo dalla
# console: `php artisan inertia:check-ssr`.
# TZ: le date che il server scrive nell'HTML sono quelle che vede un visitatore
# in Italia, come nel browser. Memoria: ~260 MB di RSS a regime (old space
# limitata a 160 MB) sui 1 GB dell'istanza, sorvegliati dall'alert MEM_UTILIZATION.
if command -v node >/dev/null 2>&1 && [ -f bootstrap/ssr/ssr.js ]; then
    echo "[7/7] Server SSR..."
    (
        while true; do
            TZ=Europe/Rome node --max-old-space-size=160 bootstrap/ssr/ssr.js
            echo "  ⚠️  Server SSR uscito (codice $?): riavvio fra 2 secondi" >&2
            sleep 2
        done
    ) &
else
    echo "  ⚠️  Node o bootstrap/ssr/ssr.js assenti: pagine senza SSR"
fi

echo "=== Avvio Apache (con OPcache) ==="
exec heroku-php-apache2 -i php-config/opcache.ini public/
