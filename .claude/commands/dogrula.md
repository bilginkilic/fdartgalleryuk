---
description: fdartgallery degisikligi sonrasi dogrulama — HTTP kodu yetmez
---

Dogrula: **$ARGUMENTS**

**HTTP 200 dogrulama DEGILDIR** ve **varlik kontrolu gorunurluk kontrolu
degildir** — bir keresinde HTML 303 KB'di, `getElementById` her seyi buluyordu,
butun testler yesildi, sayfa bombostu (`body{display:none}`).

Sunucudan calistir (konteynerden `--resolve` yok sayilir, `HTTPS_PROXY` var):

1. **Canli + dev ayakta mi:** `fdartgallery.com`, `dev.fdartgallery.com` → 200.
   chestnyznak ikilisine de bak — **ayni sunucuda** ve OPcache/FPM paylasiliyor
   (`adr/0003`), bir yanlis ayar dordunu birden dusurur.
2. **Onbellek:** `X-FastCGI-Cache` → `/` **HIT**; `/cart/`, `/my-account/`
   **BYPASS**. Kenar icin `cf-cache-status` → listedeki uc yol **DYNAMIC**,
   `/shop/` HIT.
   > Kenar olcumu `--resolve 127.0.0.1` ile **yapilmaz** — origin'e gider,
   > `cf-cache-status` hic gelmez. Kenar icin sunucudan gercek DNS ile istek at.
3. **Formlar bozuldu mu** (form/Turnstile/tema dokunulduysa): basilan HTML'de
   `id="fluentform_N"` say. Form referansi her zaman kisa kod degildir.
   **Gonderim testi kabuktan YAPILAMAZ** — `cfturnstile_fluent=1`, token yok;
   zinciri kapatmanin tek yolu tarayicidan gercek gonderim.
4. **Giris kirildi mi** (Turnstile/varlik diyeti dokunulduysa): `/my-account/`
   HTML'inde `cf-turnstile` alani duruyor mu? XStore her sayfaya gizli bir
   WooCommerce giris formu basiyor; script cikarilirsa **giris kirilir**.
5. **Site haritasi:** `wp-sitemap.xml` → **200** ve govde `<sitemapindex`.
   Dev'de 404 **normaldir** (staging guard `blog_public`i 0'a zorluyor).
6. **Eklenti sayisi** canli = dev mi (11)? Ayrisma sessiz bir sapma isaretidir.
7. Hiz olctuysen: **isinmis** TTFB ver, ilk istegi degil. OPcache reload'dan
   sonra ilk istek 3-4 sn surer — bu isinma, hata degil.

Atladigin maddeyi **yaz**. Olculmeyen sey dogrulanmis sayilmaz.
