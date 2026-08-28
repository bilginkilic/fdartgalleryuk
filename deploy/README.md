# OVHcloud VPS — Multi-site nginx kurulumu

Tek VPS uzerinde birden fazla bagimsiz WordPress sitesi (multi-vhost) calistirmak icin
hazirlanmis nginx + PHP-FPM yapisi. Ilk site: **fdartgallery.com**.

Sunucu: `vps-913eb1fb.vps.ovh.net`

## Tasarim

Her site birbirinden izole edilir:

| Bilesen | Ornek (fdartgallery.com) |
|---|---|
| Web kok dizini | `/var/www/fdartgallery.com/public` |
| Sistem kullanicisi | `web_fdartgallery_com` |
| PHP-FPM havuzu | `/etc/php/8.3/fpm/pool.d/fdartgallery_com.conf` |
| PHP-FPM socket | `/run/php/php-fpm-fdartgallery_com.sock` |
| nginx vhost | `/etc/nginx/sites-available/fdartgallery.com.conf` |
| Veritabani | `wp_fdartgallery_com` (kendi DB kullanicisi ile) |
| Loglar | `/var/log/nginx/fdartgallery_com.{access,error}.log` |

Bir site ele gecirilse bile `open_basedir` + ayri unix kullanicisi sayesinde
digerlerinin dosyalarina erisemez.

## Kurulum sirasi

```bash
# 0) Repoyu sunucuya al
sudo git clone https://github.com/bilginkilic/fdartgalleryuk.git /opt/fdartgalleryuk
cd /opt/fdartgalleryuk

# 1) Sunucu temeli (bir kez): nginx, PHP-FPM, MariaDB, certbot, ufw, wp-cli
sudo bash deploy/scripts/setup-server.sh
sudo mysql_secure_installation

# 2) Ilk siteyi olustur (vhost + FPM havuzu + DB + cron)
sudo bash deploy/scripts/add-site.sh fdartgallery.com

# 3) Dosyalar tamamlaninca: yukle + wp-config uret + yedegi ice aktar
sudo bash deploy/scripts/deploy-site.sh fdartgallery.com --with-db

# 4) DNS A kaydi VPS IP'sine baktiktan sonra sertifika
sudo certbot --nginx -d fdartgallery.com -d www.fdartgallery.com
```

Ikinci, ucuncu site icin sadece 2. adimi tekrarlayin:

```bash
sudo bash deploy/scripts/add-site.sh ikincisite.com
```

## Onemli notlar

- **Adim 3'u dosyalar tamamlanmadan calistirmayin.** `rsync --delete` kullaniyor,
  eksik repo hedefteki dosyalari siler.
- `deploy-site.sh` mevcut `wp-config.php` dosyasini ezmez; DB sifreleri
  `/var/www/<domain>/db-credentials.txt` icinde (mod 600) tutulur.
- Sertifika oncesi site yalnizca HTTP'de yayindadir; `certbot --nginx` 443 blogunu
  ve HTTP→HTTPS yonlendirmesini vhost'a kendisi ekler.
- Tanimsiz domain/IP ile gelen istekler `000-default.conf` tarafindan `444` ile kapatilir,
  boylece ilk site yanlislikla varsayilan site olmaz.
- `wp-cron.php` web uzerinden kapali; her site icin `/etc/cron.d/wp-cron-<slug>`
  ile 10 dakikada bir CLI'dan calisir (`wp-config.php` icinde `DISABLE_WP_CRON`).
- `xmlrpc.php`, `wp-config.php`, `readme.html`, `.sql`/`.zip` yedekleri ve
  `wp-content/uploads` icindeki PHP dosyalari nginx seviyesinde bloklu.
- `wp-login.php` icin dakikada 15 istek limiti (`limit_req zone=wplogin`).

## Yapilandirma dosyalari

```
deploy/
├─ nginx/
│  ├─ nginx.conf.d-00-tuning.conf     → /etc/nginx/conf.d/00-tuning.conf
│  ├─ snippets/security.conf          → /etc/nginx/snippets/
│  ├─ snippets/wordpress.conf         → /etc/nginx/snippets/
│  ├─ sites-available/000-default.conf
│  └─ templates/{site,fastcgi-php}.conf.template
├─ php-fpm/pool.conf.template
└─ scripts/{setup-server,add-site,deploy-site}.sh
```

## Faydali komutlar

```bash
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl status php8.3-fpm
sudo tail -f /var/log/nginx/fdartgallery_com.error.log
sudo -u web_fdartgallery_com wp --path=/var/www/fdartgallery.com/public option get siteurl
```
