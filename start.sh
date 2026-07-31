#!/bin/bash
set -e

echo "=== Savino Del Bene Volley — Container Start ==="

# 1. Migrazioni
# Non deve avere `|| true`: uno schema disallineato dal codice appena rilasciato
# produce errori peggiori e più difficili da diagnosticare di un avvio fallito.
echo "[1/6] Migrazioni..."
php artisan migrate --force

# 2. Seeder di configurazione
# Sono idempotenti e servono a garantire che le impostazioni di base esistano.
# Il `|| true` che avevano copriva anche i fallimenti veri: un seeder rotto
# passava inosservato per settimane, e le impostazioni mancanti si
# manifestavano poi come valori di default sbagliati in pagina. Ora un
# fallimento ferma l'avvio, che è l'unico modo per accorgersene.
echo "[2/6] Seeder di configurazione..."
php artisan db:seed --class=SiteSettingSeeder --force
php artisan db:seed --class=CorporateGovernanceSeeder --force

# 3. Storage link
# Qui il `|| true` resta legittimo: il link può già esistere da un avvio
# precedente, e in quel caso il comando esce in errore senza che ci sia un
# problema da segnalare.
echo "[3/6] Storage link..."
php artisan storage:link 2>/dev/null || true

# 4. Cache applicativa
# ATTENZIONE: questo cancella anche il battito del pianificatore, che vive
# nella cache condivisa fra i container. L'health check ne tiene conto con
# initial_delay_seconds: 120 (vedi .do/app.yaml). Non ridurre quel valore.
echo "[4/6] Pulizia cache..."
php artisan cache:clear

# 5. Cache di configurazione, rotte, viste ed eventi
echo "[5/6] Cache di config, rotte, viste ed eventi..."
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
echo "[6/6] Ottimizzazione Filament..."
php artisan filament:optimize

# Il pianificatore NON gira più qui: ha un componente dedicato in .do/app.yaml.
# Era avviato in background con `&` prima di exec Apache, e questo lo rendeva
# invisibile quando moriva — il container restava "healthy" perché Apache era
# vivo, mentre aste, sblocco stock e sincronizzazione Lega si erano fermati.

echo "=== Avvio Apache (con OPcache) ==="
exec heroku-php-apache2 -i php-config/opcache.ini public/
