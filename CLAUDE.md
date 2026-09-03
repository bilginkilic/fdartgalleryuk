# CLAUDE.md — fdartgallery.com

Bu depo **fdartgallery.com**'un calisma hafizasi. Ayrica **butun sitelerin
`deploy/` araclarini** barindirir (7. bolum) — chestnyznak'in scriptleri de burada.

> **GENEL ALTYAPI BURADA DEGIL.** Sunucu, SSH/tunel, DNS, kimlik bilgileri, site
> yerlesim standardi ve butun sitelerde gecerli tuzaklar
> **`bilginkilic/ovgcloudukmultisite` → `CLAUDE.md`** icinde. **Once orayi oku.**
> chestnyznak'a ozel: **`bilginkilic/chestnyznakuk` → `CLAUDE.md`**.

**Sunucuya baglanma** (tarifin tamami genel dosyada):

```sh
curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 \
     -o /tmp/cloudflared && chmod +x /tmp/cloudflared
export TUNNEL_SERVICE_TOKEN_ID="$CLOUDFLARE_CF_Access_Client_Id"
export TUNNEL_SERVICE_TOKEN_SECRET="$CLOUDFLARE_CF_Access_Client_Secret"
ssh -o "ProxyCommand=/tmp/cloudflared access ssh --hostname ssh.fdartgallery.com" \
    -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@vps
```

Ham TCP/22 **kapali**, makinede hazir anahtar **yok** — yoksa uret, `.pub`'i dala
push et, dur ve haber ver. Kullanici daima `root`. Site kullanicisi
`web_fdartgallery_com` (dev: `web_dev_fdartgallery_com`) — `wp` komutlari
`sudo -u` ile.

---

## 0. SU AN NE KALDI

Site yayinda; kurulum, performans, guvenlik, yedekleme, site haritasi, formlar,
e-posta ve onbellek bitti. **Sunucu tarafinda yapilacak is kalmadi.**

Kalan tek performans maddesi **temada** ve **kod isi degil, tasarim isi**:

**Mobil LCP 10,9 sn (puan 37-40).** Masaustu iyi ve daha da iyilesti
(**puan 85**, LCP 1,99 sn). Mobildeki sebep tarayicidaki is: Style & Layout
4,3 sn, jQuery 4,8 sn, sayfa 3.159 KB. Tam dokum ve denenip **ise yaramayan**
mudahaleler 3. bolumde. Kalan secenekler, olculen kazanc ve riskiyle —
**hepsi kullanici karari**:

1. **Slayt 3 → 1 (~330 KB).** Ana sayfanin en agir uc dosyasi slayt gorselleri
   (`5.jpg.webp` 287 KB, `6` 197 KB, `7` 132 KB); yalnizca birincisi goruluyor,
   digerleri yine de iniyor. Icerik karari.
2. **Turnstile ~1 MB.** Dogru cozum: gizli giris formunun widget'ini sayfa
   acilisinda degil, **giris penceresi acilinca** render etmek. Ozel is; yanlis
   yapilirsa **giris kirilir** (3. bolumdeki tuzaklara bakin).
3. **Ana sayfadaki widget sayisi** (33 container / 60 widget, 2 urun listesi).
   Asil `Style & Layout` maliyeti burada. Tasarim karari.
- **Blogun en eski 4 yazisi** hala Ingilizce demo slug'inda; 301 ister, tablo
  hazir (`fd-eski-adresler.php`). Ayrinti `deploy/blog/BACKLOG.md`.

Altyapi bekleyenleri (kalan 4 sitenin arsivi, SSH parola girisi,
`VPS_SSH_PRIVATE_KEY`, PTR) **genel dosyanin 0. bolumunde**.

---

## 1. Site

| | |
|---|---|
| Canli | `fdartgallery.com` → `/var/www/fdartgallery.com/public` |
| Dev | `dev.fdartgallery.com` → `/var/www/dev.fdartgallery.com/public` |
| DB / onek | `wp_fdartgallery_com` / **`fdArt0Og_`** (`wp_` DEGIL) |
| Tema | XStore (WooCommerce) + Elementor |
| Redis | canli db=3, dev db=4 |
| DNS | `fdartgallery.com`, `www`, `dev` — **turuncu**; SSL **Full (strict)** |
| CF zone | `fa3f51825d634f23741ff5fbf2ae8b1a` |

Vhost'larda `cloudflare-only.conf` **VAR** (chestnyznak'ta yoktur).
Mail kayitlari (Brevo DKIM, DMARC, SPF, Email Routing MX) **degistirilmez**.

---

## 2. Yapilmis isler

| Alan | Durum |
|---|---|
| Hiz | canli + dev: 47 → 26 script, 32 → 26 stylesheet, react+12 `js/dist` dustu, `.ttf` gzip (3) |
| Kenar onbellegi | **Cloudflare APO acik** 03.09.2026; otomatik purge olculdu ve calisiyor (3) |
| Reklam/bulten trafigi | `?utm_*`/`gclid`/`fbclid` onbellekten okuyor; TTFB ~2,3 sn → 15-25 ms (3) |
| Eklentiler | **17 → 12**, canli = dev. Kapatilan 4: `mailchimp-for-woocommerce`, `fluentforms-pdf`, `image-optimization`, `updraftplus`. Kaldirilan 2: **CF7** (formlar FluentForm'da), **`mc4wp`** (hicbir sayfada basilmiyordu). Eklenen 1: `cloudflare` |
| PHP / OPcache | **sunucu geneli** ayar 03.09.2026: sepet 2,9 sn → **0,95 sn**, hesabim 2,7 → 0,87, odeme 1,8 → 0,42 (3) |
| Site haritasi | `wp-sitemap*.xml` 404 → **200**; indeks + 8 alt harita, 329 adres (4) |
| Formlar / e-posta | FluentForm besleme onarimi, butun ekip bildirimleri tek adrese, altbilgi bulten formu (5) |
| Turnstile | giris/kayit/form/WooCommerce |
| Urun gorselleri | `woocommerce_thumbnail` 1000 → **600**: gorsel basina 112 → 45 KB, `/shop/` ~2.128 → **662 KB** (3) |
| Cerez bildirimi | onbellekli sayfada kapanmiyordu, duzeltildi; onay omru 3 gun → 365 (3) |
| Site preloader | tema ayarindan **kapatildi** (3) |
| Odeme sayfasi | Elementor widget'indaki sabit Ingilizce etiketler Turkcelestirildi, 61 alan (3) |
| WebP | 6437 dosya (02.09.2026; `/etc/cron.d/webp-fdartgallery_com` gece artirir); orijinaller korunur |

**mu-plugin'ler** — `deploy/wordpress/fdart-mu-plugins/` (yalnizca bu site):

| Dosya | Ne yapar |
|---|---|
| `fd-asset-diyet.php` | sayfada karsiligi olmayan CSS/JS'i kuyruktan cikarir (3) |
| `fd-font-hizlandirma.php` | googleapis isteklerini tek istege indirir, preconnect (3) |
| `fd-eski-adresler.php` | slug degisimi icin 301 tablosu (4) |
| `fd-cerez-bildirimi.php` | cerez bildirimini onbellekli sayfada da kapatir (3) |
| `fd-urun-gorsel-srcset.php` | srcset'ten artik `..._preview` (1000x1000) adayini eler (3) |
| `fd-site-haritasi-durum.php` | `wp-sitemap*.xml` 200 dondurur (4) |

Butun sitelerde ortak olanlar (`mu-plugins/`, `staging-mu-plugins/`) genel
dosyanin 4. bolumunde; `lead-mu-plugins/` chestnyznak'indir.

---

## 3. Hiz ve onbellek

**Once olcun, sonra dokunun.** Sunucu darbogaz degil; darbogaz **istek sayisi**
ve **tema/Elementor yuku**.

### Nerede duruyoruz (03.09.2026 olcumu)

Cloudflare Observatory, Tel Aviv (`me-west1`) — Turkiye'ye en yakin test bolgesi.
Temiz A/B: **canli APO'lu**, **dev APO'suz**, ayni sunucu ve ayni sayfa.

| | canli (APO) | dev (APO yok) |
|---|---|---|
| **TTFB masaustu / mobil** | **53 / 52 ms** | **96 / 98 ms** |
| LCP masaustu | 2,25 sn | 2,16 sn |
| LCP mobil | 10,5 sn | 12,2 sn |
| Puan masaustu / mobil | 85 / 41 | 83 / 48 |

**APO'nun kazanci TTFB'de ve nettir: ~97 → ~52 ms.** LCP ve puanlar iki tarafta
da ayni bantta ve dalgali — onlar APO'nun isi degil, 0. bolumdeki tema yuku.

Origin hala saglam: nginx `fastcgi_cache` TTFB **17-25 ms**, kenar MISS olsa bile
arkada HIT veriyor — hicbir istek PHP'ye dusmuyor.

> **"Yavaslik WordPress'in hantalligi" TAM DOGRU DEGIL.** HTML 52 ms'de geliyor;
> cekirdek ve sunucu isini yapiyor. Mobildeki 10 sn **tarayicida render**:
> XStore + Elementor'un 46 script / 29 stylesheet'i. Sunucuyu daha da
> hizlandirmak bu sayiyi **degistirmez**. Istisna sepet/odeme: orada gercekten
> PHP yavas (0. bolum).

### OPcache — sepet/odeme yavasliginin sebebiydi (03.09.2026)

`/cart/` 2,9 sn suruyordu. Profil (gecici mu-plugin, `?fdprofil=`) sucluyu net
gosterdi: **veritabani %1** (21 sorgu, 14 ms). Zaman PHP'nin her istekte
**3.240 dosyayi yeniden derlemesinde** gidiyordu.

```
opcache: bellek 128/128 MB DOLU · isabet %22 · kacirma 106.213
TOPLAM 2.761–3.330 ms · bellek tepe 186 MB
```

OPcache sunucuda **hic yapilandirilmamisti** — tamamen PHP varsayilani (128 MB,
10.000 dosya, 8 MB interned). Kutuda **4 WordPress sitesi** var, her biri ~3.240
dosya yukluyor.

> **TUZAK: OPcache dolunca sessizce devre disi kalir.** Yeni script kabul etmez
> ve **tahliye de yapmaz** — hangi sitenin dosyalari once girdiyse onlar kalir.
> Kalanlar her istekte yeniden derlenir ve **her isci onlari kendi belleginde
> tutar**. Canli ile dev arasindaki fark tam olarak buydu: ayni kod, ayni 12
> eklenti, ayni veri; canli 2,9 sn / tepe **186 MB**, dev 1,2 sn / tepe **38 MB**.
> `memory_get_peak_usage()` arasindaki bu ucurum OPcache doldugunun imzasidir.

Cozum `/etc/php/8.5/fpm/conf.d/99-opcache-tuning.ini` (**sunucu geneli** —
`opcache.memory_consumption` `PHP_INI_SYSTEM`, havuz basina ayarlanamaz; tek
php-fpm ana sureci var, dolayisiyla dort siteyi birden etkiler; kullanici onayi
alindi):

```ini
opcache.memory_consumption = 768   ; once 512 denendi, 509/512 ile doldu
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 50000
opcache.validate_timestamps = 1    ; deploy sonrasi elle reload gerekmesin
opcache.revalidate_freq = 2
```

Sonuc — ayni yontemle olculdu:

| | once | sonra |
|---|---|---|
| `/cart/` | 2,9–3,2 sn | **0,94–0,97 sn** |
| `/my-account/` | 2,6–2,9 sn | **0,74–0,95 sn** |
| `/checkout/` | 1,7–2,0 sn | **0,40–0,48 sn** |
| tepe bellek | 186 MB | **34 MB** |
| OPcache isabet | %22 | **%89** (508/768 MB, 15.8k script) |

Ana sayfa 17–21 ms, chestnyznak ikilisi 17–24 ms, dort site de 200. Kenar
davranisi degismedi (`/` HIT, `/cart/` ve `/my-account/` DYNAMIC).

Kalan ~0,95 sn hala PHP (DB %1) — sonraki kazanc 3.240 dosyayi azaltmaktan,
yani tema yukunden gecer.

> `/checkout/` bos sepetle **302 → `/cart/`** doner; bu WooCommerce'in normal
> davranisi, hata degil.

### LCP on yukleme DENENDI, ISE YARAMADI (03.09.2026)

Hero gorseli (`5.jpg.webp`, 287 KB) Elementor tarafindan satir ici
`style="background-image: url(...)"` ile HTML'in **74.856. karakterinde**
basiliyor; `</head>` 25.246'da bitiyor. CSS arka planlari on tarayiciya
gorunmez, yani tarayici gorseli cok gec kesfediyor. Mantikli bir hedefti.

`fd-lcp-onyukleme.php` yazildi (on sayfanin `_elementor_data`'sindan ilk
`background_image` okunur, transient'te tutulur, `<head>`'e
`<link rel=preload as=image fetchpriority=high>` basar). Dev'de dogrulandi:
etiket 737. karakterde, adres 200.

**Olcum (Observatory / me-west1, mobil):**

| | once | preload ile |
|---|---|---|
| LCP | 12.211 ms | 12.309 ms |
| Puan | 48 | 39 |

**Fark yok.** Cunku darbogaz gorsel indirmek degil, JS calistirmak (0. bolum).
Olculebilir kazanc olmadigi ve 287 KB'i yuksek oncelikle cekmenin bedeli
oldugu icin **geri alindi**; dosya depoda da tutulmadi.

> **Ders: "preload 0" bir belirti, tanı degil.** Once LCP elemaninin ne oldugunu
> ve neyin bekledigini olcun. Lighthouse `mainthread-work-breakdown` ve
> `bootup-time` denetimleri bunu dogrudan soyluyor; rapor JSON'una
> `speed_api/.../tests/<id>` cevabindaki `jsonReportUrl` ile ulasilir.

### Mobil LCP — nereye gidiyor (03.09.2026 tam olcum)

Masaustu **sorun degil** (puan 81, LCP 2,3 sn). Sorun yalnizca mobilde:
puan 39, FCP 3,0 sn, **LCP 10,8 sn**, TBT 1,25 sn, TTI 13,3 sn.
TTFB 58 ms — yani bekleme degil, **tarayicida is**.

Ana is dagilimi (Lighthouse, mobil emulasyon):

| | ms |
|---|---|
| Style & Layout | **4.333** |
| Other | 3.251 |
| Script Evaluation | 1.766 |
| jQuery (bootup toplami) | **4.819** |

Sayfa **3.358 KB**, 111 istek:

| tip | istek | KB |
|---|---|---|
| Gorsel | 19 | **1.332** |
| XHR (Turnstile) | 3 | **769** |
| Script | 39 | 397 |
| Document (Turnstile) | 4 | 314 |
| Font | 11 | 251 |
| Stylesheet | 28 | 199 |

Israf kalemleri:

- **Turnstile ~1 MB / 15+ istek.** Sayfada IKI widget var: XStore'un her sayfaya
  bastigi **gizli WooCommerce giris formu** (`-woo-login-`) ve altbilgi bulteni
  (`-fluent-`).
- `elementor-all-widgets.min.js` 135 KB, **%82 kullanilmiyor**.
- CSS: `woocommerce-all.min.css` **%99**, `elementor-all-widgets.min.css` %92,
  `xstore.min.css` %90 kullanilmiyor.
- Slayt 3 gorsel = **616 KB**, yalnizca birincisi gorunuyor.
- Urun gorselleri **954x1000 inip 299x314 gosteriliyor** — `sizes` niteligi
  `(max-width: 954px) 100vw, 954px`, yani tarayiciya "ekrani kaplayacak" deniyor.
  WooCommerce kucuk resmi de **1000x1000**. Lighthouse tahmini kazanc 589 KB.

**Denenen ve OLCULEN mudahaleler — ucu de yetmedi:**

| deneme | mobil LCP | sonuc |
|---|---|---|
| hero gorseline `preload` | 12.211 → 12.309 ms | fark yok, geri alindi |
| Turnstile widget'i 2 → 1 | 12.309 → 11.767 ms | −0,5 sn ama **girisin korumasi da gidiyor**, kabul edilemez |
| `cfturnstile_appearance = interaction-only` | 12.233 ms | LCP'ye etkisi yok (TBT 1.220 → 750) |

> **TUZAK: `cfturnstile_woo_login = 0` giris sayfasini da korumasiz birakir.**
> Dev'de olculdu: `/my-account/` uzerindeki `cf-turnstile-woo-` alani da kayboldu,
> geriye yalnizca altbilgi formununki kaldi. Ayar sayfa bazli degil.

> **TUZAK: `appearance` yalnizca GORUNURLUGU degistirir, calismayi ertelemez.**
> `interaction-only` ile de Turnstile 20 istek / 1.071 KB cekmeye devam etti.

Dev'de yapilan butun deneme ayarlari geri alindi; **canliya hic dokunulmadi**
(`cfturnstile_woo_login = 1`, `appearance = always`).

Kalan gercek secenekler tema/icerik kararidir — 0. bolume bakin.

### Urun gorseli boyutu (03.09.2026)

`woocommerce_thumbnail` **1000x1000** uretiliyordu. Gercekte gosterilen olcu:
**mobilde 299x314, masaustunde 165x165**. Urun detay sayfasinin kendi gorseli
(`woocommerce_single`) bile yalnizca **600** genisliginde — yani izgara kucuk
resmi detay gorselinden buyuktu.

> **`sizes` niteligini duzeltmek TEK BASINA ISE YARAMAZ.** Once oyle planlandi,
> sonra `srcset`'e bakildi: yalnizca **iki aday** var — `954w` ve `1w`. Cunku
> `wp_calculate_image_srcset()` yalnizca **ayni en-boy oranindaki** boyutlari
> aday yapar; `600x841`, `768x1076`, `300x300` hepsi farkli oranda. Kirpilmis
> `woocommerce_thumbnail`'in kucuk kardesi yok. Tarayiciya daha kucugunu sec
> desen de secebilecegi bir sey yok. Cozum boyutun kendisini kucultmek.

Yeni deger **600** (`woocommerce_thumbnail_image_width`): mobil 299 CSS px x DPR 2
= 598, masaustunde zaten fazlasiyla yeterli.

> **TUZAK: `wc_get_image_size()` NESNE ONBELLEGINDEN okur.** Secenek 600 yapildi,
> fonksiyon hala `1000x1000` donuyordu ve ortada filtre yoktu. Redis kalici
> nesne onbellegi eski degeri tutuyor. **Boyutu degistirdikten sonra
> `wp cache flush` sart**, yoksa yeniden uretim eski olcuyle calisir (ilk
> denemede tam bunu yapti, 3 dosya bosuna uretildi).

Sira: secenek → `wp cache flush` → `wp media regenerate --image_size=woocommerce_thumbnail`
→ `convert-webp.sh` (yeni dosyalarin `.webp` kopyasi yoksa `fd-webp-rewrite`
JPEG'e duser, kazanc yanar) → nginx + kenar onbellegi temizligi.

Yedek (degisiklik oncesi secenekler): `/var/backups/claude-2026-09-03-gorsel/`.

> **IKINCI TUZAK: boyut kuculdu ama tarayici hala 1000x1000 indiriyordu.**
> `srcset`'te eski temadan kalan bir **artik** aday duruyordu:
> `woocommerce_thumbnail_preview` (1000x1000). Bu boyut artik ne temada ne
> eklentide kayitli — yalnizca ek verisinde duruyor ve ayni en-boy oraninda
> oldugu icin `wp_calculate_image_srcset()` onu da aday yapiyor. `.webp`
> kopyasi da olmadigi icin **en agir dosya (153 KB)** seciliyordu.
> `fd-urun-gorsel-srcset.php` bu adayi listeden cikariyor (dosyalar diskte kalir).
>
> `sizes` duzeltmesi burada da tek basina yetmezdi: tarayici gerekenden
> **kucuk** olani secmez — slot 299 CSS px, DPR 2 ile 598 px gerekiyor; 618 px
> gerektiginde 600w'yi atlayip 1000w'ye cikardi.

**Sonuc (canli, 03.09.2026):**

| | once | sonra |
|---|---|---|
| urun kucuk resmi | 954x1000, **112 KB** | 600x600, **45 KB** |
| `/shop/` urun gorselleri (19 adet) | ~2.128 KB | **662 KB** |
| ana sayfa toplam | 3.358 KB | **3.159 KB** |
| masaustu puan / LCP | 81 / 2.306 ms | **85 / 1.992 ms** |
| mobil puan / LCP | 39 / 10.800 ms | 37 / 10.888 ms (degismedi) |

Mobil degismedi cunku orada darbogaz JS (0. bolum). Kazanc **indirilen veride**
ve asil `/shop/` ve kategori sayfalarinda: ~1,4 MB.

Kirpma artik gercekten 1:1 — eskiden ayar 1:1 diyordu ama genislik kaynak
gorsellerden buyuk oldugu icin hic uygulanamiyor, boy 1000'e sabitleniyordu
(954x1000, 927x1000, 879x1000...). Izgara artik duzgun kare. **Bu gorunur bir
degisiklik**, kullanici onayiyla yapildi.

Yeniden uretimde 670 ekin 652'si yenilendi; **basarisiz 18'in tamami SVG**
(ImageMagick rasterleyemiyor, raster kucuk resim de gerekmez).

Eski `954x1000` kucuk resimlerin `.jpeg` dosyalari `wp media regenerate`
tarafindan silindi ama **`.jpeg.webp` kardeslerini WordPress bilmiyor**, diskte
oksuz kaldilar (~70 MB). Zararsiz — hicbir yerde referans verilmiyor.

### Site preloader kapatildi (03.09.2026)

`site_preloader` tema ayari **1 → 0** (`set_theme_mod`, `etheme_get_option`
`get_theme_mod`'u okuyor). Body sinifi `et-preloader-on` → `et-preloader-off`,
overlay HTML'i hic basilmiyor. Tema JS'i overlay'i **500 ms sonra** kaldiriyordu;
o sure artik yok. Geri almak: `set_theme_mod('site_preloader', 1)`.
Yedek: `/var/backups/claude-2026-09-03-gorsel/preloader-oncesi.txt`.

### Cerez bildirimi onbellekte takiliyordu (03.09.2026)

Kullanici bildirdi: "Tamam. Hazirim" tiklaniyor, bildirim her sayfada geri
geliyor.

> **TUZAK: XStore cerez bildirimini SUNUCUDA karar veriyor** —
> `xstore/framework/features/gdpr.php:39`, `$_COOKIE['etheme_cookies']` kontrolu.
> Sayfa hem nginx `fastcgi_cache` hem APO tarafindan onbelleklendigi icin
> onbellege giren kopya **her zaman** bildirimi iceriyor. Temanin JS'i yalnizca
> TIKLAMADA kaldiriyor, acilista cerezi **kontrol etmiyor**. Ikinci sebep: onay
> cerezinin omru `et_cookies_notice_cache` = **3 gun**.

`fd-cerez-bildirimi.php`: karar tarayiciya tasindi — `wp_head`'in en basinda
(552. karakter, `</head>` 25.432'de) stil + kucuk script, cerez varsa `<html>`'e
isaret konuyor ve bildirim hic boyanmiyor. Cerez omru filtreyle **365 gun**;
tema ayarina dokunulmadi.

> iOS Safari, JS ile yazilan cerezleri **7 gunle** sinirlar (ITP). Sunucudan
> `Set-Cookie` ile yazmak bunu asardi ama sayfayi onbelleklenemez yapardi —
> takas iyi degil.


### Odeme sayfasi Turkcelestirildi (03.09.2026)

Kullanici bildirdi: odeme adiminda "First Name", "Last Name", "Company Name",
"Have a coupon? Click here to enter" Ingilizce cikiyordu — sayfanin geri kalani
Turkce oldugu halde.

> **TUZAK: WooCommerce cevirisi CALISIYORDU.** `get_locale()` `tr_TR`,
> `woocommerce-tr_TR.mo` 1,1 MB, `__('First name','woocommerce')` → `Ad`,
> `WC()->countries->get_address_fields('TR','billing_')` → `Ad`, `Soyad`,
> `Firma adı`. Yani ceviriyi aramak yanlis iz. Ipucu **buyuk harflerdeydi**:
> cekirdek "First name" yazar, ekranda "First **N**ame" goruluyordu — **baska
> bir dize**.

**Kok sebep:** odeme sayfasi (**id 448**) Elementor ile kurulmus ve XStore'un
`woocommerce-checkout-etheme_page` widget'i kullaniliyor. Etiketler widget'in
kendi ayarlarinda, `_elementor_data` icinde **sabit Ingilizce** duruyordu:

```json
"billing_details_form_fields":[
  {"field_key":"billing_first_name","field_label":"First Name",
   "label":"First Name","placeholder":"First Name","_id":"76f4b3a"}, ...]
```

Iki tekrarlayici (`billing_details_form_fields` 10 alan,
`shipping_details_form_fields` 8 alan) + yedi bolum basligi.

**Cozum** — `deploy/scripts` disinda tek seferlik script; etiketler
**WooCommerce'in kendi tr_TR cevirisinden** okundu (sayfanin geri kalaniyla ayni
sozcukler olsun diye), 61 alan degistirildi. `--user=fdsanat` ile calistirildi
(Elementor yazma kurali), JSON geri okunup dogrulandi, Elementor onbellegi
temizlendi.

**Dogrulama gercek sepetle yapildi:** `curl` ile cerez kavanozu acilip
`?add-to-cart=<id>` cagrildi, sonra `/checkout/` ayni cerezle cekildi — bos
sepette sayfa `/cart/`'a 302 dondugu icin baska turlu goruntulenemiyor.
Sonuc: **Ingilizce kalinti 0**, `billing_first_name` / `shipping_first_name` /
`place_order` / `iyzico` alanlari yerinde, durum 200.

Yedek: `/var/backups/claude-2026-09-03-odeme/` (canli + dev, `_elementor_data`
ve `post_content`).

> Sayfanin `post_content`'inde Elementor'un eski **render kopyasi** duruyor:
> Ingilizce etiketler ve gecmis bir denemeden kalan `value="bilgin"` gibi
> degerler var. On yuz bunu KULLANMIYOR (`_elementor_data`'dan render ediliyor);
> bilerek dokunulmadi — silinirse Elementor bir gun render edemezse sayfa bos
> kalir.

### Varlik diyeti

`fdart-mu-plugins/fd-asset-diyet.php` — sayfada karsiligi olmayan dosyalari
kuyruktan cikarir. Kosul saglanmazsa dosya **aynen kalir** (hata durumunda eksik
degil, fazla yuklenir).

> chestnyznak'in AYRI dosyasi var (`lead-mu-plugins/fd-asset-diyet.php`) —
> **karistirmayin**.

> **TUZAK: tek bagimlilik 15 dosya getiriyordu.** `cfturnstile-woo-js`'in
> bagimliliginda `wp-data` var → react + 12 `js/dist`. **"Scripti cikaralim"
> YANLISTI:** XStore her sayfaya gizli bir WooCommerce giris formu basiyor,
> script cikarilsa Turnstile dogrulanmaz ve **giris kirilirdi**. Dogru mudahale:
> script KALIR, sepet/odeme disinda handle `wp-data`'siz **on kayit** edilir
> (`WP_Dependencies::add()` mevcut handle'i EZMEZ).

> **Bir handle'i cikarmadan once `src`'sini ve KANCASININ NE ZAMAN calistigini
> okuyun.** Turnstile `wp_enqueue_scripts`'te degil kendi eyleminde enqueue
> ediyor — oradaki dequeue kurali ona hic degmedi.

**Fontlar** — `fd-font-hizlandirma.php`: googleapis stylesheet'leri **tek**
istege birlesir, agirlik 18 → 9, `fonts.googleapis.com` + `fonts.gstatic.com`
**preconnect** (gstatic'te `crossorigin` SART). Aile ELENMEDI — kit tipografisi
kullaniliyor, atmak basliklari sistem fontuna dusururdu; bu bir **tasarim** karari.

`deploy/nginx/snippets/font-gzip.conf` — yalnizca `.ttf|.otf|.eot` location'inda gzip.

### Izleme parametreli istekler (`?utm_*`)

**Okuma ile yazma AYRILIR:** `fastcgi_cache_bypass` OKUMAYI, `fastcgi_no_cache`
YAZMAYI kontrol eder. Izleme-only isteklerde okuma **serbest** (anahtar sorgu
dizesiz), yazma **yasak**. Kapsam yalnizca fdartgallery (`$wpc_qs_norm`).
`ref`, `source`, `campaign` BILEREK izleme sayilmaz — ortaklik kodu olabilirler.

> **"utm'i anahtardan at, hepsi ayni girdiyi paylassin" YANLIS OLURDU:** sorgu
> dizesi HTML'e SIZIYOR (ana sayfada 19 `add-to-cart` baglantisi ve
> `_wp_http_referer` gecerli URL uzerine kurulu). Sonraki ziyaretci
> **baskasinin utm'ini** gorurdu.

> **`$uri` KULLANILAMAZ** — `try_files ... /index.php?$args` sonrasi `$uri`
> `/index.php` olur, butun sayfalar tek girdide toplanir. Yol `$request_uri`'den
> regex ile kesilir (`$wpc_request_path`).

### Cloudflare APO (acik, 03.09.2026)

HTML **Cloudflare kenarinda** onbelleklenir; istek sunucuya gelmez. Kapsam
`["fdartgallery.com","www.fdartgallery.com"]`, `wp_plugin: true`. Origin'deki
`fastcgi_cache` yerine GECMEZ, onunun katmanidir.

**Kenarda ASLA onbelleklenmeyecek yollar** — `http_request_cache_settings`
kural seti `3d4413e240434bedae771766a8502d8e`: `/cart`, `/checkout`,
`/my-account`, `/sepet`, `/odeme`, `/hesabim`, `/siparis`, `/wc-api`. Uclu de
`DYNAMIC` dogrulandi. `wordpress_logged_in` / `woocommerce_items_in_cart` cerezi
varsa APO kendisi de atlar. `dev.fdartgallery.com` kapsam disi ve **oyle kalmali**.

**Otomatik purge** — `cloudflare` eklentisi 4.14.4, kanca
`transition_post_status`. Bir sayfa kaydi 26, bir urun kaydi 34 URL purge eder:
kendi adresi, `/`, `/blog/`, beslemeler, yazar arsivi; urunde ayrica `/shop/` ve
urun kategorisi. (Yarisi gereksiz `http://` kopyasi — zararsiz.)

> **TUZAK: APO'yu API'den acinca eklenti purge ETMEZ, hata da VERMEZ.**
> `Hooks::purgeCacheByRelevantURLs()` iki kapidan gecer, ikisi de sessizce
> `return` eder:
> 1. `isAutomaticPlatformOptimizationEnabled()` → WP secenegi
>    `automatic_platform_optimization` (`['value' => 'on']`).
> 2. `getDomainList()` → secenek `cloudflare_cached_domain_name`; bos ise
>    144-146. satirda `return`. **Asil sebep buydu.**
>
> Ikisi de canliya yazildi. **`dev`'de ikisi de YOK ve olmamali** — dev'in
> canlinin zone'unu purge etmesi istenmez. (Dev'de dogrulanamaz da: dev bir CF
> zone'u degil, `getZoneTag()` bos doner. Bu is bilerek canlida yapildi.)

Uctan uca olculdu: `/` `/blog/` `/ozel-siparis/` HIT'e isitildi, 20 sn sonra hala
HIT; sayfa #2 kaydedildi; **8 sn icinde ucu de MISS**, listede olmayan `/shop/`
**HIT kaldi**.

> Her kayitta `GET /pagerules?status=active` **400** doner (token'da Page Rules
> okuma izni yok). **Zararsiz:** donen `false` yalnizca besleme adreslerini
> eler, APO acik oldugu icin ikinci filtre zaten atlanir.

Elle purge: zone purge API'sinde `purge_everything`.

> **Kenar olcumu konteynerden veya `--resolve 127.0.0.1` ile YAPILMAZ.**
> `--resolve` origin'e gider, `cf-cache-status` hic gelmez. Kenari olcmek icin
> sunucudan gercek DNS ile istek at; **ziyaretci gibi** olcmek icin Observatory
> (`speed_api`) kullan — sunucu origin'in yanindadir, oradan APO detour gibi gorunur.

### Yapilmayanlar ve nedeni

- **`sourcebuster-js` + `wc-order-attribution` DOKUNULMADI** — ilk temasi giris
  sayfasinda kaydeder; yalnizca sepette yuklemek veriyi eksiltmez **yanlis**
  yapar. Kaldirilacaksa WooCommerce ayarindan ozellik komple kapatilir.
- **`akismet-frontend.js` DENENDI, VAZGECILDI** — Akismet altbilgideki
  FluentForm bultene bal kupu alani basiyor, o alani bu JS dolduruyor.
- **Elementor deneysel ozellikleri** acilmadi (agir temayla render riski).
- **TTF → WOFF2** yapilmadi (gzip %46 verdi; `@font-face` uretimi temanin isi).

---

## 4. Site haritasi ve adresler

`wp-sitemap.xml` **gecerli XML donuyordu ama HTTP durumu 404 idi**; `robots.txt`
o adresi ilan ettigi icin arama motorlari haritayi reddeder.

**Kok sebep:** rewrite dogru, ama `WP::query_posts()` istegi siradan bir gonderi
sorgusu gibi calistiriyor. Bu sitede **yayinlanmis `post` sayisi SIFIR** → sorgu
bos → `WP::handle_404()` 404 basiyor; `render_sitemaps()` sonra indeksi basip
`exit` ediyor ama durumu 200'e cekmiyor.

**Cozum:** `fd-site-haritasi-durum.php`, `pre_handle_404` kancasi, yalnizca
`sitemap` sorgu degiskeni doluyken `true`. Sonuc: indeks + 8 alt harita **200**,
329 adres; gercek 404'ler ve `/wp-sitemap-posts-post-1.xml` (0 yazi) **404 kaliyor**.

> **TUZAK: dev'deki ayni belirti BASKA BIR OLAYDI** — orada 404 **kasitli ve
> dogru**: `sitemaps_enabled()` `blog_public`'e bakar, staging guard onu 0'a
> zorlar. Ayirt eden olcut **govde**: canlida `<sitemapindex`, dev'de `<html`.

**Sayfa slug'i degisiminde WordPress 301 KURMAZ** —
`wp_check_for_changed_slugs()` hiyerarsik tiplerde (yani `page`) erken doner.
Cozum `fd-eski-adresler.php`: yalnizca istek 404'e dustugunde calisir, sorgu
dizesini korur. **Sira: once mu-plugin, sonra slug.**

---

## 5. Formlar ve e-posta

Formlar **FluentForm**'da (CF7 ve `mc4wp` kaldirildi). Giden posta **Brevo API**
(`BREVO_API_KEY`), DKIM/SPF/DMARC kurulu; FluentSMTP tuzaklari **genel dosya 6d**.
Turnstile giris, kayit, form ve WooCommerce akislarinda aktif — kaldirmadan once
3. bolumdeki gizli giris formu uyarisini okuyun.

### Bildirimler kime gidiyor

Kullanici karari: **butun EKIP bildirimleri `info@fdsanatmerkezi.com`** —
WooCommerce `new_order` / `cancelled_order` / `failed_order`,
`admin_payment_gateway_enabled`, stok uyarisi, WordPress `admin_email` ve butun
FluentForm beslemeleri. Yedek: `/var/backups/claude-2026-09-03-eposta/`.

**BILEREK DOKUNULMAYANLAR:**

- **Musteriye giden WooCommerce e-postalari** — alicisi musterinin kendisi;
  yonlendirmek siparis onayini musteriden calar.
- **`woocommerce_paypal_settings` → `receiver_email`** (`swordbros@gmail.com`) —
  bu bir **odeme hesabi**, bildirim alicisi degil. Degistirmek odemeyi kirar.
- **`woocommerce_email_from_address`** — GONDEREN alani, alici degil.
- **FluentForm `sendTo.type = field`** beslemeleri — alici basvuranin kendisi
  (otomatik yanit).

Yarim kalmis bir `new_admin_email` degisikligi (`blgnklc@gmail.com`, `adminhash`
onayi bekliyordu) iptal edildi.

> **GONDEREN ayardaki adres DEGILDIR.** WooCommerce ayari
> `info@fdsanatmerkezi.com` diyor ama FluentSMTP gonderirken
> `info@fdartgallery.com`'a ceviriyor — ve bu **dogru**: SPF/DKIM/DMARC o alan
> adina kurulu, ayardaki adresten gitse Brevo dogrulanmamis gonderen diye
> reddederdi. Panelde gordugunuz adres yaniltici, log'daki `from` gercek.

### Form bildirimleri yeniden kuruldu (03.09.2026)

**Sorun:** kayit geliyordu, e-posta cikmiyordu. **Kok sebep:**
`fluentform_form_meta`'da form #7/#8/#9'un `notifications` kaydi **satir satir
parcalanmisti** (tek JSON nesnesi yerine her alan ayri satir); #6/#12/#15'te
kayit **hic yoktu**. FluentForm bunlari besleme olarak okuyamiyor, sessizce
hicbir sey gondermiyordu.

**Cozum:** parcalanmis 30 satir silindi, her forma calisan form #5 sablonundan
**tek saglikli besleme** kuruldu.

| Form | Kayit | Durum |
|---|---|---|
| #5 Ozel Siparis | 7 | saglikliydi, dokunulmadi |
| #6 Duvara Resim | 2 | besleme yoktu → kuruldu |
| #7 Resim Workshop | 7 | parcalanmisti → yeniden |
| #8 Resim Kurs | 11 | parcalanmisti → yeniden |
| #9 Iletisim kuralim | **27** | parcalanmisti → yeniden |
| #12 Heykel Kurs | 2 | besleme yoktu → kuruldu |
| #15 Heykel Workshop | 3 | besleme yoktu → kuruldu |

Form #2 "Subscription Form" bildirimi de acildi; konusu bozuktu
(`{inputs.names}` diyordu ama formda yalnizca `email` alani var) →
`Konu: {form_title} | {inputs.email} - Yeni Abone`. Form #3 yayinda degil.

**Dogrulama uretim yolundan yapildi** (sahte `wp_mail` DEGIL): her form icin son
kayit alinip `ShortCodeParser::parse()` → `EmailNotification::notify()`
calistirildi, konuya `[TEST]` onegi konuldu. **7/7 SENT**, hepsi
`info@fdsanatmerkezi.com`. Yedek:
`/var/backups/claude-2026-09-03-ff-bildirim/notifications-oncesi.tsv`.

> **`feed_trigger_event` BILEREK KONULMADI.** Form #5'in beslemesinde
> `payment_success` yaziyor ve yine de calisiyor:
> `EmailNotificationActions::notify()` bu alani YALNIZCA `$form->has_payment` ve
> sayfada `payment_method` alani varken dikkate alir. Odemesiz formlarda inert;
> birakmak ileride sessiz kirilma riskidir.

> **TUZAK: `WC_Email_New_Order::trigger()` ayni siparis icin IKINCI KEZ
> gondermez** — siparise `_new_order_email_sent` meta'si koyar. Test ederken
> ikinci tetikleme sessizce hicbir sey yapmaz ve "e-posta bozuk" sanilir. Baska
> siparisle test edin, bitince bayragi `false`'a cekin. **Test yontemi:** gercek
> yolu tetikle (`WC()->mailer()->get_emails()['WC_Email_New_Order']->trigger(...)`),
> konuya `[TEST]` koy, sonucu `..._fsmpt_email_logs`'tan oku. 03.09.2026 siparis
> #3508 ile **SENT**.

### Hangi form hangi sayfada (basilan HTML'den)

| Sayfa | Form |
|---|---|
| `/ozel-siparis/` (#2) | `fluentform_5` Ozel Siparis |
| `/contact-us/` (#69) | `fluentform_9` Iletisim kuralim |
| `/elementor-1424/` | `fluentform_7` Resim Workshop |
| `/elementor-1441/` | `fluentform_6` Duvara Resim |
| `/resim-kursu-satin-al/` | `fluentform_8` Resim Kurs |
| `/heykel-ders-talep-formu/` | `fluentform_12` Heykel Kurs |
| `/resim-workshop-satin-al/` | `fluentform_7` Resim Workshop |
| `/heykel-workshop-satin-al/` | `fluentform_15` Heykel Workshop |
| altbilgi (her sayfa) | `fluentform_2` Subscription |

> **Formu DB'den aramaya calismayin.** Elementor widget'i `fluent-form-widget`
> ve form id'sini kacisli JSON icinde tutuyor; `"formId"` / `"form_id"` regex'i
> **bos doner** (uc kez denendi, uc kez yanlis sonuc). Guvenilir iki yol:
> basilan HTML'de `id="fluentform_N"`, ya da
> `fluentform_submissions.source_url` sutununu gruplamak.

### Altbilgideki bulten formu (03.09.2026)

Altbilgi bir **Elementor sablonu**: `elementor_library` **#1041** ("Elementor
Footer #194", tip `footer`). Widget alanlari (`footer-1`, `prefooter`, telif
altnotu) **BOS** — tema onlari kullanmiyor, aramayin.

Eklenen: uc sutunlu bolum ile telif satiri **ARASINA** tam genislikte serit —
baslik "Bültenimize abone olun" + `fluent-form-widget` (`form_list` = `"2"`).
Mevcut hicbir elemana dokunulmadi; container `_element_id` =
**`fd-bulten-seridi`**, geri almak o tek elemani silmek. Form metinleri
Turkcelestirildi (`fluentform_forms.form_fields`): `Your Email Address` →
**`E-posta adresiniz`**, `Subscribe` → **`Abone Ol`** (uc yerde: alan, ozel
buton, `submitButton`). Yedek: `/var/backups/claude-2026-09-03-altbilgi/`.

> **Veri yapisi bir seviye daha derin.** `_elementor_data`'nin koku `j[0]` ve
> altinda TEK sarmalayici var; uc sutunlu bolum ile telif satiri
> `j[0]['elements'][0]['elements']` altinda. Ilk denemede `j[0]['elements']`'e
> yazilmak istendi, kuru calistirma "1 bolum var" deyip yakaladi.

> **Uctan uca gonderim testi KABUKTAN YAPILAMAZ:** `cfturnstile_fluent=1`, yani
> FluentForm gonderimleri Turnstile istiyor ve script'in token'i yok. Zinciri
> kapatmanin tek yolu tarayicidan gercek gonderim.

**Zincir kapandi** — kullanici tarayicidan abone oldu (03.09.2026 09:00:44):
kayit #61 (form #2, kaynak `https://fdartgallery.com/`) ve log #46 **SENT** →
`info@fdsanatmerkezi.com`, **ayni saniye**. Bu tek gonderim dordunu birden
kanitladi: form gorunuyor, Turnstile gercek tarayicida engel olmuyor, kayit
DB'ye dusuyor, bildirim aliciya gidiyor. Test kaydi silindi; log satiri #46
kanit olarak birakildi.

> **FluentForm kaydini HAM SQL ile SILMEYIN.** Kayit dort tabloya yayilir; test
> aboneligi silinirken olculdu: `submissions` 1, `entry_details` 1,
> **`submission_meta` 2**, **`logs` 1**. Yalnizca `submissions`'tan silmek uc
> yetim satir birakirdi. Dogru yol eklentinin servisi —
> `SubmissionService::deleteEntries($ids, $formId)` → `Submission::remove()`;
> dosya eklerini de temizler ve `fluentform/after_deleting_submissions` kancasini
> calistirir. Silme sonrasi dordu de 0 dogrulandi.
> Yedek: `/var/backups/claude-2026-09-03-abone-kaydi/`.

---

## 6. Diger notlar

- **`mc4wp` kaldirilirken `wp rewrite flush` bir kez yanlislikla
  `--skip-plugins` ile calistirildi.** Hasar, dokunulmamis canli ile dev'in
  **saklanan** `rewrite_rules` anahtarlari diff'lenerek denetlendi: fark tam 20
  kural, hepsi kaldirilan eklentinin `mc4wp-form/...` kurallariydi. Yine de
  dogru bicimiyle tekrar flush edildi. (Kural genel dosyada 6f.)
- Blog altyapisi burada ama **icerik chestnyznak'in**: `deploy/blog/` araclari ve
  haftalik Routine (`trig_01Q2abbcd19d1CQem3fs5Z7M`, Sali 06:00 UTC) chestnyznak
  blogunu besler. Uslup ve kuyruk: `deploy/blog/README.md`, `BACKLOG.md`.

---

## 7. Bu depodaki araclar (butun siteler icin)

| Yol | Ne yapar |
|---|---|
| `deploy/nginx/` | vhost'lar, `snippets/`, `templates/`, `conf.d/` |
| `deploy/scripts/` | **21 script — asagida tam liste** |
| `deploy/wordpress/fdart-mu-plugins/` | **fdartgallery** mu-plugin'leri |
| `deploy/wordpress/lead-mu-plugins/` | **chestnyznak** mu-plugin'leri |
| `deploy/wordpress/i18n/` | chestnyznak uc dil scriptleri (01→16) + JSON sozlukler |
| `deploy/blog/` | `yazi-yayinla.php`, `ceviri-yayinla.php`, `telegram-cek.py`, `cover.php`, `README.md`, `BACKLOG.md` |
| `deploy/cloudflare/` | `email-worker.js` |

Sunucuda ayni depo `/opt/fdartgalleryuk/` altinda.

**`deploy/scripts/` tam envanteri** — genel dosya bu dizinden hic soz etmiyor,
burasi TEK listesi:

| Grup | Dosyalar |
|---|---|
| Site yasam dongusu | `add-site.sh`, `clone-site.sh`, `deploy-site.sh` |
| **Yedekleme** | `backup-site.sh` (gece 04:15 → R2, `/etc/cron.d/backup-<slug>`), `setup-backup.sh` |
| Kurulum / sertlestirme | `setup-server.sh`, `setup-redis.sh`, `setup-fail2ban.sh`, `setup-named-tunnel.sh`, `harden-ssh.sh`, `harden-mysql.sh` |
| Cloudflare / DNS | `cloudflare-dns.sh`, `update-cloudflare-ips.sh` |
| Bakim | `purge-cache.sh` (nginx + istege bagli Cloudflare), `convert-webp.sh` |
| Oturum erisimi | `claude-access.sh` (adlandirilmis tunel calisirken GEREKMEZ) |
| wp-config yamalari | `wpconfig-redis.py`, `wpconfig-bildirim-sabitleri.py` |
| Tek seferlik / icerik | `demo-icerik-temizle.php`, `eposta-alicilari.php`, `migrate-czlead-to-elementor.php` (son ikisi chestnyznak) |

> chestnyznak araclarini **calistirmadan once** o sitenin kurallarini okuyun
> (`chestnyznakuk/CLAUDE.md`): uc dil kurali, Elementor kurali, icerik kapatma
> yasagi. Aracin burada durmasi kurallarin burada oldugu anlamina gelmez.

---

## 8. Calisma kurallari

Ortak kurallarin tamami **genel dosyanin 7. bolumunde**. Bu depoya ozgu:

- Tum gelistirme `claude/ovhcloud-vps-multisite-0eb3xj` dalinda; izinsiz baska
  dala push yok, izinsiz PR yok.
- **Bu dalda birden fazla oturum calisiyor — push oncesi `git fetch` + `git
  rebase`.** Gelen commit'leri once **okuyun**: 02.09.2026'da iki push reddedildi,
  ikisinde de araya giren commit CLAUDE.md'ye deger bir sey eklemisti ve
  korunmasi gerekti. **Force push YOK.**
- **Sirlar asla commit edilmez**: token, DB sifresi, private key, `wp-config.php`.
- **Once dev, sonra canli.** Canliya alirken dev'in HTML'i **kopyalanmaz** — ayni
  donusum canlinin kendi icerigi uzerinde calistirilir, cikti denetlenir. Bir isin
  dev'de dogrulanmasi anlamsizsa (ornek: APO purge — dev CF zone'u degil) bunu
  yazip **canlida** yapin.
- Canliyi etkileyen her islemden **once yedek**, mumkunse kuru calistirma, sonra
  **kullanici onayi**.
- `deploy-site.sh` `rsync --delete` kullanir → depo eksikken calistirmayin.
- **Degisiklikten sonra dogrula: HTTP kodu yetmez** (genel dosya 6a).
- **Bu dosyayi fdartgallery'e ozel tut.** Ortak tuzagi genel depoya, chestnyznak'a
  ait olani `chestnyznakuk`'a yaz.
