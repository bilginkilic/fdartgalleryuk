# Claude oturum araclari

Claude oturumlarinda tekrar tekrar kurulan islerin kalici hali. Bunlar
`/tmp` altinda yaziliyordu ve konteyner her yeniden basladiginda kayboluyordu
(25.09.2026'da gercekten oldu). Artik burada.

Uretim/ops scriptleri bir ust dizinde (`deploy/scripts/`); burasi **denetim ve
duzenleme** araclari icin.

## Kurulum

Tarayici olcumu icin (konteynerde, bir kez):

```sh
python3 -m pip install playwright requests   # tarayici ZATEN kurulu
apt-get install -y openssh-client            # taze konteynerde ssh yok
curl -sSL -o /tmp/cloudflared \
  https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64
chmod +x /tmp/cloudflared
```

> **`pip` DEGIL, `python3 -m pip`.** Konteynerde `pip` 3.13'e, `python3` ise
> /usr/local/bin'deki 3.11'e bakiyor; `pip install requests` "basarili" der ama
> `python3` onu goremez.

`ssh-vps.sh` anahtari `VPS_SSH_PRIVATE_KEY`'den kendi kurar. `IdentitiesOnly=yes`
sart — yoksa ajandaki fazla anahtarlar "Too many authentication failures" verir.

`playwright install` CALISTIRMAYIN — Chromium `/opt/pw-browsers/chromium`
altinda hazir.

## Araclar

| Dosya | Nerede calisir | Ne yapar |
|---|---|---|
| `ssh-vps.sh` | konteyner | Cloudflare tunnel + Access token ile VPS'e SSH |
| `gonder.sh` | konteyner | yerel script'i base64 ile tasir, istege bagli calistirir |
| `sayfa-olc.py` | konteyner | **gorunurluk olcumu** — getBoundingClientRect + getComputedStyle |
| `ekran-goruntusu.py` | konteyner | ekran goruntusu (masaustu/mobil) |
| `html-kiyas.py` | konteyner | iki HTML dosyasinin boyut/varlik farki |
| `tasma-bul.py` | konteyner | **yatay tasmaya sebep olan ogeyi** bulur (mobil) |
| `ff-genislik.php` | sunucu (wp) | FluentForm widget'larinin sabit px genisligini mobilde %100 yapar |
| `siralama-yeni.php` | sunucu (wp) | varsayilan urun siralamasini "yeniden eskiye" yapar (WC secenegi + izgaralar) |
| `sayfa-denetle.sh` | **sunucu** | HTTP kodu, boyut, varlik sayilari, onbellek durumu |
| `isaret-say.sh` | **sunucu** | bir sayfada aranan isaretleri sayar |
| `cf-purge.php` | sunucu (wp) | Cloudflare zone tam temizligi, zone adiyla aranir |
| `elementor-tara.php` | sunucu (wp) | sablon kosullari + widget agaci + konteyner yonu |
| `ornek-elementor-duzenle.php` | sunucu (wp) | guvenli `_elementor_data` duzenleme kalibi |
| `zz-profil.php` | sunucu (mu-plugin) | istek profilleyici — **yalnizca dev** |

## Neden bu bicimde — uc kural, uc kaza

**1. SSH komut dizesinin icine heredoc yazilmaz.** Tirnaklar sessizce bozulur.
01.09.2026'da canli + dev `wp-config.php` ayni anda kirildi. `gonder.sh` bu
kurali arac haline getirir: script yerelde dosyaya yazilir, base64 ile tasinir,
sunucuda acilir.

**2. Konteynerden `curl --resolve` SAYILMAZ** — `HTTPS_PROXY` var, istek
origin'e gitmez. Origin testleri sunucudan yapilir:
`./gonder.sh sayfa-denetle.sh --sh fdartgallery.com / /shop/`

**3. "Sayfa saga kayiyor" sikayetinde goze guvenmeyin — olcun.** 10.10.2026'da
sikayet "hero mobilde saga kaymis" seklinde geldi. Hero masumdu: ALTBILGIDEKI
bulten formu 390px ekranda 480px genisligindeydi, belge 510px'e cikiyor ve
butun sayfa kayiyordu. `tasma-bul.py` kirpilmayan tasan ogeleri bulur ve
sorumluyu gosterir.

**4. Varlik kontrolu gorunurluk kontrolu DEGILDIR.** 25.09.2026'da filtre
cubugu HTML'de vardi ama urun konteyneri `row` yonundeydi — cubuk izgaranin
yanina sikisacakti. Sadece `sayfa-olc.py` gosterdi.

> `sayfa-olc.py` TLS dogrulamasini **kapatmaz**. Chromium agent proxy'sinin
> CA'sini okumadigi icin istekler Python'a yaptirilir ve `/root/.ccr/ca-bundle.crt`
> ile dogrulanir; tarayici yalnizca sonucu isler.

## Tipik akis — canliyi etkileyen bir degisiklik

```sh
# 0) once dev
./gonder.sh elementor-tara.php --wp dev.fdartgallery.com -- product-archive

# 1) kuru calisma
./gonder.sh degisiklik.php --wp dev.fdartgallery.com

# 2) uygula, olc
./gonder.sh degisiklik.php --wp dev.fdartgallery.com -- uygula
./sayfa-olc.py https://dev.fdartgallery.com/shop/

# 3) YEDEK (kabuktan — PHP /var/backups'a yazamaz, open_basedir)
./ssh-vps.sh 'D=/var/backups/claude-$(date +%F)-<is>; mkdir -p $D; \
  cd /var/www/fdartgallery.com/public; \
  sudo -u www-data wp post meta get <id> _elementor_data > $D/<id>-oncesi.json'

# 4) kullanici onayi ALINDIKTAN sonra canli
./gonder.sh degisiklik.php --wp fdartgallery.com -- uygula
./gonder.sh cf-purge.php   --wp fdartgallery.com
./gonder.sh sayfa-denetle.sh --sh fdartgallery.com / /shop/ /cart/
./sayfa-olc.py https://fdartgallery.com/shop/

# 5) CLAUDE.md'ye yaz, commit
```
