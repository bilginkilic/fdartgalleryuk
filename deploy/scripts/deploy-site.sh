#!/usr/bin/env bash
# Repodaki WordPress dosyalarini siteye kopyalar, izinleri duzeltir,
# istege bagli olarak database/*.sql yedegini yukler.
#
# Kullanim:
#   sudo bash deploy/scripts/deploy-site.sh fdartgallery.com
#   sudo bash deploy/scripts/deploy-site.sh fdartgallery.com --with-db

set -euo pipefail

DOMAIN="${1:-}"
WITH_DB="${2:-}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

if [[ -z "$DOMAIN" ]]; then
    echo "Kullanim: sudo bash $0 <domain> [--with-db]" >&2
    exit 1
fi
if [[ $EUID -ne 0 ]]; then
    echo "Bu script root olarak calistirilmali (sudo)." >&2
    exit 1
fi

SITE_SLUG="$(echo "$DOMAIN" | tr '.' '_' | tr -cd '[:alnum:]_')"
SITE_USER="web_${SITE_SLUG:0:26}"
SITE_HOME="/var/www/${DOMAIN}"
ROOT="${SITE_HOME}/public"
CRED_FILE="${SITE_HOME}/db-credentials.txt"

if [[ ! -d "$ROOT" ]]; then
    echo "Site bulunamadi. Once: sudo bash deploy/scripts/add-site.sh ${DOMAIN}" >&2
    exit 1
fi

echo "==> Dosyalar kopyalaniyor -> ${ROOT}"
# Basindaki / bu kaliplari repo koku ile sinirlar. Aksi halde rsync ayni isimli
# ic dizinleri de atlar — orn. eklentilerin kendi database/ klasorleri.
rsync -a --delete \
    --exclude '/.git/' \
    --exclude '/deploy/' \
    --exclude '/database/' \
    --exclude '/CLAUDE.md' \
    --exclude 'wp-config.php' \
    "${REPO_DIR}/" "${ROOT}/"

echo "==> wp-config.php"
if [[ ! -f "${ROOT}/wp-config.php" ]]; then
    if [[ ! -f "$CRED_FILE" ]]; then
        echo "DB bilgileri yok: ${CRED_FILE}" >&2
        exit 1
    fi
    # shellcheck disable=SC1090
    source "$CRED_FILE"
    SALTS="$(curl -fsSL https://api.wordpress.org/secret-key/1.1/salt/)"
    cat > "${ROOT}/wp-config.php" <<EOF
<?php
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASSWORD}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

${SALTS}

\$table_prefix = 'wp_';

define( 'WP_DEBUG', false );
define( 'DISABLE_WP_CRON', true );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'DISALLOW_FILE_EDIT', true );
define( 'FS_METHOD', 'direct' );

if ( ! empty( \$_SERVER['HTTP_X_FORWARDED_PROTO'] ) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
    \$_SERVER['HTTPS'] = 'on';
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
EOF
    echo "    olusturuldu"
else
    echo "    mevcut, korunuyor"
fi

if [[ "$WITH_DB" == "--with-db" ]]; then
    DUMP="$(ls -1t "${REPO_DIR}"/database/*.sql 2>/dev/null | head -1 || true)"
    if [[ -z "$DUMP" ]]; then
        echo "database/ altinda .sql yedegi bulunamadi." >&2
        exit 1
    fi
    # shellcheck disable=SC1090
    source "$CRED_FILE"
    echo "==> Yedek yukleniyor: ${DUMP} -> ${DB_NAME}"
    mysql --default-character-set=utf8mb4 "$DB_NAME" < "$DUMP"
fi

# Yedegin tablo oneki 'wp_' olmayabilir (guvenlik icin rastgele onek yaygin).
# wp-config.php'yi veritabaninda gercekte bulunan onekle esitle.
if [[ -f "$CRED_FILE" ]]; then
    # shellcheck disable=SC1090
    source "$CRED_FILE"
    PREFIX="$(mysql -N -B "$DB_NAME" -e 'SHOW TABLES LIKE "%options"' 2>/dev/null \
        | grep -E 'options$' | head -1 | sed 's/options$//')"
    if [[ -n "$PREFIX" ]] && ! grep -q "table_prefix = '${PREFIX}'" "${ROOT}/wp-config.php"; then
        sed -i "s/^\$table_prefix = .*/\$table_prefix = '${PREFIX}';/" "${ROOT}/wp-config.php"
        echo "==> Tablo oneki wp-config.php'de guncellendi: ${PREFIX}"
    fi
fi

echo "==> Izinler"
chown -R "${SITE_USER}:${SITE_USER}" "$ROOT"
find "$ROOT" -type d -exec chmod 755 {} +
find "$ROOT" -type f -exec chmod 644 {} +
chmod 640 "${ROOT}/wp-config.php"
mkdir -p "${ROOT}/wp-content/uploads"
chown -R "${SITE_USER}:${SITE_USER}" "${ROOT}/wp-content"
chmod -R 775 "${ROOT}/wp-content/uploads"

echo "==> Bitti. https://${DOMAIN} (sertifika alindiysa)"
