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

Site yayinda; kurulum, performans, guvenlik, yedekleme, site haritasi, formlar
ve e-posta bitti. **Sunucu tarafinda yapilacak is kalmadi** — kalan iki madde
tema/PHP tarafinda ve **kullanici onayi bekliyor**:

- **Mobil LCP ~10,5 sn (puan ~41).** TTFB 52 ms oldugu icin sebep bekleme degil
  **sayfa agirligi**: 252 KB HTML, 46 `<script>`, 29 stylesheet,
  `elementor-all-widgets.min.js` tek basina 134 KB (sikistirilmis), `preload` **0**.
  Ilk adim LCP gorseline `preload` + `fetchpriority`.
- **`/cart/` 2,7 sn · `/my-account/` 2,6 sn · `/checkout/` 1,8 sn.** Origin'de de
  ayni → **PHP**, ag degil. Bu sayfalar dogasi geregi onbelleklenemez (oyle
  kalmali), o yuzden ne APO ne nginx yardim eder. Dogrudan satisa deguyor.
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
| Site haritasi | `wp-sitemap*.xml` 404 → **200**; indeks + 8 alt harita, 329 adres (4) |
| Formlar / e-posta | FluentForm besleme onarimi, butun ekip bildirimleri tek adrese, altbilgi bulten formu (5) |
| Turnstile | giris/kayit/form/WooCommerce |
| WebP | 6437 dosya (02.09.2026; `/etc/cron.d/webp-fdartgallery_com` gece artirir); orijinaller korunur |

**mu-plugin'ler** — `deploy/wordpress/fdart-mu-plugins/` (yalnizca bu site):

| Dosya | Ne yapar |
|---|---|
| `fd-asset-diyet.php` | sayfada karsiligi olmayan CSS/JS'i kuyruktan cikarir (3) |
| `fd-font-hizlandirma.php` | googleapis isteklerini tek istege indirir, preconnect (3) |
| `fd-eski-adresler.php` | slug degisimi icin 301 tablosu (4) |
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
