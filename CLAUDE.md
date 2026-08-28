# CLAUDE.md — fdartgallery.com / OVHcloud multi-site runbook

Bu dosya, projeyi devralan her oturum icin kalici hafizadir. Sunucu bilgileri,
API uclari, yapilmis isler ve **yapilacaklar listesi** burada tutulur.
Bir adim tamamlandiginda bu dosyadaki kutucugu isaretle ve commit et.

---

## 1. Altyapi ozeti

### VPS #1 (hedef sunucu — dogrulanmis)

| Alan | Deger |
|---|---|
| Hostname | `vps-913eb1fb.vps.ovh.net` |
| IPv4 | `57.129.128.118` (gateway `57.129.128.1`) |
| IPv6 | `2001:41d0:801:2000::7e80` |
| Model | VPS-2 2027 — 4 vCore / 8 GB RAM / 75 GB SSD |
| Bolge | `os-uk2` (Region OpenStack, UK) |
| Durum | `running`, `lockStatus: none` |
| OVH URN | `urn:v1:ca:resource:vps:vps-913eb1fb.vps.ovh.net` |

### VPS #2 — **BILGI EKSIK**

Kullanici iki VPS oldugunu soyledi ama her ikisi icin de ayni hostname'i verdi.
Ikinci sunucunun hostname'i alinmali. OVH API anahtari `GET /vps` listelemesine
yetkili degil (sadece `/vps/*`), bu yuzden isim ogrenilmeden sorgulanamaz.

DNS'te gorunen `147.45.254.181` **eski/mevcut origin**'dir (OVH araliginda degil,
muhtemelen baska bir saglayici). fdartgallery.com su an bu IP'ye bakiyor ve
Cloudflare `521 Web server is down` donuyor → eski origin cevap vermiyor.

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

### fdartgallery.com mevcut DNS kayitlari (28.08.2026)

```
A     fdartgallery.com        -> 147.45.254.181   proxied=ON    (ESKI ORIGIN)
A     www.fdartgallery.com    -> 147.45.254.181   proxied=ON    (ESKI ORIGIN)
A     dev.fdartgallery.com    -> 147.45.254.181   proxied=OFF
CNAME brevo1._domainkey       -> b1.fdartgallery-com.dkim.brevo.com
CNAME brevo2._domainkey       -> b2.fdartgallery-com.dkim.brevo.com
CNAME _domainconnect         -> _domainconnect.gd.domaincontrol.com
TXT   _dmarc                  -> v=DMARC1; p=quarantine; ...
TXT   @                       -> google-site-verification=xj6o_-...
TXT   @                       -> brevo-code:33cb1e9c...
```

**Onemli:** Mail (Brevo DKIM/DMARC) ve dogrulama kayitlarina dokunma.
Cutover'da yalnizca apex + `www` A kayitlari degisecek.
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

- **SSH bu container'dan mumkun degil.** `ssh` istemcisi kurulu degil, kurulumu
  basarisiz oluyor ve disari cikis yalnizca HTTPS proxy uzerinden. `57.129.128.118:22`
  baglantisi timeout veriyor. Sunucu komutlarini kullanici kendi terminalinden
  calistirmali — bu repodaki script'ler tam da bunun icin hazirlandi.
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

---

## 5. YAPILACAKLAR

### A. Sunucu kurulumu (kullanici sunucuda calistirir)

- [ ] Repoyu sunucuya al:
      `sudo git clone https://github.com/bilginkilic/fdartgalleryuk.git /opt/fdartgalleryuk`
      `cd /opt/fdartgalleryuk && git checkout claude/ovhcloud-vps-multisite-0eb3xj`
- [ ] `sudo bash deploy/scripts/setup-server.sh` (nginx, PHP 8.3-FPM, MariaDB, certbot, ufw, wp-cli)
- [ ] `sudo mysql_secure_installation`
- [ ] `sudo bash deploy/scripts/add-site.sh fdartgallery.com`
- [ ] `sudo bash deploy/scripts/deploy-site.sh fdartgallery.com --with-db`
      (dosyalar repoda tamamlandi; `database/backup_2026-08-25-2045.sql` ice aktarilir)

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

### D. DNS cutover

- [ ] Once kuru calistirma:
      `bash deploy/scripts/cloudflare-dns.sh fdartgallery.com 57.129.128.118 --dry-run`
- [ ] Onay alindiktan sonra gercek gecis (apex + www, proxy durumu korunur)
- [ ] `dev.fdartgallery.com` kaydi ne olacak? Kullaniciya sor (eski sunucuda mi kalacak)
- [ ] Gecisten sonra Cloudflare cache purge

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
- [ ] VPS #2 hostname'ini kullanicidan al; rolunu netlestir
      (staging mi, yuk paylasimi mi, yedek mi?)

---

## 6. Sunucu duzeni (site basina)

| Bilesen | Ornek: fdartgallery.com |
|---|---|
| Kok dizin | `/var/www/fdartgallery.com/public` |
| Sistem kullanicisi | `web_fdartgallery_com` |
| PHP-FPM havuzu | `/etc/php/8.3/fpm/pool.d/fdartgallery_com.conf` |
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
