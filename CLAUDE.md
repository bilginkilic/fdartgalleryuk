# CLAUDE.md — OVHcloud VPS / cok siteli WordPress runbook

Bu dosya projeyi devralan her oturum icin kalici hafizadir. Kisa tutulur:
**su an ne oldugu**, **nasil erisilecegi** ve **hangi tuzaklara dusuldugu**.
Ayrintili gecmis git gecmisinde duruyor (`git log -p -- CLAUDE.md`).

Son sadelestirme: 01.09.2026 (2237 → ~380 satir).

---

## 0. SU AN NE KALDI

Yayindaki siteler: **fdartgallery.com**, **chestnyznak.com.tr** (+ iki dev ortami).
Sunucu kurulumu, performans, guvenlik ve yedekleme bitti.

### Kullanicidan bekleyenler

| # | Is | Neden bekliyor |
|---|---|---|
| 1 | **chestnyznak giden e-posta** — `info@chestnyznak.com.tr` timeweb posta kutusu parolasi | Tasimada bozuldu, kurtarilamiyor. `TIMEWEB_MAIL_PASSWORD` env'e konursa kurulum hazir: `scratchpad/set-chestnyznak-smtp.sh` (bkz. 6a) |
| 2 | **Kalan 4 site** icin dosya arsivi + `.sql` + **orijinal `wp-config.php`** | fdsanatmerkezi, chemiartclick.uk apex, davetevet, byhio, wedreply |
| 3 | **Kalici SSH anahtari** — public kismi bize, private kismi `VPS_SSH_PRIVATE_KEY` env'ine | Iki isi acar: haftalik blog Routine'inin kendi basina yayinlamasi **ve** SSH parola girisinin kapatilmasi (6c) |
| 4 | **chestnyznak Rusya'dan acilmiyor** — gri buluta cekildi, yine acilmiyor. Geriye tek degisken **alan adi** | `chestnyznak-test.chemiartclick.uk` testinin sonucu bekleniyor (6d) |
| 5 | **`webmail.chestnyznak.com.tr` bozuk** | Bizim IP'ye bakiyor, sunucuda vhost yok → magazaya 301. Timeweb'e yonlendirilmeli |

### Karar bekleyenler

- **Cloudflare APO** (~5 $/ay): HTML'i edge'de onbellekler. Turkiye'deki ziyaretci
  icin en buyuk tek kazanc; su an `cf-cache-status: DYNAMIC`.
- **fdartgallery eklenti sadelestirme**: iki form + iki mailchimp eklentisi;
  `elementor-pro` + `pro-elements` cakismasi.
- **Blogun en eski 4 yazisi**: slug'lari hala Ingilizce demo slug'i. Duzeltmek
  adresi kirar → 301 gerekir. Ayrinti `deploy/blog/BACKLOG.md`.
- **chestnyznak kalan 14 JS enjeksiyonu** Elementor'a tasinsin mi (6f).
- **PTR** posta alan adina cevrilsin mi — oncelik dusuk, mevcut PTR calisiyor.

---

## 1. Altyapi

### VPS

| Alan | Deger |
|---|---|
| Hostname | `vps-913eb1fb.vps.ovh.net` |
| IPv4 / IPv6 | `57.129.128.118` (gw `57.129.128.1`) / `2001:41d0:801:2000::7e80` |
| Model | VPS-2 2027 — 4 vCore / 8 GB RAM / 75 GB SSD |
| OS / PHP | Ubuntu 26.04 / **PHP 8.5**-FPM |
| Bolge | `os-uk2` — Londra |

PTR **calisiyor** ve ileri-dogrulamali: `57.129.128.118 <-> vps-913eb1fb.vps.ovh.net`.

### Cloudflare zone'lari

**Chemiartclick hesabi** (`cloudflare_api_token`): fdartgallery.com
`fa3f51825d634f23741ff5fbf2ae8b1a`, chemiartclick.uk
`56fcc0694b5c39e44fd5ada387e72da7`, ayrica fdsanatmerkezi.com, davetevet.com,
byhio.com, wedreply.co.uk.

**SWORD BROS hesabi** (`CF_SWORDBROS_API_TOKEN`, **yazma yetkisi VAR**):
chestnyznak.com.tr `858396f27bcc338ad737fc52cf2d8a9f` + 12 zone daha.

### DNS durumu

| Kayit | Deger | Proxy |
|---|---|---|
| `fdartgallery.com`, `www`, `dev` | 57.129.128.118 | **turuncu** |
| `chestnyznak.com.tr`, `www` | 57.129.128.118 | **gri** (Rusya erisimi icin) |
| `dev.chestnyznak.com.tr` | 57.129.128.118 | gri |
| `chestnyznak.chemiartclick.uk` | 57.129.128.118 | turuncu |

fdartgallery mail kayitlari (Brevo DKIM, DMARC, SPF, Email Routing MX)
**degistirilmez**. chestnyznak mail kayitlari timeweb'de, **dokunulmaz**.

`fdartgallery.com` SSL modu **Full (strict)** — `Flexible` yapmayin, dongu olur.

---

## 2. Kimlik bilgileri

Degerler **ortam degiskenlerinde**; asla repoya yazilmaz, log'a basilmaz.

| Degisken | Kullanim |
|---|---|
| `OVH_ENDPOINT` / `_APPLICATION_KEY` / `_APPLICATION_SECRET` / `_CONSUMER_KEY` | OVH API (`ovh-ca` → `https://ca.api.ovh.com/1.0`) |
| `cloudflare_api_token` / `cloudflare_account_id` | Cloudflare (chemiartclick hesabi) |
| `cloudflare_access_keyid` / `cloudflare_access_key` | R2 / S3 anahtarlari |
| `CF_SWORDBROS_API_TOKEN` / `CF_SWORDBROS_ACCOUNT_ID` | Cloudflare (SWORD BROS hesabi) |
| `CLOUDFLARE_CF_Access_Client_Id` / `_Secret` | SSH tuneli Access service token |
| `BREVO_API_KEY` | fdartgallery giden posta |

**OVH consumer key yalnizca `/vps/*` kapsiyor.** `GET /me`, `/ip/*` → 403.
Reverse DNS API'den **degistirilemez** (`PUT /vps/{n}/ips/{ip}` → 403 not implemented);
OVH Manager'dan yapilir.

**Cloudflare notu:** `GET /user/tokens/verify` hesap kapsamli token'lar icin
`Invalid API Token` doner — normaldir. `GET /zones` ile test edin.

---

## 3. Bu ortamin sinirlari + sunucuya erisim

**Ham TCP/22 bu oturumdan GECMIYOR** — engel port bazli, host bazli degil
(github.com:22 de kapali, :443 aciliyor). Ikinci bir ortamda da dogrulandi.
Ag politikasini genisletmek host listesini genisletir, TCP/22'yi acmaz.

**Cozum: Cloudflare tuneli (HTTPS uzerinden SSH).** Sunucuda `cloudflared`
servisi **active**; adlandirilmis tunel `ovh-vps`, adres `ssh.fdartgallery.com`,
onunde Cloudflare Access service token var.

```sh
curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 \
     -o /tmp/cloudflared && chmod +x /tmp/cloudflared
export TUNNEL_SERVICE_TOKEN_ID="$CLOUDFLARE_CF_Access_Client_Id"
export TUNNEL_SERVICE_TOKEN_SECRET="$CLOUDFLARE_CF_Access_Client_Secret"
[ -n "$VPS_SSH_PRIVATE_KEY" ] && { mkdir -p ~/.ssh; printf '%s\n' "$VPS_SSH_PRIVATE_KEY" > ~/.ssh/id_ed25519; chmod 600 ~/.ssh/id_ed25519; }
ssh -o "ProxyCommand=/tmp/cloudflared access ssh --hostname ssh.fdartgallery.com" \
    -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@vps
```

Sunucuda ayrica bu oturumun public key'i `authorized_keys`'te olmali.
**Yeni oturumlarda yoktur** — kalici anahtar icin 0. bolumdeki 3. madde.

**Diger sinirlar:**
- GitHub reposu sunucudan erisilemiyor (ozel repo) → `git pull` calismaz;
  dosyalar tunel uzerinden `rsync` / `cat >` ile aktarilir.
- Sunucunun 443'u de bu oturuma kapali; egress gateway 403 doner.
- KVM konsolunda klavye eslemesi bozuk (`:`→`;`, `|`→`\`); `sudo loadkeys us` duzeltir.

---

## 4. Siteler

| Adres | Kok dizin | DB | Not |
|---|---|---|---|
| `fdartgallery.com` | `/var/www/fdartgallery.com` | `wp_fdartgallery_com` | onek `fdArt0Og_` |
| `dev.fdartgallery.com` | `/var/www/dev.fdartgallery.com` | — | staging |
| `chestnyznak.com.tr` | `/var/www/chestnyznak.chemiartclick.uk` | `wp_chestnyznak_chemiartclic` | onek `ChestZna_` |
| `dev.chestnyznak.com.tr` | `/var/www/dev.chestnyznak.chemiartclick.uk` | `wp_dev_chestnyznak_chemiart` | staging |

> **DIKKAT:** chestnyznak sitelerinde **dizin adi ile alan adi FARKLI**.
> Dizin, sistem kullanicisi, FPM havuzu, socket, DB ve log adlari hala eski
> `*.chemiartclick.uk` adina gore. Yeniden adlandirmak `open_basedir`'i, cron'u
> ve yedek isini birden kirar — bilerek dokunulmadi.

### Site basina duzen

| Bilesen | Ornek |
|---|---|
| Kok | `/var/www/<dizin>/public` |
| Kullanici | `web_<slug>` — dizinden `stat -c %U` ile okuyun, tahmin etmeyin |
| FPM havuzu | `/etc/php/8.5/fpm/pool.d/<slug>.conf` |
| vhost | `/etc/nginx/sites-available/<ad>.conf` |
| DB sifresi | `/var/www/<dizin>/db-credentials.txt` (mod 600) |
| Loglar | `/var/log/nginx/<slug>.{access,error}.log`, `/var/log/php/<slug>.error.log` |
| WP cron | `/etc/cron.d/wp-cron-<slug>` (10 dk, CLI) |

Izolasyon: ayri unix kullanicisi + `open_basedir`.

### Staging korumasi

`fd-staging-guard.php` (mu-plugin): giden e-postayi PHPMailer seviyesinde keser,
`blog_public`'i 0'a zorlar, `robots.txt`'yi `Disallow: /` yapar, `X-Robots-Tag`
basar, panelde ve sayfada "DENEME ORTAMI" bandi gosterir.

Ayrica nginx'te `conf.d/02-staging-hosts.conf` icindeki `$robots_tag` map'i dev
hostlarina `X-Robots-Tag: noindex` basar — **statik dosyalar dahil**. Yeni dev
sitesi eklerken o map'e bir satir eklenir.

> DB'deki `blog_public` degeri **1 gorunur**, bu normaldir — guard calisma
> aninda filtreyle 0'a ceker. Olcut sayfadaki `noindex, nofollow` etiketidir.

---

## 5. Yapilmis isler (ozet)

| Alan | Durum |
|---|---|
| Sunucu | nginx + PHP 8.5-FPM + MariaDB + Redis + certbot + ufw + fail2ban + wp-cli |
| Sayfa onbellegi | nginx `fastcgi_cache`; ana sayfa 0.90 sn → **0.006 sn** |
| WebP | fdartgallery 6394 dosya, chestnyznak 5820; orijinaller korunuyor, gece cron |
| Redis | site basina izole (db indeksi + anahtar oneki) |
| Yedekleme | Cloudflare R2, gece 04:15, 30 gun; uploads artimli (`copy`, `sync` DEGIL) |
| Sertifikalar | Let's Encrypt, otomatik yenileme |
| MariaDB | `root@localhost` parolasizdi → `unix_socket` + yedek parola |
| fail2ban | 3 jail; hem Cloudflare edge hem yerel nftables bani |
| Kalici baglantilar | `/%postname%/`; urun sayfalari artik onbellege giriyor |
| E-posta (fdartgallery) | Brevo API + DKIM/SPF/DMARC; MX = Cloudflare Email Routing + Worker |
| Turnstile | fdartgallery giris/kayit/form/WooCommerce |
| Blog | `deploy/blog/` araclari + haftalik Routine (`trig_01Q2abbcd19d1CQem3fs5Z7M`, Sali 06:00 UTC) |
| chestnyznak | Elementor gecisi + basvuru kaydi (6f, 6g) |

Komutlar ve olcumler icin `deploy/` altindaki script'ler ve git gecmisi.

---

## 6. Tuzaklar — hepsi yasandi, tekrarlanmasin

### 6a. E-posta: tasima sirlari bozuyor

FluentSMTP kayitli parolayi `LOGGED_IN_KEY`+`LOGGED_IN_SALT` ile sifreler.
Yeni `wp-config.php` uretilince cozulemez, saglayici 401 doner.
**fdartgallery ve chestnyznak'ta bu yasandi.**

Onlem — tasimadan once wp-config'e sabit anahtar (dort sitede yapildi):
```php
define( 'FLUENTMAIL_ENCRYPT_KEY',  '<sabit>' );
define( 'FLUENTMAIL_ENCRYPT_SALT', '<sabit>' );
```

**Her tasimadan sonra e-posta testi zorunlu:**
```sh
sudo -u <user> wp --path=<kok> eval \
 'add_action("wp_mail_failed",function($e){echo $e->get_error_message();});
  var_dump(wp_mail(get_option("admin_email"),"t","t"));'
```

Uc ek tuzak:
1. **`wp_mail()` TRUE donmesi teslimat demek DEGIL.** Brevo istegi kabul edip
   kuyrukta reddedebilir. `GET /v3/smtp/statistics/events` ile dogrulayin.
2. **`sudo -u <user> -E wp` ortam degiskeni TASIMAZ** (sudoers `env_reset`).
   Sir gecici bir PHP dosyasina yazilip `wp eval-file` ile calistirilir.
3. **Ham `update_option('fluentmail-settings',...)` KULLANMAYIN.** Dogru API
   `fluentMailSetSettings()` — duz metin alir, sifrelemeyi kendi yapar.

`info@chestnyznak.com.tr` **timeweb**'de barinir (MX `mx1/mx2.timeweb.ru`,
SMTP `smtp.timeweb.ru:465/587` acik, DKIM timeweb imzali). **Gelen posta
calisiyor**; giden posta yalnizca parola eksikligi yuzunden durdu.

### 6b. nginx

- **`location = /robots.txt` bloguna `try_files` SART.** Yoksa nginx 404 doner,
  istek WordPress'e ulasmaz ve Yoast'in `Sitemap:` satiri kanonik adresinde
  bulunmaz. Iki sitede de bu yuzden 404 aliniyordu.
- **Kendi `add_header`'i olan bir location ust seviyedekileri MIRAS ALMAZ.**
  Staging noindex basligi bu yuzden statik dosya blogunda TEKRAR edilir.
- **`$realip_remote_addr` kullanin, `$remote_addr` DEGIL.** real_ip modulu
  `$remote_addr`'i ziyaretcinin IP'siyle degistirir; gercek TCP eslenigini
  yalnizca `$realip_remote_addr` tutar (Cloudflare ayrimi buna dayanir).
- **Vhost'ta ad degistirirken YALNIZCA `server_name` satirini degistirin.**
  `sed` ile toplu degisim sertifika yollarini ve kok dizini de bozdu.
- **80 portundaki blokta server seviyesinde `return` KOYMAYIN.** Rewrite
  asamasinda calisip location eslesmesini kisa devre yapar; ACME dogrulamasi
  404 alir. Yonlendirme `location / { return 301 ... }` icine konur.
- **Sertifika icin `certbot certonly --webroot`** tercih edin, `--nginx` degil:
  nginx eklentisi vhost'u yeniden yazar.
- **`update-cloudflare-ips.sh` iki dosyayi AYNI listeden uretir** (realip
  snippet + `$cf_peer` geo blogu). Ayrisirlarsa mesru ziyaretciler 444 yer.

### 6c. SSH

`sshd_config.d/*.conf` alfabetik yuklenir ve **her anahtar icin ILK deger kazanir**.
Imajin `60-cloudimg-settings.conf` dosyasindaki `PasswordAuthentication no`,
cloud-init'in `50-...conf` dosyasindaki `yes` yuzunden hic devreye girmemis.
Bizim dosyamizin adi bu yuzden **`00-hardening.conf`**.

Sertlestirme yapildi (MaxAuthTries 3, LoginGraceTime 30, forwarding kapali,
modern cipher/MAC/Kex). **Parola girisi hala acik** — kapatmak icin kullanicinin
public key'i gerekiyor. `harden-ssh.sh --disable-password` kalici anahtar yoksa
**reddeder** (gecici `claude-session-*` anahtarlarini saymaz — kilitlenme korumasi).

Acil durum: OVH Manager → VPS → KVM konsolu.

### 6d. Rusya erisimi (chestnyznak)

**Kanitlandi:** engel Cloudflare IP araliklarina. VPN kapali, ayni telefon:
`chestnyznak.com.tr` (turuncuyken) → `ERR_TIMED_OUT`; ayni sunucudaki
`origin.chemiartclick.uk` (gri) → **acildi**. DNS temiz, TCP hic kurulmuyor.

Gri buluta cekildi (TTL 120, geri alma yedegi `scratchpad/chestnyznak-A-before.json`).
Origin'i acmadan once nginx'te kaynak ayrimi yapildi: `cloudflare-only.conf`
fdartgallery ve dev vhost'larinda var, chestnyznak'ta **YOK**.

**AMA gri olduktan sonra da acilmiyor.** Geriye tek degisken alan adi.
`chestnyznak-test.chemiartclick.uk` testi bunun icin kuruldu (sonuc bekleniyor):
- 2 acilmaz + 1 acilirsa → engel **isimde**; barindirma degisikligi kurtarmaz.
- 2 acilirsa → engel yalnizca `chestnyznak.com.tr`'ye ozel; yeni alan adi cozer.

Gecici test kaynaklari (**is bitince silinecek**): `origin.chemiartclick.uk` ve
`chestnyznak-test.chemiartclick.uk` A kayitlari,
`/etc/nginx/sites-available/origin-test.conf`, `/var/www/origin-test`,
`certbot delete --cert-name origin.chemiartclick.uk`.

### 6e. Test tuzaklari — yanlis sonuca goturur

- **Konteynerden `curl --resolve` YOK SAYILIR.** `HTTPS_PROXY` tanimli; curl
  proxy'ye `CONNECT <host>:443` der, adi proxy cozer. Istek yine Cloudflare'den
  gider ve yaniltici 200 doner. Dogru test **sunucudan**:
  `curl --interface 57.129.128.118 --resolve <host>:443:57.129.128.118 ...`
  veya yerel icin `--resolve <host>:443:127.0.0.1`.
- **Konteynerden `openssl s_client` GECERSIZ.** Egress proxy kendi sertifikasini
  sunar (`CN=Egress Gateway SDS Issuing CA`). Sunucudan bakin.
- **"Sayfa 200 donuyor" gorsel dogrulama DEGILDIR.** revslider kapatildiginda
  ana sayfa ustte ham `[rev_slider ...]` metni gosteriyor ve yine 200 donuyordu.
- **Ekran goruntusuyle gelen kanitta durum cubugunu okuyun** (VPN, roaming, Wi-Fi).
  Bir "basarili" kare VPN acikken cekilmisti; iki degisken degistigi icin kanit sayilmadi.
- **Eklenti kullaniliyor mu?** `post_content` + `postmeta` aramak YANILTIR;
  `_elementor_data`, tema widget alanlari ve `cpt_layouts` da taranmali.
  revslider "kullanilmiyor" sanilip kapatildi, **canli ana sayfa 2 gun bozuk kaldi**.
- Sayfa JS'i test edilecekse: sunucudan HTML indirilip yerel Chromium'da
  calistirilabilir (`/opt/pw-browsers/chromium-1194/chrome-linux/chrome`,
  harici istekler kapali). Karsilastirma icin **canli sayfayi da ayni testten
  gecirin** — yoksa cevrimdisi artifakti gercek ariza sanilir.

### 6f. chestnyznak — Elementor duzenlenebilirligi

> **KURAL (kullanici karari):** siteye eklenen her icerik **Elementor ile
> duzenlenebilir** olmalidir. JS enjeksiyonu, Custom HTML widget'i veya tema
> widget alani KULLANILMAZ. Once **dev**'de yapilir, onaydan sonra canliya alinir.

**Bulunan durum:** sitenin gorunur bolumlerinin buyuk kismi, tema widget
alanindaki tek bir 57 KB'lik Custom HTML widget'indan **16 JS enjeksiyonuyla**
uretiliyordu. Elementor bunlari goremez; editorde o alan bos gorunur.

**Yapilan (01.09.2026, dev → canli):** `CZLEAD` bolgesi (style + 5 template +
scriptler) widget'tan cikarilip ana sayfanin Elementor icerigine tasindi.
Ust blok artik gercek bir section (`_element_id=cz-lead`, 2 kolon 52/48):
sol kolonda **6 text-editor widget'i** (eyebrow, H1, alt metin, rozetler, guven
satiri, dipnot), sag kolonda form karti tek HTML widget. Olu `trx_widget_slider`
kaldirildi. Ozgun `.cz-lead-*` siniflari markup'in icinde kaldigi icin CSS
degismedi; Elementor sarmalayicilari icin kucuk bir kopru CSS'i eklendi.

Script: `deploy/scripts/migrate-czlead-to-elementor.php` (`dry` destegi var).
Blogun arsivi: `deploy/wordpress/cz-lead-block.html`.

**Elementor editoru 500 veriyordu** — editorun JS yapilandirmasi tek bir
`json_encode` cagrisinda 256M'yi asiyordu (bu sitede cikti 5.4 MB). Dort sitede
de duzeltildi:
- FPM havuzu `memory_limit` 256M → **512M**
- wp-config `WP_MAX_MEMORY_LIMIT` = **512M**, `WP_MEMORY_LIMIT` = 256M

**Ikisi de gerekli:** `php_admin_value` HARD tavandir (`ini_set` yukseltemez);
WordPress ise panelde bellegi kendi `WP_MAX_MEMORY_LIMIT` degerine ceker.
512M bir tavandir, rezervasyon degil; sunucu takasa duserse `pm.max_children`
(havuz basina 12) dusurulur.

**Kalan 14 enjeksiyon** hala widget'ta: `cz-hero-block`, `cz-header`, `cz-banner`,
`cz-news`, `cz-marquee`, `cz-home`, `cz-wa`, `cz-contact`, `cz-shop`, `cz-cookie`,
`cz-audit`, `cz-relabel`, `cz-fixlinks`, `cz-btn-brand`, `cz-urun-talep`.
Sirayla tasinmali; her biri ayri dogrulama ister.

#### wp-cli ile Elementor verisine yazarken

**`wp --user=<admin>` SART.** Oturum acmis kullanici yoksa
`current_user_can('unfiltered_html')` false doner ve WordPress `<style>`,
`<script>`, `<template>` etiketlerini **sessizce siler** — hata vermez.
Ilk denemede CSS sayfaya ciplak metin olarak basildi.

Yazan her script **kayittan sonra kendini dogrulamali** ve etiketler yoksa
degisikligi geri almali. Ayrica `wp_json_encode` `/` karakterini `\/` yapar —
kontrol dizesinde egik cizgi kullanmayin.

#### Adres degisikliginden sonra onbellek

Ana sayfa bir sure `301 → kendisi` donuyordu. Uygulama hatasi degildi: nginx
sayfa onbellegine, rename sirasinda uretilmis bir WordPress kanonik yonlendirmesi
yazilmisti. **Ayirt edici isaret: `HEAD` 200, `GET` 301.** Adres degistiren her
islemden sonra onbellek temizlenip GET ile tekrar test edilir.

### 6g. Basvuru formu (chestnyznak ana sayfa)

**Onceki hali uc kusurluydu:** form dogrudan tarayicidan bir Google Apps Script'e
POST ediyordu; `.catch(show)` ve `setTimeout(show,2500)` yuzunden relay coksa bile
ziyaretciye **basari ekrani** cikiyordu; basvuru hicbir yere kaydedilmiyordu;
`chat_id` sayfa kaynaginda **aciktı**.

**Yeni akis** — `deploy/wordpress/lead-mu-plugins/fd-lead-capture.php`
(yalnizca chestnyznak sitelerine kurulur):

| Adim | |
|---|---|
| Uc | `POST /wp-json/fd-lead/v1/submit` |
| 1 | **Kalici kayit** — `fd_lead` icerik tipi, panelde "Basvurular" |
| 2 | E-posta bildirimi (`Reply-To` = basvuran) |
| 3 | Telegram aktarimi — **sunucu tarafinda**, `chat_id` wp-config'de |

Ziyaretciye "basarili" demenin **tek olcutu veritabani kaydidir**. Uc `stored`
donmezse form acik kalir, hata kutusu ve WhatsApp yedegi cikar.
Spam: gizli bal kupu alani + IP basina saatte 8 kayit.

wp-config sabitleri (repoya yazilmaz): `FD_LEAD_RELAY_URL`, `FD_LEAD_CHAT_ID`,
`FD_LEAD_NOTIFY_EMAIL`. **Dev'de `FD_LEAD_CHAT_ID` BOS** — dev denemeleri canli
Telegram grubuna dusmez. Ayri bir dev grubu acilirsa tek satir degisir.

> **Google Apps Script tuzagi:** basarili POST'a **302** doner ve yaniti
> `script.googleusercontent.com`'a yonlendirir; o adres yalnizca GET aldigi icin
> yonlendirme takip edilirse 405/400 gorunur ve mesaj gitmis olmasina ragmen
> "basarisiz" sanilir. Bu yuzden `redirection => 0` ve **302 basari sayilir**.

### 6h. Diger

- **`rsync --exclude 'database/'` her seviyedeki `database` dizinini atlar.**
  Elementor Pro'nun `submissions/database` klasoru eksik kalip site fatal verdi.
  Kaliplar `/database/` seklinde koke sabitlenir.
- **Yedegin tablo oneki `wp_` olmayabilir.** `deploy-site.sh` onegi DB'den okur.
- **Cloudflare Universal SSL yalnizca TEK seviye alt alan adi kapsar.**
  `dev.chestnyznak.chemiartclick.uk` iki seviye oldugu icin TLS handshake hatasi
  verdi. **Kural: yeni alt alan adlari tek seviye olsun.**
- **`clone-site.sh` sonrasi `chown`** hemen rsync'in ardindan gelir; yoksa hedef
  kullanici `wp-config.php`'yi okuyamaz ve tum wp-cli adimlari duser.
- **Klonda `object-cache.php` ve `advanced-cache.php` drop-in'leri silinir.**
  Tanimsiz drop-in varsayilan veritabanina baglanip **eski** `siteurl`'u servis etti.
- **SPF tek kayit olmak zorunda.** Ikinci bir SPF kaydi ikisini de gecersiz kilar
  (`permerror`). Email Routing eklenirken mevcut kayit **guncellendi**, yenisi eklenmedi.
- **Cloudflare Email Routing bir adresi tek hedefe yonlendirir.** Coklu dagitim
  icin Email Worker gerekti (`deploy/cloudflare/email-worker.js`).
- **`wp-super-cache` aktif ama etkisiz** (drop-in yok, `WP_CACHE` tanimsiz).
  Etkinlestirilirse nginx sayfa onbellegiyle cakisir — **aktif etmeyin**.
- **`elementor_canvas` sablonu bos tuvaldir** (menu/logo/footer basilmaz).
  `kod-sorgulama` bu yuzden siteden kopuk aciliyordu → `elementor_header_footer`
  yapildi. Reklam acilis sayfalari (`urun-talep-formu`, `lp-iletisim-formu`,
  `datamatrix-kod-etiket`) **bilerek** canvas — onlara dokunmayin.
- **WebP: Accept pazarligi (Vary) kullanilmadi.** Zone Free planda; Free'de
  `Vary: Accept` donen gorseller edge'de BYPASS olur. Ayri `.webp` URL yontemi
  secildi. Bedeli: cok eski tarayicilar gorselleri goremez (~%2-3).

---

## 7. Calisma kurallari

- Tum gelistirme `claude/ovhcloud-vps-multisite-0eb3xj` dalinda; izinsiz baska
  dala push yok, izinsiz PR yok.
- **Sirlar asla commit edilmez**: token, DB sifresi, `wp-config.php`, `.ini`.
- **Once dev, sonra canli.** Canliya alirken dev'in HTML'i **kopyalanmaz** —
  icinde dev adresleri olur; ayni donusum canlinin kendi icerigi uzerinde
  calistirilir ve cikti denetlenir (dev URL sayisi 0 olmali).
- Canliyi etkileyen her islemden (DNS, guvenlik duvari, sertifika, veri
  degisikligi) **once yedek**, mumkunse `--dry-run`, sonra kullanici onayi.
- `deploy-site.sh` `rsync --delete` kullanir → repo eksikken calistirmayin.
  Mevcut `wp-config.php`'yi ezmez.
- Degisiklikten sonra **dogrula**: HTTP kodu yetmez, icerigi kontrol edin.
- Bu dosyayi guncel tut: yeni tuzagi 6. bolume, biten isi 5'e, bekleyeni 0'a
  yaz. Uzun anlatiyi commit mesajina birak.
