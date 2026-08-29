#!/usr/bin/env bash
# nginx sayfa onbellegini bosaltir (ve istege bagli olarak Cloudflare'i de).
#
# Kullanim:
#   sudo bash deploy/scripts/purge-cache.sh                 # sadece nginx
#   sudo bash deploy/scripts/purge-cache.sh fdartgallery.com  # nginx + Cloudflare
#
# Icerik guncellemesi sonrasi sayfanin hemen gorunmesi icin calistirin.
# (Onbellek omru zaten 10 dakika; bu komut beklemeden temizler.)

set -euo pipefail

DOMAIN="${1:-}"
CACHE_DIR="/var/cache/nginx/fastcgi"

if [[ $EUID -ne 0 ]]; then
    echo "root olarak calistirin (sudo)." >&2
    exit 1
fi

if [[ -d "$CACHE_DIR" ]]; then
    find "$CACHE_DIR" -mindepth 1 -delete
    echo "nginx onbellegi bosaltildi: ${CACHE_DIR}"
else
    echo "Onbellek dizini yok: ${CACHE_DIR}" >&2
fi

systemctl reload nginx

if [[ -n "$DOMAIN" ]]; then
    TOKEN="${CLOUDFLARE_API_TOKEN:-${cloudflare_api_token:-}}"
    if [[ -z "$TOKEN" ]]; then
        echo "CLOUDFLARE_API_TOKEN yok, Cloudflare purge atlandi." >&2
        exit 0
    fi
    API="https://api.cloudflare.com/client/v4"
    ZONE_ID="$(curl -fsS -H "Authorization: Bearer ${TOKEN}" "${API}/zones?name=${DOMAIN}" \
        | python3 -c 'import sys,json;r=json.load(sys.stdin)["result"];print(r[0]["id"] if r else "")')"
    if [[ -n "$ZONE_ID" ]]; then
        curl -fsS -X POST "${API}/zones/${ZONE_ID}/purge_cache" \
            -H "Authorization: Bearer ${TOKEN}" -H "Content-Type: application/json" \
            --data '{"purge_everything":true}' >/dev/null
        echo "Cloudflare onbellegi bosaltildi: ${DOMAIN}"
    else
        echo "Cloudflare zone bulunamadi: ${DOMAIN}" >&2
    fi
fi
