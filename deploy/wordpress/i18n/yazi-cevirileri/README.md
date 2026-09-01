# Blog yazilarinin EN/RU cevirileri

`13-yazilari-cevir.php` bu dosyalari `/tmp/yazi-cevirileri.json` olarak okur:

```sh
scp parti-1.json sunucu:/tmp/yazi-cevirileri.json
wp --user=<admin> --path=<kok> eval-file 13-yazilari-cevir.php dry
wp --user=<admin> --path=<kok> eval-file 13-yazilari-cevir.php
```

Partiler tek seferde uygulanmasi kolay olsun diye bolundu; script
**yeniden calistirilabilir** — cevirisi zaten olan yazi atlanir.

| Parti | Yazi |
|---|---|
| 1 | 267, 270, 272, 275, 37360, 37514 |
| 2 | 37526, 37530, 37533, 37553 |
| 3 | 37565, 37567, 37576, 37578 |
| 4 | 37596, 37602, 37633 |
| 5 | 37648, 37658 |

Toplam 19 Turkce yazi -> 19 EN + 19 RU.

## Bicim

```json
{ "<tr_post_id>": { "en": {"baslik","slug","ozet","icerik"}, "ru": {...} } }
```

`icerik` icindeki Turkce ic baglantilar (`/iletisim/`, `/shop/`, baska bir
yazinin slug'i) OLDUGU GIBI yazilir; script bunlari o dilin adresine kendisi
cevirir ve cevrilmeyen bir adres kalirsa DURUR.
