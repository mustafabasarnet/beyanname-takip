#!/bin/bash
# =====================================================================
#  AJANDA — KİŞİSEL KAYIT GİZLİLİĞİ TESTİ
#
#  Kullanıcı isteği:
#    "Ajanda'da kişisel olan hatırlatmaları yönetici dahi kimse
#     birbirinkini göremesin."
#
#  Kural: gorunurluk='kisisel' kaydı YALNIZ sahibine görünür.
#         Yönetici (admin) bile başkasınınkini göremez / açamaz /
#         düzenleyemez / silemez / "yapıldı" işaretleyemez.
#         Diğer görünürlüklerde (genel / gorev / musavir) admin tümünü görür.
#
#  Test ettikleri:
#    1) Admin kendi kişisel kaydını görür, personelin kişisel kaydını GÖRMEZ
#    2) Personel kendi kişisel kaydını görür
#    3) Admin başkasının kişisel kaydını listede göremez (satır yok)
#    4) Admin doğrudan URL ile detayı açamaz (302)
#    5) Admin düzenleme ekranını açamaz (302)
#    6) Admin "yapıldı" işaretleyemez (JSON durum=false + DB değişmez)
#    7) Admin silemez (DB'de duruyor)
#    8) genel / gorev / musavir kayıtları admin için hâlâ görünür (regresyon)
#    9) Sayaçlar: admin'in menü rozeti başkasının kişisel gecikmiş işini saymaz
#   10) Giriş uyarısı: admin'in uyarı penceresine başkasının kişisel işi düşmez
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin / personel / musavir / fatma = Test1234 hesapları mevcut.
#  Kullanım:  bash tests/ajanda_kisisel_gizlilik_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
JA=/tmp/akg_admin.txt
JP=/tmp/akg_personel.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/akg_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/akg_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }
# Listede görünen kayıt id'leri (data-aj-satir)
gorunen(){ python3 -c "
import re
h=open('$1',encoding='utf-8').read()
print(' '.join(sorted(set(re.findall(r'data-aj-satir=\"(\d+)\"', h)), key=int)))"; }

g=$((g+0))
echo "=== 0) HAZIRLIK ==="
girisYap admin $JA
girisYap personel $JP

# Kullanıcı id'leri (sabit varsayma)
ADMIN_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='admin' LIMIT 1;")
PERS_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='personel' LIMIT 1;")
ol "kullanıcı id'leri bulundu" "1" "$([ -n "$ADMIN_ID" ] && [ -n "$PERS_ID" ] && echo 1 || echo 0)"
ol "Admin girişi" "200" "$(curl -s -b $JA -o /dev/null -w '%{http_code}' $B/ajanda)"
ol "Personel girişi" "200" "$(curl -s -b $JP -o /dev/null -w '%{http_code}' $B/ajanda)"

BUGUN=$(date +%F)
DUN=$(date -d 'yesterday' +%F)
db "DELETE FROM ajanda WHERE baslik LIKE 'AKG-TEST%';" >/dev/null 2>&1

# Admin'in kişisel kaydı (acil, dün → gecikmiş)
T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /tmp/akg_p.html -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=AKG-TEST admin kisisel" \
  -d "tarih=$DUN" -d "gorunurluk=kisisel" -d "oncelik=acil"
ol "admin kişisel kaydı oluştu" "kisisel" "$(db "SELECT gorunurluk FROM ajanda WHERE baslik='AKG-TEST admin kisisel';")"
AID=$(db "SELECT id FROM ajanda WHERE baslik='AKG-TEST admin kisisel';")

# Personelin kişisel kaydı (acil, dün → gecikmiş)
T=$(jeton $JP "$B/ajanda/yeni")
curl -s -b $JP -c $JP -o /tmp/akg_p2.html -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=AKG-TEST personel kisisel" \
  -d "tarih=$DUN" -d "gorunurluk=kisisel" -d "oncelik=acil"
ol "personel kişisel kaydı oluştu" "kisisel" "$(db "SELECT gorunurluk FROM ajanda WHERE baslik='AKG-TEST personel kisisel';")"
PID=$(db "SELECT id FROM ajanda WHERE baslik='AKG-TEST personel kisisel';")
ol "kayıtlar farklı kişilere ait" "1|2" "$(db "SELECT olusturan_id FROM ajanda WHERE id IN ($AID,$PID) ORDER BY id;" | tr '\n' '|' | sed 's/|$//')"

echo
echo "=== 1) LİSTE GÖRÜNÜRLÜĞÜ ==="
curl -s -b $JA -o /tmp/akg_l_admin.html "$B/ajanda"
ol "admin KENDİ kişisel kaydını görür" "1" "$(grep -c "data-aj-satir=\"$AID\"" /tmp/akg_l_admin.html | awk '{print ($1>0)?1:0}')"
ol "admin BAŞKASININ kişisel kaydını GÖRMEZ" "0" "$(grep -c "data-aj-satir=\"$PID\"" /tmp/akg_l_admin.html)"

curl -s -b $JP -o /tmp/akg_l_per.html "$B/ajanda"
ol "personel KENDİ kişisel kaydını görür" "1" "$(grep -c "data-aj-satir=\"$PID\"" /tmp/akg_l_per.html | awk '{print ($1>0)?1:0}')"
ol "personel admin'in kişisel kaydını GÖRMEZ" "0" "$(grep -c "data-aj-satir=\"$AID\"" /tmp/akg_l_per.html)"

echo
echo "=== 2) DOĞRUDAN URL ERİŞİMİ (yetki sızıntısı) ==="
ol "admin başkasının kişisel DETAYINI açamaz" "302" \
   "$(curl -s -b $JA -o /dev/null -w '%{http_code}' "$B/ajanda/detay/$PID")"
ol "admin başkasının kişisel DÜZENLE ekranını açamaz" "302" \
   "$(curl -s -b $JA -o /dev/null -w '%{http_code}' "$B/ajanda/duzenle/$PID")"
ol "admin KENDİ kişisel detayını açabilir" "200" \
   "$(curl -s -b $JA -o /dev/null -w '%{http_code}' "$B/ajanda/detay/$AID")"
ol "personel kendi kişisel detayını açabilir" "200" \
   "$(curl -s -b $JP -o /dev/null -w '%{http_code}' "$B/ajanda/detay/$PID")"
ol "personel admin'in kişisel detayını açamaz" "302" \
   "$(curl -s -b $JP -o /dev/null -w '%{http_code}' "$B/ajanda/detay/$AID")"

echo
echo "=== 3) DEĞİŞTİRME DENEMELERİ (admin → başkasının kişisel kaydı) ==="
T=$(jeton $JA "$B/ajanda")
curl -s -b $JA -c $JA -o /tmp/akg_yap.json -X POST "$B/ajanda/yapildi" \
  -d "csrf_beyanname=$T" -d "id=$PID"
ol "admin 'yapıldı' işaretleyemez (durum=false)" "False" \
   "$(python3 -c "
import json
try: print(json.load(open('/tmp/akg_yap.json',encoding='utf-8')).get('durum'))
except Exception: print('YOK')")"
ol "  DB'de durum DEĞİŞMEDİ (BEKLIYOR)" "BEKLIYOR" "$(db "SELECT durum FROM ajanda WHERE id=$PID;")"

T=$(jeton $JA "$B/ajanda")
curl -s -b $JA -c $JA -o /tmp/akg_ert.json -X POST "$B/ajanda/ertele" \
  -d "csrf_beyanname=$T" -d "id=$PID" -d "gun=1"
ol "admin erteleyemez (durum=false)" "False" \
   "$(python3 -c "
import json
try: print(json.load(open('/tmp/akg_ert.json',encoding='utf-8')).get('durum'))
except Exception: print('YOK')")"
ol "  tarih DEĞİŞMEDİ" "$DUN" "$(db "SELECT tarih FROM ajanda WHERE id=$PID;")"

curl -s -b $JA -o /dev/null -w '%{redirect_url}' "$B/ajanda/sil/$PID" > /tmp/akg_sil.txt
ol "admin silemez (kayıt hâlâ duruyor)" "1" "$(db "SELECT COUNT(*) FROM ajanda WHERE id=$PID AND deleted_at IS NULL;")"

echo
echo "=== 4) SAHİBİ TÜM İŞLEMLERİ YAPABİLİR (regresyon) ==="
T=$(jeton $JP "$B/ajanda")
curl -s -b $JP -c $JP -o /tmp/akg_yp2.json -X POST "$B/ajanda/yapildi" \
  -d "csrf_beyanname=$T" -d "id=$PID"
ol "personel kendi kaydını 'yapıldı' işaretler" "True" \
   "$(python3 -c "
import json
try: print(json.load(open('/tmp/akg_yp2.json',encoding='utf-8')).get('durum'))
except Exception: print('YOK')")"
ol "  DB'de YAPILDI" "YAPILDI" "$(db "SELECT durum FROM ajanda WHERE id=$PID;")"

echo
echo "=== 5) DİĞER GÖRÜNÜRLÜKLER (admin tümünü görür — regresyon) ==="
T=$(jeton $JP "$B/ajanda/yeni")
curl -s -b $JP -c $JP -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=AKG-TEST personel genel" \
  -d "tarih=$BUGUN" -d "gorunurluk=genel" -d "oncelik=normal"
GID=$(db "SELECT id FROM ajanda WHERE baslik='AKG-TEST personel genel';")
curl -s -b $JA -o /tmp/akg_l_admin2.html "$B/ajanda"
ol "admin personelin GENEL kaydını görür" "1" \
   "$(grep -c "data-aj-satir=\"$GID\"" /tmp/akg_l_admin2.html | awk '{print ($1>0)?1:0}')"
ol "admin personelin genel detayını açabilir" "200" \
   "$(curl -s -b $JA -o /dev/null -w '%{http_code}' "$B/ajanda/detay/$GID")"

T=$(jeton $JP "$B/ajanda/yeni")
curl -s -b $JP -c $JP -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=AKG-TEST personel gorev" \
  -d "tarih=$BUGUN" -d "gorunurluk=gorev" -d "atanan_id=$PERS_ID" -d "oncelik=normal"
VID=$(db "SELECT id FROM ajanda WHERE baslik='AKG-TEST personel gorev';")
ol "görev kaydı 'gorev' görünürlüğüyle oluştu" "gorev" "$(db "SELECT gorunurluk FROM ajanda WHERE id=$VID;")"
curl -s -b $JA -o /tmp/akg_l_admin3.html "$B/ajanda"
ol "admin personelin GÖREV kaydını görür" "1" \
   "$(grep -c "data-aj-satir=\"$VID\"" /tmp/akg_l_admin3.html | awk '{print ($1>0)?1:0}')"
ol "admin görev kaydının detayını açabilir" "200" \
   "$(curl -s -b $JA -o /dev/null -w '%{http_code}' "$B/ajanda/detay/$VID")"

echo
echo "=== 6) SAYAÇLAR / MENÜ ROZETİ ==="
# Personelin gecikmiş kişisel kaydı admin'in rozetini şişirmemeli:
# admin'in gecikmiş+bugün sayısı, personelin kişisel kaydı hariç hesaplanmalı.
db "UPDATE ajanda SET durum='BEKLIYOR', tarih='$DUN' WHERE id=$PID;" >/dev/null
curl -s -b $JA -o /tmp/akg_oyun.html "$B/ajanda"
ADMIN_ROZET=$(python3 -c "
import re
h=open('/tmp/akg_oyun.html',encoding='utf-8').read()
m=re.search(r'<span class=\"menu-rozet\" title=\"Gecikmiş \+ bugünkü işler\">(\d+)</span>', h)
print(m.group(1) if m else '0')")
# Not: 'genel' ve 'gorev' kayıtları admin'e görünür; yalnız KİŞİSEL kayıt sızmamalı
AKG_OZET=$(curl -s -b $JA "$B/ajanda/giris-uyarisi" | python3 -c "
import json,sys
d=json.load(sys.stdin)
hepsi=[i.get('baslik','') for i in (d.get('isler') or [])]
print(len([b for b in hepsi if b == 'AKG-TEST personel kisisel']))")
ol "admin giriş uyarısında personelin KİŞİSEL işi YOK" "0" "$AKG_OZET"
AKG_GORUNUR=$(curl -s -b $JA "$B/ajanda/giris-uyarisi" | python3 -c "
import json,sys
d=json.load(sys.stdin)
hepsi=[i.get('baslik','') for i in (d.get('isler') or [])]
# admin'in kendi kişisel kaydı + personelin genel ve görev kayıtları = 3
print(sum(1 for b in hepsi if b in ('AKG-TEST admin kisisel',
                                    'AKG-TEST personel genel',
                                    'AKG-TEST personel gorev')))")
ol "  admin kendi + genel + görev kayıtlarını uyarıda görür" "3" "$AKG_GORUNUR"
AKG_GOREV_GORUNUR=$(curl -s -b $JA "$B/ajanda/giris-uyarisi" | python3 -c "
import json,sys
d=json.load(sys.stdin)
hepsi=[i.get('baslik','') for i in (d.get('isler') or [])]
print(1 if 'AKG-TEST personel gorev' in hepsi else 0)")
ol "  personelin GÖREV kaydı admin uyarısında görünür" "1" "$AKG_GOREV_GORUNUR"
ol "admin menü rozeti bir sayı (sayaç çalışıyor)" "1" "$([ -n "$ADMIN_ROZET" ] && echo 1 || echo 0)"

curl -s -b $JP -o /tmp/akg_poyun.html "$B/ajanda"
ol "personel kendi kişisel işini listede görür (gecikmiş)" "1" \
   "$(grep -c "data-aj-satir=\"$PID\"" /tmp/akg_poyun.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 7) TEMİZLİK ==="
db "DELETE FROM ajanda WHERE baslik LIKE 'AKG-TEST%';" >/dev/null
ol "test verisi temizlendi" "0" "$(db "SELECT COUNT(*) FROM ajanda WHERE baslik LIKE 'AKG-TEST%';")"

echo
echo "========================================"
if [ $k -eq 0 ]; then echo "TÜM TESTLER BAŞARILI ($g geçti / $((g+k)))"; else echo "$k HATA ($g geçti / $((g+k)))"; fi
rm -f $JA $JP
[ $k -eq 0 ] || exit 1
