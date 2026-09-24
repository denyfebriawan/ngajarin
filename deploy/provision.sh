#!/usr/bin/env bash
#
# One-time server setup for Ngajarin on a fresh Ubuntu 24.04 VM.
# Installs Nginx, PHP 8.4, PostgreSQL 18, Composer, Node 24, swap space, a firewall and fail2ban.
#
# Usage (on the server):  sudo bash provision.sh
# Safe to run again: every step checks whether it has already been done.

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "Run this with sudo: sudo bash provision.sh" >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

step() { echo -e "\n==> $1"; }

# ---------------------------------------------------------------------------------------------
step "Swap space (2 GB): lets 1 GB of RAM survive Composer and frontend builds"
if [[ ! -f /swapfile ]]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
echo 'vm.swappiness=10' > /etc/sysctl.d/99-swappiness.conf
sysctl --quiet --system

# ---------------------------------------------------------------------------------------------
step "Base tools"
apt-get update
apt-get install -y software-properties-common ca-certificates curl git unzip ufw fail2ban

# ---------------------------------------------------------------------------------------------
step "PHP 8.4 (from the ondrej/php PPA; Ubuntu 24.04 itself only ships 8.3)"
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y \
    php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl \
    php8.4-zip php8.4-bcmath php8.4-intl php8.4-opcache

# ---------------------------------------------------------------------------------------------
step "Composer (installer verified against its published checksum)"
if ! command -v composer > /dev/null; then
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    actual="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    if [[ "$expected" != "$actual" ]]; then
        echo "Composer installer checksum mismatch, aborting." >&2
        rm /tmp/composer-setup.php
        exit 1
    fi
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi

# ---------------------------------------------------------------------------------------------
step "Node.js 24 (from NodeSource), for building the frontend"
if ! command -v node > /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_24.x | bash -
    apt-get install -y nodejs
fi

# ---------------------------------------------------------------------------------------------
step "PostgreSQL 18 (from the official PostgreSQL apt repository)"
apt-get install -y postgresql-common
/usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
apt-get install -y postgresql-18

# ---------------------------------------------------------------------------------------------
step "Nginx"
apt-get install -y nginx

# ---------------------------------------------------------------------------------------------
step "Firewall: allow only SSH, HTTP and HTTPS"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# ---------------------------------------------------------------------------------------------
step "fail2ban: temporarily ban IPs that keep failing SSH logins"
systemctl enable --now fail2ban

# ---------------------------------------------------------------------------------------------
step "Done. Installed versions:"
php -v | head -n 1
# As the user who ran sudo, not root: Composer (rightly) refuses to run as root without asking.
sudo -u "${SUDO_USER:-root}" composer --version
node --version
npm --version
sudo -u postgres psql -tAc 'SELECT version();'
nginx -v
free -h
