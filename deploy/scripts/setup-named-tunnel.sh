#!/usr/bin/env bash
# Kalici Cloudflare tuneli kurar (systemd servisi olarak).
#
# Quick tunnel'in aksine:
#   - yeniden baslatmalarda kendiliginden ayaga kalkar
#   - adresi sabittir (ssh.fdartgallery.com)
#   - onunde Cloudflare Access vardir: adresi bilmek YETMEZ, service token sart
#
# Kullanim:
#   sudo bash deploy/scripts/setup-named-tunnel.sh /root/.cloudflared-token
#   sudo bash deploy/scripts/setup-named-tunnel.sh --status
#   sudo bash deploy/scripts/setup-named-tunnel.sh --remove
#
# Token dosyasi: tunel token'ini iceren tek satirlik dosya (mod 600).
# Token REPOYA YAZILMAZ — Cloudflare panelinden veya API'den alinir.

set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "root olarak calistirin (sudo)." >&2; exit 1; }

ACTION="${1:-}"

if [[ "$ACTION" == "--status" ]]; then
    systemctl status cloudflared --no-pager 2>/dev/null | head -12 || echo "servis yok"
    echo
    echo "--- baglanti ---"
    journalctl -u cloudflared -n 15 --no-pager 2>/dev/null | grep -iE "registered|connection|error" | tail -5 || true
    exit 0
fi

if [[ "$ACTION" == "--remove" ]]; then
    echo "==> Kalici tunel kaldiriliyor"
    cloudflared service uninstall 2>/dev/null || true
    systemctl disable --now cloudflared 2>/dev/null || true
    rm -f /root/.cloudflared-token
    echo "Kaldirildi. (Cloudflare tarafindaki tunel kaydi durur — panelden silin.)"
    exit 0
fi

TOKEN_FILE="${ACTION:-/root/.cloudflared-token}"
[[ -s "$TOKEN_FILE" ]] || { echo "Token dosyasi yok/bos: ${TOKEN_FILE}" >&2; exit 1; }
TOKEN="$(tr -d '[:space:]' < "$TOKEN_FILE")"
chmod 600 "$TOKEN_FILE"

if ! command -v cloudflared >/dev/null 2>&1; then
    echo "==> cloudflared kuruluyor"
    ARCH="$(dpkg --print-architecture 2>/dev/null || echo amd64)"
    curl -fsSL -o /tmp/cloudflared.deb \
        "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-${ARCH}.deb"
    dpkg -i /tmp/cloudflared.deb >/dev/null 2>&1 || apt-get install -f -y
fi
cloudflared --version

# Onceki kurulum varsa temizle (token degismis olabilir).
cloudflared service uninstall >/dev/null 2>&1 || true

echo "==> systemd servisi kuruluyor"
cloudflared service install "$TOKEN"
systemctl enable --now cloudflared
sleep 5

echo "==> Durum"
systemctl is-active cloudflared
journalctl -u cloudflared -n 20 --no-pager 2>/dev/null \
    | grep -iE "registered tunnel connection|error" | tail -4 || true

cat <<'EOF'

Kalici tunel hazir. Baglanmak icin (istemci tarafinda):

  export TUNNEL_SERVICE_TOKEN_ID=<client_id>
  export TUNNEL_SERVICE_TOKEN_SECRET=<client_secret>
  ssh -o "ProxyCommand=cloudflared access ssh --hostname ssh.fdartgallery.com" root@vps

Token olmadan adres 403 doner — adresi bilmek yetmez.
EOF
