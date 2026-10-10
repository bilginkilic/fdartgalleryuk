#!/bin/sh
# VPS'e Cloudflare named tunnel + Access service token uzerinden SSH.
# Ham TCP/22 kapali; tek yol bu.
#
# Gerekli ortam degiskenleri:
#   CLOUDFLARE_CF_Access_Client_Id, CLOUDFLARE_CF_Access_Client_Secret
# Istege bagli:
#   FD_SSH_HOST (varsayilan ssh.fdartgallery.com), FD_SSH_USER (varsayilan root)
#
# Kullanim:  ./ssh-vps.sh 'uptime'
[ -x /tmp/cloudflared ] || { echo "HATA: /tmp/cloudflared yok." >&2; exit 1; }
[ -n "$CLOUDFLARE_CF_Access_Client_Id" ] || { echo "HATA: CLOUDFLARE_CF_Access_Client_Id bos." >&2; exit 1; }

export TUNNEL_SERVICE_TOKEN_ID="$CLOUDFLARE_CF_Access_Client_Id"
export TUNNEL_SERVICE_TOKEN_SECRET="$CLOUDFLARE_CF_Access_Client_Secret"
# Anahtar: taze konteynerde ~/.ssh bos olur; VPS_SSH_PRIVATE_KEY'den kurulur.
ANAHTAR="${FD_SSH_KEY:-$HOME/.ssh/id_vps}"
if [ ! -f "$ANAHTAR" ] && [ -n "$VPS_SSH_PRIVATE_KEY" ]; then
    mkdir -p "$(dirname "$ANAHTAR")"; chmod 700 "$(dirname "$ANAHTAR")"
    printf '%s\n' "$VPS_SSH_PRIVATE_KEY" > "$ANAHTAR"; chmod 600 "$ANAHTAR"
fi
[ -f "$ANAHTAR" ] || { echo "HATA: SSH anahtari yok ($ANAHTAR) ve VPS_SSH_PRIVATE_KEY bos." >&2; exit 1; }

# IdentitiesOnly sart: ajandaki fazla anahtarlar "Too many authentication failures" verir.
exec ssh -o "ProxyCommand=/tmp/cloudflared access ssh --hostname ${FD_SSH_HOST:-ssh.fdartgallery.com}" \
    -i "$ANAHTAR" -o IdentitiesOnly=yes \
    -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR \
    -o ConnectTimeout=25 "${FD_SSH_USER:-root}@vps" "$@"
