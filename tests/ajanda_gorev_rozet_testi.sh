#!/bin/bash
# =====================================================================
#  AJANDA — "BANA ATANAN GÖREVLER" ROZETİ (sol alt sayaç)
#
#  Kullanıcı isteği:
#    Ajanda'da personele görev atanıyor. Personel, sol altta isminin
#    yazdığı yerde görev sayısını görsün; görevi yapınca sayı düşsün;
#    bunu AJANDA'YA GİRMEDEN (her sayfada) görebilsin.
#
#  Test ettikleri:
#    1) Rozet sol altta (kullanıcı satırında) ve HER SAYFADA var
#    2) Sayaç = bana atanan AÇIK görev sayısı
#    3) Yapıldı/İptal edilenler ve başkasına atananlar SAYILMAZ
#    4) Kişisel (görünürlük=kisisel) kayıtlar sayılmaz
#    5) AJAX "Yapıldı" → gorev_sayi döner ve DÜŞER
#    6) Geri al (reopen) → sayı ARTAR
#    7) Tümü bitince rozet GİZLENİR
#    8) Kullanıcı izolasyonu: herkes yalnız kendi rozetini görür
#    9) İpucu görev başlıklarını + tarihleri listeler
#   10) Rozete tıklayınca yalnız bana atananlar açılır
#   11) Canlı güncelleme yardımcısı (JS) layout'ta tanımlı
#   12) N+1 yok: rozet, görev sayısından bağımsız sabit sorgu ekler
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor.
#  Not: Kendi verisini kurar (AJG- önekli kayıtlar).
#  Kullanım:  bash tests/ajanda_gorev_rozet_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
JP=/tmp/ajgr_personel.txt
JA=/tmp/ajgr_admin.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/ajgr_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ajgr_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Rozeti okur: "sayi|stil|baglanti|ipucu"
rozet(){ python3 -c "
import re, html, json
h = open('$1', encoding='utf-8').read()
blok = re.search(r'<a href=\"([^\"]*)\"\s*\n?\s*class=\"gorev-rozet\"[\s\S]{0,600}?</a>', h)
if not blok:
    print(json.dumps({'sayi': None, 'gizli': None, 'link': None, 'ipucu': None})); raise SystemExit
sayi = re.search(r'class=\"adet\"[^>]*>(\d+)<', blok.group(0))
stil = re.search(r'style=\"([^\"]*)\"', blok.group(0))
ipucu = re.search(r'title=\"([^\"]*)\"', blok.group(0))
print(json.dumps({
    'sayi': int(sayi.group(1)) if sayi else None,
    'gizli': bool(stil and 'display:none' in stil.group(1)),
    'link': blok.group(1),
    'ipucu': html.unescape(ipucu.group(1)) if ipucu else None,
}, ensure_ascii=False))"; }
alan(){ python3 -c "
import json,sys
d=json.loads(sys.stdin.read() or '{}')
v=d.get('$1')
print('YOK' if v is None else (str(v).lower() if isinstance(v,bool) else v))"; }
rozetFormVar(){ python3 -c "
import re
h=open('$1',encoding='utf-8').read()
b=re.search(r'class=.gorev-rozet.[\\s\\S]{0,600}?</a>', h)
print(1 if (b and '<form' in b.group(0)) else 0)"; }

sayacAlan(){ python3 -c "
import json,sys
try: d=json.load(sys.stdin)
except Exception: print('YOK'); raise SystemExit
v = d.get('$1', 'YOK')
# bool'ları küçük harfe çevir (alan() ile aynı biçim)
print(str(v).lower() if isinstance(v, bool) else v)"; }

echo "=== 0) HAZIRLIK ==="
girisYap personel $JP
girisYap admin $JA
PERS_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='personel' LIMIT 1;")
ADMIN_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='admin' LIMIT 1;")
ol "kullanıcı id'leri bulundu" "1" "$([ -n "$PERS_ID" ] && [ -n "$ADMIN_ID" ] && echo 1 || echo 0)"

db "DELETE FROM ajanda WHERE baslik LIKE 'AJGR-%';" >/dev/null 2>&1
# Personel'e 3 açık + 1 yapıldı + 1 iptal + 1 başkasına atanmış + 1 kişisel
$MDBR -e "
INSERT INTO ajanda (baslik,tarih,saat,gorunurluk,atanan_id,oncelik,durum,olusturan_id,created_at,updated_at) VALUES
('AJGR-1 evrak teslimi',     CURDATE(),                        '10:00:00','gorev',  $PERS_ID,'yuksek','BEKLIYOR',$ADMIN_ID,NOW(),NOW()),
('AJGR-2 beyanname kontrol', DATE_ADD(CURDATE(), INTERVAL 2 DAY), NULL,    'gorev',  $PERS_ID,'normal','BEKLIYOR',$ADMIN_ID,NOW(),NOW()),
('AJGR-3 mükellef ziyareti', DATE_ADD(CURDATE(), INTERVAL 5 DAY),'14:30:00','gorev',  $PERS_ID,'acil',  'BEKLIYOR',$ADMIN_ID,NOW(),NOW()),
('AJGR-4 yapılmış',          CURDATE(),                        NULL,      'gorev',  $PERS_ID,'normal','YAPILDI', $ADMIN_ID,NOW(),NOW()),
('AJGR-5 iptal',             CURDATE(),                        NULL,      'gorev',  $PERS_ID,'normal','IPTAL',   $ADMIN_ID,NOW(),NOW()),
('AJGR-6 admin görevi',      CURDATE(),                        NULL,      'gorev',  $ADMIN_ID,'normal','BEKLIYOR',$PERS_ID,NOW(),NOW()),
('AJGR-7 personel kişisel',  CURDATE(),                        NULL,      'kisisel',$PERS_ID,'normal','BEKLIYOR',$PERS_ID,NOW(),NOW());" >/dev/null 2>&1
ol "test verisi kuruldu (7 kayıt)" "7" "$(db "SELECT COUNT(*) FROM ajanda WHERE baslik LIKE 'AJGR-%';")"

echo
echo "=== 1) ROZET HER SAYFADA VAR (Ajanda'ya girmeden) ==="
# Personelin erişebildiği sayfalar (makbuz mali bilgi → personel giremez).
# Amaç: AJANDA'YA GİRMEDEN de rozetin görünmesi.
for yol in "panel" "takip" "evrak" "kisisel" "ajanda"; do
  curl -s -b $JP -c $JP -o /tmp/ajgr_$yol.html "$B/$yol"
  ol "/$yol sayfasında rozet var" "True" "$([ -n "$(rozet /tmp/ajgr_$yol.html | alan sayi)" ] && [ "$(rozet /tmp/ajgr_$yol.html | alan sayi)" != "None" ] && [ "$(rozet /tmp/ajgr_$yol.html | alan sayi)" != "YOK" ] && echo True || echo False)"
done

echo
echo "=== 2) SAYAÇ = BANA ATANAN AÇIK GÖREVLER ==="
curl -s -b $JP -c $JP -o /tmp/ajgr_panel.html "$B/panel"
ol "rozet sayısı 3 (AJGR-1,2,3)" "3" "$(rozet /tmp/ajgr_panel.html | alan sayi)"
ol "yapılmış/iptal SAYILMADI (DB ile uyumlu)" "3" \
   "$(db "SELECT COUNT(*) FROM ajanda WHERE gorunurluk='gorev' AND atanan_id=$PERS_ID AND durum='BEKLIYOR' AND deleted_at IS NULL AND baslik LIKE 'AJGR-%';")"
ol "rozet görünür (gizli değil)" "false" "$(rozet /tmp/ajgr_panel.html | alan gizli)"
ol "bağlantı: yalnız bana atananlar" "$B/ajanda?atanan_id=$PERS_ID" "$(rozet /tmp/ajgr_panel.html | alan link)"

echo
echo "=== 3) İPUCU GÖREV ADLARINI LİSTELER ==="
IPUCU=$(rozet /tmp/ajgr_panel.html | alan ipucu)
ol "ipuçta '3 açık görev' ifadesi" "1" "$(echo "$IPUCU" | grep -c 'Bana atanan 3 açık görev')"
ol "görev adı 1" "1" "$(echo "$IPUCU" | grep -c 'AJGR-1 evrak teslimi')"
ol "görev adı 3" "1" "$(echo "$IPUCU" | grep -c 'AJGR-3 mükellef ziyareti')"
ol "tarih biçimi (gg.aa.yyyy) — en az 3 görev" "1" \
   "$([ "$(echo "$IPUCU" | grep -cE '[0-9]{2}\.[0-9]{2}\.[0-9]{4}')" -ge 3 ] && echo 1 || echo 0)"
ol "saat gösterildi (10:00)" "1" "$(echo "$IPUCU" | grep -c '10:00')"
ol "yapılmış görev ipuçta YOK" "0" "$(echo "$IPUCU" | grep -c 'AJGR-4')"
ol "tıklama açıklaması" "1" "$(echo "$IPUCU" | grep -c 'yalnız bana atanan')"

echo
echo "=== 4) KULLANICI İZOLASYONU ==="
curl -s -b $JA -c $JA -o /tmp/ajgr_panel_a.html "$B/panel"
ol "admin rozeti 1 (yalnız kendine atanan)" "1" "$(rozet /tmp/ajgr_panel_a.html | alan sayi)"
ol "  admin ipuçta personelin görevi YOK" "0" "$(rozet /tmp/ajgr_panel_a.html | alan ipucu | grep -c 'AJGR-1')"
ol "  admin bağlantısı kendi id'si" "$B/ajanda?atanan_id=$ADMIN_ID" "$(rozet /tmp/ajgr_panel_a.html | alan link)"
ol "kişisel kayıt sayılmadı (AJGR-7)" "0" "$(rozet /tmp/ajgr_panel.html | alan ipucu | grep -c 'AJGR-7')"

echo
echo "=== 5) YAPILDI → SAYI DÜŞER (AJAX) ==="
G1=$(db "SELECT id FROM ajanda WHERE baslik='AJGR-1 evrak teslimi'")
T=$(jeton $JP "$B/ajanda")
curl -s -b $JP -c $JP -o /tmp/ajgr_y1.json -X POST "$B/ajanda/yapildi" -d "csrf_beyanname=$T" -d "id=$G1"
ol "AJAX durum=true" "true" "$(sayacAlan durum < /tmp/ajgr_y1.json)"
ol "AJAX gorev_sayi 2 döndü" "2" "$(sayacAlan gorev_sayi < /tmp/ajgr_y1.json)"
curl -s -b $JP -c $JP -o /tmp/ajgr_panel2.html "$B/panel"
ol "sayfa yenilenince rozet 2" "2" "$(rozet /tmp/ajgr_panel2.html | alan sayi)"
ol "  DB'de görev YAPILDI" "YAPILDI" "$(db "SELECT durum FROM ajanda WHERE id=$G1;")"

echo
echo "=== 6) GERİ AL → SAYI ARTAR ==="
T=$(jeton $JP "$B/ajanda")
curl -s -b $JP -c $JP -o /tmp/ajgr_g1.json -X POST "$B/ajanda/geri-al" -d "csrf_beyanname=$T" -d "id=$G1"
ol "geri-al gorev_sayi 3 döndü" "3" "$(sayacAlan gorev_sayi < /tmp/ajgr_g1.json)"
curl -s -b $JP -c $JP -o /tmp/ajgr_panel3.html "$B/panel"
ol "rozet tekrar 3" "3" "$(rozet /tmp/ajgr_panel3.html | alan sayi)"

echo
echo "=== 7) İPTAL → SAYI DÜŞER ==="
G2=$(db "SELECT id FROM ajanda WHERE baslik='AJGR-2 beyanname kontrol'")
T=$(jeton $JP "$B/ajanda")
curl -s -b $JP -c $JP -o /tmp/ajgr_i1.json -X POST "$B/ajanda/iptal" -d "csrf_beyanname=$T" -d "id=$G2"
ol "iptal gorev_sayi 2 döndü" "2" "$(sayacAlan gorev_sayi < /tmp/ajgr_i1.json)"
ol "  DB'de durum IPTAL" "IPTAL" "$(db "SELECT durum FROM ajanda WHERE id=$G2;")"

echo
echo "=== 8) TÜMÜ BİTİNCE ROZET GİZLENİR ==="
# 6. adımda AJGR-1 yeniden açılmıştı; kalan TÜM açık görevler kapatılır
A3=$(db "SELECT COUNT(*) FROM ajanda WHERE gorunurluk='gorev' AND atanan_id=$PERS_ID AND durum='BEKLIYOR' AND deleted_at IS NULL;")
SON=0
while [ "$A3" -gt 0 ]; do
  GID=$(db "SELECT id FROM ajanda WHERE gorunurluk='gorev' AND atanan_id=$PERS_ID AND durum='BEKLIYOR' AND deleted_at IS NULL ORDER BY id LIMIT 1;")
  T=$(jeton $JP "$B/ajanda")
  curl -s -b $JP -c $JP -o /tmp/ajgr_y3.json -X POST "$B/ajanda/yapildi" -d "csrf_beyanname=$T" -d "id=$GID"
  SON=$(sayacAlan gorev_sayi < /tmp/ajgr_y3.json)
  A3=$(db "SELECT COUNT(*) FROM ajanda WHERE gorunurluk='gorev' AND atanan_id=$PERS_ID AND durum='BEKLIYOR' AND deleted_at IS NULL;")
done
ol "son görevden sonra gorev_sayi 0" "0" "$SON"
curl -s -b $JP -c $JP -o /tmp/ajgr_panel4.html "$B/panel"
ol "rozet GİZLENDİ (display:none)" "true" "$(rozet /tmp/ajgr_panel4.html | alan gizli)"
ol "  sayı 0" "0" "$(rozet /tmp/ajgr_panel4.html | alan sayi)"

echo
echo "=== 9) ROZET BAĞLANTISI: YALNIZ BANA ATANANLAR ==="
# Yeni görev ekleyip listeyi kontrol et
$MDBR -e "UPDATE ajanda SET durum='BEKLIYOR' WHERE baslik IN ('AJGR-1 evrak teslimi','AJGR-2 beyanname kontrol','AJGR-3 mükellef ziyareti');" >/dev/null 2>&1
curl -s -b $JP -c $JP -o /tmp/ajgr_liste.html "$B/ajanda?atanan_id=$PERS_ID"
ol "kendi görevi listede" "1" "$(grep -c 'AJGR-1 evrak teslimi' /tmp/ajgr_liste.html | awk '{print ($1>0)?1:0}')"
ol "admin'in görevi listede DEĞİL" "0" "$(grep -c 'AJGR-6 admin görevi' /tmp/ajgr_liste.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 10) CANLI GÜNCELLEME YARDIMCISI (JS) ==="
ol "layout'ta ajandaGorevRozetGuncelle tanımlı" "1" "$(grep -c 'window.ajandaGorevRozetGuncelle' /tmp/ajgr_panel.html | awk '{print ($1>0)?1:0}')"
ol "ajanda listesinde çağrılıyor" "1" "$(grep -c 'ajandaGorevRozetGuncelle(v.gorev_sayi)' /tmp/ajgr_liste.html | awk '{print ($1>0)?1:0}')"
curl -s -b $JP -c $JP -o /tmp/ajgr_detay.html "$B/ajanda/detay/$(db "SELECT id FROM ajanda WHERE baslik='AJGR-1 evrak teslimi'")"
ol "ajanda detayında da çağrılıyor" "1" "$(grep -c 'ajandaGorevRozetGuncelle(v.gorev_sayi)' /tmp/ajgr_detay.html | awk '{print ($1>0)?1:0}')"
ol "rozet kutu öğeleri (zil + adet)" "1" \
   "$(grep -c 'id="ajanda-gorev-adet"' /tmp/ajgr_panel.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 11) N+1 YOK — rozet sabit sorgu ekler ==="
SORGU(){ $MDB -e "SHOW GLOBAL STATUS LIKE 'Questions'" | awk '{print $2}'; }
# 3 görevle
$MDBR -e "DELETE FROM ajanda WHERE baslik LIKE 'AJGR-COK%';" >/dev/null 2>&1
q1=$(SORGU); curl -s -b $JP -c $JP -o /dev/null "$B/panel"; q2=$(SORGU)
t1=$((q2-q1))
# 30 görevle
for i in $(seq 1 30); do
  $MDBR -e "INSERT INTO ajanda (baslik,tarih,gorunurluk,atanan_id,oncelik,durum,olusturan_id,created_at,updated_at)
            VALUES ('AJGR-COK-$i', CURDATE(), 'gorev', $PERS_ID, 'normal','BEKLIYOR',$ADMIN_ID,NOW(),NOW());" >/dev/null 2>&1
done
q3=$(SORGU); curl -s -b $JP -c $JP -o /dev/null "$B/panel"; q4=$(SORGU)
t2=$((q4-q3))
echo "  (3 görev: $t1 sorgu · 33 görev: $t2 sorgu)"
ol "görev 11 kat artınca sorgu sabit kaldı (< 6 fark)" "1" "$([ $((t2-t1)) -lt 6 ] && echo 1 || echo 0)"
curl -s -b $JP -c $JP -o /tmp/ajgr_panel5.html "$B/panel"
ol "33 görevde rozet 33 gösteriyor" "33" "$(rozet /tmp/ajgr_panel5.html | alan sayi)"
IPUCU5=$(rozet /tmp/ajgr_panel5.html | alan ipucu)
ol "  ipuçta '+30 görev daha' (ilk 3 gösterilir)" "1" "$(echo "$IPUCU5" | grep -c '+30 görev daha')"

echo
echo "=== 12) YETKİSİZ ERİŞİM / GÜVENLİK ==="
# Personelin ne oluşturduğu ne de atandığı bir görev: her iki taraf da admin
$MDBR -e "INSERT INTO ajanda (baslik,tarih,gorunurluk,atanan_id,oncelik,durum,olusturan_id,created_at,updated_at)
          VALUES ('AJGR-8 admin-admin görevi', CURDATE(),'gorev',$ADMIN_ID,'normal','BEKLIYOR',$ADMIN_ID,NOW(),NOW());" >/dev/null 2>&1
GY=$(db "SELECT id FROM ajanda WHERE baslik='AJGR-8 admin-admin görevi'")
T=$(jeton $JP "$B/ajanda")
curl -s -b $JP -c $JP -o /tmp/ajgr_yetki.json -X POST "$B/ajanda/yapildi" -d "csrf_beyanname=$T" -d "id=$GY"
ol "yetkisiz personel 'yapıldı' işaretleyemez (durum=false)" "false" "$(sayacAlan durum < /tmp/ajgr_yetki.json)"
ol "  görev hâlâ BEKLIYOR" "BEKLIYOR" "$(db "SELECT durum FROM ajanda WHERE id=$GY;")"
ol "rozet bir form/POST değil (salt bağlantı)" "0" "$(rozetFormVar /tmp/ajgr_panel.html)"
ol "rozet başkasının sayısını sızdırmıyor (izolasyon)" "1" \
   "$([ "$(rozet /tmp/ajgr_panel.html | alan sayi)" != "$(rozet /tmp/ajgr_panel_a.html | alan sayi)" ] || [ "$(rozet /tmp/ajgr_panel_a.html | alan sayi)" = "0" ] && echo 1 || echo 0)"

echo
echo "=== 13) TEMİZLİK ==="
db "DELETE FROM ajanda WHERE baslik LIKE 'AJGR-%';" >/dev/null
ol "test verisi temizlendi" "0" "$(db "SELECT COUNT(*) FROM ajanda WHERE baslik LIKE 'AJGR-%';")"

echo
echo "========================================"
if [ $k -eq 0 ]; then echo "TÜM TESTLER BAŞARILI ($g geçti / $((g+k)))"; else echo "$k HATA ($g geçti / $((g+k)))"; fi
rm -f $JP $JA
[ $k -eq 0 ] || exit 1
