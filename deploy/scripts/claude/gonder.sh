#!/bin/sh
# Yerel bir script'i base64 ile sunucuya tasir, istege bagli calistirir.
#
# NEDEN: SSH komut dizesinin ICINE heredoc yazmak tirnaklari sessizce bozar.
# 01.09.2026'da canli + dev wp-config.php ayni anda bu yuzden kirildi.
# Bu script o kurali arac haline getirir — elle base64 yazmayin, bunu kullanin.
#
# Kullanim:
#   ./gonder.sh script.php                       # sadece /tmp/script.php'ye kopyala
#   ./gonder.sh script.php --wp <site> [arg...]  # kopyala + wp eval-file
#   ./gonder.sh script.sh  --sh  [arg...]        # kopyala + sh ile calistir
#
# <site>: fdartgallery.com | dev.fdartgallery.com | ...  (/var/www/<site>/public)
# wp her zaman --user=fdsanat ile cagrilir (FD_WP_USER ile degistirilebilir).
set -e
YEREL="$1"; shift || true
[ -f "$YEREL" ] || { echo "HATA: dosya yok: $YEREL" >&2; exit 1; }
AD=$(basename "$YEREL")
UZAK="/tmp/$AD"
KOK=$(dirname "$0")
B64=$(base64 -w0 "$YEREL")

"$KOK/ssh-vps.sh" "echo '$B64' | base64 -d > '$UZAK' && echo 'gonderildi: $UZAK ('\$(wc -c < '$UZAK')' bayt)'"

case "$1" in
  --wp)
    SITE="$2"; shift 2
    [ "$1" = "--" ] && shift          # ayirac wp'ye arguman olarak gecmesin
    "$KOK/ssh-vps.sh" "cd /var/www/$SITE/public && sudo -u www-data wp eval-file '$UZAK' $* --user=${FD_WP_USER:-fdsanat} 2>&1"
    ;;
  --sh)
    shift
    [ "$1" = "--" ] && shift
    "$KOK/ssh-vps.sh" "chmod +x '$UZAK' && '$UZAK' $* 2>&1"
    ;;
esac
