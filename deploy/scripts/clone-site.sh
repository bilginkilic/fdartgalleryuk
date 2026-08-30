#!/usr/bin/env bash
# Canli bir siteyi ayni sunucudaki DENEME (staging) sitesine kopyalar.
# Dosyalar ve veritabani sunucu icinde kopyalanir — ag uzerinden veri gitmez.
#
# Kullanim:
#   sudo bash deploy/scripts/clone-site.sh fdartgallery.com dev.fdartgallery.com
#
# Hedef site ONCEDEN olusturulmus olmali:
#   sudo bash deploy/scripts/add-site.sh dev.fdartgallery.com
#
# Yapilanlar:
#   1. Kaynak dosyalar -> hedef (uploads dahil, .webp haric — yeniden uretilir)
#   2. Kaynak veritabani dokumu -> hedef veritabani
#   3. Adres degisimi (kaynak URL -> hedef URL, tum tablolar)
#   4. Staging koruma eklentisi: mail kapali, arama motorlarina kapali, uyari bandi
#   5. Sayfa onbellegi temizlenir
#
# CANLI SITEYE DOKUNULMAZ — kaynak yalnizca okunur.

set -euo pipefail

SRC="${1:-}"
DST="${2:-}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

[[ -n "$SRC" && -n "$DST" ]] || { echo "Kullanim: sudo bash $0 <kaynak-domain> <hedef-domain>" >&2; exit 1; }
[[ $EUID -eq 0 ]] || { echo "root olarak calistirin (sudo)." >&2; exit 1; }
[[ "$SRC" != "$DST" ]] || { echo "Kaynak ve hedef ayni olamaz." >&2; exit 1; }

SRC_ROOT="/var/www/${SRC}/public"
DST_ROOT="/var/www/${DST}/public"
SRC_CRED="/var/www/${SRC}/db-credentials.txt"
DST_CRED="/var/www/${DST}/db-credentials.txt"

[[ -d "$SRC_ROOT" ]] || { echo "Kaynak site yok: ${SRC_ROOT}" >&2; exit 1; }
[[ -d "$DST_ROOT" ]] || { echo "Hedef site yok. Once: add-site.sh ${DST}" >&2; exit 1; }
[[ -f "$SRC_CRED" && -f "$DST_CRED" ]] || { echo "db-credentials.txt eksik." >&2; exit 1; }

DST_USER="$(stat -c %U "/var/www/${DST}")"
[[ -n "$DST_USER" && "$DST_USER" != "root" ]] || { echo "Hedef sahibi beklenmedik: ${DST_USER}" >&2; exit 1; }

echo "==> Kaynak: ${SRC}  ->  Hedef: ${DST}"

# --- 1) Dosyalar --------------------------------------------------------------
echo "==> Dosyalar kopyalaniyor"
# wp-config.php haric: hedefin kendi veritabani bilgileri korunmali.
# .webp haric: convert-webp.sh hedefte yeniden uretir, bosuna yer kaplamasin.
rsync -a --delete \
    --exclude 'wp-config.php' \
    --exclude '*.webp' \
    --exclude 'wp-content/cache/' \
    --exclude 'wp-content/updraft/' \
    "${SRC_ROOT}/" "${DST_ROOT}/"

# --- 2) Veritabani ------------------------------------------------------------
echo "==> Veritabani kopyalaniyor"
# shellcheck disable=SC1090
source "$SRC_CRED"; SRC_DB="$DB_NAME"
# shellcheck disable=SC1090
source "$DST_CRED"; DST_DB="$DB_NAME"

TMP_DUMP="$(mktemp /tmp/clone-XXXXXX.sql)"
trap 'rm -f "$TMP_DUMP"' EXIT
mysqldump --single-transaction --quick --default-character-set=utf8mb4 \
    --routines --events "$SRC_DB" > "$TMP_DUMP"
echo "    dokum: $(du -h "$TMP_DUMP" | cut -f1)"

mysql -e "DROP DATABASE \`${DST_DB}\`; CREATE DATABASE \`${DST_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`${DST_DB}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
mysql --default-character-set=utf8mb4 "$DST_DB" < "$TMP_DUMP"

# Hedefte wp-config.php yoksa kaynaginkinden turet: add-site.sh bu dosyayi
# olusturmaz (onu deploy-site.sh yapar), clone ise rsync'te disliyor.
if [[ ! -f "${DST_ROOT}/wp-config.php" ]]; then
    echo "    wp-config.php uretiliyor"
    cp "${SRC_ROOT}/wp-config.php" "${DST_ROOT}/wp-config.php"
    # shellcheck disable=SC1090
    source "$DST_CRED"
    sed -i "s/define( *'DB_NAME'.*/define( 'DB_NAME', '${DB_NAME}' );/" "${DST_ROOT}/wp-config.php"
    sed -i "s/define( *'DB_USER'.*/define( 'DB_USER', '${DB_USER}' );/" "${DST_ROOT}/wp-config.php"
    sed -i "s|define( *'DB_PASSWORD'.*|define( 'DB_PASSWORD', '${DB_PASSWORD}' );|" "${DST_ROOT}/wp-config.php"
    # Redis tanimlarini AT: staging canliyla ayni Redis veritabanini kullanirsa
    # iki site birbirinin onbellegini ezer.
    sed -i "/WP_REDIS_/d;/WP_CACHE_KEY_SALT/d" "${DST_ROOT}/wp-config.php"
fi

# Tablo onegi kaynaktan gelir; hedefin wp-config'ini ona esitle.
PREFIX="$(mysql -N -B "$DST_DB" -e 'SHOW TABLES LIKE "%options"' 2>/dev/null \
    | grep -E 'options$' | head -1 | sed 's/options$//' || true)"
if [[ -n "$PREFIX" ]]; then
    sed -i "s/^\$table_prefix = .*/\$table_prefix = '${PREFIX}';/" "${DST_ROOT}/wp-config.php"
    echo "    tablo oneki: ${PREFIX}"
fi

# --- 3) Adresler --------------------------------------------------------------
echo "==> Adresler degistiriliyor"
WP="sudo -u ${DST_USER} wp --path=${DST_ROOT} --skip-plugins --skip-themes"
$WP search-replace "https://${SRC}" "https://${DST}" --all-tables --report-changed-only 2>/dev/null | grep -E "^Success" || true
$WP search-replace "http://${SRC}"  "https://${DST}" --all-tables --report-changed-only 2>/dev/null | grep -E "^Success" || true
$WP option update siteurl "https://${DST}" >/dev/null 2>&1 || true
$WP option update home    "https://${DST}" >/dev/null 2>&1 || true

# --- 4) Staging korumasi ------------------------------------------------------
echo "==> Staging koruma eklentisi"
mkdir -p "${DST_ROOT}/wp-content/mu-plugins"
cp -f "${REPO_DIR}"/deploy/wordpress/staging-mu-plugins/*.php "${DST_ROOT}/wp-content/mu-plugins/"
$WP option update blog_public 0 >/dev/null 2>&1 || true

# --- 5) Izinler ve onbellek ---------------------------------------------------
echo "==> Izinler"
chown -R "${DST_USER}:${DST_USER}" "$DST_ROOT"
chmod 640 "${DST_ROOT}/wp-config.php"

bash "${REPO_DIR}/deploy/scripts/purge-cache.sh" >/dev/null 2>&1 || true

cat <<EOF

Bitti. Deneme ortami hazir: https://${DST}

  - Giden e-posta KAPALI (staging guard mu-plugin)
  - Arama motorlarina KAPALI
  - Sayfalarda "DENEME ORTAMI" bandi gorunur
  - Canli site (${SRC}) DEGISMEDI

Tekrar calistirmak canliyi yeniden kopyalar (hedef veritabani SILINIR).
EOF
