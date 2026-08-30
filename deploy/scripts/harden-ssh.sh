#!/usr/bin/env bash
# SSH sertlestirme. Iki asamali — cunku parola girisini kapatmak,
# kullanicinin anahtari sunucuda yoksa erisimi tamamen keser.
#
# Kullanim:
#   sudo bash deploy/scripts/harden-ssh.sh                 # guvenli ayarlar
#   sudo bash deploy/scripts/harden-ssh.sh --add-key <dosya|"ssh-ed25519 ...">
#   sudo bash deploy/scripts/harden-ssh.sh --disable-password
#   sudo bash deploy/scripts/harden-ssh.sh --status
#
# --disable-password, sunucuda EN AZ BIR gecerli anahtar yoksa CALISMAZ.
# Anahtar kontrolu, benim gecici oturum anahtarimi (claude-session-*) saymaz:
# o anahtar oturum bitince silinir, ona guvenip parolayi kapatmak kilitlenme
# demektir.
#
# Acil durum: OVH Manager -> VPS -> KVM konsolu. Konsolda klavye eslemesi
# bozuk olabilir; `sudo loadkeys us` duzeltir.

set -euo pipefail

CONF=/etc/ssh/sshd_config.d/00-hardening.conf
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SRC="${REPO_DIR}/deploy/ssh/00-hardening.conf"
KEY_USER="${SSH_KEY_USER:-ubuntu}"

[[ $EUID -eq 0 ]] || { echo "root olarak calistirin (sudo)." >&2; exit 1; }

# Gecici oturum anahtarlarini saymadan, gercek anahtar sayisini dondurur.
gercek_anahtar_sayisi() {
    local n=0 f
    for f in /root/.ssh/authorized_keys /home/*/.ssh/authorized_keys; do
        [[ -s "$f" ]] || continue
        n=$(( n + $(grep -c . "$f" 2>/dev/null || echo 0) - $(grep -c 'claude-session-' "$f" 2>/dev/null || echo 0) ))
    done
    echo "$n"
}

dogrula_ve_yukle() {
    if ! sshd -t; then
        echo "HATA: sshd yapilandirmasi gecersiz — degisiklik geri alindi." >&2
        return 1
    fi
    # reload, restart DEGIL: acik oturumlar kopmaz.
    systemctl reload ssh
    echo "==> yeniden yuklendi"
}

case "${1:-}" in
--status)
    echo "== etkin ayarlar =="
    sshd -T | grep -iE '^(permitrootlogin|passwordauthentication|pubkeyauthentication|permitemptypasswords|kbdinteractiveauthentication|maxauthtries|logingracetime|x11forwarding|allowtcpforwarding|allowagentforwarding)' | sort | sed 's/^/  /'
    echo "== anahtarlar (gecici oturum anahtarlari haric): $(gercek_anahtar_sayisi) =="
    for f in /root/.ssh/authorized_keys /home/*/.ssh/authorized_keys; do
        [[ -s "$f" ]] && awk -v F="$f" '{print "  "F": "$1" ... "$NF}' "$f"
    done
    echo "== parola durumlari =="
    for u in root "$KEY_USER"; do passwd -S "$u" 2>/dev/null | sed 's/^/  /'; done
    exit 0
    ;;

--add-key)
    ANAHTAR="${2:?Kullanim: --add-key <dosya veya \"ssh-ed25519 AAAA... yorum\">}"
    [[ -f "$ANAHTAR" ]] && ANAHTAR="$(cat "$ANAHTAR")"
    case "$ANAHTAR" in
        ssh-ed25519\ *|ssh-rsa\ *|ecdsa-sha2-*|sk-ssh-*) ;;
        *) echo "HATA: bu bir SSH public key'e benzemiyor." >&2; exit 1 ;;
    esac
    id "$KEY_USER" >/dev/null 2>&1 || { echo "HATA: '$KEY_USER' kullanicisi yok." >&2; exit 1; }
    HOME_DIR="$(getent passwd "$KEY_USER" | cut -d: -f6)"
    install -d -m 700 -o "$KEY_USER" -g "$KEY_USER" "${HOME_DIR}/.ssh"
    touch "${HOME_DIR}/.ssh/authorized_keys"
    if grep -qF "$ANAHTAR" "${HOME_DIR}/.ssh/authorized_keys" 2>/dev/null; then
        echo "==> anahtar zaten ekli"
    else
        printf '%s\n' "$ANAHTAR" >> "${HOME_DIR}/.ssh/authorized_keys"
        echo "==> anahtar eklendi: ${KEY_USER}"
    fi
    chown "${KEY_USER}:${KEY_USER}" "${HOME_DIR}/.ssh/authorized_keys"
    chmod 600 "${HOME_DIR}/.ssh/authorized_keys"
    echo
    echo "SIMDI BASKA BIR TERMINALDEN giris yapip DOGRULAYIN:"
    echo "  ssh ${KEY_USER}@57.129.128.118"
    echo "Calistigini gordukten SONRA:  sudo bash $0 --disable-password"
    exit 0
    ;;

--disable-password)
    N="$(gercek_anahtar_sayisi)"
    if [[ "$N" -lt 1 ]]; then
        cat >&2 <<'MSG'
REDDEDILDI: sunucuda kalici bir SSH anahtari yok.
Parola girisini simdi kapatmak SSH erisimini tamamen keser.
Once:  sudo bash deploy/scripts/harden-ssh.sh --add-key "<public key>"
sonra giris yaptiginizi DOGRULAYIN, ardindan bu komutu tekrar calistirin.
MSG
        exit 1
    fi
    echo "==> ${N} kalici anahtar bulundu, parola girisi kapatiliyor"
    sed -i 's/^PasswordAuthentication .*/PasswordAuthentication no/' "$CONF"
    grep -q '^PasswordAuthentication' "$CONF" || echo 'PasswordAuthentication no' >> "$CONF"
    dogrula_ve_yukle
    sshd -T | grep -i '^passwordauthentication' | sed 's/^/  /'
    echo
    echo "MEVCUT OTURUMUNUZU KAPATMAYIN. Once yeni bir pencerede giris deneyin."
    echo "Geri almak icin: sed -i 's/^PasswordAuthentication no/PasswordAuthentication yes/' $CONF && systemctl reload ssh"
    exit 0
    ;;

"")
    [[ -f "$SRC" ]] || { echo "HATA: ${SRC} yok." >&2; exit 1; }
    mkdir -p /root/backups
    tar -czf "/root/backups/sshd-config-$(date +%F-%H%M).tar.gz" \
        /etc/ssh/sshd_config /etc/ssh/sshd_config.d 2>/dev/null || true
    echo "==> yedek: /root/backups/sshd-config-*.tar.gz"
    install -m 644 "$SRC" "$CONF"
    echo "==> ${CONF} yazildi"
    dogrula_ve_yukle
    echo
    echo "Parola girisi HALA ACIK (bilerek). Kapatmak icin once --add-key."
    exit 0
    ;;

*)
    echo "Bilinmeyen parametre: $1" >&2
    exit 1
    ;;
esac
