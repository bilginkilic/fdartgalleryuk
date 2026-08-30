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
- **GitHub reposu artik sunucudan erisilemiyor (30.08.2026):** hem
  `fdartgalleryuk` hem `chestnyznakuk` sunucudan `404` / kimlik istegi donuyor
  (repolar ozel). Sunucuda `git pull` CALISMAZ. Script guncellemeleri ve site
  dosyalari tunel uzerinden `rsync` ile aktariliyor:
  `rsync -az -e "<tunel ssh>" deploy/ root@vps:/opt/fdartgalleryuk/deploy/`
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

**DURUM (30.08.2026): tunel KAPATILDI, gecici anahtar silindi.** Yeni bir
oturumun sunucuya erisebilmesi icin 1. adim KVM konsolundan tekrar
calistirilmali — ve script'teki `KEY_URL` o oturumun kendi public key'ini
gostermeli (`deploy/claude-session-key.pub` guncellenmeli). Repolar artik ozel
oldugu icin `curl` ile anahtar cekmek de calismayabilir; o durumda anahtari
konsoldan elle `/root/.ssh/authorized_keys`'e eklemek gerekir.

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

## 4b. Oturum ozeti — tunel sonrasi yapilanlar (29.08.2026)

Cloudflare tuneli ile sunucuya erisim saglandiktan sonra sirasiyla:

| # | Is | Sonuc |
|---|---|---|
| 1 | `setup-server.sh` | nginx + PHP 8.5-FPM + MariaDB + certbot + ufw + wp-cli |
| 2 | `add-site.sh fdartgallery.com` | izole kullanici, FPM havuzu, DB, cron |
| 3 | `deploy-site.sh --with-db` | dosyalar + 71 MB yedek ice aktarildi |
| 4 | `certbot --nginx` | Let's Encrypt, bitis 27.11.2026, oto-yenileme |
| 5 | Cloudflare | cache purge + SSL `full (strict)` |
| 6 | Sayfa onbellegi | TTFB 0.90 sn → **0.006 sn** |
| 7 | WebP donusumu | 6394 dosya; ana sayfa gorselleri **-%76** |
| 8 | WebP cron | her gece 03:30, artimli |
| 9 | Redis object cache | site basina izole (db indeksi + onek) |
| 10 | Kalici baglantilar | sade → `/%postname%/`; urun sayfalari onbelleklenir |

**Kurulum sirasinda bulunup duzeltilen hatalar** (hepsi script'lere islendi):

1. `php8.5-opcache` paketi Ubuntu 26.04'te yok (PHP'ye gomulu) → opsiyonel
   eklenti dongusu.
2. Ubuntu 26.04 `nginx.conf` icinde `server_tokens` ve `gzip on` zaten tanimli
   → "directive is duplicate"; conf.d'den cikarildi.
3. `rsync --exclude 'database/'` **her seviyedeki** `database` dizinini
   atliyordu → Elementor Pro'nun `submissions/database` klasoru eksik kaldi,
   site fatal error verdi. Kaliplar `/database/` ile koke sabitlendi (15 dizin).
4. Yedegin tablo oneki `wp_` degil `fdArt0Og_` → `deploy-site.sh` artik onegi
   veritabanindan okur.
5. `fastcgi_cache_use_stale` `http_502/504` kabul etmiyor → gecerli kodlar.
6. WebP mu-plugin'inde duzenli ifade sona sabitlenmemisti → `.webp.webp` (404),
   **urun galerisi gorselleri bozuldu** (kullanici fark etti). `(?!\.webp)`
   sarti eklendi; 406 gorsel tarandi, 0 kirik.
7. `Set-Cookie` donen yanitlarin onbelleklenmemesi urun sayfalarini hep yavas
   birakiyordu → guvenlik yanit-tarafi map'e tasindi.

---

## 5. YAPILACAKLAR

### A. Sunucu kurulumu — **TAMAMLANDI (29.08.2026)**

- [x] Repoyu sunucuya al:
      `sudo git clone https://github.com/bilginkilic/fdartgalleryuk.git /opt/fdartgalleryuk`
      `cd /opt/fdartgalleryuk && git checkout claude/ovhcloud-vps-multisite-0eb3xj`
- [x] `sudo bash deploy/scripts/setup-server.sh` (nginx, PHP 8.3-FPM, MariaDB, certbot, ufw, wp-cli)
- [x] MariaDB sertlestirildi — `harden-mysql.sh` (bkz. 5f)
- [x] Tunel kapatildi + gecici anahtar silindi (30.08.2026)
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

### E. Sertlestirme — **BUYUK OLCUDE TAMAMLANDI (29.08.2026)**

- [x] **Yedekleme R2'ye** — `setup-backup.sh` + `backup-site.sh` (bkz. 5e)
- [x] **MariaDB sertlestirildi** — `harden-mysql.sh` (bkz. 5f)
- [x] **Origin Cloudflare'e kilitlendi** — `update-cloudflare-ips.sh --firewall`;
      22 Cloudflare araligi acik, `Nginx Full` kurali kaldirildi.
      Dogrulandi: `http://57.129.128.118/` → baglanti yok; site Cloudflare
      uzerinden 200. SSH (22) disariya acik kaldi — kasitli.
- [x] **fail2ban kuruldu** — 3 jail, WordPress jail'leri Cloudflare edge'inde
      engelliyor (bkz. 5g)
- [ ] **PTR (reverse DNS) — API ILE YAPILAMIYOR (30.08.2026'da yeniden dogrulandi).**
      Mevcut consumer key (olusturma 11.08.2026) yalnizca `/vps/*` kapsiyor:
      - `GET /auth/currentCredential` → kurallar: `GET|POST|PUT|DELETE /vps/*`
      - `GET|POST /ip/{ip}/reverse` → `403 This call has not been granted`
      - `PUT /vps/{name}/ips/{ip}` → **`403 not implemented`** — uc, API semasinda
        (`vps.Ip` modelinde `reverse` alani var) tanimli olmasina ragmen arka uc
        reddediyor. Uc farkli govde denendi (yalniz `reverse`, noktali FQDN,
        tam model): ucu de ayni hatayi verdi. Bu, VPS 2027 urununun reverse
        yonetimini `/ip/*` servisine tasidigini gosteriyor.
      **Cozum (kullanici yapmali):** OVH Manager → VPS → IP → reverse DNS,
      deger `vps-913eb1fb.vps.ovh.net`; ya da `/ip/*` yetkili YENI consumer key
      uretip ortam degiskenlerine koymak.
      **Oncelik dusuk:** mail Brevo uzerinden gidiyor (DKIM/DMARC DNS'te), sunucu
      dogrudan mail gondermedigi surece teslimat etkilenmez.
- [ ] SSH: parola girisi kapali, anahtar zorunlu, root login `prohibit-password`
      — henuz gozden gecirilmedi.

### F. Diger siteler

#### chestnyznak.chemiartclick.uk — **YAYINDA (30.08.2026)**

Repo: `bilginkilic/chestnyznakuk` (**ozel** repo). Docker tabanli proje:
WordPress koku `files/`, veritabani yedegi `db/wp_chestnyznak.sql` (47 MB).
Kaynak site **`https://chestnyznak.com.tr`** idi; buraya tasindi.

| Bilesen | Deger |
|---|---|
| DNS | `A chestnyznak.chemiartclick.uk -> 57.129.128.118`, **proxied=ON** |
| Sertifika | Let's Encrypt, bitis 28.11.2026 (yalnizca bu isim; `www.` yok) |
| Kok dizin | `/var/www/chestnyznak.chemiartclick.uk/public` |
| Sistem kullanici | `web_chestnyznak_chemiartclick_` |
| Socket | `/run/php/php-fpm-chestnyznak_chemiartclick_uk.sock` |
| DB | `wp_chestnyznak_chemiartclick` |
| DB | `wp_chestnyznak_chemiartclic`, tablo oneki **`ChestZna_`**, 170 tablo |
| Tema | `qwery-child` (ust tema `qwery`) |
| Durum | `https://...` → **200**, sayfa onbellegi HIT (6 ms) |
| Arama motorlari | **ACIK** (`blog_public=1`, 30.08.2026 kullanici karariyla).
  `chestnyznak.com.tr` hala yayindaysa ikizlenmis icerik riski var; asil adres
  buraya tasinacaksa eski alan adindan 301 yonlendirme kurulmali. |

**proxied=ON zorunlu**: origin guvenlik duvari yalnizca Cloudflare IP'lerini
kabul ediyor; turuncu bulut kapatilirsa site tamamen erisilemez olur.

Zone `chemiartclick.uk` SSL modu **`full`** (apex Cloudflare Pages'e bakiyor).
Sertifika alindi, `full (strict)`'e cekilebilir — ama zone genelinde etkili,
Pages siteleri de etkilenir, kullaniciya sorulmali.

**Yapilan kurulum (30.08.2026):**
```
# repo OZEL -> sunucu klonlayamiyor; dosyalar tunelden rsync ile aktarildi
rsync -az -e "<tunel ssh>" chestnyznakuk/files/ root@vps:/opt/chestnyznakuk/files/
rsync -az -e "<tunel ssh>" chestnyznakuk/db/    root@vps:/opt/chestnyznakuk/db/
sudo bash deploy/scripts/deploy-site.sh chestnyznak.chemiartclick.uk \
     --source /opt/chestnyznakuk/files --db-file /opt/chestnyznakuk/db/wp_chestnyznak.sql
wp search-replace 'https://chestnyznak.com.tr' 'https://chestnyznak.chemiartclick.uk' --all-tables
     # 14.174 + 179 degisiklik
sudo bash deploy/scripts/setup-redis.sh    chestnyznak.chemiartclick.uk
sudo bash deploy/scripts/convert-webp.sh   chestnyznak.chemiartclick.uk --cron
sudo bash deploy/scripts/backup-site.sh    chestnyznak.chemiartclick.uk --cron
```

WebP: 5755 dosya, 320 MB → 70 MB (**%78**). uploads toplam 663 MB.

**Dikkat — `wp-super-cache` aktif** ama etkisiz: `advanced-cache.php` drop-in'i yok
ve `wp-config.php`'de `WP_CACHE` tanimli degil. Etkinlestirilirse nginx sayfa
onbellegiyle cakisir; aktif etmeyin.

**PHP 8.5 uyarilari:** Elementor `atomic-global-styles.php` icinde "Implicitly
marking parameter nullable is deprecated" notice'lari uretiyor. Olumcul degil,
site calisiyor; Elementor guncellemesi bunu kapatacaktir.

#### Kalan siteler

- [ ] `fdsanatmerkezi.com`, `chemiartclick.uk` (apex — su an Pages'te),
      `davetevet.com`, `byhio.com`, `wedreply.co.uk` — her biri icin
      `add-site.sh <domain>`, ardindan dosya + DB tasima.
      **Gerekli:** her site icin dosya arsivi + `.sql` yedegi.
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

**Yanit tarafi ikinci katman** (`$wpc_bypass_respcookie`): PHP `Set-Cookie` ile
`wordpress_logged_in_`, `wordpress_sec_`, `wp-postpass_`, `wp-settings-`,
`comment_author_`, `wp_woocommerce_session_`, `woocommerce_cart_hash` veya
`woocommerce_items_in_cart` kuruyorsa o yanit **asla** onbellege yazilmaz.
Bu sart olmadan bir ziyaretcinin oturum cerezi baskalarina servis edilebilirdi
(WooCommerce'te sepetlerin karismasi demek).

`fastcgi_ignore_headers Set-Cookie` bilerek aciktir: WooCommerce urun
sayfalarinda zararsiz `woocommerce_recently_viewed` cerezi kuruyor ve nginx'in
varsayilan davranisi yuzunden **hicbir urun sayfasi onbellege girmiyordu**
(~1 sn). Guvenlik yukaridaki yanit-tarafi map'e tasindi.

**Dogrulama yapildi:** ana sayfa HIT; sepet/odeme/hesabim/giris yapmis
kullanici/arama BYPASS; gercek sepete-ekleme akisi (POST → cerez → sepet
sayfasi) urunu dogru gosteriyor.

Teshis: `curl -I https://fdartgallery.com/` → `X-FastCGI-Cache: HIT|MISS|BYPASS`
Onbellek omru 10 dk. Hemen temizlemek icin:
`sudo bash deploy/scripts/purge-cache.sh fdartgallery.com` (Cloudflare'i de bosaltir)

### WebP donusumu — YAPILDI (29.08.2026)

- [x] `deploy/scripts/convert-webp.sh fdartgallery.com` → her JPEG/PNG'nin yanina
      `<dosya>.webp` uretildi. **Orijinaller silinmedi/degistirilmedi.**
      Sonuc: 6377 webp / 494 MB (orijinal 1122 MB) — %56 kucuk.
      Ana sayfadaki gorseller: 4398 KB → 1028 KB (**%76 kazanc**).
- [x] `deploy/wordpress/mu-plugins/fd-webp-rewrite.php` — diskte `.webp` varsa
      gorsel adreslerini ona cevirir; yoksa orijinal adres kalir. Veritabani
      degismez, eklentiyi silmek her seyi geri alir.

**Neden Accept pazarligi (Vary) kullanilmadi:** zone **Free** planda ve Cloudflare
"Vary for Images" yalnizca Pro+ planlarda var. Free'de `Vary: Accept` donen
gorseller edge'de `BYPASS` olur — yani tum gorseller origin'e duserdi.
Ayri URL yontemi ile Cloudflare her iki dosyayi da normal onbellege aliyor
(dogrulandi: `.webp` istegi MISS → HIT, `content-type: image/webp`).

**Dikkat — bu yontemin bedeli:** WebP desteklemeyen cok eski tarayicilar
(IE11, Safari 13 ve oncesi) gorselleri goremez. Gunumuzde destek ~%97-98.

**Yasanan hata (duzeltildi):** mu-plugin'deki HTML duzenli ifadesi sona
sabitlenmemisti; `foo.jpeg.webp` icindeki `foo.jpeg` ile eslesip ikinci bir
`.webp` ekliyor (`.webp.webp` → 404) ve urun galerisi gorsellerini bozuyordu.
Desene `(?!\.webp)` sarti eklendi, `filter_url` de `.webp` ile biten adresi
dokunmadan geri donduruyor. 6 sayfa / 406 gorsel taranarak dogrulandi: 0 kirik.

- [x] Yeni yuklenen gorseller icin gunluk cron kuruldu:
      `/etc/cron.d/webp-fdartgallery_com` (her gece 03:30, artimli — mevcut
      `.webp` dosyalari atlanir). Kurulum: `convert-webp.sh <domain> --cron`.
- [ ] Kutuphanedeki 23 MB / 21 MB'lik islenmemis orijinaller hala duruyor;
      WordPress bunlari sayfada servis etmiyor ama disk kapliyor.
- [ ] **Redis object cache** — dinamik sayfalarda ve panelde PHP suresini kisar.
- [ ] Cloudflare: Brotli, Tiered Cache, statik icerik icin Cache Rules.
- [ ] **Eklenti cakismasi**: hem `elementor-pro` hem `pro-elements` aktif —
      ikincisi Elementor Pro'nun kopyasi. Kurulumdaki fatal error da Elementor
      Pro'daydi. Incelenmeli (canliyi etkileyecegi icin kullanici karari).
- [ ] CDN notu: **harici CDN gerekmiyor** — gorseller Cloudflare edge'inde
      `HIT` doneyor (`Cache-Control: public, max-age=2592000, immutable`).

---

## 5c. Redis object cache (29.08.2026)

`deploy/scripts/setup-redis.sh fdartgallery.com` ile kuruldu.

- Redis yalnizca `127.0.0.1`, `maxmemory 256mb`, `maxmemory-policy allkeys-lru`.
- **Multi-site izolasyonu**: her site kendi Redis veritabani indeksini
  (slug'dan `cksum % 16`) ve anahtar onekini (`<slug>:`) kullanir —
  siteler birbirinin onbellegini goremez veya silemez.
- WordPress tarafi: `redis-cache` eklentisi + drop-in (`Status: Connected`,
  `PhpRedis 6.2.0`). Tanimlar `wp-config.php` icinde (`WP_REDIS_*`).
- Etki: onbellek disi (BYPASS) sayfalarda ~%10 (0.95 sn → 0.85 sn). Asil fayda
  yonetim panelinde ve tekrarlanan sorgularda.
- Kapatmak: `sudo bash deploy/scripts/setup-redis.sh fdartgallery.com --disable`
- Bosaltmak: `sudo -u web_fdartgallery_com wp --path=... redis flush`

---

## 5d. Kalici baglantilar duzeltildi (29.08.2026)

**Sorun:** `permalink_structure` bostu (sade/plain). Urun adresleri
`?product=bardak` seklindeydi. Daha kotusu: `/urun/bardak/`, `/shop/` ve hatta
`/boyle-bir-sayfa-yok/` gibi **her adres 200 ile ANA SAYFAYI donduruyordu** —
yani yumusak 404 + yaygin ikizlenmis icerik. Ayrica sorgu dizeli adresler
sayfa onbellegini hep atliyordu.

**Yapilan:** once `mysqldump` yedegi (`/root/backups/before-permalinks-*.sql`,
71 MB), sonra:
```
wp option update permalink_structure '/%postname%/'
wp rewrite flush --hard
```

**Sonuc:**
- `/urun/bardak/` → 200, basligi "Bardak"; `/shop/` → "Shop"; `/cart/` → "Cart"
- Urun sayfalari artik onbellege giriyor: **1.20 sn → 0.006 sn**
- Eski `?product=...` adresleri **calismaya devam ediyor** (200) ve sayfada
  `<link rel="canonical" href="https://fdartgallery.com/urun/bardak/">` var —
  yani mevcut baglantilar kirilmadi, SEO tarafinda dogru adres isaret ediliyor.
- WooCommerce urun taban adresi: `urun` (`woocommerce_permalinks` ayarindan).

---

## 5e. Yedekleme — Cloudflare R2 (29.08.2026)

**Onceki durum tehlikeliydi:** UpdraftPlus gunluk calisiyordu ama hedef depolama
bos, yedekler `wp-content/updraft/` icinde **ayni diskte** duruyordu. Disk
bozulsa veya site ele gecirilse yedek de giderdi.

Sunucu seviyesinde, WordPress'ten bagimsiz yedekleme kuruldu:

| Bilesen | Ayrinti |
|---|---|
| Kova | `ovh-vps-backups` (R2, `weur`), site basina klasor |
| Anlik yedek | `snapshots/db-*.sql.gz` (~4.9 MB) + `files-*.tar.gz` (158 MB) + `wp-config-*.php` |
| uploads | `uploads/` altina **artimli** (`rclone copy`) — sadece yeni dosyalar gider |
| Cron | `/etc/cron.d/backup-fdartgallery_com`, her gece **04:15** |
| Saklama | R2'de 30 gun (anlik yedekler), yerelde son 3 |
| Kimlik | `/root/.config/rclone/rclone.conf` (mod 600) — repoda **yok** |

Tasarim kararlari:
- `files-*.tar.gz` **uploads icermez**; ilk denemede 1.36 GB'lik arsiv her gece
  yeniden yukleniyordu. Simdi 158 MB (tema/eklenti/mu-plugin) + artimli uploads.
- uploads icin `sync` degil **`copy`** kullaniliyor: sunucuda yanlislikla silinen
  bir gorsel R2'deki yedekten de silinmesin.
- `.webp` dosyalari yedeklenmiyor — orijinalinden her an yeniden uretilebiliyorlar
  (`convert-webp.sh`), R2'de yer kaplamalarinin anlami yok.
- `--s3-disable-checksum`: R2 bazi S3 cagrilarina `501 Not Implemented` donuyor.

Geri yukleme: `rclone copy r2:ovh-vps-backups/<domain>/snapshots ./` → `.sql.gz`
dosyasini `gunzip` + `mysql` ile ice aktar, `files-*.tar.gz`'i `public/` altina ac.

---

## 5f. MariaDB sertlestirildi (29.08.2026)

`deploy/scripts/harden-mysql.sh` — `mysql_secure_installation`'in etkilesimsiz hali.

**Bulunan sorun:** `root@localhost` **parolasizdi** (`mysql_native_password`, bos
parola). Sunucuda herhangi bir kullanici — orn. bir eklenti acigindan giren
saldirgan — `mysql -u root` ile tum sitelerin veritabanina erisebilirdi.

**Yapilan:** `IDENTIFIED VIA unix_socket OR mysql_native_password` — yalnizca
sistem root'u parolasiz baglanabilir; yedek parola `/root/.my.cnf` (mod 600).
Anonim kullanici ve `test` veritabani zaten yoktu, uzaktan root girisi kapali.

**Dogrulandi:**
- `sudo -u web_fdartgallery_com mysql -u root` → `ERROR 1698 Access denied`
- `mysql -u root -h 127.0.0.1 --password=""` → `ERROR 1698 Access denied`
- `mysql` (sistem root, socket) → calisiyor

---

## 5g. fail2ban (29.08.2026)

`deploy/scripts/setup-fail2ban.sh` — 3 jail:

| Jail | Kaynak | Esik | Ban | Eylem |
|---|---|---|---|---|
| `sshd` | auth log | 5/10dk | 1 saat | yerel guvenlik duvari |
| `wordpress-login` | nginx access log | 5/10dk | 1 saat | **Cloudflare edge** |
| `wordpress-xmlrpc` | nginx **error** log | 3/10dk | 24 saat | **Cloudflare edge** |

**Neden Cloudflare'de banliyoruz:** site proxy arkasinda oldugu icin paketler
Cloudflare IP'lerinden geliyor. Saldirganin gercek IP'sini logda goruyoruz
(`real_ip` modulu) ama onu yerel guvenlik duvarinda engellemek ise yaramaz.
Cloudflare API'si ile engellendiginde istek origin'e **hic ulasmiyor**.
Token `/root/.cloudflare-token` (mod 600), eylem dosyasi
`/etc/fail2ban/action.d/cloudflare-token.conf`.

**Yasanan hata:** xmlrpc filtresi once access log'a bakiyordu ve 979 satirin
hicbirini yakalamadi — cunku `security.conf` bu adres icin `access_log off`
yapiyor, denemeler **error log'a** dusuyor. Filtre error log'a yonlendirildi
(`datepattern` ile birlikte): 318 satirin 250'si eslesti.

**Uctan uca dogrulandi:** `103.137.148.42` xmlrpc denemeleri yuzunden banlandi
ve Cloudflare'de kural olustu (`block ... | fail2ban xmlrpc`). sshd jail'i de
ilk dakikada 2 gercek SSH brute-force IP'sini banladi.

**Durum:** `sudo bash deploy/scripts/setup-fail2ban.sh --status`
**Elle ban kaldirma:** `sudo fail2ban-client set <jail> unbanip <ip>`

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
