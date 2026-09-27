#!/bin/bash
# SUNUCUDA calisir. Bir sayfada aranan isaretleri sayar — "eklediğim sey
# basildi mi" sorusunun hizli cevabi. Gorunurluk kaniti DEGILDIR; onun icin
# sayfa-olc.py kullanin.
#
# Kullanim (sunucuda): isaret-say.sh <url> <isaret> [isaret...]
U="${1:?kullanim: isaret-say.sh <url> <isaret>...}"; shift
H=$(echo "$U" | awk -F/ '{print $3}')
T=$(mktemp); curl -s --resolve "$H:443:127.0.0.1" "$U" -o "$T"
echo "=== $U  ($(wc -c < "$T") bayt) ==="
for I in "$@"; do printf "  %-34s %s\n" "$I" "$(grep -o -- "$I" "$T" | wc -l)"; done
rm -f "$T"
