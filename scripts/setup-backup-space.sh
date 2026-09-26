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

# Solo il Deny. Fino al 26/09/2026 c'era anche uno statement Allow con
# `Principal: "*"` su PutObject/GetObject/ListBucket: in una bucket policy
# "chiunque" vuol dire anche chi non firma la richiesta, quindi il bucket dei
# backup era leggibile ed elencabile — e scrivibile — senza chiavi. I dump sono
# cifrati, ma chiunque poteva scaricarli e caricarci sopra spazzatura. L'accesso
# legittimo passa dalle chiavi di Spaces (backup-writer per i workflow,
# do-admin per il setup), che hanno i loro permessi e non hanno bisogno di un
# Allow nella policy. Il Deny resta per tutti, chiavi comprese.
# Verifica dopo il rilancio (BACKUP.md): un PUT non firmato deve dare 403.
POLICY=$(cat <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
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

ok "Bucket Policy applicata: DeleteObject e DeleteBucket negati per tutti, nessun accesso anonimo."

# --- Step 4: Configurazione Lifecycle Rules ---
info "Step 4/4 — Configurazione Lifecycle Rules..."

# I dump hanno nel nome il loro timestamp e non vengono mai riscritti: non
# diventano mai "noncurrent", quindi la sola NoncurrentVersionExpiration non
# ne cancellava nessuno e i 90 giorni dichiarati erano infiniti. Per `db/`
# serve `Expiration` sull'età dell'oggetto.
#
# `media/` resta com'è, di proposito: è lo specchio del bucket di produzione
# (rclone copy --checksum), e i file non cambiano nome né contenuto. Farli
# scadere per età toglierebbe dal backup foto ancora in produzione, che
# tornerebbero solo al giro della domenica successiva: fino a una settimana
# di buco. Scadono solo le versioni sovrascritte. I manifest settimanali hanno
# nome con la data, come i dump, e seguono i dump.
#
# Da verificare dopo il rilancio (BACKUP.md): che la scadenza non sia
# bloccata dal Deny di DeleteObject nella bucket policy — su S3 le azioni del
# lifecycle non passano dalla policy, su Spaces non è documentato.
LIFECYCLE=$(cat <<EOF
{
    "Rules": [
        {
            "ID": "db-backup-retention-90days",
            "Filter": {
                "Prefix": "db/"
            },
            "Status": "Enabled",
            "Expiration": {
                "Days": 90
            },
            "NoncurrentVersionExpiration": {
                "NoncurrentDays": 90
            }
        },
        {
            "ID": "media-manifest-retention-90days",
            "Filter": {
                "Prefix": "media/_manifests/"
            },
            "Status": "Enabled",
            "Expiration": {
                "Days": 90
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

ok "Lifecycle rules configurate: DB=90gg dalla creazione, manifest media=90gg, versioni sostituite dei media=30gg, multipart abort=7gg."

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

# --- Test: nessun accesso senza firma ---
# Con lo statement Allow su `Principal: "*"` un PUT anonimo veniva accettato.
info "Verifica che il bucket rifiuti le richieste non firmate..."
ANON_STATUS=$(curl -s -o /dev/null -w '%{http_code}' -X PUT --data 'prova' \
    "https://${BUCKET_NAME}.${REGION}.digitaloceanspaces.com/verifica-accesso-anonimo.txt" || true)
if [ "$ANON_STATUS" == "403" ]; then
    ok "PUT non firmato rifiutato (403)."
else
    warn "⚠️  PUT non firmato ha risposto ${ANON_STATUS}: il bucket potrebbe essere scrivibile senza chiavi."
    warn "   Controlla policy e ACL dal pannello DO."
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
echo "  Policy:     solo Deny Delete (nessun accesso anonimo)"
echo "  Lifecycle:  DB=90gg, manifest media=90gg, versioni media sostituite=30gg"
echo ""
echo "  ⚠️  PROSSIMI PASSI:"
echo "  1. Crea una API key dedicata per il backup dal pannello DO"
echo "     (Settings → API → Spaces Keys → Generate New Key)"
echo "  2. Configura i GitHub Secrets (vedi BACKUP.md)"
echo "  3. Attiva MFA sull'account DO se non già attivo"
echo ""
