#!/usr/bin/env bash
# rclone kurar ve Cloudflare R2 baglantisini yapilandirir.
#
# Kimlik bilgileri ORTAM DEGISKENLERINDEN okunur, repoya yazilmaz:
#   R2_ACCOUNT_ID   (veya cloudflare_account_id)
#   R2_ACCESS_KEY_ID     (veya cloudflare_access_keyid)
#   R2_SECRET_ACCESS_KEY (veya cloudflare_access_key)
#
# Kullanim (degiskenler tanimliyken):
#   sudo -E bash deploy/scripts/setup-backup.sh
#
# Olusan dosya: /root/.config/rclone/rclone.conf (mod 600)

set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "root olarak calistirin (sudo -E)." >&2; exit 1; }

ACCOUNT="${R2_ACCOUNT_ID:-${cloudflare_account_id:-}}"
KEY_ID="${R2_ACCESS_KEY_ID:-${cloudflare_access_keyid:-}}"
SECRET="${R2_SECRET_ACCESS_KEY:-${cloudflare_access_key:-}}"
BUCKET="${R2_BUCKET:-ovh-vps-backups}"

if [[ -z "$ACCOUNT" || -z "$KEY_ID" || -z "$SECRET" ]]; then
    echo "R2 kimlik bilgileri eksik. Gerekli degiskenler:" >&2
    echo "  R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY" >&2
    echo "sudo kullaniyorsaniz -E ekleyin, yoksa degiskenler aktarilmaz." >&2
    exit 1
fi

if ! command -v rclone >/dev/null 2>&1; then
    echo "==> rclone kuruluyor"
    apt-get update -qq
    apt-get install -y rclone
fi

echo "==> rclone yapilandiriliyor"
mkdir -p /root/.config/rclone
umask 077
cat > /root/.config/rclone/rclone.conf <<EOF
[r2]
type = s3
provider = Cloudflare
access_key_id = ${KEY_ID}
secret_access_key = ${SECRET}
endpoint = https://${ACCOUNT}.r2.cloudflarestorage.com
acl = private
no_check_bucket = true
EOF
chmod 600 /root/.config/rclone/rclone.conf

echo "==> Baglanti testi"
if rclone lsd "r2:${BUCKET}" >/dev/null 2>&1 || rclone ls "r2:${BUCKET}" >/dev/null 2>&1; then
    echo "    R2 baglantisi calisiyor (kova: ${BUCKET})"
else
    echo "    Kova okunamadi: ${BUCKET}" >&2
    echo "    Kovanin var oldugundan ve anahtarin yetkisi oldugundan emin olun." >&2
    exit 1
fi

echo
echo "Hazir. Yedek almak icin:"
echo "  sudo bash deploy/scripts/backup-site.sh <domain> --cron"
