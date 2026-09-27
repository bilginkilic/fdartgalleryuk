#!/bin/bash
# SUNUCUDA calisir. Verilen yollari origin'den (Cloudflare atlanarak) cekip
# HTTP kodu, boyut, varlik sayilari ve onbellek durumunu yazar.
#
# NEDEN sunucuda: konteynerden curl --resolve SAYILMAZ (HTTPS_PROXY var).
# Bu script gonder.sh ile tasinip orada calistirilir:
#   ./gonder.sh sayfa-denetle.sh --sh fdartgallery.com / /shop/ /cart/
#
# Kullanim (sunucuda): sayfa-denetle.sh <host> [yol...]
H="${1:?kullanim: sayfa-denetle.sh <host> [yol...]}"; shift
[ $# -gt 0 ] || set -- / /shop/ /cart/ /checkout/

printf "=== %s (origin, --resolve 127.0.0.1) ===\n" "$H"
printf "%-34s %5s %9s %7s %7s %6s %9s %8s\n" \
       "yol" "HTTP" "boyut" "script" "style" "urun" "nginx" "kenar"
for Y in "$@"; do
  T=$(mktemp)
  read -r KOD <<< "$(curl -s -o "$T" -D /tmp/_bd -w '%{http_code}' \
        --resolve "$H:443:127.0.0.1" "https://$H$Y")"
  BOYUT=$(wc -c < "$T")
  SCRIPT=$(grep -o '<script[^>]*\ssrc=' "$T" | wc -l)
  STYLE=$(grep -o "rel=['\"]stylesheet" "$T" | wc -l)
  URUN=$(grep -o 'class="[^"]*product type-product' "$T" | wc -l)
  NGINX=$(tr -d '\r' < /tmp/_bd | awk -F': ' 'tolower($1)=="x-fastcgi-cache"{print $2}')
  KENAR=$(tr -d '\r' < /tmp/_bd | awk -F': ' 'tolower($1)=="cf-cache-status"{print $2}')
  printf "%-34s %5s %9s %7s %7s %6s %9s %8s\n" \
         "$Y" "$KOD" "$BOYUT" "$SCRIPT" "$STYLE" "$URUN" "${NGINX:--}" "${KENAR:--}"
  rm -f "$T"
done
