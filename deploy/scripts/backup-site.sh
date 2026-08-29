#!/usr/bin/env bash
# Site yedegi: veritabani dokumu + wp-content arsivi, ardindan Cloudflare R2'ye
# kopyalama. Sunucu seviyesinde calisir — WordPress ele gecirilse bile yedek
# alma zinciri eklentiye bagli degildir.
#
# Kullanim:
#   sudo bash deploy/scripts/backup-site.sh fdartgallery.com
#   sudo bash deploy/scripts/backup-site.sh fdartgallery.com --local-only
#   sudo bash deploy/scripts/backup-site.sh fdartgallery.com --cron
#
# R2 kimlik bilgileri /root/.config/rclone/rclone.conf icinde tutulur (mod 600),
# repoya asla yazilmaz. Yapilandirma icin: setup-backup.sh

set -euo pipefail

DOMAIN="${1:-}"
LOCAL_ONLY=0
CRON=0
shift || true
while [[ $# -gt 0 ]]; do
    case "$1" in
        --local-only) LOCAL_ONLY=1; shift ;;
        --cron)       CRON=1; shift ;;
        *) echo "Bilinmeyen parametre: $1" >&2; exit 1 ;;
    esac
done

[[ -n "$DOMAIN" ]] || { echo "Kullanim: sudo bash $0 <domain> [--local-only] [--cron]" >&2; exit 1; }
[[ $EUID -eq 0 ]] || { echo "root olarak calistirin (sudo)." >&2; exit 1; }

SITE_SLUG="$(echo "$DOMAIN" | tr '.' '_' | tr -cd '[:alnum:]_')"
ROOT="/var/www/${DOMAIN}/public"
CRED_FILE="/var/www/${DOMAIN}/db-credentials.txt"
DEST="/var/backups/sites/${SITE_SLUG}"
REMOTE="r2:ovh-vps-backups/${DOMAIN}"
KEEP_LOCAL=3          # yerelde tutulacak yedek sayisi
KEEP_REMOTE_DAYS=30   # R2'de tutulacak gun sayisi
STAMP="$(date +%F-%H%M)"

[[ -d "$ROOT" ]] || { echo "Site yok: ${ROOT}" >&2; exit 1; }

if [[ "$CRON" == "1" ]]; then
    SCRIPT_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/$(basename "${BASH_SOURCE[0]}")"
    cat > "/etc/cron.d/backup-${SITE_SLUG}" <<EOF
# Gunluk site yedegi (veritabani + wp-content) -> Cloudflare R2
15 4 * * * root ${SCRIPT_PATH} ${DOMAIN} >/var/log/backup-${SITE_SLUG}.log 2>&1
EOF
    chmod 644 "/etc/cron.d/backup-${SITE_SLUG}"
    echo "==> Gunluk cron kuruldu: /etc/cron.d/backup-${SITE_SLUG} (her gece 04:15)"
fi

mkdir -p "$DEST"
chmod 700 /var/backups/sites "$DEST"

echo "==> Veritabani dokumu"
# shellcheck disable=SC1090
source "$CRED_FILE"
mysqldump --single-transaction --quick --default-character-set=utf8mb4 \
    --routines --events "$DB_NAME" | gzip -6 > "${DEST}/db-${STAMP}.sql.gz"
echo "    $(du -h "${DEST}/db-${STAMP}.sql.gz" | cut -f1)"

echo "==> Kod arsivi (tema/eklenti/mu-plugin)"
# uploads BURAYA GIRMEZ — asagida artimli olarak ayrica kopyalanir. Her gece
# 1+ GB'lik ayni arsivi yeniden yuklemenin anlami yok.
tar -C "$ROOT" -czf "${DEST}/files-${STAMP}.tar.gz" \
    --exclude='wp-content/uploads' \
    --exclude='wp-content/cache' \
    --exclude='wp-content/updraft' \
    --exclude='*.log' \
    wp-content 2>/dev/null || true
echo "    $(du -h "${DEST}/files-${STAMP}.tar.gz" | cut -f1)"

echo "==> wp-config.php"
install -m 600 "${ROOT}/wp-config.php" "${DEST}/wp-config-${STAMP}.php"

if [[ "$LOCAL_ONLY" == "0" ]]; then
    if ! command -v rclone >/dev/null 2>&1 || [[ ! -f /root/.config/rclone/rclone.conf ]]; then
        echo "    UYARI: rclone yapilandirilmamis, R2'ye kopyalanmadi." >&2
        echo "    Kurulum: sudo bash deploy/scripts/setup-backup.sh" >&2
    else
        # R2, S3'un bazi ucra ozelliklerini desteklemiyor; checksum dogrulamasini
        # kapatmak "501 Not Implemented" gurultusunu onler.
        RC_OPTS=(--transfers 2 --retries 3 --s3-disable-checksum -q)

        echo "==> Veritabani + kod arsivi R2'ye"
        rclone copy "$DEST" "${REMOTE}/snapshots" --include "*-${STAMP}.*" "${RC_OPTS[@]}"
        echo "    tamam"

        # uploads ARTIMLI kopyalanir: yalnizca yeni/degisen dosyalar gider.
        # `copy` kullaniliyor, `sync` degil — boylece sunucuda yanlislikla silinen
        # bir gorsel R2'deki yedekten de silinmez.
        # .webp dosyalari haric: orijinalinden her an yeniden uretilebiliyorlar.
        echo "==> uploads artimli kopyalaniyor"
        rclone copy "${ROOT}/wp-content/uploads" "${REMOTE}/uploads" \
            --exclude '*.webp' "${RC_OPTS[@]}"
        echo "    tamam"

        echo "==> R2'de ${KEEP_REMOTE_DAYS} gunden eski anlik yedekler siliniyor"
        rclone delete "${REMOTE}/snapshots" --min-age "${KEEP_REMOTE_DAYS}d" -q || true
    fi
fi

echo "==> Yerelde son ${KEEP_LOCAL} yedek tutuluyor"
for pattern in 'db-*.sql.gz' 'files-*.tar.gz' 'wp-config-*.php'; do
    # shellcheck disable=SC2012
    ls -1t "${DEST}"/$pattern 2>/dev/null | tail -n +$((KEEP_LOCAL + 1)) | xargs -r rm -f
done

echo
echo "Bitti. Yerel: ${DEST}"
ls -lh "$DEST" | tail -6
