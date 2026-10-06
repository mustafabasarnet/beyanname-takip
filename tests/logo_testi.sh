#!/bin/bash
# =====================================================================
#  FİRMA / BÜRO KİMLİĞİ — LOGO + AD (sol üst köşe)
#
#  Kullanıcı isteği:
#    "Sol üstteki Beyanname Takip yazan kısma kendi logomu yüklemek
#     istiyorum; ayarlarda Firma Büro adı görünsün, solunda da benim
#     yüklediğim logo."
#
#  Test ettikleri:
#    1) Ayarlar'da "Firma / Büro Kimliği" kartı (ad + logo + önizleme)
#    2) Yükleme: PNG kabul edilir, ayara yazılır, dosya writable'a düşer
#    3) Sol üst köşe: logo görseli + firma adı birlikte görünür (her sayfada)
#    4) Giriş ekranı (oturum yokken) da logo + ad gösterir
#    5) /logo ucu doğru içerik tipiyle sunar
#    6) GÜVENLİK: .png uzantılı PHP dosyası reddedilir (içerik doğrulaması)
#    7) Reddedilen dosya diske YAZILMAZ ve ayar değişmez
#    8) Desteklenmeyen uzantı (svg) ve boyut aşımı reddedilir
#    9) Yetki: yönetici olmayan (müşavir/personel) logo değiştiremez
#   10) CSRF'siz POST reddedilir
#   11) Yeni logo yüklenince ESKİ dosya silinir (tek dosya kalır)
#   12) Logo kaldırma: dosya silinir, varsayılan 📋 simgesine dönülür
#   13) Firma adı boş bırakılırsa "Beyanname Takip" varsayılanına döner
#   14) Logo dosyaları web'den doğrudan erişilemez (writable altında)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            database/migration_logo.sql koşulmuş olmalı.
#  Kullanım:  bash tests/logo_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
PROJE=/home/user/beyanname-takip
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
JA=/tmp/lg_admin.txt
JM=/tmp/lg_musavir.txt
JP=/tmp/lg_personel.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/lg_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/lg_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Sol üst köşe bloğunu okur: "logo|ad"
solUst(){ python3 -c "
import re, html
h = open('$1', encoding='utf-8').read()
blok = re.search(r'<div class=\"logo\">([\s\S]{0,500}?)<nav', h)
if not blok:
    print('YOK|YOK'); raise SystemExit
b = blok.group(1)
img = 'LOGO' if 'class=\"logo-img\"' in b else 'VARSAYILAN'
ad  = re.search(r'<b[^>]*>([^<]*)</b>', b)
print(img + '|' + html.unescape(ad.group(1)) if ad else img + '|YOK')"; }

echo "=== 0) HAZIRLIK ==="
db "UPDATE ayarlar SET deger='' WHERE anahtar='logo_dosya';" > /dev/null 2>&1
db "UPDATE ayarlar SET deger='Beyanname Takip Sistemi' WHERE anahtar='firma_adi';" > /dev/null 2>&1
rm -f $PROJE/writable/uploads/logo/logo_* 2>/dev/null
girisYap admin $JA
girisYap musavir $JM
girisYap personel $JP
ol "logo ayar anahtarı mevcut" "1" "$(db "SELECT COUNT(*) FROM ayarlar WHERE anahtar='logo_dosya';")"

# Test görüntüsü: 64x64 gerçek PNG
python3 - <<'PY'
import struct, zlib
w=h=64
satir=[]
for y in range(h):
    s=b'\x00'
    for x in range(w):
        dx,dy=x-32,y-32
        s += bytes((37,99,235)) if (dx*dx+dy*dy)<26*26 else bytes((255,255,255))
    satir.append(s)
def p(t,v):
    c=t+v
    return struct.pack('>I',len(v))+c+struct.pack('>I',zlib.crc32(c)&0xffffffff)
png=b'\x89PNG\r\n\x1a\n'+p(b'IHDR',struct.pack('>IIBBBBB',w,h,8,2,0,0,0))+p(b'IDAT',zlib.compress(b''.join(satir)))+p(b'IEND',b'')
open('/tmp/lg_test.png','wb').write(png)
# PHP içeren sahte "png"
open('/tmp/lg_kotu.png','wb').write(b'<?php system($_GET["c"]); ?>')
# Uzantı testi
open('/tmp/lg_test.svg','w').write('<svg xmlns="http://www.w3.org/2000/svg"></svg>')
# 1.2 MB yapay dosya (gerçek PNG başlığı + dolgu)
open('/tmp/lg_buyuk.png','wb').write(png + b'\x00' * (1200*1024))
PY
ol "test dosyaları hazır" "1" "$([ -f /tmp/lg_test.png ] && [ -f /tmp/lg_kotu.png ] && echo 1 || echo 0)"

echo
echo "=== 1) AYARLAR'DA KİMLİK KARTI ==="
curl -s -b $JA -c $JA -o /tmp/lg_ayar.html "$B/tanimlar/ayarlar"
ol "kart başlığı" "1" "$(grep -c '🏢 Firma / Büro Kimliği' /tmp/lg_ayar.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "firma adı alanı" "1" "$(grep -c 'name="firma_adi"' /tmp/lg_ayar.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "logo dosya alanı (multipart)" "1" "$(grep -c 'name="logo"' /tmp/lg_ayar.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "enctype multipart" "1" "$(grep -c 'enctype="multipart/form-data"' /tmp/lg_ayar.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "canlı önizleme bloğu" "1" "$(grep -c 'Önizleme — sol üst köşe' /tmp/lg_ayar.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "eski'Genel' kartındaki yinelenen alan KALDIRILDI" "0" "$(grep -c 'name="ayar\[firma_adi\]"' /tmp/lg_ayar.html)"

echo
echo "=== 2) LOGO YÜKLEME (yönetici) ==="
T=$(jeton $JA "$B/tanimlar/ayarlar")
KOD=$(curl -s -b $JA -c $JA -o /dev/null -w '%{http_code}' -F "csrf_beyanname=$T" \
      -F "firma_adi=BAŞAR MUHASEBE BÜROSU" -F "logo=@/tmp/lg_test.png" "$B/tanimlar/firma-kaydet")
ol "POST 303 (kaydedildi)" "303" "$KOD"
ol "firma adı kaydedildi" "BAŞAR MUHASEBE BÜROSU" "$(db "SELECT deger FROM ayarlar WHERE anahtar='firma_adi';")"
LOGO=$(db "SELECT deger FROM ayarlar WHERE anahtar='logo_dosya';")
ol "logo dosya adı kaydedildi (logo_ ile başlar)" "1" "$(echo "$LOGO" | grep -c '^logo_')"
ol "dosya diske yazıldı" "1" "$([ -f "$PROJE/writable/uploads/logo/$LOGO" ] && echo 1 || echo 0)"
ol "uzantı .png" "png" "$(echo "$LOGO" | sed 's/.*\.//')"

echo
echo "=== 3) SOL ÜST KÖŞE (her sayfada) ==="
for yol in panel takip sicil makbuz; do
  curl -s -b $JA -c $JA -o /tmp/lg_$yol.html "$B/$yol"
  ol "/$yol → logo + ad" "LOGO|BAŞAR MUHASEBE BÜROSU" "$(solUst /tmp/lg_$yol.html)"
done
ol "logo görseli /logo ucuna bağlı" "1" \
   "$(grep -c 'class="logo-img" src="[^"]*/logo"' /tmp/lg_panel.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "eski sabit başlık artık YOK (Beyanname Takip yazan blok)" "0" \
   "$(grep -c '<b>Beyanname Takip</b>' /tmp/lg_panel.html)"
ol "menüdeki 'Beyanname Takip' bağlantısı KORUNDU" "1" \
   "$(grep -c 'Beyanname Takip' /tmp/lg_panel.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 4) GİRİŞ EKRANI (oturum yok) ==="
curl -s -o /tmp/lg_giris2.html "$B/giris"
ol "giriş ekranında logo görseli" "1" "$(grep -c 'class="giris-logo"' /tmp/lg_giris2.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "giriş ekranında firma adı" "1" "$(grep -c '<h1>BAŞAR MUHASEBE BÜROSU</h1>' /tmp/lg_giris2.html | head -1 | awk '{print ($1>0)?1:0}')"

echo
echo "=== 5) /logo UCU ==="
curl -s -o /tmp/lg_served.png -w '%{http_code}|%{content_type}' "$B/logo" > /tmp/lg_served.txt
ol "200 + image/png" "200|image/png" "$(cat /tmp/lg_served.txt)"
ol "içerik gerçekten PNG" "1" "$(file -b /tmp/lg_served.png | grep -c 'PNG image data' | awk '{print ($1>0)?1:0}')"
ol "X-Content-Type-Options: nosniff" "1" \
   "$(curl -s -D - -o /dev/null "$B/logo" | grep -ci 'x-content-type-options: nosniff' | awk '{print ($1>0)?1:0}')"

echo
echo "=== 6) GÜVENLİK: SAHTE GÖRÜNTÜ ==="
T=$(jeton $JA "$B/tanimlar/ayarlar")
KOD=$(curl -s -b $JA -c $JA -o /dev/null -w '%{http_code}' -F "csrf_beyanname=$T" \
      -F "firma_adi=BAŞAR MUHASEBE BÜROSU" -F "logo=@/tmp/lg_kotu.png" "$B/tanimlar/firma-kaydet")
ol ".png uzantılı PHP dosyası REDDEDİLDİ (303 + hata)" "303" "$KOD"
curl -s -b $JA -c $JA -o /tmp/lg_hata.html "$B/tanimlar/ayarlar"
ol "  kullanıcıya hata mesajı" "1" "$(grep -c 'geçerli bir görüntü değil' /tmp/lg_hata.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "  ayar DEĞİŞMEDİ (eski logo duruyor)" "$LOGO" "$(db "SELECT deger FROM ayarlar WHERE anahtar='logo_dosya';")"
ol "  klasörde yalnız 1 dosya var" "1" "$(ls -1 $PROJE/writable/uploads/logo/ | wc -l | tr -d ' ')"

echo
echo "=== 7) GÜVENLİK: UZANTI ve BOYUT ==="
T=$(jeton $JA "$B/tanimlar/ayarlar")
curl -s -b $JA -c $JA -o /dev/null -F "csrf_beyanname=$T" -F "firma_adi=BAŞAR MUHASEBE BÜROSU" \
     -F "logo=@/tmp/lg_test.svg" "$B/tanimlar/firma-kaydet"
curl -s -b $JA -c $JA -o /tmp/lg_hata2.html "$B/tanimlar/ayarlar"
ol "SVG reddedildi (XSS riski)" "1" "$(grep -c 'SVG güvenlik nedeniyle kabul edilmez' /tmp/lg_hata2.html | head -1 | awk '{print ($1>0)?1:0}')"
T=$(jeton $JA "$B/tanimlar/ayarlar")
curl -s -b $JA -c $JA -o /dev/null -F "csrf_beyanname=$T" -F "firma_adi=BAŞAR MUHASEBE BÜROSU" \
     -F "logo=@/tmp/lg_buyuk.png" "$B/tanimlar/firma-kaydet"
curl -s -b $JA -c $JA -o /tmp/lg_hata3.html "$B/tanimlar/ayarlar"
ol "1.2 MB dosya reddedildi (sınır 1 MB)" "1" "$(grep -c '1024 KB üzerinde olamaz' /tmp/lg_hata3.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "  ayar hâlâ değişmedi" "$LOGO" "$(db "SELECT deger FROM ayarlar WHERE anahtar='logo_dosya';")"

echo
echo "=== 8) YETKİ ==="
for kullanici in musavir personel; do
  JAR=$([ "$kullanici" = "musavir" ] && echo $JM || echo $JP)
  T=$(jeton $JAR "$B/tanimlar/ayarlar")
  curl -s -b $JAR -c $JAR -o /dev/null -F "csrf_beyanname=$T" -F "firma_adi=ELE GEÇİRİLDİ" \
       -F "logo=@/tmp/lg_test.png" "$B/tanimlar/firma-kaydet"
  ol "$kullanici logo/ad değiştiremedi" "BAŞAR MUHASEBE BÜROSU" "$(db "SELECT deger FROM ayarlar WHERE anahtar='firma_adi';")"
done
curl -s -b $JP -c $JP -o /tmp/lg_pliste.html "$B/tanimlar/ayarlar"
ol "personel ayarlar sayfasına giremiyor (menü kapalı)" "0" "$(grep -c 'Firma / Büro Kimliği' /tmp/lg_pliste.html)"
curl -s -b $JM -c $JM -o /tmp/lg_mus_ayar.html "$B/tanimlar/ayarlar"
ol "müşavir kartı görür" "1" "$(grep -c 'Firma / Büro Kimliği' /tmp/lg_mus_ayar.html | head -1 | awk '{print ($1>0)?1:0}')"
# Not: HTML'de "disabled" bir alt satıra düşebildiği için satır bazlı grep yeterli
# değil; alan blokları çok satırlı olarak kontrol edilir.
ol "müşavir alanları KİLİTLİ görür (disabled)" "1|1" \
   "$(python3 -c "
import re
h = open('/tmp/lg_mus_ayar.html', encoding='utf-8').read()
ad = 1 if re.search(r'name=\"firma_adi\"[^>]*disabled', h, re.S) else 0
lg = 1 if re.search(r'name=\"logo\"[^>]*disabled', h, re.S) else 0
print(str(ad) + '|' + str(lg))")"
ol "müşavire 'yalnız yönetici' uyarısı gösterilir" "1" \
   "$(grep -c 'Firma bilgilerini yalnız' /tmp/lg_mus_ayar.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "müşavire Kaydet düğmesi gösterilmez" "0" \
   "$(python3 -c "
import re
h=open('/tmp/lg_mus_ayar.html',encoding='utf-8').read()
b=re.search(r'Firma / Büro Kimliği([\s\S]{0,6000}?)Diğer|Firma / Büro Kimliği([\s\S]{0,6000})', h)
blok=b.group(0) if b else ''
print(1 if 'Tanimlar/firma-kaydet' in blok and '💾 Kaydet' in blok and 'disabled' not in blok.split('firma-kaydet')[1][:200] else 0)")"

echo
echo "=== 9) CSRF ==="
KOD=$(curl -s -b $JA -c $JA -o /dev/null -w '%{http_code}' -F "firma_adi=CSRF_SIZ" \
      -F "logo=@/tmp/lg_test.png" "$B/tanimlar/firma-kaydet")
ol "CSRF'siz POST reddedildi (403)" "403" "$KOD"
ol "  firma adı bozulmadı" "BAŞAR MUHASEBE BÜROSU" "$(db "SELECT deger FROM ayarlar WHERE anahtar='firma_adi';")"

echo
echo "=== 10) YENİ LOGO → ESKİ DOSYA SİLİNİR ==="
T=$(jeton $JA "$B/tanimlar/ayarlar")
curl -s -b $JA -c $JA -o /dev/null -F "csrf_beyanname=$T" -F "firma_adi=BAŞAR MUHASEBE BÜROSU" \
     -F "logo=@/tmp/lg_test.png" "$B/tanimlar/firma-kaydet"
YENI=$(db "SELECT deger FROM ayarlar WHERE anahtar='logo_dosya';")
ol "yeni dosya adı değişti" "1" "$([ "$YENI" != "$LOGO" ] && echo 1 || echo 0)"
ol "eski dosya diskten silindi" "0" "$([ -f "$PROJE/writable/uploads/logo/$LOGO" ] && echo 1 || echo 0)"
ol "klasörde tek dosya kaldı" "1" "$(ls -1 $PROJE/writable/uploads/logo/ | wc -l | tr -d ' ')"

echo
echo "=== 11) FİRMA ADI BOŞ → VARSAYILAN ==="
T=$(jeton $JA "$B/tanimlar/ayarlar")
curl -s -b $JA -c $JA -o /dev/null -F "csrf_beyanname=$T" -F "firma_adi=" -F "logo=@/tmp/lg_test.png" "$B/tanimlar/firma-kaydet"
ol "boş ad → 'Beyanname Takip'" "Beyanname Takip" "$(db "SELECT deger FROM ayarlar WHERE anahtar='firma_adi';")"
curl -s -b $JA -c $JA -o /tmp/lg_p2.html "$B/panel"
ol "sol üstte varsayılan ad" "LOGO|Beyanname Takip" "$(solUst /tmp/lg_p2.html)"

echo
echo "=== 12) LOGO KALDIRMA ==="
T=$(jeton $JA "$B/tanimlar/ayarlar")
curl -s -b $JA -c $JA -o /dev/null -F "csrf_beyanname=$T" -F "firma_adi=BAŞAR MUHASEBE BÜROSU" \
     -F "logo_kaldir=1" "$B/tanimlar/firma-kaydet"
ol "ayar temizlendi" "" "$(db "SELECT deger FROM ayarlar WHERE anahtar='logo_dosya';")"
ol "dosya silindi" "0" "$(ls -1 $PROJE/writable/uploads/logo/ 2>/dev/null | wc -l | tr -d ' ')"
curl -s -b $JA -c $JA -o /tmp/lg_p3.html "$B/panel"
ol "sol üstte varsayılan 📋 simgesi" "VARSAYILAN|BAŞAR MUHASEBE BÜROSU" "$(solUst /tmp/lg_p3.html)"
ol "logo-img artık çizilmiyor" "0" "$(grep -c 'class="logo-img"' /tmp/lg_p3.html)"

echo
echo "=== 13) LOGO DOSYALARI WEB'DEN ERİŞİLEMEZ ==="
ol "writable dizini public DIŞINDA" "0" "$([ -d "$PROJE/public/writable" ] && echo 1 || echo 0)"
KOD=$(curl -s -o /dev/null -w '%{http_code}' "$B/writable/uploads/logo/logo_test.png")
ol "doğrudan dosya isteği başarısız (404/403)" "1" "$([ "$KOD" = "404" ] || [ "$KOD" = "403" ] && echo 1 || echo 0)"

echo
echo "=== 14) TEMİZLİK (önceki duruma dön) ==="
db "UPDATE ayarlar SET deger='Beyanname Takip Sistemi' WHERE anahtar='firma_adi';" > /dev/null 2>&1
db "UPDATE ayarlar SET deger='' WHERE anahtar='logo_dosya';" > /dev/null 2>&1
rm -f $PROJE/writable/uploads/logo/logo_* /tmp/lg_*.png /tmp/lg_test.svg /tmp/lg_kotu.png /tmp/lg_buyuk.png 2>/dev/null
ol "ayarlar varsayılana döndü" "0" "$(db "SELECT COUNT(*) FROM ayarlar WHERE anahtar='logo_dosya' AND deger<>'';")"

echo
echo "======================================================"
echo " SONUÇ: $g geçti, $k hata"
echo "======================================================"
[ "$k" -eq 0 ]
