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
exec ssh -o "ProxyCommand=/tmp/cloudflared access ssh --hostname ${FD_SSH_HOST:-ssh.fdartgallery.com}" \
    -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR \
    -o ConnectTimeout=25 "${FD_SSH_USER:-root}@vps" "$@"
