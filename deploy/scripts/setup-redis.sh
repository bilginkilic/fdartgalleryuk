#!/usr/bin/env bash
# Redis object cache kurar ve bir siteye baglar.
# Multi-site guvenli: her site kendi Redis veritabani indeksini ve anahtar
# onekini kullanir, birbirlerinin onbellegini goremez/silemez.
#
# Kullanim:
#   sudo bash deploy/scripts/setup-redis.sh fdartgallery.com
#   sudo bash deploy/scripts/setup-redis.sh fdartgallery.com --disable

set -euo pipefail

DOMAIN="${1:-}"
ACTION="${2:-enable}"
PHP_VERSION="${PHP_VERSION:-$(ls -1 /etc/php 2>/dev/null | grep -E '^[0-9]+\.[0-9]+$' | sort -V | tail -1)}"

if [[ -z "$DOMAIN" ]]; then
    echo "Kullanim: sudo bash $0 <domain> [--disable]" >&2
    exit 1
fi
if [[ $EUID -ne 0 ]]; then
    echo "root olarak calistirin (sudo)." >&2
    exit 1
fi

SITE_SLUG="$(echo "$DOMAIN" | tr '.' '_' | tr -cd '[:alnum:]_')"
SITE_USER="web_${SITE_SLUG:0:26}"
ROOT="/var/www/${DOMAIN}/public"
WPCFG="${ROOT}/wp-config.php"

[[ -f "$WPCFG" ]] || { echo "wp-config.php yok: ${WPCFG}" >&2; exit 1; }

wpcli() { sudo -u "$SITE_USER" -- wp --path="$ROOT" "$@"; }

if [[ "$ACTION" == "--disable" ]]; then
    echo "==> Redis object cache kapatiliyor: ${DOMAIN}"
    wpcli redis disable 2>/dev/null || true
    rm -f "${ROOT}/wp-content/object-cache.php"
    sed -i "/WP_REDIS_/d;/WP_CACHE_KEY_SALT/d" "$WPCFG"
    echo "Kapatildi. (Redis servisi calismaya devam eder.)"
    exit 0
fi

echo "==> Paketler"
if ! command -v redis-server >/dev/null 2>&1; then
    apt-get update -qq
    apt-get install -y redis-server
fi
dpkg -l "php${PHP_VERSION}-redis" >/dev/null 2>&1 || apt-get install -y "php${PHP_VERSION}-redis"

echo "==> Redis ayarlari"
CONF=/etc/redis/redis.conf
# Yalnizca localhost, bellek siniri ve LRU tahliye politikasi.
sed -i 's/^# *maxmemory .*/maxmemory 256mb/; s/^maxmemory .*/maxmemory 256mb/' "$CONF"
grep -q '^maxmemory ' "$CONF" || echo 'maxmemory 256mb' >> "$CONF"
sed -i 's/^# *maxmemory-policy .*/maxmemory-policy allkeys-lru/; s/^maxmemory-policy .*/maxmemory-policy allkeys-lru/' "$CONF"
grep -q '^maxmemory-policy ' "$CONF" || echo 'maxmemory-policy allkeys-lru' >> "$CONF"
grep -qE '^bind 127\.0\.0\.1' "$CONF" || sed -i 's/^bind .*/bind 127.0.0.1 -::1/' "$CONF"

systemctl enable --now redis-server
systemctl restart redis-server
redis-cli ping

# Her site icin ayri veritabani indeksi (0-15). Ayni sunucudaki siteler
# birbirinin anahtarlarini gormesin diye slug'dan tureniyor.
DB_INDEX=$(( $(printf '%s' "$SITE_SLUG" | cksum | cut -d' ' -f1) % 16 ))

echo "==> wp-config.php'ye Redis tanimlari (db=${DB_INDEX})"
sed -i "/WP_REDIS_/d;/WP_CACHE_KEY_SALT/d" "$WPCFG"
DEFINES=$(cat <<EOF
define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
define( 'WP_REDIS_DATABASE', ${DB_INDEX} );
define( 'WP_REDIS_PREFIX', '${SITE_SLUG}:' );
define( 'WP_REDIS_TIMEOUT', 1 );
define( 'WP_REDIS_READ_TIMEOUT', 1 );
define( 'WP_CACHE_KEY_SALT', '${DOMAIN}' );
EOF
)
# Tanimlar wp-settings.php require'indan once gelmeli.
python3 - "$WPCFG" "$DEFINES" <<'PY'
import sys
path, defines = sys.argv[1], sys.argv[2]
src = open(path).read()
anchor = "if ( ! defined( 'ABSPATH' ) ) {"
if anchor in src:
    src = src.replace(anchor, defines + "\n\n" + anchor, 1)
else:
    src = src.replace("require_once ABSPATH . 'wp-settings.php';",
                      defines + "\n\nrequire_once ABSPATH . 'wp-settings.php';", 1)
open(path, 'w').write(src)
PY

echo "==> redis-cache eklentisi"
wpcli plugin is-installed redis-cache >/dev/null 2>&1 || wpcli plugin install redis-cache
wpcli plugin activate redis-cache
wpcli redis enable

echo "==> Durum"
wpcli redis status || true
echo
echo "Bitti. Onbellegi bosaltmak icin: sudo -u ${SITE_USER} wp --path=${ROOT} redis flush"
