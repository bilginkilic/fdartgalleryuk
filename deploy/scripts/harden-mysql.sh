#!/usr/bin/env bash
# mysql_secure_installation'in etkilesimsiz ve tekrar calistirilabilir hali.
#
#   - root@localhost icin unix_socket kimlik dogrulamasi + guclu yedek parola
#   - anonim kullanicilari siler
#   - uzaktan root girisini kapatir
#   - test veritabanini ve uzerindeki izinleri kaldirir
#
# Parola /root/.my.cnf icine (mod 600) yazilir; root olarak `mysql` komutu
# parola sormadan calismaya devam eder. Repoya hicbir sey yazilmaz.
#
# Kullanim: sudo bash deploy/scripts/harden-mysql.sh

set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "root olarak calistirin (sudo)." >&2; exit 1; }

echo "==> Mevcut durum"
mysql -N -B -e "SELECT CONCAT(user,'@',host) FROM mysql.global_priv;" | sed 's/^/    /'

# Zaten bir parola tanimliysa onu koru, yoksa uret.
if [[ -f /root/.my.cnf ]] && grep -q '^password=' /root/.my.cnf; then
    ROOT_PW="$(sed -n 's/^password=//p' /root/.my.cnf | head -1)"
    echo "==> Mevcut root parolasi korunuyor"
else
    ROOT_PW="$(openssl rand -base64 30 | tr -d '/+=' | head -c 28)"
    echo "==> Yeni root parolasi uretildi"
fi

echo "==> Anonim kullanicilar, uzaktan root, test veritabani"
mysql <<SQL
-- Anonim kullanicilar
DELETE FROM mysql.global_priv WHERE user = '';
-- Uzaktan root girisi (yalnizca localhost kalir)
DELETE FROM mysql.global_priv WHERE user = 'root' AND host NOT IN ('localhost', '127.0.0.1', '::1');
-- Test veritabani ve uzerindeki acik izinler
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE db IN ('test', 'test\\_%');
FLUSH PRIVILEGES;
SQL

echo "==> root@localhost: unix_socket + yedek parola"
# unix_socket: yalnizca sistem root'u parolasiz baglanabilir.
# Parola ise script/araclar icin yedek yol olarak durur.
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket OR mysql_native_password USING PASSWORD('${ROOT_PW}'); FLUSH PRIVILEGES;"

umask 077
cat > /root/.my.cnf <<EOF
[client]
user=root
password=${ROOT_PW}
EOF
chmod 600 /root/.my.cnf

echo "==> Dogrulama"
mysql -e "SELECT 1;" >/dev/null && echo "    root olarak baglanti: calisiyor"
mysql -N -B -e "SELECT CONCAT(user,'@',host,' -> ',JSON_VALUE(priv,'\$.plugin')) FROM mysql.global_priv WHERE user='root';" | sed 's/^/    /'
ANON="$(mysql -N -B -e "SELECT COUNT(*) FROM mysql.global_priv WHERE user='';")"
echo "    anonim kullanici sayisi: ${ANON}"

echo
echo "Bitti. Parola /root/.my.cnf icinde (mod 600)."
