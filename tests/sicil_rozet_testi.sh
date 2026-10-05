#!/bin/bash
# =====================================================================
#  SİCİL — MENÜ ROZETİ (son günü gelen / gecikmiş todo sayısı)
#
#  Kullanıcı isteği:
#    "Sicil işlemlerinde son gün gelince tıpkı ajanda da olduğu gibi kaç
#     işlem varsa yanına sayı rozeti yapalım."
#
#  Test ettikleri:
#    1) Menüde "Sicil İşlemleri" yanında rozet var ve HER SAYFADA görünür
#    2) Sayaç = SON GÜNÜ GELMİŞ (bugün) + GECİKMİŞ, hâlâ AÇIK todolar
#       (ajanda rozetiyle aynı mantık)
#    3) İleri tarihli açık todo SAYILMAZ (henüz son günü gelmedi)
#    4) TAMAM / GEREKSIZ (takip dışı) todolar SAYILMAZ
#    5) Yumuşak silinmiş todo ve silinmiş işlem SAYILMAZ
#    6) Eski durumlar (HAZIR/GONDERILDI) açık sayılır → sayılır
#    7) Kapsam izolasyonu: personel yalnız eriştiği müşaviri sayar; admin tümü
#    8) AJAX "Yapıldı" → rozet alanı döner ve sayı DÜŞER
#    9) "Takip dışı" da rozeti düşürür; hepsi kapanınca rozet GİZLENİR
#   10) Yetkisiz kullanıcı başka müşavirin todo'sunu değiştiremez (403)
#   11) Canlı güncelleme yardımcısı (JS) layout'ta tanımlı
#   12) CSRF'siz POST reddedilir (403)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin|personel / Test1234 hesapları mevcut.
#  Not: Kendi verisini kurar (SROZ- önekli kayıtlar) ve sonunda temizler.
#  Kullanım:  bash tests/sicil_rozet_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
JP=/tmp/sroz_personel.txt
JA=/tmp/sroz_admin.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/sroz_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/sroz_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Rozeti okur: sayi | gizli | ipucu
rozet(){ python3 -c "
import re, html, json
h = open('$1', encoding='utf-8').read()
blok = re.search(r'<span class=\"menu-rozet sicil\" id=\"sicil-menu-rozet\"[^>]*>(\d+)</span>', h)
if not blok:
    print(json.dumps({'sayi': None, 'gizli': None, 'ipucu': None})); raise SystemExit
tag = re.search(r'<span class=\"menu-rozet sicil\" id=\"sicil-menu-rozet\"[^>]*>', h).group(0)
stil = re.search(r'style=\"([^\"]*)\"', tag)
ipucu = re.search(r'title=\"([^\"]*)\"', tag)
print(json.dumps({
    'sayi': int(blok.group(1)),
    'gizli': bool(stil and 'display:none' in stil.group(1)),
    'ipucu': html.unescape(ipucu.group(1)) if ipucu else None,
}, ensure_ascii=False))"; }
alan(){ python3 -c "
import json,sys
d=json.loads(sys.stdin.read() or '{}')
v=d.get('$1')
print('YOK' if v is None else (str(v).lower() if isinstance(v,bool) else v))"; }
# $1 = alan, $2 = dosya (varsayılan ilk yanıt)
# $1 = alan, $2 = yanıt dosyası (varsayılan: ilk AJAX yanıtı)
jalin(){ local d="${2:-/tmp/sroz_json.html}"
  grep -oP "\"$1\"\s*:\s*(\"[^\"]*\"|[0-9]+|true|false)" "$d" | head -1 | sed 's/.*:\s*//;s/"//g'; }

echo "=== 0) HAZIRLIK ==="
girisYap personel $JP
girisYap admin $JA
PERS_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='personel' LIMIT 1;")
ADMIN_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='admin' LIMIT 1;")
MUK_ID=$(db "SELECT id FROM mukellefler WHERE deleted_at IS NULL ORDER BY id LIMIT 1;")
TUR_ID=$(db "SELECT id FROM sicil_degisiklik_turleri ORDER BY id LIMIT 1;")
ol "kullanıcı/mükellef/tür bulundu" "1" "$([ -n "$PERS_ID" ] && [ -n "$ADMIN_ID" ] && [ -n "$MUK_ID" ] && [ -n "$TUR_ID" ] && echo 1 || echo 0)"

# Temizlik (önceki koşumdan kalanlar) — SROZ- önekli mükellef işlemleri cascade silinir
db "DELETE FROM sicil_degisiklikleri WHERE konu LIKE 'SROZ-%';" > /dev/null 2>&1
db "DELETE FROM mukellefler WHERE unvan LIKE 'SROZ-TEST%';" > /dev/null 2>&1

# İkinci kapsam mükellefi: müşavir 2'ye bağlı (personel bu müşaviri GÖRMEZ)
MUK2_ID=$($MDBR -N -B -e "INSERT INTO mukellefler (musavir_id,unvan,mukellef_tipi,defter_tipi,edefter_sorumlu_id,edefter_donem,ise_baslama_tarihi,aktif,created_at,updated_at)
  VALUES (2,'SROZ-TEST kapsam dışı','tuzel','bilanco',NULL,'YOK',CURDATE(),1,NOW(),NOW()); SELECT LAST_INSERT_ID();")

# --- İşlem 1 (kapsam içi): 6 todo ---
DEG1=$($MDBR -N -B -e "INSERT INTO sicil_degisiklikleri (mukellef_id,turu_id,degisiklik_tarihi,konu,durum,kaydeden_id,created_at,updated_at)
  VALUES ($MUK_ID,$TUR_ID,CURDATE(),'SROZ-1 işlem','BEKLIYOR',$ADMIN_ID,NOW(),NOW()); SELECT LAST_INSERT_ID();")
$MDBR -e "
INSERT INTO sicil_bildirim_gorevleri (sicil_degisikligi_id,kural_id,ad,son_tarih,durum,yapan_id,tamamlanma_tarihi,created_at,updated_at,deleted_at) VALUES
($DEG1,NULL,'SROZ-1 gecikmiş açık',   DATE_SUB(CURDATE(), INTERVAL 3 DAY),'BEKLIYOR',NULL,NULL,NOW(),NOW(),NULL),
($DEG1,NULL,'SROZ-2 bugün eski durum',CURDATE(),                          'HAZIR',   NULL,NULL,NOW(),NOW(),NULL),
($DEG1,NULL,'SROZ-3 ileri tarih',     DATE_ADD(CURDATE(), INTERVAL 4 DAY),'BEKLIYOR',NULL,NULL,NOW(),NOW(),NULL),
($DEG1,NULL,'SROZ-4 tamamlanmış',     DATE_SUB(CURDATE(), INTERVAL 3 DAY),'TAMAM',   $PERS_ID,NOW(),NOW(),NOW(),NULL),
($DEG1,NULL,'SROZ-5 takip dışı',      DATE_SUB(CURDATE(), INTERVAL 3 DAY),'GEREKSIZ',$PERS_ID,NULL,NOW(),NOW(),NULL),
($DEG1,NULL,'SROZ-6 silinmiş todo',   DATE_SUB(CURDATE(), INTERVAL 3 DAY),'BEKLIYOR',NULL,NULL,NOW(),NOW(),NOW());" > /dev/null 2>&1

# --- İşlem 2 (kapsam dışı): gecikmiş açık todo ---
DEG2=$($MDBR -N -B -e "INSERT INTO sicil_degisiklikleri (mukellef_id,turu_id,degisiklik_tarihi,konu,durum,kaydeden_id,created_at,updated_at)
  VALUES ($MUK2_ID,$TUR_ID,CURDATE(),'SROZ-2 kapsam dışı işlem','BEKLIYOR',$ADMIN_ID,NOW(),NOW()); SELECT LAST_INSERT_ID();")
$MDBR -e "INSERT INTO sicil_bildirim_gorevleri (sicil_degisikligi_id,kural_id,ad,son_tarih,durum,created_at,updated_at)
  VALUES ($DEG2,NULL,'SROZ-7 kapsam dışı gecikmiş',DATE_SUB(CURDATE(), INTERVAL 1 DAY),'BEKLIYOR',NOW(),NOW());" > /dev/null 2>&1

# --- İşlem 3 (silinmiş işlem, kapsam içi): gecikmiş açık todo ---
DEG3=$($MDBR -N -B -e "INSERT INTO sicil_degisiklikleri (mukellef_id,turu_id,degisiklik_tarihi,konu,durum,kaydeden_id,created_at,updated_at,deleted_at)
  VALUES ($MUK_ID,$TUR_ID,CURDATE(),'SROZ-3 silinmiş işlem','BEKLIYOR',$ADMIN_ID,NOW(),NOW(),NOW()); SELECT LAST_INSERT_ID();")
$MDBR -e "INSERT INTO sicil_bildirim_gorevleri (sicil_degisikligi_id,kural_id,ad,son_tarih,durum,created_at,updated_at)
  VALUES ($DEG3,NULL,'SROZ-8 silinmiş işlem todo',DATE_SUB(CURDATE(), INTERVAL 2 DAY),'BEKLIYOR',NOW(),NOW());" > /dev/null 2>&1

T1=$(db "SELECT id FROM sicil_bildirim_gorevleri WHERE ad='SROZ-1 gecikmiş açık';")
T2=$(db "SELECT id FROM sicil_bildirim_gorevleri WHERE ad='SROZ-2 bugün eski durum';")
T7=$(db "SELECT id FROM sicil_bildirim_gorevleri WHERE ad='SROZ-7 kapsam dışı gecikmiş';")
ol "test verisi kuruldu (3 işlem / 8 todo)" "8" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE ad LIKE 'SROZ-%';")"

echo
echo "=== 1) ROZET MENÜDE VE HER SAYFADA ==="
for yol in "panel" "takip" "evrak" "sicil"; do
  curl -s -b $JP -c $JP -o /tmp/sroz_$yol.html "$B/$yol"
  RS=$(rozet /tmp/sroz_$yol.html | alan sayi)
  ol "/$yol sayfasında sicil rozeti var" "True" \
     "$([ "$RS" != "YOK" ] && [ "$RS" != "None" ] && echo True || echo False)"
done
ol "ipucu metni: son günü gelen + gecikmiş açıklaması" "1" \
   "$(rozet /tmp/sroz_panel.html | alan ipucu | grep -c 'Son günü gelen ve gecikmiş yapılmamış sicil işleri')"

echo
echo "=== 2) SAYAÇ = SON GÜNÜ GELMİŞ + GECİKMİŞ AÇIK TODOLAR (personel: 2) ==="
ol "personel rozeti 2 (SROZ-1 gecikmiş + SROZ-2 bugün/HAZIR)" "2" "$(rozet /tmp/sroz_panel.html | alan sayi)"
ol "rozet görünür (gizli değil)" "false" "$(rozet /tmp/sroz_panel.html | alan gizli)"
ol "DB ile uyumlu (kapsam içi, silinmemiş)" "2" \
   "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri g JOIN sicil_degisiklikleri d ON d.id=g.sicil_degisikligi_id
          WHERE g.ad LIKE 'SROZ-%' AND g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI') AND g.son_tarih <= CURDATE()
            AND g.deleted_at IS NULL AND d.deleted_at IS NULL AND d.mukellef_id=$MUK_ID;")"

echo
echo "=== 3) SAYILMAYANLAR ==="
ol "ileri tarihli açık todo sayılmadı (SROZ-3)" "0" \
   "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE ad='SROZ-3 ileri tarih' AND son_tarih <= CURDATE();")"
ol "TAMAM sayılmadı (SROZ-4)" "0" \
   "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE ad='SROZ-4 tamamlanmış' AND durum IN ('BEKLIYOR','HAZIR','GONDERILDI');")"
ol "GEREKSIZ sayılmadı (SROZ-5)" "0" \
   "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE ad='SROZ-5 takip dışı' AND durum IN ('BEKLIYOR','HAZIR','GONDERILDI');")"
ol "silinmiş todo sayılmadı (SROZ-6)" "0" \
   "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE ad='SROZ-6 silinmiş todo' AND deleted_at IS NULL;")"
ol "silinmiş işlemin todo'su sayılmadı (SROZ-8)" "0" \
   "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri g JOIN sicil_degisiklikleri d ON d.id=g.sicil_degisikligi_id
          WHERE g.ad='SROZ-8 silinmiş işlem todo' AND d.deleted_at IS NULL;")"

echo
echo "=== 4) ADMIN KAPSAMI: tümü (kapsam dışı mükellef dahil = 3) ==="
curl -s -b $JA -c $JA -o /tmp/sroz_panel_a.html "$B/panel"
ol "admin rozeti 3 (personelin 2 + kapsam dışı 1)" "3" "$(rozet /tmp/sroz_panel_a.html | alan sayi)"

echo
echo "=== 5) İZOLE: kapsam dışı todo personelin sayacına GİRMEZ ==="
ol "personel sayısı hâlâ 2" "2" "$(rozet /tmp/sroz_panel.html | alan sayi)"
ol "kapsam dışı todo DB'de açık+gecikmiş" "1" \
   "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE ad='SROZ-7 kapsam dışı gecikmiş' AND durum='BEKLIYOR' AND son_tarih <= CURDATE();")"

echo
echo "=== 6) AJAX 'YAPILDI' → ROZET DÜŞER ==="
T=$(jeton $JP "$B/sicil")
curl -s -b $JP -c $JP -o /tmp/sroz_json.html -X POST "$B/sicil/todo-durum" -d "csrf_beyanname=$T" -d "id=$T1" -d "durum=TAMAM"
ol "AJAX durum=true" "true" "$(jalin durum)"
ol "AJAX rozet=1 döndü (2 → 1)" "1" "$(jalin rozet /tmp/sroz_json.html)"
ol "  DB'de todo TAMAM" "TAMAM" "$(db "SELECT durum FROM sicil_bildirim_gorevleri WHERE id=$T1;")"
curl -s -b $JP -c $JP -o /tmp/sroz_panel2.html "$B/panel"
ol "sayfa yenilenince personel rozeti 1" "1" "$(rozet /tmp/sroz_panel2.html | alan sayi)"

echo
echo "=== 7) 'TAKİP DIŞI' DA DÜŞÜRÜR → 0 OLUNCA ROZET GİZLENİR ==="
T=$(jeton $JP "$B/sicil")
curl -s -b $JP -c $JP -o /tmp/sroz_json2.html -X POST "$B/sicil/todo-durum" -d "csrf_beyanname=$T" -d "id=$T2" -d "durum=GEREKSIZ"
ol "AJAX rozet=0 döndü" "0" "$(jalin rozet /tmp/sroz_json2.html)"
curl -s -b $JP -c $JP -o /tmp/sroz_panel3.html "$B/panel"
ol "rozet 0" "0" "$(rozet /tmp/sroz_panel3.html | alan sayi)"
ol "rozet GİZLİ (display:none)" "true" "$(rozet /tmp/sroz_panel3.html | alan gizli)"
ol "admin rozeti hâlâ görünür (1: kapsam dışı)" "1" "$(curl -s -b $JA -c $JA "$B/panel" | python3 -c "
import re,sys
h=sys.stdin.read()
b=re.search(r'id=\"sicil-menu-rozet\"[^>]*>(\d+)</span>',h)
print(b.group(1) if b else 'YOK')")"

echo
echo "=== 8) GERİ AL → SAYI YENİDEN ARTAR ==="
T=$(jeton $JP "$B/sicil")
curl -s -b $JP -c $JP -o /tmp/sroz_json3.html -X POST "$B/sicil/todo-durum" -d "csrf_beyanname=$T" -d "id=$T2" -d "durum=BEKLIYOR"
ol "geri alınca rozet=1" "1" "$(jalin rozet /tmp/sroz_json3.html)"
curl -s -b $JP -c $JP -o /tmp/sroz_panel4.html "$B/panel"
ol "sayfada rozet 1 ve görünür" "1|false" "$(rozet /tmp/sroz_panel4.html | python3 -c "import json,sys;d=json.load(sys.stdin);print(str(d['sayi'])+'|'+str(d['gizli']).lower())")"

echo
echo "=== 9) YETKİ / GÜVENLİK ==="
T=$(jeton $JP "$B/sicil")
curl -s -b $JP -c $JP -o /tmp/sroz_yetki.html -w '%{http_code}' -X POST "$B/sicil/todo-durum" -d "csrf_beyanname=$T" -d "id=$T7" -d "durum=TAMAM" > /tmp/sroz_kod.txt
ol "personel kapsam dışı todo'yu değiştiremez (403)" "403" "$(cat /tmp/sroz_kod.txt)"
ol "  todo hâlâ BEKLIYOR" "BEKLIYOR" "$(db "SELECT durum FROM sicil_bildirim_gorevleri WHERE id=$T7;")"
KOD=$(curl -s -b $JP -c $JP -o /dev/null -w '%{http_code}' -X POST "$B/sicil/todo-durum" -d "id=$T1" -d "durum=BEKLIYOR")
ol "CSRF'siz POST reddedildi (403)" "403" "$KOD"

echo
echo "=== 10) JS CANLI GÜNCELLEME YARDIMCISI ==="
ol "window.sicilRozetGuncelle tanımlı" "1" "$(grep -c 'window.sicilRozetGuncelle' /tmp/sroz_panel.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "detay sayfası AJAX yanıtını rozete bağlar" "1" \
   "$(curl -s -b $JP -c $JP "$B/sicil/detay/$DEG1" | grep -c 'window.sicilRozetGuncelle(j.rozet)' | head -1 | awk '{print ($1>0)?1:0}')"

echo
echo "=== 11) TEMİZLİK ==="
db "DELETE FROM sicil_degisiklikleri WHERE konu LIKE 'SROZ-%';" > /dev/null 2>&1
db "DELETE FROM mukellefler WHERE unvan LIKE 'SROZ-TEST%';" > /dev/null 2>&1
ol "test kayıtları silindi" "0" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE ad LIKE 'SROZ-%';")"

echo
echo "======================================================"
echo " SONUÇ: $g geçti, $k hata"
echo "======================================================"
[ "$k" -eq 0 ]
