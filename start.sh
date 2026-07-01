#!/bin/bash
set -e

echo "=== Savino Del Bene Volley — Container Start ==="

# 1. Migrations
echo "[1/6] Running migrations..."
php artisan migrate --force

# 2. Storage link
echo "[2/6] Creating storage link..."
php artisan storage:link 2>/dev/null || true

# 3. Clear old cache
echo "[3/6] Clearing cache..."
php artisan cache:clear

# 4. Config cache — solo se le credenziali S3 sono disponibili
echo "[4/6] Config cache..."
if [ -n "$AWS_ACCESS_KEY_ID" ] && [ -n "$AWS_SECRET_ACCESS_KEY" ]; then
    echo "  ✅ AWS credentials found (key: ${AWS_ACCESS_KEY_ID:0:8}...)"
    php artisan config:cache
    echo "  ✅ Config cached successfully"
else
    echo "  ⚠️  AWS credentials NOT found — skipping config:cache"
    echo "  ⚠️  Laravel will read env() live (slower but functional)"
    # Rimuovi eventuale cache vecchia con credenziali vuote
    php artisan config:clear
fi

# 5. Route & View cache (non dipendono da env vars)
echo "[5/6] Route & View cache..."
php artisan route:cache
php artisan view:cache

# 6. Filament optimize
echo "[6/6] Filament optimize..."
php artisan filament:optimize

echo "=== Starting Apache server ==="
exec heroku-php-apache2 public/
