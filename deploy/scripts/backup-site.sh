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

echo "==> wp-content arsivi"
# Onbellek, gecici dosyalar ve eklentinin kendi yedekleri disarida — bunlar
# yeniden uretilebilir ve arsivi gereksiz sisirir.
tar -C "$ROOT" -czf "${DEST}/files-${STAMP}.tar.gz" \
    --exclude='wp-content/cache' \
    --exclude='wp-content/updraft' \
    --exclude='wp-content/uploads/*/*.webp' \
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
        echo "==> R2'ye kopyalaniyor: ${REMOTE}"
        rclone copy "$DEST" "$REMOTE" --include "*-${STAMP}.*" --transfers 2 --retries 3 -q
        echo "    tamam"
        echo "==> R2'de ${KEEP_REMOTE_DAYS} gunden eski yedekler siliniyor"
        rclone delete "$REMOTE" --min-age "${KEEP_REMOTE_DAYS}d" -q || true
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
