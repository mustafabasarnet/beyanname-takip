#!/bin/bash
# =====================================================================
#  KİŞİSEL NOTLAR — MENÜ ROZETİ + "OKUNDU" İZOLASYONU TESTİ
#
#  Kullanıcı istekleri:
#   1) "Kişisel notlarda tıpkı ajanda gibi menüde sayı görünsün — yapılmayanlar."
#   2) "Yapılanlar tıklanınca sayı düşsün."
#   3) "Kişisel not olduğu için okundu diğer kullanıcılara çıkmayacak."
#
#  Test ettikleri:
#    1) Menüde rozet var ve sayı = yapılmamış görev sayısı (canlı, DB ile birebir)
#    2) Görev tamamlanınca sayı DÜŞER (AJAX yanıtındaki acikSayisi ile eşleşir)
#    3) Görev eklenince sayı ARTAR
#    4) Tüm görevler bitince rozet GİZLENİR (0 → display:none)
#    5) Rozet KİŞİYE ÖZEL: personel yalnız kendi sayısını görür (admin'inki değil)
#    6) giris-uyarisi 'acik' alanı = menü rozetiyle aynı sayı
#    7) "Okundu" kaydı kişiye özel: admin'in okundu'su personelin penceresini
#       kapatmaz; personelin okundu'su admin'i etkilemez
#    8) Popup'tan görev tamamlanınca rozet düşer (JS yardımcısı + 'acik' alanı)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor.
#  Kullanım:  bash tests/kisisel_rozet_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
JA=/tmp/krz_admin.txt
JP=/tmp/krz_personel.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/krz_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/krz_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }
# Menüdeki rozet SAYISI (gizliyken 0 kabul edilir)
rozet(){ python3 -c "
import re
h=open('$1',encoding='utf-8').read()
m=re.search(r'id=\"kisisel-menu-rozet\"[^>]*>(\d+)</span>', h)
if not m: print('YOK'); raise SystemExit
tag=re.search(r'<span[^>]*id=\"kisisel-menu-rozet\"[^>]*>', h).group(0)
print(0 if 'display:none' in tag else m.group(1))"; }
# Rozetin görünürlük durumu: GIZLI / GORUNUR
rozetGizli(){ python3 -c "
import re
h=open('$1',encoding='utf-8').read()
tag=re.search(r'<span[^>]*id=\"kisisel-menu-rozet\"[^>]*>', h)
if not tag: print('YOK'); raise SystemExit
print('GIZLI' if 'display:none' in tag.group(0) else 'GORUNUR')"; }
gorevEkle(){ local T; T=$(jeton "$1" "$B/kisisel")
  curl -s -b "$1" -c "$1" -o /tmp/krz_ek.json -X POST "$B/kisisel/gorev-ekle" \
    -d "csrf_beyanname=$T" --data-urlencode "baslik=$2" -d "son_tarih=$3" -d "oncelik=normal"; }
gorevTers(){ local T; T=$(jeton "$1" "$B/kisisel")
  curl -s -b "$1" -c "$1" -o /tmp/krz_ters.json -X POST "$B/kisisel/gorev-ters" \
    -d "csrf_beyanname=$T" -d "id=$2"; }

BUGUN=$(date +%F)
DUN=$(date -d 'yesterday' +%F)

echo "=== 0) HAZIRLIK ==="
girisYap admin $JA
girisYap personel $JP
db "DELETE FROM kisisel_notlar WHERE baslik LIKE 'KRZ-TEST%';" >/dev/null 2>&1
db "DELETE FROM kisisel_uyari_okundu;" >/dev/null 2>&1
ol "admin girişi" "200" "$(curl -s -b $JA -o /dev/null -w '%{http_code}' $B/kisisel)"
ol "personel girişi" "200" "$(curl -s -b $JP -o /dev/null -w '%{http_code}' $B/kisisel)"

echo
echo "=== 1) MENÜ ROZETİ (yapılmayanlar) ==="
curl -s -b $JA -o /tmp/krz_panel0.html "$B/panel"
ADMIN_ACIK=$(db "SELECT COUNT(*) FROM kisisel_notlar WHERE kullanici_id=1 AND tur='gorev' AND tamamlandi=0;")
ol "rozet sayısı = yapılmamış görev sayısı" "$ADMIN_ACIK" "$(rozet /tmp/krz_panel0.html)"
ol "rozet kişisel sayfasında da var" "$ADMIN_ACIK" \
   "$(curl -s -b $JA "$B/kisisel" -o /tmp/krz_k0.html; rozet /tmp/krz_k0.html)"
ol "rozet başlığı açıklayıcı" "1" \
   "$(grep -c 'title="Yapılmamış kişisel görevler"' /tmp/krz_panel0.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 2) GÖREV EKLE → SAYI ARTAR ==="
ONCE=$(rozet /tmp/krz_panel0.html)
gorevEkle $JA "KRZ-TEST bir" "$DUN"
gorevEkle $JA "KRZ-TEST iki" "$BUGUN"
curl -s -b $JA -o /tmp/krz_panel1.html "$B/panel"
SONRA=$(rozet /tmp/krz_panel1.html)
ol "2 görev eklenince rozet +2" "$((ONCE+2))" "$SONRA"
ol "DB ile uyumlu" "$(db "SELECT COUNT(*) FROM kisisel_notlar WHERE kullanici_id=1 AND tur='gorev' AND tamamlandi=0;")" "$SONRA"

echo
echo "=== 3) GÖREV TAMAMLA → SAYI DÜŞER (AJAX 'acikSayisi' ile) ==="
GID=$(db "SELECT id FROM kisisel_notlar WHERE baslik='KRZ-TEST bir';")
gorevTers $JA "$GID"
AJAX_ACIK=$(python3 -c "
import json
print(json.load(open('/tmp/krz_ters.json',encoding='utf-8')).get('acikSayisi'))")
ol "AJAX yanıtı 'acikSayisi' 1 azaldı" "$((SONRA-1))" "$AJAX_ACIK"
curl -s -b $JA -o /tmp/krz_panel2.html "$B/panel"
ol "sayfa yenilenince rozet düşmüş" "$((SONRA-1))" "$(rozet /tmp/krz_panel2.html)"
ol "tamamlanan görev rozetçe SAYILMAZ (DB)" "1" \
   "$(db "SELECT COUNT(*) FROM kisisel_notlar WHERE baslik='KRZ-TEST bir' AND tamamlandi=1;")"

echo
echo "=== 4) TÜMÜ BİTİNCE ROZET GİZLENİR ==="
DB_ACIK=$(db "SELECT COUNT(*) FROM kisisel_notlar WHERE kullanici_id=1 AND tur='gorev' AND tamamlandi=0;")
i=0
for gid in $(db "SELECT id FROM kisisel_notlar WHERE kullanici_id=1 AND tur='gorev' AND tamamlandi=0;"); do
  gorevTers $JA "$gid"; i=$((i+1))
  [ $i -ge "$DB_ACIK" ] && break
done
ol "tüm açık görevler kapatıldı (0 açık)" "0" \
   "$(db "SELECT COUNT(*) FROM kisisel_notlar WHERE kullanici_id=1 AND tur='gorev' AND tamamlandi=0;")"
curl -s -b $JA -o /tmp/krz_panel3.html "$B/panel"
ol "açık görev yokken rozet GİZLİ" "GIZLI" "$(rozetGizli /tmp/krz_panel3.html)"
ol "  ve sayı 0" "0" "$(rozet /tmp/krz_panel3.html)"

# Sonraki bölüm için: admin görevlerini sil (rozet karşılaştırması temiz kalsın)
db "DELETE FROM kisisel_notlar WHERE baslik LIKE 'KRZ-TEST%';" >/dev/null 2>&1

echo
echo "=== 5) ROZET KİŞİYE ÖZEL (personel ↔ admin) ==="
gorevEkle $JA "KRZ-TEST admin gorev" "$BUGUN"
gorevEkle $JP "KRZ-TEST personel gorev" "$BUGUN"
gorevEkle $JP "KRZ-TEST personel gorev 2" "$BUGUN"
curl -s -b $JA -o /tmp/krz_a.html "$B/panel"
curl -s -b $JP -o /tmp/krz_p.html "$B/panel"
ol "admin rozeti yalnız kendi görevini sayar (1)" "1" "$(rozet /tmp/krz_a.html)"
ol "  admin rozeti görünür" "GORUNUR" "$(rozetGizli /tmp/krz_a.html)"
ol "personel rozeti yalnız kendi görevlerini sayar (2)" "2" "$(rozet /tmp/krz_p.html)"
ol "DB: admin açık=1, personel açık=2" "1|2" \
   "$(db "SELECT CONCAT(
        (SELECT COUNT(*) FROM kisisel_notlar WHERE kullanici_id=1 AND tur='gorev' AND tamamlandi=0),'|',
        (SELECT COUNT(*) FROM kisisel_notlar WHERE kullanici_id=2 AND tur='gorev' AND tamamlandi=0));")"

echo
echo "=== 6) giris-uyarisi 'acik' ALANI ROZETLE AYNI ==="
ACIK_API=$(curl -s -b $JA "$B/kisisel/giris-uyarisi" | python3 -c "
import json,sys
print(json.load(sys.stdin).get('acik'))")
ol "API 'acik' = menü rozeti" "$(rozet /tmp/krz_a.html)" "$ACIK_API"

echo
echo "=== 7) 'OKUNDU' KİŞİYE ÖZEL (birbirini etkilemez) ==="
db "DELETE FROM kisisel_uyari_okundu;" >/dev/null 2>&1
# Admin penceresini kapatır
T=$(jeton $JA "$B/kisisel")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/kisisel/uyari-okundu" -d "csrf_beyanname=$T"
ol "admin için okundu kaydı yazıldı" "1" \
   "$(db "SELECT COUNT(*) FROM kisisel_uyari_okundu WHERE kullanici_id=1 AND tarih=CURDATE();")"
ol "personel için okundu kaydı YOK" "0" \
   "$(db "SELECT COUNT(*) FROM kisisel_uyari_okundu WHERE kullanici_id=2 AND tarih=CURDATE();")"
ol "admin tekrar açılışta görmez" "False" \
   "$(curl -s -b $JA "$B/kisisel/giris-uyarisi" | python3 -c "import json,sys; print(json.load(sys.stdin).get('goster'))")"
ol "PERSONEL hâlâ görür (etkilenmedi)" "True" \
   "$(curl -s -b $JP "$B/kisisel/giris-uyarisi" | python3 -c "import json,sys; print(json.load(sys.stdin).get('goster'))")"
# Personel de kapatır → yalnız kendi satırı eklenir
T=$(jeton $JP "$B/kisisel")
curl -s -b $JP -c $JP -o /dev/null -X POST "$B/kisisel/uyari-okundu" -d "csrf_beyanname=$T"
ol "personel kapatınca kendi kaydı oluşur" "2" \
   "$(db "SELECT COUNT(*) FROM kisisel_uyari_okundu WHERE tarih=CURDATE();")"
ol "  admin'in kaydı aynen duruyor" "1" \
   "$(db "SELECT COUNT(*) FROM kisisel_uyari_okundu WHERE kullanici_id=1 AND tarih=CURDATE();")"

echo
echo "=== 8) DOSYADA CANLI ROZET YARDIMCISI ==="
curl -s -b $JA -o /tmp/krz_lay.html "$B/panel"
ol "layout'ta kisiselRozetGuncelle tanımlı" "1" \
   "$(grep -c 'window.kisiselRozetGuncelle' /tmp/krz_lay.html | awk '{print ($1>0)?1:0}')"
ol "kişisel sayfasında çağrılıyor" "1" \
   "$(grep -c 'kisiselRozetGuncelle(a)' /tmp/krz_k0.html | awk '{print ($1>0)?1:0}')"
ol "popup hazırlığında çağrılıyor" "1" \
   "$(grep -c 'kisiselRozetGuncelle(v.acik)\|kisiselRozetGuncelle(Math.max' /tmp/krz_lay.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 9) TEMİZLİK ==="
db "DELETE FROM kisisel_notlar WHERE baslik LIKE 'KRZ-TEST%';" >/dev/null
db "DELETE FROM kisisel_uyari_okundu;" >/dev/null
ol "test verisi temizlendi" "0" "$(db "SELECT COUNT(*) FROM kisisel_notlar WHERE baslik LIKE 'KRZ-TEST%';")"

echo
echo "========================================"
if [ $k -eq 0 ]; then echo "TÜM TESTLER BAŞARILI ($g geçti / $((g+k)))"; else echo "$k HATA ($g geçti / $((g+k)))"; fi
rm -f $JA $JP
[ $k -eq 0 ] || exit 1
