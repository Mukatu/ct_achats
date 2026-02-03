#!/bin/bash
# =============================================================
# Script de déploiement - ct-engagements.speedpro.cg
# À exécuter sur le serveur en tant que speedpro-ct-engagements
# =============================================================

set -e

SITE_DIR="/home/speedpro-ct-engagements/htdocs/ct-engagements.speedpro.cg"
REPO_URL="https://github.com/Mukatu/ct_achats.git"
BRANCH="electron-build"

echo "=== Étape 1 : Sauvegarde du site existant ==="
if [ -d "$SITE_DIR" ] && [ ! -d "${SITE_DIR}.bak" ]; then
    mv "$SITE_DIR" "${SITE_DIR}.bak"
    echo "Backup créé: ${SITE_DIR}.bak"
elif [ -d "$SITE_DIR" ]; then
    rm -rf "${SITE_DIR}.bak"
    mv "$SITE_DIR" "${SITE_DIR}.bak"
    echo "Ancien backup remplacé"
fi

echo ""
echo "=== Étape 2 : Cloner le dépôt ==="
cd /home/speedpro-ct-engagements/htdocs/
git clone "$REPO_URL" temp_clone
cd temp_clone
git checkout "$BRANCH"

echo ""
echo "=== Étape 3 : Copier le backend Laravel ==="
mkdir -p "$SITE_DIR"
cp -r ct_A_backend/* "$SITE_DIR/"
cp -r ct_A_backend/.env.example "$SITE_DIR/.env"
# Copier les fichiers cachés importants du backend
[ -f ct_A_backend/.htaccess ] && cp ct_A_backend/.htaccess "$SITE_DIR/"

echo ""
echo "=== Étape 4 : Copier le frontend build dans public/ ==="
cp -r ct_A_frontend/dist/* "$SITE_DIR/public/"

echo ""
echo "=== Étape 5 : Nettoyage du clone temporaire ==="
cd /home/speedpro-ct-engagements/htdocs/
rm -rf temp_clone

echo ""
echo "=== Étape 6 : Configuration .env production ==="
cat > "$SITE_DIR/.env" << 'ENVEOF'
APP_NAME=CT_Engagements
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://ct-engagements.speedpro.cg

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ct_engagements
DB_USERNAME=ct_engagements
DB_PASSWORD=CHANGEZ_MOI

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

SANCTUM_STATEFUL_DOMAINS=ct-engagements.speedpro.cg
SESSION_DOMAIN=.ct-engagements.speedpro.cg

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@congotelecom.cg"
MAIL_FROM_NAME="${APP_NAME}"
ENVEOF

echo ""
echo "========================================================"
echo "  IMPORTANT: Éditez le fichier .env avant de continuer !"
echo "  Mettez le mot de passe DB créé dans CloudPanel :"
echo "  nano $SITE_DIR/.env"
echo "========================================================"
echo ""
read -p "Appuyez sur Entrée après avoir configuré le .env..."

echo ""
echo "=== Étape 7 : Installation Laravel ==="
cd "$SITE_DIR"

composer install --no-dev --optimize-autoloader

php artisan key:generate

php artisan migrate --force

php artisan db:seed --force

php artisan storage:link

echo ""
echo "=== Étape 8 : Cache et permissions ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache

chmod -R 775 storage bootstrap/cache

echo ""
echo "=========================================="
echo "  Déploiement terminé !"
echo "=========================================="
echo ""
echo "Étapes manuelles restantes :"
echo "1. Configurer le vhost Nginx dans CloudPanel :"
echo ""
echo "   location / {"
echo "       try_files \$uri \$uri/ /index.html;"
echo "   }"
echo ""
echo "   location /api {"
echo "       try_files \$uri \$uri/ /index.php?\$query_string;"
echo "   }"
echo ""
echo "2. Activer SSL Let's Encrypt dans CloudPanel"
echo "3. Tester : https://ct-engagements.speedpro.cg"
echo "   Login : admin@congotelecom.cg / Admin@2025!"
echo ""
