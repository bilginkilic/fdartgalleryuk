#!/usr/bin/env python3
"""
Sayfanin ekran goruntusunu alir. sayfa-olc.py ile ayni proxy/CA yolunu kullanir
(TLS dogrulamasi kapatilmaz).

Kullanim:
  ./ekran-goruntusu.py <url> [url...] [--genislik 1440] [--dizin /tmp] [--tam-sayfa]
  ./ekran-goruntusu.py <url> --kaydir '[data-widget_type^="etheme_sidebar"]'
"""
import argparse, asyncio, re
import requests
from playwright.async_api import async_playwright

CA = "/root/.ccr/ca-bundle.crt"
TARAYICI = "/opt/pw-browsers/chromium"


def ad_uret(url, genislik):
    s = re.sub(r"^https?://", "", url).rstrip("/")
    s = re.sub(r"[^a-zA-Z0-9]+", "-", s).strip("-")[:60]
    return f"{s}-{genislik}px.png"


async def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("url", nargs="+")
    ap.add_argument("--genislik", type=int, default=1440)
    ap.add_argument("--yukseklik", type=int, default=1100)
    ap.add_argument("--dizin", default=".")
    ap.add_argument("--kaydir", help="once bu secicinin gorunur olacagi yere kaydir")
    ap.add_argument("--tam-sayfa", action="store_true")
    a = ap.parse_args()

    oturum = requests.Session()
    oturum.verify = CA

    async with async_playwright() as p:
        tarayici = await p.chromium.launch(executable_path=TARAYICI,
                                           args=["--no-sandbox", "--disable-gpu"])
        for url in a.url:
            ctx = await tarayici.new_context(
                viewport={"width": a.genislik, "height": a.yukseklik})

            async def yonlendir(route):
                try:
                    rq = route.request
                    rs = oturum.request(rq.method, rq.url, timeout=40,
                                        headers={k: v for k, v in rq.headers.items()
                                                 if k.lower() not in ("host", "accept-encoding")})
                    await route.fulfill(
                        status=rs.status_code, body=rs.content,
                        headers={k: v for k, v in rs.headers.items()
                                 if k.lower() not in ("content-encoding", "content-length",
                                                      "transfer-encoding",
                                                      "strict-transport-security")})
                except Exception:
                    await route.abort()

            await ctx.route("**/*", yonlendir)
            pg = await ctx.new_page()
            yol = f"{a.dizin.rstrip('/')}/{ad_uret(url, a.genislik)}"
            try:
                await pg.goto(url, wait_until="domcontentloaded", timeout=60000)
                await pg.wait_for_timeout(4000)
                if a.kaydir:
                    el = await pg.query_selector(a.kaydir)
                    if el:
                        await el.scroll_into_view_if_needed()
                        await pg.wait_for_timeout(800)
                await pg.screenshot(path=yol, full_page=a.tam_sayfa)
                print(f"{yol}")
            except Exception as e:
                print(f"{url} HATA: {str(e)[:150]}")
            await ctx.close()
        await tarayici.close()

asyncio.run(main())
