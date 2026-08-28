#!/usr/bin/env bash
# Cloudflare edge IP araliklarini gunceller (real_ip + firewall).
# Ayda bir cron ile calistirilmasi onerilir.
#
# Kullanim: sudo bash deploy/scripts/update-cloudflare-ips.sh [--firewall]

set -euo pipefail

SNIPPET="/etc/nginx/snippets/cloudflare-realip.conf"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

if [[ $EUID -ne 0 ]]; then
    echo "root olarak calistirin (sudo)." >&2
    exit 1
fi

{
    echo "# Otomatik uretildi: $(date -Is)"
    echo "# Kaynak: https://api.cloudflare.com/client/v4/ips"
    echo
    for url in https://www.cloudflare.com/ips-v4 https://www.cloudflare.com/ips-v6; do
        curl -fsSL "$url" | sed 's|^|set_real_ip_from |; s|$|;|'
    done
    echo
    echo "real_ip_header CF-Connecting-IP;"
    echo "real_ip_recursive on;"
} > "$TMP"

if ! grep -q '^set_real_ip_from' "$TMP"; then
    echo "Cloudflare IP listesi alinamadi, mevcut dosya korunuyor." >&2
    exit 1
fi

install -m 0644 "$TMP" "$SNIPPET"
nginx -t && systemctl reload nginx
echo "Guncellendi: ${SNIPPET}"

# --firewall: origin'e sadece Cloudflare'den HTTP(S) gelsin
if [[ "${1:-}" == "--firewall" ]]; then
    echo "==> ufw kurallari Cloudflare'e kisitlaniyor"
    while read -r cidr; do
        [[ -n "$cidr" ]] && ufw allow from "$cidr" to any port 80,443 proto tcp comment 'cloudflare'
    done < <(curl -fsSL https://www.cloudflare.com/ips-v4; curl -fsSL https://www.cloudflare.com/ips-v6)
    ufw delete allow 'Nginx Full' 2>/dev/null || true
    ufw reload
    echo "    Dikkat: artik origin IP'ye dogrudan erisim kapali."
fi
