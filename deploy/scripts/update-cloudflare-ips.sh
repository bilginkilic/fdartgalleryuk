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
    # Iki listeyi ayri ayri al ve aralarina satir sonu koy: cloudflare.com/ips-v4
    # ciktisi yeni satirla bitmiyor, dogrudan birlestirilirse son IPv4 araligi ile
    # ilk IPv6 araligi tek satirda birlesiyor ("Bad source address").
    CIDRS="$(mktemp)"
    trap 'rm -f "$CIDRS"' RETURN
    { curl -fsSL https://www.cloudflare.com/ips-v4; echo; curl -fsSL https://www.cloudflare.com/ips-v6; echo; } \
        | tr -d '\r' | grep -E '^[0-9a-fA-F:.]+/[0-9]+$' > "$CIDRS"

    COUNT="$(wc -l < "$CIDRS")"
    if [[ "$COUNT" -lt 10 ]]; then
        echo "    Cloudflare IP listesi eksik gorunuyor (${COUNT} satir), kurallar degistirilmedi." >&2
        rm -f "$CIDRS"
        exit 1
    fi

    while read -r cidr; do
        ufw allow from "$cidr" to any port 80,443 proto tcp comment 'cloudflare'
    done < "$CIDRS"

    # Genel erisim yalnizca Cloudflare kurallari yerine oturduktan SONRA kapatilir.
    ufw delete allow 'Nginx Full' 2>/dev/null || true
    ufw reload
    rm -f "$CIDRS"
    echo "    ${COUNT} Cloudflare araligi eklendi; origin IP'ye dogrudan HTTP(S) erisimi kapali."
fi
