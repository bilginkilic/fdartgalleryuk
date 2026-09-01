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
| 1 | **chestnyznak giden e-posta** — `info@chestnyznak.com.tr` timeweb posta kutusu parolasi | Tasimada bozuldu, kurtarilamiyor. `TIMEWEB_MAIL_PASSWORD` env'e konursa kurulum hazir: `scratchpad/set-chestnyznak-smtp.sh` (bkz. 6a). **Alici adresleri artik dogru (6m) ama bu parola girilene kadar hicbir bildirim TESLIM EDILMEZ.** |
| 2 | **Kalan 4 site** icin dosya arsivi + `.sql` + **orijinal `wp-config.php`** | fdsanatmerkezi, chemiartclick.uk apex, davetevet, byhio, wedreply |
| 3 | **Kalici SSH anahtari** — public kismi bize, private kismi `VPS_SSH_PRIVATE_KEY` env'ine | Iki isi acar: haftalik blog Routine'inin kendi basina yayinlamasi **ve** SSH parola girisinin kapatilmasi (6c) |
| 4 | **chestnyznak Rusya'dan acilmiyor** — gri buluta cekildi, yine acilmiyor. Geriye tek degisken **alan adi** | `chestnyznak-test.chemiartclick.uk` testinin sonucu bekleniyor (6d) |

### Karar bekleyenler

- **Cloudflare APO** (~5 $/ay): HTML'i edge'de onbellekler. Turkiye'deki ziyaretci
  icin en buyuk tek kazanc; su an `cf-cache-status: DYNAMIC`.
- **fdartgallery eklenti sadelestirme**: iki form + iki mailchimp eklentisi;
  `elementor-pro` + `pro-elements` cakismasi.
- **Blogun en eski 4 yazisi**: slug'lari hala Ingilizce demo slug'i. Duzeltmek
  adresi kirar → 301 gerekir. Ayrinti `deploy/blog/BACKLOG.md`.
- **chestnyznak kalan 14 JS enjeksiyonu** Elementor'a tasinsin mi (6f).
- **PTR** posta alan adina cevrilsin mi — oncelik dusuk, mevcut PTR calisiyor.
- **Urun sayfalari** (`/product/...`) tek dilde. Cevirisi Polylang'in
  **ucretli** WooCommerce eklentisini ister; sepet/kasa kirma riski oldugu
  icin ucretsiz surumle denenmedi (6k).

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

> **Yeni eklenen ortam degiskeni CALISAN oturuma yansimayabilir.** 01.09.2026'da
> `TIMEWEB_MAIL_PASSWORD` eklendi ve 10+ dakika sonra hala `env` ciktisinda yoktu.
> Genelde yalnizca **yeni baslayan** oturumlar alir. Beklemek yerine ya yeni
> oturum acin ya da isi panelden yaptirin.
> Ayrica: arka planda `until [ -n "$VAR" ]` gibi bir bekleme **ISE YARAMAZ** —
> arka plan sureci basladigi andaki ortami miras alir, degiskeni asla gormez.
> Kontrol her seferinde **yeni bir komut cagrisiyla** yapilir.

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

> **SSH KOMUT DIZESININ ICINE GOMULU HEREDOC YAZMAYIN.** Tirnak icinde tirnak
> katmanlari sessizce bozulur. 01.09.2026'da bir Python heredoc'u
> `ssh "... <<PY ... '\"'\"' ... PY"` seklinde gomuldu; tirnaklar mahvoldu ve
> **canli + dev `wp-config.php` ayni anda bozuldu** (yaklasik 30 sn iki site
> de PHP parse hatasi verdi). Yedekten geri alindi.
>
> **Dogru yol:** script yerelde dosyaya yazilir, `base64 -w0` ile aktarilir,
> sunucuda `base64 -d` ile acilip calistirilir. wp-config gibi kritik
> dosyalarda ayrica: once kopyasini al, degistir, `php -l` ile dogrula,
> bozuksa **kopyadan geri yaz**. Bu oruntu artik butun script aktarimlarinda
> kullaniliyor.

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
| Uc dil (canli) | Menuden ulasilan her adres + 20 blog yazisi tr/en/ru; dil dusuren baglanti 0; iletisim formu dile gore (6k) |

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

#### Onbellege yazilan yonlendirme — KOK SEBEP BULUNDU ve duzeltildi

Ana sayfa bir sure `301 → kendisi` donuyordu; sonra kullanici "dev acilmiyor"
dedi ama sunucu disaridan **200** veriyordu. Ikisinin de sebebi ayni:

```nginx
fastcgi_cache_valid 200 301 302 10m;   # <-- 301/302 de onbellege giriyordu
```

WordPress yonlendirmeleri **duruma baglidir** (kanonik, giris, dil). Onbellege
yazilan tek bir 301, on dakika boyunca **herkese** servis edilir; dahasi tarayici
301'i **KALICI** sayip saklar. Sonuc: site sunucuda calisirken kullanicida
acilmaz hale gelir ve sunucuyu ne kadar test etseniz sorunu goremezsiniz.

**Duzeltildi (01.09.2026):** dort sitede de `fastcgi_cache_valid 200 10m;`.
Artik yalnizca 200 ve 404 onbellege girer. Repo: `deploy/nginx/templates/fastcgi-php.conf.template`

> **Ayirt edici isaret: `HEAD` 200 ama `GET` 301** → onbellekte yonlendirme var.
> **Kullanicida acilmiyor ama sunucuda 200** → tarayiciya yazilmis 301.
> Cozum kullanici tarafinda: gizli sekmede dene, ya da site verisini temizle
> (Chrome: adres cubugundaki kilit → "Cerezler ve site verileri" → temizle).

Adres degistiren her islemden sonra onbellek temizlenip **GET** ile test edilir.

### 6i. Cok dillilik — Polylang (dev, 01.09.2026)

**CANLIDA VE DEV'DE AKTIF (01.09.2026).**

**Neden Polylang:** ucretsiz surumu **sinirsiz dil** destekliyor. TranslatePress'in
ucretsiz surumu yalnizca **1** ek dil verir — bize 2 lazimdi (EN + RU). WPML ucretli.
Polylang her dile ayri URL ve ayri gonderi verir; Elementor ile her dil ayri sayfa
oldugu icin duzen bagimsiz duzenlenebilir.

| Dil | URL | Durum |
|---|---|---|
| Turkce (varsayilan) | `/` | tum icerik (470 gonderi + 86 terim Turkce isaretlendi) |
| English | `/en/` | **yalnizca ana sayfa** cevrildi (ID 37686) |
| Русский | `/ru/` | **yalnizca ana sayfa** cevrildi (ID 37687) |

Ayarlar: `force_lang=1` (dizin oneki), `hide_default=1` (Turkce koke kalir),
`rewrite=1`, `browser=0` (tarayici diline gore otomatik yonlendirme KAPALI),
**`redirect_lang=1`**.

> **`redirect_lang=1` SART.** 0 birakilinca `/en/` cevrilmis ana sayfayi degil
> **blog arsivini** gosteriyordu; cevrilmis sayfa `/en/home-2/` adresinde
> kaliyordu. Ceviriler dogru bagliydi, sorun yalnizca bu ayardaydi.

Script'ler: `deploy/wordpress/i18n/` (sirayla 01→04), ceviri metinleri
`ceviriler.json`. Yedek: `/root/backups/dev-before-polylang-*.sql` ve
`dev-before-i18n-pages-*.sql`.

#### Sayfa kimligine bagli kilavuzlar KIRILIYORDU

`cz-lead` blogunun script'i `if(!/page-id-5002/.test(document.body.className))return;`
ile basliyordu; CSS'te de 7 adet `body.page-id-5002` kurali vardi. Dil kopyalari
**farkli ID** aldigi icin EN/RU sayfalarinda script hic calismaz, form baglanmaz,
enjekte edilen bolumler gelmezdi. Duzeltildi:

- script kilavuzu → `if(!document.getElementById('cz-lead'))return;`
- CSS `body.page-id-5002` → **`body.frontpage`**

> Bu tema on sayfaya `home` DEGIL `frontpage` sinifi basiyor.

##### TUM SAYFAYI BEYAZ YAPAN HATA — dikkat

Ilk denemede CSS'i `body.frontpage, body.home` ile degistirdim. Bu **YANLIS**:
o kurallar zaten **virgullu secici listesiydi** —

```css
body.page-id-5002 .elementor-element-85f2abf,body.page-id-5002 .elementor-element-3c0b548{display:none!important}
```

duz metin degisimi sonucu

```css
body.frontpage, body.home .elementor-element-85f2abf,body.frontpage, ...{display:none!important}
```

oldu. Listede artik **ciplak `body.frontpage`** var → `body{display:none}` →
**butun sayfa beyaz**. Dev'de uc sayfada 14'er secici bozuldu.

**Ders 1 — bir secici icinde virgullu liste ile degisim yapmayin.** Cok
bilesenli seciciyi tek bilesikle degistirin (`body.frontpage`).

**Ders 2 — VARLIK kontrolu GORUNURLUK kontrolu DEGILDIR.** HTTP 200 doneyordu,
HTML 303 KB'di, `getElementById` her seyi buluyordu; sunucu testlerinin hepsi
yesildi. Sayfa yine de bomboştu. Bundan sonra tarayici testinde
`getComputedStyle(body).display` ve `getBoundingClientRect()` olculur:

```js
const bb = e => { const r = e.getBoundingClientRect(); return {w:r.width, h:r.height}; };
// body yuksekligi > 200 ve #cz-lead yuksekligi > 100 olmali
```

Duzeltme sonrasi olculen: body 1280x8386, `#cz-lead` 1280x1066, H1 614x146.
**Canli ETKILENMEDI** — bu degisiklik yalnizca dev'e uygulanmisti.

#### Menu ve dil degistirici

Yalnizca ana sayfa cevrildigi icin EN/RU'ya **ayri menu acilmadi**; ayni menu
(171) her uc dile atandi, boylece gezinme calismaya devam ediyor. Menuye
Polylang dil degistirici ogesi eklendi (`_pll_menu_item`, bayrak + ad, acilir
menu kapali). **Sayfalar cevrildikce dile ozel menu acilmalidir.**

#### Dogrulandi

```
/     -> 200 lang=tr-TR  H1 "Rusya'nin zorunlu urun markalamasini..."
/en/  -> 200 lang=en-US  H1 "Russia's mandatory product labelling..."
/ru/  -> 200 lang=ru-RU  H1 "Обязательную маркировку товаров в России..."
```
Her ucunde `#cz-lead` + form var; tarayicida `/ru/` icin `data-cz-bound=1`,
buton "Отправить заявку →". Dil degistirici uc sayfada da cikiyor, 6 hreflang
etiketi basiliyor. Dev hala aramaya kapali (`Disallow: /` + `X-Robots-Tag`).
Canli ve fdartgallery etkilenmedi.

> `lang="ru-RU"` olmasinin yan faydasi: `kod-sorgulama` araci ve `cz-lead`
> blogu zaten `document.documentElement.lang` degerine bakip TR/RU arasinda
> geciyor — Rusca sayfada kendiliginden Rusca calisiyor.

#### Altbilgi ve widget — tema duzenini dile gore secmek

Altbilgi bir `cpt_layouts` duzenidir ve tema onu `footer_style` ayarindan
secer; ayar TEK degerdir, dil bilmez. Duzenin cevirisi olsa bile tema hep
Turkce olani basardi.

**Iki yol denendi ve OLMADI — tekrar denenmesin:**
1. `theme_mod_footer_style` filtresi → tema `get_theme_mod()` **kullanmiyor**,
   kendi deposundan (`qwery_storage`) okuyor.
2. `get_footer` kancasinda depoyu degistirmek → depo dize degil, ayarin **tum
   tanim dizisini** tutuyor (gercek deger `['val']` icinde) ve deger zaten
   `static` degiskende onbelleklenmis oluyor.

**Calisan yol:** temanin `qwery_get_custom_footer_id()` fonksiyonu
`function_exists()` ile korunuyor. mu-plugin'ler temadan **once** yuklendigi
icin ayni adla tanimlayinca tema kendi surumunu tanimlamiyor.
→ `deploy/wordpress/lead-mu-plugins/fd-i18n-layouts.php`

Ayni dosya, tema widget alanindaki 4 `extra_item` baglantisini de
`widget_custom_html_content` filtresiyle **sunucu tarafinda** ceviriyor.
Widget'i dile bolmek icindeki cz-* scriptlerini kirardi; JS enjeksiyonu da
kural geregi kullanilmadi.

#### Olculen sonuc (01.09.2026, canli)

```
/     kalan Turkce metin: 63 (dogru — Turkce sayfa)
/en/  kalan Turkce metin:  2 → ikisi de kendi Ingilizce cevirimizde gecen
                              "Türkiye" ulke adi; dogru kullanim
/ru/  kalan Turkce metin:  0
```
Uc sayfa da tarayicida GORUNUR: body 1280x8386/7118, `#cz-lead` 1280x1066.
Form ucu canlida: `{"stored":true,"relay":"gonderildi (302)"}`.
Tum sayfalar 200; fdartgallery ve dev etkilenmedi.

#### Kalan is (kullanici karari)

Ceviri **ana sayfa + menu + altbilgi** ile sinirli. 177 ic sayfa, blog
yazilari, magaza ve WooCommerce urunleri hala tek dilde. WooCommerce
urunlerinin cevirisi Polylang'in **ucretli** eklentisini gerektirir.
Hangi sayfalarin cevrilecegi icerik karari.

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

### 6j. Hiz — olculen durum ve yapilanlar (01.09.2026)

Kullanici "basar basmaz acilsin" istedi. **Once olculdu, sonra dokunuldu.**

| Olcum | Deger |
|---|---|
| TTFB (onbellek isabetli, sunucudan) | **26 ms** — sunucu darbogaz DEGIL |
| HTML boyutu | **294 KB** (gzip'li gonderiliyor) |
| Harici varlik | **40 script + 49 stylesheet = 89 istek**, 60 benzersiz dosya |
| Protokol | **HTTP/1.1** ← asil sorun buydu |

**Yapilan: HTTP/2 acildi** (`conf.d/03-http2.conf`, `http2 on;`).
nginx 1.28.3'te `listen ... http2` **kullanimdan kalkti**; dogru yol bu direktif.

Neden en buyuk kazanc: HTTP/1.1'de tarayici ayni sunucuya ~6 paralel baglanti
acar. 89 varlik ≈ **15 tur gidis-donus**. Turkiye-Londra RTT ~60 ms oldugundan
bu tek basina 1 saniyeden fazla bekleme demekti. HTTP/2 hepsini tek baglantida
cogullar. Dogrulandi: `HTTP/2 200`, dort site de saglam.

> **Yanlis teshis, olcumle duzeltildi:** `nginx.conf`'ta `gzip_types` satiri
> yorumda oldugu icin "CSS/JS sikistirilmiyor" sandim. Olcunce ikisinin de
> `content-encoding: gzip` dondugu gorildu. **Yorum satirina bakip cikarim
> yapmayin, yaniti olcun.**

**Sirada (kullanici karari):**
- **Edge onbellegi YOK** — chestnyznak Rusya icin gri bulutta, yani Cloudflare
  APO kullanilamiyor. Turkiye'den gelen her istek Londra'ya gidiyor.
  fdartgallery turuncu oldugu icin onda APO bir secenek.
- **Brotli** kurulu degil, Ubuntu deposunda `libnginx-mod-brotli` bulunamadi.
  Metin varliklarinda gzip'e gore ~%15-20 daha iyi olurdu.

### 6k. Dil kalicligi — Polylang dili URL'den okur

**Sikayet:** "dil degistirip gezerken tekrar Turkceye donuyor."

**Sebep basit ve kacinilmaz:** Polylang gecerli dili **URL onekinden** belirler
(`force_lang=1`). `/en/` menusundeki bir oge `/iletisim/` derse — cunku o
sayfanin cevirisi yoktur — onek duser ve site Turkceye doner. Menuyu
kandirmanin yolu yok; **cozum sayfayi gercekten cevirmektir.** Denenip
elenen iki yol:

- **`pll_language` cerezi**: icerik dili her zaman kazanir; ustelik nginx
  `fastcgi_cache` cerezi anahtara katmadigi icin cerez temelli bir dil
  secimi zaten onbellek tarafindan ezilirdi. Olculdu: `/blog-standard/`
  cerezle de `lang=tr-TR` donuyor.
- **Onekli adresle cevirisiz icerik** (`/en/<turkce-slug>/`): Polylang
  `redirect_lang=1` ile bunu 301 ile oneksiz adrese geri atar.

**Yapilan (01.09.2026, once dev sonra CANLI):** menuden ulasilan HER adres
uc dile cevrildi.

| Turkce | English | Русский |
|---|---|---|
| `/hakkimizda/` | `/en/about-us/` | `/ru/o-nas/` |
| `/iletisim/` | `/en/contact/` | `/ru/kontakty/` |
| `/kod-sorgulama/` | `/en/code-lookup/` | `/ru/proverka-koda/` |
| `/blog-standard/` | `/en/news/` | `/ru/novosti/` |
| `/shop/` | `/en/solutions/` | `/ru/uslugi/` |
| 20 blog yazisi | 20 yazi | 20 yazi |

Araclar: `deploy/wordpress/i18n/08..15*.php` + yanlarindaki JSON sozlukler
(`yazi-cevirileri/parti-1..6.json` blog metinleridir).

> **SCRIPTLERE POST ID YAZILMAZ.** Dev ve canlida ayni degiller: EN ana sayfa
> dev'de 37686, canlida 37687. Ilk surumde ID'ler koda gomuluydu; canliya
> alirken hepsi `page_on_front`, `pll_get_post()`, `get_nav_menu_locations()`
> ve slug/baslik aramasindan cozulecek sekilde degistirildi. Ayni sebeple
> `13-yazilari-cevir.php` yuku TR yazi ID'siyle anahtarlanir — o ID'ler
> klonda AYNI kalir, cunku dev canlidan kopyalanmistir.

**Olcut:** `/en/` ve `/ru/` sayfalarindaki ic baglantilardan **dil dusuren
sifir tane** olmali. Tarama araci sunucuda `python3 /tmp/tara2.py <dil> <yollar>`
mantigiyla yazildi: `<head>` ve `lang-item` ogeleri disarida birakilir (dil
degistiricinin diger dile gitmesi dogrudur), kalan her ic baglanti onekli
olmalidir. Son olcum: 20 sayfada **en=0, ru=0**.

#### Magaza: urun sayfalari neden `[products]` ile listelenmiyor

`/en/solutions/` ve `/ru/uslugi/` once `[products]` kisa koduyla yapildi ve
urunler uc dilde de listelendi (`product` Polylang'de cevrilen tipler arasinda
DEGIL). Ama urun baglantilari `/product/...` — oneksiz. Tiklayan ziyaretci
Turkceye duserdi. Bu yuzden sayfalar **cevrilmis paket kartlarina** cevrildi;
kartlarin CTA'si cevrilmis iletisim sayfasina gider. Turkce ziyaretci icin
gercek magaza (`/shop/`) oldugu gibi durur.

> `product` tipini Polylang'de cevrilebilir yapmak denenmedi: Polylang'in
> ucretsiz surumu WooCommerce entegrasyonu icermez, sepet/kasa/vergi
> akislarini kirma riski var. Calisan bir magazayi kirmayin.

#### Uc ayri yerde adres duzeltmek gerekti

1. **Menu ogeleri** (`_menu_item_url`) — 08 numarali script menuleri de baglar.
2. **Sayfa/duzen icerigi** — ana sayfa ve altbilgi duzenlerindeki dugmeler
   (`09-ic-baglantilari-dile-bagla.php`). Ayni script temanin DEMO sayfasina
   giden bir hatayi da duzeltti: "Hakkimizda devamini oku" dugmesi
   `/about-creative/` ("About - Creative") adresine gidiyordu.
3. **Tema widget'i** (`widget_custom_html`) — icindeki cz-* scriptleri her
   dilde ayni basildigi icin adresler `fd-i18n-layouts.php` filtresinde
   sunucu tarafinda cevrilir.

#### TUZAK: sayfaya ve ELEMAN KIMLIGINE civilenmis scriptler

**Sikayet:** "en ve ru ana sayfa tr'den farkli, birebir ayni olmali."

Olculdu: Elementor YAPISI ucunde de birebir ayniydi (70 dugum, diff bos) ama
govde TR 8386 px, EN/RU 6877 px. Fark **calisma aninda** olusuyordu, iki
sebepten:

1. **Yol kilavuzu.** Widget'taki `cz-hero-block` ve `cz-fix2` bloklari
   `if(p!=='/' && !/\/home\/?$/.test(p)) return;` ile basliyordu. `/en/` ve
   `/ru/` bu testi gecemez → scriptler HIC CALISMAZ, gizlenmesi gereken
   bolumler acik kalir.
2. **Eleman kimligi.** `document.querySelector('.elementor-element-54fa5520')`
   ve `[data-id="567ae5d"]` gibi seciciler. Dil kopyalari uretilirken her
   elemanin `id` alani YENIDEN URETILIR, bu yuzden hicbir sey bulunmaz.

**Duzeltme** (`11-eleman-kimliklerini-siniflandir.php`):
- yol kilavuzu → `document.body.classList.contains('frontpage')`
  (tema on sayfaya bu sinifi basiyor; uc dilde de var, ic sayfalarda YOK —
  ikisi de olculdu),
- eleman kimligi → kalici sinif `cz-el-<kimlik>`. Sinif TR sayfasindaki
  kimlige gore adlandirilir ama uc sayfada da **ayni indeksteki** elemana
  yazilir; script once "uc sayfa da 70 dugum, turler ayni" diye dogrular.

> **Alt tuzak:** Elementor'da "CSS Classes" denetiminin adi eleman turune
> gore DEGISIR — widget'ta `_css_classes`, section/column'da `css_classes`
> (`elementor/includes/elements/section.php:1291`). Hepsine `_css_classes`
> yazildiginda 16 dugumden yalnizca 4'unun sinifi basildi.

> **Ikinci alt tuzak:** yol kilavuzu duzeldikten sonra scriptler CEVRIMDISI
> testte de calismaya basladi — `file://` yolunda `location.pathname` asla
> `/` olmadigi icin onceki olcumler o bloklari hic calistirmamisti. Yani
> "TR 8386 px" bir cevrimdisi artifaktiymis (CLAUDE.md 6e).

Scriptler calisinca EN/RU'ya **Turkce** hero metinleri girdi (widget tek ve
uc dilde ayni basiliyor). Bu yuzden widget metinleri de sunucu tarafinda
cevrildi: `fd-i18n-layouts.php` + `fd-i18n-widget-sozluk.json` (47 metin).

**Son olcum:** govde TR 9826 / EN 9792 / RU 9795 px (%0,35 fark, yalnizca
satir kaydirma). Yirmi ust bolumun yuksekligi ucunde de ayni.

#### TUZAK: `_elementor_data` uzerinde ham strtr CALISMAZ

`_elementor_data` JSON'unda Turkce harfler `ı` bicimindedir. Ham dize
uzerinde `strtr` yalnizca ASCII anahtarlari yakalar — **56 anahtardan 6'si
esletti ve "kalan 0" yaziyordu**, cunku kalanlari da ayni ham dizede ariyordu.
Dogru yol: JSON'u **coz**, dizeleri PHP tarafinda (gercek UTF-8) cevir,
`wp_json_encode` ile yeniden kodla. `10-anasayfa-bloklarini-cevir.php` boyle.

Dogrulama olcutu HTTP kodu degil: sayfanin **gorunur metninde** Turkce'ye ozgu
harf (`çğışÇĞİŞ`) iceren kelime sayilir. Olculen sonuc: `/en/` ve `/ru/` icin
**3** (ucu de dil secicideki "Türkçe" adi — dogru), `/` icin 163.

### 6l. Eklenti sadelestirme (dev, 01.09.2026)

Kapatilanlar — dordunun de icerikte KARSILIGI YOK, yalnizca kendi secenek
satirlarinda ve temanin kullanilmayan demo duzenlerinde geciyordu:
`latepoint` (22 MB, **hic randevu kaydi yok**), `devvn-image-hotspot`,
`fluent-affiliate-connector`, `fluentforms-pdf`. **24 → 20 aktif eklenti.**

**Kapatilmayanlar ve sebebi** (arama once yapildi, CLAUDE.md 6e):
- `revslider` — 103 `trx_widget_slider` meta satiri. 30.08'de "kullanilmiyor"
  sanilip kapatilmisti, **canli ana sayfa 2 gun bozuk kaldi**.
- `fluentform` — altbilgideki bulten formu (`[fluentform id='2']`) uc dilin
  altbilgi duzeninde de var; ayrica 8-13 kayitli canli formlar mevcut.
- `contact-form-7` — Iletisim sayfasinin formu.
- `easy-code-manager` — icinde **iki calisan hiz snippet'i** var
  (`wp-content/fluent-snippet-storage/`): ana sayfada revslider varliklarini
  ve magaza disinda WooCommerce varliklarini kuyruktan cikariyorlar.

**Varlik diyeti** — `deploy/wordpress/lead-mu-plugins/fd-asset-diyet.php`
mu-plugin'i, sayfada KARSILIGI OLMAYAN dosyalari kuyruktan cikarir:
mediaelement (5 dosya, sayfada oynatici yoksa), Site Kit'in CF7 olay
saglayicisi, WooCommerce `sourcebuster` (satin alma akisi disinda).

> `akismet-frontend.js` DENENDI, VAZGECILDI: ana sayfada yorum formu yok ama
> Akismet altbilgideki FluentForm bultene bal kupu alani basiyor ve o alani
> **bu JS dolduruyor**. Cikarilsa mesru abonelikler spam sayilirdi.

**Olculen (dev ana sayfa):** 89 → **75 istek**, 85 → 71 benzersiz dosya.
`/shop/` ve `/cart/` bilerek etkilenmedi (Woo varliklari orada gerekli).

### 6m. Form bildirimleri kime gidiyor (01.09.2026, CANLI + dev)

**Kullanici karari:** ekip bildirimleri **To: `info@chestnyznak.com.tr`,
Cc: `oguzk@chestnyznak.com.tr`**. WordPress yonetici adresi
(`admin_email = swordbros@gmail.com`) **DEGISMEZ**; panelde bekleyen
"yonetici e-postasini info@ yap" degisikligi **iptal edildi**
(`new_admin_email` + `adminhash` silindi).

Kisisel adresler (`swordali@gmail.com`, `blgnklc@gmail.com`) alicilardan
cikarildi. Arac: `deploy/scripts/eposta-alicilari.php` (`dry` destegi var,
yeniden calistirilabilir).

**Politika** (`deploy/scripts/eposta-alicilari.php`, `dry` destegi var,
yeniden calistirilabilir; iki sitede de uygulandi):

| Alan | Kural |
|---|---|
| Alici | `admin_email` disindaki her alici → To `info@`, Cc `oguzk@` |
| Gonderen | Alan adi `chestnyznak.com.tr` DISINDA olan her gonderen → `info@` |
| Bcc | `chestnyznak.com.tr` disindaki bcc adresleri kaldirilir |

`wordpress@chestnyznak.com.tr` **dokunulmaz** — dogru alan adinda ve SPF/DKIM
zaten bu alana kurulu.

Kapsanan yerler: **9 CF7 formu** (8'i temanin demo formuydu, hepsi
`test@fwe.com`'a gidiyordu), **FluentForm 2/3/4/6/8/9/10/11**, **WooCommerce**
(siparis + stok bildirimi alicilari, `from_address`, `from_name`),
`booked_email_force_sender_from`, tema iletisim widget'i ve
**`fd-lead-capture.php`** (`FD_LEAD_NOTIFY_EMAIL` + `FD_LEAD_NOTIFY_CC`,
wp-config'de tanimli, repoya yazilmaz).

#### Iki yerde bilerek FARKLI davranildi

> **Otomatik yanitlara `sendTo` olarak ekip adresi YAZILMAZ.** FluentForm 3, 4
> ve 8'in bildirimi `sendTo.type = field` — alicisi BASVURANIN kendisidir.
> Oraya ekip adresi yazmak musteriye giden yaniti kirar. Ekip kopyasi
> yalnizca `cc` alanina eklendi.

> **"E-postayla paylas" dugmesi bir alici ayari DEGILDIR.**
> `trx_addons_options[share][*][url]` icinde `mailto:test@fwe.com?...`
> duruyordu. O baglanti ZIYARETCININ posta istemcisini acar; oraya ekip
> adresi yazmak "yaziyi paylas" dugmesini "bize e-posta gonder"e cevirirdi.
> Dogrusu alici kismini bos birakmaktir: `mailto:?subject=...`.

**Kaldirilan bcc'ler:** FluentForm 3/4'te `ali.kilic@swordbros.com`, 8'de
`ali.kilic@swordbros.ru`. Ikili zaten `cc`'de oldugu icin kopya kaybolmuyor;
ayri bir ic dagitim istenirse geri eklenir.

**Hala demo verisi:** tema iletisim widget'inin adres ve telefon alanlari
(Berlin adresi, +1 telefon). Widget `wp_inactive_widgets` icinde, yani
kullanilmiyor; kullanilacaksa elle duzeltilmeli.

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
- **`webmail.chestnyznak.com.tr` duzeltildi (01.09.2026).** Bizim IP'ye bakiyordu
  ama vhost yoktu; istek ilk 443 bloguna dusup WordPress kanonik yonlendirmesiyle
  **magazaya** gidiyordu. Simdi kendi Let's Encrypt sertifikasiyla
  `https://mail.timeweb.com/` adresine 301. Kayit **griye** cekildi (Rusya).
  **CNAME yapilmadi**, cunku timeweb `*.timeweb.com` sertifikasi sunuyor ve
  tarayici uyari verirdi. Vhost: `deploy/nginx/sites-available/webmail.chestnyznak.com.tr.conf`
  — icine `cloudflare-only.conf` EKLENMEZ.
  > `mail.chestnyznak.com.tr` (CNAME → `mail.timeweb.com`) **bilerek
  > DOKUNULMADI**: ayni sertifika uyusmazligi orada da var, ama bu ad bir posta
  > istemcisinde (IMAP/SMTP sunucusu olarak) kullaniliyor olabilir; degistirmek
  > calisan bir kurulumu kirar. Webmail icin dogru adres artik `webmail.` olani.
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
- **UC DIL KURALI (chestnyznak).** Siteye eklenen her yeni icerik — blog yazisi,
  sayfa, menu ogesi, hero blogu, buton metni — **Turkce + English + Русский**
  olarak eklenir. Tek dilde birakilan icerik eksik istir.
  - Yeni yazi/sayfa: Turkcesi yayinlanir, sonra `pll_set_post_language()` ile
    dili isaretlenir, EN ve RU kopyalari acilir, `pll_save_post_translations()`
    ile uc kayit birbirine baglanir.
  - Menuye oge eklenirken **her uc menuye** eklenir (tr=171, en=372, ru=373).
  - Elementor blogu eklenirken metinler uc sayfada ayri ayri cevrilir; ortak
    olan CSS/JS `cz-lead-assets` widget'inda tutulur, **sayfa kimligine bagli
    kilavuz yazilmaz** (bkz. 6i).
  - Ceviri sozlugu: `deploy/wordpress/i18n/sayfa-cevirileri.json`
- Degisiklikten sonra **dogrula**: HTTP kodu yetmez, icerigi kontrol edin.
- Bu dosyayi guncel tut: yeni tuzagi 6. bolume, biten isi 5'e, bekleyeni 0'a
  yaz. Uzun anlatiyi commit mesajina birak.
