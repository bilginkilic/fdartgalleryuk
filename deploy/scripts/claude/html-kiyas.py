#!/usr/bin/env python3
"""
Iki kaydedilmis HTML dosyasini kiyaslar: boyut, script/stylesheet sayisi,
inline script, widget basliklari. Bir degisikligin SAYFAYA MALIYETINI
olcmek icin — "once/sonra" ya da "canli/dev".

Kullanim:
  ./html-kiyas.py once.html sonra.html
  ./html-kiyas.py a.html b.html --ad-a CANLI --ad-b DEV
"""
import argparse, re


def olc(yol):
    h = open(yol, encoding="utf-8", errors="replace").read()
    return dict(
        boyut=len(h),
        script=len(re.findall(r"<script[^>]*\ssrc=", h)),
        inline=len(re.findall(r"<script(?![^>]*\ssrc=)", h)),
        style=len(re.findall(r"rel=['\"]stylesheet", h)),
        img=len(re.findall(r"<img[^>]", h)),
        link=len(re.findall(r"<link[^>]", h)),
    )


ap = argparse.ArgumentParser()
ap.add_argument("a"); ap.add_argument("b")
ap.add_argument("--ad-a", default="A"); ap.add_argument("--ad-b", default="B")
ap.add_argument("--ara", action="append", default=[],
                help="ek olarak bu isaretin adedini kiyasla (birden fazla verilebilir)")
n = ap.parse_args()

x, y = olc(n.a), olc(n.b)
print("  %-14s %14s %14s %11s" % ("", n.ad_a, n.ad_b, "fark"))
for k in ("boyut", "script", "inline", "style", "link", "img"):
    print("  %-14s %14s %14s %+11s" % (k, x[k], y[k], y[k] - x[k]))

if n.ara:
    ha = open(n.a, encoding="utf-8", errors="replace").read()
    hb = open(n.b, encoding="utf-8", errors="replace").read()
    for i in n.ara:
        a, b = ha.count(i), hb.count(i)
        print("  %-14s %14s %14s %+11s" % (i[:14], a, b, b - a))
