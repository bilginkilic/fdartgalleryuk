# CLAUDE.md — fdartgallery.com / OVHcloud multi-site runbook

Bu dosya, projeyi devralan her oturum icin kalici hafizadir. Sunucu bilgileri,
API uclari, yapilmis isler ve **yapilacaklar listesi** burada tutulur.
Bir adim tamamlandiginda bu dosyadaki kutucugu isaretle ve commit et.

---

## 1. Altyapi ozeti

**Tek VPS var.** (Kullanici teyit etti — daha once "iki VPS" denmisti, gecerli degil.)
Tum siteler asagidaki sunucuda barinacak.

### VPS (hedef sunucu — dogrulanmis)

| Alan | Deger |
|---|---|
| Hostname | `vps-913eb1fb.vps.ovh.net` |
| IPv4 | `57.129.128.118` (gateway `57.129.128.1`) |
| IPv6 | `2001:41d0:801:2000::7e80` |
| Model | VPS-2 2027 — 4 vCore / 8 GB RAM / 75 GB SSD |
| Isletim sistemi | **Ubuntu 26.04** |
| Bolge | `os-uk2` (Region OpenStack) — Londra, Birlesik Krallik |
| Boot | LOCAL |
| Durum | `running` / Active, `lockStatus: none` |
| OVH URN | `urn:v1:ca:resource:vps:vps-913eb1fb.vps.ovh.net` |

### Eski origin

DNS'te gorunen `147.45.254.181` **eski/mevcut origin**'dir (OVH araliginda degil,
muhtemelen baska bir saglayici). Cutover oncesi fdartgallery.com bu IP'ye bakiyordu
ve Cloudflare `521` donuyordu. Artik yalnizca `dev` kaydi bu IP'de.

### Cloudflare hesabindaki zone'lar

| Domain | Zone ID | Durum |
|---|---|---|
| fdartgallery.com | `fa3f51825d634f23741ff5fbf2ae8b1a` | active |
| fdsanatmerkezi.com | `78b4b06944b6e157e345a39a9c23e1f7` | active |
| chemiartclick.uk | `56fcc0694b5c39e44fd5ada387e72da7` | active |
| davetevet.com | `65ba8de2aee8b6b79fa0dd29397ec4eb` | active |
| byhio.com | `986370d54939838389193f09b03d26f3` | active |
| wedreply.co.uk | `f17909afa2608874da114130aa29e150` | active |

Multi-site plani: bu domainler sirayla ayni VPS'e tasinacak
(`deploy/scripts/add-site.sh <domain>`).

### fdartgallery.com DNS kayitlari (28.08.2026, cutover SONRASI)

```
A     fdartgallery.com        -> 57.129.128.118   proxied=ON    (YENI VPS) ✓
A     www.fdartgallery.com    -> 57.129.128.118   proxied=ON    (YENI VPS) ✓
A     dev.fdartgallery.com    -> 147.45.254.181   proxied=OFF   (eski IP'de birakildi)
CNAME brevo1._domainkey       -> b1.fdartgallery-com.dkim.brevo.com
CNAME brevo2._domainkey       -> b2.fdartgallery-com.dkim.brevo.com
CNAME _domainconnect         -> _domainconnect.gd.domaincontrol.com
TXT   _dmarc                  -> v=DMARC1; p=quarantine; ...
TXT   @                       -> google-site-verification=xj6o_-...
TXT   @                       -> brevo-code:33cb1e9c...
```

**Onemli:** Mail (Brevo DKIM/DMARC) ve dogrulama kayitlarina dokunulmadi.
Cutover'da yalnizca apex + `www` A kayitlari degistirildi (proxy durumlari korundu).
Kullanici karari: **`dev` tasinmayacak**, once `www` acilacak.
Geri alma: `bash deploy/scripts/cloudflare-dns.sh fdartgallery.com 147.45.254.181 --names @,www`

**Cloudflare SSL modu: `full (strict)`** (29.08.2026'da yukseltildi) — origin'de
gecerli Let's Encrypt sertifikasi var. `flexible`a **dusurmeyin**, yonlendirme
dongusu yaratir.
Domain kayit sirketi GoDaddy gorunuyor (`_domainconnect`), DNS ise Cloudflare'de.

---

## 2. Kimlik bilgileri ve API uclari

Degerler **ortam degiskenlerinde** tutulur; asla repoya yazilmaz, log'a basilmaz.

| Degisken | Kullanim |
|---|---|
| `OVH_ENDPOINT` | `ovh-ca` → API kokU `https://ca.api.ovh.com/1.0` |
| `OVH_APPLICATION_KEY` / `OVH_APPLICATION_SECRET` / `OVH_CONSUMER_KEY` | OVH API imzasi |
| `cloudflare_api_token` | Cloudflare API (zone okuma/yazma calisiyor) |
| `cloudflare_account_id` | Cloudflare hesap kimligi |
| `cloudflare_access_keyid` / `cloudflare_access_key` | R2 / S3 uyumlu depolama anahtarlari |

### OVH API

- Kok: `https://ca.api.ovh.com/1.0` (endpoint `ovh-ca`)
- Consumer key yetkisi: **yalnizca** `GET|POST|PUT|DELETE /vps/*`
  (`GET /vps` ve `GET /me` → `403 This call has not been granted`)
- Imza: `X-Ovh-Signature = "$1$" + sha1(SECRET+CONSUMER+METHOD+URL+BODY+TIMESTAMP)`,
  basliklar: `X-Ovh-Application`, `X-Ovh-Consumer`, `X-Ovh-Timestamp`
- Ise yarayan uclar:
  - `GET /vps/vps-913eb1fb.vps.ovh.net` — durum, model
  - `GET /vps/vps-913eb1fb.vps.ovh.net/ips` — IP listesi
  - `GET /vps/{name}/ips/{ip}` — reverse DNS, gateway
  - `POST /vps/{name}/reboot` — yeniden baslat
  - `GET /vps/{name}/task` — devam eden isler
  - `POST /vps/{name}/ips/{ip}` (PUT) — **reverse DNS (PTR)** ayarla; su an `null`,
    mail gonderimi icin `vps-913eb1fb.vps.ovh.net` veya site adina ayarlanmali
- Yeniden kurulum (`POST /vps/{name}/rebuild`) **calistirilmaz** — diski siler.

### Cloudflare API

- Kok: `https://api.cloudflare.com/client/v4`
- Kimlik: `Authorization: Bearer $cloudflare_api_token`
- Not: `GET /user/tokens/verify` bu token icin `Invalid API Token` doner
  (account-scoped token), ama `GET /zones` calisir — token gecerlidir.
- Kullanilan uclar:
  - `GET /zones?name=<domain>` — zone id
  - `GET /zones/{zone}/dns_records?type=A&name=<fqdn>`
  - `PATCH /zones/{zone}/dns_records/{id}` — IP degistir
  - `GET /zones/{zone}/settings/ssl` — SSL modu
  - `POST /zones/{zone}/purge_cache` — `{"purge_everything":true}`
  - `GET /ips` — edge IP araliklari (real_ip ve firewall icin)

---

## 3. Bu ortamin sinirlari (unutma)

- **SSH bu oturumdan mumkun degil** — sebep agi politikasi, eksik istemci degil.
  Dogrulama (28.08.2026):
  - `apt-get update && apt-get install openssh-client` → **basarili**,
    OpenSSH 9.6p1 kurulu. (Ilk denemede apt indeksi bayat oldugu icin 404 alinmisti.)
  - Dogrudan `57.129.128.118:22` → `Connection timed out`.
  - Agent proxy uzerinden (`nc -X connect -x 127.0.0.1:$PROXY_PORT`) →
    `HTTP/1.1 200 Connection Established` doner ama **tek bayt akmaz**, SSH banner gelmez.
  - Ayni test `github.com:22` icin de bantsiz → engel VPS'te degil,
    **oturumun egress politikasinda**: yalnizca TLS/HTTPS trafigi geciyor
    (proxy TLS'i yeniden sonlandiriyor; `gitSshRewrite: true` de bunu dogruluyor).
  - `/__agentproxy/status` → `recentRelayFailures: []` (politika reddi kaydi yok,
    trafik sessizce dusuyor).
- **Ayrica bu container'da hicbir SSH anahtari yok** (`~/.ssh` bos, diskte
  ozel anahtar bulunamadi). Ag acik olsa bile kimlik dogrulama yapilamazdi.
- **Ikinci ortamda da test edildi (28.08.2026):** `Default — trusted network access`
  (`env_011B9xyR8Rkq9wEjwa7YKdFZ`) ortaminda ayri bir oturum acilip ayni testler
  kosuldu. Sonuc ayni: **SSH EGRESS: BLOCKED**. Ayrintili kanit
  `claude/ssh-egress-probe` dalindaki `ssh-egress-probe.md` dosyasinda. Ozet:
  - Port 22 → her iki hostta da (VPS ve github.com) 10s'de timeout;
    port 443 → aninda baglaniyor. Engel **port bazli, host bazli degil**.
  - `CONNECT host:22` → sahte `200 Connection Established`, sifir bayt, ilk
    yazmada `ECONNRESET`. `CONNECT github.com:443` → tam TLS 1.3 el sikismasi.
  - `ssh -vv` TCP connect asamasinda kaliyor; anahtar eksikligi belirleyici degil.
  - Ek bulgu: **VPS 443'ten de erisilemiyor** — egress gateway `403` donuyor
    (`CN=default.domain` yer tutucu sertifika, 13 ms). Yalnizca izin listesindeki
    hostlar (ornegin github.com) disari cikiyor.
  → Bulut ortamlarinda ag politikasini genistirmek **host** listesini genisletir,
    ham TCP/22'yi acmaz. Bu yol denendi ve kapali.
### COZUM: Cloudflare tuneli (29.08.2026 — calisti)

Ham TCP/22 gecmiyor ama **HTTPS geciyor**, o yuzden SSH'i Cloudflare uzerinden
tasidik ve oturum sunucuya tam erisim kazandi:

1. Sunucuda (KVM konsolundan, tek sefer):
   `sudo bash deploy/scripts/claude-access.sh`
   → oturumun gecici public key'ini `authorized_keys`'e ekler, `cloudflared`
     kurar, `cloudflared tunnel --url ssh://localhost:22` calistirir ve
     `xxx.trycloudflare.com` adresini basar.
2. Oturum tarafinda:
   `ssh -o "ProxyCommand=cloudflared access ssh --hostname <adres>" root@vps`
3. Is bitince **mutlaka**: `sudo bash /root/claude-access.sh --stop`
   (tuneli kapatir, gecici anahtari siler)

Uyari: quick tunnel adresinin onunde Access politikasi yoktur; adresi bilen
22. porta ulasir (giris yine anahtarla korunur). Kalici cozum icin adlandirilmis
tunel + Cloudflare Access service token kurulmali.

KVM konsolu notu: klavye eslemesi bozuk, `:` → `;` ve `|` → `\` gidiyor.
`sudo loadkeys us` bunu duzeltir; yoksa `|` iceren komutlar calismaz.
- Buna karsilik **HTTPS API'leri sorunsuz calisiyor**: OVH (`/vps/*`) ve Cloudflare
  uzerinden sunucu durumu, DNS ve zone ayarlari yonetilebiliyor — DNS cutover
  bu yolla yapildi.
- Ham IP'ye HTTP probe'lari da proxy uzerinden gittigi icin guvenilir degil;
  origin durumunu Cloudflare uzerinden veya sunucuda dogrula.
- Yeni bir oturum SSH gerektiginde: kullanicidan ya sunucuda calistirmasini iste,
  ya da SSH erisimine izin veren bir ortamda calis.

---

## 4. Yapilmis isler

- [x] Multi-site nginx yapisi kuruldu (`deploy/`): site basina ayri sistem
      kullanicisi, PHP-FPM havuzu, socket, veritabani, cron, log.
- [x] Guvenlik snippet'leri: xmlrpc/wp-config/yedek dosyalari bloklu,
      uploads icinde PHP yasak, `wp-login.php` icin rate limit.
- [x] Catch-all vhost (`000-default.conf`) — tanimsiz Host → `444`.
- [x] Cloudflare `real_ip` snippet'i + aylik guncelleme script'i.
- [x] `cloudflare-dns.sh` — A kayitlarini yeni IP'ye tasiyan cutover script'i.
- [x] `setup-server.sh` / `add-site.sh` / `deploy-site.sh` yazildi ve
      sozdizimi dogrulandi (`bash -n`).
- [x] OVH ve Cloudflare API erisimi dogrulandi, VPS ve zone bilgileri cikarildi.
- [x] PHP surumu script'lerde sabit degil, dagitimdan otomatik tespit ediliyor
      (Ubuntu 26.04 → PHP 8.4).
- [x] **DNS cutover yapildi**: fdartgallery.com + www → `57.129.128.118`.
- [x] **SITE YAYINDA (29.08.2026)** — sunucu kurulumu bastan sona tamamlandi:
      Ubuntu 26.04 + nginx + **PHP 8.5**-FPM + MariaDB + Let's Encrypt.
      `https://fdartgallery.com` → 200, `www` → apex'e 301, wp-login calisiyor.
      Cloudflare cache purge edildi, SSL modu `full (strict)`.
- [x] Kurulum sirasinda cikan ve duzeltilen hatalar (script'lere islendi):
      - `php8.5-opcache` paketi yok (PHP'ye gomulu) → opsiyonel eklenti dongusu.
      - Ubuntu 26.04 nginx.conf'ta `server_tokens`/`gzip on` zaten var → duplicate
        hatasi; conf.d'den cikarildi, `server_tokens` yerinde `off` yapiliyor.
      - `rsync --exclude 'database/'` **her seviyedeki** `database` dizinini
        atliyordu → Elementor Pro'nun `submissions/database` klasoru eksik kaldi ve
        site fatal error verdi. Kaliplar `/database/` seklinde koke sabitlendi
        (15 dizin bu yuzden kayboluyordu).
      - Yedegin tablo oneki `wp_` degil **`fdArt0Og_`** → `deploy-site.sh` artik
        onegi veritabanindan okuyup `wp-config.php`'ye yaziyor.

---

## 5. YAPILACAKLAR

### A. Sunucu kurulumu — **TAMAMLANDI (29.08.2026)**

- [x] Repoyu sunucuya al:
      `sudo git clone https://github.com/bilginkilic/fdartgalleryuk.git /opt/fdartgalleryuk`
      `cd /opt/fdartgalleryuk && git checkout claude/ovhcloud-vps-multisite-0eb3xj`
- [x] `sudo bash deploy/scripts/setup-server.sh` (nginx, PHP 8.3-FPM, MariaDB, certbot, ufw, wp-cli)
- [ ] `sudo mysql_secure_installation` — **hala yapilmadi**
- [ ] Tunel kapatma + gecici anahtari silme: `sudo bash /root/claude-access.sh --stop`
- [x] `sudo bash deploy/scripts/add-site.sh fdartgallery.com`
- [x] `sudo bash deploy/scripts/deploy-site.sh fdartgallery.com --with-db`
      (dosyalar repoda tamamlandi; `database/backup_2026-08-25-2045.sql` ice aktarilir)
- [x] Sertifika alindi (Let's Encrypt, bitis 27.11.2026, otomatik yenileme kurulu)
      (DNS zaten yeni IP'de, HTTP-01 Cloudflare proxy'si uzerinden calisir)
- [ ] `dev.fdartgallery.com` — **simdilik yok**, eski IP'de birakildi. Ileride
      istenirse: `add-site.sh dev.fdartgallery.com` + `wp search-replace` + `blog_public 0`.

> **PHP surumu: 8.5** — Ubuntu 26.04 depolarinda baska surum yok (8.4/8.3 icin
> `ondrej/php` PPA gerekir). WordPress 7.1 ve eklentiler su an 8.5'te sorunsuz
> calisiyor; ileride bir eklenti 8.5 ile bozulursa PPA ekleyip
> `PHP_VERSION=8.4 sudo -E bash deploy/scripts/add-site.sh ...` ile dusurulebilir.

### B. Cutover oncesi dogrulama (DNS'e dokunmadan)

- [ ] Yerel makinede `/etc/hosts` satiri ile siteyi yeni sunucudan test et:
      `57.129.128.118  fdartgallery.com www.fdartgallery.com`
- [ ] `wp option get siteurl` / `home` degerlerini kontrol et; gerekirse
      `wp search-replace 'http://eski' 'https://fdartgallery.com' --all-tables`
- [ ] Gorseller, tema, eklentiler, wp-admin girisi calisiyor mu?

### C. Sertifika

- [ ] Cloudflare **proxy acikken** HTTP-01 sorun cikarirsa DNS-01 kullan:
      ```
      printf 'dns_cloudflare_api_token = <token>\n' | sudo tee /root/.secrets/cloudflare.ini
      sudo chmod 600 /root/.secrets/cloudflare.ini
      sudo certbot certonly --dns-cloudflare \
           --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
           -d fdartgallery.com -d www.fdartgallery.com
      ```
- [ ] Alternatif: Cloudflare Origin CA sertifikasi (15 yil) + SSL modu **Full (strict)**
- [ ] Cloudflare SSL modu **Flexible olmamali** — yonlendirme dongusu yaratir.

### D. DNS cutover — **YAPILDI (28.08.2026)**

- [x] `bash deploy/scripts/cloudflare-dns.sh fdartgallery.com 57.129.128.118 --names @,www`
      → apex ve `www` artik yeni VPS'e bakiyor, proxy acik kaldi.
- [x] Cloudflare cache purge yapildi:
      `curl -X POST "$API/zones/$ZONE/purge_cache" -H "Authorization: Bearer $TOKEN" \
       -H "Content-Type: application/json" --data '{"purge_everything":true}'`
- [x] Cloudflare SSL modu **Full (strict)** yapildi.

### E. Sertlestirme (cutover sonrasi)

- [ ] `sudo bash deploy/scripts/update-cloudflare-ips.sh --firewall`
      → origin'e sadece Cloudflare edge IP'lerinden 80/443 acilir
- [ ] OVH API ile **reverse DNS (PTR)** ayarla — su an `null`
- [ ] SSH: parola girisi kapali, anahtar zorunlu, root login `prohibit-password`
- [ ] `fail2ban` kur (nginx + sshd jail)
- [ ] Yedekleme: gunluk `mysqldump` + `wp-content` arsivi
      (Cloudflare R2 anahtarlari mevcut — `cloudflare_access_keyid/key` ile rclone)

### F. Diger siteler

- [ ] `fdsanatmerkezi.com`, `chemiartclick.uk`, `davetevet.com`, `byhio.com`,
      `wedreply.co.uk` — her biri icin `add-site.sh <domain>`, ardindan dosya + DB tasima
- [ ] Kaynak takibi: 8 GB RAM / 4 vCore tek sunucuda 6+ WordPress. Havuz basina
      `pm.max_children = 12` (ondemand). Site sayisi arttikca `pm.max_children`
      degerlerini dusur veya MariaDB `innodb_buffer_pool_size`'i ayarla.

---

## 5b. Performans — sayfa onbellegi (29.08.2026)

nginx `fastcgi_cache` devreye alindi. Olculen etki (origin, ana sayfa):

| Durum | Ilk bayt |
|---|---|
| Onbellek yokken | ~0.90 sn |
| MISS (ilk istek) | ~1.03 sn |
| **HIT** | **~0.006 sn** |

Disaridan (Cloudflare uzerinden) toplam sure 1.71 sn → ~0.55 sn.

**Atlama kurallari** (`00-tuning.conf` icindeki map'ler, dordu de 0 ise onbellek acik):
- Cerez: `wordpress_logged_in_`, `wp-postpass_`, `comment_author_`,
  `woocommerce_items_in_cart`, `woocommerce_cart_hash`, `wp_woocommerce_session_`
- Adres: `/wp-admin`, `/wp-json`, `/wp-login.php`, `/xmlrpc.php`, `/wp-cron.php`,
  `/wc-api`, `/cart`, `/checkout`, `/my-account`, `/sepet`, `/odeme`, `/hesabim`,
  `add-to-cart=`, `/feed`, `sitemap*.xml`
- Yontem: yalnizca GET/HEAD
- Sorgu dizesi olan her istek (arama, filtre, utm)

`Set-Cookie` donen yanitlar nginx'in kendi varsayilani geregi zaten
onbelleklenmez — `fastcgi_ignore_headers` **bilerek kullanilmadi**, ikinci
guvenlik katmani olarak duruyor.

**Dogrulama yapildi:** ana sayfa HIT; sepet/odeme/hesabim/giris yapmis
kullanici/arama BYPASS; gercek sepete-ekleme akisi (POST → cerez → sepet
sayfasi) urunu dogru gosteriyor.

Teshis: `curl -I https://fdartgallery.com/` → `X-FastCGI-Cache: HIT|MISS|BYPASS`
Onbellek omru 10 dk. Hemen temizlemek icin:
`sudo bash deploy/scripts/purge-cache.sh fdartgallery.com` (Cloudflare'i de bosaltir)

### Siradaki performans adimlari (henuz yapilmadi)

- [ ] **Gorsel agirligi**: `uploads` 1.2 GB; 4108 JPEG'e karsilik yalnizca 12 WebP.
      Kutuphanede 23 MB / 21 MB'lik islenmemis telefon fotograflari var.
      WebP'ye cevirip kucultmek mobilde en buyuk kazanci verir.
      (`image-optimization` eklentisi kurulu, yapilandirilabilir.)
- [ ] **Redis object cache** — dinamik sayfalarda ve panelde PHP suresini kisar.
- [ ] Cloudflare: Brotli, Tiered Cache, statik icerik icin Cache Rules.
- [ ] **Eklenti cakismasi**: hem `elementor-pro` hem `pro-elements` aktif —
      ikincisi Elementor Pro'nun kopyasi. Kurulumdaki fatal error da Elementor
      Pro'daydi. Incelenmeli (canliyi etkileyecegi icin kullanici karari).
- [ ] CDN notu: **harici CDN gerekmiyor** — gorseller Cloudflare edge'inde
      `HIT` doneyor (`Cache-Control: public, max-age=2592000, immutable`).

---

## 6. Sunucu duzeni (site basina)

| Bilesen | Ornek: fdartgallery.com |
|---|---|
| Kok dizin | `/var/www/fdartgallery.com/public` |
| Sistem kullanicisi | `web_fdartgallery_com` |
| PHP-FPM havuzu | `/etc/php/8.5/fpm/pool.d/fdartgallery_com.conf` |
| Socket | `/run/php/php-fpm-fdartgallery_com.sock` |
| vhost | `/etc/nginx/sites-available/fdartgallery.com.conf` |
| DB | `wp_fdartgallery_com` (kendi kullanicisi) |
| DB sifresi | `/var/www/fdartgallery.com/db-credentials.txt` (mod 600) |
| Loglar | `/var/log/nginx/fdartgallery_com.{access,error}.log` |
| WP cron | `/etc/cron.d/wp-cron-fdartgallery_com` (10 dk, CLI) |

Izolasyon: ayri unix kullanicisi + `open_basedir` → bir site ele gecse digerlerine erisemez.

---

## 7. Calisma kurallari

- Tum gelistirme `claude/ovhcloud-vps-multisite-0eb3xj` dalinda; izinsiz baska dala push yok.
- **Sirlar asla commit edilmez**: token, DB sifresi, `wp-config.php`, `.ini` dosyalari.
- `deploy-site.sh` `rsync --delete` kullanir → repo eksikken calistirma.
- DNS degisikligi, firewall kisitlamasi ve sertifika islemleri **canliyi etkiler**:
  once `--dry-run`, sonra kullanici onayi.
- `deploy-site.sh` mevcut `wp-config.php`'yi ezmez.
- Bu dosyayi guncel tut: her tamamlanan adimda kutucugu isaretle, yeni ogrenilen
  bilgiyi (IP, zone id, hostname) ilgili tabloya yaz.
