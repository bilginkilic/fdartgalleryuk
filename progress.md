# progress.md — fdartgallery.com

**Bu dosya CLAUDE.md DEGILDIR.** Burada yalnizca **degisken** seyler durur:
bekleyen isler, acik kararlar, son olcum. Kural ve tuzak CLAUDE.md'ye, karar
gerekcesi `ovgcloudukmultisite/adr/` altina yazilir.

Son guncelleme: **27.09.2026**

---

## Durum

Site yayinda. Kurulum, performans, guvenlik, yedekleme, site haritasi, formlar,
e-posta ve onbellek bitti. **Sunucu tarafinda yapilacak is kalmadi.**

## Bekleyen: bu dosyayi bolmek (kullanici onayladi 27.09.2026)

CLAUDE.md **921 satir**; buyuk kismi §3'te. Genel dosyayla birlikte her oturumun
basinda ~1.400 satir yukleniyor ve bir oturum sikistirmaya girdi.

27.09'da ADR cikarmasiyla 1.082 → 921'e indi; bolme **hala gerekli**:

| Dosya | Icerik |
|---|---|
| `CLAUDE.md` | ~150 satir: site, yollar, komutlar, **tuzak listesi**, calisma kurallari, baglantilar |
| `docs/hiz.md` | §3 (OPcache, APO, LCP, gorsel boyutlari) |
| `docs/elementor.md` | slaytlar, hero, urun kartlari, filtreler, sablon yapisi |
| `docs/formlar.md` | §5 |

> **Tuzaklar bolunmez.** OPcache, `open_basedir` yedek tuzagi, SSH heredoc,
> "genis fiyat araligiyla test etme", "row konteyner" — bunlarin **tek satirlik
> ozeti CLAUDE.md'de kalir**, detayi `docs/`'a gider. Yanlis yerden bolunurse bir
> tuzak kaybolur ve bu ancak bir sey kirildiginda fark edilir.
>
> Once **plan cikarin**; boldukten sonra her `docs/` dosyasinin CLAUDE.md'den
> baglantili oldugunu ve hicbir tuzagin dusmedigini **sayarak** dogrulayin.

## Bekleyen tek performans maddesi — temada, tasarim karari

**Mobil LCP 10,9 sn (puan 37-40).** Masaustu iyi: **puan 85**, LCP 1,99 sn.
Mobildeki sebep tarayicidaki is — Style & Layout 4,3 sn, jQuery 4,8 sn, sayfa
3.159 KB. TTFB 58 ms, yani bekleme degil. Denenip **ise yaramayan** uc mudahale
`adr/0011` (yeniden denemeyin).

Kalan iki secenek — **ikisi de kullanici karari**:

| # | Secenek | Kazanc | Risk |
|---|---|---|---|
| 1 | **Turnstile ~1 MB** — widget'i sayfa acilisinda degil **giris penceresi acilinca** render etmek | 15+ istek, ~1 MB | ozel is; yanlis yapilirsa **giris kirilir** (CLAUDE.md 3) |
| 2 | **Ana sayfa widget sayisi** (33 container / 60 widget, 2 urun listesi) | asil `Style & Layout` maliyeti | tasarim karari |

> **Slayt 3 → 1 YAPILDI** (20.09.2026), artik secenek degil. Hero sonradan
> **8 slaytlik kesif seridine** cevrildi (23.09) — CLAUDE.md 3.

## Bekleyen: Haftanin Seckisi blogu (10.10.2026)

Kod ve ilk yazi depoda, **sunucuya henuz konmadi** (oturumda tunel kimligi yoktu).
Yedekten (25.08) kurulan yerel kopyada tarayiciyla dogrulandi: ana sayfada
bolum altbilginin hemen ustunde, 8 eser karti, paylasim satiri, tek
`description` + OG + JSON-LD (BlogPosting + ItemList).

1. dev: `fd-blog-seckiler.php` → `wp-content/mu-plugins/`, sonra
   `seckiler-yayinla.php` dry → taslak (tarif `deploy/blog/fdart-seckiler/README.md`).
2. Dev'de bak: `/`'nin en alti, yazi sayfasi masaustu + mobil (`sayfa-olc.py`).
3. Kullanici onayi → canli (`/canliya-al`). Canlida urunler 25.08'den beri
   degismis olabilir: kuru calistirma her eserin yayin/stok durumunu listeler.

Tek yazi sablonu #1053'te (Elementor) kullanicinin onayini bekleyen iki kusur:
meta satiri **Ingilizce** ("4 min read", "Author:", "Published:") ve paylasim
ikonlari Facebook / LinkedIn / Pinterest / **Skype** (kapandi). Mu-plugin bunlara
dokunmuyor; kendi paylasim satirini (WhatsApp, Pinterest, Facebook, X, kopyala)
yazinin sonuna ekliyor.

## Bekleyenler — kullanicidan

| # | Is | Not |
|---|---|---|
| 1 | **Blogun en eski 4 yazisi** hala Ingilizce demo slug'inda | 301 ister, tablo hazir (`fd-eski-adresler.php`). Ayrinti `deploy/blog/BACKLOG.md` |

## ADR yazilmasi onerilenler (27.09 degerlendirmesi)

Bunlar henuz kayit degil; sirasi gelince `/adr` ile yazilir:

- **Elementor arsiv sablonlari** — hangi sablon hangi gorunumu suruyor, neden.
- **iyzico** odeme saglayicisi secimi.

Yazilmis olanlar: `0002` onbellek, `0003` OPcache, `0004` APO/bulut rengi,
`0010` urun gorseli, `0011` mobil LCP.

## Son olcum (03.09.2026, Observatory / me-west1)

| | canli (APO) | dev (APO yok) |
|---|---|---|
| TTFB masaustu / mobil | **53 / 52 ms** | 96 / 98 ms |
| LCP masaustu | 2,25 sn | 2,16 sn |
| LCP mobil | 10,5 sn | 12,2 sn |
| Puan masaustu / mobil | 85 / 41 | 83 / 48 |

Origin: nginx `fastcgi_cache` TTFB **17-25 ms**, kenar MISS olsa bile arkada HIT.

Onbellege GIREMEYEN sayfalar (09.09.2026, OPcache isinirken olculdu):
`/cart/` 0,76-0,80 sn · `/my-account/` 0,68-0,71 sn · `/checkout/` 302.

> Altyapi bekleyenleri (kalan 4 site, SSH parolasi, `VPS_SSH_PRIVATE_KEY`, PTR,
> sunucu maliyeti) **`ovgcloudukmultisite/progress.md`** icinde.

## 27.09.2026 — Kategori filtre paneli varsayilan kapali (CANLI)

`fd-kategori-filtresi-kapali.php` (mu-plugin, dev + canli). Yatay filtre
cubugunda yalnizca **Kategori** paneli kapali basliyor; Durum ve Fiyat acik.

Neden temanin ayariyla degil: XStore'da panel BAZINDA acik/kapali ayari **yok**.
`widgets_toggle_action_opened` uc paneli birden kapatiyor, `enabled_default` tum
cubugu gizliyor — masaustunde acma dugmesi `display:none` oldugu icin filtreler
tamamen **erisilemez** hale gelirdi. Cozum: temanin kendi toggle'ini
(`.widget-title` tiklamasi) programatik tetiklemek.

Olculen: kategori sayfalarinda cubuk **478 -> 140 px**, urun izgarasi
**338 px yukari** (y=1121 -> y=783). Tiklayinca aciliyor, tekrar tiklayinca
kapaniyor. Mobil degismedi.

> **TUZAK: 8 saniyelik guvenlik agi gozlemciyi de kapatiyordu.**
> Ilk surumde MutationObserver 8 sn sonra `disconnect()` ediliyordu. Kategori
> sayfalarinda cubuk yukarida oldugu icin sorun gorunmedi — ama **/shop/**
> sayfasinda cubuk y~1454'te ve panelleri ancak gorus alanina girince
> yukleniyor. O an gozlemci coktan kapanmisti: degisiklik /shop/'ta **hic etki
> etmiyordu ve bu HTML'e bakarak fark edilmiyordu** (script sayfada, isaret
> sayimi 1 donuyor). Ancak tarayicida KAYDIRARAK olcunce ortaya cikti.
> Duzeltme: 8 sn artik yalnizca icerigi gosterir; gozlemci basariya kadar
> (en fazla 5 dk) acik kalir.

> **TUZAK: `/shop/` HTML'indeki `widget-has-toggle` eslesmeleri sahte.**
> Filtre panelleri AJAX ile geliyor; sunucudan gelen HTML'de yalnizca
> kacirilmis JSON yuku (`et-element-args`) var. `grep` ile sayarsaniz "var"
> gorunur ama DOM'da yoktur. Ayni tuzak `widget-title` icin de gecerli.

Yedek: `/var/backups/claude-2026-09-27-kategori-paneli/`.

## 10.10.2026 — Haftanin Seckisi blogu CANLIDA

`claude/project-thread-mqhmdh` (34e5bf61) dalindaki is dev'de kuruldu, olculdu,
sonra canliya alindi. Kullanici onayi alindi.

| | dev | canli |
|---|---|---|
| yazi | #3809 | **#3899** |
| ana sayfadaki `.fdsk-bolum` | 1 adet, 1440x830 | 1 adet, 1440x830 |
| bolum altbilginin ustunde | evet (5358 < 6188) | evet (5393) |
| yazidaki `.fdsk-eser` | 8 | **8** |
| `<meta name="description">` | 1 (yazinin kendi) | 1 (yazinin kendi) |
| `og:title` / JSON-LD | var / 1 | var / 1 |

Kuru calistirma 8 eserin (3703, 3650, 3688, 3099, 3633, 3358, 2760, 3471)
yayinda ve stokta oldugunu dogruladi. Icerik kayipsiz yazildi (9119 bayt).
Kategori `haftanin-seckisi` (term 83) olustu.

> **Canlida yazi YOKTU.** Devreye almadan once `wp post list --post_status=any`
> **0** dondu. CLAUDE.md'deki "blogun en eski 4 yazisi Ingilizce demo
> slug'inda" maddesi bu yuzden **eskimis** — kontrol edilmeli.

> **TUZAK: yazi kaydinda `[Cloudflare] ERROR: Bad Request` cikar, ama purge
> CALISIR.** Panige kapilmayin. Eklenti temizlikten once
> `GET /zones/<id>/pagerules?status=active` cagiriyor; bizim belirtecimiz
> **hesap sahipli (account-owned)** oldugu icin Cloudflare bunu
> `400 / kod 1011 "Page Rules endpoint does not support account owned tokens"`
> ile reddediyor. Eklenti hatayi basip devam ediyor ve asil `purge_cache`
> istegi **200 / success:true** donuyor.
>
> Yanlis teshis koymayin: once "DELETE yerine POST olmali" sandim, sonra
> "URL listesi bozuk" sandim — ikisi de YANLISTI. Ayni govde ayni yontemle
> elle gonderildiginde 200 donuyor. Dogru yontem `http_api_debug` kancasiyla
> **butun** giden istekleri yanit koduyla birlikte loglamak; hatali olan
> ucuncu bir cagriydi. Duzeltmek icin kullanici sahipli belirtec gerekir;
> islevsel kazanc yok, birakildi.

Yedek: `/var/backups/claude-2026-10-10-secki/`.

## 10.10.2026 — Mobilde yatay kayma (dev'de duzeldi, CANLI BEKLIYOR)

Kullanici bildirimi: "ekranin sag kaymasi sorunu, cep telefonu versiyonunda,
oncesinde yoktu". Sikayet hero'yu isaret ediyordu; **suclu hero degildi.**

390px mobil Safari'de olculen: **belge 510px / ekran 390px -> 120px tasma.**
Tek sebep **altbilgideki bulten formu**:

```
.elementor-1041 .elementor-element-<id> .fluentform-widget-wrapper{width:480px}
```

330px'lik kabin icinde 480px; sag kenari 510 = belge genisliginin kendisi.
Butun sayfa saga kayiyor, hero da onunla suruklenıyor.

> **TUZAK: kontrolun adi `form_container_max_width` ama Elementor bunu
> `max-width` DEGIL `width` olarak basiyor.** Adi "max-width" oldugu icin
> zararsiz sanilir; degildir.

> **TUZAK: widget kimlikleri dev ve canlida FARKLI** (dev `5ee039e`,
> canli `21d63db`). Kimlik gomulu bir script canlida hicbir sey bulamaz.
> `ff-genislik.php` widgetType'a gore tarar. Revizyonlar da dislanir —
> 12 eslesmenin 10'u revizyondu.

Duzeltme: tablet + mobil `%100`, **masaustu 480px korundu**. Dev'de olculdu:

| | once | sonra |
|---|---|---|
| masaustu 1440px | 480px | **480px** (degismedi) |
| mobil 390px | 480px (tasiyor) | **300px** |
| tasma: `/`, `/contact-us/`, `/shop/`, yeni blog yazisi | 120px | **0** |

Ayrica **Iletisim sayfasinda (#69) ayni hata vardi** — orada 536px. Kimse
sikayet etmemisti; o da duzeltildi.

**CANLIYA ALINDI** (10.10.2026, kullanici onayiyla). Canlida widget'lar
`#69 ec41b71` (536px) ve `#1041 21d63db` (480px) idi; ikisi de yazildi, geri
okuma temiz. nginx + Cloudflare purge yapildi.

Canlida olculen:

| | sonuc |
|---|---|
| tasma: `/`, `/contact-us/`, `/shop/`, `/product-category/oil/`, blog yazisi | **hepsi 0px** |
| masaustu 1440px form genisligi | **480px** (degismedi) |
| mobil 390px form genisligi | **300px** |
| sayfalar | / 200, /contact-us/ 200, /shop/ 200, /cart/ 200, /checkout/ 302 |
| diger siteler | dev + iki chestnyznak 200 |

Yedek: `/var/backups/claude-2026-10-10-ff-genislik/` (dev + canli oncesi).

## 10.10.2026 — Varsayilan siralama "yeniden eskiye" (CANLI)

Istek: ana sayfa ve toplu eser listeleri hep guncel gorunsun.

> **Durum eskiden ALFABETIKTI, kimse farkinda degildi.**
> `woocommerce_default_catalog_orderby` = `menu_order` idi; menu_order her
> urunde 0 oldugu icin WooCommerce basliga dusuyor ve `/shop/` fiilen
> A-Z siralaniyordu: Acorn, Agil, Alplerde, Antika, Armutlar, Aslan...
> 8 Ekim'de eklenen eserler hicbir yerde gorunmuyordu.

Iki ayri yerden geliyor, **ikisi de gerekli**:

1. **Arsivler** (`/shop/` + 9 kategori sablonu): hicbirinde kendi siralamasi
   yok, hepsi `woocommerce_default_catalog_orderby` seceneğine bakiyor.
   `menu_order` -> **`date`**.
2. **Elementor urun izgaralari** (#60 ana sayfa `521d25d`, #3510 `dc543cb`):
   bunlar WC secenegini **DINLEMEZ**, kendi ayarlari gerekir.

> **TUZAK: widget'in `orderby` varsayilani zaten `date` ama `order`
> varsayilani `ASC`.** Yani yalnizca orderby verirseniz ESKIDEN YENIYE olur.
> Ikisi birden yazilmali: `orderby=date`, `order=DESC`.

Canlida dogrulandi — gorunen sira veritabanindaki `date DESC` ile birebir:

| | 1 | 2 | 3 | 4 |
|---|---|---|---|---|
| ana sayfa | Esik dostu | Pink rose | Rose | Seftaliler |
| `/shop/` | Esik dostu | Pink rose | Rose | Seftaliler |
| veritabani (date DESC) | #3892 08.10 | #3883 08.10 | #3881 08.10 | #3874 30.09 |

`/indirimdekiler/` dokunulmadi — o kisa kodla fiyata gore siralı, oyle kalmali.

Yedek: `/var/backups/claude-2026-10-10-siralama/` (dev + canli oncesi,
secenek degeri dahil).
