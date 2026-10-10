#!/usr/bin/env python3
"""
Yatay tasmaya SEBEP OLAN ogeyi bulur: viewport'un sag kenarini asan
en DIS ogeleri listeler (cocuklari degil, sorumlu olani).

NEDEN: "sayfa saga kayiyor" sikayetinde suclu tek bir ogedir; gerisi onun
icinde surüklenir. Bu script sagdan tasan ogeleri bulup, ebeveyni de tasiyorsa
cocugu eler — geriye SEBEP kalir.

Kullanim: ./tasma-bul.py <url> [--genislik 390]
"""
import argparse, asyncio, json, requests
from playwright.async_api import async_playwright

CA = "/root/.ccr/ca-bundle.crt"
TARAYICI = "/opt/pw-browsers/chromium"

JS = """(vw) => {
  const out = [];
  const hepsi = document.querySelectorAll('body *');
  for (const e of hepsi) {
    const b = e.getBoundingClientRect();
    if (b.width === 0 || b.height === 0) continue;
    const c = getComputedStyle(e);
    if (c.display === 'none' || c.visibility === 'hidden') continue;
    if (b.right > vw + 1 || b.left < -1) {
      out.push({el:e, sag:Math.round(b.right), sol:Math.round(b.left), w:Math.round(b.width)});
    }
  }
  // ebeveyni de tasiyorsa cocugu ele -> geriye en dis sorumlular kalir
  const kok = out.filter(o => !out.some(p => p.el !== o.el && p.el.contains(o.el)));
  const yol = e => {
    const p = [];
    while (e && e !== document.body && p.length < 4) {
      let s = e.tagName.toLowerCase();
      if (e.id) s += '#' + e.id;
      else if (e.className && typeof e.className === 'string')
        s += '.' + e.className.trim().split(/\\s+/).slice(0,3).join('.');
      p.unshift(s); e = e.parentElement;
    }
    return p.join(' > ');
  };
  return {
    vw, docW: Math.round(document.documentElement.scrollWidth),
    bodyW: Math.round(document.body.scrollWidth),
    tasanToplam: out.length,
    sorumlular: kok.slice(0,12).map(o => ({
      yol: yol(o.el), sol:o.sol, sag:o.sag, genislik:o.w,
      tasma: o.sag - vw,
      stil: (st => ({pos:st.position, w:st.width, mw:st.minWidth, ml:st.marginLeft,
                     mr:st.marginRight, tr:st.transform === 'none' ? '-' : st.transform,
                     ox:st.overflowX}))(getComputedStyle(o.el))
    }))
  };
}"""


async def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("url"); ap.add_argument("--genislik", type=int, default=390)
    a = ap.parse_args()
    oturum = requests.Session(); oturum.verify = CA

    async with async_playwright() as p:
        b = await p.chromium.launch(executable_path=TARAYICI, args=["--no-sandbox", "--disable-gpu"])
        ctx = await b.new_context(viewport={"width": a.genislik, "height": 844},
                                  is_mobile=True, has_touch=True,
                                  device_scale_factor=3,
                                  user_agent=("Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) "
                                              "AppleWebKit/605.1.15 (KHTML, like Gecko) "
                                              "Version/17.0 Mobile/15E148 Safari/604.1"))

        async def yonlendir(route):
            try:
                rq = route.request
                rs = oturum.request(rq.method, rq.url, timeout=40,
                                    headers={k: v for k, v in rq.headers.items()
                                             if k.lower() not in ("host", "accept-encoding")})
                await route.fulfill(status=rs.status_code, body=rs.content,
                                    headers={k: v for k, v in rs.headers.items()
                                             if k.lower() not in ("content-encoding", "content-length",
                                                                  "transfer-encoding",
                                                                  "strict-transport-security")})
            except Exception:
                await route.abort()

        await ctx.route("**/*", yonlendir)
        pg = await ctx.new_page()
        await pg.goto(a.url, wait_until="domcontentloaded", timeout=60000)
        await pg.wait_for_timeout(5000)
        d = await pg.evaluate(JS, a.genislik)
        print(f"\n{a.url}  [{a.genislik}px]")
        print(f"  belge genisligi : {d['docW']}px  (viewport {d['vw']}px)  "
              f"{'>>> TASMA ' + str(d['docW'] - d['vw']) + 'px' if d['docW'] > d['vw'] else 'tasma yok'}")
        print(f"  tasan oge       : {d['tasanToplam']} (sorumlu {len(d['sorumlular'])})\n")
        for s in d["sorumlular"]:
            print(f"  +{s['tasma']:>4}px  sol={s['sol']:>5} sag={s['sag']:>5} w={s['genislik']:>4}  {s['yol']}")
            print(f"            {json.dumps(s['stil'], ensure_ascii=False)}")
        await b.close()

asyncio.run(main())
