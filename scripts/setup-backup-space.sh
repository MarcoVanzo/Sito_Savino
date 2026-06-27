#!/usr/bin/env bash
# =============================================================================
# setup-backup-space.sh
# Setup one-time del bucket di backup su DigitalOcean Spaces
#
# Prerequisiti:
#   - AWS CLI installato (brew install awscli)
#   - Full-access key DO Spaces configurata:
#     aws configure --profile do-admin
#       AWS Access Key ID:     <full-access-key>
#       AWS Secret Access Key: <full-access-secret>
#       Default region name:   fra1
#       Default output format: json
#
# Uso:
#   chmod +x scripts/setup-backup-space.sh
#   ./scripts/setup-backup-space.sh
# =============================================================================

set -euo pipefail

# --- Configurazione ---
BUCKET_NAME="sito-savino-backups"
REGION="fra1"
ENDPOINT="https://${REGION}.digitaloceanspaces.com"
AWS_PROFILE="do-admin"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

info()  { echo -e "${BLUE}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERRORE]${NC} $1"; exit 1; }

# --- Verifica prerequisiti ---
info "Verifico prerequisiti..."

if ! command -v aws &> /dev/null; then
    error "AWS CLI non trovato. Installalo con: brew install awscli"
fi

if ! aws configure list --profile "$AWS_PROFILE" &> /dev/null; then
    error "Profilo AWS '$AWS_PROFILE' non configurato. Esegui: aws configure --profile $AWS_PROFILE"
fi

ok "AWS CLI configurato con profilo '$AWS_PROFILE'"

# Alias per semplificare i comandi
aws_cmd() {
    aws --profile "$AWS_PROFILE" --endpoint-url "$ENDPOINT" "$@"
}

# --- Step 1: Creazione bucket ---
info "Step 1/4 — Creazione bucket '$BUCKET_NAME'..."

if aws_cmd s3api head-bucket --bucket "$BUCKET_NAME" 2>/dev/null; then
    warn "Il bucket '$BUCKET_NAME' esiste già. Proseguo con la configurazione."
else
    aws_cmd s3api create-bucket --bucket "$BUCKET_NAME" --acl private
    ok "Bucket '$BUCKET_NAME' creato con successo."
fi

# --- Step 2: Abilitazione versioning ---
info "Step 2/4 — Abilitazione versioning..."

aws_cmd s3api put-bucket-versioning \
    --bucket "$BUCKET_NAME" \
    --versioning-configuration Status=Enabled

# Verifica
VERSIONING=$(aws_cmd s3api get-bucket-versioning --bucket "$BUCKET_NAME" --query 'Status' --output text)
if [ "$VERSIONING" == "Enabled" ]; then
    ok "Versioning abilitato."
else
    error "Versioning non abilitato correttamente. Stato: $VERSIONING"
fi

# --- Step 3: Applicazione Bucket Policy (Deny Delete) ---
info "Step 3/4 — Applicazione Bucket Policy (Deny Delete)..."

POLICY=$(cat <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "AllowWriteRead",
            "Effect": "Allow",
            "Principal": "*",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:ListBucket",
                "s3:GetBucketLocation"
            ],
            "Resource": [
                "arn:aws:s3:::${BUCKET_NAME}",
                "arn:aws:s3:::${BUCKET_NAME}/*"
            ]
        },
        {
            "Sid": "DenyDeleteObjects",
            "Effect": "Deny",
            "Principal": "*",
            "Action": [
                "s3:DeleteObject",
                "s3:DeleteObjectVersion",
                "s3:DeleteBucket"
            ],
            "Resource": [
                "arn:aws:s3:::${BUCKET_NAME}",
                "arn:aws:s3:::${BUCKET_NAME}/*"
            ]
        }
    ]
}
EOF
)

echo "$POLICY" | aws_cmd s3api put-bucket-policy \
    --bucket "$BUCKET_NAME" \
    --policy file:///dev/stdin

ok "Bucket Policy applicata: DeleteObject e DeleteBucket negati per tutti."

# --- Step 4: Configurazione Lifecycle Rules ---
info "Step 4/4 — Configurazione Lifecycle Rules..."

LIFECYCLE=$(cat <<EOF
{
    "Rules": [
        {
            "ID": "db-backup-retention-90days",
            "Filter": {
                "Prefix": "db/"
            },
            "Status": "Enabled",
            "NoncurrentVersionExpiration": {
                "NoncurrentDays": 90
            }
        },
        {
            "ID": "media-backup-retention-30days",
            "Filter": {
                "Prefix": "media/"
            },
            "Status": "Enabled",
            "NoncurrentVersionExpiration": {
                "NoncurrentDays": 30
            }
        },
        {
            "ID": "cleanup-incomplete-uploads",
            "Filter": {
                "Prefix": ""
            },
            "Status": "Enabled",
            "AbortIncompleteMultipartUpload": {
                "DaysAfterInitiation": 7
            }
        }
    ]
}
EOF
)

echo "$LIFECYCLE" | aws_cmd s3api put-bucket-lifecycle-configuration \
    --bucket "$BUCKET_NAME" \
    --lifecycle-configuration file:///dev/stdin

ok "Lifecycle rules configurate: DB=90gg, Media=30gg, Multipart abort=7gg."

# --- Creazione struttura cartelle ---
info "Creazione struttura cartelle nel bucket..."
echo "" | aws_cmd s3 cp - "s3://${BUCKET_NAME}/db/.keep"
echo "" | aws_cmd s3 cp - "s3://${BUCKET_NAME}/media/.keep"
echo "" | aws_cmd s3 cp - "s3://${BUCKET_NAME}/checksums/.keep"
ok "Struttura cartelle creata (db/, media/, checksums/)."

# --- Test: Verifica che il delete sia bloccato ---
info "Verifica protezione anti-cancellazione..."
if aws_cmd s3 rm "s3://${BUCKET_NAME}/db/.keep" 2>/dev/null; then
    warn "⚠️  ATTENZIONE: La policy Deny Delete potrebbe non essere attiva!"
    warn "   Verifica manualmente la policy sul pannello DO."
else
    ok "Protezione confermata: impossibile cancellare file dal bucket."
fi

# --- Riepilogo ---
echo ""
echo "============================================="
echo -e "${GREEN}  SETUP COMPLETATO CON SUCCESSO${NC}"
echo "============================================="
echo ""
echo "  Bucket:     $BUCKET_NAME"
echo "  Regione:    $REGION"
echo "  Endpoint:   $ENDPOINT"
echo "  Versioning: Abilitato"
echo "  Policy:     Deny Delete (attiva)"
echo "  Lifecycle:  DB=90gg, Media=30gg"
echo ""
echo "  ⚠️  PROSSIMI PASSI:"
echo "  1. Crea una API key dedicata per il backup dal pannello DO"
echo "     (Settings → API → Spaces Keys → Generate New Key)"
echo "  2. Configura i GitHub Secrets (vedi BACKUP.md)"
echo "  3. Attiva MFA sull'account DO se non già attivo"
echo ""
