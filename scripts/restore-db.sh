#!/usr/bin/env bash
# =============================================================================
# restore-db.sh
# Restore del database da un backup cifrato su DO Spaces
#
# ⚠️ ATTENZIONE: Questo script sovrascrive il database target!
#
# Prerequisiti:
#   - AWS CLI configurato con profilo 'do-admin'
#   - mysql client installato
#   - GPG installato
#   - Passphrase GPG nota
#
# Uso:
#   ./scripts/restore-db.sh                       # lista backup disponibili
#   ./scripts/restore-db.sh --date 2026-06-27     # restore backup specifico
#   ./scripts/restore-db.sh --latest              # restore ultimo backup
# =============================================================================

set -euo pipefail

# --- Configurazione ---
BUCKET_NAME="sito-savino-backups"
REGION="fra1"
ENDPOINT="https://${REGION}.digitaloceanspaces.com"
AWS_PROFILE="${AWS_PROFILE:-do-admin}"
WORK_DIR=$(mktemp -d)

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

info()  { echo -e "${BLUE}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[  OK]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[FAIL]${NC} $1"; }

cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

aws_cmd() {
    aws --profile "$AWS_PROFILE" --endpoint-url "$ENDPOINT" "$@"
}

# --- Parsing argomenti ---
MODE=""
TARGET_DATE=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --date)
            MODE="date"
            TARGET_DATE="$2"
            shift 2
            ;;
        --latest)
            MODE="latest"
            shift
            ;;
        --help|-h)
            echo "Uso: $0 [--latest | --date YYYY-MM-DD]"
            echo ""
            echo "  (nessun argomento)   Lista i backup disponibili"
            echo "  --latest             Restore dell'ultimo backup"
            echo "  --date YYYY-MM-DD    Restore del backup di una data specifica"
            exit 0
            ;;
        *)
            echo "Argomento sconosciuto: $1"
            echo "Usa --help per vedere le opzioni."
            exit 1
            ;;
    esac
done

# --- Lista backup disponibili ---
info "Recupero lista backup dal bucket '$BUCKET_NAME'..."

BACKUPS=$(aws_cmd s3 ls "s3://${BUCKET_NAME}/db/" --recursive \
    | grep '\.sql\.gz\.gpg$' \
    | sort)

if [ -z "$BACKUPS" ]; then
    error "Nessun backup trovato nel bucket!"
    exit 1
fi

BACKUP_COUNT=$(echo "$BACKUPS" | wc -l | tr -d ' ')

if [ -z "$MODE" ]; then
    echo ""
    echo -e "${BOLD}Backup disponibili ($BACKUP_COUNT):${NC}"
    echo "-------------------------------------------"
    echo "$BACKUPS" | while read -r line; do
        SIZE=$(echo "$line" | awk '{print $3}')
        FILE=$(echo "$line" | awk '{print $4}')
        DATE=$(echo "$line" | awk '{print $1}')
        # Converti bytes in formato leggibile
        if [ "$SIZE" -gt 1048576 ]; then
            SIZE_H="$(echo "scale=1; $SIZE/1048576" | bc)MB"
        elif [ "$SIZE" -gt 1024 ]; then
            SIZE_H="$(echo "scale=1; $SIZE/1024" | bc)KB"
        else
            SIZE_H="${SIZE}B"
        fi
        echo -e "  ${GREEN}$DATE${NC}  ${SIZE_H}\t$FILE"
    done
    echo "-------------------------------------------"
    echo ""
    echo "Per eseguire un restore:"
    echo "  $0 --latest              # ultimo backup"
    echo "  $0 --date 2026-06-27     # backup specifico"
    exit 0
fi

# --- Seleziona backup ---
if [ "$MODE" = "latest" ]; then
    BACKUP_FILE=$(echo "$BACKUPS" | tail -1 | awk '{print $4}')
elif [ "$MODE" = "date" ]; then
    BACKUP_FILE=$(echo "$BACKUPS" | grep "$TARGET_DATE" | tail -1 | awk '{print $4}')
fi

if [ -z "$BACKUP_FILE" ]; then
    error "Nessun backup trovato per la selezione specificata!"
    exit 1
fi

FILENAME=$(basename "$BACKUP_FILE")
ok "Backup selezionato: $BACKUP_FILE"

# --- Conferma interattiva ---
echo ""
echo -e "${RED}${BOLD}⚠️  ATTENZIONE — OPERAZIONE DISTRUTTIVA${NC}"
echo -e "${RED}Questo comando sovrascriverà il database target con il contenuto del backup.${NC}"
echo ""
echo -e "  Backup: ${GREEN}$FILENAME${NC}"
echo ""
read -p "Inserisci i dati di connessione al database TARGET:"
echo ""
read -p "  Host MySQL: " DB_HOST
read -p "  Porta:      " DB_PORT
read -p "  Utente:     " DB_USER
read -s -p "  Password:   " DB_PASSWORD
echo ""
read -p "  Database:   " DB_NAME
echo ""

echo -e "${YELLOW}Stai per sovrascrivere il database '${DB_NAME}' su '${DB_HOST}:${DB_PORT}'.${NC}"
read -p "Sei sicuro? Digita 'SI-RESTORE' per confermare: " CONFIRM

if [ "$CONFIRM" != "SI-RESTORE" ]; then
    info "Restore annullato dall'utente."
    exit 0
fi

# --- Step 1: Download ---
info "Step 1/4 — Download backup..."
aws_cmd s3 cp "s3://${BUCKET_NAME}/${BACKUP_FILE}" "${WORK_DIR}/${FILENAME}"
ok "Download completato: $(du -h "${WORK_DIR}/${FILENAME}" | cut -f1)"

# --- Step 2: Decifratura ---
info "Step 2/4 — Decifratura GPG..."
read -s -p "Inserisci la passphrase GPG: " GPG_PASSPHRASE
echo ""

DECRYPTED="${WORK_DIR}/${FILENAME%.gpg}"
gpg --decrypt \
    --batch \
    --passphrase "$GPG_PASSPHRASE" \
    --output "$DECRYPTED" \
    "${WORK_DIR}/${FILENAME}" 2>/dev/null

ok "Decifratura completata."

# --- Step 3: Decompressione ---
info "Step 3/4 — Decompressione..."
SQL_FILE="${DECRYPTED%.gz}"
gunzip -k "$DECRYPTED"
ok "File SQL pronto: $(du -h "$SQL_FILE" | cut -f1)"

# --- Step 4: Import ---
info "Step 4/4 — Import nel database '$DB_NAME'..."

mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASSWORD" \
    "$DB_NAME" < "$SQL_FILE"

if [ $? -eq 0 ]; then
    echo ""
    echo "============================================="
    echo -e "${GREEN}  ✅ RESTORE COMPLETATO CON SUCCESSO${NC}"
    echo "============================================="
    echo "  Backup:   $FILENAME"
    echo "  Database: $DB_NAME @ $DB_HOST:$DB_PORT"
    echo "============================================="
else
    error "Import fallito! Controlla i log di errore MySQL."
    exit 1
fi
