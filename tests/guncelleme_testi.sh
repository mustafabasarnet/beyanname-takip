#!/bin/bash
# =====================================================================
#  GÜNCELLEME LOGLARI — EKRAN + GİRİŞ PENCERESİ TESTİ
#
#  Kullanıcı istekleri:
#   1) "Güncelleme logları olan bir ekran istiyorum."
#   2) "Güncellemede değiştirilen/eklenen kısımlar modern bir pop-up ile
#       kullanıcıya girişte yansıtılsın."
#   3) "Okunduğunda tekrar görünmesin."
#
#  Test ettikleri:
#    1) Ekran tüm rollerde açılıyor; sürüm/madde listesi doğru çiziliyor
#    2) İçerik biçimi: + eklenen · ~ değişen · ! düzeltilen · - kaldırılan · not
#    3) Yönetici kayıt ekler/düzenler/siler; diğer rolleri ENGELLENİR
#    4) Yayında olmayan (aktif=0) kayıt kullanıcıya gösterilmez
#    5) Giriş penceresi verisi yalnız OKUNMAMIŞLARI döner
#    6) "Okudum" → o kullanıcı için bir daha gösterilmez
#    7) Okundu bilgisi KİŞİ BAZLI: biri okuması diğerini etkilemez
#    8) Menü rozeti okunmamış sayısını gösterir, okuyunca kaybolur
#    9) CSRF'siz okundu POST'u reddedilir
#   10) Popup markup'ı ve sıra bekleyicisi her sayfada var
#   11) XSS: madde içeriği kaçırılıyor (ham <script> basılmaz)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor.
#  Kullanım:  bash tests/guncelleme_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
JA=/tmp/gn_admin.txt
JP=/tmp/gn_personel.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/gn_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/gn_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }
jalin(){ python3 -c "
import json,sys
try: d=json.load(open('$1',encoding='utf-8'))
except Exception: print('YOK'); sys.exit()
v=d.get('$2')
print(json.dumps(v,ensure_ascii=False) if isinstance(v,(list,dict)) else v)"; }
# Menü rozeti (gizliyse 0)
rozet(){ python3 -c "
import re
h=open('$1',encoding='utf-8').read()
m=re.search(r'id=\"guncelleme-menu-rozet\"[^>]*style=\"([^\"]*)\">(\d+)', h)
print(0 if (not m or 'display:none' in m.group(1)) else m.group(2))"; }
# Güncelleme ekle: $1=jar $2=versiyon $3=baslik $4=icerik $5=aktif(0/1)
gnEkle(){ local T; T=$(jeton "$1" "$B/guncellemeler/yeni")
  curl -s -b "$1" -c "$1" -o /dev/null -w '%{redirect_url}' -X POST "$B/guncellemeler/kaydet" \
    -d "csrf_beyanname=$T" -d "id=0" -d "versiyon=$2" -d "tarih=$(date +%F)" \
    --data-urlencode "baslik=$3" --data-urlencode "icerik=$4" -d "aktif=$5"; }

echo "=== 0) HAZIRLIK ==="
girisYap admin $JA
girisYap personel $JP
# Test kayıtlarını temizle (okundu kayıtları cascade gider)
db "DELETE FROM guncellemeler WHERE versiyon LIKE '9.%' OR versiyon LIKE '8.%';" >/dev/null 2>&1
ol "admin girişi" "200" "$(curl -s -b $JA -o /dev/null -w '%{http_code}' $B/guncellemeler)"
ol "personel girişi" "200" "$(curl -s -b $JP -o /dev/null -w '%{http_code}' $B/guncellemeler)"

echo
echo "=== 1) EKRAN (her rol okur) ==="
curl -s -b $JA -c $JA -o /tmp/gn_a.html "$B/guncellemeler"
ol "yönetici listeyi görüyor" "1" "$(grep -c 'gn-kart' /tmp/gn_a.html | awk '{print ($1>0)?1:0}')"
ol "yönetici 'Yeni Güncelleme' düğmesini görüyor" "1" "$(grep -c 'guncellemeler/yeni' /tmp/gn_a.html | awk '{print ($1>0)?1:0}')"
curl -s -b $JP -c $JP -o /tmp/gn_p.html "$B/guncellemeler"
ol "personel listeyi görüyor" "1" "$(grep -c 'gn-kart' /tmp/gn_p.html | awk '{print ($1>0)?1:0}')"
ol "personel 'Yeni Güncelleme' düğmesini GÖRMÜYOR" "0" "$(grep -c 'guncellemeler/yeni' /tmp/gn_p.html | awk '{print ($1>0)?1:0}')"
ol "personel düzenle/sil bağlantısı GÖRMÜYOR" "0" "$(grep -c 'guncellemeler/duzenle' /tmp/gn_p.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 2) İÇERİK BİÇİMİ (madde türleri) ==="
gnEkle $JA "9.9.1" "Biçim testi" "+ **Kalın** eklenen madde
~ Değişen madde
! Düzeltilen madde
- Kaldırılan madde
İşaretsiz not maddesi" 1 >/dev/null
GID=$(db "SELECT id FROM guncellemeler WHERE versiyon='9.9.1' LIMIT 1;")
curl -s -b $JA -c $JA -o /tmp/gn_bicim.html "$B/guncellemeler"
ol "kayıt oluştu" "1" "$([ -n "$GID" ] && echo 1 || echo 0)"
for tur in "Eklendi" "Değişti" "Düzeltildi" "Kaldırıldı" "Not"; do
  ol "  '$tur' rozeti çizildi" "1" "$(grep -c ">.*$tur</span>" /tmp/gn_bicim.html | awk '{print ($1>0)?1:0}')"
done
ol "5 madde sayıldı" "5" "$(db "SELECT (LENGTH(icerik)-LENGTH(REPLACE(icerik,CHAR(10),'')))+1 FROM guncellemeler WHERE id=$GID;" | tr -d '\n')"
# Yalnız madde metinleri içindeki ham ** aranır (popup JS'inde regex olarak geçer)
ol "**kalın** vurgu <b>'ye dönüştürüldü" "0" \
   "$(grep -o '\.mt">[^<]*\*\*[^<]*' /tmp/gn_bicim.html | wc -l | tr -d ' ')"
ol "  kalın metin <b> ile sarıldı" "1" \
   "$(grep -c '<b>Kalın</b>' /tmp/gn_bicim.html | awk '{print ($1>0)?1:0}')"
ol "işaretler (+) metinden temizlendi" "0" "$(grep -c '>+ Eklenen' /tmp/gn_bicim.html)"

echo
echo "=== 3) YAYINDA OLMAYAN KAYIT KULLANICIYA GİTMEZ ==="
gnEkle $JA "9.9.2" "Taslak sürüm" "+ Henüz yayında değil" 0 >/dev/null
TID=$(db "SELECT id FROM guncellemeler WHERE versiyon='9.9.2' LIMIT 1;")
ol "taslak kayıt oluştu (aktif=0)" "0" "$(db "SELECT aktif FROM guncellemeler WHERE id=$TID;")"
curl -s -b $JP -c $JP -o /tmp/gn_taslak.html "$B/guncellemeler"
ol "personel taslağı listede GÖRMÜYOR" "0" "$(grep -c 'Taslak sürüm' /tmp/gn_taslak.html | awk '{print ($1>0)?1:0}')"
curl -s -b $JA -c $JA -o /tmp/gn_taslakA.html "$B/guncellemeler"
ol "yönetici taslağı 'Yayında Olmayanlar' bölümünde görüyor" "1" \
   "$(grep -c 'Yayında Olmayanlar' /tmp/gn_taslakA.html | awk '{print ($1>0)?1:0}')"
db "DELETE FROM guncellemeler WHERE id=$TID;" >/dev/null

echo
echo "=== 4) GİRİŞ PENCERESİ: yalnız okunmamışlar ==="
db "DELETE FROM guncelleme_okundu WHERE kullanici_id=2;" >/dev/null 2>&1
curl -s -b $JP -o /tmp/gn_u1.json "$B/guncellemeler/giris-uyarisi"
ol "personel için pencere gösterilecek" "True" "$(jalin /tmp/gn_u1.json goster)"
ol "  yeni sürüm listede var" "1" "$(python3 -c "
import json; d=json.load(open('/tmp/gn_u1.json',encoding='utf-8'))
print(sum(1 for x in d.get('kayitlar',[]) if x['versiyon']=='9.9.1'))")"
ol "  taslak (aktif=0) listede YOK" "0" "$(python3 -c "
import json; d=json.load(open('/tmp/gn_u1.json',encoding='utf-8'))
print(sum(1 for x in d.get('kayitlar',[]) if x['baslik']=='Taslak sürüm'))")"
ol "  maddeler tür/renk bilgisiyle geliyor" "1" "$(python3 -c "
import json; d=json.load(open('/tmp/gn_u1.json',encoding='utf-8'))
k=[x for x in d.get('kayitlar',[]) if x['versiyon']=='9.9.1'][0]
print(1 if all(set(('tur','ad','ikon','renk','metin'))<=set(m) for m in k['maddeler']) else 0)")"

echo
echo "=== 5) OKUNDU → BİR DAHA GÖSTERİLMEZ ==="
T=$(jeton $JP "$B/guncellemeler")
curl -s -b $JP -c $JP -o /tmp/gn_ok.json -X POST "$B/guncellemeler/uyari-okundu" \
  -d "csrf_beyanname=$T" -d "idler[]=$GID" -d "idler[]=1"
ol "okundu işaretlendi" "True" "$(python3 -c "
import json; print(json.load(open('/tmp/gn_ok.json',encoding='utf-8')).get('durum'))")"
ol "  DB'de kayıt oluştu" "1" \
   "$(db "SELECT COUNT(*) FROM guncelleme_okundu WHERE kullanici_id=2 AND guncelleme_id=$GID;")"
curl -s -b $JP -o /tmp/gn_u2.json "$B/guncellemeler/giris-uyarisi"
ol "9.9.1 artık GÖSTERİLMİYOR" "0" "$(python3 -c "
import json; d=json.load(open('/tmp/gn_u2.json',encoding='utf-8'))
print(sum(1 for x in d.get('kayitlar',[]) or [] if x['versiyon']=='9.9.1'))")"

echo
echo "=== 6) OKUNDU KİŞİ BAZLI (izolasyon) ==="
curl -s -b $JA -o /tmp/gn_u3.json "$B/guncellemeler/giris-uyarisi"
ol "admin 9.9.1'i HÂLÂ görüyor (okumadı)" "1" "$(python3 -c "
import json; d=json.load(open('/tmp/gn_u3.json',encoding='utf-8'))
print(sum(1 for x in d.get('kayitlar',[]) or [] if x['versiyon']=='9.9.1'))")"
ol "admin'in okundu kaydı yok" "0" \
   "$(db "SELECT COUNT(*) FROM guncelleme_okundu WHERE kullanici_id=1 AND guncelleme_id=$GID;")"

echo
echo "=== 7) MENÜ ROZETİ ==="
db "DELETE FROM guncelleme_okundu WHERE kullanici_id=1;" >/dev/null 2>&1
AKTIF_SAYI=$(db "SELECT COUNT(*) FROM guncellemeler WHERE aktif=1;")
curl -s -b $JA -c $JA -o /tmp/gn_panel.html "$B/panel"
ol "rozet = okunmamış güncelleme sayısı" "$AKTIF_SAYI" "$(rozet /tmp/gn_panel.html)"
curl -s -b $JP -c $JP -o /tmp/gn_panel_p.html "$B/panel"
P_SAYI=$(db "SELECT COUNT(*) FROM guncellemeler WHERE aktif=1 AND id NOT IN (SELECT guncelleme_id FROM guncelleme_okundu WHERE kullanici_id=2);")
ol "personel rozeti kendi okunmamışlarını sayar" "$P_SAYI" "$(rozet /tmp/gn_panel_p.html)"
ol "  admin ve personel rozeti farklı (kişi bazlı)" "1" \
   "$([ "$AKTIF_SAYI" != "$P_SAYI" ] && echo 1 || echo 0)"

echo
echo "=== 8) GÜVENLİK / YETKİ ==="
ol "CSRF'siz okundu POST'u reddedildi (403)" "403" \
   "$(curl -s -b $JP -o /dev/null -w '%{http_code}' -X POST "$B/guncellemeler/uyari-okundu")"
ol "personel yeni kayıt ekleyemez (302 → listeye)" "/guncellemeler" \
   "$(curl -s -b $JP -o /dev/null -w '%{redirect_url}' "$B/guncellemeler/yeni" | sed "s|$B||")"
T=$(jeton $JP "$B/guncellemeler")
ONCE=$(db "SELECT COUNT(*) FROM guncellemeler;")
curl -s -b $JP -c $JP -o /dev/null -X POST "$B/guncellemeler/kaydet" \
  -d "csrf_beyanname=$T" -d "id=0" -d "versiyon=9.9.9" -d "tarih=$(date +%F)" \
  --data-urlencode "baslik=Personel ekleme denemesi" --data-urlencode "icerik=+ olmamalı" -d "aktif=1"
ol "personel kaydet denemesi ENGELLENDİ" "$ONCE" "$(db "SELECT COUNT(*) FROM guncellemeler;")"
ol "  'yetkiniz yok' uyarısı gösterildi" "1" \
   "$(curl -s -b $JP "$B/guncellemeler" | grep -c 'yetkiniz yok' | awk '{print ($1>0)?1:0}')"
ol "personel düzenle sayfasına giremez" "/guncellemeler" \
   "$(curl -s -b $JP -o /dev/null -w '%{redirect_url}' "$B/guncellemeler/duzenle/$GID" | sed "s|$B||")"
ol "personel kaydı silemez" "1" \
   "$(curl -s -b $JP -o /dev/null -w '%{http_code}' "$B/guncellemeler/sil/$GID" >/dev/null; db "SELECT COUNT(*) FROM guncellemeler WHERE id=$GID;")"

echo
echo "=== 9) XSS KORUMASI ==="
gnEkle $JA "9.9.3" "XSS denemesi" '+ <script>alert(1)</script> maddesi' 1 >/dev/null
curl -s -b $JA -c $JA -o /tmp/gn_xss.html "$B/guncellemeler"
ol "ham <script> basılmadı (kaçırıldı)" "0" "$(grep -c '<script>alert(1)</script>' /tmp/gn_xss.html)"
ol "  kaçırılmış biçimde göründü" "1" "$(grep -c '&lt;script&gt;' /tmp/gn_xss.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 10) YÖNETİCİ DÜZENLE / SİL ==="
T=$(jeton $JA "$B/guncellemeler/duzenle/$GID")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/guncellemeler/kaydet" \
  -d "csrf_beyanname=$T" -d "id=$GID" -d "versiyon=9.9.1" -d "tarih=$(date +%F)" \
  --data-urlencode "baslik=Biçim testi (güncellendi)" --data-urlencode "icerik=+ Tek madde kaldı" -d "aktif=1"
ol "başlık güncellendi" "Biçim testi (güncellendi)" \
   "$(db "SELECT baslik FROM guncellemeler WHERE id=$GID;")"
ol "  madde sayısı 1'e indi" "1" \
   "$(db "SELECT (LENGTH(icerik)-LENGTH(REPLACE(icerik,CHAR(10),'')))+1 FROM guncellemeler WHERE id=$GID;")"
db "DELETE FROM guncelleme_okundu WHERE guncelleme_id=$GID;" >/dev/null
curl -s -b $JA -c $JA -o /dev/null "$B/guncellemeler/sil/$GID"
ol "kayıt silindi" "0" "$(db "SELECT COUNT(*) FROM guncellemeler WHERE id=$GID;")"

echo
echo "=== 11) POPUP SAYFAYA GÖMÜLÜ MÜ ==="
curl -s -b $JA -c $JA -o /tmp/gn_layout.html "$B/panel"
curl -s -b $JP -c $JP -o /tmp/gn_menu_personel.html "$B/panel"
ol "pencere kutusu var (gn-uyari-ort)" "1" "$(grep -c 'id="gn-uyari-ort"' /tmp/gn_layout.html | awk '{print ($1>0)?1:0}')"
ol "başlık: Neler Değişti?" "1" "$(grep -c 'Neler Değişti' /tmp/gn_layout.html | awk '{print ($1>0)?1:0}')"
ol "giris-uyarisi fetch ediliyor" "1" "$(grep -c 'guncellemeler/giris-uyarisi' /tmp/gn_layout.html | awk '{print ($1>0)?1:0}')"
ol "okundu POST'u var" "1" "$(grep -c 'guncellemeler/uyari-okundu' /tmp/gn_layout.html | awk '{print ($1>0)?1:0}')"
ol "diğer pencereleri bekleyen sıra mantığı var" "1" "$(grep -c 'digerleriKapaliMi' /tmp/gn_layout.html | awk '{print ($1>0)?1:0}')"
ol "menüde Güncellemeler bağlantısı var" "1" "$(grep -c 'guncellemeler"' /tmp/gn_layout.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 12) MENÜ KONUMU (Sistem bölümünün en altı) ==="
# Menüyü bölüm başlıklarına göre çözer: her öge hangi bölümde, kaçıncı sırada
menuParcala(){ python3 -c "
import re, sys
h = open('$1', encoding='utf-8').read()
nav = re.search(r'<nav class=\"menu-liste\">(.*?)</nav>', h, re.S)
if not nav: print('NAV YOK'); sys.exit()
bolum = ''
for m in re.finditer(r'<div class=\"menu-baslik\">([^<]+)</div>|</span>\s*([^<\n]+?)\s*\n', nav.group(1)):
    if m.group(1):
        bolum = m.group(1).strip()
    elif m.group(2).strip():
        print(bolum + '|' + m.group(2).strip())
"; }
menuParcala /tmp/gn_layout.html > /tmp/gn_menu_admin.txt
menuParcala /tmp/gn_menu_personel.html > /tmp/gn_menu_personel.txt

ol "admin: Güncellemeler 'Sistem' bölümünde" "1" \
   "$(grep -c '^Sistem|Güncellemeler$' /tmp/gn_menu_admin.txt)"
ol "admin: Sistem bölümünün EN SON ögesi" "1" \
   "$(python3 -c "
satirlar=[l.strip() for l in open('/tmp/gn_menu_admin.txt',encoding='utf-8') if l.strip()]
sistem=[l.split('|',1)[1] for l in satirlar if l.startswith('Sistem|')]
print(1 if sistem and sistem[-1]=='Güncellemeler' else 0)")"
ol "admin: Genel bölümünde artık YOK" "0" \
   "$(grep -c '^Genel|Güncellemeler$' /tmp/gn_menu_admin.txt)"
ol "admin: yönetici araçları hâlâ Sistem'de" "5" \
   "$(grep -c '^Sistem|' /tmp/gn_menu_admin.txt | awk '{print $1-1}')"
ol "personel: Güncellemeler Sistem bölümünde (erişim korundu)" "1" \
   "$(grep -c '^Sistem|Güncellemeler$' /tmp/gn_menu_personel.txt)"
ol "personel: Sistem bölümünde BAŞKA öge yok (admin araçları gizli)" "1" \
   "$(grep -c '^Sistem|' /tmp/gn_menu_personel.txt)"

ol "giriş ekranında popup YOK" "0" "$(curl -s "$B/giris" | grep -c 'gn-uyari-ort')"

echo
echo "=== 13) TEMİZLİK ==="
db "DELETE FROM guncellemeler WHERE versiyon LIKE '9.%';" >/dev/null
db "DELETE FROM guncelleme_okundu WHERE kullanici_id IN (1,2);" >/dev/null
ol "test kayıtları temizlendi" "0" "$(db "SELECT COUNT(*) FROM guncellemeler WHERE versiyon LIKE '9.%';")"
ol "okundu kayıtları temizlendi" "0" "$(db "SELECT COUNT(*) FROM guncelleme_okundu;")"

echo
echo "========================================"
if [ $k -eq 0 ]; then echo "TÜM TESTLER BAŞARILI ($g geçti / $((g+k)))"; else echo "$k HATA ($g geçti / $((g+k)))"; fi
rm -f $JA $JP
[ $k -eq 0 ] || exit 1
