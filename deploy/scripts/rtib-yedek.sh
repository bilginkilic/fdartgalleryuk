#!/usr/bin/env bash
# RTIB icerik yedegi -> yerel + Cloudflare R2.
#
# NEDEN AYRI SCRIPT: backup-site.sh WordPress icin yazildi (DB dokumu,
# wp-content, /var/www/<domain>/public). RTIB bir Node uygulamasi; yedeklenmesi
# gereken tek sey `/admin.html`'den girilen icerik.
#
# NE YEDEKLENIR: server/data/ (icinde content.json). Kod yedeklenmez — git'te
# (bilginkilic/rtib-swordbros, dal main). `/etc/rtib/rtib.env` de YEDEKLENMEZ:
# icinde sir var ve R2'ye sir konmaz; ADMIN_TOKEN kaybolursa yenisi uretilir.
#
# Kullanim:
#   sudo bash rtib-yedek.sh              # yerel + R2
#   sudo bash rtib-yedek.sh --local-only # yalnizca yerel
#   sudo bash rtib-yedek.sh --cron       # sessiz (cron icin)

set -euo pipefail

LOCAL_ONLY=0; CRON=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --local-only) LOCAL_ONLY=1; shift ;;
        --cron)       CRON=1; shift ;;
        *) echo "Bilinmeyen parametre: $1" >&2; exit 1 ;;
    esac
done
[[ $EUID -eq 0 ]] || { echo "root olarak calistirin (sudo)." >&2; exit 1; }

KAYNAK=/opt/rtib-swordbros/server/data
DEST=/var/backups/sites/rtib
REMOTE="r2:ovh-vps-backups/rtib.chemiartclick.uk"
KEEP_LOCAL=7
KEEP_REMOTE_DAYS=30
STAMP="$(date +%F-%H%M)"

say() { [[ $CRON -eq 1 ]] || echo "$@"; }

[[ -d "$KAYNAK" ]] || { echo "HATA: $KAYNAK yok" >&2; exit 1; }
mkdir -p "$DEST"

ARSIV="${DEST}/rtib-data-${STAMP}.tar.gz"
say "==> Arsiv: $ARSIV"
tar -czf "$ARSIV" -C /opt/rtib-swordbros server/data
chmod 600 "$ARSIV"

# Bos bir arsiv "yedek alindi" sayilmaz — icerik dosyasi gercekten iceride mi?
if ! tar -tzf "$ARSIV" | grep -q 'server/data/'; then
    echo "HATA: arsiv beklenen yolu icermiyor, siliniyor" >&2
    rm -f "$ARSIV"; exit 1
fi
say "    $(du -h "$ARSIV" | cut -f1) · $(tar -tzf "$ARSIV" | wc -l) girdi"

say "==> Yerelde en yeni ${KEEP_LOCAL} arsiv tutuluyor"
ls -1t "${DEST}"/rtib-data-*.tar.gz 2>/dev/null | tail -n +$((KEEP_LOCAL+1)) | xargs -r rm -f

if [[ $LOCAL_ONLY -eq 0 ]]; then
    if ! command -v rclone >/dev/null 2>&1 || [[ ! -f /root/.config/rclone/rclone.conf ]]; then
        say "    UYARI: rclone yapilandirilmamis, R2'ye kopyalanmadi."
    else
        say "==> R2'ye kopyalaniyor"
        # R2 zaman zaman PutObject'e 501 donuyor; backup-site.sh ile ayni onlem.
        rclone copy "$DEST" "${REMOTE}/snapshots" --include "*-${STAMP}.tar.gz" \
            --retries 5 --low-level-retries 10 -q || say "    UYARI: R2 kopyalama basarisiz"
        rclone delete "${REMOTE}/snapshots" --min-age "${KEEP_REMOTE_DAYS}d" -q || true
    fi
fi
say "TAMAM"
