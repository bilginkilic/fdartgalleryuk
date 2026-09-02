#!/usr/bin/env bash
# GECICI ERISIM KOPRUSU — sunucuda root olarak bir kez calistirilir.
#
# Ne yapar:
#   1. Bu oturuma ait gecici public key'i /root/.ssh/authorized_keys'e ekler.
#   2. cloudflared kurar.
#   3. localhost:22'yi Cloudflare quick tunnel ile disari acar ve
#      baglanti icin kullanilacak hostname'i ekrana basar.
#
# Neden: Claude oturumunun agi ham TCP/22'yi tasimiyor, yalnizca HTTPS geciyor.
# Tunel SSH'i 443/HTTPS uzerinden tasir.
#
# GUVENLIK:
#   - Quick tunnel adresi rastgele ve tahmin edilemez, ama Access politikasi yok:
#     adresi bilen herkes 22. porta ULASIR. Giris yine de anahtar ile korunur.
#   - Anahtar gecicidir, oturum bitince yok olur.
#   - Is bitince mutlaka temizleyin:  bash claude-access.sh --stop
#
# Kullanim:
#   curl -fsSL <raw-url>/claude-access.sh | bash
#   bash claude-access.sh --stop

set -euo pipefail

KEY_URL="https://raw.githubusercontent.com/bilginkilic/fdartgalleryuk/claude/ovhcloud-vps-multisite-0eb3xj/deploy/claude-session-key.pub"
KEY_TAG="claude-session-01Y2RFQy-ephemeral"
LOG="/var/log/claude-tunnel.log"
PIDFILE="/run/claude-tunnel.pid"

if [[ $EUID -ne 0 ]]; then
    echo "root olarak calistirin:  sudo bash $0" >&2
    exit 1
fi

# --- temizlik modu -----------------------------------------------------------
if [[ "${1:-}" == "--stop" ]]; then
    echo "==> Tunel durduruluyor"
    [[ -f "$PIDFILE" ]] && kill "$(cat "$PIDFILE")" 2>/dev/null || true
    pkill -f 'cloudflared.*--url ssh://localhost:22' 2>/dev/null || true
    rm -f "$PIDFILE"
    echo "==> Gecici anahtar kaldiriliyor"
    if [[ -f /root/.ssh/authorized_keys ]]; then
        sed -i "/${KEY_TAG}/d" /root/.ssh/authorized_keys
    fi
    echo "Temizlendi. Kalan anahtarlar:"
    grep -c . /root/.ssh/authorized_keys 2>/dev/null || echo 0
    exit 0
fi

# --- 1) anahtar --------------------------------------------------------------
echo "==> Gecici erisim anahtari ekleniyor"
mkdir -p /root/.ssh
chmod 700 /root/.ssh
touch /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys

# Once repodaki YEREL kopya denenir. Repolar ozel oldugu icin `curl` ile
# raw.githubusercontent'ten cekmek kimlik dogrulamasi ister ve calismaz;
# yerel dosya varsa ag hic gerekmez.
LOCAL_KEY="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/claude-session-key.pub"
if [[ -s "$LOCAL_KEY" ]]; then
    PUBKEY="$(cat "$LOCAL_KEY")"
    echo "    anahtar yerel dosyadan okundu: ${LOCAL_KEY}"
else
    PUBKEY="$(curl -fsSL "$KEY_URL" || true)"
fi
if [[ "$PUBKEY" != ssh-ed25519* ]]; then
    echo "Anahtar bulunamadi. Beklenen dosya: ${LOCAL_KEY}" >&2
    exit 1
fi
sed -i "/${KEY_TAG}/d" /root/.ssh/authorized_keys
echo "$PUBKEY" >> /root/.ssh/authorized_keys
echo "    eklendi: ${KEY_TAG}"

systemctl enable --now ssh 2>/dev/null || systemctl enable --now sshd 2>/dev/null || true

# --- 2) cloudflared ----------------------------------------------------------
if ! command -v cloudflared >/dev/null 2>&1; then
    echo "==> cloudflared kuruluyor"
    ARCH="$(dpkg --print-architecture 2>/dev/null || echo amd64)"
    curl -fsSL -o /tmp/cloudflared.deb \
        "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-${ARCH}.deb"
    dpkg -i /tmp/cloudflared.deb >/dev/null 2>&1 || apt-get install -f -y
fi
cloudflared --version

# --- 3) tunel ----------------------------------------------------------------
echo "==> Tunel baslatiliyor (SSH -> Cloudflare)"
pkill -f 'cloudflared.*--url ssh://localhost:22' 2>/dev/null || true
rm -f "$LOG"

nohup cloudflared tunnel --no-autoupdate --url ssh://localhost:22 \
    > "$LOG" 2>&1 &
echo $! > "$PIDFILE"

echo "    hostname bekleniyor..."
HOST=""
for _ in $(seq 1 30); do
    sleep 2
    HOST="$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' "$LOG" | head -1 || true)"
    [[ -n "$HOST" ]] && break
done

echo
echo "============================================================"
if [[ -n "$HOST" ]]; then
    echo " TUNEL HAZIR. Asagidaki adresi Claude'a yapistirin:"
    echo
    echo "   ${HOST#https://}"
    echo
else
    echo " Hostname alinamadi. Log'un son satirlari:"
    tail -20 "$LOG"
fi
echo "============================================================"
echo " Is bitince kapatmayi unutmayin:"
echo "   sudo bash /root/claude-access.sh --stop"
echo "============================================================"

# Kendini /root altina kopyala ki --stop icin elde kalsin
cp -f "$0" /root/claude-access.sh 2>/dev/null || \
    curl -fsSL "${KEY_URL%/*}/scripts/claude-access.sh" -o /root/claude-access.sh 2>/dev/null || true
