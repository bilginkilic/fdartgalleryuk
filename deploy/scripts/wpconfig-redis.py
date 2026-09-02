#!/usr/bin/env python3
"""wp-config.php'ye Redis nesne onbellegi sabitlerini ekler.

NEDEN AYRI DOSYA: ssh komut dizesine gomulu heredoc tirnaklari sessizce bozar
ve 01.09.2026'da canli + dev wp-config'i AYNI ANDA kirdi (CLAUDE.md 3).
Dosya yerelde yazilir, `base64 -w0` ile aktarilir, sunucuda calistirilir.

Kullanim:
    wpconfig-redis.py <wp-config yolu> <db indeksi> <anahtar oneki> <cache key salt>

Ornek:
    wpconfig-redis.py /var/www/dev.fdartgallery.com/public/wp-config.php \
        4 dev_fdartgallery_com: dev.fdartgallery.com

IZOLASYON KURALI: her sitenin AYRI `WP_REDIS_DATABASE` indeksi ve AYRI
`WP_REDIS_PREFIX` degeri olur. Ayni indeksi paylasan iki site birbirinin
onbellegini okur — bir sitede yapilan degisiklik digerinde gorunur.
Kullanimda olan indeksler `redis-cli info keyspace` ile gorulur.

Cagiran taraf ISLEMDEN ONCE dosyanin kopyasini almali ve `php -l` basarisiz
olursa kopyadan geri yazmalidir.
"""
import sys

if len(sys.argv) != 5:
    print(__doc__)
    sys.exit(2)

yol, db, onek, salt = sys.argv[1], int(sys.argv[2]), sys.argv[3], sys.argv[4]
s = open(yol, encoding="utf-8").read()

if "WP_REDIS_DATABASE" in s:
    print("zaten var")
    sys.exit(0)

ekle = (
    "\n/* Redis nesne onbellegi — site basina IZOLE (ayri db indeksi + onek). */\n"
    "define( 'WP_REDIS_HOST', '127.0.0.1' );\n"
    "define( 'WP_REDIS_PORT', 6379 );\n"
    "define( 'WP_REDIS_DATABASE', %d );\n"
    "define( 'WP_REDIS_PREFIX', '%s' );\n"
    "define( 'WP_REDIS_TIMEOUT', 1 );\n"
    "define( 'WP_REDIS_READ_TIMEOUT', 1 );\n"
    "define( 'WP_CACHE_KEY_SALT', '%s' );\n"
) % (db, onek, salt)

# WordPress'in "buradan sonrasini duzenlemeyin" satirindan ONCE eklenir;
# ABSPATH tanimlandiktan sonra eklenen sabitleri drop-in goremez.
for anahtar in (
    "/* That's all, stop editing!",
    "/* That's it, stop editing!",
    "/** Sets up WordPress vars",
    "require_once ABSPATH",
):
    i = s.find(anahtar)
    if i >= 0:
        open(yol, "w", encoding="utf-8").write(s[:i] + ekle + "\n" + s[i:])
        print("eklendi (%s)" % anahtar.strip()[:32])
        sys.exit(0)

print("ANAHTAR BULUNAMADI — dosya degistirilmedi")
sys.exit(1)
