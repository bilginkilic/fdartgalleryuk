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
