# chestnyznak.com.tr — blog yayin sureci

Bu dizin, siteye duzenli olarak yeni rehber yazisi eklemek icin gereken her seyi
tutar. Amac iki tane: **arama motorlarinda bulunabilirlik** ve ziyaretcinin
iletisim formuna / WhatsApp'a ulasmasi.

| Dosya | Ne ise yarar |
|---|---|
| `BACKLOG.md` | Konu kuyrugu + yayinlanmis yazilarin listesi. Tek dogruluk kaynagi. |
| `cover.php` | 1200x630 kapak gorseli uretir (mevcut yazilarin gorsel dilini birebir taklit eder). |

Haftalik **Routine** bu sureci yurutur (bkz. CLAUDE.md 5m).

---

## Yazi kurallari — mevcut yazilarla ayni olsun

Ornek alinacak yazi: `rusya-edo-elektronik-belge-akisi-chestny-znak` (ID 37684)
veya `chestny-znak-cezalari-etiketsiz-urun-rusya-2026` (ID 37658).

**Bicim**
- Duz HTML; Gutenberg blogu veya Elementor **kullanilmaz**. `<p>`, `<h2>`, `<h3>`,
  `<ul>`, `<ol>`, `<strong>`, `<em>`, `<a>` yeterlidir.
- 1200-1600 kelime. 8-11 adet `<h2>`.
- Acilis: somut bir sahne veya ihracatcinin yasadigi bir tikanma. Genel tanimla baslama.
- Sonda mutlaka **"Sik sorulan sorular"** bolumu (`<h3>` sorular, 4-5 tane).
- Kapanis paragrafi: Moskova/Istanbul/Podgorica ofisleri + RTIB uyeligi + CTA.

**Uslup**
- Turkce, ikinci cogul ("edin", "kontrol edin"). Satis dili degil, danisman dili.
- Rusca terimlerin ilk gecisinde parantez icinde orijinali verilir:
  EDO (*электронный документооборот*), UPD, UKEP, CRPT gibi.
- Rakam ve tarih verilirken **hedge sart**: "kamuya acik kaynaklarda", "aktarilan
  ust sinir". Yazinin sonunda bilgilendirme notu bulunur.
- Uydurma mevzuat maddesi, uydurma tutar, uydurma tarih **yazilmaz**. Emin
  olunmayan sayi hic verilmez; yerine "urun grubuna gore degisir, teyit ettirin" denir.

**Ic baglantilar (SEO'nun asil isi burada)**
- Yazi basina **5-8 ic baglanti**. Hedefler `BACKLOG.md`'deki yayinlanmis yazilar.
- Her yazida en az bir kez `/kod-sorgulama/` aracina baglanti verin — donusum
  noktasi orasi.
- Kapanista `/iletisim/`, `/shop/` ve `/blog-standard/` baglantilari.

**Yayin alanlari**
- Kategori: `modern` (gorunen adi "Rusya Markalama Rehberi") — tum yazilar burada.
- Yazar: kullanici ID **3**.
- Yoast: `_yoast_wpseo_title` (≤ 60 karakter) ve `_yoast_wpseo_metadesc` (≤ 155).
- Slug: anahtar kelimeyi tasisin, Turkce karakter ve noktalama icermesin.

---

## Kapak gorseli

```sh
php deploy/blog/cover.php \
  --title="Rusya’da EDO Nedir?" \
  --title2="Chestny Znak’in Gorunmeyen Sarti" \
  --desc1="Elektronik belge akisi, UPD ve devir" \
  --desc2="bildiriminin isleyisi · 2026" \
  --seed=<slug> \
  --out=/tmp/<slug>.png
```

- Cikti 1200x630 PNG (Open Graph olcusu).
- `--seed` slug olsun: desen slug'dan turetilir, yani ayni yazi her zaman ayni
  gorseli uretir.
- Baslik sigmazsa punto otomatik dusurulur; yine de `--title` icin ~30,
  `--title2` icin ~34 karakteri asmayin.
- Sagdaki kare **dekoratiftir**, okunabilir bir DataMatrix degildir.
- Fontlar temadan gelir (`--fontdir` ile degistirilebilir).

---

## Yayinlama (sunucuda)

```sh
P=/var/www/chestnyznak.chemiartclick.uk/public
U=web_chestnyznak_chemiartclick_

# 1) kapak gorselini kutuphaneye al
AID=$(sudo -u $U wp --path=$P media import /tmp/<slug>.png \
        --title="<gorsel basligi>" --alt="<alt metin>" --porcelain)

# 2) yaziyi olustur (icerik dosyadan okunur)
PID=$(sudo -u $U wp --path=$P post create /tmp/yazi.html \
        --post_type=post --post_status=publish --post_author=3 \
        --post_title="..." --post_name="<slug>" --post_excerpt="..." --porcelain)

# 3) kategori, one cikan gorsel, Yoast
sudo -u $U wp --path=$P post term set  $PID category modern
sudo -u $U wp --path=$P post meta update $PID _thumbnail_id "$AID"
sudo -u $U wp --path=$P post meta update $PID _yoast_wpseo_title    "..."
sudo -u $U wp --path=$P post meta update $PID _yoast_wpseo_metadesc "..."

# 4) webp uret + onbellek temizle
sudo bash deploy/scripts/convert-webp.sh chestnyznak.chemiartclick.uk
sudo bash deploy/scripts/purge-cache.sh  chestnyznak.chemiartclick.uk
```

### Yayin sonrasi dogrulama — atlanmaz

```sh
curl -sI --resolve chestnyznak.com.tr:443:127.0.0.1 \
     https://chestnyznak.com.tr/<slug>/            # 200 olmali
curl -s  --resolve chestnyznak.com.tr:443:127.0.0.1 \
     https://chestnyznak.com.tr/blog-standard/ | grep -c <slug>   # listede gorunmeli
curl -s  --resolve chestnyznak.com.tr:443:127.0.0.1 \
     https://chestnyznak.com.tr/page-sitemap.xml   # Yoast site haritasi
```

`--resolve ... 127.0.0.1` sart: sunucunun kendi genel IP'sine giden istek
`cloudflare-only` kuralina takilabilir. Konteynerden `--resolve` ise
`HTTPS_PROXY` yuzunden **yok sayilir** (CLAUDE.md 5l, "TEST TUZAGI").

---

## wptexturize tuzagi

WordPress icerikteki duz tirnaklari tipografik tirnaga cevirir. Yazi metninde
sorun degil, ama **`<script>` iceren sayfalarda** kod bozulur. Bu yuzden
`kod-sorgulama` gibi arac sayfalarinda JavaScript'te karsilastirma operatoru
yerine `indexOf`/`Math.max` gibi fonksiyonlar kullanilmistir. Blog yazilarinda
script kullanmayin; gerek olursa ayri bir sayfa acin.
