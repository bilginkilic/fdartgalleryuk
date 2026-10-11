# fdartgallery.com — Haftanın Seçkisi

Her hafta galeriden 5-8 eser seçip bir hikâye etrafında anlatan blog yazısı.
Amaç üç şey: **Google'dan gelen ziyaretçi** (uzun kuyruklu aramalar:
"sonbahar temalı tablo", "nar tablosu anlamı"), **paylaşım** (sanat tarihi
hikâyesi insanların başkasına göndermek istediği şeydir) ve **duygu** (ziyaretçi
bir eseri kendi hayatına bağlayınca satın alır).

> `deploy/blog/README.md` chestnyznak'ın blogudur; üslubu ve kuralları farklı.
> Bu dizin yalnızca fdartgallery içindir.

| Dosya | Ne işe yarar |
|---|---|
| `../../wordpress/fdart-mu-plugins/fd-blog-seckiler.php` | ana sayfa bölümü, `[fd_eser]` kartı, paylaşım satırı, SEO |
| `seckiler-yayinla.php` | yazıyı oluşturur (varsayılan **taslak**), kapak, kategori, açıklama |
| `YYYY-MM-<slug>.html` + `.json` | her seçkinin içeriği ve yükü |

---

## Yazı kuralları

**Biçim**
- Düz HTML: `<p>`, `<h2>`, `<em>`, `<strong>`. Gutenberg/Elementor **yok**.
- 900-1.400 kelime. Her eser bir `<h2>` + iki paragraf + `[fd_eser]` kartı.
- Açılış: bir **sahne** (ışık, mevsim, an). Tanımla başlanmaz.
- Kapanış "Seçkinin notu": eserlerin taşıdığı duyguları tek cümlede toplar,
  2-4 iç bağlantı (`[fd_link kat=...]`, `[fd_link sayfa="ozel-siparis"]`).

**Her eser için üç katman**
1. **Tabloda ne var** — yalnızca görselde gerçekten görülen. Eser görseline
   bakılmadan yazılmaz (`wp-content/uploads`'tan açıp bakın).
2. **Akraba başyapıt** — aynı konuyu/tekniği/duyguyu taşıyan bilinen bir eser:
   sanatçı, yıl, bugün hangi müzede. Bir mit, bir mektup, bir anekdot.
3. **Size ne söylüyor** — bir duygu ve evde nereye yakışacağı (kartın notu).

**Doğruluk — pazarlık yok**
- Sanat tarihi bilgisi (tarih, müze, anekdot) **emin olunmayan yazılmaz**.
  Yıl emin değilse "civarında" denir ya da hiç verilmez.
- Teknik yalnızca ürün kategorisinden okunur (`fd_eser` kartı bunu kendisi
  basar). Görselden teknik tahmin edilmez.
- **Fiyat ve stok yazıya YAZILMAZ.** Kart canlı veriyi basar; satılan eser
  "yeni evine gitti" der ve kategorisine yönlendirir.
- Sanatçı adı, ürün verisinde yoksa yazılmaz (tuvaldeki imzadan çıkarım yok).

**SEO**
- Başlık ≤ 60 karakter, ana anahtar kelime başta ("Sonbahar Tabloları: ...").
- Slug Türkçe karaktersiz, anahtar kelimeyi taşır.
- `meta_aciklama` ≤ 155 karakter.
- Kapak: seçkideki en duygusal **yatay** eser (`kapak_urun`) — ana sayfa kartı
  ve paylaşım önizlemesi bu görselle çıkar.

---

## Yayınlama (sunucuda)

```sh
P=/var/www/dev.fdartgallery.com/public      # ÖNCE dev
U=web_dev_fdartgallery_com
cp 2026-10-sonbahar-tablolari.html /tmp/
cp 2026-10-sonbahar-tablolari.json /tmp/secki-yuk.json
sudo -u $U wp --user=fdsanat --path=$P eval-file seckiler-yayinla.php dry
sudo -u $U wp --user=fdsanat --path=$P eval-file seckiler-yayinla.php
```

Kuru çalıştırma her eserin **yayında ve stokta** olup olmadığını listeler;
yayında olmayan eser varsa durur. Yazı **taslak** oluşur — önizleyip
yayınlayın ya da yükte `"durum": "publish"` verin.

Ana sayfa bölümü yazı yayınlanınca kendiliğinden yenilenir (transient silinir;
nginx 10 dk, APO purge `/`'u da kapsar).

---

## Yayınlanan seçkiler

| Tarih | Başlık | Slug | Eserler |
|---|---|---|---|
| 10.10.2026 (canlı #3899) | Sonbahar Tabloları: Hasattan İlk Kara Sekiz Eser | `sonbahar-tablolari-ekim-seckisi` | 3703, 3650, 3688, 3099, 3633, 3358, 2760, 3471 |
| 11.10.2026 (taslak, yüklenmedi) | Şehir Tabloları: İstanbul'dan Mostar'a Sekiz Durak | `sehir-tablolari-istanbul-venedik-paris-seckisi` | 3642, 2921, 1488, 2868, 3474, 617, 3610, 1491 |

Bir eser iki seçkide arka arkaya kullanılmaz.
