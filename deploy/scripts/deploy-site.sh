#!/usr/bin/env bash
# Repodaki WordPress dosyalarini siteye kopyalar, izinleri duzeltir,
# istege bagli olarak database/*.sql yedegini yukler.
#
# Kullanim:
#   sudo bash deploy/scripts/deploy-site.sh fdartgallery.com
#   sudo bash deploy/scripts/deploy-site.sh fdartgallery.com --with-db
#
#   # Baska bir repodan (orn. WordPress koku alt dizinde olan projeler):
#   sudo bash deploy/scripts/deploy-site.sh chestnyznak.chemiartclick.uk \
#        --source /opt/chestnyznakuk/files
#   sudo bash ... --source /opt/x/files --db-file /root/dumps/site.sql
#   sudo bash ... --table-prefix ChestZna_    # yeni kurulumda tablo oneki

set -euo pipefail

DOMAIN="${1:-}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SOURCE_DIR=""
DB_FILE=""
WITH_DB=""
TABLE_PREFIX="wp_"
shift || true
while [[ $# -gt 0 ]]; do
    case "$1" in
        --with-db)  WITH_DB="--with-db"; shift ;;
        --source)   SOURCE_DIR="${2:-}"; shift 2 ;;
        --db-file)  DB_FILE="${2:-}"; WITH_DB="--with-db"; shift 2 ;;
        --table-prefix) TABLE_PREFIX="${2:-wp_}"; shift 2 ;;
        *) echo "Bilinmeyen parametre: $1" >&2; exit 1 ;;
    esac
done

if [[ -z "$DOMAIN" ]]; then
    echo "Kullanim: sudo bash $0 <domain> [--with-db] [--source <dizin>] [--db-file <dosya>]" >&2
    exit 1
fi
if [[ $EUID -ne 0 ]]; then
    echo "Bu script root olarak calistirilmali (sudo)." >&2
    exit 1
fi

# Kaynak belirtilmediyse bu reponun koku kullanilir (fdartgallery.com boyle kuruldu).
SOURCE_DIR="${SOURCE_DIR:-$REPO_DIR}"
[[ -d "$SOURCE_DIR" ]] || { echo "Kaynak dizin yok: ${SOURCE_DIR}" >&2; exit 1; }
[[ -f "${SOURCE_DIR}/wp-settings.php" ]] || {
    echo "Kaynak dizin WordPress koku gorunmuyor (wp-settings.php yok): ${SOURCE_DIR}" >&2
    exit 1
}

SITE_SLUG="$(echo "$DOMAIN" | tr '.' '_' | tr -cd '[:alnum:]_')"
SITE_HOME="/var/www/${DOMAIN}"
ROOT="${SITE_HOME}/public"
CRED_FILE="${SITE_HOME}/db-credentials.txt"

if [[ ! -d "$ROOT" ]]; then
    echo "Site bulunamadi. Once: sudo bash deploy/scripts/add-site.sh ${DOMAIN}" >&2
    exit 1
fi

# Sahibi dizinden oku — add-site.sh ile ayni kurali tekrar uretmeye calismak
# (ve iki yerde farkli sonuc vermesi) riskli.
SITE_USER="$(stat -c %U "$SITE_HOME")"
[[ -n "$SITE_USER" && "$SITE_USER" != "root" ]] || {
    echo "Site dizininin sahibi beklenmedik: ${SITE_USER}" >&2; exit 1; }

echo "==> Dosyalar kopyalaniyor -> ${ROOT}"
# Basindaki / bu kaliplari repo koku ile sinirlar. Aksi halde rsync ayni isimli
# ic dizinleri de atlar — orn. eklentilerin kendi database/ klasorleri.
rsync -a --delete \
    --exclude '/.git/' \
    --exclude '/deploy/' \
    --exclude '/database/' \
    --exclude '/CLAUDE.md' \
    --exclude 'wp-config.php' \
    "${SOURCE_DIR}/" "${ROOT}/"

# mu-plugins repoda deploy/ altinda durur (rsync disi), her dagitimda kopyalanir.
if compgen -G "${REPO_DIR}/deploy/wordpress/mu-plugins/*.php" >/dev/null; then
    echo "==> mu-plugins"
    mkdir -p "${ROOT}/wp-content/mu-plugins"
    cp -f "${REPO_DIR}"/deploy/wordpress/mu-plugins/*.php "${ROOT}/wp-content/mu-plugins/"
    ls -1 "${REPO_DIR}"/deploy/wordpress/mu-plugins/*.php | xargs -n1 basename | sed 's/^/    /'
fi

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

\$table_prefix = '${TABLE_PREFIX}';

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
    if [[ -n "$DB_FILE" ]]; then
        DUMP="$DB_FILE"
        [[ -f "$DUMP" ]] || { echo "Yedek dosyasi yok: ${DUMP}" >&2; exit 1; }
    else
        DUMP="$(ls -1t "${SOURCE_DIR}"/database/*.sql "${REPO_DIR}"/database/*.sql 2>/dev/null | head -1 || true)"
        if [[ -z "$DUMP" ]]; then
            echo "database/ altinda .sql yedegi bulunamadi. --db-file ile belirtin." >&2
            exit 1
        fi
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
    # `|| true` sart: veritabani bosken grep bos doner, `set -e` + `pipefail`
    # altinda bu tum script'i sessizce durdurur (izinler hic uygulanmaz).
    PREFIX="$(mysql -N -B "$DB_NAME" -e 'SHOW TABLES LIKE "%options"' 2>/dev/null \
        | grep -E 'options$' | head -1 | sed 's/options$//' || true)"
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
