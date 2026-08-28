#!/usr/bin/env bash
# OVHcloud VPS (Debian/Ubuntu) uzerinde multi-site nginx + PHP-FPM + MariaDB temel kurulumu.
# Bir kez calistirilir. Site eklemek icin: add-site.sh
#
# Kullanim:  sudo bash deploy/scripts/setup-server.sh

set -euo pipefail

PHP_VERSION="${PHP_VERSION:-8.3}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

if [[ $EUID -ne 0 ]]; then
    echo "Bu script root olarak calistirilmali (sudo)." >&2
    exit 1
fi

echo "==> Paketler kuruluyor (PHP ${PHP_VERSION})"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y \
    nginx \
    mariadb-server \
    certbot python3-certbot-nginx python3-certbot-dns-cloudflare \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-imagick" \
    "php${PHP_VERSION}-opcache" \
    unzip curl ufw

echo "==> Dizinler"
mkdir -p /var/www/letsencrypt /var/cache/nginx/fastcgi /var/log/php /etc/nginx/snippets
chown -R www-data:www-data /var/cache/nginx /var/log/php

echo "==> Global nginx ayarlari"
install -m 0644 "${REPO_DIR}/deploy/nginx/nginx.conf.d-00-tuning.conf" /etc/nginx/conf.d/00-tuning.conf
install -m 0644 "${REPO_DIR}/deploy/nginx/snippets/security.conf"  /etc/nginx/snippets/security.conf
install -m 0644 "${REPO_DIR}/deploy/nginx/snippets/wordpress.conf" /etc/nginx/snippets/wordpress.conf
install -m 0644 "${REPO_DIR}/deploy/nginx/snippets/cloudflare-realip.conf" /etc/nginx/snippets/cloudflare-realip.conf

echo "==> Cloudflare IP araliklari guncelleniyor"
bash "${REPO_DIR}/deploy/scripts/update-cloudflare-ips.sh" || echo "    (atlanildi, repodaki liste kullanilacak)"

echo "==> Cloudflare IP listesi icin aylik cron"
cat > /etc/cron.d/cloudflare-ips <<EOF
0 4 1 * * root ${REPO_DIR}/deploy/scripts/update-cloudflare-ips.sh >/dev/null 2>&1
EOF
chmod 644 /etc/cron.d/cloudflare-ips

echo "==> Catch-all vhost"
install -m 0644 "${REPO_DIR}/deploy/nginx/sites-available/000-default.conf" /etc/nginx/sites-available/000-default.conf
ln -sfn /etc/nginx/sites-available/000-default.conf /etc/nginx/sites-enabled/000-default.conf
rm -f /etc/nginx/sites-enabled/default

echo "==> WP-CLI"
if ! command -v wp >/dev/null 2>&1; then
    curl -fsSL -o /usr/local/bin/wp \
        https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /usr/local/bin/wp
fi

echo "==> Guvenlik duvari"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo "==> Sertifika yenileme"
systemctl enable --now certbot.timer 2>/dev/null || true

echo "==> nginx testi"
nginx -t
systemctl enable --now nginx "php${PHP_VERSION}-fpm"
systemctl reload nginx

cat <<EOF

Temel kurulum tamam.
Sonraki adim (ilk site):
  sudo bash deploy/scripts/add-site.sh fdartgallery.com

MariaDB henuz sertlestirilmedi, calistirin:
  mysql_secure_installation
EOF
