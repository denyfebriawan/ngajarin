#!/usr/bin/env bash
#
# One-time setup of the Ngajarin app on a server prepared by provision.sh: database, code,
# production .env, PHP-FPM pool, Nginx site, queue worker, scheduler, first deploy, HTTPS.
#
# Usage (on the server, as your normal user):
#   sudo bash setup-app.sh you@example.com
# The email is given to Let's Encrypt for certificate expiry warnings.
# Safe to run again: each step skips work that is already done.

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "Run this with sudo: sudo bash setup-app.sh you@example.com" >&2
    exit 1
fi

LE_EMAIL="${1:?Pass your email for the HTTPS certificate: sudo bash setup-app.sh you@example.com}"
APP_USER="${SUDO_USER:?Run this with sudo from your normal user, not as root}"
APP_DIR=/var/www/ngajarin
DOMAIN=ngajarin.denyfebriawan.dev
REPO=https://github.com/denyfebriawan/ngajarin.git

step() { echo -e "\n==> $1"; }
as_app() { sudo -u "$APP_USER" -H "$@"; }

# ---------------------------------------------------------------------------------------------
step "Database: a 'ngajarin' user that owns a 'ngajarin' database"
DB_PASSWORD=""
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname = 'ngajarin'" | grep -q 1; then
    DB_PASSWORD="$(openssl rand -hex 24)"
    sudo -u postgres psql -c "CREATE ROLE ngajarin LOGIN PASSWORD '$DB_PASSWORD';"
    sudo -u postgres createdb --owner=ngajarin ngajarin
fi

# ---------------------------------------------------------------------------------------------
step "Code: clone the repository into $APP_DIR"
mkdir -p "$APP_DIR"
chown "$APP_USER:$APP_USER" "$APP_DIR"
if [[ ! -d "$APP_DIR/.git" ]]; then
    as_app git clone "$REPO" "$APP_DIR"
else
    # Re-run: bring the existing clone up to date, so it has the latest deploy/ files.
    as_app git -C "$APP_DIR" pull --ff-only
fi

# ---------------------------------------------------------------------------------------------
step "Production .env (written once; never committed)"
if [[ ! -f "$APP_DIR/.env" ]]; then
    if [[ -z "$DB_PASSWORD" ]]; then
        echo "The database user already exists but there is no .env to hold its password." >&2
        echo "Reset it with: sudo -u postgres psql -c \"ALTER ROLE ngajarin PASSWORD 'new'\"" >&2
        exit 1
    fi
    as_app tee "$APP_DIR/.env" > /dev/null <<EOF
APP_NAME=Ngajarin
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://$DOMAIN

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ngajarin
DB_USERNAME=ngajarin
DB_PASSWORD=$DB_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@$DOMAIN"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"
EOF
    chmod 600 "$APP_DIR/.env"
    as_app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --working-dir="$APP_DIR"
    as_app php "$APP_DIR/artisan" key:generate --force
fi

# ---------------------------------------------------------------------------------------------
step "PHP-FPM pool running as $APP_USER"
cp "$APP_DIR/deploy/php-fpm/ngajarin.conf" /etc/php/8.4/fpm/pool.d/ngajarin.conf
systemctl restart php8.4-fpm

# ---------------------------------------------------------------------------------------------
step "Nginx site for $DOMAIN"
if [[ ! -f /etc/nginx/sites-available/ngajarin ]]; then
    cp "$APP_DIR/deploy/nginx/ngajarin.conf" /etc/nginx/sites-available/ngajarin
fi
ln -sf /etc/nginx/sites-available/ngajarin /etc/nginx/sites-enabled/ngajarin
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

# ---------------------------------------------------------------------------------------------
step "First deploy"
as_app bash "$APP_DIR/deploy/deploy.sh"
as_app php "$APP_DIR/artisan" storage:link --force

# ---------------------------------------------------------------------------------------------
step "Queue worker (systemd) and scheduler (cron)"
cp "$APP_DIR/deploy/systemd/ngajarin-queue.service" /etc/systemd/system/ngajarin-queue.service
systemctl daemon-reload
systemctl enable --now ngajarin-queue
( crontab -u "$APP_USER" -l 2> /dev/null | grep -v 'schedule:run' || true
  echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
) | crontab -u "$APP_USER" -

# ---------------------------------------------------------------------------------------------
step "HTTPS certificate from Let's Encrypt (renews automatically)"
apt-get install -y certbot python3-certbot-nginx
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$LE_EMAIL" --redirect

step "Done: https://$DOMAIN"
