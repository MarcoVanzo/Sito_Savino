#!/bin/bash
# SessionStart hook per Claude Code on the web.
# Prepara l'ambiente cloud: dipendenze PHP/JS, build frontend e MariaDB per i test.
# Gira SOLO in ambiente remoto (Claude Code on the web). In locale non fa nulla.
#
# Note sui workaround (specifici della sandbox cloud):
#  - Il proxy blocca i download di archivi GitHub (zipball/codeload -> 403): Composer
#    deve installare da sorgente git (--prefer-source), non da dist.
#  - Un auth.json pre-iniettato contiene un token fittizio "proxy-injected" che GitHub
#    rifiuta: va neutralizzato (l'auth reale la inietta il proxy in modo trasparente).
#  - phpstan/phpstan e' dist-only e la sua repo git e' troppo grande (clone in timeout).
#    Insieme a larastan (che lo richiede) e' escluso: sono solo strumenti di analisi
#    statica, non servono a PHPUnit. composer.json/lock vengono ripristinati a fine hook.
#  - Non esiste MySQL/Docker in sandbox: si usa MariaDB (protocollo compatibile, supporta
#    la sintassi MySQL delle migration come ALTER TABLE ... MODIFY COLUMN).
set -euo pipefail

# --- solo ambiente remoto (web) ---
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}"

export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_PROCESS_TIMEOUT=0
export DEBIAN_FRONTEND=noninteractive

log() { echo "[session-start] $*"; }

# ---------------------------------------------------------------------------
# 1. .env applicativo
# ---------------------------------------------------------------------------
if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
  log ".env creato da .env.example"
fi

# ---------------------------------------------------------------------------
# 2. Composer: neutralizza il token fittizio iniettato dal proxy
# ---------------------------------------------------------------------------
AUTH="${COMPOSER_HOME:-/root/.config/composer}/auth.json"
if [ -f "$AUTH" ] && grep -q "proxy-injected" "$AUTH" 2>/dev/null; then
  python3 - "$AUTH" <<'PY'
import json, sys
p = sys.argv[1]
try:
    d = json.load(open(p))
except Exception:
    d = {}
d["github-oauth"] = {}
json.dump(d, open(p, "w"), indent=4)
PY
  log "auth.json: token 'proxy-injected' neutralizzato"
fi

# ---------------------------------------------------------------------------
# 3. Dipendenze PHP (da sorgente git; phpstan/larastan esclusi)
# ---------------------------------------------------------------------------
if [ ! -f vendor/autoload.php ]; then
  log "composer install (da sorgente, puo' richiedere alcuni minuti al primo avvio)"
  cp composer.json .composer.json.hookbak
  cp composer.lock .composer.lock.hookbak
  # ripristina sempre i file di progetto, anche in caso di errore
  restore_composer() {
    [ -f .composer.json.hookbak ] && mv -f .composer.json.hookbak composer.json
    [ -f .composer.lock.hookbak ] && mv -f .composer.lock.hookbak composer.lock
  }
  trap restore_composer EXIT

  python3 - <<'PY'
import json
# composer.json: rimuovi larastan (richiede phpstan) da require-dev
cj = json.load(open("composer.json"))
cj.get("require-dev", {}).pop("larastan/larastan", None)
json.dump(cj, open("composer.json", "w"), indent=4, ensure_ascii=False)
open("composer.json", "a").write("\n")
# composer.lock: rimuovi phpstan/phpstan e larastan/larastan da packages-dev
cl = json.load(open("composer.lock"))
drop = {"phpstan/phpstan", "larastan/larastan"}
cl["packages-dev"] = [p for p in cl["packages-dev"] if p["name"] not in drop]
json.dump(cl, open("composer.lock", "w"), indent=4, ensure_ascii=False)
open("composer.lock", "a").write("\n")
PY

  composer install --no-interaction --prefer-source --no-progress

  restore_composer
  trap - EXIT
  log "dipendenze PHP installate (phpstan/larastan esclusi: analisi statica non disponibile in cloud)"
else
  log "vendor/ gia' presente, salto composer install"
fi

# app key (se .env appena creato e chiave vuota)
if [ -f vendor/autoload.php ] && ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force >/dev/null 2>&1 || true
  log "APP_KEY generata"
fi

# ---------------------------------------------------------------------------
# 4. Dipendenze JS + build frontend (manifest Vite richiesto dai feature test)
# ---------------------------------------------------------------------------
if [ ! -d node_modules ] && [ -f package.json ]; then
  log "npm install"
  npm install --no-progress --no-audit --no-fund
fi
if [ ! -f public/build/manifest.json ] && [ -f package.json ]; then
  log "npm run build (genera il manifest Vite)"
  npm run build
fi

# ---------------------------------------------------------------------------
# 5. MariaDB per i test (MySQL/Docker non disponibili in sandbox)
# ---------------------------------------------------------------------------
if ! command -v mariadbd >/dev/null 2>&1; then
  log "installazione MariaDB"
  apt-get update -qq
  apt-get install -y --no-install-recommends mariadb-server mariadb-client >/dev/null
fi

mkdir -p /var/run/mysqld
chown mysql:mysql /var/run/mysqld 2>/dev/null || true

if ! mysqladmin ping --silent >/dev/null 2>&1; then
  log "avvio mariadbd"
  setsid mariadbd --user=mysql --port=3306 --bind-address=127.0.0.1 \
    >/tmp/mariadb-hook.log 2>&1 < /dev/null &
  for _ in $(seq 1 30); do
    mysqladmin ping --silent >/dev/null 2>&1 && break
    sleep 1
  done
fi

# DB e utente di test attesi da phpunit.xml (root usa auth via socket unix)
mysql -uroot <<'SQL' 2>/dev/null || true
CREATE DATABASE IF NOT EXISTS sito_savino_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'sito_savino'@'127.0.0.1' IDENTIFIED BY 'secret';
CREATE USER IF NOT EXISTS 'sito_savino'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON sito_savino_test.* TO 'sito_savino'@'127.0.0.1';
GRANT ALL PRIVILEGES ON sito_savino_test.* TO 'sito_savino'@'localhost';
FLUSH PRIVILEGES;
SQL

log "ambiente pronto (esegui i test con: php artisan test)"
exit 0
