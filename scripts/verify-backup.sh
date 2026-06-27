#!/usr/bin/env bash
# =============================================================================
# verify-backup.sh
# Verifica l'integrità dell'ultimo backup database su DO Spaces
#
# Prerequisiti:
#   - AWS CLI configurato con profilo 'do-admin' o 'do-backup'
#   - GPG installato
#   - Passphrase GPG nota
#
# Uso:
#   ./scripts/verify-backup.sh                    # verifica l'ultimo backup
#   ./scripts/verify-backup.sh 2026-06-27         # verifica un backup specifico
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
NC='\033[0m'

info()  { echo -e "${BLUE}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[  OK]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[FAIL]${NC} $1"; }

cleanup() {
    rm -rf "$WORK_DIR"
    info "File temporanei rimossi."
}
trap cleanup EXIT

aws_cmd() {
    aws --profile "$AWS_PROFILE" --endpoint-url "$ENDPOINT" "$@"
}

# --- Trova l'ultimo backup ---
TARGET_DATE="${1:-}"

info "Ricerca backup nel bucket '$BUCKET_NAME'..."

if [ -n "$TARGET_DATE" ]; then
    info "Cerco backup per data: $TARGET_DATE"
    BACKUP_FILE=$(aws_cmd s3 ls "s3://${BUCKET_NAME}/db/" --recursive \
        | grep "$TARGET_DATE" \
        | grep '\.sql\.gz\.gpg$' \
        | sort | tail -1 | awk '{print $4}')
else
    info "Cerco l'ultimo backup disponibile..."
    BACKUP_FILE=$(aws_cmd s3 ls "s3://${BUCKET_NAME}/db/" --recursive \
        | grep '\.sql\.gz\.gpg$' \
        | sort | tail -1 | awk '{print $4}')
fi

if [ -z "$BACKUP_FILE" ]; then
    error "Nessun backup trovato!"
    exit 1
fi

ok "Backup trovato: $BACKUP_FILE"
CHECKSUM_FILE="${BACKUP_FILE}.sha256"
FILENAME=$(basename "$BACKUP_FILE")

# --- Step 1: Download ---
info "Step 1/5 — Download backup e checksum..."

aws_cmd s3 cp "s3://${BUCKET_NAME}/${BACKUP_FILE}" "${WORK_DIR}/${FILENAME}"
ok "Backup scaricato: $(du -h "${WORK_DIR}/${FILENAME}" | cut -f1)"

CHECKSUM_FOUND=true
aws_cmd s3 cp "s3://${BUCKET_NAME}/${CHECKSUM_FILE}" "${WORK_DIR}/${FILENAME}.sha256" 2>/dev/null || CHECKSUM_FOUND=false

if [ "$CHECKSUM_FOUND" = true ]; then
    ok "Checksum scaricato."
else
    warn "File checksum non trovato — salto verifica SHA-256."
fi

# --- Step 2: Verifica checksum ---
info "Step 2/5 — Verifica integrità (SHA-256)..."

if [ "$CHECKSUM_FOUND" = true ]; then
    cd "$WORK_DIR"
    EXPECTED=$(cat "${FILENAME}.sha256" | awk '{print $1}')
    ACTUAL=$(sha256sum "${FILENAME}" | awk '{print $1}')

    if [ "$EXPECTED" = "$ACTUAL" ]; then
        ok "Checksum SHA-256 valido ✓"
    else
        error "CHECKSUM NON CORRISPONDENTE!"
        error "  Atteso:  $EXPECTED"
        error "  Trovato: $ACTUAL"
        exit 1
    fi
    cd - > /dev/null
else
    warn "Checksum non disponibile, verifico solo decifratura."
fi

# --- Step 3: Decifratura ---
info "Step 3/5 — Decifratura GPG..."

read -s -p "Inserisci la passphrase GPG: " GPG_PASSPHRASE
echo ""

DECRYPTED="${WORK_DIR}/${FILENAME%.gpg}"

gpg --decrypt \
    --batch \
    --passphrase "$GPG_PASSPHRASE" \
    --output "$DECRYPTED" \
    "${WORK_DIR}/${FILENAME}" 2>/dev/null

if [ $? -eq 0 ]; then
    ok "Decifratura completata: $(du -h "$DECRYPTED" | cut -f1)"
else
    error "Decifratura fallita! Passphrase errata?"
    exit 1
fi

# --- Step 4: Decompressione ---
info "Step 4/5 — Decompressione gzip..."

SQL_FILE="${DECRYPTED%.gz}"
gunzip -k "$DECRYPTED"

if [ -f "$SQL_FILE" ]; then
    ok "Decompressione completata: $(du -h "$SQL_FILE" | cut -f1)"
else
    error "Decompressione fallita!"
    exit 1
fi

# --- Step 5: Validazione SQL ---
info "Step 5/5 — Validazione contenuto SQL..."

# Verifica header MySQL
if head -1 "$SQL_FILE" | grep -q "MySQL"; then
    ok "Header MySQL valido."
else
    warn "Header MySQL non standard (potrebbe essere comunque valido)."
fi

# Conta tabelle
TABLE_COUNT=$(grep -c "^CREATE TABLE" "$SQL_FILE" 2>/dev/null || echo "0")
ok "Tabelle trovate nel dump: $TABLE_COUNT"

# Conta INSERT
INSERT_COUNT=$(grep -c "^INSERT INTO" "$SQL_FILE" 2>/dev/null || echo "0")
ok "Statement INSERT trovati: $INSERT_COUNT"

# Verifica completamento dump (Dump completed)
if tail -5 "$SQL_FILE" | grep -qi "dump completed\|Dump completed"; then
    ok "Dump completato correttamente (marker di fine presente)."
else
    warn "Marker 'Dump completed' non trovato — il dump potrebbe essere incompleto."
fi

# Dimensione file SQL
SQL_SIZE=$(du -h "$SQL_FILE" | cut -f1)

# --- Riepilogo ---
echo ""
echo "============================================="
echo -e "${GREEN}  VERIFICA COMPLETATA${NC}"
echo "============================================="
echo ""
echo "  File:       $FILENAME"
echo "  Checksum:   $([ "$CHECKSUM_FOUND" = true ] && echo "✅ Valido" || echo "⚠️ Non disponibile")"
echo "  Cifratura:  ✅ GPG AES-256 — decifratura OK"
echo "  Compresso:  $(du -h "$DECRYPTED" | cut -f1) → $SQL_SIZE decompresso"
echo "  Tabelle:    $TABLE_COUNT"
echo "  INSERT:     $INSERT_COUNT"
echo "  Completato: $(tail -5 "$SQL_FILE" | grep -qi "dump completed" && echo "✅ Sì" || echo "⚠️ Non verificabile")"
echo ""
