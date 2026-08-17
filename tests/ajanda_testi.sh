#!/bin/bash
# =====================================================================
#  AJANDA / HATIRLATICI — REGRESYON TESTİ
#
#  Kapsam:
#   1. Şema + idempotent migration
#   2. CRUD (oluştur / düzenle / sil)
#   3. GÖRÜNÜRLÜK İZOLASYONU (kisisel / genel / gorev / musavir)
#   4. Düzenleme yetkisi (oluşturan + atanan + admin)
#   5. Durum: yapıldı / geri al / iptal / ertele
#   6. Tekrar mantığı (günlük/haftalık/aylık/yıllık + ay sonu taşması)
#   7. Sayaçlar ve menü rozeti
#   8. Takvim görünümü
#   9. Giriş uyarısı (günde bir kez)
#  10. Dosya ekleri
#  11. Filtreleme ve arama
#  12. Panel kartı
#  13. Yazdırma
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/personel/musavir/fatma (Test1234) kullanıcıları var.
#  Kullanım:  bash tests/ajanda_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
KOK="$(cd "$(dirname "$0")/.." && pwd)"
JA=/tmp/aj_admin.txt
JP=/tmp/aj_personel.txt
JM=/tmp/aj_musavir.txt
JF=/tmp/aj_fatma.txt
g=0; k=0

ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1));
      else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/aj_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/aj_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# CSRF: sayfada form olmayabilir → meta etiketinden alınır
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }

# Listede görünen kayıt id'leri
gorunen(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
print(' '.join(re.findall(r'data-aj-satir=\"(\d+)\"', h)))
" "$1"; }

# JSON alanı
jal(){ python3 -c "
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
for x in sys.argv[2].split('.'): d=d[x]
print(d)
" "$1" "$2" 2>/dev/null || echo "JSON-HATA"; }

# Sayaç kutusu okur
sayac(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
m=re.search(r'<div class=\"et\">%s</div>\s*<div class=\"dg[^\"]*\">(\d+)</div>' % sys.argv[2], h, re.S)
print(m.group(1) if m else 'YOK')
" "$1" "$2"; }

BUGUN=$(date +%F)
DUN=$(date -d '-3 days' +%F)
YARIN=$(date -d '+2 days' +%F)

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE ajanda_ek; TRUNCATE ajanda_uyari_okundu; TRUNCATE ajanda;
SET FOREIGN_KEY_CHECKS=1;
ALTER TABLE ajanda AUTO_INCREMENT=1;
UPDATE kullanicilar SET musavir_id=1 WHERE kullanici_adi IN ('personel','musavir','fatma');
DELETE FROM kullanici_musavirleri;
INSERT INTO kullanici_musavirleri (kullanici_id,musavir_id) VALUES (3,1);
" >/dev/null 2>&1
}

echo "=== HAZIRLIK ==="
veriKur
giris admin    $JA
giris personel $JP
giris musavir  $JM
giris fatma    $JF
c=$(curl -s -b $JA -o /dev/null -w "%{http_code}" $B/ajanda)
ol "admin ajandaya erişiyor" "200" "$c"

# =====================================================================
echo; echo "=== 1) ŞEMA VE MIGRATION ==="
# =====================================================================
for t in ajanda ajanda_ek ajanda_uyari_okundu; do
  ol "$t tablosu var" "1" \
     "$($MDB -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$t';")"
done
for a in ajanda_panel_gun ajanda_giris_uyari ajanda_ek_boyut; do
  ol "ayar $a tanımlı" "1" "$($MDB -e "SELECT COUNT(*) FROM ayarlar WHERE anahtar='$a';")"
done
ol "yumuşak silme sütunu var" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='ajanda' AND column_name='deleted_at';")"

$MDBR < "$KOK/database/migration_ajanda.sql" >/dev/null 2>/tmp/aj_mig.err
ol "migration 2. kez çalışır (idempotent)" "0" "$(wc -c </tmp/aj_mig.err | tr -d ' ')"

# =====================================================================
echo; echo "=== 2) KAYIT OLUŞTURMA ==="
# =====================================================================
T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=Vergi dairesine uğra" \
  -d "tarih=$DUN" -d "gorunurluk=kisisel" -d "oncelik=acil" --data-urlencode "etiket=Ziyaret"
ol "1) kişisel kayıt oluştu" "1" "$($MDB -e "SELECT COUNT(*) FROM ajanda WHERE id=1;")"
ol "   Türkçe başlık bozulmadı" "Vergi dairesine uğra" "$($MDB -e "SELECT baslik FROM ajanda WHERE id=1;")"
ol "   oluşturan admin (1)" "1" "$($MDB -e "SELECT olusturan_id FROM ajanda WHERE id=1;")"

T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=Büro toplantısı" \
  -d "tarih=$BUGUN" -d "saat=14:30" -d "gorunurluk=genel" -d "oncelik=yuksek"
ol "2) genel kayıt oluştu" "genel" "$($MDB -e "SELECT gorunurluk FROM ajanda WHERE id=2;")"
ol "   saat kaydedildi" "14:30:00" "$($MDB -e "SELECT saat FROM ajanda WHERE id=2;")"

T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=SGK bildirimi" \
  -d "tarih=$YARIN" -d "gorunurluk=gorev" -d "atanan_id=4" -d "tekrar=aylik" -d "hatirlat_gun=3"
ol "3) görev kaydı, fatma'ya atandı" "4" "$($MDB -e "SELECT atanan_id FROM ajanda WHERE id=3;")"
ol "   tekrar aylık" "aylik" "$($MDB -e "SELECT tekrar FROM ajanda WHERE id=3;")"

T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=ALFA sözleşme yenileme" \
  -d "tarih=$YARIN" -d "gorunurluk=musavir" -d "musavir_id=1" -d "mukellef_id=1"
ol "4) müşavir ekibi kaydı" "1" "$($MDB -e "SELECT musavir_id FROM ajanda WHERE id=4;")"
ol "   mükellefe bağlandı" "1" "$($MDB -e "SELECT mukellef_id FROM ajanda WHERE id=4;")"

# --- Tutarsız kombinasyonlar temizlenmeli ----------------------------
T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=Kişisel ama atanan dolu" \
  -d "tarih=$BUGUN" -d "gorunurluk=kisisel" -d "atanan_id=4"
ol "kişiselde atanan temizlendi" "" "$($MDB -e "SELECT IFNULL(atanan_id,'') FROM ajanda WHERE id=5;")"

T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=Görev ama kişi yok" \
  -d "tarih=$BUGUN" -d "gorunurluk=gorev"
ol "kişisiz görev → kişisele düştü" "kisisel" "$($MDB -e "SELECT gorunurluk FROM ajanda WHERE id=6;")"

T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=Geçersiz renk" \
  -d "tarih=$BUGUN" -d "renk=javascript:alert(1)"
ol "geçersiz renk reddedildi" "" "$($MDB -e "SELECT IFNULL(renk,'') FROM ajanda WHERE id=7;")"

T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" --data-urlencode "baslik=Bitiş başlangıçtan önce" \
  -d "tarih=$BUGUN" -d "bitis_tarihi=$DUN"
ol "geçersiz bitiş temizlendi" "" "$($MDB -e "SELECT IFNULL(bitis_tarihi,'') FROM ajanda WHERE id=8;")"

# Başlıksız kayıt reddedilmeli
ONCE=$($MDB -e "SELECT COUNT(*) FROM ajanda;")
T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" -d "baslik=" -d "tarih=$BUGUN"
ol "başlıksız kayıt reddedildi" "$ONCE" "$($MDB -e "SELECT COUNT(*) FROM ajanda;")"

# =====================================================================
echo; echo "=== 3) GÖRÜNÜRLÜK İZOLASYONU (en kritik) ==="
# =====================================================================
# Test verisini sadeleştir: 1=admin kişisel, 2=genel, 3=görev(fatma), 4=müşavir1
$MDBR -e "DELETE FROM ajanda WHERE id > 4;" >/dev/null

curl -s -b $JA -o /tmp/aj_l_admin.html "$B/ajanda"
ol "admin hepsini görür" "1 2 3 4" "$(gorunen /tmp/aj_l_admin.html)"

curl -s -b $JP -o /tmp/aj_l_per.html "$B/ajanda"
ol "personel: genel + müşavir ekibi" "2 4" "$(gorunen /tmp/aj_l_per.html)"

curl -s -b $JM -o /tmp/aj_l_mus.html "$B/ajanda"
ol "müşavir: genel + müşavir ekibi" "2 4" "$(gorunen /tmp/aj_l_mus.html)"

curl -s -b $JF -o /tmp/aj_l_fat.html "$B/ajanda"
ol "fatma: genel + kendi görevi + ekip" "2 3 4" "$(gorunen /tmp/aj_l_fat.html)"

# Doğrudan URL ile de sızmamalı
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" "$B/ajanda/detay/1")
ol "personel kişisel kayda giremez" "302" "$c"
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" "$B/ajanda/detay/3")
ol "personel başkasının görevine giremez" "302" "$c"
c=$(curl -s -b $JF -o /dev/null -w "%{http_code}" "$B/ajanda/detay/3")
ol "fatma kendi görevini görür" "200" "$c"
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" "$B/ajanda/detay/2")
ol "personel genel kaydı görür" "200" "$c"

# Müşavir ekibi: erişimi olmayan müşavirin kaydı görünmemeli
$MDBR -e "INSERT INTO ajanda (baslik,tarih,gorunurluk,musavir_id,olusturan_id,durum,created_at)
          VALUES ('Veli ekibi işi','$BUGUN','musavir',2,1,'BEKLIYOR',NOW());" >/dev/null
YID=$($MDB -e "SELECT MAX(id) FROM ajanda;")
curl -s -b $JP -o /tmp/aj_l_per2.html "$B/ajanda"
ol "başka müşavirin ekip kaydı gizli" "0" \
   "$(python3 -c "
import re
h=open('/tmp/aj_l_per2.html',encoding='utf-8').read()
print(1 if 'data-aj-satir=\"$YID\"' in h else 0)")"
$MDBR -e "DELETE FROM ajanda WHERE id=$YID;" >/dev/null

# =====================================================================
echo; echo "=== 4) DÜZENLEME YETKİSİ ==="
# =====================================================================
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" "$B/ajanda/duzenle/2")
ol "personel başkasının kaydını düzenleyemez" "302" "$c"
c=$(curl -s -b $JF -o /dev/null -w "%{http_code}" "$B/ajanda/duzenle/3")
ol "atanan kişi görevini düzenleyebilir" "200" "$c"
c=$(curl -s -b $JA -o /dev/null -w "%{http_code}" "$B/ajanda/duzenle/3")
ol "admin her kaydı düzenleyebilir" "200" "$c"

# POST ile zorlama da engellenmeli
T=$(jeton $JP "$B/ajanda")
curl -s -b $JP -c $JP -o /dev/null -X POST "$B/ajanda/kaydet" \
  -d "csrf_beyanname=$T" -d "id=2" --data-urlencode "baslik=ELE GEÇİRİLDİ" -d "tarih=$BUGUN"
ol "POST zorlaması engellendi" "Büro toplantısı" "$($MDB -e "SELECT baslik FROM ajanda WHERE id=2;")"

# Silme yetkisi
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" "$B/ajanda/sil/2")
ol "personel silemez (yönlendirildi)" "302" "$c"
ol "kayıt duruyor" "1" "$($MDB -e "SELECT COUNT(*) FROM ajanda WHERE id=2 AND deleted_at IS NULL;")"

# =====================================================================
echo; echo "=== 5) DURUM İŞLEMLERİ ==="
# =====================================================================
T=$(jeton $JA "$B/ajanda")
curl -s -b $JA -c $JA -o /tmp/aj_j1.json -X POST "$B/ajanda/yapildi" \
  -d "csrf_beyanname=$T" -d "id=1"
ol "tekrarsız iş yapıldı" "True" "$(jal /tmp/aj_j1.json durum)"
ol "  kayıt kapandı" "True" "$(jal /tmp/aj_j1.json kapandi)"
ol "  DB durumu YAPILDI" "YAPILDI" "$($MDB -e "SELECT durum FROM ajanda WHERE id=1;")"
ol "  yapan kaydedildi" "1" "$($MDB -e "SELECT yapan_id FROM ajanda WHERE id=1;")"

T=$(jeton $JA "$B/ajanda")
curl -s -b $JA -c $JA -o /tmp/aj_j2.json -X POST "$B/ajanda/geri-al" \
  -d "csrf_beyanname=$T" -d "id=1"
ol "geri alma çalıştı" "True" "$(jal /tmp/aj_j2.json durum)"
ol "  durum BEKLIYOR" "BEKLIYOR" "$($MDB -e "SELECT durum FROM ajanda WHERE id=1;")"
ol "  yapan temizlendi" "" "$($MDB -e "SELECT IFNULL(yapan_id,'') FROM ajanda WHERE id=1;")"

T=$(jeton $JA "$B/ajanda")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/iptal" -d "csrf_beyanname=$T" -d "id=1"
ol "iptal çalıştı" "IPTAL" "$($MDB -e "SELECT durum FROM ajanda WHERE id=1;")"
$MDBR -e "UPDATE ajanda SET durum='BEKLIYOR' WHERE id=1;" >/dev/null

# Erteleme
T=$(jeton $JA "$B/ajanda")
curl -s -b $JA -c $JA -o /tmp/aj_j3.json -X POST "$B/ajanda/ertele" \
  -d "csrf_beyanname=$T" -d "id=1" -d "tarih=$YARIN"
ol "erteleme çalıştı" "True" "$(jal /tmp/aj_j3.json durum)"
ol "  yeni tarih yazıldı" "$YARIN" "$($MDB -e "SELECT tarih FROM ajanda WHERE id=1;")"

T=$(jeton $JA "$B/ajanda")
curl -s -b $JA -c $JA -o /tmp/aj_j4.json -X POST "$B/ajanda/ertele" \
  -d "csrf_beyanname=$T" -d "id=1" -d "tarih=GECERSIZ"
ol "geçersiz tarih reddedildi" "False" "$(jal /tmp/aj_j4.json durum)"

# Yetkisiz durum değişikliği
T=$(jeton $JP "$B/ajanda")
curl -s -b $JP -c $JP -o /tmp/aj_j5.json -X POST "$B/ajanda/yapildi" \
  -d "csrf_beyanname=$T" -d "id=1"
ol "personel başkasının işini kapatamaz" "False" "$(jal /tmp/aj_j5.json durum)"

# =====================================================================
echo; echo "=== 6) TEKRAR MANTIĞI ==="
# =====================================================================
$MDBR -e "UPDATE ajanda SET tarih='$YARIN', durum='BEKLIYOR' WHERE id=3;" >/dev/null
BEKLENEN=$(date -d "$YARIN +1 month" +%F 2>/dev/null || python3 -c "
import datetime,sys
d=datetime.date.fromisoformat('$YARIN')
y,m=(d.year+1,1) if d.month==12 else (d.year,d.month+1)
import calendar
print(datetime.date(y,m,min(d.day,calendar.monthrange(y,m)[1])).isoformat())")

T=$(jeton $JF "$B/ajanda")
curl -s -b $JF -c $JF -o /tmp/aj_j6.json -X POST "$B/ajanda/yapildi" \
  -d "csrf_beyanname=$T" -d "id=3"
ol "tekrarlı iş KAPANMADI" "False" "$(jal /tmp/aj_j6.json kapandi)"
ol "  tarih 1 ay ötelendi" "$BEKLENEN" "$($MDB -e "SELECT tarih FROM ajanda WHERE id=3;")"
ol "  durum BEKLIYOR kaldı" "BEKLIYOR" "$($MDB -e "SELECT durum FROM ajanda WHERE id=3;")"

# Ay sonu taşması: 31 Ocak + 1 ay = 28/29 Şubat (3 Mart DEĞİL)
$MDBR -e "UPDATE ajanda SET tarih='2026-01-31', tekrar='aylik', durum='BEKLIYOR' WHERE id=3;" >/dev/null
T=$(jeton $JF "$B/ajanda")
curl -s -b $JF -c $JF -o /dev/null -X POST "$B/ajanda/yapildi" -d "csrf_beyanname=$T" -d "id=3"
ol "31 Ocak + 1 ay = 28 Şubat" "2026-02-28" "$($MDB -e "SELECT tarih FROM ajanda WHERE id=3;")"

# Haftalık
$MDBR -e "UPDATE ajanda SET tarih='2026-03-05', tekrar='haftalik', durum='BEKLIYOR' WHERE id=3;" >/dev/null
T=$(jeton $JF "$B/ajanda")
curl -s -b $JF -c $JF -o /dev/null -X POST "$B/ajanda/yapildi" -d "csrf_beyanname=$T" -d "id=3"
ol "haftalık +7 gün" "2026-03-12" "$($MDB -e "SELECT tarih FROM ajanda WHERE id=3;")"

# Yıllık
$MDBR -e "UPDATE ajanda SET tarih='2024-02-29', tekrar='yillik', durum='BEKLIYOR' WHERE id=3;" >/dev/null
T=$(jeton $JF "$B/ajanda")
curl -s -b $JF -c $JF -o /dev/null -X POST "$B/ajanda/yapildi" -d "csrf_beyanname=$T" -d "id=3"
ol "29 Şubat + 1 yıl = 28 Şubat" "2025-02-28" "$($MDB -e "SELECT tarih FROM ajanda WHERE id=3;")"

# Tekrar bitişi geçilince kayıt kapanmalı
$MDBR -e "UPDATE ajanda SET tarih='2026-01-10', tekrar='aylik',
          tekrar_bitis='2026-01-31', durum='BEKLIYOR' WHERE id=3;" >/dev/null
T=$(jeton $JF "$B/ajanda")
curl -s -b $JF -c $JF -o /tmp/aj_j7.json -X POST "$B/ajanda/yapildi" -d "csrf_beyanname=$T" -d "id=3"
ol "tekrar bitişinde kapandı" "True" "$(jal /tmp/aj_j7.json kapandi)"
ol "  durum YAPILDI" "YAPILDI" "$($MDB -e "SELECT durum FROM ajanda WHERE id=3;")"
$MDBR -e "UPDATE ajanda SET tarih='$YARIN', tekrar='aylik', tekrar_bitis=NULL,
          durum='BEKLIYOR' WHERE id=3;" >/dev/null

# =====================================================================
echo; echo "=== 7) SAYAÇLAR VE MENÜ ROZETİ ==="
# =====================================================================
$MDBR -e "UPDATE ajanda SET tarih='$DUN', durum='BEKLIYOR' WHERE id=1;
          UPDATE ajanda SET tarih='$BUGUN', durum='BEKLIYOR' WHERE id=2;" >/dev/null

curl -s -b $JA -o /tmp/aj_s.html "$B/ajanda"
ol "gecikmiş sayacı 1" "1" "$(sayac /tmp/aj_s.html 'Gecikmiş')"
ol "bugün sayacı 1"    "1" "$(sayac /tmp/aj_s.html 'Bugün')"
ol "yaklaşan sayacı 2" "2" "$(sayac /tmp/aj_s.html 'Yaklaşan')"

# Menü rozeti = gecikmiş + bugün
ol "menü rozeti 2" "2" \
   "$(python3 -c "
import re
h=open('/tmp/aj_s.html',encoding='utf-8').read()
m=re.search(r'menu-rozet[^>]*>(\d+)<', h)
print(m.group(1) if m else 'YOK')")"

# Rozet başka sayfada da görünmeli (BaseController'dan gelir)
curl -s -b $JA -o /tmp/aj_s2.html "$B/mukellefler"
ol "rozet mükellefler sayfasında da var" "2" \
   "$(python3 -c "
import re
h=open('/tmp/aj_s2.html',encoding='utf-8').read()
m=re.search(r'menu-rozet[^>]*>(\d+)<', h)
print(m.group(1) if m else 'YOK')")"

# Rozet kullanıcıya göre değişmeli
curl -s -b $JP -o /tmp/aj_s3.html "$B/ajanda"
ol "personel rozeti farklı (kendi kapsamı)" "1" "$(sayac /tmp/aj_s3.html 'Bugün')"

# =====================================================================
echo; echo "=== 8) TAKVİM ==="
# =====================================================================
YIL=$(date +%Y); AY=$(date +%-m)
c=$(curl -s -b $JA -o /tmp/aj_t.html -w "%{http_code}" "$B/ajanda/takvim?yil=$YIL&ay=$AY")
ol "takvim açılıyor" "200" "$c"
ol "takvim hatasız" "0" "$(grep -ciE 'Whoops|Fatal error|Uncaught' /tmp/aj_t.html)"
ol "bugün hücresi işaretli" "1" \
   "$([ "$(grep -c 'class="bugun"' /tmp/aj_t.html)" -gt 0 ] && echo 1 || echo 0)"
ol "olay bağlantısı var" "1" \
   "$([ "$(grep -c 'aj-olay' /tmp/aj_t.html)" -gt 0 ] && echo 1 || echo 0)"
ol "güne ekleme bağlantısı" "1" \
   "$([ "$(grep -c 'aj-gun-ekle' /tmp/aj_t.html)" -gt 0 ] && echo 1 || echo 0)"

# Geçersiz ay güvenli
c=$(curl -s -b $JA -o /dev/null -w "%{http_code}" "$B/ajanda/takvim?yil=2026&ay=99")
ol "geçersiz ay çökmez" "200" "$c"

# Takvim de görünürlüğe uyar
curl -s -b $JP -o /tmp/aj_t2.html "$B/ajanda/takvim?yil=$YIL&ay=$AY"
ol "takvimde kişisel kayıt gizli" "0" \
   "$(python3 -c "
h=open('/tmp/aj_t2.html',encoding='utf-8').read()
print(1 if 'Vergi dairesine uğra' in h else 0)")"

# Bir günde 3'ten çok iş → "+N daha"
$MDBR -e "INSERT INTO ajanda (baslik,tarih,gorunurluk,oncelik,olusturan_id,durum,created_at) VALUES
 ('Yoğun gün 1','$BUGUN','genel','normal',1,'BEKLIYOR',NOW()),
 ('Yoğun gün 2','$BUGUN','genel','normal',1,'BEKLIYOR',NOW()),
 ('Yoğun gün 3','$BUGUN','genel','normal',1,'BEKLIYOR',NOW());" >/dev/null
curl -s -b $JA -o /tmp/aj_t3.html "$B/ajanda/takvim?yil=$YIL&ay=$AY"
ol "3'ten çok işte '+N daha'" "1" \
   "$([ "$(grep -c 'aj-daha' /tmp/aj_t3.html)" -gt 0 ] && echo 1 || echo 0)"
$MDBR -e "DELETE FROM ajanda WHERE baslik LIKE 'Yoğun gün%';" >/dev/null

# =====================================================================
echo; echo "=== 9) GİRİŞ UYARISI ==="
# =====================================================================
$MDBR -e "TRUNCATE ajanda_uyari_okundu;" >/dev/null
curl -s -b $JA -o /tmp/aj_u1.json "$B/ajanda/giris-uyarisi"
ol "uyarı gösterilecek" "True" "$(jal /tmp/aj_u1.json goster)"
ol "  iş listesi dolu" "1" \
   "$(python3 -c "
import json
d=json.load(open('/tmp/aj_u1.json',encoding='utf-8'))
print(1 if len(d.get('isler',[]))>0 else 0)")"
ol "  yalnız gecikmiş+bugün" "2" \
   "$(python3 -c "
import json
d=json.load(open('/tmp/aj_u1.json',encoding='utf-8'))
print(len(d.get('isler',[])))")"

T=$(jeton $JA "$B/ajanda")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/uyari-okundu" -d "csrf_beyanname=$T"
ol "okundu kaydedildi" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM ajanda_uyari_okundu WHERE kullanici_id=1 AND tarih=CURDATE();")"

curl -s -b $JA -o /tmp/aj_u2.json "$B/ajanda/giris-uyarisi"
ol "aynı gün TEKRAR gösterilmez" "False" "$(jal /tmp/aj_u2.json goster)"

# Ayar kapalıyken hiç gösterme
$MDBR -e "UPDATE ayarlar SET deger='0' WHERE anahtar='ajanda_giris_uyari';" >/dev/null
$MDBR -e "TRUNCATE ajanda_uyari_okundu;" >/dev/null
curl -s -b $JA -o /tmp/aj_u3.json "$B/ajanda/giris-uyarisi"
ol "ayar kapalıyken gösterilmez" "False" "$(jal /tmp/aj_u3.json goster)"
$MDBR -e "UPDATE ayarlar SET deger='1' WHERE anahtar='ajanda_giris_uyari';" >/dev/null

# =====================================================================
echo; echo "=== 10) DOSYA EKLERİ ==="
# =====================================================================
echo "test icerigi" > /tmp/aj_ek.txt
T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -F "csrf_beyanname=$T" -F "baslik=Ekli kayit" -F "tarih=$BUGUN" \
  -F "gorunurluk=genel" -F "ekler[]=@/tmp/aj_ek.txt"
EKID=$($MDB -e "SELECT MAX(id) FROM ajanda_ek;")
ol "dosya eki kaydedildi" "1" "$($MDB -e "SELECT COUNT(*) FROM ajanda_ek;")"
ol "  özgün ad korundu" "aj_ek.txt" "$($MDB -e "SELECT dosya_adi FROM ajanda_ek WHERE id=$EKID;")"
ol "  disk adı farklı (çakışma yok)" "0" \
   "$($MDB -e "SELECT COUNT(*) FROM ajanda_ek WHERE id=$EKID AND saklanan=dosya_adi;")"

c=$(curl -s -b $JA -o /tmp/aj_indir.txt -w "%{http_code}" "$B/ajanda/ek/$EKID")
ol "ek indirilebiliyor" "200" "$c"
ol "  içerik doğru" "test icerigi" "$(cat /tmp/aj_indir.txt)"

# Yetkisiz indirme (kişisel kayda ek koyup dene)
AJID=$($MDB -e "SELECT ajanda_id FROM ajanda_ek WHERE id=$EKID;")
$MDBR -e "UPDATE ajanda SET gorunurluk='kisisel' WHERE id=$AJID;" >/dev/null
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" "$B/ajanda/ek/$EKID")
ol "yetkisiz ek indiremez" "302" "$c"
$MDBR -e "UPDATE ajanda SET gorunurluk='genel' WHERE id=$AJID;" >/dev/null

# İzinsiz uzantı reddedilmeli
echo "zararli" > /tmp/aj_ek.php
T=$(jeton $JA "$B/ajanda/yeni")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/ajanda/kaydet" \
  -F "csrf_beyanname=$T" -F "baslik=Php ek denemesi" -F "tarih=$BUGUN" \
  -F "ekler[]=@/tmp/aj_ek.php"
ol ".php uzantısı reddedildi" "1" "$($MDB -e "SELECT COUNT(*) FROM ajanda_ek;")"

curl -s -b $JA -o /dev/null "$B/ajanda/ek-sil/$EKID"
ol "ek silindi" "0" "$($MDB -e "SELECT COUNT(*) FROM ajanda_ek WHERE id=$EKID;")"

# =====================================================================
echo; echo "=== 11) FİLTRELEME VE ARAMA ==="
# =====================================================================
curl -s -b $JA -o /tmp/aj_f1.html "$B/ajanda?durum=BEKLIYOR"
ol "durum filtresi çalışıyor" "0" \
   "$(python3 -c "
import re
h=open('/tmp/aj_f1.html',encoding='utf-8').read()
# YAPILDI rozeti listede olmamalı
print(len(re.findall(r'aj-rozet d-YAPILDI', h)))")"

curl -s -b $JA -o /tmp/aj_f2.html "$B/ajanda?oncelik=acil"
ol "öncelik filtresi" "1" \
   "$(python3 -c "
import re
h=open('/tmp/aj_f2.html',encoding='utf-8').read()
ids=re.findall(r'data-aj-satir=\"(\d+)\"',h)
print(1 if ids==['1'] else 0)")"

curl -s -b $JA -o /tmp/aj_f3.html "$B/ajanda?q=toplant"
ol "arama çalışıyor" "1" \
   "$([ "$(grep -c 'Büro toplantısı' /tmp/aj_f3.html)" -gt 0 ] && echo 1 || echo 0)"

curl -s -b $JA -o /tmp/aj_f4.html "$B/ajanda?mukellef_id=1"
ol "mükellef filtresi" "1" \
   "$(python3 -c "
import re
h=open('/tmp/aj_f4.html',encoding='utf-8').read()
ids=re.findall(r'data-aj-satir=\"(\d+)\"',h)
print(1 if ids==['4'] else 0)")"

curl -s -b $JA -o /tmp/aj_f5.html "$B/ajanda?bas=$BUGUN&bit=$BUGUN"
ol "tarih aralığı filtresi" "1" \
   "$(python3 -c "
import re
h=open('/tmp/aj_f5.html',encoding='utf-8').read()
print(1 if 'Büro toplantısı' in h and 'Vergi dairesine' not in h else 0)")"

curl -s -b $JF -o /tmp/aj_f6.html "$B/ajanda?atanan_id=4"
ol "atanan filtresi" "1" \
   "$(python3 -c "
import re
h=open('/tmp/aj_f6.html',encoding='utf-8').read()
ids=re.findall(r'data-aj-satir=\"(\d+)\"',h)
print(1 if ids==['3'] else 0)")"

# Filtre + görünürlük birlikte: personel filtreyle de sızmamalı
curl -s -b $JP -o /tmp/aj_f7.html "$B/ajanda?oncelik=acil"
ol "filtre görünürlüğü delmiyor" "" "$(gorunen /tmp/aj_f7.html)"

# =====================================================================
echo; echo "=== 12) PANEL KARTI ==="
# =====================================================================
c=$(curl -s -b $JA -o /tmp/aj_p.html -w "%{http_code}" "$B/panel")
ol "panel açılıyor" "200" "$c"
ol "panel hatasız" "0" "$(grep -ciE 'Whoops|Fatal error|Uncaught' /tmp/aj_p.html)"
ol "ajanda kartı görünüyor" "1" \
   "$([ "$(grep -c 'Ajanda — Yaklaşan İşler' /tmp/aj_p.html)" -gt 0 ] && echo 1 || echo 0)"
ol "panelde gecikmiş rozeti" "1" \
   "$([ "$(grep -c 'gecikmiş' /tmp/aj_p.html)" -gt 0 ] && echo 1 || echo 0)"

# Panel de görünürlüğe uymalı
curl -s -b $JP -o /tmp/aj_p2.html "$B/panel"
ol "panelde kişisel kayıt gizli" "0" \
   "$(python3 -c "
h=open('/tmp/aj_p2.html',encoding='utf-8').read()
print(1 if 'Vergi dairesine uğra' in h else 0)")"

# =====================================================================
echo; echo "=== 13) YAZDIRMA ==="
# =====================================================================
c=$(curl -s -b $JA -o /tmp/aj_y.html -w "%{http_code}" "$B/ajanda/yazdir")
ol "yazdırma açılıyor" "200" "$c"
ol "yazdırma hatasız" "0" "$(grep -ciE 'Whoops|Fatal error|Uncaught' /tmp/aj_y.html)"
ol "stil gömülü (stil.css yok)" "0" "$(grep -c 'stil.css' /tmp/aj_y.html)"
ol "imza bloğu var" "1" \
   "$([ "$(grep -c 'Hazırlayan' /tmp/aj_y.html)" -gt 0 ] && echo 1 || echo 0)"
ol "araç çubuğu yazdırmada gizli" "1" \
   "$([ "$(grep -c 'yazdirma-gizle' /tmp/aj_y.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırma filtreyi taşıyor" "1" \
   "$(python3 -c "
import re
h=open('/tmp/aj_y.html',encoding='utf-8').read()
print(1 if 'Ajanda Listesi' in h else 0)")"

curl -s -b $JP -o /tmp/aj_y2.html "$B/ajanda/yazdir"
ol "yazdırmada da görünürlük geçerli" "0" \
   "$(python3 -c "
h=open('/tmp/aj_y2.html',encoding='utf-8').read()
print(1 if 'Vergi dairesine uğra' in h else 0)")"

# =====================================================================
echo; echo "=== 14) YUMUŞAK SİLME ==="
# =====================================================================
T=$(jeton $JA "$B/ajanda")
curl -s -b $JA -o /dev/null "$B/ajanda/sil/2"
ol "kayıt silindi (yumuşak)" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM ajanda WHERE id=2 AND deleted_at IS NOT NULL;")"
curl -s -b $JA -o /tmp/aj_sil.html "$B/ajanda"
ol "silinen listede görünmüyor" "0" \
   "$(python3 -c "
h=open('/tmp/aj_sil.html',encoding='utf-8').read()
print(1 if 'data-aj-satir=\"2\"' in h else 0)")"
c=$(curl -s -b $JA -o /dev/null -w "%{http_code}" "$B/ajanda/detay/2")
ol "silinen kayda erişilemiyor" "302" "$c"

# =====================================================================
#  TEMİZLİK — kendi izimizi bırakmayalım
# =====================================================================
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE ajanda_ek; TRUNCATE ajanda_uyari_okundu; TRUNCATE ajanda;
SET FOREIGN_KEY_CHECKS=1;
DELETE FROM kullanici_musavirleri;
" >/dev/null 2>&1
rm -f /tmp/aj_ek.txt /tmp/aj_ek.php

echo
echo "======================================================"
echo " GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "======================================================"
[ $k -eq 0 ] || exit 1
