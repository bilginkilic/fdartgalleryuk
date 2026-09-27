#!/usr/bin/env python3
"""
Bir sayfadaki ogenin GERCEKTEN gorunur olup olmadigini tarayicida olcer.

NEDEN: "HTML'de var" gorunurluk kaniti DEGILDIR. Ogenin height'i 0 olabilir,
display:none olabilir, ya da bir satir konteynerinde yan tarafa sikismis
olabilir. 25.09.2026'da tam bu oldu: filtre cubugu HTML'de vardi ama urun
izgarasini tutan konteyner row yonundeydi, cubuk yanina sikisacakti.
Sadece getBoundingClientRect bunu gosterdi.

Konteynerden dogrudan HTTPS, agent proxy'sinden geciyor ve Chromium proxy
CA'sini okumuyor. Bu yuzden TLS dogrulamasi KAPATILMAZ; istekler Python
tarafindan CA paketiyle DOGRULANARAK cekilip tarayiciya beslenir.

Kullanim:
  ./sayfa-olc.py <url> [url...]
  ./sayfa-olc.py --hedef '.filtre' --referans 'ul.products' <url>

Varsayilan hedef/referans XStore magaza filtresi + urun izgarasidir.
"""
import argparse, asyncio, json, sys
import requests
from playwright.async_api import async_playwright

CA = "/root/.ccr/ca-bundle.crt"
TARAYICI = "/opt/pw-browsers/chromium"

VARSAYILAN_HEDEF = ('[data-widget_type^="etheme_sidebar_horizontal"], '
                    '.etheme-sidebar-horizontal, .sidebar-horizontal')
VARSAYILAN_REFERANS = "li.product, .product.type-product"   # ilk kartin EBEVEYNI olculur

JS = """(secim) => {
  const {hedef, referans, dugmeMetni} = secim;
  const r = el => { if(!el) return null; const b = el.getBoundingClientRect(); const c = getComputedStyle(el);
      return {x:Math.round(b.x), y:Math.round(b.y), w:Math.round(b.width), h:Math.round(b.height),
              disp:c.display, vis:c.visibility, op:c.opacity}; };
  const h = document.querySelector(hedef);
  const ilk = document.querySelector(referans);
  const g = ilk ? ilk.parentElement : null;
  const d = dugmeMetni
    ? [...document.querySelectorAll('button,a,span,div')].find(e => (e.textContent||'').trim() === dugmeMetni)
    : null;
  return {hedef:r(h), referans:r(g), referansSinif:(g?g.className:null),
          dugme:r(d), adet:document.querySelectorAll(referans).length};
}"""


async def olc(tarayici, url, vp, secim, oturum):
    ctx = await tarayici.new_context(viewport=vp)

    async def yonlendir(route):
        try:
            rq = route.request
            basliklar = {k: v for k, v in rq.headers.items()
                         if k.lower() not in ("host", "accept-encoding")}
            rs = oturum.request(rq.method, rq.url, headers=basliklar, timeout=40)
            await route.fulfill(
                status=rs.status_code, body=rs.content,
                headers={k: v for k, v in rs.headers.items()
                         if k.lower() not in ("content-encoding", "content-length",
                                              "transfer-encoding", "strict-transport-security")})
        except Exception:
            await route.abort()

    await ctx.route("**/*", yonlendir)
    pg = await ctx.new_page()
    try:
        await pg.goto(url, wait_until="domcontentloaded", timeout=60000)
        await pg.wait_for_timeout(3500)
        return await pg.evaluate(JS, secim)
    finally:
        await ctx.close()


def yazdir(url, ad, vp, d):
    h, g, k = d["hedef"], d["referans"], d["dugme"]
    print(f"\n{url}  [{ad} {vp['width']}px]  referans adedi={d['adet']}")
    print(f"   hedef    : {h}")
    print(f"   referans : {g}")
    if k:
        print(f"   dugme    : {k}")
    if h and g:
        gorunur = (h["h"] > 0 and h["w"] > 0 and h["disp"] != "none"
                   and h["vis"] != "hidden" and float(h["op"]) > 0)
        ustunde = h["y"] + h["h"] <= g["y"] + 5
        ayni_en = h["w"] >= g["w"] * 0.9
        print(f"   -> gorunur={gorunur}  referansin USTUNDE={ustunde}  "
              f"ayni genislik={ayni_en} ({h['w']}px / {g['w']}px)")
    elif h is None:
        print("   -> HEDEF BULUNAMADI")


async def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("url", nargs="+")
    ap.add_argument("--hedef", default=VARSAYILAN_HEDEF)
    ap.add_argument("--referans", default=VARSAYILAN_REFERANS)
    ap.add_argument("--dugme", default="Filtreler", help="metni birebir eslesen ogeyi de olc")
    ap.add_argument("--sadece-masaustu", action="store_true")
    a = ap.parse_args()

    secim = {"hedef": a.hedef, "referans": a.referans, "dugmeMetni": a.dugme}
    goruntuler = [("masaustu", {"width": 1440, "height": 900})]
    if not a.sadece_masaustu:
        goruntuler.append(("mobil", {"width": 390, "height": 844}))

    oturum = requests.Session()
    oturum.verify = CA

    async with async_playwright() as p:
        tarayici = await p.chromium.launch(executable_path=TARAYICI,
                                           args=["--no-sandbox", "--disable-gpu"])
        for url in a.url:
            for ad, vp in goruntuler:
                try:
                    yazdir(url, ad, vp, await olc(tarayici, url, vp, secim, oturum))
                except Exception as e:
                    print(f"\n{url} [{ad}] HATA: {str(e)[:150]}")
        await tarayici.close()

asyncio.run(main())
