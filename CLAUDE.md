# CLAUDE.md — fdartgallery.com / OVHcloud multi-site runbook

Bu dosya, projeyi devralan her oturum icin kalici hafizadir. Sunucu bilgileri,
API uclari, yapilmis isler ve **yapilacaklar listesi** burada tutulur.
Bir adim tamamlandiginda bu dosyadaki kutucugu isaretle ve commit et.

---

## 0. SU AN NE KALDI (30.08.2026 sonu)

Yayindaki siteler: **fdartgallery.com**, **chestnyznak.com.tr** (+ iki dev ortami).
Sunucu kurulumu, performans, guvenlik, yedekleme ve fdartgallery e-postasi bitti.

### Kullanicidan bekleyenler (bunlar olmadan ilerlenemez)

| # | Is | Neden bekliyor |
|---|---|---|
| 1 | **chestnyznak giden e-posta** — `info@chestnyznak.com.tr` timeweb parolasi | Parola tasimada bozuldu, kurtarilamiyor. `TIMEWEB_MAIL_PASSWORD` env'e konursa kurulum hazir (5i) |
| 2 | **info@ fazladan kopya** — "MX testi 4" hangi kutulara geldi? | Kopyanin Brevo'dan mi Gmail'den mi geldigini ayirt eden test (5j) |
| 3 | **Kalan 4 site** icin dosya arsivi + `.sql` + **orijinal `wp-config.php`** | fdsanatmerkezi, chemiartclick.uk apex, davetevet, byhio, wedreply (5F) |
| 4 | **chestnyznak gri, ama Rusya'dan HALA acilmiyor.** Ayni IP'deki `origin.chemiartclick.uk` aciliyor → geriye tek degisken ALAN ADI. SNI engellemesi test ediliyor | `chestnyznak-test.chemiartclick.uk` sonucu bekleniyor. Isim engelliyse barindirma degisikligi cozmez (5l) |
| 5 | **PTR** posta alan adina cevrilsin mi | OVH Manager'dan; API yetkisi yok. **Oncelik dusuk** — mevcut PTR calisiyor (5E) |

### Karar bekleyenler (aciliyeti yok)

- **Ucretli hizlandirma**: Cloudflare APO (~5 $/ay, HTML'i edge'de onbellekler —
  Turkiye'deki ziyaretci icin en buyuk tek kazanc) veya Pro plan (~20 $/ay) (5b)
- **Eklenti sadelestirme**: fdartgallery'de iki form + iki mailchimp eklentisi;
  `elementor-pro` + `pro-elements` cakismasi (5b)
- **SSH parola girisi**: sertlestirmenin geri kalani yapildi (5k); parolayi
  kapatmak icin kullanicinin **public key**'i lazim — sunucuda hic kalici
  anahtar yok, script guvenlik geregi reddediyor
- **Kalici tunel**: `cloudflared` servisi sunucuda kurulu degil; su anki erisim
  Access service token ile calisiyor (3. bolum)

### Kozmetik / kucuk

- Dev sitelerinde birkac canli adrese baglanti kaldi → Elementor > Tools >
  Regenerate CSS & Data
- Kutuphanede islenmemis buyuk orijinal gorseller disk kapliyor (5b)

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
TXT   @                       -> brevo-code:33cb1e9c...   (eski kayit, duruyor)
TXT   @                       -> brevo-code:856ab74d...   (30.08 eklendi, AKTIF dogrulama)
TXT   @                       -> v=spf1 include:spf.brevo.com include:_spf.mx.cloudflare.net ~all
MX    @                       -> route1/2/3.mx.cloudflare.net  (Email Routing, 30.08)
TXT   cf2024-1._domainkey      -> v=DKIM1; ...  (Email Routing)
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
### COZUM: Cloudflare tuneli — SSH'i HTTPS uzerinden tasimak

Ham TCP/22 gecmiyor ama **HTTPS geciyor**. Iki nesil kullanildi:

#### 1) Quick tunnel (29.08.2026 — ilk kurulum boyle yapildi, ARTIK KAPALI)

`cloudflared tunnel --url ssh://localhost:22` → rastgele `xxx.trycloudflare.com`.
Kurulumun tamami (fdartgallery + chestnyznak) bu yolla yapildi. Sakincasi:
**onunde kimlik dogrulama yoktu** — adresi bilen SSH giris ekranina ulasiyordu.
Is bitince kapatildi, gecici anahtar `authorized_keys`'ten silindi.

#### 2) Adlandirilmis tunel + Cloudflare Access (30.08.2026 — KALICI YOL)

| Bilesen | Deger |
|---|---|
| Tunel adi | `ovh-vps` |
| Tunel ID | `10207ab7-28c8-4a96-a56c-3fa0c8d79711` |
| Adres | `ssh.fdartgallery.com` (CNAME → `<id>.cfargotunnel.com`, proxied) |
| Yonlendirme | `ssh.fdartgallery.com` → `ssh://localhost:22` |
| Access uygulamasi | "OVH VPS SSH" — id `51ecfcce-e8e8-4e27-ad20-6a2af7c68187` |
| Zero Trust takim | `chemiartclick.cloudflareaccess.com` |

Quick tunnel'dan farki: **adresi bilmek yetmez**, Cloudflare kapida service
token ister. Dogrulandi — tokensiz `403 + Cloudflare Access` sayfasi.

**DURUM (30.08.2026): yarim kurulu.**
- [x] Tunel kaydi, DNS, ingress, Access uygulamasi — hazir
- [ ] **Sunucuda `cloudflared` servisi kurulmadi** → tunel `inactive`,
      `ssh.fdartgallery.com` → `530`. Konsoldan tek komut:
      `sudo cloudflared service install <TUNEL_TOKEN>`
      (token: Cloudflare paneli → Zero Trust → Networks → Tunnels → ovh-vps)
      veya repodan: `sudo bash deploy/scripts/setup-named-tunnel.sh <token-dosyasi>`
- [ ] **Service token ve politika YOK** (kullanici karariyla iptal edildi —
      kimlik bilgisi oturumun gecici alaninda degil, parola yoneticisinde
      durmali). Kullanicinin yapmasi gereken:
      1. Zero Trust → Access → Service Auth → **Create Service Token**
         (ad orn. `ops-ssh`, sure 1 yil) → `Client ID` + `Client Secret`
         degerlerini **parola yoneticisine** kaydet (secret bir daha gosterilmez)
      2. Access → Applications → "OVH VPS SSH" → Policies → Add a policy →
         Action **Service Auth** → Include: **Service Token** = olusturulan token
- [ ] Politika eklenene kadar adres kimseyi iceri almaz (`302` → Access girisi).
      **Bu guvenli bir durum**, aceleye gerek yok.

**Bir oturum nasil baglanir** (token elinde oldugunda):
```
export TUNNEL_SERVICE_TOKEN_ID=<client_id>
export TUNNEL_SERVICE_TOKEN_SECRET=<client_secret>
ssh -o "ProxyCommand=cloudflared access ssh --hostname ssh.fdartgallery.com" root@vps
```
`cloudflared` ikilisi GitHub release'inden indirilebiliyor (bu oturumda calisti).
Ayrica sunucuda oturumun public key'i `authorized_keys`'te olmali —
`deploy/claude-session-key.pub` her oturumda YENILENMELI.

**Uyari:** kurulum sirasinda tunel token'i sohbet penceresine yazildi (kullanici
konsola girebilsin diye). Tunel token'i yalnizca "bu tunel icin baglayici
calistirma" yetkisi verir, sunucuya giris yetkisi vermez — ama sohbet kanalini
guvenilmez sayiyorsaniz tuneli silip yeniden olusturarak token'i dondurun.

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
- [x] `dev.fdartgallery.com` — **KURULDU (30.08.2026)**, bkz. "Dev / staging
      ortamlari". Staging guard ile mail kapali, arama motorlarina kapali.

> **PHP surumu: 8.5** — Ubuntu 26.04 depolarinda baska surum yok (8.4/8.3 icin
> `ondrej/php` PPA gerekir). WordPress 7.1 ve eklentiler su an 8.5'te sorunsuz
> calisiyor; ileride bir eklenti 8.5 ile bozulursa PPA ekleyip
> `PHP_VERSION=8.4 sudo -E bash deploy/scripts/add-site.sh ...` ile dusurulebilir.

### B. Cutover oncesi dogrulama — **KONUSUZ KALDI (site 29.08'de yayina alindi)**

- [x] Adres/gorsel/tema/eklenti/wp-admin kontrolleri canlida yapildi.
      (`/etc/hosts` ile on-test artik gereksiz; cutover tamamlandi.)

### C. Sertifika — **TAMAMLANDI**

- [x] Let's Encrypt HTTP-01 ile alindi (Cloudflare proxy aciktı, sorun cikmadi).
      Bitis 27.11.2026, otomatik yenileme kurulu.
- [x] Cloudflare SSL modu **Full (strict)** — `Flexible` YAPMAYIN, yonlendirme
      dongusu yaratir.
- DNS-01'e gerek kalmadi. Gerekirse yontem:
  ```
  printf 'dns_cloudflare_api_token = <token>\n' | sudo tee /root/.secrets/cloudflare.ini
  sudo chmod 600 /root/.secrets/cloudflare.ini
  sudo certbot certonly --dns-cloudflare \
       --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
       -d fdartgallery.com -d www.fdartgallery.com
  ```

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
      **DUZELTME (30.08.2026):** PTR aslinda **BOS DEGIL**. API'de `reverse: null`
      gorunuyor ama DNS'te OVH'nin varsayilan kaydi duruyor ve ileri-dogrulamali
      (FCrDNS tam):
      `57.129.128.118 -> vps-913eb1fb.vps.ovh.net -> 57.129.128.118`
      Yani "PTR yok" onceki notu yanlisti. Yapilabilecek iyilestirme, PTR'yi
      posta alan adina (orn. `mail.fdartgallery.com`) cevirmek — ama bu ancak
      sunucudan DOGRUDAN mail gonderilirse anlamli.
      **Oncelik dusuk:** mail Brevo uzerinden gidiyor (DKIM/DMARC DNS'te), sunucu
      dogrudan mail gondermedigi surece teslimat etkilenmez.
- [x] **SSH sertlestirildi (30.08.2026)** — `deploy/scripts/harden-ssh.sh` +
      `deploy/ssh/00-hardening.conf`. Ayrintilar 5k'da.
- [ ] **SSH parola girisi HALA ACIK** — kapatmak icin kullanicinin public
      key'i gerekiyor (bkz. 5k). Anahtar gelince:
      `sudo bash deploy/scripts/harden-ssh.sh --add-key "<public key>"`
      → giris dogrulanir → `--disable-password`

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

#### chestnyznak.com.tr — ASIL ADRES OLACAK (kullanici karari, 30.08.2026)

Alan adi **arkadasinin Cloudflare hesabinda** kalacak; biz yalnizca origin'iz.
Bu calisir: ufw Cloudflare'in TUM IP araliklarini kabul ediyor, hangi hesaptan
proxy'lendigi fark etmez.

**Mevcut durum (kazara calisiyor):**
`chestnyznak.com.tr` → 301 → `chestnyznak.chemiartclick.uk`
Sebep: A kaydi bizim IP'ye bakiyor ve proxy acik, ama sunucuda o alan adi icin
vhost YOK. HTTPS istegi SNI ile eslesen blok bulamayinca nginx ilk 443 bloguna
dusuyor, oradaki WordPress kendi `siteurl`'una kanonik yonlendirme yapiyor.
Sunulan sertifika da baska alan adina ait — arkadasinin SSL modu `Full (strict)`
olsaydi bu bile calismazdi.

**Yapilacaklar (sunucu erisimi gelince, sirayla):**
1. Vhost'a alan adlarini ekle:
   `server_name chestnyznak.chemiartclick.uk chestnyznak.com.tr www.chestnyznak.com.tr;`
2. Sertifika — **arkadastan TXT istemeye GEREK YOK**: alan adi zaten bizim
   sunucuya proxy'leniyor, HTTP-01 Cloudflare uzerinden calisir:
   `certbot --nginx -d chestnyznak.com.tr -d www.chestnyznak.com.tr -d chestnyznak.chemiartclick.uk`
3. Adresleri asil alan adina cevir:
   `wp search-replace 'https://chestnyznak.chemiartclick.uk' 'https://chestnyznak.com.tr' --all-tables`
   + `wp option update siteurl/home`
4. `chestnyznak.chemiartclick.uk` → asil adrese **301** yonlendirme (ayri server blogu).
   Dev kopyasi zaten `dev.chestnyznak.chemiartclick.uk` uzerinde duracak.
5. Onbellek temizligi (nginx + her iki Cloudflare hesabi).
6. **Arkadasina soylenecek:** SSL/TLS modunu **Full (strict)** yap. (Bizim
   hesabimizdaki zone'lari biz ayarliyoruz; onunkini goremiyoruz, o yapmali.)

**Arkadasina verilecek kalici talimat:**
- `@` ve `www` A kayitlari `57.129.128.118`, **turuncu bulut ACIK** (gri olursa
  site erisilemez — origin sadece Cloudflare'i kabul ediyor)
- `webmail`, `mail`, MX, SPF, DKIM, DMARC **degismez** — mail timeweb'de kalir
- SSL/TLS: **Full (strict)**, asla `Flexible`

Eski origin `5.42.123.26` (timeweb) geri donus yolu olarak durmali.

#### Dev / staging ortamlari — **KURULDU (30.08.2026)**

| Dev adresi | Kaynak | Dizin | Durum |
|---|---|---|---|
| `dev.fdartgallery.com` | fdartgallery.com | `/var/www/dev.fdartgallery.com` | 200 |
| `chestnyznak-dev.chemiartclick.uk` | chestnyznak.com.tr | `/var/www/dev.chestnyznak.chemiartclick.uk` | 200 |

**DIKKAT — ikinci sitenin dizin adi ile alan adi FARKLI.** Dizin hala
`dev.chestnyznak...`, yayin adresi ise `chestnyznak-dev...`. Sebep asagida.

Kurulum: `add-site.sh` → `certbot` → `clone-site.sh <kaynak-dizin> <hedef>`.
`clone-site.sh` kaynagi yalnizca OKUR; canliya hicbir yazma yapmaz.

**Staging korumasi** (`fd-staging-guard.php`, otomatik kurulur):
giden e-posta PHPMailer seviyesinde kesilir, `blog_public` 0'a zorlanir,
panelde ve sayfa altinda "DENEME ORTAMI" bandi cikar. Dogrulandi.

##### Bu kurulumda cikan tuzaklar (hepsi cozuldu)

1. **Cloudflare Universal SSL yalnizca TEK seviye alt alan adi kapsar.**
   Sertifika `chemiartclick.uk` + `*.chemiartclick.uk` iceriyor;
   `dev.chestnyznak.chemiartclick.uk` **iki seviye** oldugu icin Cloudflare
   sertifika sunamadi → disaridan `TLS handshake failure` (sunucuda 200 idi).
   Cozum: ad `chestnyznak-dev.chemiartclick.uk` olarak tek seviyeye indirildi.
   Ucretli `Advanced Certificate Manager` alternatifti, gerek kalmadi.
   → **Kural: yeni alt alan adlari tek seviye olsun.**
2. `rsync -a` root olarak calisinca dosyalar KAYNAK kullanicida kaliyordu;
   hedef kullanici `wp-config.php`'yi okuyamadigi icin tum wp-cli adimlari
   "Permission denied" ile dusuyordu. `chown` artik rsync'in hemen ardinda.
3. Kaynagin `object-cache.php` drop-in'i kopyalaniyor ama Redis tanimlari
   siliniyordu; tanimsiz drop-in varsayilan veritabanina baglanip **eski**
   `siteurl` degerini servis ediyordu — guncelleme sessizce etkisiz kaliyordu.
   Clone artik bu drop-in'i (ve `advanced-cache.php`'yi) siliyor.
4. Hata ciktilari `/dev/null`'a gidiyordu; adres degisimi basarisiz olunca
   staging canliya yonleniyordu. Bastirma kaldirildi, artik hata script'i durdurur.
5. Kaynak adres dizin adindan turetiliyordu; chestnyznak'ta dizin ile alan adi
   ayrisinca yanlis adres arandi. Artik `siteurl` veritabanindan okunuyor.
6. Vhost'ta `sed` ile ad degistirirken **sertifika yollari ve kok dizin de**
   degisti → `nginx -t` coktu (nginx eski yapilandirmayla calismaya devam etti,
   site etkilenmedi) ve sonra `404`. Yollar elle geri alindi.
   → **Kural: vhost'ta ad degistirirken yalnizca `server_name` satirini degistir.**

##### Kalan kusur (kozmetik)

Dev sayfalarinda hala az sayida canli adrese baglanti var
(dev.fdartgallery ~13, chestnyznak-dev ~7). Elementor'un yeniden urettigi
onbellekten ve tema ayarlarindan geliyor. Kacisli bicim (`https:\/\/...`)
`/root/fixurl.sh` ile duzeltildi (611 + 422 degisiklik), gerisi icin
Elementor > Tools > Regenerate CSS & Data calistirilmali.
Gorsel ve icerik dogru; tikladiginda canliya giden birkac menu baglantisi var.

#### Kalan siteler

- [ ] `fdsanatmerkezi.com`, `chemiartclick.uk` (apex — su an Pages'te),
      `davetevet.com`, `byhio.com`, `wedreply.co.uk` — her biri icin
      `add-site.sh <domain>`, ardindan dosya + DB tasima.
      **Gerekli:** her site icin dosya arsivi + `.sql` yedegi.
      **Ayrica ORIJINAL `wp-config.php`'yi de isteyin** (salt'lari icin) —
      yoksa 5i'deki e-posta arizasi bu sitelerde de tekrarlanir.
- [ ] **Her tasimadan SONRA e-posta testi zorunlu** (5i):
      `wp eval 'var_dump(wp_mail(get_option("admin_email"),"t","t"));'`
      FALSE donuyorsa SMTP kimlik bilgisi panelden yeniden girilmeli.
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
- [x] **Redis object cache** — KURULDU (bkz. 5c), site basina izole.
- [x] **Cloudflare ayarlari (30.08.2026)** — her iki zone'da (fdartgallery.com,
      chemiartclick.uk) acildi: `early_hints`, `0rtt`, `always_use_https`,
      `h2_prioritization`, `tiered_caching`. `brotli` ve `http3` zaten aciktı.
      `rocket_loader` **kapali birakildi** — Elementor sitelerinde JS bozuyor.
      Statik icerik icin Cache Rule gerekmedi: nginx zaten
      `Cache-Control: public, max-age=2592000, immutable` gonderiyor ve
      CSS/JS edge'de `HIT` doneyor (dogrulandi).
### Eklenti denetimi — dev'de olculdu (30.08.2026)

**Elementor optimizasyon anahtarlari ARTIK YOK.** Surum 4.2.2; 3.x'teki
"Improved CSS Loading / Improved Asset Loading / Inline Font Icons" deneysel
ayarlari 4.x'te varsayilan hale gelip kaldirilmis. `wp elementor experiments`
yalnizca editor ozellikleri (Editor V4, landing-pages) sunuyor. Cevrilecek
dugme kalmadi — kazanc yalnizca eklenti sayisindan gelir.

**Olcum yontemi:** `/root/measure.sh <domain>` — sunucu icinden sayfayi ceker,
CSS/JS etiketlerini ve HTML boyutunu sayar. `/root/audit.sh <dizin> <onek>` —
bir eklentinin GERCEKTEN kullanilip kullanilmadigini icerik ve postmeta'da
arayarak dogrular.

| Site | Kapatilanlar | CSS | JS | HTML |
|---|---|---|---|---|
| dev.fdartgallery | jetpack, pro-elements | 34 → **32** | 34 → **29** | 241 → 238 KB |
| chestnyznak-dev | revslider, advanced-popups, wp-super-cache | 52 → **49** | 41 → **40** | 309 → **283 KB** |

Her iki dev sitesinde ana sayfa, magaza, urun ve sepet **200**, PHP hatasi yok.

**Kanit (audit.sh ciktisi):**
- `revslider` → icerik ve meta'da **0 kullanim** (chestnyznak)
- `advanced-popups` → **0 kullanim** (chestnyznak)
- `wp-super-cache` → etkisiz (drop-in yok, `WP_CACHE` tanimsiz), nginx sayfa
  onbellegiyle kavramsal cakisma
- `pro-elements` → `elementor-pro` ile ayni eklentinin kopyasi, **ikisi de aktif**;
  kurulumdaki fatal error da Elementor Pro'daydi
- `contact-form-7` → fdartgallery'de 12, chestnyznak'ta 10 kullanim var — **KALMALI**
- `latepoint` (31), `devvn-image-hotspot` (18) → chestnyznak'ta kullaniliyor, kalmali

**Duzeltme:** onceki "eklenti basina varlik sayisi" tahminlerim fazla iyimserdi
(orn. revslider icin 11) — o sayim URL *gecis sayisiydi*, benzersiz dosya degil.
Gercek kazanc yukaridaki tabloda: mutevazi ama gercek.

**Kalan agirlik kaldirilamaz:** tema (`xstore` 24, `qwery`+`trx_addons` 44),
Elementor ve WooCommerce cekirdegi. Asil buyuk kazanc icin ya tema degisikligi
ya da APO gibi edge onbellekleme gerekir.

**CANLIYA UYGULANDI (30.08.2026):**

| Site | Kapatilan | CSS | JS | HTML |
|---|---|---|---|---|
| chestnyznak.com.tr | revslider, advanced-popups, wp-super-cache | 52 → **49** | 41 → **40** | 309 → **280 KB** |
| fdartgallery.com | pro-elements, **jetpack** | 34 → **32** | 34 → **29** | 241 → **237 KB** |

Dogrulandi: her iki sitede ana sayfa/magaza/urun/sepet/iletisim **200**,
PHP hata logu bos. Cloudflare ve nginx onbellekleri temizlendi.

Geri alma: `wp plugin activate <ad>`. Kapatma oncesi aktif eklenti listeleri
`/root/backups/active-plugins-<domain>-2026-08-30.txt` icinde.

`jetpack` de kullanici onayiyla kapatildi (30.08.2026). Sonuc dev olcumuyle
birebir ayni cikti: 34→32 CSS, 34→29 JS. Sepet, odeme ve hesabim sayfalari
dahil hepsi 200, PHP logu temiz.

**Jetpack kapatildi — bunlar ARTIK CALISMIYOR** (kullaniciya hatirlatilmali):
istatistikler, Jetpack CDN (Photon), ilgili yazilar, sosyal paylasim otomasyonu,
downtime izleme. Ihtiyac olursa: `wp plugin activate jetpack`.
- [ ] fdartgallery'de **iki form eklentisi** (`contact-form-7` + `fluentform`)
      ve **iki mailchimp eklentisi** aktif — birlestirilebilir, ama icerik
      degisikligi gerektirir.
- [ ] **Asil kalan darbogaz: istemci tarafi varlik sayisi** (30.08.2026 olcumu)
      - fdartgallery.com : 34 CSS + 34 JS + 240 KB HTML
      - chestnyznak      : 52 CSS + 43 JS + 307 KB HTML
      Sunucu tarafi bitti (onbellekten 6 ms); bundan sonraki kazanc eklenti
      sayisini dusurmek, Elementor varlik yuklemesini optimize etmek ve
      birlestirme/erteleme ile gelir. Sunucu erisimi gerektirir.
- [ ] **Ucretli secenekler** (kullanici karari):
      - Cloudflare APO (~5 USD/ay): HTML'i edge'de onbellege alir; su an
        `cf-cache-status: DYNAMIC`, yani her sayfa istegi Londra'ya gidiyor.
        Turkiye'deki ziyaretci icin en buyuk tek kazanc bu olur.
      - Pro plan (~20 USD/ay): Polish + Mirage + "Vary for Images" — WebP'yi
        Accept pazarligiyla dogru sekilde servis etmeyi mumkun kilar.
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

## 5h. Cloudflare Turnstile — fdartgallery.com (30.08.2026)

Bot korumasi. Once `dev.fdartgallery.com`'da kurulup dogrulandi, sonra canliya alindi.

| Bilesen | Deger |
|---|---|
| Widget adi | `fdartgallery` (Cloudflare → Turnstile) |
| Site anahtari | `0x4AAAAAAEiH-CCM9uHXek80` (herkese acik, gizli degil) |
| Gizli anahtar | WordPress secenegi `cfturnstile_secret` — **repoda YOK** |
| Alan adlari | fdartgallery.com, www.fdartgallery.com, dev.fdartgallery.com |
| Mod | `managed` |
| Eklenti | `simple-cloudflare-turnstile` 1.42.1 |

**Korunan noktalar** (`cfturnstile_*` secenekleri, hepsi `1`):
`login`, `register`, `password`, `comment`, `cf7_all`, `fluent`,
`woo_login`, `woo_register`, `woo_reset`.

**Iki tuzak:**

1. **`cfturnstile_tested` = `no` oldugu surece eklenti widget'i HIC basmaz.**
   Kurulumda bu deger `no` olarak yaziliyor ve ayarlari wp-cli ile girince
   eklentinin kendi test akisi calismadigi icin oyle kaliyor. Belirtisi:
   `has_action('login_form')` **false**, sayfada yalnizca eklentinin JS
   onyukleyicisi var, `class="cf-turnstile"` yok.
   Once anahtar gercekten dogrulandi:
   `curl -X POST https://challenges.cloudflare.com/turnstile/v0/siteverify -d secret=<gizli> -d response=dummy`
   → `invalid-input-response` (yani gizli anahtar GECERLI; gecersiz olsaydi
   `invalid-input-secret` donerdi). Ardindan `cfturnstile_tested=yes` yapildi.
2. **`cfturnstile_appearance`** ilk kurulumda `interaction-only` yapilmisti —
   bu modda widget **gorunmez**, yalnizca Cloudflare supheli buldugunda cikar.
   Kullanici "goremedim" dedi, hakliydi. `always` yapildi; gorunur kutu artik
   giris formunda duruyor. Daha az goze batmasi istenirse tekrar
   `interaction-only` yapilabilir (koruma her iki modda da calisir).

**Dogrulandi:** `wp-login.php` ve `/my-account/` sayfalarinda `class="cf-turnstile"`
ve `data-sitekey` mevcut; ana sayfa/magaza/sepet 200, PHP hatasi yok.

### Iletisim formu — uctan uca dogrulandi (30.08.2026)

Kullanici `appearance` degisikliginden once dev'de formu gonderdiginde altta
"WordPress'in destegiyle" yazan bir ekran gormustu. `appearance=always` yapilip
onbellek temizlendikten sonra **duzeldi** — kullanici dogruladi, veritabaninda
kayit da var (`fluentform_submissions` id 58, form 9, 30.08.2026 15:54).

Teshis notu (yanlis alarma dusmemek icin): `/contact-us/` HTML'inde
`name="cf-turnstile-response"` alani **gorunmez** — bu normaldir, alani
Cloudflare betigi widget cizildikten sonra JS ile ekler. Sayfada 4 adet
`class="cf-turnstile"` blogu var (FluentForm + WooCommerce giris formu).
Sayfada ayrica `woocommerce-form-login` ve iki tema arama formu bulunuyor;
JS yuklenmeden Enter'a basilirsa bunlardan biri gonderilebilir.

**Dev ile canli ayarlari birebir ayni** (`tested=yes`, `appearance=always`,
`fluent/woo_login/login = 1`, her iki sitede de 4 widget blogu).

Kaldirmak icin: `wp plugin deactivate simple-cloudflare-turnstile`.

**Canli form dogrulamasi (30.08.2026):** kullanici canlida gercek bir gonderim
yapti — `fluentform_submissions` id **58**, 16:00:15, Chrome, kaynak
`https://fdartgallery.com/contact-us/`. Turnstile'siz sunucu tarafi POST
denemeleri ise dogru sekilde reddedildi (`Please verify that you are human.`)
— yani koruma canlida gercekten calisiyor.

Not: `admin-ajax.php`'ye `X-Requested-With: XMLHttpRequest` basligi OLMADAN
POST atilirsa 302 doner. Teshis sirasinda bunu Turnstile hatasi sanmayin;
FluentForm'un AJAX yolu bu basligi bekler.

---

## 5i. E-POSTA — tasima kaynakli ariza (30.08.2026)

> **DURUM: fdartgallery.com COZULDU** (30.08.2026 20:02, teslim + acilma
> dogrulandi). **chestnyznak.com.tr HALA ACIK** — timeweb posta kutusu parolasi
> kullanici tarafindan yeniden girilmeli. Cozum ayrintilari asagida "SONUC".

**Belirti:** her iki sitede de `wp_mail()` **FALSE** donuyor. Yani yalnizca form
bildirimleri degil; WooCommerce siparis e-postalari, parola sifirlama, stok
uyarilari — **hicbiri gonderilmiyor**.

| Site | Saglayici | Hata |
|---|---|---|
| fdartgallery.com | Brevo/Sendinblue API (`fluent-smtp`) | `401 authentication not found in headers` |
| chestnyznak.com.tr | SMTP `smtp.timeweb.ru:465` (`fluent-smtp`) | `SMTP hatasi: Kimlik dogrulanamadi` |

**Sebep — bizim tasimamiz.** FluentSMTP kayitli API anahtarini/parolasini
`wp-config.php` icindeki `LOGGED_IN_KEY` (anahtar) + `LOGGED_IN_SALT` (tuz) ile
sifreliyor (`fluent-smtp/app/Functions/helpers.php`, `fluentMailEncryptDecrypt`,
`aes-256-ctr`). Tasimada **yeni salt'larla yeni bir `wp-config.php` uretildi**
(fdartgallery: 29.08.2026 09:02), bu yuzden depodaki sifreli deger artik
cozulemiyor:

```
fdartgallery  Brevo api_key : ham 296 bayt -> cozulen 0
chestnyznak   SMTP password : ham 156 bayt -> cozulen 0
```

Bos anahtar → saglayici 401 → mail yok.

**Kurtarma denendi, MUMKUN DEGIL.** Eski salt'lar elimizde yok:
- fdartgallery'nin orijinal `wp-config.php`'si git'e hic girmemis
  (`.gitignore` satir 3 — dogrusu da bu).
- chestnyznak'inki Docker'dan geliyor:
  `define('LOGGED_IN_KEY', getenv_docker('WORDPRESS_LOGGED_IN_KEY', '<yedek>'))`.
  Dosyadaki yedek degerlerle cozme denendi → `COZULEMEDI`; demek ki konteyner
  kendi ortam degiskenlerini veriyordu ve o degerler kayip.

**COZUM (kullanici yapmali, site basina ~2 dk):**
WP paneli → **FluentSMTP** → baglantiya tikla → anahtari/parolayi **yeniden gir**
→ Kaydet → *Send Test Email*.
- fdartgallery.com → Brevo API anahtari (Brevo → SMTP & API → API Keys).
  Eski anahtarin degeri bir daha gosterilmez → **yeni anahtar uretilmeli**.
- chestnyznak.com.tr → timeweb'deki `info@chestnyznak.com.tr` posta kutusu parolasi.

Anahtarlar sohbete yazilmaz, panelden girilir; sonra `wp_mail` ile dogrulanir.

**Tekrarini onlemek icin — KALAN 4 SITEDE DE OLACAK.** Tasima kontrol listesine
eklendi (bkz. 5F "Kalan siteler"): yeni `wp-config.php` uretilen her sitede
salt'a bagli sifrelenmis TUM sirlar bozulur. FluentSMTP'de kalici cozum, taşıma
oncesi `wp-config.php`'ye sabit bir anahtar koymaktir:
```
define( 'FLUENTMAIL_ENCRYPT_KEY',  '<sabit-rastgele-deger>' );
define( 'FLUENTMAIL_ENCRYPT_SALT', '<sabit-rastgele-deger>' );
```
Bunlar tanimliysa eklenti WordPress salt'larini kullanmaz; ileride wp-config
yenilense de kimlik bilgisi cozulmeye devam eder.

**Etki suresi:** 29.08.2026'dan beri. **Veri kaybi yok** — form talepleri
Telegram'a dusmeye devam etti ve kayitlar veritabaninda duruyor.

---

### SONUC — fdartgallery.com onarildi (30.08.2026 20:02)

Kullanici yeni Brevo API anahtarini `BREVO_API_KEY` ortam degiskenine koydu.
Yapilanlar sirasiyla:

1. **Anahtar dogrulandi** — `GET /v3/account` → 200, hesap
   `chemiartclick@gmail.com`, **free plan / 300 kredi**.
2. **`FLUENTMAIL_ENCRYPT_KEY` + `FLUENTMAIL_ENCRYPT_SALT`** dort sitenin
   `wp-config.php`'sine eklendi (rastgele 32 bayt, repoda YOK). Artik SMTP
   sirlari WordPress salt'larindan bagimsiz — bu ariza tekrarlanmaz.
   Yedekler: `/root/backups/wp-config-*-2026-08-30-*.php`
3. **Anahtar `fluentMailSetSettings()` ile kaydedildi** (asagidaki tuzaklara bak).
4. **Brevo'ya alan adi eklendi** (`POST /v3/senders/domains`). DKIM CNAME'leri
   (`brevo1/2._domainkey`) DNS'te zaten vardi ve **esletti**; eksik olan tek sey
   dogrulama TXT'iydi.
5. **Cloudflare'e iki TXT eklendi** (kullanici onayiyla, mevcut kayitlara
   dokunulmadan):
   ```
   TXT @  brevo-code:856ab74daec9babf76368024f39666d0   (Brevo dogrulama)
   TXT @  v=spf1 include:spf.brevo.com ~all             (eksik olan SPF)
   ```
   Eski `brevo-code:33cb1e9c...` kaydi silinmedi (baska bir kayittan kalma,
   zararsiz).
6. `PUT /v3/senders/domains/fdartgallery.com/authenticate` →
   *"Domain has been authenticated successfully."*
7. **Uctan uca dogrulandi** — Brevo olay kaydi:
   `20:02:13 requests → 20:02:15 delivered → 20:02:18 opened` (swordbros@gmail.com)

#### Bu is sirasinda ogrenilen UC TUZAK

1. **`wp_mail()` TRUE donmesi teslimat demek DEGIL.** Brevo API istegi kabul
   edip (HTTP 201) mesaji kuyrukta reddedebiliyor. Ilk testte `wp_mail` TRUE
   dondu ama olay kaydinda `error: Sending has been rejected because the sender
   you used info@fdartgallery.com is not valid` yaziyordu. **Her zaman
   `GET /v3/smtp/statistics/events` ile dogrulayin.**
2. **`sudo -u <user> -E wp ...` ortam degiskenini TASIMIYOR** (sudoers
   `env_reset`). `getenv("BKEY")` bos dondu ve anahtar sessizce **bos** kaydedildi
   — hata da vermedi. Sirri gecici bir PHP dosyasina yazip `wp eval-file` ile
   calistirin; boylece deger komut satirinda (`ps`) da gorunmez.
3. **Ham `update_option('fluentmail-settings', ...)` KULLANMAYIN.** Dogru API
   `fluentMailSetSettings($settings)` — DUZ METIN alir, sifrelemeyi kendi yapar
   ve `settings['test']` kontrol alanini gunceller. Ham yazinca `test` alani eski
   kalir, eklenti anahtari bozuk sayip bosaltir.
   Okuma tarafi: `fluentMailGetSettings()` cozulmus degeri verir.

Dogrulama tek satir:
```
sudo -u web_fdartgallery_com wp --path=/var/www/fdartgallery.com/public eval \
 'add_action("wp_mail_failed",function($e){echo $e->get_error_message()."\n";});
  var_dump(wp_mail(get_option("admin_email"),"test","test"));'
```

#### chestnyznak.com.tr — HALA ACIK (30.08.2026 test edildi)

**GELEN posta CALISIYOR, GIDEN posta calismiyor.** Olculdu:

| Yon | Test | Sonuc |
|---|---|---|
| Giden | `wp_mail()` | **FALSE** — `SMTP hatasi: Kimlik dogrulanamadi` |
| Gelen | `mx1.timeweb.ru` → `RCPT TO: info@chestnyznak.com.tr` | **250 Accepted** — kutu VAR |
| Kayitli parola | cozulen uzunluk | **0** (tasimada bozuldu) |

Gelen testinde `DATA` gonderilmedi (`RSET` cekildi) — kutuya deneme maili
dusmedi, yalnizca adres varligi dogrulandi.

**Altyapi fdartgallery'nin aksine EKSIKSIZ — DNS'e dokunmaya gerek YOK:**

| Kayit | Deger |
|---|---|
| MX | `10 mx1.timeweb.ru`, `20 mx2.timeweb.ru` |
| SPF | `v=spf1 include:_spf.timeweb.ru ~all` |
| DKIM | `dkim._domainkey.chestnyznak.com.tr` (timeweb imzaliyor) |
| DMARC | `v=DMARC1; p=none` |
| Sunucudan SMTP | `smtp.timeweb.ru` 465 **ve** 587 ACIK (`220 ... ESMTP`) |

FluentSMTP ayarlari da dogru (host/port/ssl/username/sender). `FLUENTMAIL_ENCRYPT_KEY`
tanimli, yani bir daha bozulmayacak. **Eksik olan TEK sey parola.**

**Kayip bilancosu (29.08'den beri):** siparis **0**, yeni uye **0**,
form gonderimi **1** (veritabaninda duruyor, yalnizca e-posta bildirimi gitmedi).
Musteri magduriyeti olusmamis.

**Cozum:** `info@chestnyznak.com.tr` posta kutusu parolasi. Iki yol:
- Ortam degiskeni `TIMEWEB_MAIL_PASSWORD` → kurulum script'i hazir:
  `scratchpad/set-chestnyznak-smtp.sh` (fluentMailSetSettings + gecici PHP dosyasi
  yontemi; `sudo -E` tuzagina dusmez)
- veya WP paneli → FluentSMTP → `smtp.timeweb.ru` → parolayi gir → Send Test Email

**DIKKAT:** `chestnyznak.com.tr` DNS'i ayri bir Cloudflare hesabinda
(kullanici orada admin, ama mevcut API token o zone'u gormuyor — bkz. 5l).
Brevo'ya gecirmek DKIM/SPF kaydi eklemesini gerektirir ve calisan timeweb
kurulumunu bozar — parola alinamazsa bile once sifirlama denenmeli.

### FluentForm form 9 bildirimleri (fdartgallery)

Soru "sadece Telegram mi?" idi — **hayir, ikisi de tanimli**:

| Bildirim | Hedef | Durum |
|---|---|---|
| Telegram feed "Iletisim formu" | chat `-1003786778142`, `enabled:true` | **calisiyor** |
| E-posta "Admin Notification Email" | `{wp.admin_email}` = `swordbros@gmail.com` | **basarisiz** (yukaridaki sebep) |

Teshis sorgulari:
```
# Telegram/entegrasyon kuyrugu
SELECT id, origin_id, action, status, note FROM <onek>ff_scheduled_actions ORDER BY id DESC LIMIT 10;
# E-posta bildirimi loglari
SELECT id, source_id, component, status, title FROM <onek>fluentform_logs ORDER BY id DESC LIMIT 10;
# Dogrudan test
wp eval 'add_action("wp_mail_failed",function($e){echo $e->get_error_message();}); var_dump(wp_mail(get_option("admin_email"),"t","t"));'
```

Not: FluentSMTP ayarinda `log_emails: yes` ama `<onek>fsmtp_email_logs` tablosu
**yok** — mail calismaya baslayinca eklenti onu kendisi olusturacak.

### "Kendi SMTP'mizi kuralim mi?" — inceleme (30.08.2026)

Sunucunun mail gondermeye teknik uygunlugu olculdu:

| Kontrol | Sonuc |
|---|---|
| 25. porta cikis | **ACIK** (gmail-smtp-in.l.google.com:25 baglandi) |
| 587 (Brevo relay) | ACIK |
| PTR / FCrDNS | **TAM** — `57.129.128.118 <-> vps-913eb1fb.vps.ovh.net` |
| `fdartgallery.com` SPF | **HIC YOK** (`v=spf1` kaydi bulunamadi) |
| DKIM | Brevo'nunki DNS'te duruyor (`brevo1/2._domainkey`) |
| DMARC | `p=quarantine; adkim=r; aspf=r` |
| MX | **HIC YOK** → `info@fdartgallery.com` gercek bir posta kutusu DEGIL |
| Sunucuda MTA | kurulu degil |

**Karar: kendi MTA'mizdan MUSTERI e-postasi gondermek ONERILMEZ.** Sebep
teknik yetersizlik degil, teslimat: taze bir OVH IP'sinin gonderim itibari
sifirdir; Gmail/Outlook boyle IP'leri once erteler, sonra spam'e atar. Siparis
onayi gibi islem e-postalarinda bu kabul edilemez. Ustelik SPF/DKIM uretimi,
bounce yonetimi, kara liste takibi ve IP isitma sureci kalici bakis yuku getirir.

**Yapilmasi gerekenler (oncelik sirasiyla):**
1. Brevo API anahtarini yeniden gir (5i) — DKIM zaten DNS'te, ucretsiz kota
   gunde 300 mail.
2. **Eksik SPF kaydini ekle** — su an hic yok:
   `TXT @  "v=spf1 include:spf.brevo.com ~all"`  (proxy'siz, Cloudflare'de)
   DMARC `aspf=r` oldugu icin bu hizalanir; simdilik DMARC'i yalnizca DKIM
   tasiyor, bu kirilgan bir durum.
3. **MX kararı** — `info@fdartgallery.com` adresine gelen mailler ve yanitlar
   su an HICBIR YERE gitmiyor (MX yok, bounce). Ya bir posta kutusu saglayicisi
   secilmeli ya da gonderen adres gercek bir kutuya cevrilmeli.
4. Brevo'dan cikilmak istenirse alternatif kimlik dogrulamali relay'ler:
   Google Workspace SMTP, Amazon SES, Mailgun, Postmark — FluentSMTP hepsini
   destekler. Hicbiri "kendi MTA" degildir; itibar saglayicida kalir.

**Yine de yapilmasi mantikli olan tek MTA isi:** Postfix'i *null-client relay*
olarak kurmak — sunucunun kendisi (cron, certbot suresi doluyor uyarilari,
fail2ban, mdadm) mail atabilsin diye. Gonderim yine Brevo/587 uzerinden gider,
WordPress tarafina dokunmaz.

---

## 5j. MX / gelen posta — Cloudflare Email Routing (30.08.2026)

**Sorun:** `fdartgallery.com`'un **hic MX kaydi yoktu**. Yani `info@fdartgallery.com`
gonderebiliyor (Brevo) ama **alamiyordu** — musteri siparis mailini yanitlarsa
mesaj hicbir yere gitmiyordu. Kullanici karari: **Cloudflare Email Routing**
(ucretsiz yonlendirme), gercek posta kutusu yerine.

| Bilesen | Deger |
|---|---|
| Zone | fdartgallery.com |
| MX | `route1/2/3.mx.cloudflare.net` (prio 63 / 21 / 18) — Cloudflare OTOMATIK ekledi |
| DKIM | `cf2024-1._domainkey` TXT — otomatik eklendi |
| Hedef adresler | `blgnklc@gmail.com`, `fusundogan80@gmail.com`, `chemiartclick@gmail.com` (hepsi dogrulanmis) |
| Kural | `info@fdartgallery.com` → **Worker** `fdart-info-dagitim` → iki alici |
| Catch-all | **KAPALI** — bilerek. Acik olsaydi rastgele adreslere gelen spam de yonlenirdi. |

### Neden Worker gerekti — Cloudflare'in iki siniri

Email Routing bir adresi **birden fazla kisiye dagitamaz**:
- `forward action must contain exactly one destination` (kod 2007) — bir eylemde tek hedef
- `only one action per rule is allowed` (kod 2007) — bir kuralda tek eylem
- Ayni adres icin ikinci kural: `2014 Duplicated Zone rule`

Cozum: kural bir **Email Worker**'a baglandi. Worker `message.forward()` cagrisini
her alici icin tekrarliyor. Kaynak: `deploy/cloudflare/email-worker.js`

```
Worker adi : fdart-info-dagitim
Yukleme    : PUT /accounts/{acc}/workers/scripts/fdart-info-dagitim
             (multipart: metadata.json + worker.js, type=application/javascript+module)
Kural      : actions:[{"type":"worker","value":["fdart-info-dagitim"]}]
```

**Alici eklemek/cikarmak:** `deploy/cloudflare/email-worker.js` icindeki `ALICILAR`
dizisini duzenleyip Worker'i yeniden yukleyin. **Yeni adres once Email Routing'de
hedef olarak eklenip DOGRULANMALI**, yoksa Worker o adrese gonderemez.

**KRITIK — SPF tek kayit olmak zorunda.** Cloudflare `v=spf1 include:_spf.mx.cloudflare.net ~all`
eklemenizi ister; ayri kayit olarak eklenirse **iki SPF kaydi olur ve ikisi de
gecersizlesir** (`permerror`). Mevcut kayit GUNCELLENDI, yenisi eklenmedi:
```
v=spf1 include:spf.brevo.com include:_spf.mx.cloudflare.net ~all
```
Dogrulandi: genel DNS'te **1 adet** SPF kaydi goruluyor.

**API notlari (tekrar gerekirse):**
- `POST /zones/{zone}/email/routing/enable` — routing acilir; ardindan MX ve DKIM
  kayitlarini Cloudflare **kendisi** yazar. Elle MX eklemeye calisirsaniz
  `890190 This zone is managed by Email Routing` alirsiniz — normaldir.
- `GET /zones/{zone}/email/routing/dns` — gereken kayitlari listeler.
- `POST /accounts/{acc}/email/routing/addresses` — hedef adres ekler ve
  **dogrulama maili gonderir**.
- `POST /zones/{zone}/email/routing/rules` — **hedef adres dogrulanmadan
  CALISMAZ**: `2054 Destination address is not verified`.

**Durum (30.08.2026 20:35):** kurulum tamam, uc test maili gonderildi.
`blgnklc@` ve `fusundogan80@` **aliyor** — yani Worker calisiyor (bu iki adrese
Worker disinda giden baska bir yol yok). `swordbros@gmail.com` hedef listesinden
silindi.

**ACIK KONU — fazladan kopya.** Test 1-3'un ucu de `chemiartclick@gmail.com`
kutusunda da gorundu. Cloudflare tarafinda buna giden **hicbir yol yok**;
ham kural dokumu ile dogrulandi:
```
Kural 1 : info@fdartgallery.com -> worker "fdart-info-dagitim"  (etkin, oncelik 0)
Kural 2 : catch-all -> drop                                      (KAPALI)
```
Worker'in `ALICILAR` listesinde de bu adres yok. Test 3, kural degisikliginden
8 dakika sonra gonderildi — yayilma gecikmesi aciklamasi elendi.

**Test 4 (sonucu BEKLENIYOR):** Brevo'yu tamamen devre disi birakan test.
Sunucudan dogrudan Cloudflare MX'ine SMTP ile teslim edildi:
```
route3.mx.cloudflare.net  ->  RCPT 250 2.1.0 Ok  ->  DATA 250 2.0.0 Ok
zarf gonderen: test@vps-913eb1fb.vps.ovh.net  (PTR ileri-dogrulamali, SPF/DMARC yok)
konu: "MX testi 4 - Brevo DEVRE DISI"
```
- Yalnizca `blgnklc@` + `fusundogan80@`'a geldiyse → kopyayi **Brevo** uretiyordu
- `chemiartclick@`'e de geldiyse → kopya **Gmail tarafindaki bir yonlendirmeden**

Ikinci ihtimalde gercek musteri mailleri de uc kutuya birden duser; Gmail →
Ayarlar → "Yonlendirme ve POP/IMAP" ile "Hesaplar" sekmesi temizlenmeli.
Kesin teshis: kopyada Gmail → mesaj → ⋮ → **"Orijinali goster"** →
`Delivered-To:` ve `X-Forwarded-To:` basliklari.

**Not:** yonlendirme etkinlik gunlugunu API'den okumak icin token'da
`zone.analytics.read` yetkisi YOK (`emailRoutingAdaptive` sorgusu 403 doner).
Panelden bakilabilir: zone → Email → Email Routing → Activity log.
Ayrica `workersInvocationsAdaptive` e-posta tetikli calismalari gostermiyor
(0 kayit donuyor) — Worker'in calistigina bu dataset ile karar VERMEYIN.

**Gonderim tarafi degismedi** — `info@` adresinden cikis hala Brevo uzerinden.
Gmail'den `info@fdartgallery.com` kimligiyle yanit verebilmek icin:
Gmail → Ayarlar → Hesaplar → "Baska bir adresten posta gonder" →
SMTP `smtp-relay.brevo.com:587`, kullanici adi Brevo SMTP kullanicisi,
parola Brevo SMTP anahtari (API anahtari DEGIL — Brevo panelinde ayri uretilir).

---

## 5k. SSH sertlestirme (30.08.2026)

`deploy/scripts/harden-ssh.sh` + `deploy/ssh/00-hardening.conf`.

### Bulunan hata — parola girisi hic kapanmamis

```
/etc/ssh/sshd_config.d/50-cloud-init.conf        -> PasswordAuthentication yes
/etc/ssh/sshd_config.d/60-cloudimg-settings.conf -> PasswordAuthentication no
```

OpenSSH her anahtar icin **ILK okudugu degeri** kullanir ve `sshd_config.d/*.conf`
alfabetik yuklenir. Yani imajin `no` ayari hicbir zaman devreye girmemis;
cloud-init'in `yes`'i kazaniyordu. `sshd -T` ile dogrulandi.
**Bu yuzden bizim dosyamizin adi `00-hardening.conf`** — en basta okunur, hepsini ezer.
Cloud-init reboot'ta kendi dosyasini yeniden yazsa bile bizimki kazanmaya devam eder.

### Uygulanan (kilitlenme riski YOK)

| Ayar | Once | Sonra |
|---|---|---|
| `MaxAuthTries` | 6 | **3** |
| `LoginGraceTime` | 120 | **30** |
| `X11Forwarding` | yes | **no** |
| `AllowAgentForwarding` | yes | **no** |
| `AllowTcpForwarding` | yes | **no** |
| `PermitTunnel` / `GatewayPorts` / `PermitUserEnvironment` | — | **no** |
| Ciphers / MACs / KexAlgorithms | dagitim varsayilani | yalnizca modern (chacha20-poly1305, aes-gcm, sntrup761x25519, curve25519, etm-MAC) |
| `MaxStartups` | 10:30:100 | 10:30:60 |
| `ClientAliveInterval` | 0 | 300 |

`reload` kullanildi (`restart` DEGIL) — acik oturumlar kopmadi. Uygulamadan
sonra **yeni bir baglanti acilarak** girisin calistigi dogrulandi.
Yedek: `/root/backups/sshd-config-*.tar.gz`

**Not:** `AllowTcpForwarding no` port yonlendirmeyi kapatir (orn.
`ssh -L 3306:localhost:3306`). Cloudflare tuneli bundan ETKILENMEZ — cloudflared
sunucuda calisip `localhost:22`'ye baglaniyor, SSH port forwarding kullanmiyor.
Gerekirse tek satir geri alinir.

### YAPILMADI — parola girisi hala acik, sebebi

| Hesap | Parola | Anahtar | Sudo |
|---|---|---|---|
| `root` | **kilitli** (L) | yalnizca Claude'un gecici oturum anahtari | — |
| `ubuntu` | **var** (P) | `authorized_keys` **0 bayt (BOS)** | `NOPASSWD:ALL` |

Sunucuya tek giris yolu `ubuntu` + parola. Parolayi kapatmak = SSH erisiminin
tamamen kaybi (geriye yalnizca OVH KVM konsolu kalir).
fail2ban sshd jail'i baski altinda: **700 basarisiz deneme, 100 ban.**

**Script'te kilitlenme koruması var:** `--disable-password`, sunucuda kalici
anahtar yoksa REDDEDER. Sayimda `claude-session-*` etiketli gecici anahtarlar
**haric tutulur** — o anahtar oturum bitince silinir, ona guvenip parolayi
kapatmak kilitlenme demektir. Sunucuda test edildi: `gercek anahtar = 0` → reddediyor.

**Kullanici yapmali:**
1. Kendi makinesinde anahtar (varsa atla): `ssh-keygen -t ed25519 -C "bilgin-laptop"`
2. **Public** kismi (`~/.ssh/id_ed25519.pub`) paylasilir — gizli degildir
3. `sudo bash deploy/scripts/harden-ssh.sh --add-key "<public key>"`
4. **Baska bir terminalde** `ssh ubuntu@57.129.128.118` ile giris DOGRULANIR
5. `sudo bash deploy/scripts/harden-ssh.sh --disable-password`

Acil durum: OVH Manager → VPS → KVM konsolu. Konsolda klavye eslemesi bozuk
olabilir (`:` → `;`, `|` → `\`); `sudo loadkeys us` duzeltir.

Durum: `sudo bash deploy/scripts/harden-ssh.sh --status`

---

## 5l. chestnyznak.com.tr zone erisimi (30.08.2026)

**Duzeltme:** onceki notlarda "alan adi arkadasin hesabinda, biz dokunamayiz"
yaziyordu. Kullanici o Cloudflare hesabinda **admin**. Ama bu, mevcut API
token'in oraya erisebildigi anlamina GELMIYOR — Cloudflare token'lari
olusturulurken belirli hesap/zone'lara sabitlenir, uyelik sonradan token'i
genisletmez. Olculdu:

```
GET /accounts  -> tek hesap: "Chemiartclick@gmail.com's Account"
GET /zones     -> byhio, chemiartclick.uk, davetevet, fdartgallery,
                  fdsanatmerkezi, wedreply     (6 zone)
GET /zones?name=chestnyznak.com.tr -> BOS
```

### SSL modu Full (strict) — GUVENLI, dogrulandi

Origin sertifikasi o alan adini kapsiyor ve zincir gecerli:

```
Certificate Name: chestnyznak.chemiartclick.uk
  Domains: chestnyznak.com.tr chestnyznak.chemiartclick.uk www.chestnyznak.com.tr
  Expiry : 2026-11-28  (Let's Encrypt, oto-yenileme kurulu)
openssl -servername chestnyznak.com.tr -> CN=chestnyznak.com.tr
                                          Verify return code: 0 (ok)
```

Yani `Full (strict)` yapinca yonlendirme dongusu veya 526 hatasi OLMAZ.
`Flexible` ise **asla** kullanilmamali.

### Iki yol

1. **Panelden (hizli):** o hesapta zone → SSL/TLS → Overview → **Full (strict)**.
2. **API token (kalici):** Cloudflare → My Profile → API Tokens → Create Token,
   sablon "Edit zone DNS" yerine ozel: `Zone.Zone Settings:Edit`,
   `Zone.DNS:Edit`, `Zone.Cache Purge:Purge`; kapsam o hesap + chestnyznak.com.tr.
   Token ortam degiskenine konursa bu zone da buradan yonetilebilir.

### Token OLMADAN disaridan dogrulananlar (30.08.2026)

**Turuncu bulut ACIK** — apex ve `www` Cloudflare edge IP'lerine cozuluyor;
adresler Cloudflare'in kendi `GET /ips` listesiyle karsilastirilarak dogrulandi:

```
chestnyznak.com.tr      104.21.70.221, 172.67.139.246  -> CLOUDFLARE
www.chestnyznak.com.tr  104.21.70.221, 172.67.139.246  -> CLOUDFLARE
site: HTTP 200, 0.81 sn
```

**SSL modu `Flexible` DEGIL** (cikarim): Flexible olsaydi Cloudflare origin'e
80'den baglanir, nginx HTTPS'e yonlendirir ve dongu olusurdu. Site 200
dondugune gore mod `Full` ya da `Full (strict)`.

→ SSL tarafinda ariza YOK. `Full (strict)` bir duzeltme degil, iyilestirme:
Cloudflare↔origin arasindaki sertifikayi da dogrular. **Aciliyeti dusuk.**

### AMA — kullanici turuncu bulutu ISTEMIYOR (30.08.2026)

Kullanici: *"turuncu yapinca Rusya'da acilmadigini biliyorum, turuncu yapmayalim."*
chestnyznak Rus pazarina calisan bir site, yani bu ciddi bir kisit.

**Celiski:** site **su anda zaten turuncu** (yukaridaki olcum). Yani Rusya'dan
erisim sorunu yasaniyorsa sebebi buyuk ihtimalle bu ve **canli bir sorun**.

**Griye cekmek MUMKUN ama SIRA KRITIK.** Mevcut durum:
```
ufw: 80,443/tcp ALLOW  <22 Cloudflare araligi>
     "Anywhere" kurali: YOK
```
Bulut simdi griye cekilirse site **aninda tamamen erisilemez** olur.

Duz "herkese ac" da yeterli degil: 443 dunyaya acilinca birisi
`https://57.129.128.118` adresine `Host: fdartgallery.com` basligiyla vurup
**fdartgallery icin Cloudflare'i baypas edebilir** (WAF, DDoS, edge ban devre disi).

**Planlanan sira (kullanici onayi bekliyor):**
1. nginx'te kaynak ayrimi — Cloudflare'de KALACAK siteler yalnizca CF IP'lerinden
   yanit versin:
   ```nginx
   geo $realip_remote_addr $cf_peer { default 0; <CF araliklari> 1; }
   # fdartgallery + dev vhost'larinda:
   if ($cf_peer = 0) { return 444; }
   ```
   chestnyznak vhost'una bu kosul KONMAZ.
2. ufw'de 80/443 dunyaya acilir (ayrimi artik nginx yapiyor).
3. **Sonra** DNS'te chestnyznak griye cekilir (`@`, `www` → 57.129.128.118,
   turuncu bulut KAPALI).
4. fail2ban: o site icin edge ban ise yaramaz, yerel guvenlik duvarina cevrilmeli.

**Griye cekince chestnyznak'in kaybettikleri:** edge onbellegi, DDoS korumasi,
WAF, edge ban. **Kaybetmedikleri:** HTTPS (origin'de gecerli LE sertifikasi var,
dogrudan calisir) ve nginx sayfa onbellegi (6 ms — asil hiz zaten buradan).
Origin IP'si aciga cikar; fdartgallery'yi 1. adimdaki nginx kurali korur.

**Acik soru:** Rusya'dan erisilemedigi nasil dogrulandi? Cloudflare'in tamami mi
engelli, yoksa belirli bir hata mi? Sorun ECH veya belirli bir edge datacenter'i
ise turuncuyu kapatmadan cozulebilir — ama dogrulanmadan varsayilmamali.

### AMA — kullanici turuncu bulutu ISTEMIYOR (30.08.2026)

Kullanici: *"turuncu yapinca Rusya'da acilmadigini biliyorum, turuncu yapmayalim."*
chestnyznak Rus pazarina calisan bir site, yani bu ciddi bir kisit.

**Celiski:** site **su anda zaten turuncu** (yukaridaki olcum). Yani Rusya'dan
erisim sorunu yasaniyorsa sebebi buyuk ihtimalle bu ve **canli bir sorun**.

#### Kanit (30.08.2026, Rusya'dan cep telefonu, 4G+)

Ekran goruntusu: Chrome → `chestnyznak.com.tr` → **`ERR_TIMED_OUT`**
("took too long to respond").

| Katman | Durum |
|---|---|
| DNS | **temiz** — Yandex 77.88.8.8 ve 77.88.8.88 dogru CF IP'lerini donuyor |
| TCP | **olu** — baglanti hic kurulmuyor |
| TLS | oraya kadar gelmiyor |

Yani engelleme **paket seviyesinde, Cloudflare IP araliklarina** karsi —
TLS/SNI kisitlamasi degil. Rusya'nin Cloudflare'e uyguladigi bilinen yontemle
tutarli.

**Kanitlanmayan:** origin'in (57.129.128.118, OVH Londra) oradan erisilebilir
oldugu. Cloudflare engelli diye OVH'nin acik oldugu VARSAYILAMAZ.

#### Once KANITLA, sonra yik (planlanan test)

1. Arkadasin IPv4'u alinir (`https://yandex.ru/internet/` — Rusya'da calisiyor)
2. ufw'de yalnizca o IP'ye gecici izin
3. `curl -sI --resolve chestnyznak.com.tr:443:57.129.128.118 https://chestnyznak.com.tr/`
4. `200` → gri bulut cozumdur. Timeout → gri bulut ISE YARAMAZ, korumayi
   bosuna kaldirmis olurduk.
5. Izin kaldirilir

Ek veri noktalari: Wi-Fi/sabit hattan da denesin (Rus mobil operatorleri daha
agresif kisitliyor); `chestnyznak.chemiartclick.uk` de denesin (o da CF arkasinda
— ikisi de timeout ise engel alan adina degil Cloudflare'e).

#### UYGULANDI — adim 1 ve 2 (01.09.2026)

Kullanici karari: *"Rusya'daki herkesin ya da dunyadaki herkesin bu siteye
girebilmesini istiyorum."* Buna gore ufw + nginx tarafi yapildi. **DNS henuz
degismedi** — chestnyznak hala Cloudflare uzerinden de calisiyor.

| Bilesen | Durum |
|---|---|
| `conf.d/01-cf-peer.conf` | `geo $realip_remote_addr $cf_peer` — 23 aralik + loopback |
| `snippets/cloudflare-only.conf` | `if ($cf_peer = 0) { return 444; }` |
| Eklendigi vhost'lar | fdartgallery.com, dev.fdartgallery.com, dev.chestnyznak.chemiartclick.uk |
| chestnyznak vhost | **kosul YOK** — dogrudan erisime acik (kasitli) |
| ufw | `80,443/tcp ALLOW Anywhere` eklendi; CF aralik kurallari duruyor (zararsiz yedek) |

**Neden `$realip_remote_addr`, `$remote_addr` DEGIL:** real_ip modulu
`$remote_addr`'i CF-Connecting-IP ile degistiriyor. Gercek TCP eslenigini
yalnizca `$realip_remote_addr` tutar; ayrimi yapabilen tek degisken bu.

**Dogrulandi** (sunucunun kendi genel IP'sinden, ne loopback ne Cloudflare):
```
fdartgallery.com      -> 444 (log: 57.129.128.118 ... "GET /" 444 0)
dev.fdartgallery.com  -> 444
chestnyznak.com.tr    -> 200
```
Cloudflare uzerinden hepsi 200 (ana sayfa/magaza/sepet/hesabim/dev'ler).

**TEST TUZAGI — bu oturumdan yapilan "baypas" testi GECERSIZDIR.** Konteynerde
`HTTPS_PROXY` tanimli; curl proxy'ye `CONNECT <host>:443` diyor ve `--resolve`
YOK SAYILIYOR (adi proxy cozuyor), yani istek yine Cloudflare'den gidiyor ve
yaniltici 200 doner. Dogru test sunucunun kendi genel IP'sinden:
`curl --interface 57.129.128.118 --resolve <host>:443:57.129.128.118 ...`

**`update-cloudflare-ips.sh` guncellendi:** artik `01-cf-peer.conf`'u da AYNI
listeden uretiyor. Onceden yalnizca realip snippet'ini guncelliyordu — aylik
cron'da Cloudflare yeni bir aralik eklerse o aralikdan gelen **mesru
ziyaretciler 444 yerdi**. Iki dosya artik asla ayrisamaz.

#### Gecici test adresi: `origin.chemiartclick.uk`

Rusya'dan "origin dogrudan erisilebiliyor mu" testini telefondan yapilabilir
kilmak icin kuruldu:
- DNS: `A origin.chemiartclick.uk -> 57.129.128.118`, **proxied=false (GRI)**
- Kendi Let's Encrypt sertifikasi var, vhost `/var/www/origin-test`
- `cloudflare-only.conf` bilerek **eklenmedi**
- **Is bitince silinecek:** DNS kaydi, vhost, `/var/www/origin-test`, sertifika

#### fail2ban — gri bulut oncesi kapatilan acik (01.09.2026)

Zaten var olan bir bosluktu, gri bulutla daha da kritiklesecekti:

```
[wordpress-login]  logpath = /var/log/nginx/*.access.log   <- TUM siteler
                   action  = cloudflare-token[cfzone=fa3f...]  <- fdartgallery zone'u
```

Yani chestnyznak'a saldiran bir IP, **fdartgallery'nin** Cloudflare zone'unda
banlaniyordu — chestnyznak icin hicbir koruma saglamiyordu.

**Duzeltme:** her iki jail'e ikinci bir eylem eklendi:
```
action   = cloudflare-token[cfzone=<zone>, name=<ad>]
           nftables[name=<ad>, port="http,https", protocol=tcp]
```
Site Cloudflare arkasindaysa edge bani is gorur; dogrudan erisiliyorsa yerel
nftables bani is gorur. Uygun olmayan taraf zararsiz sekilde bos calisir.
`setup-fail2ban.sh` de guncellendi. Dogrulandi: `fail2ban-client -t` OK,
jail'ler aktif (xmlrpc 4 ban).

#### ADIM 3 TAMAMLANDI — chestnyznak GRIYE CEKILDI (01.09.2026)

Token'a `Edit` yetkisi eklendikten sonra API ile yapildi:

```
chestnyznak.com.tr      -> 57.129.128.118 | proxy KAPALI | ttl 120
www.chestnyznak.com.tr  -> 57.129.128.118 | proxy KAPALI | ttl 120
```

TTL bilerek **120 sn** — geri donus gerekirse dakikalar icinde yayilir.
Geri alma yedegi: `scratchpad/chestnyznak-A-before.json`
Geri almak icin ayni kayitlara `{"proxied":true}` PATCH'lemek yeterli.

**Dogrulandi:**
```
genel DNS      : ikisi de 57.129.128.118 (gri)
site           : 200 | Server: nginx | cf-ray YOK | X-FastCGI-Cache: HIT
dogrudan erisim: 200 / 18 ms
sertifika      : CN=chestnyznak.com.tr, Let's Encrypt YE2, 28.11.2026,
                 SAN uc adi da kapsiyor, Verify return code: 0 (ok)
sayfalar       : ana sayfa / magaza / sepet -> 200
fdartgallery   : etkilenmedi (200, hala Cloudflare arkasinda ve korumali)
```

**TEST TUZAGI (ikincisi):** bu oturumdan `openssl s_client` ile sertifika
bakmak da GECERSIZDIR — egress proxy TLS'i araya girip kendi sertifikasini
sunuyor (`issuer=O=Anthropic, CN=Egress Gateway SDS Issuing CA`). Gercek
sertifika icin sunucudan bakin:
`openssl s_client -connect 57.129.128.118:443 -servername <ad>`

#### KANITLANDI — sebep Cloudflare'di (01.09.2026)

Rusya'dan, **VPN KAPALI**, ayni telefon ve sebeke:

| Adres | Cloudflare yolda mi | Sonuc |
|---|---|---|
| `chestnyznak.com.tr` (o an turuncuydu) | EVET | **ERR_TIMED_OUT** |
| `origin.chemiartclick.uk` (gri, ayni sunucu) | HAYIR | **acildi** |

Temiz A/B: tek degisken Cloudflare'in yolda olup olmamasi. Engel **Cloudflare
IP araliklarina**, bizim sunucumuza degil. Gri bulut dogru cozum.

**Teshis notu — VPN tuzagi:** ilk "basarili" ekran goruntusunde durum
cubugunda VPN simgesi vardi ve basarisiz testte yoktu; iki degisken birden
degistigi icin o kare kanit sayilmadi, VPN'siz tekrar istendi. Ekran
goruntusuyle gelen kanitlarda **durum cubugunu da okuyun** (VPN, roaming "R",
Wi-Fi/mobil).

#### AMA `chestnyznak.com.tr` HALA ACILMIYOR — SONUC BEKLENIYOR (01.09.2026)

Gri buluta cekildikten, DNS dunya genelinde yayildiktan (Yandex dahil tum
cozumleyiciler `57.129.128.118`) ve VPN kapaliyken test edildikten sonra bile
`chestnyznak.com.tr` Rusya'dan **acilmiyor**. Buna karsilik `origin.chemiartclick.uk`
**ayni IP'de, ayni sunucuda, ayni nginx'te** ve VPN'siz **aciliyor**.

Geriye tek degisken kaliyor: **alan adi.**

**Hipotez (KANITLANMADI):** Rusya'nin DPI sistemi TLS ClientHello icindeki
**SNI** alanina bakip alan adina gore kesiyor olabilir. "Chestny Znak"
(Честный знак) Rusya'nin resmi urun etiketleme sistemi; bu adi tasiyan bir
`.com.tr` alan adinin RKN listesinde olmasi makul — ama olculmedi.

**Bunu ayirt eden test kuruldu** (sonuc bekleniyor):

| # | Adres | Ne olcuyor |
|---|---|---|
| 1 | `https://origin.chemiartclick.uk` | kontrol noktasi — daha once acildi |
| 2 | `https://chestnyznak-test.chemiartclick.uk` | **kritik** — ayni IP/sunucu/sayfa, ama isimde "chestnyznak" var |
| 3 | `http://chestnyznak.com.tr` (HTTP, HTTPS degil) | engel SNI'ye mi ozel, Host basligini da mi goruyor |

Okumasi:
- **2 acilmaz, 1 acilirsa** → engel ISIMDE. Hicbir barindirma degisikligi
  kurtarmaz (sunucu Moskova'da olsa da ayni olurdu). Cozum farkli alan adi.
- **2 acilirsa** → engel yalnizca `chestnyznak.com.tr`'ye ozel. Yeni bir alan
  adi (orn. `.ru`) sorunu cozer.
- **3 acilir ama HTTPS acilmazsa** → engel SNI'ye ozel, duz HTTP'yi gormuyorlar.

Ayrica sorulacak: su anki hata **`ERR_TIMED_OUT`** mu **`ERR_CONNECTION_RESET`**
mi? Timeout = paketler sessizce dusuruluyor; reset = baglanti kurulup sonra
kesiliyor (SNI engellemesinin klasik imzasi).

#### Test icin olusturulan GECICI kaynaklar — is bitince SILINECEK

| Kaynak | Yer |
|---|---|
| `A origin.chemiartclick.uk` | Cloudflare zone `chemiartclick.uk`, proxied=false, ttl 120 |
| `A chestnyznak-test.chemiartclick.uk` | ayni zone, proxied=false, ttl 120 |
| vhost | `/etc/nginx/sites-available/origin-test.conf` (iki isim de `server_name`'de) |
| icerik | `/var/www/origin-test/index.html` |
| sertifika | `certbot delete --cert-name origin.chemiartclick.uk` (iki ismi de kapsiyor) |

Bu vhost'a `snippets/cloudflare-only.conf` **bilerek eklenmedi** — amaci zaten
Cloudflare'i baypas etmek.

#### (Referans) Adim 3 oncesi durum — SIRA NEDEN KRITIKTI

Mevcut durum:
```
ufw: 80,443/tcp ALLOW  <22 Cloudflare araligi>
     "Anywhere" kurali: YOK
```
Bulut once griye cekilirse site **aninda tamamen erisilemez** olur.

Duz "herkese ac" da yetmez: 443 dunyaya acilinca birisi
`https://57.129.128.118` adresine `Host: fdartgallery.com` basligiyla vurup
**fdartgallery icin Cloudflare'i baypas edebilir** (WAF, DDoS, edge ban devre disi).

Sira:
1. nginx'te kaynak ayrimi — Cloudflare'de KALACAK siteler yalnizca CF
   IP'lerinden yanit versin:
   ```nginx
   geo $realip_remote_addr $cf_peer { default 0; <CF araliklari> 1; }
   # fdartgallery + dev vhost'larinda:
   if ($cf_peer = 0) { return 444; }
   ```
   chestnyznak vhost'una bu kosul KONMAZ.
2. ufw'de 80/443 dunyaya acilir (ayrimi artik nginx yapiyor).
3. **Sonra** DNS'te chestnyznak griye cekilir (`@`, `www` → 57.129.128.118,
   turuncu bulut KAPALI).
4. fail2ban: o site icin edge ban ise yaramaz, yerel guvenlik duvarina cevrilmeli.

**Griye cekince chestnyznak'in kaybettikleri:** edge onbellegi, DDoS korumasi,
WAF, edge ban. **Kaybetmedikleri:** HTTPS (origin'de gecerli LE sertifikasi var,
dogrudan calisir) ve nginx sayfa onbellegi (6 ms — asil hiz zaten buradan).
Origin IP'si aciga cikar; fdartgallery'yi 1. adimdaki nginx kurali korur.

### Hala gorulmeyen / kontrol edilemeyen

- SSL modunun `Full` mu `Full (strict)` mi oldugu (ayar okunamiyor)
- MX / SPF / DKIM / DMARC **degismemeli** — posta timeweb'de (bkz. 5i)

### Token GELDI ama SALT-OKUNUR (01.09.2026)

`CF_SWORDBROS_API_TOKEN` ortam degiskenine eklendi ve calisiyor. Kapsam:
**SWORD BROS.** hesabindaki 13 zone (abcdeterjan.ru, brosmarket.ru,
**chestnyznak.com.tr**, maicollection.ru, nazimhikmet.com, palto.ru,
revinalife.ru, starstil.ru, swordbros.com/.net/.org/.ru, turkrusportal.com).

`chestnyznak.com.tr` zone id: **`858396f27bcc338ad737fc52cf2d8a9f`**

**Yetki olcumu:**
```
DNS kayitlarini OKU       -> OK
Zone ayarlarini OKU       -> OK
DNS kaydini DEGISTIR      -> REDDEDILDI (10000 Authentication error)
Onbellek temizle          -> REDDEDILDI (10000)
```
Yani token **Read**, `Edit` degil. Gri buluta cekme islemi bu token'la
YAPILAMAZ. Kullanici ya token'a `Zone.DNS: Edit` ekleyecek, ya da panelden
elle cevirecek.

Not: `/user/tokens/verify` bu token icin de `Invalid API Token` doner —
hesap kapsamli token'larda normaldir, token gecerlidir.

### Okuma yetkisiyle ogrenilenler

**SSL modu = `full`** (strict DEGIL) — onceki cikarim dogrulandi.
Diger ayarlar: `always_use_https on`, `automatic_https_rewrites on`,
`brotli on`, `http3 on`, `development_mode off`,
**`min_tls_version 1.0`** ← zayif, 1.2 yapilmali.

**`webmail.chestnyznak.com.tr` BOZUK:** A kaydi `57.129.128.118` (bizim
sunucu) ve turuncu. Sunucuda o isim icin vhost yok, istek ilk 443 bloguna
dusuyor ve **301 ile `https://chestnyznak.com.tr/`'ye** gidiyor. Yani webmail'e
girmek isteyen musteri magazanin ana sayfasina atiliyor. Dogru hedef timeweb
olmali (`mail` CNAME'i zaten `mail.timeweb.com`'a bakiyor). Kullanici karari.

**Bakim artigi:** uc adet `_acme-challenge` TXT kaydi duruyor (apex x2, www x1)
— eski DNS-01 denemelerinden kalma, zararsiz ama temizlenebilir.

### Ortam degiskeni adlandirmasi

```
CF_SWORDBROS_ACCOUNT_ID
CF_SWORDBROS_API_TOKEN
CF_SWORDBROS_R2_ACCESS_KEY_ID        # yalnizca o hesapta R2 yedegi kurulursa
CF_SWORDBROS_R2_SECRET_ACCESS_KEY    # simdilik GEREKMIYOR
CF_SWORDBROS_R2_ENDPOINT             # simdilik GEREKMIYOR
```

**Not (01.09.2026):** degiskenler ilk eklendiginde oturumun ortaminda
gorunmuyordu, bir sure sonra GORUNDU. Yani ortam degisikligi calisan konteynere
gecikmeli yansiyor — hemen gorunmezse birkac dakika sonra tekrar bakin,
yeni oturum acmaya gerek yok.

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
