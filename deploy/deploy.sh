#!/usr/bin/env bash
#
# Updates the live app to the latest code on GitHub's main branch.
# Runs as the app user (not root), on the server:  bash /var/www/ngajarin/deploy/deploy.sh
# GitHub Actions runs it after CI passes on main, through an SSH key that can run nothing else.

set -euo pipefail

# Everything is inside { ... } so bash reads the whole script before running any of it. `git reset`
# below can replace this very file mid-run; bash reads scripts as it goes, so the braces guarantee
# the run never depends on how git rewrites the file.
{
    cd /var/www/ngajarin

    step() { echo -e "\n==> $1"; }

    step "Fetching the latest code"
    git fetch origin main
    # The server never has its own edits, so match GitHub exactly (this also drops stray files).
    git reset --hard origin/main

    step "PHP dependencies (no dev tools such as Pest or Pint)"
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

    step "Clearing the previous deploy's caches"
    # The last deploy cached the old routes and config. The frontend build runs
    # `artisan wayfinder:generate`, which would read that stale route cache and miss new routes.
    php artisan optimize:clear

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
    exit
}
