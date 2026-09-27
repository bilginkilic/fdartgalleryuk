---
description: Dev'de dogrulanmis bir degisikligi canliya alir — yedek, dry, onay, dogrulama
---

Dev'de hazir olan degisikligi canliya al: **$ARGUMENTS**

**Dev'in HTML'i KOPYALANMAZ.** Ayni donusum canlinin **kendi icerigi**
uzerinde calistirilir, cikti denetlenir. Iki ortamda post ID'ler ayni degil
(EN ana sayfa dev 37686, canli 37687) — scriptlere ID gomulmez; `page_on_front`,
`pll_get_post()`, `get_nav_menu_locations()` ve slug aramasindan cozulur.

Sirayla:

1. **Dev'de gercekten dogrulandi mi?** Degilse dur. Bir isin dev'de
   dogrulanmasi **anlamsizsa** (ornek: APO purge — dev bir CF zone'u degil) bunu
   yaz ve canlida yap; sessizce atlama.
2. **Yedek.** Ne degisecekse onun oncesi:
   - DB kaydi → `/var/backups/claude-<tarih>-<konu>/` altina `wp post get` /
     `wp post meta list` ciktisi
   - Secenek → `wp option get` ciktisi
   - Dosya → `cp -a` zaman damgali kopya
   - `wp-config.php` → kopya + `php -l` dogrulamasi + bozuksa geri yazma
   Yedegin **yolunu bana yaz**.
3. **`dry` calistir** (script destekliyorsa) ve ciktiyi bana goster.
4. **Kullanici onayi bekle.** Onay gelmeden canliya yazma.
5. **Uygula.** Elementor verisine yaziyorsan `--user=fdsanat` **sart** — yoksa
   `unfiltered_html` false doner ve `<style>`, `<script>`, `<template>` sessizce
   silinir. Yazdiktan sonra **geri okuyup dogrula**, tutmuyorsa geri al.
6. **Onbellekleri temizle** — nginx `fastcgi_cache` (`purge-cache.sh`) ve
   Cloudflare APO. Gorsel boyutuna dokunulduysa `wp cache flush` de sart
   (`wc_get_image_size()` nesne onbelleginden okuyor, `adr/0010`).
7. **Dogrula** — `/dogrula` komutunu calistir.
8. `progress.md`yi guncelle; kalici bir kural ogrenildiyse CLAUDE.md'ye, karar
   verildiyse `ovgcloudukmultisite/adr/`ye yaz.

Push oncesi `git fetch` + gelen commit'leri **oku** + rebase. Force push yok.
