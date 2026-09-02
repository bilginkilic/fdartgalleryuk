#!/usr/bin/env python3
"""Genel bir Telegram kanalinin web onizlemesini (t.me/s/<kanal>) sayfalayarak
   ceker ve JSON'a yazar. Bot API veya oturum GEREKTIRMEZ — yalnizca herkese
   acik HTML okunur."""
import html, json, re, subprocess, sys, time

kanal = sys.argv[1]
cikti = sys.argv[2]
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36"

def getir(url):
    for deneme in range(3):
        r = subprocess.run(["curl","-sL","-A",UA,url], capture_output=True, text=True, timeout=60)
        if r.stdout and "tgme_widget_message" in r.stdout:
            return r.stdout
        time.sleep(2)
    return ""

def ayristir(h):
    """data-post sinirlarina gore mesajlara boler."""
    yerler = [(m.start(), m.group(1)) for m in re.finditer(r'data-post="[^/"]+/(\d+)"', h)]
    out = []
    for i, (poz, mid) in enumerate(yerler):
        bas = max(0, poz - 4000)
        son = yerler[i+1][0] if i+1 < len(yerler) else len(h)
        b = h[poz:son]
        onceki = h[bas:poz]
        metin = ""
        m = re.search(r'class="tgme_widget_message_text[^"]*"[^>]*>(.*?)</div>', b, re.S)
        if m:
            metin = m.group(1)
        tarih = ""
        d = re.search(r'datetime="([^"]+)"', b)
        if d:
            tarih = d.group(1)
        fotolar = re.findall(r"background-image:url\('([^']+)'\)", b)
        videolar = re.findall(r'<video[^>]+src="([^"]+)"', b)
        yt = re.findall(r'(https://(?:www\.)?youtu(?:\.be|be\.com)/[^\s"<]+)', b)
        gorulme = ""
        v = re.search(r'tgme_widget_message_views">([^<]+)<', b)
        if v:
            gorulme = v.group(1)
        out.append({
            "id": int(mid), "tarih": tarih, "metin_html": metin.strip(),
            "foto": fotolar, "video": videolar, "youtube": list(dict.fromkeys(yt)),
            "gorulme": gorulme,
        })
    return out

tumu = {}
url = "https://t.me/s/%s" % kanal
tur = 0
while url and tur < 60:
    h = getir(url)
    if not h:
        print("  (bos yanit, duruldu)"); break
    yeni = ayristir(h)
    if not yeni:
        break
    onceki_adet = len(tumu)
    for m in yeni:
        tumu[m["id"]] = m
    enk = min(m["id"] for m in yeni)
    print("  tur %2d: %3d mesaj gorundu, toplam %d (en kucuk id %d)" % (tur+1, len(yeni), len(tumu), enk))
    if len(tumu) == onceki_adet or enk <= 1:
        break
    url = "https://t.me/s/%s?before=%d" % (kanal, enk)
    tur += 1
    time.sleep(1)

liste = sorted(tumu.values(), key=lambda x: x["id"])
json.dump(liste, open(cikti, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
print("\nTOPLAM %d mesaj -> %s" % (len(liste), cikti))
if liste:
    print("id araligi: %d .. %d" % (liste[0]["id"], liste[-1]["id"]))
    print("tarih araligi: %s .. %s" % (liste[0]["tarih"][:10], liste[-1]["tarih"][:10]))
    metinli = [m for m in liste if len(re.sub(r"<[^>]+>","",m["metin_html"]).strip()) > 200]
    print("200+ karakter metni olan: %d" % len(metinli))
    print("fotolu: %d, videolu: %d, youtube linkli: %d" % (
        sum(1 for m in liste if m["foto"]), sum(1 for m in liste if m["video"]),
        sum(1 for m in liste if m["youtube"])))
