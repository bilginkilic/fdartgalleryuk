#!/usr/bin/env bash
# Cloudflare DNS kayitlarini yeni VPS IP'sine tasir (cutover).
# CLOUDFLARE_API_TOKEN ortam degiskeni gereklidir.
#
# Varsayilan olarak apex, www ve dev A kayitlarini gunceller; mevcut olmayan
# kayitlar atlanir, her kaydin proxy (turuncu bulut) durumu korunur.
#
# Kullanim:
#   bash deploy/scripts/cloudflare-dns.sh fdartgallery.com 57.129.128.118 --dry-run
#   bash deploy/scripts/cloudflare-dns.sh fdartgallery.com 57.129.128.118
#   bash deploy/scripts/cloudflare-dns.sh fdartgallery.com 57.129.128.118 --names @,www,dev,shop
#
# DIKKAT: Bu canli yayini yeni sunucuya cevirir. Once yeni sunucuda siteyi
#         /etc/hosts ile dogrulayin (bkz. CLAUDE.md).

set -euo pipefail

DOMAIN="${1:-}"
NEW_IP="${2:-}"
shift 2 2>/dev/null || true

DRY=""
SUBS="@,www,dev"
while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run) DRY="--dry-run"; shift ;;
        --names)   SUBS="${2:-}"; shift 2 ;;
        *) echo "Bilinmeyen parametre: $1" >&2; exit 1 ;;
    esac
done

TOKEN="${CLOUDFLARE_API_TOKEN:-${cloudflare_api_token:-}}"
API="https://api.cloudflare.com/client/v4"

if [[ -z "$DOMAIN" || -z "$NEW_IP" ]]; then
    echo "Kullanim: $0 <domain> <yeni-ip> [--dry-run] [--names @,www,dev]" >&2
    exit 1
fi
if [[ -z "$TOKEN" ]]; then
    echo "CLOUDFLARE_API_TOKEN tanimli degil." >&2
    exit 1
fi

cf() { curl -fsS -H "Authorization: Bearer ${TOKEN}" -H "Content-Type: application/json" "$@"; }

ZONE_ID="$(cf "${API}/zones?name=${DOMAIN}" | python3 -c 'import sys,json;r=json.load(sys.stdin)["result"];print(r[0]["id"] if r else "")')"
if [[ -z "$ZONE_ID" ]]; then
    echo "Zone bulunamadi: ${DOMAIN}" >&2
    exit 1
fi
echo "Zone: ${DOMAIN} (${ZONE_ID})"

# Guncellenecek A kayitlari (varsayilan: apex, www, dev)
for SUB in ${SUBS//,/ }; do
    if [[ "$SUB" == "@" || "$SUB" == "$DOMAIN" ]]; then
        NAME="$DOMAIN"
    else
        NAME="${SUB}.${DOMAIN}"
    fi
    REC="$(cf "${API}/zones/${ZONE_ID}/dns_records?type=A&name=${NAME}" \
        | python3 -c 'import sys,json
r = json.load(sys.stdin)["result"]
if r:
    print(r[0]["id"], r[0]["content"], str(r[0]["proxied"]).lower())')"
    if [[ -z "$REC" ]]; then
        echo "  ${NAME}: A kaydi yok, atlaniyor"
        continue
    fi
    read -r REC_ID OLD_IP PROXIED <<<"$REC"

    if [[ "$OLD_IP" == "$NEW_IP" ]]; then
        echo "  ${NAME}: zaten ${NEW_IP}"
        continue
    fi
    if [[ "$DRY" == "--dry-run" ]]; then
        echo "  [dry-run] ${NAME}: ${OLD_IP} -> ${NEW_IP} (proxied=${PROXIED})"
        continue
    fi

    cf -X PATCH "${API}/zones/${ZONE_ID}/dns_records/${REC_ID}" \
        --data "{\"content\":\"${NEW_IP}\",\"proxied\":${PROXIED},\"ttl\":1}" >/dev/null
    echo "  ${NAME}: ${OLD_IP} -> ${NEW_IP} (guncellendi)"
done

echo "Bitti. Geri almak icin ayni komutu eski IP ile calistirin."
