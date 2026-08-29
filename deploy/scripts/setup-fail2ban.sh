#!/usr/bin/env bash
# fail2ban kurar. WordPress jail'leri saldirgani Cloudflare EDGE'inde engeller,
# SSH jail'i ise dogrudan guvenlik duvarinda.
#
# Neden Cloudflare'de: site Cloudflare arkasinda oldugu icin paketler
# Cloudflare IP'lerinden geliyor. Saldirganin gercek IP'sini logda goruyoruz
# (real_ip modulu sayesinde) ama onu yerel guvenlik duvarinda engellemek ise
# yaramaz — trafik yine Cloudflare uzerinden gelir. Cloudflare API'si ile
# engellendiginde istek origin'e hic ulasmaz.
#
# Kullanim:
#   CLOUDFLARE_API_TOKEN=... sudo -E bash deploy/scripts/setup-fail2ban.sh
#   sudo bash deploy/scripts/setup-fail2ban.sh --status

set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "root olarak calistirin (sudo -E)." >&2; exit 1; }

if [[ "${1:-}" == "--status" ]]; then
    fail2ban-client status
    for j in $(fail2ban-client status | sed -n 's/.*Jail list:\s*//p' | tr ',' ' '); do
        echo; fail2ban-client status "$j"
    done
    exit 0
fi

TOKEN="${CLOUDFLARE_API_TOKEN:-${cloudflare_api_token:-}}"
TOKEN_FILE=/root/.cloudflare-token

if [[ -n "$TOKEN" ]]; then
    umask 077; printf '%s' "$TOKEN" > "$TOKEN_FILE"; chmod 600 "$TOKEN_FILE"
fi
[[ -s "$TOKEN_FILE" ]] || { echo "Cloudflare token yok. CLOUDFLARE_API_TOKEN verin." >&2; exit 1; }

command -v fail2ban-server >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y fail2ban; }

echo "==> Cloudflare engelleme eylemi"
cat > /etc/fail2ban/action.d/cloudflare-token.conf <<'EOF'
# Saldirgan IP'sini Cloudflare zone'unda bloklar (Bearer token ile).
[Definition]
actionstart =
actionstop =
actioncheck =

actionban = curl -s -X POST \
  "https://api.cloudflare.com/client/v4/zones/<cfzone>/firewall/access_rules/rules" \
  -H "Authorization: Bearer $(cat /root/.cloudflare-token)" \
  -H "Content-Type: application/json" \
  --data '{"mode":"block","configuration":{"target":"ip","value":"<ip>"},"notes":"fail2ban <name>"}' \
  >/dev/null

actionunban = id=$(curl -s -G \
    "https://api.cloudflare.com/client/v4/zones/<cfzone>/firewall/access_rules/rules" \
    -H "Authorization: Bearer $(cat /root/.cloudflare-token)" \
    --data-urlencode "configuration.target=ip" \
    --data-urlencode "configuration.value=<ip>" \
  | python3 -c 'import sys,json; r=json.load(sys.stdin).get("result") or [{}]; print(r[0].get("id",""))'); \
  [ -n "$id" ] && curl -s -X DELETE \
    "https://api.cloudflare.com/client/v4/zones/<cfzone>/firewall/access_rules/rules/$id" \
    -H "Authorization: Bearer $(cat /root/.cloudflare-token)" >/dev/null || true

[Init]
cfzone =
name = default
EOF

echo "==> WordPress filtreleri"
cat > /etc/fail2ban/filter.d/wordpress-login.conf <<'EOF'
# wp-login.php'ye tekrarlayan POST denemeleri
[Definition]
failregex = ^<HOST> .*"POST /wp-login\.php
ignoreregex =
EOF

cat > /etc/fail2ban/filter.d/wordpress-xmlrpc.conf <<'EOF'
# xmlrpc.php denemeleri ERROR log'da aranir: security.conf bu adres icin
# `access_log off` yaptigi icin access log'da hic gorunmuyorlar.
# Ornek satir:
#   2026/08/29 09:43:12 [error] 127054#127054: *4217 access forbidden by rule,
#   client: 207.154.203.32, server: fdartgallery.com, request: "POST /xmlrpc.php HTTP/1.1"
[Definition]
failregex = ^.* \[error\] .*access forbidden by rule, client: <HOST>, .*request: "[A-Z]+ /xmlrpc\.php
ignoreregex =
datepattern = ^%%Y/%%m/%%d %%H:%%M:%%S
EOF

echo "==> Jail'ler"
cat > /etc/fail2ban/jail.d/wordpress.local <<EOF
[DEFAULT]
# Cloudflare edge IP'leri asla banlanmamali (real_ip calismazsa kendimizi keseriz)
ignoreip = 127.0.0.1/8 ::1 57.129.128.118

[sshd]
enabled  = true
maxretry = 5
findtime = 10m
bantime  = 1h

[wordpress-login]
enabled  = true
filter   = wordpress-login
logpath  = /var/log/nginx/*.access.log
maxretry = 5
findtime = 10m
bantime  = 1h
action   = cloudflare-token[cfzone=<CFZONE>, name=wp-login]

[wordpress-xmlrpc]
enabled  = true
filter   = wordpress-xmlrpc
logpath  = /var/log/nginx/*.error.log
maxretry = 3
findtime = 10m
bantime  = 24h
action   = cloudflare-token[cfzone=<CFZONE>, name=xmlrpc]
EOF

# Zone kimligini domainden bul
ZONE_ID="$(curl -fsS -H "Authorization: Bearer $(cat "$TOKEN_FILE")" \
    "https://api.cloudflare.com/client/v4/zones?name=fdartgallery.com" \
    | python3 -c 'import sys,json;r=json.load(sys.stdin)["result"];print(r[0]["id"] if r else "")')"
[[ -n "$ZONE_ID" ]] || { echo "Cloudflare zone bulunamadi." >&2; exit 1; }
sed -i "s|<CFZONE>|${ZONE_ID}|g" /etc/fail2ban/jail.d/wordpress.local

systemctl enable --now fail2ban
systemctl restart fail2ban
sleep 3

echo "==> Durum"
fail2ban-client status
