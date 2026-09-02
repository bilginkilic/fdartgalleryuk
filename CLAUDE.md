# CLAUDE.md — OVHcloud VPS / cok siteli WordPress runbook

Projeyi devralan her oturum icin kalici hafiza. Kisa tutulur: **su an ne oldugu**,
**nasil erisilecegi**, **hangi tuzaklara dusuldugu**. Anlati git gecmisinde:
`git log -p -- CLAUDE.md`.

Son duzenleme: 02.09.2026 — 1529 satirdan sadelestirildi, su an 778 satir.

> **YENI OTURUMSAN ONCE BURAYI OKU → [3. bolum](#3-sunucuya-baglanma-yeni-oturum-buradan-baslar).**
> Sunucuya **ham TCP/22 ile GIRILMEZ** — Cloudflare tuneli kullanilir. Makinede
> hazir anahtar **ARAMA**, yok: kendi anahtarini uret, `.pub`'i dala push et,
> erisimi olan bir oturum onu sunucuya ekler. Tarif 3. bolumde, tek sayfa.

---

## 0. SU AN NE KALDI

Yayindaki siteler: **fdartgallery.com**, **chestnyznak.com.tr** (+ iki dev ortami).
Sunucu kurulumu, performans, guvenlik, yedekleme, uc dil ve SEO temizligi bitti.

### Kullanicidan bekleyenler

| # | Is | Not |
|---|---|---|
| 1 | **Reklam acilis sayfalari** — hangileri kampanyada kullaniliyor? | Demo temizligi (6i) menude olmayan sayfalari taslaga cekti; `rusya-markalama-danismanlik` reklamda kullaniliyormus ve **geri alindi**. Ayni riski tasiyan 5 sayfa daha var, asagida |
| 2 | **Video** (Telegram #22, #37) YouTube'a yuklenecek | Link gelince blog yazilarina gomulur (6j) |
| 3 | **Kalan 4 site** icin dosya arsivi + `.sql` + orijinal `wp-config.php` | fdsanatmerkezi, chemiartclick.uk apex, davetevet, byhio, wedreply |
| 4 | **SSH parola girisi kapatilsin mi** | Anahtarla giris CALISIYOR (5 anahtar, 6c). Loglarda dakikalik root parola denemesi var. Kapatilirsa acil giris yolu yalnizca OVH KVM konsolu |
| 5 | **`VPS_SSH_PRIVATE_KEY` maskesiz yapistirilsin** | Env'deki deger bozuk: 1150 karakter, govdesi bastan sona `•` (U+2022). Kalici anahtari kullanacak yeni oturumlar bu yuzden giremiyor; beklenen parmak izi `SHA256:i7IciIuo…` (6c) |
| 6 | **chestnyznak Rusya'dan acilmiyor** | Gri buluta cekildi, yine acilmiyor. Geriye tek degisken alan adi; `chestnyznak-test.chemiartclick.uk` testi bekleniyor (6k) |

**Madde 1'in listesi** — demo temizliginde taslaga cekilen, reklam sayfasi
olabilecek kayitlar (hepsi `elementor_canvas` sablonunda veya form iceriyor):

| ID | slug | baslik |
|---|---|---|
| ~~37518~~ | `rusya-markalama-danismanlik` | **GERI ALINDI, yayinda** |
| 37231 | `rusya-markalama-kaydi-chestny-znak-...` | Rusya Markalama Kaydi (TR+RU) |
| 37209 | `chestny-znak-oguz-k` | Chestny Znak Oguz K |
| 37182 | `chestny-znak-webinar-landing-page-taslak-icerik` | Webinar Landing (form var) |
| 37124 | `elementor-37124` | Chestny Znak Destek |
| 36972 | `elementor-sayfa-36972` | Yapim Asamasinda Form RUS |

Geri almak tek komut: `wp post update <id> --post_status=publish`.
Tam liste `wp-content/demo-temizlik-geri-alma.json` icinde (370 kayit).

### Karar bekleyenler

- **Cloudflare APO** (~5 $/ay) — fdartgallery turuncu bulutta, secenek. chestnyznak
  gri oldugu icin kullanilamaz. Su an `cf-cache-status: DYNAMIC`.
- **Blogun en eski 4 yazisi** hala Ingilizce demo slug'inda. Duzeltmek 301 ister;
  tablo hazir (`fd-eski-adresler.php`). Ayrinti `deploy/blog/BACKLOG.md`.
- **chestnyznak kalan 14 JS enjeksiyonu** Elementor'a tasinsin mi (6f).
- **Urun sayfalari** (`/product/...`) tek dilde — Polylang'in **ucretli** WooCommerce
  eklentisini ister, sepet/kasa kirma riski var (6e).
- **PTR** posta alan adina cevrilsin mi — oncelik dusuk, mevcut PTR calisiyor.

---

## 1. Altyapi

| Alan | Deger |
|---|---|
| Hostname | `vps-913eb1fb.vps.ovh.net` |
| IPv4 / IPv6 | `57.129.128.118` (gw `57.129.128.1`) / `2001:41d0:801:2000::7e80` |
| Model | VPS-2 2027 — 4 vCore / 8 GB RAM / 75 GB SSD |
| OS / PHP | Ubuntu 26.04 / **PHP 8.5**-FPM |
| Bolge | `os-uk2` — Londra |

PTR **calisiyor**, ileri-dogrulamali: `57.129.128.118 <-> vps-913eb1fb.vps.ovh.net`.

**Cloudflare zone'lari.** Chemiartclick hesabi (`cloudflare_api_token`):
fdartgallery.com `fa3f51825d634f23741ff5fbf2ae8b1a`, chemiartclick.uk
`56fcc0694b5c39e44fd5ada387e72da7`, + fdsanatmerkezi.com, davetevet.com,
byhio.com, wedreply.co.uk. SWORD BROS hesabi (`CF_SWORDBROS_API_TOKEN`,
**yazma yetkisi var**): chestnyznak.com.tr `858396f27bcc338ad737fc52cf2d8a9f` + 12 zone.

| DNS kaydi | Proxy |
|---|---|
| `fdartgallery.com`, `www`, `dev` | **turuncu** |
| `chestnyznak.com.tr`, `www`, `dev.` | **gri** (Rusya erisimi icin) |
| `chestnyznak.chemiartclick.uk` | turuncu |

Hepsi `57.129.128.118`. fdartgallery mail kayitlari (Brevo DKIM, DMARC, SPF,
Email Routing MX) ve chestnyznak mail kayitlari (timeweb) **degistirilmez**.
`fdartgallery.com` SSL modu **Full (strict)** — `Flexible` yapmayin, dongu olur.

---

## 2. Kimlik bilgileri

Degerler **ortam degiskenlerinde**; asla repoya yazilmaz, log'a basilmaz.

| Degisken | Kullanim |
|---|---|
| `OVH_ENDPOINT` / `_APPLICATION_KEY` / `_APPLICATION_SECRET` / `_CONSUMER_KEY` | OVH API (`ovh-ca`) |
| `cloudflare_api_token` / `cloudflare_account_id` | Cloudflare (chemiartclick) |
| `cloudflare_access_keyid` / `cloudflare_access_key` | R2 / S3 |
| `CF_SWORDBROS_API_TOKEN` / `CF_SWORDBROS_ACCOUNT_ID` | Cloudflare (SWORD BROS) |
| `CLOUDFLARE_CF_Access_Client_Id` / `_Secret` | SSH tuneli Access service token |
| ~~`VPS_SSH_PRIVATE_KEY`~~ | Kalici SSH anahtari — **su an BOZUK**, maskeli yapistirilmis (0. bolum madde 5, 6c) |
| `BREVO_API_KEY` | fdartgallery giden posta |
| ~~`TIMEWEB_MAIL_PASSWORD`~~ | **KULLANMAYIN** — degeri YANLIS (6d) |

> **Yeni eklenen ortam degiskeni CALISAN oturuma yansimayabilir.** Genelde
> yalnizca **yeni baslayan** oturumlar alir. Arka planda `until [ -n "$VAR" ]`
> beklemesi **ISE YARAMAZ** — arka plan sureci basladigi andaki ortami miras alir.
> Kontrol her seferinde yeni bir komut cagrisiyla yapilir.

- **OVH consumer key yalnizca `/vps/*` kapsiyor.** `GET /me`, `/ip/*` → 403.
  Reverse DNS API'den degistirilemez; OVH Manager'dan yapilir.
- **Cloudflare:** `GET /user/tokens/verify` hesap kapsamli token'da `Invalid API
  Token` doner — normaldir. `GET /zones` ile test edin.

---

## 3. Sunucuya baglanma (yeni oturum buradan baslar)

**Ham TCP/22 bu ortamdan GECMIYOR** — engel port bazli (github.com:22 de kapali,
:443 aciliyor). Ag politikasini genisletmek TCP/22'yi acmaz, `nc -z ... 22`
denemesi zaman kaybidir. **Makinede hazir anahtar/tunel yapilandirmasi de
ARAMAYIN** — yok. Yol Cloudflare tuneli (HTTPS uzerinden SSH): sunucuda
`cloudflared` servisi active, tunel `ovh-vps`, adres `ssh.fdartgallery.com`,
onunde Access service token.

### Adim 1 — anahtarin var mi?

```sh
ls ~/.ssh/id_ed25519 2>/dev/null || echo YOK
```

**Varsa** Adim 2'ye gec. **Yoksa** ikisinden biri:

- `VPS_SSH_PRIVATE_KEY` **dolu ve gecerliyse** (kalici anahtar, 6c):
  ```sh
  mkdir -p ~/.ssh
  printf '%s\n' "$VPS_SSH_PRIVATE_KEY" > ~/.ssh/id_ed25519 && chmod 600 ~/.ssh/id_ed25519
  ssh-keygen -lf ~/.ssh/id_ed25519   # SHA256:i7IciIuo4c3uDhaLaH/AmNkQRb/V3VHXwqOtJ+yXuL8 olmali
  ```
  > Bu komut hata veriyor veya parmak izi tutmuyorsa env degeri **maskeli
  > yapistirilmis** demektir (govde bastan sona `•` / U+2022 olur). Zorlamayin,
  > asagidaki oturum anahtari yoluna gecin.

- Aksi halde **kendi efemeral anahtarini uret ve onaya gonder**:
  ```sh
  ssh-keygen -t ed25519 -N '' -C "claude-session-<OTURUM-KISA-ID>-ephemeral" -f ~/.ssh/id_ed25519
  cp ~/.ssh/id_ed25519.pub deploy/claude-session-key.pub
  git add deploy/claude-session-key.pub && git commit -m "SSH: oturum anahtari" \
    && git push -u origin claude/ovhcloud-vps-multisite-0eb3xj
  ```
  Sonra **DUR** ve kullaniciya haber ver: erisimi olan bir oturum bu `.pub`'i
  `/root/.ssh/authorized_keys`'e ekler (6c). Private anahtar **asla** commit edilmez.

### Adim 2 — baglan

```sh
curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 \
     -o /tmp/cloudflared && chmod +x /tmp/cloudflared
export TUNNEL_SERVICE_TOKEN_ID="$CLOUDFLARE_CF_Access_Client_Id"
export TUNNEL_SERVICE_TOKEN_SECRET="$CLOUDFLARE_CF_Access_Client_Secret"
ssh -o "ProxyCommand=/tmp/cloudflared access ssh --hostname ssh.fdartgallery.com" \
    -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@vps
```

**Kullanici DAIMA `root`** — `ubuntu`'nun `authorized_keys`'i bos, `debian`/`admin`
hic yok. Giris reddedilirse `-v` ile **hangi anahtarin sunuldugunu** okuyun (6c).

**Diger sinirlar:**
- GitHub reposu sunucudan erisilemiyor (ozel repo) → dosyalar tunel uzerinden
  `base64` / `rsync` ile aktarilir.
- Sunucunun 443'u bu oturuma kapali; egress gateway 403 doner.
- **Chromium konteynerden disari cikamiyor** — egress proxy BoringSSL el
  sikismasini kesiyor (`ws_closed_mid_exchange`); curl geciyor. Tarayici testi
  icin sayfayi ve varliklarini yerele aynalayip `python3 -m http.server` ile
  servis edin.
- KVM konsolunda klavye eslemesi bozuk (`:`→`;`); `sudo loadkeys us` duzeltir.

> **SSH KOMUT DIZESININ ICINE GOMULU HEREDOC YAZMAYIN.** Tirnak katmanlari
> sessizce bozulur; 01.09.2026'da **canli + dev `wp-config.php` ayni anda
> bozuldu**. Dogru yol: script yerelde dosyaya yazilir, `base64 -w0` ile
> aktarilir, sunucuda `base64 -d` ile acilir. wp-config gibi kritik dosyalarda
> once kopya al, degistir, `php -l` ile dogrula, bozuksa kopyadan geri yaz.

### Yeni oturum acarken seed prompta NE KONUR

Oturumlar birbirine mesaj gonderemez (6c); yeni oturum yalnizca seed promptta
yazani bilir. Eksik birakilan her madde saatlerce bosuna arama demektir.

1. **Bu bolumun tamami** — TCP/22 kapali, tunel tarifi, anahtar uretme adimi.
2. **Kapsam**: hangi site / hangi repo, digerlerine dokunulmayacagi.
3. **Dal**: `claude/ovhcloud-vps-multisite-0eb3xj` (izinsiz baska dala push yok).
4. **Site yollari** (4. bolum) — ozellikle chestnyznak'ta **dizin adi ≠ alan adi**.
5. **Once dev, sonra canli** ve **icerik kapatmadan once sor** kurallari (7. bolum).

---

## 4. Siteler

| Adres | Kok dizin | DB | Onek |
|---|---|---|---|
| `fdartgallery.com` | `/var/www/fdartgallery.com` | `wp_fdartgallery_com` | `fdArt0Og_` |
| `dev.fdartgallery.com` | `/var/www/dev.fdartgallery.com` | — | staging |
| `chestnyznak.com.tr` | `/var/www/chestnyznak.chemiartclick.uk` | `wp_chestnyznak_chemiartclic` | `ChestZna_` |
| `dev.chestnyznak.com.tr` | `/var/www/dev.chestnyznak.chemiartclick.uk` | `wp_dev_chestnyznak_chemiart` | staging |

> **DIKKAT:** chestnyznak'ta **dizin adi ile alan adi FARKLI**. Dizin, sistem
> kullanicisi, FPM havuzu, socket, DB ve log adlari eski `*.chemiartclick.uk`
> adina gore. Yeniden adlandirmak `open_basedir`'i, cron'u ve yedegi kirar.

| Bilesen | Yol |
|---|---|
| Kok | `/var/www/<dizin>/public` |
| Kullanici | `web_<slug>` — **`stat -c %U` ile okuyun, tahmin etmeyin** |
| FPM havuzu | `/etc/php/8.5/fpm/pool.d/<slug>.conf` (`memory_limit` 512M) |
| vhost | `/etc/nginx/sites-available/<ad>.conf` |
| DB sifresi | `/var/www/<dizin>/db-credentials.txt` (mod 600) |
| Loglar | `/var/log/nginx/<slug>.{access,error}.log` |
| WP cron | `/etc/cron.d/wp-cron-<slug>` (10 dk, CLI) |
| Repo (sunucuda) | `/opt/fdartgalleryuk/` |

Izolasyon: ayri unix kullanicisi + `open_basedir`.

**mu-plugin envanteri** (`deploy/wordpress/` altinda, dizin = hangi siteye gider):

| Dosya | Dizin | Ne yapar |
|---|---|---|
| `fd-webp-rewrite.php` | `mu-plugins/` (hepsi) | `.webp` varsa onu servis eder |
| `fd-staging-guard.php` | `staging-mu-plugins/` (dev) | e-postayi keser, `blog_public`=0, noindex, "DENEME ORTAMI" bandi |
| `fd-asset-diyet.php` | `fdart-` ve `lead-` (AYRI dosyalar) | sayfada karsiligi olmayan CSS/JS'i kuyruktan cikarir (6h) |
| `fd-font-hizlandirma.php` | `fdart-` | googleapis isteklerini tek istege indirir, preconnect (6h) |
| `fd-eski-adresler.php` | `fdart-` | slug degisimi icin 301 tablosu — cekirdek SAYFA slug'inda 301 kurmaz (6i) |
| `fd-site-haritasi-durum.php` | `fdart-` | `wp-sitemap*.xml` 404 yerine 200 doner (6i) |
| `fd-lead-capture.php`, `fd-i18n-layouts.php`, `fd-404-cevirisi.php` | `lead-` (chestnyznak) | basvuru kaydi (6g), dile gore layout, 404 cevirisi (6e, 6i) |

**Staging korumasi.** `fd-staging-guard.php` (mu-plugin): giden e-postayi
PHPMailer seviyesinde keser, `blog_public`'i 0'a zorlar, `robots.txt`'yi
`Disallow: /` yapar, "DENEME ORTAMI" bandi gosterir. nginx'te
`conf.d/02-staging-hosts.conf` dev hostlarina `X-Robots-Tag: noindex` basar
(statik dosyalar dahil); yeni dev sitesi eklerken o map'e satir eklenir.

> DB'deki `blog_public` **1 gorunur**, normaldir — guard calisma aninda filtreyle
> 0'a ceker. Olcut sayfadaki `noindex, nofollow` etiketidir.

---

## 5. Yapilmis isler

| Alan | Durum |
|---|---|
| Sunucu | nginx + PHP 8.5-FPM + MariaDB + Redis + certbot + ufw + fail2ban + wp-cli |
| Oturum SSH erisimi | Cloudflare tuneli (`ssh.fdartgallery.com`) + `authorized_keys`'te 5 anahtar; dort Claude oturumu bagli (3, 6c) |
| Sayfa onbellegi | nginx `fastcgi_cache`; ana sayfa 0.90 sn → **0.006 sn** |
| HTTP/2 | `conf.d/03-http2.conf` (`http2 on;` — `listen ... http2` kullanimdan kalkti) |
| Reklam/bulten trafigi | `?utm_*`/`gclid`/`fbclid` artik onbellekten okuyor; TTFB ~2,3 sn → **15-25 ms** (6h) |
| WebP | fdartgallery 6394, chestnyznak 5839 dosya; orijinaller korunur, gece cron |
| Redis | site basina izole: canli fd=3, dev fd=4, chestnyznak=8 |
| fdartgallery hizi | **canli + dev**: 47 → 26 script, 32 → 26 stylesheet, react+12 `js/dist` dustu, `.ttf` gzip, isinmis TTFB 15-19 ms (6h) |
| Yedekleme | Cloudflare R2, gece 04:15, 30 gun; uploads artimli (`copy`, `sync` DEGIL) |
| UpdraftPlus | komple kaldirildi; disk %40 → **%26** (9,7 GB yerel arsiv silindi) |
| Sertifikalar | Let's Encrypt, otomatik yenileme |
| MariaDB | `root@localhost` parolasizdi → `unix_socket` + yedek parola |
| fail2ban | 3 jail; Cloudflare edge + yerel nftables bani |
| E-posta | fdartgallery Brevo API + DKIM/SPF/DMARC; chestnyznak timeweb SMTP — **ikisi de calisiyor** |
| Turnstile | fdartgallery giris/kayit/form/WooCommerce |
| fdartgallery eklenti sadelestirmesi | **17 → 11**, canli ve dev esit. Kapatilan 4: `mailchimp-for-woocommerce`, `fluentforms-pdf`, `image-optimization`, `updraftplus`. Komple kaldirilan 2: **Contact Form 7** (formlar FluentForm'da) ve **`mc4wp`** (hicbir sayfada basilmiyordu) |
| Site haritasi (fdartgallery) | `wp-sitemap*.xml` 404 yerine **200**; indeks + 8 alt harita, 329 adres (6i) |
| Uc dil (chestnyznak) | Menuden ulasilan her adres + 23 blog yazisi tr/en/ru; dil dusuren baglanti **0** (6e) |
| Demo temizligi (chestnyznak) | Site haritasi **523 → 97** adres, 370 kayit taslaga (6i) |
| 404 sayfasi | Uc dile cevrildi, dile uygun faydali baglantilarla (6i) |
| Blog | `deploy/blog/` araclari + haftalik Routine (`trig_01Q2abbcd19d1CQem3fs5Z7M`, Sali 06:00 UTC); Telegram'dan 3 yazi x 3 dil (6j) |

---

## 6. Tuzaklar — hepsi yasandi, tekrarlanmasin

### 6a. Dogrulama — bunlar olmadan "calisiyor" denmez

- **HTTP 200 gorsel dogrulama DEGILDIR.** revslider kapatildiginda ana sayfa ustte
  ham `[rev_slider ...]` metni gosteriyor ve yine 200 donuyordu.
- **VARLIK kontrolu GORUNURLUK kontrolu DEGILDIR.** Bir keresinde HTML 303 KB'di,
  `getElementById` her seyi buluyordu, butun testler yesildi — sayfa bomboştu
  (`body{display:none}`). Tarayici testinde `getComputedStyle(body).display` ve
  `getBoundingClientRect()` olculur.
- **Konteynerden `curl --resolve` YOK SAYILIR** (`HTTPS_PROXY` var; istek yine
  Cloudflare'den gider, yaniltici 200 doner). Dogru test **sunucudan**:
  `curl --resolve <host>:443:127.0.0.1 ...`.
- **Konteynerden `openssl s_client` GECERSIZ** — egress proxy kendi sertifikasini
  sunar (`CN=Egress Gateway SDS Issuing CA`).
- **Eklenti/handle kullaniliyor mu?** `post_content` + `postmeta` aramak YANILTIR.
  `_elementor_data`, `elementor_library`, tema widget alanlari, `cpt_layouts`,
  tema secenekleri ve **Gutenberg blok yorumlari** da taranmali. revslider
  "kullanilmiyor" sanilip kapatildi, **canli ana sayfa 2 gun bozuk kaldi**.
- **Form referansi her zaman kisa kod degildir:** `<!-- wp:contact-form-7/
  contact-form-selector {"id":64} -->` ortada `[contact-form-7` YOK.
- **`postmeta` sayimlarinda `post_type != 'revision'` FILTRESI OLMADAN cikan
  sayi KULLANIM DEGILDIR.** `mc4wp` icin bu dosyada uzun sure "formu **8 yerde
  gomulu**" yaziyordu ve bir icerik karari gibi sunuluyordu; sayinin kaynagi
  revizyonlari filtrelemeyen bir `_elementor_data` sorgusuydu. Kirilim
  alininca revizyon HARIC kullanim **0** cikti (CF7'de de ayni tuzak vardi:
  canli 57 / dev 58 eslesmenin hepsi revizyonda). Son olcut **basilan HTML**:
  alti temsili sayfada `mc4wp` gecisi 0.
- **Yorum satirina bakip cikarim yapmayin, yaniti olcun.** `gzip_types` yorumdaydi
  diye "sikistirilmiyor" sanildi; olcunce gzip'li cikti.
- **Ekran goruntusuyle gelen kanitta durum cubugunu okuyun** (VPN, roaming).
- Cevrimdisi tarayici testinde `location.pathname` asla `/` olmaz — yol
  kilavuzlu scriptler hic calismaz ve yanlis olcum verir. **Karsilastirma icin
  canli sayfayi da ayni testten gecirin.**

### 6b. nginx

- **`location = /robots.txt` bloguna `try_files` SART.** Yoksa nginx 404 doner ve
  Yoast'in `Sitemap:` satiri kanonik adresinde bulunmaz.
- **Kendi `add_header`'i olan location ust seviyeyi MIRAS ALMAZ** — staging
  noindex basligi statik dosya blogunda TEKRAR edilir.
- **`$realip_remote_addr` kullanin, `$remote_addr` DEGIL** — real_ip modulu
  `$remote_addr`'i ziyaretcinin IP'siyle degistirir.
- **Vhost'ta ad degistirirken YALNIZCA `server_name` satirini degistirin**;
  toplu `sed` sertifika yollarini ve kok dizini de bozdu.
- **80 portundaki blokta server seviyesinde `return` KOYMAYIN** — ACME dogrulamasi
  404 alir. Yonlendirme `location / { return 301 ... }` icine konur.
- **Sertifika icin `certbot certonly --webroot`**, `--nginx` degil (vhost'u yeniden yazar).
- **`fastcgi_cache_valid` yalnizca `200`** olmali
  (`deploy/nginx/templates/fastcgi-php.conf.template`). `301 302` da onbellege girerse
  duruma bagli bir yonlendirme herkese servis edilir ve tarayici onu KALICI sayar.
  Ayirt edici isaret: **`HEAD` 200 ama `GET` 301**.
- **`update-cloudflare-ips.sh` iki dosyayi AYNI listeden uretir**; ayrisirlarsa
  mesru ziyaretciler 444 yer.
- **Regex location'lar TANIM SIRASINA gore denenir** — `snippets/font-gzip.conf`
  bu yuzden `snippets/wordpress.conf`'tan ONCE include edilir.

### 6c. SSH

Baglanma tarifi 3. bolumde. Burasi **anahtar yonetimi**.

`sshd_config.d/*.conf` alfabetik yuklenir ve **her anahtar icin ILK deger kazanir**
— bizim dosyamizin adi bu yuzden `00-hardening.conf`.

`root` **`prohibit-password`** modunda — root'a yalnizca anahtarla girilir.
`ubuntu` var ama `authorized_keys`'i BOS; `debian`/`admin` HIC YOK.
**Dogru kullanici her zaman `root`.**

#### `/root/.ssh/authorized_keys` — kimin anahtari var (02.09.2026)

| Parmak izi | Etiket | Ne |
|---|---|---|
| `SHA256:i7IciIuo…` | `claude-persistent-fdartgalleryuk` | **Kalici** — private kismi `VPS_SSH_PRIVATE_KEY` env'inde |
| `SHA256:zQKtspd2…` | `claude-session-016mZ8S3-ephemeral` | ana oturum |
| `SHA256:DwKDbB2c…` | `claude-session-01Y2RFQy-ephemeral` | fdartgallery oturumu |
| `SHA256:6TJ2koLB…` | `claude-session-01MuywGH-ephemeral` | chestnyznak oturumu |
| `SHA256:19dkzsf0…` | `claude-session-01FSqNzN-ephemeral` | chestnyznakuk oturumu |

Bir anahtari iptal etmek = o satiri silmek. **`authorized_keys` her baglantida
okunur — sshd yeniden BASLATILMAZ.**

#### Yeni oturuma erisim verme (iki taraf)

**Yeni oturum:** kendi anahtarini uretir, `.pub`'i `deploy/claude-session-key.pub`
olarak dala push eder, DURUR ve haber verir (3. bolum Adim 1).

**Erisimi olan oturum:** `.pub`'i alir, sunucuda `authorized_keys`'e ekler.
Heredoc'u SSH komut dizesine gommeyin — script yerelde yazilir, `base64 -w0` ile
aktarilir (3. bolum sonundaki uyari). Islem sirasi: **once zaman damgali kopya**,
sonra etiket zaten varsa atla / yoksa ekle, `chmod 600`, sonunda
`ssh-keygen -lf` ile **butun** parmak izlerini bas ve tabloyu buraya isle.

> **Yeni oturum giremiyorsa once HANGI ANAHTARI SUNDUGUNA bakin.** Istemcideki
> `Offering public key: ... SHA256:...` satirini yukaridaki tabloya karsi tutun.
> Farkliysa sorun sunucuda degil — anahtar henuz eklenmemistir.

> **TUZAK (02.09.2026): oturumlar birbirine mesaj gonderemiyor.** Bulut
> oturumlari `ListAgents`'ta gorunmuyor, `SendMessage` "not reachable" doner.
> Oturumlar arasi tek kanal **kullanici** ve **git dali**. Bu yuzden yeni oturum
> anahtarini repoya push eder, tarifi de kullanici yapistirir.

> **TUZAK: tarifi almayan oturum bosuna ariyor.** chestnyznakuk oturumu, seed
> promptu tuneli anlatmadigi icin ~3,7 $ boyunca "makinede kullanilabilir anahtar
> malzemesi" aradi ve "SSH imkansiz" sonucuna vardi. **Yeni oturum acarken 3.
> bolumun tamami seed prompta konur.**

Parola girisi hala **acik** (0. bolum madde 4). Acil durum: OVH Manager → KVM konsolu.

### 6d. E-posta

**FluentSMTP kayitli parolayi `LOGGED_IN_KEY`+`LOGGED_IN_SALT` ile sifreler.**
Yeni `wp-config.php` uretilince cozulemez → saglayici 401. Onlem (dort sitede
yapildi): wp-config'e sabit `FLUENTMAIL_ENCRYPT_KEY` / `_SALT`.

- **`wp_mail()` TRUE donmesi teslimat demek DEGIL** — saglayici kuyrukta reddedebilir.
- **`sudo -u <user> -E wp` ortam degiskeni TASIMAZ** (sudoers `env_reset`); sir
  gecici PHP dosyasina yazilip `wp eval-file` ile calistirilir.
- **Ham `update_option('fluentmail-settings',...)` KULLANMAYIN.** Dogru API
  `fluentMailSetSettings()` — duz metin alir, sifrelemeyi kendi yapar.

**chestnyznak giden posta CALISIYOR** (dogrulandi 02.09.2026): son `failed`
01.09 16:20, ilk `sent` 16:41; sonrasinda 0 hata. Kayitli parola sunucudan
SMTP **ve** IMAP ile sinandi, ikisi de basarili.

> **`TIMEWEB_MAIL_PASSWORD` env degiskenini KULLANMAYIN** — icindeki deger
> YANLIS (SMTP ve IMAP reddediyor). FluentSMTP'ye yazmak **CALISAN postayi kirar**.

Gonderim yapmadan kontrol: parolayi `fluentMailEncryptDecrypt($ham,'d')` ile coz,
mod 600 dosyaya yaz, `smtplib.SMTP_SSL(...).login()` ile sina, `shred -u` ile sil.
Cogu zaman `<onek>_fsmpt_email_logs` tablosundaki `status` dagilimi yeterlidir.

**Form bildirim politikasi (chestnyznak).** Ekip bildirimleri To `info@`,
Cc `oguzk@`; `admin_email` (`swordbros@gmail.com`) **DEGISMEZ**. Arac:
`deploy/scripts/eposta-alicilari.php` (`dry` destekli, yeniden calistirilabilir).

> **Otomatik yanitlara ekip adresi YAZILMAZ** — FluentForm'da `sendTo.type=field`
> olan bildirimlerin alicisi BASVURANIN kendisidir; ekip kopyasi yalnizca `cc`.
> **"E-postayla paylas" dugmesi bir alici ayari DEGILDIR** — `mailto:` ziyaretcinin
> istemcisini acar; alici kismi bos birakilir.

### 6e. Cok dillilik — Polylang (chestnyznak, canli)

**Neden Polylang:** ucretsiz surumu sinirsiz dil destekler (TranslatePress 1,
WPML ucretli). Ayarlar: `force_lang=1`, `hide_default=1`, `rewrite=1`,
`browser=0`, **`redirect_lang=1`**.

> **`redirect_lang=1` SART.** 0 birakilinca `/en/` cevrilmis ana sayfayi degil
> blog arsivini gosteriyordu.

**Polylang dili URL onekinden okur.** `/en/` icindeki bir baglanti `/iletisim/`
derse onek duser ve site Turkceye doner. Cerez ve icerik dili **kazanmaz**
(ustelik `fastcgi_cache` cerezi anahtara katmaz). **Tek cozum sayfayi gercekten
cevirmektir.**

| Turkce | English | Русский |
|---|---|---|
| `/hakkimizda/` | `/en/about-us/` | `/ru/o-nas/` |
| `/iletisim/` | `/en/contact/` | `/ru/kontakty/` |
| `/kod-sorgulama/` | `/en/code-lookup/` | `/ru/proverka-koda/` |
| `/blog-standard/` | `/en/news/` | `/ru/novosti/` |
| `/shop/` | `/en/solutions/` | `/ru/uslugi/` |

Menuler: tr=171, en=372, ru=373. Araclar `deploy/wordpress/i18n/` (01→16) +
JSON sozlukler (`sayfa-cevirileri.json`, `yazi-cevirileri/parti-1..6.json`). Olcut: `/en/` ve `/ru/` sayfalarinda **dil dusuren baglanti 0**.

> **SCRIPTLERE POST ID YAZILMAZ.** Dev ve canlida ayni degiller (EN ana sayfa
> dev 37686, canli 37687). Her sey `page_on_front`, `pll_get_post()`,
> `get_nav_menu_locations()` ve slug aramasindan cozulur.

> **EN/RU slug'lari TAHMIN EDILMEZ.** Bir yazida tahmin edilen yedi ic
> baglantinin yedisi de yanlisti. `pll_get_post( $tr_id, $dil )` ile cozun.

**Adres uc ayri yerde duzeltilir:** menu ogeleri (`_menu_item_url`),
sayfa/duzen icerigi, tema widget'i (`fd-i18n-layouts.php` sunucu tarafinda cevirir).

**Magaza:** `/en/solutions/` ve `/ru/uslugi/` `[products]` ile YAPILMADI — urun
baglantilari `/product/...` oneksiz oldugu icin ziyaretci Turkceye duserdi.
Yerine cevrilmis paket kartlari kondu. `product` tipini Polylang'de cevrilebilir
yapmak DENENMEDI: ucretsiz surum WooCommerce entegrasyonu icermez.

**Altbilgi dile gore:** temanin `qwery_get_custom_footer_id()` fonksiyonu
mu-plugin'den (`lead-mu-plugins/fd-i18n-layouts.php`) ayni adla tanimlanarak
degistirilir — mu-plugin'ler temadan ONCE yuklenir.
`theme_mod_footer_style` filtresi ve `get_footer` kancasi DENENDI, ikisi de OLMADI.

#### TUZAK: sayfaya ve eleman kimligine civilenmis scriptler

Dil kopyalarinda Elementor eleman `id`'leri YENIDEN URETILIR ve sayfa ID'si
degisir. Bu yuzden:
- yol/sayfa kilavuzu → `document.body.classList.contains('frontpage')`
  (bu tema on sayfaya `home` DEGIL `frontpage` basar),
- eleman secici → kalici sinif `cz-el-<kimlik>`.

> Elementor'da "CSS Classes" denetiminin adi eleman turune gore DEGISIR:
> widget'ta `_css_classes`, section/column'da `css_classes`.

> **Bir secici icinde virgullu liste ile duz metin degisimi YAPMAYIN.**
> `body.page-id-5002 .a, body.page-id-5002 .b` → `body.frontpage, body.home .a,
> body.frontpage, ...` olunca listede **ciplak `body.frontpage`** olustu →
> `body{display:none}` → **butun sayfa beyaz**.

> **`_elementor_data` uzerinde ham `strtr` CALISMAZ** — Turkce harfler `\uXXXX`
> bicimindedir. JSON'u coz, PHP tarafinda cevir, `wp_json_encode` ile kodla.
> (`wp_json_encode` `/` karakterini `\/` yapar — kontrol dizesinde egik cizgi
> kullanmayin.)

### 6f. Elementor / tema (chestnyznak)

> **KURAL (kullanici karari):** siteye eklenen her icerik **Elementor ile
> duzenlenebilir** olmalidir. JS enjeksiyonu, Custom HTML widget'i veya tema
> widget alani KULLANILMAZ. Once dev, onaydan sonra canli.

Sitenin gorunur bolumlerinin buyuk kismi tema widget alanindaki tek bir 57 KB'lik
Custom HTML widget'indan **16 JS enjeksiyonuyla** uretiliyordu. `CZLEAD` bolgesi
Elementor'a tasindi (`deploy/scripts/migrate-czlead-to-elementor.php`;
blogun arsivi `deploy/wordpress/cz-lead-block.html`).

**Kalan 14 enjeksiyon** hala widget'ta: `cz-hero-block`, `cz-header`, `cz-banner`,
`cz-news`, `cz-marquee`, `cz-home`, `cz-wa`, `cz-contact`, `cz-shop`, `cz-cookie`,
`cz-audit`, `cz-relabel`, `cz-fixlinks`, `cz-btn-brand`, `cz-urun-talep`.

**Elementor editoru 500 veriyordu** — JS yapilandirmasi tek `json_encode`
cagrisinda 256M'yi asiyordu. Dort sitede de: FPM `memory_limit` **512M** +
wp-config `WP_MAX_MEMORY_LIMIT` **512M** (`WP_MEMORY_LIMIT` 256M kalir).
**Ikisi de gerekli** —
`php_admin_value` HARD tavandir, WordPress ise kendi degerine ceker.

> **wp-cli ile Elementor verisine yazarken `--user=<admin>` SART.** Yoksa
> `unfiltered_html` false doner ve WordPress `<style>`, `<script>`, `<template>`
> etiketlerini **sessizce siler**. Yazan script kayittan sonra kendini
> dogrulamali, tutmazsa geri almalidir.

- **`elementor_canvas` sablonu bos tuvaldir** (menu/logo/footer basilmaz).
  Reklam acilis sayfalari **bilerek** canvas — onlara dokunmayin. Sohbet iframe
  sayfalari da canvas KALMALI.
- **Bir bileseni onarmadan once o sayfada AYNI ISI yapan baska sey var mi bakin.**
  Sayfa #2'de olu bir CF7 blogu "onarildi" ve sayfada zaten calisan FluentForm
  varken ikinci form olustu. Olcut: `<form>` etiketlerini saymak.
- **Birebir dize eslesmesi UTF-8'de sessizce tutmaz** — `<div> </div>` icindeki
  "bosluk" U+00A0 cikti. Regex (`/su`) kullanin.

### 6g. Basvuru formu (chestnyznak ana sayfa)

`lead-mu-plugins/fd-lead-capture.php` (chestnyznak) — uc
`POST /wp-json/fd-lead/v1/submit`:
1. **Kalici kayit** (`fd_lead` icerik tipi, panelde "Basvurular"),
2. e-posta bildirimi (`Reply-To` = basvuran),
3. Telegram aktarimi **sunucu tarafinda** (`chat_id` wp-config'de).

Ziyaretciye "basarili" demenin **tek olcutu veritabani kaydidir**. `stored`
donmezse form acik kalir, hata kutusu + WhatsApp yedegi cikar. Spam: bal kupu
alani + IP basina saatte 8 kayit.

wp-config sabitleri (repoya yazilmaz): `FD_LEAD_RELAY_URL`, `FD_LEAD_CHAT_ID`,
`FD_LEAD_NOTIFY_EMAIL`, `FD_LEAD_NOTIFY_CC`. **Dev'de `FD_LEAD_CHAT_ID` BOS.**

> **Google Apps Script tuzagi:** basarili POST'a **302** doner ve yaniti
> `script.googleusercontent.com`'a yonlendirir (o adres yalnizca GET alir).
> `redirection => 0` ve **302 basari sayilir**.

### 6h. Hiz ve onbellek

**Once olcun, sonra dokunun.** Sunucu darbogaz degil (TTFB 16-26 ms); darbogaz
**istek sayisi** ve **harici kaynak**.

**Varlik diyeti** — `fd-asset-diyet.php` **iki AYRI dosya**, karistirmayin:
`fdart-mu-plugins/` (fdartgallery) ve `lead-mu-plugins/` (chestnyznak). Ikisi de
sayfada karsiligi olmayan dosyalari kuyruktan cikarir; kosul saglanmiyorsa dosya
AYNEN kalir (hata durumunda eksik degil, fazla yuklenir).

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

**Fontlar** — `fd-font-hizlandirma.php`: googleapis stylesheet'leri **tek**
istege birlestirilir, agirlik listesi 18 → 9, `fonts.googleapis.com` ve
`fonts.gstatic.com` icin **preconnect** (gstatic'te `crossorigin` SART).
Aile ELENMEDI — kit tipografisi sayfada kullaniliyor, aile atmak basliklari
sistem fontuna dusururdu; bu bir **tasarim** karari.

`deploy/nginx/snippets/font-gzip.conf` — Ubuntu `mime.types`'ta `ttf`/`otf` YOK,
nginx `application/octet-stream` veriyor ve o tur global `gzip_types`'a
**konmamali**. Cozum: yalnizca `.ttf|.otf|.eot` eslesen location icinde gzip.
`woff/woff2` bilerek disarida (zaten sikistirilmis).

#### Izleme parametreli istekler (`?utm_*`)

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

Kapsam yalnizca fdartgallery (`$wpc_qs_norm` map'i). `ref`, `source`, `campaign`
BILEREK izleme sayilmaz — ortaklik kodu olabilirler.

#### Yapilmayanlar ve nedeni

- **`sourcebuster-js` + `wc-order-attribution` DOKUNULMADI** — ilk temasi giris
  sayfasinda kaydeder; yalnizca sepette yuklemek veriyi eksiltmez, **YANLIS**
  yapar. Kaldirilacaksa WooCommerce ayarindan ozellik komple kapatilir.
- **`akismet-frontend.js` DENENDI, VAZGECILDI** — Akismet altbilgideki FluentForm
  bultene bal kupu alani basiyor ve o alani bu JS dolduruyor.
- **Elementor deneysel ozellikleri** acilmadi (agir temayla render riski).
- **TTF → WOFF2** yapilmadi (gzip zaten %46 verdi; `@font-face` uretimi temanin isi).
- **Brotli** kurulu degil (Ubuntu deposunda `libnginx-mod-brotli` yok).

### 6i. Icerik ve SEO temizligi (chestnyznak)

**Tema demosu aramaya siziyordu.** Site haritasi **523 → 97** adres, yayindaki
`page` 191 → 27, 370 kayit taslaga. Arac:
`deploy/scripts/demo-icerik-temizle.php` (`dry` ve `geri-al` destekli).

> **KOK SEBEP sayfalarin yayinda olmasi DEGILDI:** Yoast'ta `noindex-cpt_portfolio`,
> `noindex-cpt_layouts`, `noindex-tribe_events`... **hepsi `false`**'ti. Tip
> bazinda noindex olmadan yeni demo icerik ayni yerden geri doner. 21 ayar acildi.

> **TUZAK: "menude var" olcut DEGILDIR.** Temanin **atanmamis** demo menuleri
> hala kayitli (`Main Menu` 179 oge, `Developer's menu`, `Footer Menu 2/3`,
> `Simple Menu`) ve demo sayfalara link veriyor. Ilk denemede sonuc **174 gercek
> / 26 demo** cikti — tam tersi. Gercek olcut: **bir tema konumuna ATANMIS**
> menuler (`get_nav_menu_locations()`). Dogru sonuc: 27 gercek, 164 demo.

> **`cpt_layouts` TASLAGA CEKILMEZ** — sitenin kullanilan basligi (`Header Main`
> #4614) ve altbilgisi (`Footer Default` #4105) o tipte. Yalnizca `noindex`.

> **TUZAK (02.09.2026): menude olmayan sayfa "demo" demek DEGILDIR.**
> `rusya-markalama-danismanlik` bir **reklam kampanyasinda** kullaniliyordu ve
> temizlikte taslaga cekildi; kullanici bildirdi, geri alindi. Menude olmayan
> ama isletmeye ait sayfalar (canvas sablonu, TR/RU baslik, form icerigi)
> kapatilmadan once **kullaniciya tek tek sorulur**. Kalan liste 0. bolumde.

**404 sayfasi** — `HTTP 404` ve `noindex,follow` ZATEN dogruydu; eksik olan
metindi. `lead-mu-plugins/fd-404-cevirisi.php` + `fd-404-metinleri.json` ile uc dile cevrildi ve
dile uygun uc faydali baglanti eklendi.

> **`gettext` filtresinde `is_404()` sarti ZORUNLU** — filtre her istekte calisir,
> sartsiz birakilirsa "Homepage" gecen HER yer degisir.

> Faydali baglantilar **sablona dokunmadan** eklendi: aciklama metni
> `wp_kses(..., 'qwery_kses_content')` icinden geciyor ve o kume `<a>`, `<span>`,
> `<br>`'a izin veriyor. Adresler JSON'da yer tutucu, PHP'de
> `fd_i18n_yol_esleme()` haritasindan cozuluyor. **Tema dosyasi degistirilmedi,
> cocuk temaya kopyalanmadi** (guncellemede kaybolur / catallar).

#### Site haritasi 404 donuyordu (fdartgallery)

`wp-sitemap.xml` GECERLI XML donuyordu ama HTTP durumu **404** idi; `robots.txt`
o adresi ilan ettigi icin arama motorlari haritayi reddeder.

**Kok sebep:** rewrite dogru calisiyor, ama `WP::query_posts()` istegi siradan
bir gonderi sorgusu gibi calistiriyor. Bu sitede **yayinlanmis `post` sayisi
SIFIR** → sorgu bos → `WP::handle_404()` `status_header(404)` basiyor.
`render_sitemaps()` sonra indeksi basip `exit` ediyor ama durumu 200'e geri
CEKMIYOR. Yani hata "harita uretilmiyor" degil, **bos blog yuzunden istek 404
damgasi yiyor**.

**Cozum:** `fdart-mu-plugins/fd-site-haritasi-durum.php` — cekirdegin tam bu is
icin sundugu `pre_handle_404` kancasi. Yalnizca `sitemap` sorgu degiskeni
doluyken `true` doner. Sonuc: indeks + 8 alt harita **200**, 329 adres;
gercek 404'ler ve `/wp-sitemap-posts-post-1.xml` (0 yazi) **404 kaliyor**.

> **TUZAK: dev'deki ayni belirti BASKA BIR OLAYDI.** Dev'de de 404 goruluyordu
> ama orada **kasitli ve dogru**: cekirdek `sitemaps_enabled()` `blog_public`'e
> bakar, staging guard onu 0'a zorlar. Ayirt eden olcut **govde**: canlida
> `<sitemapindex`, dev'de `<html` (tema 404 sayfasi). Iki ortamda ayni durum
> kodunu gormek ayni sebep demek DEGILDIR.

**Sayfa slug'i degisiminde WordPress 301 KURMAZ.** `wp_check_for_changed_slugs()`
hiyerarsik tiplerde (yani `page`) erken doner. Cozum:
`deploy/wordpress/fdart-mu-plugins/fd-eski-adresler.php` — yalnizca istek 404'e
dustugunde calisir, sorgu dizesini korur. **Sira: once mu-plugin, sonra slug.**

### 6j. Blog

Surec ve uslup kurallari: `deploy/blog/README.md`. Konu kuyrugu ve yayinlanan
yazilar: `deploy/blog/BACKLOG.md` (**tek dogruluk kaynagi**).

Standart: 1200–1600 kelime, 8–11 `<h2>`, SSS bolumu, 5–8 ic baglanti (biri
mutlaka `/kod-sorgulama/`), kapak gorseli (`deploy/blog/cover.php`), kategori
`modern`, yazar 3, Yoast baslik ≤60 / aciklama ≤155.

**Araclar** (ikisi de `dry` destekli, post ID gomulmez):
- `deploy/blog/yazi-yayinla.php` — Turkce yaziyi yayinlar (kapak, kategori, Yoast, dil).
- `deploy/blog/ceviri-yayinla.php` — EN/RU cevirilerini yayinlar + Polylang baglar.
- `deploy/blog/telegram-cek.py` — genel Telegram kanalini `t.me/s/<kanal>`
  uzerinden sayfalayarak ceker (Bot API/oturum gerekmez).

**Telegram bir icerik kaynagi.** `t.me/globalznak_rusya` (@global_znak).
Telegram yazilari 150–500 kelime — **hazir blog yazisi DEGIL**, kaynak malzemedir
ve birlestirilir.

> **ORTUSME ONCE OLCULUR.** Ilk plan 5 yaziydi; mevcut yazilarla karsilastirinca
> ikisi elendi ("hangi urunlerde zorunlu" 4 yazida islenmis, "adim adim yol
> haritasi" ayni yazinin bolumu). Olcmeden yazmak kendi yazilarimizla rekabet
> eden kopya icerik uretir.

> **TUZAK: wp-cli `--porcelain` bu kurulumda GUVENILIR DEGIL.** Elementor'un
> shutdown kaydedicisi STDOUT'a deprecation uyarisi basiyor; `$(...)` onu da
> yakaliyor ve degisken bozuluyor. Olusturma isi kabuktan degil PHP'den yapilir.
> Kabuktan kimlik yakalamak gerekirse `| grep -oE '^[0-9]+$' | head -1` sart
> (`wp user list --field=ID` icin de gecerli).

> **TUZAK: ceviri yazilarda `uncategorized` yapisiyordu.**
> `wp_set_post_terms(..., append=true)` birakilinca WordPress'in ekleme aninda
> atadigi varsayilan kategori kaliyor. **`append=false` sart.** Sira da onemli:
> Polylang, **dil atandiktan sonra** atanan kategoriyi kendiliginden o dildeki
> karsiligina esler — `pll_set_post_language()` kategoriden ONCE cagrilir.

> `/comments/feed/` her yazida 404 doner; **sitede oteden beri boyle**, bizim
> eklediklerimize ozgu degil. Kirik baglanti taramasinda yanlis alarm vermesin.

### 6k. Rusya erisimi (chestnyznak)

**Kanitlandi:** engel Cloudflare IP araliklarina. VPN kapali, ayni telefon:
`chestnyznak.com.tr` (turuncuyken) → `ERR_TIMED_OUT`; ayni sunucudaki
`origin.chemiartclick.uk` (gri) → **acildi**. DNS temiz, TCP hic kurulmuyor.

Gri buluta cekildi (geri alma yedegi `scratchpad/chestnyznak-A-before.json`).
Origin'i acmadan once nginx'te kaynak ayrimi yapildi: `cloudflare-only.conf`
fdartgallery vhost'larinda VAR, chestnyznak'ta **YOK**.

**Gri olduktan sonra da acilmiyor.** Geriye tek degisken alan adi.
`chestnyznak-test.chemiartclick.uk` testi bunun icin kuruldu:
2 acilmaz + 1 acilirsa → engel **isimde**; 2 acilirsa → yeni alan adi cozer.

Gecici test kaynaklari (**is bitince silinecek**): `origin.chemiartclick.uk` ve
`chestnyznak-test.chemiartclick.uk` A kayitlari, `origin-test.conf`,
`/var/www/origin-test`, `certbot delete --cert-name origin.chemiartclick.uk`.

### 6l. Diger

- **`rsync --exclude 'database/'` her seviyedeki `database` dizinini atlar** —
  Elementor Pro'nun `submissions/database` klasoru eksik kalip site fatal verdi.
  Kaliplar `/database/` seklinde koke sabitlenir.
- **Yedegin tablo oneki `wp_` olmayabilir** — `deploy-site.sh` onegi DB'den okur.
- **`wp rewrite flush` ASLA `--skip-plugins` ile calistirilmaz** — kural tablosu
  eklenti kurallari olmadan yeniden uretilir (WooCommerce urun/magaza adresleri).
  Bir kez oyle calistirildi; hasar, dokunulmamis canli ile dev'in **saklanan**
  `rewrite_rules` anahtarlari diff'lenerek denetlendi: fark tam 20 kural ve hepsi
  kaldirilan eklentinin kendi `mc4wp-form/...` kurallariydi, baska hicbir sey
  dusmemisti. Yine de dogru bicimiyle tekrar flush edildi.
- **Eklenti kaldirma sirasi:** ozel post tipi YALNIZCA eklenti aktifken kayitlidir.
  Once form/kayit disa aktarilir ve silinir, SONRA eklenti kapatilip dosyalari
  silinir. `wp export` bu yuzden `--skip-plugins` ile calismaz; `wp post get` ve
  `wp post meta list` ham DB satirini okudugu icin calisir.
- **Cloudflare Universal SSL yalnizca TEK seviye alt alan adi kapsar.**
  Kural: yeni alt alan adlari tek seviye olsun.
- **`clone-site.sh` sonrasi `chown` hemen gelir**; yoksa hedef kullanici
  `wp-config.php`'yi okuyamaz ve tum wp-cli adimlari duser.
- **Klonda `object-cache.php` ve `advanced-cache.php` drop-in'leri silinir** —
  tanimsiz drop-in varsayilan veritabanina baglanip **eski** `siteurl`'u servis etti.
- **SPF tek kayit olmak zorunda** — ikinci kayit ikisini de gecersiz kilar.
  Coklu dagitim icin Email Worker (`deploy/cloudflare/email-worker.js`).
- **`wp-super-cache` aktif ama etkisiz** (drop-in yok, `WP_CACHE` tanimsiz). Etkinlestirilirse nginx
  sayfa onbellegiyle cakisir — **aktif etmeyin**.
- **WebP: Accept pazarligi (Vary) kullanilmadi** — Free planda `Vary: Accept`
  donen gorseller edge'de BYPASS olur. Ayri `.webp` URL yontemi secildi.
- **`webmail.chestnyznak.com.tr`** kendi sertifikasiyla `https://mail.timeweb.com/`
  adresine 301; kayit **gri**. `cloudflare-only.conf` EKLENMEZ.
  Vhost: `deploy/nginx/sites-available/webmail.chestnyznak.com.tr.conf`.
  > `mail.chestnyznak.com.tr` **bilerek dokunulmadi** — posta istemcisinde
  > sunucu adi olarak kullaniliyor olabilir.

---

## 7. Calisma kurallari

- Tum gelistirme `claude/ovhcloud-vps-multisite-0eb3xj` dalinda; izinsiz baska
  dala push yok, izinsiz PR yok.
- **Sirlar asla commit edilmez**: token, DB sifresi, private key, `wp-config.php`.
- **Once dev, sonra canli.** Canliya alirken dev'in HTML'i **kopyalanmaz** —
  ayni donusum canlinin kendi icerigi uzerinde calistirilir, cikti denetlenir.
- Canliyi etkileyen her islemden (DNS, guvenlik duvari, sertifika, veri
  degisikligi) **once yedek**, mumkunse `dry`, sonra kullanici onayi.
- **Icerik kapatmadan once kullaniciya sorun.** Menude olmaması bir sayfanin
  kullanilmadigi anlamina gelmez — reklam kampanyalari menusuz sayfalara trafik
  gonderir (6i).
- `deploy-site.sh` `rsync --delete` kullanir → repo eksikken calistirmayin.
- **UC DIL KURALI (chestnyznak).** Eklenen her yeni icerik — blog yazisi, sayfa,
  menu ogesi, buton metni — **Turkce + English + Русский** olarak eklenir.
  Yeni yazi: Turkcesi yayinlanir, `pll_set_post_language()` ile dili isaretlenir,
  EN/RU kopyalari acilir, `pll_save_post_translations()` ile uc kayit baglanir.
  Menuye oge eklenirken **her uc menuye** (tr=171, en=372, ru=373).
- **Degisiklikten sonra dogrula: HTTP kodu yetmez** (6a).
- **Yeni oturum acarken 3. bolumun tamami seed prompta konur** — oturumlar
  birbirine mesaj gonderemez, yeni oturum yalnizca seed promptta yazani bilir.
  Bir oturuma SSH verildiginde 6c'deki anahtar tablosu **ayni commit'te** guncellenir.
- **Bu dosyayi guncel tut ve KISA tut**: yeni tuzagi 6'ya, biten isi 5'e,
  bekleyeni 0'a yaz. Uzun anlatiyi commit mesajina birak.
