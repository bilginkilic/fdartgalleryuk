#!/usr/bin/env bash
# uploads altindaki JPEG/PNG gorsellerin yanina .webp kopyalarini uretir.
# ORIJINALLER ASLA SILINMEZ veya degistirilmez — yalnizca yeni dosya eklenir.
#
# Ornek:  photo.jpg  ->  photo.jpg.webp   (orijinal yerinde kalir)
#
# Kullanim:
#   sudo bash deploy/scripts/convert-webp.sh fdartgallery.com
#   sudo bash deploy/scripts/convert-webp.sh fdartgallery.com --quality 78
#   sudo bash deploy/scripts/convert-webp.sh fdartgallery.com --clean   # webp'leri sil
#
# Notlar:
#   - Zaten guncel .webp varsa dosya atlanir (tekrar calistirilabilir).
#   - Uretilen .webp orijinalden buyukse silinir; kazanc yoksa tutmanin anlami yok.
#   - Islem `nice`/`ionice` ile calisir, canli siteyi bogmaz.

set -euo pipefail

DOMAIN="${1:-}"
QUALITY=82
CLEAN=0
CRON=0
shift || true
while [[ $# -gt 0 ]]; do
    case "$1" in
        --quality) QUALITY="${2:-82}"; shift 2 ;;
        --clean)   CLEAN=1; shift ;;
        --cron)    CRON=1; shift ;;
        *) echo "Bilinmeyen parametre: $1" >&2; exit 1 ;;
    esac
done

if [[ -z "$DOMAIN" ]]; then
    echo "Kullanim: sudo bash $0 <domain> [--quality 82] [--clean]" >&2
    exit 1
fi
if [[ $EUID -ne 0 ]]; then
    echo "root olarak calistirin (sudo)." >&2
    exit 1
fi

UPLOADS="/var/www/${DOMAIN}/public/wp-content/uploads"
SITE_SLUG="$(echo "$DOMAIN" | tr '.' '_' | tr -cd '[:alnum:]_')"
SITE_USER="web_${SITE_SLUG:0:26}"

if [[ ! -d "$UPLOADS" ]]; then
    echo "uploads dizini yok: ${UPLOADS}" >&2
    exit 1
fi

if [[ "$CLEAN" == "1" ]]; then
    echo "==> Uretilen .webp dosyalari siliniyor"
    find "$UPLOADS" -type f \( -name '*.jpg.webp' -o -name '*.jpeg.webp' -o -name '*.png.webp' \) -delete
    echo "Silindi. (Orijinallere dokunulmadi.)"
    exit 0
fi

command -v cwebp >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y webp; }

# Yeni yuklenen gorseller icin gunluk otomatik donusum.
if [[ "$CRON" == "1" ]]; then
    SCRIPT_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/$(basename "${BASH_SOURCE[0]}")"
    CRON_FILE="/etc/cron.d/webp-${SITE_SLUG}"
    cat > "$CRON_FILE" <<EOF
# Yeni yuklenen gorselleri her gece WebP'ye cevirir (mevcutlar atlanir).
30 3 * * * root ${SCRIPT_PATH} ${DOMAIN} --quality ${QUALITY} >/var/log/webp-${SITE_SLUG}.log 2>&1
EOF
    chmod 644 "$CRON_FILE"
    echo "==> Gunluk cron kuruldu: ${CRON_FILE} (her gece 03:30)"
fi

BEFORE="$(du -sh "$UPLOADS" | cut -f1)"
echo "==> Kaynak: ${UPLOADS} (${BEFORE})"
echo "==> Kalite: ${QUALITY}, paralel is: $(nproc)"

# Tum JPEG/PNG'ler aday; guncel .webp'si olan dosyalar asagida atlanir.
mapfile -t FILES < <(find "$UPLOADS" -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \))

TOTAL="${#FILES[@]}"
echo "==> Aday dosya: ${TOTAL}"
if [[ "$TOTAL" -eq 0 ]]; then echo "Donusturulecek gorsel yok."; exit 0; fi

printf '%s\0' "${FILES[@]}" | nice -n 10 ionice -c3 xargs -0 -P "$(nproc)" -I{} bash -c '
    src="$1"; q="$2"; out="${src}.webp"
    # Guncel webp varsa atla
    if [[ -f "$out" && "$out" -nt "$src" ]]; then exit 0; fi
    if ! cwebp -quiet -q "$q" -m 4 -metadata none "$src" -o "$out" 2>/dev/null; then
        rm -f "$out"; exit 0
    fi
    # Kazanc yoksa webp tutma
    so=$(stat -c%s "$src" 2>/dev/null || echo 0)
    sw=$(stat -c%s "$out" 2>/dev/null || echo 0)
    if [[ "$sw" -ge "$so" || "$sw" -eq 0 ]]; then rm -f "$out"; fi
' _ {} "$QUALITY"

chown -R "${SITE_USER}:${SITE_USER}" "$UPLOADS"
find "$UPLOADS" -type f -name '*.webp' -exec chmod 644 {} +

WEBP_COUNT="$(find "$UPLOADS" -type f -name '*.webp' | wc -l)"
WEBP_SIZE="$(find "$UPLOADS" -type f -name '*.webp' -printf '%s\n' | awk '{s+=$1} END {printf "%.0f", s/1048576}')"
SRC_SIZE="$(find "$UPLOADS" -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \) -printf '%s\n' | awk '{s+=$1} END {printf "%.0f", s/1048576}')"

echo
echo "==> Bitti."
echo "    Orijinal JPEG/PNG toplami : ${SRC_SIZE} MB (dokunulmadi)"
echo "    Uretilen .webp            : ${WEBP_COUNT} dosya, ${WEBP_SIZE} MB"
echo "    Disk: $(df -h / | tail -1 | awk '{print $4" bos"}')"
