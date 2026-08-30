# Cloudflare Email Worker — info@fdartgallery.com dagitimi

Cloudflare Email Routing bir adresi tek hedefe yonlendirebiliyor. Birden fazla
kisiye dagitmak icin bu Worker kullaniliyor (bkz. CLAUDE.md 5j).

## Alici listesini degistirmek

1. `email-worker.js` icindeki `ALICILAR` dizisini duzenleyin.
2. Yeni adres Email Routing'de **hedef olarak eklenmis ve dogrulanmis** olmali:
   `POST /accounts/{acc}/email/routing/addresses` → adrese dogrulama maili gider.
3. Worker'i yeniden yukleyin:

```sh
printf '%s' '{"main_module":"worker.js","compatibility_date":"2024-11-01"}' > /tmp/metadata.json
curl -X PUT -H "Authorization: Bearer $cloudflare_api_token" \
  "https://api.cloudflare.com/client/v4/accounts/$cloudflare_account_id/workers/scripts/fdart-info-dagitim" \
  -F "metadata=@/tmp/metadata.json;type=application/json" \
  -F "worker.js=@deploy/cloudflare/email-worker.js;type=application/javascript+module"
```

Kural zaten Worker'a bagli; yeniden yukleme yeterlidir, kurala dokunmaya gerek yok.
