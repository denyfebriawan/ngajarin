#!/usr/bin/env bash
#
# Updates the live app to the latest code on GitHub's main branch.
# Runs as the app user (not root), on the server:  bash /var/www/ngajarin/deploy/deploy.sh
# Phase 5's GitHub Actions workflow runs this same script after CI passes.

set -euo pipefail

cd /var/www/ngajarin

step() { echo -e "\n==> $1"; }

step "Fetching the latest code"
git fetch origin main
# The server never has its own edits, so match GitHub exactly (this also drops stray files).
git reset --hard origin/main

step "PHP dependencies (no dev tools such as Pest or Pint)"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

step "Frontend build"
npm ci --no-audit --no-fund
npm run build

step "Database migrations"
php artisan migrate --force

step "Caching config, routes, views and events"
php artisan optimize

step "Restarting PHP and the queue worker so they run the new code"
sudo systemctl reload php8.4-fpm
php artisan queue:restart

step "Deployed $(git rev-parse --short HEAD): $(git log -1 --format=%s)"
