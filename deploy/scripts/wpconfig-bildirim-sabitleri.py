#!/usr/bin/env python3
"""wp-config.php'ye form bildirim sabitlerini ekler.

Tirnak bozulmasin diye ayri dosya olarak calistirilir — ssh komut satirinda
gomulu heredoc kullanilmaz (o denendi ve wp-config'i BOZDU).
"""
import sys

yol = sys.argv[1]
s = open(yol, encoding="utf-8").read()

if "FD_LEAD_NOTIFY_EMAIL" in s:
    print("zaten var")
    sys.exit(0)

ekle = (
    "// Form bildirimleri — ekip kutusu. admin_email BILEREK farkli kalir\n"
    "// (kullanici karari 01.09.2026: yonetici adresi kisisel gmail).\n"
    "define( 'FD_LEAD_NOTIFY_EMAIL', 'info@chestnyznak.com.tr' );\n"
    "define( 'FD_LEAD_NOTIFY_CC',    'oguzk@chestnyznak.com.tr' );\n"
)

anahtar = "define( 'FD_LEAD_RELAY_URL'"
i = s.find(anahtar)
if i < 0:
    print("ANAHTAR BULUNAMADI")
    sys.exit(1)

open(yol, "w", encoding="utf-8").write(s[:i] + ekle + s[i:])
print("eklendi")
