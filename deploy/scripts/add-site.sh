#!/usr/bin/env bash
# Yeni site ekler: sistem kullanicisi + izole PHP-FPM havuzu + nginx vhost + DB.
#
# Kullanim:
#   sudo bash deploy/scripts/add-site.sh fdartgallery.com
#   sudo bash deploy/scripts/add-site.sh ikincisite.com          # ayni VPS, ikinci site
#
#   sudo bash deploy/scripts/add-site.sh blog.ornek.com    # alt alan adi
#
# Sertifika icin gereken tam komut kurulum sonunda ekrana basilir
# (apex'te www dahil, alt alan adinda yalnizca kendisi).

set -euo pipefail

DOMAIN="${1:-}"
# Kurulu PHP surumunu bul (Ubuntu 26.04 -> 8.4). PHP_VERSION ile ezilebilir.
PHP_VERSION="${PHP_VERSION:-$(ls -1 /etc/php 2>/dev/null | grep -E '^[0-9]+\.[0-9]+$' | sort -V | tail -1)}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

if [[ -z "$DOMAIN" ]]; then
    echo "Kullanim: sudo bash $0 <domain>" >&2
    exit 1
fi
if [[ $EUID -ne 0 ]]; then
    echo "Bu script root olarak calistirilmali (sudo)." >&2
    exit 1
fi
if [[ -z "$PHP_VERSION" || ! -d "/etc/php/${PHP_VERSION}/fpm" ]]; then
    echo "PHP-FPM bulunamadi. Once: sudo bash deploy/scripts/setup-server.sh" >&2
    exit 1
fi

SITE_SLUG="$(echo "$DOMAIN" | tr '.' '_' | tr -cd '[:alnum:]_')"
SITE_HOME="/var/www/${DOMAIN}"
ROOT="${SITE_HOME}/public"
FPM_SOCK="/run/php/php-fpm-${SITE_SLUG}.sock"

# Kullanici adi: Linux siniri 32 karakter. Kisaltma gerekiyorsa sonuna slug'in
# kisa ozetini ekliyoruz — aksi halde ilk 26 karakteri ayni olan iki uzun alan
# adi AYNI sistem kullanicisini paylasir ve site izolasyonu coker.
if [[ ${#SITE_SLUG} -le 26 ]]; then
    SITE_USER="web_${SITE_SLUG}"
else
    SUFFIX="$(printf '%s' "$SITE_SLUG" | cksum | cut -d' ' -f1 | tail -c 5)"
    SITE_USER="web_$(echo "${SITE_SLUG:0:21}" | sed 's/_*$//')_${SUFFIX}"
fi

# Site daha once kurulduysa mevcut sahibini koru: adlandirma kurali degisse bile
# dosyalar sahipsiz kalmasin, ikinci bir kullanici olusmasin.
if [[ -d "$SITE_HOME" ]]; then
    EXISTING_USER="$(stat -c %U "$SITE_HOME" 2>/dev/null || true)"
    if [[ -n "$EXISTING_USER" && "$EXISTING_USER" != "root" ]]; then
        SITE_USER="$EXISTING_USER"
    fi
fi

# Alt alan adlarinda `www.` uretmek anlamsiz (www.blog.site.com kimse kullanmaz).
# co.uk / com.tr gibi iki parcali uzantilar hala apex sayilir.
if [[ "$(echo "$DOMAIN" | tr -cd '.' | wc -c)" -ge 2 ]] \
   && ! echo "$DOMAIN" | grep -qE '\.(co|com|org|net|gov|edu|ac)\.[a-z]{2}$'; then
    SERVER_NAMES="$DOMAIN"
else
    SERVER_NAMES="$DOMAIN www.${DOMAIN}"
fi

render() {  # render <template> <hedef>
    sed -e "s|{{DOMAIN}}|${DOMAIN}|g" \
        -e "s|{{SERVER_NAMES}}|${SERVER_NAMES}|g" \
        -e "s|{{SITE_SLUG}}|${SITE_SLUG}|g" \
        -e "s|{{SITE_USER}}|${SITE_USER}|g" \
        -e "s|{{ROOT}}|${ROOT}|g" \
        -e "s|{{FPM_SOCK}}|${FPM_SOCK}|g" \
        "$1" > "$2"
}

echo "==> Kullanici ve dizin: ${SITE_USER} -> ${ROOT}"
id -u "$SITE_USER" >/dev/null 2>&1 || useradd --system --home-dir "$SITE_HOME" --shell /usr/sbin/nologin "$SITE_USER"
mkdir -p "$ROOT"
chown -R "${SITE_USER}:${SITE_USER}" "$SITE_HOME"
chmod 755 "$SITE_HOME" "$ROOT"
usermod -aG "$SITE_USER" www-data

echo "==> PHP-FPM havuzu"
render "${REPO_DIR}/deploy/php-fpm/pool.conf.template" "/etc/php/${PHP_VERSION}/fpm/pool.d/${SITE_SLUG}.conf"

echo "==> nginx vhost"
render "${REPO_DIR}/deploy/nginx/templates/fastcgi-php.conf.template" "/etc/nginx/snippets/fastcgi-php-${SITE_SLUG}.conf"

VHOST="/etc/nginx/sites-available/${DOMAIN}.conf"
# certbot TLS blogunu ve yonlendirmeyi bu dosyaya ekliyor. Sablonu uzerine
# yazmak sertifikayi devre disi birakir — o yuzden dokunmuyoruz.
if [[ -f "$VHOST" ]] && grep -q "managed by Certbot" "$VHOST"; then
    echo "    Mevcut vhost certbot tarafindan duzenlenmis, korunuyor: ${VHOST}"
    echo "    Sablonu yeniden uygulamak isterseniz once yedekleyin:"
    echo "      sudo cp ${VHOST} ${VHOST}.bak"
else
    render "${REPO_DIR}/deploy/nginx/templates/site.conf.template" "$VHOST"
fi
ln -sfn "$VHOST" "/etc/nginx/sites-enabled/${DOMAIN}.conf"

echo "==> Veritabani"
DB_NAME="wp_${SITE_SLUG:0:24}"
DB_USER="wp_${SITE_SLUG:0:24}"
DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
CRED_FILE="${SITE_HOME}/db-credentials.txt"

if [[ -f "$CRED_FILE" ]]; then
    echo "    Mevcut kimlik bilgileri korunuyor: ${CRED_FILE}"
else
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    umask 077
    cat > "$CRED_FILE" <<EOF
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASS}
DB_HOST=localhost
EOF
    chown "${SITE_USER}:${SITE_USER}" "$CRED_FILE"
    chmod 600 "$CRED_FILE"
fi

echo "==> WP cron (sistem cron)"
CRON_FILE="/etc/cron.d/wp-cron-${SITE_SLUG}"
cat > "$CRON_FILE" <<EOF
*/10 * * * * ${SITE_USER} /usr/bin/php${PHP_VERSION} ${ROOT}/wp-cron.php >/dev/null 2>&1
EOF
chmod 644 "$CRON_FILE"

echo "==> Servisler yeniden yukleniyor"
nginx -t
systemctl reload "php${PHP_VERSION}-fpm"
systemctl reload nginx

CERTBOT_ARGS=""
for n in $SERVER_NAMES; do CERTBOT_ARGS="${CERTBOT_ARGS}-d ${n} "; done

cat <<EOF

Site hazir: ${DOMAIN}
  Web kok dizini : ${ROOT}
  Sistem kullanici: ${SITE_USER}
  PHP-FPM socket : ${FPM_SOCK}
  DB bilgileri   : ${CRED_FILE}

Siradaki adimlar:
  1. Dosyalari ${ROOT} icine yukleyin, sonra:
       sudo chown -R ${SITE_USER}:${SITE_USER} ${ROOT}
  2. DNS A kaydini VPS IP'sine yonlendirin.
  3. Sertifika: sudo certbot --nginx ${CERTBOT_ARGS}
EOF
