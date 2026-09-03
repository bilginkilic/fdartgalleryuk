# CLAUDE.md — fdartgallery.com

Bu depo **fdartgallery.com**'un calisma hafizasi. **Ayrica butun sitelerin
`deploy/` araclarini barindirir** — chestnyznak'in i18n, blog ve temizlik
scriptleri de burada durur (asagida 7).

> **GENEL ALTYAPI BURADA DEGIL.** Sunucu, SSH/tunel erisimi, DNS, kimlik
> bilgileri, site yerlesim standardi ve butun sitelerde gecerli tuzaklar
> **`bilginkilic/ovgcloudukmultisite` → `CLAUDE.md`** icinde.
> **Yeni oturumsan once orayi oku.**
>
> chestnyznak'a ozel bilgi: **`bilginkilic/chestnyznakuk` → `CLAUDE.md`**.

**Kisayol — sunucuya baglanma** (tarifin tamami genel dosyada):

```sh
curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 \
     -o /tmp/cloudflared && chmod +x /tmp/cloudflared
export TUNNEL_SERVICE_TOKEN_ID="$CLOUDFLARE_CF_Access_Client_Id"
export TUNNEL_SERVICE_TOKEN_SECRET="$CLOUDFLARE_CF_Access_Client_Secret"
ssh -o "ProxyCommand=/tmp/cloudflared access ssh --hostname ssh.fdartgallery.com" \
    -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@vps
```

Ham TCP/22 **kapali**, makinede hazir anahtar **yok** — anahtarin yoksa uret,
`.pub`'i dala push et, dur ve haber ver. Kullanici daima `root`.

---

## 0. SU AN NE KALDI (fdartgallery)

Site yayinda. Kurulum, performans, guvenlik, yedekleme ve site haritasi bitti.

### Karar bekleyenler

- **Cloudflare APO** (~5 $/ay) — fdartgallery turuncu bulutta, secenek.
  Su an `cf-cache-status: DYNAMIC`.

- **Blogun en eski 4 yazisi** hala Ingilizce demo slug'inda. Duzeltmek 301 ister;
  tablo hazir (`fd-eski-adresler.php`). Ayrinti `deploy/blog/BACKLOG.md`.

Altyapi tarafinda bekleyenler (kalan 4 sitenin arsivi, SSH parola girisi,
`VPS_SSH_PRIVATE_KEY`, PTR) **genel dosyanin 0. bolumunde**.

---

## 1. Site

| | |
|---|---|
| Canli | `fdartgallery.com` → `/var/www/fdartgallery.com/public` |
| Dev | `dev.fdartgallery.com` → `/var/www/dev.fdartgallery.com/public` |
| DB / onek | `wp_fdartgallery_com` / `fdArt0Og_` |
| Tema | XStore (WooCommerce) |
| Redis | canli db=3, dev db=4 |
| DNS | `fdartgallery.com`, `www`, `dev` — **turuncu**; SSL **Full (strict)** |

Vhost'larda `cloudflare-only.conf` **VAR** (turuncu kayit; chestnyznak'ta yoktur).
mu-plugin'ler: `deploy/wordpress/fdart-mu-plugins/`.

Mail kayitlari (Brevo DKIM, DMARC, SPF, Email Routing MX) **degistirilmez**.

---

## 2. Yapilmis isler

| Alan | Durum |
|---|---|
| Hiz | **canli + dev**: 47 → 26 script, 32 → 26 stylesheet, react+12 `js/dist` dustu, `.ttf` gzip, isinmis TTFB 15-19 ms (3) |
| Eklenti sadelestirmesi | **17 → 11**, canli ve dev esit. Kapatilan 4: `mailchimp-for-woocommerce`, `fluentforms-pdf`, `image-optimization`, `updraftplus`. Komple kaldirilan 2: **Contact Form 7** (formlar FluentForm'da) ve **`mc4wp`** (hicbir sayfada basilmiyordu) |
| Reklam/bulten trafigi | `?utm_*`/`gclid`/`fbclid` artik onbellekten okuyor; TTFB ~2,3 sn → **15-25 ms** (3) |
| Site haritasi | `wp-sitemap*.xml` 404 yerine **200**; indeks + 8 alt harita, 329 adres (4) |
| Turnstile | giris/kayit/form/WooCommerce |
| E-posta | Brevo API + DKIM/SPF/DMARC — calisiyor |
| WebP | 6437 dosya (02.09.2026 olculdu, `/etc/cron.d/webp-fdartgallery_com` ile gece artar); orijinaller korunur |

**mu-plugin envanteri** — `deploy/wordpress/fdart-mu-plugins/` (yalnizca bu site):

| Dosya | Ne yapar |
|---|---|
| `fd-asset-diyet.php` | sayfada karsiligi olmayan CSS/JS'i kuyruktan cikarir (3) |
| `fd-font-hizlandirma.php` | googleapis isteklerini tek istege indirir, preconnect (3) |
| `fd-eski-adresler.php` | slug degisimi icin 301 tablosu — cekirdek SAYFA slug'inda 301 kurmaz (4) |
| `fd-site-haritasi-durum.php` | `wp-sitemap*.xml` 404 yerine 200 doner (4) |

Butun sitelerde ortak olanlar (`mu-plugins/`, `staging-mu-plugins/`) genel
dosyanin 4. bolumunde; `lead-mu-plugins/` chestnyznak'indir.

---

## 3. Hiz ve onbellek

**Once olcun, sonra dokunun.** Sunucu darbogaz degil (TTFB 16-26 ms); darbogaz
**istek sayisi** ve **harici kaynak**.

**Varlik diyeti** — `deploy/wordpress/fdart-mu-plugins/fd-asset-diyet.php`.
Sayfada karsiligi olmayan dosyalari kuyruktan cikarir. Kosul saglanmiyorsa dosya
AYNEN kalir (hata durumunda eksik degil, fazla yuklenir).

> chestnyznak'in AYRI bir dosyasi var
> (`deploy/wordpress/lead-mu-plugins/fd-asset-diyet.php`) — **karistirmayin**.

> **TUZAK: tek bagimlilik 15 dosya getiriyordu.** `cfturnstile-woo-js`
> bagimlilik listesinde `wp-data` var → react + 12 `js/dist` dosyasi.
> **"Scripti cikaralim" YANLISTI:** XStore her sayfaya gizli bir WooCommerce
> giris formu basiyor, script cikarilsaydi Turnstile dogrulanmaz ve **giris
> kirilirdi**. Dogru mudahale: script KALIR, sepet/odeme disinda handle
> `wp-data`'siz **on kayit** edilir (`WP_Dependencies::add()` mevcut handle'i
> EZMEZ).

> **Bir handle'i cikarmadan once `src`'sini ve KANCASININ NE ZAMAN calistigini
> okuyun.** Turnstile `wp_enqueue_scripts`'te degil kendi eyleminde enqueue
> ediyor — oradaki dequeue kurali ona HIC DEGMEDI.

**Fontlar** — `fdart-mu-plugins/fd-font-hizlandirma.php`: googleapis
stylesheet'leri **tek** istege birlestirilir, agirlik listesi 18 → 9,
`fonts.googleapis.com` ve `fonts.gstatic.com` icin **preconnect** (gstatic'te
`crossorigin` SART). Aile ELENMEDI — kit tipografisi sayfada kullaniliyor, aile
atmak basliklari sistem fontuna dusururdu; bu bir **tasarim** karari.

`deploy/nginx/snippets/font-gzip.conf` — yalnizca `.ttf|.otf|.eot` eslesen
location icinde gzip (nedeni genel dosyanin nginx bolumunde).

### Izleme parametreli istekler (`?utm_*`)

**Okuma ile yazma AYRILIR:** `fastcgi_cache_bypass` OKUMAYI, `fastcgi_no_cache`
YAZMAYI kontrol eder. Izleme-only isteklerde okuma **serbest** (anahtar sorgu
dizesiz), yazma **yasak**.

> **"utm'i anahtardan at, hepsi ayni girdiyi paylassin" YANLIS OLURDU:** sorgu
> dizesi HTML'e SIZIYOR (ana sayfada 19 `add-to-cart` baglantisi ve
> `_wp_http_referer` gecerli URL uzerine kuruluyor). Sonraki ziyaretci
> **baskasinin utm'ini** gorurdu.

> **`$uri` KULLANILAMAZ** — `try_files ... /index.php?$args` sonrasi `$uri`
> `/index.php` olur ve butun sayfalar tek girdide toplanir. Yol
> `$request_uri`'den regex ile kesilir (`$wpc_request_path`).

Kapsam **yalnizca fdartgallery** (`$wpc_qs_norm` map'i). `ref`, `source`,
`campaign` BILEREK izleme sayilmaz — ortaklik kodu olabilirler.

### Yapilmayanlar ve nedeni

- **`sourcebuster-js` + `wc-order-attribution` DOKUNULMADI** — ilk temasi giris
  sayfasinda kaydeder; yalnizca sepette yuklemek veriyi eksiltmez, **YANLIS**
  yapar. Kaldirilacaksa WooCommerce ayarindan ozellik komple kapatilir.
- **`akismet-frontend.js` DENENDI, VAZGECILDI** — Akismet altbilgideki FluentForm
  bultene bal kupu alani basiyor ve o alani bu JS dolduruyor.
- **Elementor deneysel ozellikleri** acilmadi (agir temayla render riski).
- **TTF → WOFF2** yapilmadi (gzip zaten %46 verdi; `@font-face` uretimi temanin isi).

---

## 4. Site haritasi ve adresler

**`wp-sitemap.xml` GECERLI XML donuyordu ama HTTP durumu 404 idi**; `robots.txt`
o adresi ilan ettigi icin arama motorlari haritayi reddeder.

**Kok sebep:** rewrite dogru calisiyor, ama `WP::query_posts()` istegi siradan
bir gonderi sorgusu gibi calistiriyor. Bu sitede **yayinlanmis `post` sayisi
SIFIR** → sorgu bos → `WP::handle_404()` `status_header(404)` basiyor.
`render_sitemaps()` sonra indeksi basip `exit` ediyor ama durumu 200'e geri
CEKMIYOR. Yani hata "harita uretilmiyor" degil, **bos blog yuzunden istek 404
damgasi yiyor**.

**Cozum:** `fdart-mu-plugins/fd-site-haritasi-durum.php` — cekirdegin tam bu is
icin sundugu `pre_handle_404` kancasi. Yalnizca `sitemap` sorgu degiskeni
doluyken `true` doner. Sonuc: indeks + 8 alt harita **200**, 329 adres; gercek
404'ler ve `/wp-sitemap-posts-post-1.xml` (0 yazi) **404 kaliyor**.

> **TUZAK: dev'deki ayni belirti BASKA BIR OLAYDI.** Dev'de de 404 goruluyordu
> ama orada **kasitli ve dogru**: cekirdek `sitemaps_enabled()` `blog_public`'e
> bakar, staging guard onu 0'a zorlar. Ayirt eden olcut **govde**: canlida
> `<sitemapindex`, dev'de `<html` (tema 404 sayfasi).

**Sayfa slug'i degisiminde WordPress 301 KURMAZ.** `wp_check_for_changed_slugs()`
hiyerarsik tiplerde (yani `page`) erken doner. Cozum:
`fdart-mu-plugins/fd-eski-adresler.php` — yalnizca istek 404'e dustugunde
calisir, sorgu dizesini korur. **Sira: once mu-plugin, sonra slug.**

---

## 5. Formlar ve e-posta

Formlar **FluentForm**'da; Contact Form 7 ve `mc4wp` kaldirildi.
Giden posta **Brevo API** (`BREVO_API_KEY`), DKIM/SPF/DMARC kurulu.
FluentSMTP tuzaklari **genel dosyanin 6d bolumunde**.

**Turnstile** giris, kayit, form ve WooCommerce akislarinda aktif — kaldirmadan
once 3. bolumdeki gizli giris formu uyarisini okuyun.

### Bildirimler kime gidiyor (03.09.2026)

Kullanici karari: **butun EKIP bildirimleri `info@fdsanatmerkezi.com`**.

| Nokta | Alici |
|---|---|
| WooCommerce `new_order`, `cancelled_order`, `failed_order` | `info@fdsanatmerkezi.com` |
| `admin_payment_gateway_enabled`, stok uyarisi | `info@fdsanatmerkezi.com` |
| WordPress `admin_email` | `info@fdsanatmerkezi.com` |
| FluentForm #5 "Ozel Siparis Formu" bildirimi | `info@fdsanatmerkezi.com` |

Yedek: `/var/backups/claude-2026-09-03-eposta/`.

### Form bildirimleri yeniden kuruldu (03.09.2026)

**Sorun:** kayit geliyordu, kimseye e-posta cikmiyordu. Kanit: log doneminde
(25.08 sonrasi) gelen 6 kayittan yalnizca form #5'inki e-posta uretti; #59 ve
#60 Brevo anahtarinin duzeldigi 30.08 17:00'den SONRA geldigi halde sessizdi —
yani sebep gonderim altyapisi degildi.

**Kok sebep:** `fluentform_form_meta`'da form #7, #8, #9'un `notifications`
kaydi **satir satir parcalanmisti** — tek JSON nesnesi yerine her alan ayri bir
satir (`name`, `sendTo`, `subject`… ve sonuncusu `false`). Form #6, #12, #15'te
ise `notifications` kaydi **hic yoktu**. FluentForm bu satirlari besleme olarak
okuyamiyor, sessizce hicbir sey gondermiyordu.

**Cozum:** parcalanmis 30 satir silindi, her forma **tek saglikli besleme**
kuruldu (calisan form #5 sablonu), alici `info@fdsanatmerkezi.com`.

| Form | Kayit | Durum |
|---|---|---|
| #5 Ozel Siparis | 7 | zaten saglikliydi, dokunulmadi |
| #6 Duvara Resim | 2 | besleme yoktu → kuruldu |
| #7 Resim Workshop | 7 | parcalanmisti → yeniden kuruldu |
| #8 Resim Kurs | 11 | parcalanmisti → yeniden kuruldu |
| #9 Iletisim kuralim | **27** | parcalanmisti → yeniden kuruldu |
| #12 Heykel Kurs | 2 | besleme yoktu → kuruldu |
| #15 Heykel Workshop | 3 | besleme yoktu → kuruldu |

> **`feed_trigger_event` BILEREK KONULMADI.** Form #5'in beslemesinde
> `payment_success` yaziyor ve yine de calisiyor: `EmailNotificationActions::notify()`
> bu alani YALNIZCA `$form->has_payment` ve sayfada `payment_method` alani
> varken dikkate alir. Odemesiz formlarda inert; birakmak ileride sessiz
> kirilma riskidir.

**Dogrulama — uretim yolu, sahte `wp_mail` degil.** Her form icin mevcut son
kayit alinip `ShortCodeParser::parse()` → `EmailNotification::notify()`
calistirildi (submission kancasinin yaptigi isin aynisi), konuya `[TEST]` onegi
konuldu. **7/7 SENT**, hepsi `info@fdsanatmerkezi.com`; konu satirindaki
`{inputs.your-name}` gercek kayitlardan dogru cozuldu.

Yedek: `/var/backups/claude-2026-09-03-ff-bildirim/notifications-oncesi.tsv`.

Form #2 "Subscription Form" bildirimi de **acildi** (kullanici karari) ve
form **altbilgiye kondu** (asagida). Konusu bozuktu — `{inputs.names}` diyordu
ama formda **yalnizca `email` alani var**; `Konu: {form_title} | {inputs.email}
- Yeni Abone` olarak duzeltildi. Form #3 yayinda degil.

### Hangi form hangi sayfada (03.09.2026, basilan HTML'den)

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

### Altbilgideki bulten formu (03.09.2026)

Altbilgi bir **Elementor sablonu**: `elementor_library` **#1041**
("Elementor Footer #194", tip `footer`). Widget alanlari (`footer-1`,
`prefooter`, telif altnotu) **BOS** — tema onlari kullanmiyor, aramayin.

Eklenen: uc sutunlu bolum ile telif satiri **ARASINA** tam genislikte bir
serit — baslik "Bültenimize abone olun" + `fluent-form-widget` (`form_list`
= `"2"`). Mevcut hicbir elemana dokunulmadi; container `_element_id` =
**`fd-bulten-seridi`**, geri almak o tek elemani silmek demek.

> **Veri yapisi bir seviye daha derin.** `_elementor_data`'nin koku
> `j[0]` (container) ve onun altinda TEK bir sarmalayici var; uc sutunlu
> bolum ile telif satiri `j[0]['elements'][0]['elements']` altinda. Ilk
> denemede `j[0]['elements']`'e yazilmak istendi, kuru calistirma
> "1 bolum var" deyip yakaladi.

Form metinleri Turkcelestirildi (`fluentform_forms.form_fields`):
placeholder `Your Email Address` → **`E-posta adresiniz`**, buton
`Subscribe` → **`Abone Ol`** (uc yerde: alan, ozel buton, `submitButton`).

**Dogrulandi:** dev'de kuruldu ve basilan HTML'den denetlendi, sonra canliya
alindi. Canli ile dev **birebir**: `fluentform_2` 5, `Bültenimize` 1,
`<img>` 31, `menu-item` 123, `<form>` 4, `elementor-widget` 172. Butun
sayfalar 200, PHP Fatal 0. Turnstile formun ICINDE (`cf-turnstile` 4,
gecerli `data-sitekey`) — yani koruma calisiyor.

> **Uctan uca gonderim testi KABUKTAN YAPILAMAZ:** `cfturnstile_fluent=1`,
> yani FluentForm gonderimleri Turnstile istiyor ve script'in token'i yok.
> Zinciri kapatmanin tek yolu tarayicidan gercek bir gonderim.

**ZINCIR KAPANDI — kullanici tarayicidan abone oldu (03.09.2026 09:00:44):**

```
kayit#61  2026-09-03 09:00:44  form #2  eposta=blgnklc@gmail.com
          kaynak: https://fdartgallery.com/          (altbilgi)
log #46   2026-09-03 09:00:44  SENT  to=info@fdsanatmerkezi.com
          konu: "Konu: Subscription Form | blgnklc@gmail.com - Yeni Abone"
```

**Ayni saniye** — kuyruk/gecikme yok. Bu tek gonderim su dordunu birden
kanitladi: altbilgideki form gorunuyor ve gonderilebiliyor, Turnstile gercek
tarayicida engel olmuyor, kayit DB'ye dusuyor, bildirim aliciya gidiyor.
Konudaki `{inputs.email}` de dogru cozuldu (eski `{inputs.names}` bos
basacakti).

Test kaydi sonradan silindi; bildirim log satiri (#46) **kanit olarak
birakildi**.

Yedek: `/var/backups/claude-2026-09-03-altbilgi/` (sablon #1041 ve form #2
alanlari, canli + dev, degisiklik oncesi).

> **FluentForm kaydini HAM SQL ile SILMEYIN.** Kayit dort tabloya yayilir.
> Test aboneligi silinirken olculdu: `submissions` 1, `entry_details` 1,
> **`submission_meta` 2**, **`logs` 1**. Yalnizca `submissions`'tan silmek
> uc yetim satir birakirdi. Dogru yol eklentinin kendi servisidir —
> `SubmissionService::deleteEntries($ids, $formId)` → `Submission::remove()`;
> dosya eklerini de temizler ve `fluentform/after_deleting_submissions`
> kancasini calistirir. Silme sonrasi dordu de 0 dogrulandi.
> Yedek: `/var/backups/claude-2026-09-03-abone-kaydi/`.

> **Formu DB'den aramaya calismayin.** Elementor widget'i `fluent-form-widget`
> ve form id'sini kacisli JSON icinde tutuyor; `"formId"` / `"form_id"` regex'i
> **bos doner** (uc kez denendi, uc kez yanlis sonuc verdi). Iki guvenilir yol:
> basilan HTML'de `id="fluentform_N"` aramak, ya da
> `fluentform_submissions.source_url` sutununu gruplamak.

**BILEREK DOKUNULMAYANLAR:**

- **Musteriye giden WooCommerce e-postalari** — alicisi musterinin kendisi;
  yonlendirmek siparis onayini musteriden calar.
- **`woocommerce_paypal_settings` → `receiver_email`** (`swordbros@gmail.com`)
  — bu bir **odeme hesabi**, bildirim alicisi degil. Degistirmek odemeyi kirar.
- **`woocommerce_email_from_address`** — GONDEREN alani, alici degil.
- **FluentForm `sendTo.type = field`** olan bildirimler — alici basvuranin
  kendisidir (otomatik yanit).

Ayrica yarim kalmis bir `new_admin_email` degisikligi (`blgnklc@gmail.com`,
`adminhash` ile onay bekliyordu) iptal edildi.

> **GONDEREN ayardaki adres DEGILDIR.** WooCommerce ayari
> `info@fdsanatmerkezi.com` diyor ama FluentSMTP gonderirken
> `info@fdartgallery.com`'a ceviriyor — ve bu DOGRU: SPF/DKIM/DMARC o alan
> adina kurulu. Ayardaki adresten gitseydi Brevo dogrulanmamis gonderen diye
> reddederdi. Panelde gorduğunuz adres yaniltici, log'daki `from` gercek.

> **TUZAK: `WC_Email_New_Order::trigger()` ayni siparis icin IKINCI KEZ
> gondermez.** Siparise `_new_order_email_sent` meta'si koyar. Test ederken
> ilk tetikleme calisir, ikincisi sessizce hicbir sey yapmaz ve "e-posta
> bozuk" sanilir. Baska bir siparisle test edin; bitince bayragi
> `false`'a geri cekin.

**Test yontemi:** sahte `wp_mail` atmayin — gercek yolu tetikleyin
(`WC()->mailer()->get_emails()['WC_Email_New_Order']->trigger($id, $order)`),
konuya `[TEST]` onegi koyun ve sonucu FluentSMTP log tablosundan
(`..._fsmpt_email_logs`) okuyun. 03.09.2026: siparis #3508 ile denendi,
**SENT**, `to = info@fdsanatmerkezi.com`.

---

## 6. Bu siteye ozgu diger notlar

- **`mc4wp` kaldirildi** ve `wp rewrite flush` bir kez yanlislikla
  `--skip-plugins` ile calistirildi. Hasar, dokunulmamis canli ile dev'in
  **saklanan** `rewrite_rules` anahtarlari diff'lenerek denetlendi: fark tam
  20 kural, hepsi kaldirilan eklentinin kendi `mc4wp-form/...` kurallariydi.
  Yine de dogru bicimiyle tekrar flush edildi. (Kural genel dosyada 6f.)
- Blog altyapisi burada ama **icerik chestnyznak'in**: `deploy/blog/` araclari
  ve haftalik Routine (`trig_01Q2abbcd19d1CQem3fs5Z7M`, Sali 06:00 UTC)
  chestnyznak blogunu besler. Uslup ve kuyruk: `deploy/blog/README.md`,
  `deploy/blog/BACKLOG.md`.

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

**`deploy/scripts/` tam envanteri.** Genel dosya `deploy/scripts`'ten hic soz
etmiyor — burasi bu scriptlerin TEK listesi, eksik birakmayin.

| Grup | Dosyalar |
|---|---|
| Site yasam dongusu | `add-site.sh`, `clone-site.sh`, `deploy-site.sh` |
| **Yedekleme** | `backup-site.sh` (gece 04:15 → R2, `/etc/cron.d/backup-<slug>`), `setup-backup.sh` |
| Sunucu kurulum / sertlestirme | `setup-server.sh`, `setup-redis.sh`, `setup-fail2ban.sh`, `setup-named-tunnel.sh`, `harden-ssh.sh`, `harden-mysql.sh` |
| Cloudflare / DNS | `cloudflare-dns.sh`, `update-cloudflare-ips.sh` |
| Bakim | `purge-cache.sh` (nginx + istege bagli Cloudflare), `convert-webp.sh` |
| Oturum erisimi | `claude-access.sh` (gecici anahtar + quick tunnel; adlandirilmis tunel calisirken GEREKMEZ) |
| wp-config yamalari | `wpconfig-redis.py`, `wpconfig-bildirim-sabitleri.py` |
| Tek seferlik / icerik | `demo-icerik-temizle.php`, `eposta-alicilari.php`, `migrate-czlead-to-elementor.php` (son ikisi chestnyznak) |

> chestnyznak araclarini **calistirmadan once** o sitenin kurallarini okuyun
> (`chestnyznakuk/CLAUDE.md`): uc dil kurali, Elementor kurali, icerik kapatma
> yasagi. Aracin burada durmasi kurallarin burada oldugu anlamina gelmez.

---

## 8. Calisma kurallari

Ortak kurallarin tamami **genel dosyanin 7. bolumunde**. Bu depoya ozgu olan:

- Tum gelistirme `claude/ovhcloud-vps-multisite-0eb3xj` dalinda; izinsiz baska
  dala push yok, izinsiz PR yok.
- **Bu dalda birden fazla oturum calisiyor — push etmeden once `git fetch
  origin claude/ovhcloud-vps-multisite-0eb3xj` + `git rebase`.** Gelen
  commit'leri once **okuyun**: 02.09.2026'da iki push reddedildi, ikisinde de
  araya giren commit CLAUDE.md'ye deger bir sey eklemisti (mu-plugin envanteri,
  duzeltilmis eklenti sayimi) ve korunmasi gerekti. **Force push YOK.**
- **Sirlar asla commit edilmez**: token, DB sifresi, private key, `wp-config.php`.
- **Once dev, sonra canli.** Canliya alirken dev'in HTML'i **kopyalanmaz** —
  ayni donusum canlinin kendi icerigi uzerinde calistirilir, cikti denetlenir.
- Canliyi etkileyen her islemden **once yedek**, mumkunse `dry`, sonra
  **kullanici onayi**.
- `deploy-site.sh` `rsync --delete` kullanir → depo eksikken calistirmayin.
- **Degisiklikten sonra dogrula: HTTP kodu yetmez** (genel dosya 6a).
- **Bu dosyayi fdartgallery'e ozel tut.** Ortak bir tuzak buldugunda genel
  depoya yaz; chestnyznak'a ait bir sey ogrendiginde `chestnyznakuk`'a.
