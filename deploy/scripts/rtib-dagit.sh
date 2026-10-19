#!/usr/bin/env bash
# RTIB demosunu sunucuya dagitir: rsync + servis yeniden baslatma + dogrulama.
#
# OTURUM KONTEYNERINDEN calisir, sunucudan degil. Neden rsync: depo
# (bilginkilic/rtib-swordbros) OZEL ve sunucu GitHub'a erisemiyor, yani
# `git pull` ile guncelleme mumkun degil (bkz. ovgcloudukmultisite/adr/0013).
#
# Kullanim:
#   bash rtib-dagit.sh /yol/rtib-swordbros-main        # dagit
#   bash rtib-dagit.sh /yol/rtib-swordbros-main --dry  # kuru calistirma
#
# Kaynak dizin `main` dalinin cikarilmis hali olmali:
#   git -C <klon> fetch --depth 1 origin main && git -C <klon> archive FETCH_HEAD | tar -x -C <hedef>

set -uo pipefail
KAYNAK="${1:-}"
DRY=0
[[ "${2:-}" == "--dry" ]] && DRY=1
[[ -n "$KAYNAK" && -d "$KAYNAK" ]] || { echo "Kullanim: bash $0 <kaynak-dizin> [--dry]" >&2; exit 1; }
[[ -f "$KAYNAK/package.json" && -d "$KAYNAK/site" ]] || {
    echo "HATA: $KAYNAK bir rtib-swordbros kopyasi gibi gorunmuyor (package.json + site/ yok)" >&2; exit 1; }

command -v rsync >/dev/null || { echo "HATA: rsync yok — apt-get install -y rsync" >&2; exit 1; }
[[ -f /tmp/cloudflared ]] || {
    echo "==> cloudflared indiriliyor"
    curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 \
        -o /tmp/cloudflared && chmod +x /tmp/cloudflared; }

export TUNNEL_SERVICE_TOKEN_ID="${CLOUDFLARE_CF_Access_Client_Id:?ortam degiskeni yok}"
export TUNNEL_SERVICE_TOKEN_SECRET="${CLOUDFLARE_CF_Access_Client_Secret:?ortam degiskeni yok}"
SSHC="ssh -o ProxyCommand='/tmp/cloudflared access ssh --hostname ssh.fdartgallery.com' -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR -o ConnectTimeout=30"
sunucu() { eval "$SSHC root@vps \"\$1\""; }

echo "==> Once yedek (icerik kaybolmasin)"
if ! sunucu '/usr/local/sbin/rtib-yedek.sh --local-only'; then
    echo "DURDURULDU: yedek alinamadi. Icerik (server/data/content.json) riske atilmaz." >&2
    echo "  Sunucuda kontrol: ls -l /usr/local/sbin/rtib-yedek.sh" >&2
    exit 1
fi

echo "==> rsync${DRY:+ (kuru)}"
# --delete YOK: server/data/ ve baska calisma zamani dosyalari silinmesin.
RS=(-az --itemize-changes --exclude '.git' --exclude 'server/data/')
[[ $DRY -eq 1 ]] && RS+=(--dry-run)
rsync "${RS[@]}" -e "$SSHC" "$KAYNAK"/ root@vps:/opt/rtib-swordbros/

if [[ $DRY -eq 1 ]]; then echo "KURU CALISTIRMA — hicbir sey degismedi."; exit 0; fi

echo "==> Sahiplik + servis"
sunucu 'chown -R rtib:rtib /opt/rtib-swordbros && systemctl restart rtib && sleep 3 && systemctl is-active rtib'

echo "==> Dogrulama"
sunucu 'curl -sS --max-time 15 http://127.0.0.1:8080/api/health; echo'
for p in "" "brd/" "admin.html"; do
    printf "  /%-12s %s\n" "$p" "$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 "https://rtib.chemiartclick.uk/$p")"
done
echo "==> Mevcut dort site (dokunulmamis olmali)"
sunucu 'for H in fdartgallery.com dev.fdartgallery.com chestnyznak.com.tr dev.chestnyznak.com.tr; do
  printf "  %-32s %s\n" "$H" "$(curl -sS -o /dev/null -w "%{http_code}" --max-time 20 --resolve "$H:443:127.0.0.1" "https://$H/")"
done'
echo "TAMAM"
