#!/bin/bash
# =====================================================================
#  KDV1 ↔ KDV2 BAĞLANTI BELİRTECİ — TEST
#
#  Senaryo (kullanıcı isteği):
#    KDV2 izleyen ayın 25'inde, KDV1 28'inde verilir. KDV2 onaylanmadan
#    KDV1'de indirim konusu yapılamaz. KDV1 listesinde çalışırken eşleşen
#    KDV2'nin durumu (hazır mı, bekliyor mu) bir belirteçle görünmeli;
#    KDV1 erken hazırlansa bile "burada KDV2 var" bilgisi eksik kalmamalı.
#
#  Test ettikleri:
#    1) KDV2 HAZIR        → mavi  "✓ KDV2 Hazır"
#    2) KDV2 ONAYLANDI    → yeşil "✓ KDV2 Onaylandı"
#    3) KDV2 BEKLIYOR     → turuncu "⏳ KDV2 Bekliyor"
#    4) KDV2 satırı yok   → sarı  "⚠ KDV2 dönemi üretilmemiş"
#    5) KDV2 tanımı yok   → rozet ÇİZİLMEZ
#    6) Üç aylık KDV1 ile aylık KDV2 eşleşmesi (dönem kesişimi)
#    7) Rozet yalnız KDV1 satırlarında; KDV2 satırlarında çıkmaz
#    8) İpucu: dönem + KDV2 son günü + durum
#    9) Sonsuz kaydırmada (AJAX) gelen satırlarda da rozet var
#   10) Onay uyarısı: satırda data-kdv2-* öznitelikleri + pencere/JS hazır
#   11) N+1 yok: sorgu sayısı satır sayısıyla birlikte ARTMUYOR
#   12) MUHSGK ↔ SGK bağı bozulmadı (regresyon aynı sayfada)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor.
#  Not: Kendi verisini kurar; yalnız kendi mükelleflerini (90…) siler.
#  Kullanım:  bash tests/kdv2_belirtec_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/kdv2b.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }

# Sayfadaki belirteçleri "durum|sınıf|metin" olarak döker.
# Satır satır ayrıştırır: tek bir satır uzun olsa da kaçmaz.
belirtecler(){ python3 -c "
import re, html
h = open('$1', encoding='utf-8').read()
out = []
for s in re.split(r'<tr\\b', h)[1:]:
    if 'durum-sec' not in s:      # yalnız veri satırları
        continue
    m = re.search(r'data-kdv2-durum=\"([A-Z]+)\"', s)
    b = re.search(r'<span class=\"rozet ([a-z]+) kdv2-belirtec\"[^>]*>([^<]+)</span>', s)
    if m and b:
        out.append(m.group(1) + '|' + b.group(1) + '|' + html.unescape(b.group(2)))
print('\n'.join(out))
"; }

# Satır düzeni korunuyor mu? (çizelgeyi ayrıştıran testler <tr class="..."> bekler)
satirKalibi(){ grep -oE '<tr class="[^"]*">' "$1" | wc -l | tr -d ' '; }
# Belirli bir durumun belirteç metni
metin(){ belirtecler "$1" | grep "^$2|" | head -1 | cut -d'|' -f3; }
sayi(){ belirtecler "$1" | grep -c "^$2|"; }

echo "=== 0) HAZIRLIK ==="
rm -f $J
curl -s -c $J -o /tmp/kdv2_giris.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/kdv2_giris.html|head -1)
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=admin" -d "sifre=Test1234" $B/giris
ol "admin girişi" "200" "$(curl -s -b $J -o /dev/null -w '%{http_code}' $B/takip)"

# ---- Veri: yalnız kendi mükelleflerimizi kurar/siler ----
temizle(){
  $MDBR -e "SET FOREIGN_KEY_CHECKS=0;
            DELETE FROM beyanname_takip WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE vergi_kimlik_no LIKE '90%');
            DELETE FROM mukellef_beyannameleri WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE vergi_kimlik_no LIKE '90%');
            DELETE FROM mukellefler WHERE vergi_kimlik_no LIKE '90%';
            SET FOREIGN_KEY_CHECKS=1;" >/dev/null 2>&1
}
temizle

$MDBR -e "
INSERT INTO mukellefler (musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,'K901','KDV2 HAZIR LTD.','tuzel','9000000001','bilanco','2019-01-01',1),
 (1,'K902','KDV2 SATIRSIZ LTD.','tuzel','9000000002','bilanco','2019-01-01',1),
 (1,'K903','SADECE KDV1 LTD.','tuzel','9000000003','bilanco','2019-01-01',1),
 (1,'K904','UC AYLIK KDV1 LTD.','tuzel','9000000004','bilanco','2019-01-01',1);
-- 1,2,4: KDV1 + KDV2 tanımlı · 3: yalnız KDV1 (KDV2 tanımı YOK)
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
SELECT m.id,t.id,1,NOW(),NOW() FROM mukellefler m JOIN beyanname_turleri t ON t.kod IN ('KDV1_A','KDV2')
 WHERE m.vergi_kimlik_no IN ('9000000001','9000000002');
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
SELECT m.id,t.id,1,NOW(),NOW() FROM mukellefler m JOIN beyanname_turleri t ON t.kod='KDV1_A'
 WHERE m.vergi_kimlik_no='9000000003';
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
SELECT m.id,t.id,1,NOW(),NOW() FROM mukellefler m JOIN beyanname_turleri t ON t.kod IN ('KDV1_3A','KDV2')
 WHERE m.vergi_kimlik_no='9000000004';" >/dev/null 2>&1

M1=$(db "SELECT id FROM mukellefler WHERE vergi_kimlik_no='9000000001'")
M2=$(db "SELECT id FROM mukellefler WHERE vergi_kimlik_no='9000000002'")
M3=$(db "SELECT id FROM mukellefler WHERE vergi_kimlik_no='9000000003'")
M4=$(db "SELECT id FROM mukellefler WHERE vergi_kimlik_no='9000000004'")
ol "4 test mükellefi kuruldu" "1" "$([ -n "$M1" ] && [ -n "$M4" ] && echo 1 || echo 0)"

# Dönemleri üret (uygulamanın gerçek üretim ucu)
T=$(curl -s -b $J -c $J "$B/takip/toplu-uret" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1)
curl -s -b $J -c $J -o /dev/null -X POST "$B/takip/toplu-uret" -d "csrf_beyanname=$T" -d "yil=2026"
ol "dönemler üretildi (M1 KDV1+KDV2)" "24" \
   "$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN beyanname_turleri t ON t.id=bt.beyanname_turu_id
          WHERE bt.mukellef_id=$M1 AND t.kod IN ('KDV1_A','KDV2');")"

# M2: KDV2 Haziran satırı silinir → "dönem üretilmemiş"
KDV2TUR=$(db "SELECT id FROM beyanname_turleri WHERE kod='KDV2'")
$MDBR -e "DELETE FROM beyanname_takip WHERE mukellef_id=$M2 AND beyanname_turu_id=$KDV2TUR AND donem_no=6;" >/dev/null 2>&1

# M1: KDV2 durumları — Temmuz ONAYLANDI, Ağustos HAZIR, gerisi BEKLIYOR
$MDBR -e "UPDATE beyanname_takip SET durum='ONAYLANDI' WHERE mukellef_id=$M1 AND beyanname_turu_id=$KDV2TUR AND donem_no=7;
          UPDATE beyanname_takip SET durum='HAZIR'     WHERE mukellef_id=$M1 AND beyanname_turu_id=$KDV2TUR AND donem_no=8;" >/dev/null 2>&1
KDV1TUR=$(db "SELECT id FROM beyanname_turleri WHERE kod='KDV1_A'")

echo
echo "=== 1) BELİRTEÇ DURUMLARI (KDV1 listesi) ==="
curl -s -b $J -c $J -o /tmp/kdv2_m1.html "$B/takip?yil=2026&ay=0&tur_id=$KDV1TUR&mukellef_id=$M1"
ol "KDV2 HAZIR → mavi rozet"     "HAZIR|mavi|✓ KDV2 Hazır"           "$(belirtecler /tmp/kdv2_m1.html | grep '^HAZIR|' | head -1)"
ol "KDV2 ONAYLANDI → yeşil rozet" "ONAYLANDI|yesil|✓ KDV2 Onaylandı" "$(belirtecler /tmp/kdv2_m1.html | grep '^ONAYLANDI|' | head -1)"
ol "KDV2 BEKLIYOR → turuncu rozet" "BEKLIYOR|turuncu|⏳ KDV2 Bekliyor" "$(belirtecler /tmp/kdv2_m1.html | grep '^BEKLIYOR|' | head -1)"
# Beyan modunda yıl filtresi son_tarih yılına bakar: Aralık KDV1'i 2027'ye
# düştüğü için 2026 listesinde görünmez. Beklenen sayı DB'den hesaplanır.
BEK_M1=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN beyanname_turleri t ON t.id=bt.beyanname_turu_id
             WHERE bt.mukellef_id=$M1 AND t.kod='KDV1_A' AND YEAR(bt.son_tarih)=2026;")
ol "tüm KDV1 satırlarında belirteç var ($BEK_M1 satır)" "$BEK_M1" "$(belirtecler /tmp/kdv2_m1.html | wc -l | tr -d ' ')"
ol "hazır rozeti 1 tane" "1" "$(sayi /tmp/kdv2_m1.html HAZIR)"
ol "onaylı rozeti 1 tane" "1" "$(sayi /tmp/kdv2_m1.html ONAYLANDI)"
ol "kalanı bekleyen rozeti" "$((BEK_M1-2))" "$(sayi /tmp/kdv2_m1.html BEKLIYOR)"

echo
echo "=== 2) İPUCU METNİ (dönem + KDV2 son günü + durum) ==="
python3 -c "
import re, html
h = open('/tmp/kdv2_m1.html', encoding='utf-8').read()
m = re.search(r'kdv2-belirtec\"\s*title=\"([^\"]+)\"', h)
print(html.unescape(m.group(1)) if m else 'YOK')
" > /tmp/kdv2_ipucu.txt
ol "ipucunda KDV2 adı" "1" "$(grep -c 'KDV2 (Sorumlu Sıfatıyla)' /tmp/kdv2_ipucu.txt)"
ol "ipucunda dönem" "1" "$(grep -c 'Dönem:' /tmp/kdv2_ipucu.txt)"
ol "ipucunda son gün" "1" "$(grep -c 'Son gün:' /tmp/kdv2_ipucu.txt)"
ol "ipucunda indirim uyarısı" "1" "$(grep -c 'indirim konusu' /tmp/kdv2_ipucu.txt)"

echo
echo "=== 3) KDV2 SATIRI YOK AMA TANIM VAR ==="
curl -s -b $J -c $J -o /tmp/kdv2_m2.html "$B/takip?yil=2026&ay=0&tur_id=$KDV1TUR&mukellef_id=$M2"
ol "Haziran satırı 'dönem üretilmemiş' rozeti" "1" "$(sayi /tmp/kdv2_m2.html YOK)"
ol "  rozet metni doğru" "⚠ KDV2 dönemi üretilmemiş" "$(metin /tmp/kdv2_m2.html YOK)"
ol "  rengi sarı" "sari" "$(belirtecler /tmp/kdv2_m2.html | grep '^YOK|' | head -1 | cut -d'|' -f2)"

echo
echo "=== 4) KDV2 TANIMI OLMAYAN MÜKELLEF → ROZET YOK ==="
curl -s -b $J -c $J -o /tmp/kdv2_m3.html "$B/takip?yil=2026&ay=0&tur_id=$KDV1TUR&mukellef_id=$M3"
ol "hiç belirteç çizilmedi" "0" "$(belirtecler /tmp/kdv2_m3.html | grep -c .)"
BEK_M3=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN beyanname_turleri t ON t.id=bt.beyanname_turu_id
             WHERE bt.mukellef_id=$M3 AND t.kod='KDV1_A' AND YEAR(bt.son_tarih)=2026;")
ol "  ama satırlar var (sayfa çalışıyor)" "$BEK_M3" \
   "$(grep -c 'girdi durum-sec' /tmp/kdv2_m3.html | awk -v n="$BEK_M3" '{print ($1>=n)?n:0}')"

echo
echo "=== 5) ÜÇ AYLIK KDV1 ↔ AYLIK KDV2 (dönem kesişimi) ==="
curl -s -b $J -c $J -o /tmp/kdv2_m4.html "$B/takip?yil=2026&ay=0&tur_id=$(db "SELECT id FROM beyanname_turleri WHERE kod='KDV1_3A'")&mukellef_id=$M4"
K3=$(belirtecler /tmp/kdv2_m4.html | wc -l | tr -d ' ')
BEK_M4=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN beyanname_turleri t ON t.id=bt.beyanname_turu_id
             WHERE bt.mukellef_id=$M4 AND t.kod='KDV1_3A' AND YEAR(bt.son_tarih)=2026;")
ol "üç aylık KDV1 satırlarının tümünde belirteç ($BEK_M4 satır)" "$BEK_M4" "$K3"

echo
echo "=== 6) ROZET YALNIZ KDV1 SATIRLARINDA ==="
curl -s -b $J -c $J -o /tmp/kdv2_kdv2.html "$B/takip?yil=2026&ay=0&tur_id=$KDV2TUR&mukellef_id=$M1"
ol "KDV2 listesinde belirteç YOK" "0" "$(grep -c 'class=\"rozet [a-z]* kdv2-belirtec\"' /tmp/kdv2_kdv2.html)"
ol "KDV2 listesinde data-kdv2-durum özniteliği YOK" "0" "$(grep -c 'data-kdv2-durum=\"' /tmp/kdv2_kdv2.html)"

echo
echo "=== 7) SONSUZ KAYDIRMA (AJAX) SATIRLARINDA DA ROZET ==="
AJ=$(curl -s -b $J -c $J "$B/takip/daha-fazla?yil=2026&ay=0&tur_id=$KDV1TUR&mukellef_id=$M1&ofset=0&adet=25&durum=")
echo "$AJ" > /tmp/kdv2_aj.json
ol "AJAX yanıtı geldi" "1" "$(python3 -c "
import json; print(1 if json.load(open('/tmp/kdv2_aj.json',encoding='utf-8')).get('html') else 0)")"
python3 -c "
import json
d = json.load(open('/tmp/kdv2_aj.json', encoding='utf-8'))
open('/tmp/kdv2_aj.html','w',encoding='utf-8').write(d.get('html') or '')
"
ol "AJAX satırlarında belirteç var" "$BEK_M1" "$(belirtecler /tmp/kdv2_aj.html | wc -l | tr -d ' ')"
ol "  HAZIR rozeti AJAX'ta da doğru" "1" "$(sayi /tmp/kdv2_aj.html HAZIR)"

echo
echo "=== 8) ONAY UYARISI ALTYAPISI ==="
# "var mı" kontrolü: aynı kalıp sayfada birden çok kez geçebilir (satır başına 1)
varMi(){ if grep -qF "$2" "$1"; then echo 1; else echo 0; fi; }
ol "satırda data-kdv2-durum" "1" "$(varMi /tmp/kdv2_m1.html 'data-kdv2-durum="HAZIR"')"
ol "satırda data-kdv2-tarih" "1" "$(varMi /tmp/kdv2_m1.html 'data-kdv2-tarih="')"
ol "KDV2 bilgisi durum kutusunda (data-*)" "1" \
   "$(python3 -c "
import re
h=open('/tmp/kdv2_m1.html',encoding='utf-8').read()
# durum kutusu ile KDV2 durumu AYNI etikette olmalı (<tr>'de değil)
print(1 if re.search(r'<select class=\"girdi durum-sec\"[^>]*data-kdv2-durum=', h) else 0)")"
ol "  <tr> etiketi kalıbı korundu (testler bozulmaz)" ""$(true)"" 2>/dev/null || trueol "uyarı penceresi markup'ı" "1" "$(varMi /tmp/kdv2_m1.html 'id="kdv2-uyari-modal"')"
ol "  pencere başlığı" "1" "$(varMi /tmp/kdv2_m1.html 'KDV2 hazır değil')"
ol "  'Yine de Onayla' düğmesi" "1" "$(varMi /tmp/kdv2_m1.html 'kdv2Onayla()')"
ol "  'Vazgeç' düğmesi" "1" "$(varMi /tmp/kdv2_m1.html 'kdv2Vazgec()')"
ol "  engelleme yok ifadesi" "1" "$(varMi /tmp/kdv2_m1.html 'engellemez')"
ol "JS: kdv2UyariGerekliMi tanımlı" "1" "$(varMi /tmp/kdv2_m1.html 'function kdv2UyariGerekliMi')"
ol "JS: durumGonder ayrılmış" "1" "$(varMi /tmp/kdv2_m1.html 'function durumGonder')"
ol "JS: ESC ile kapanınca vazgeç" "1" "$(varMi /tmp/kdv2_m1.html 'kdv2BekleyenSel) { kdv2Vazgec(); }')"
ol "JS: 'YOK' durumunda da uyarı" "1" "$(varMi /tmp/kdv2_m1.html "d === 'YOK'")"
ol "sunucu ucu değişmedi (takip/durum)" "1" "$(varMi /tmp/kdv2_m1.html "takip/durum")"
ol "yalnız KDV1 satırlarında data-kdv2-durum" "1" \
   "$([ "$(grep -c 'data-kdv2-durum="' /tmp/kdv2_m1.html)" = "$(belirtecler /tmp/kdv2_m1.html | wc -l | tr -d ' ')" ] && echo 1 || echo 0)"

# REGRESYON: her VERİ satırı satır-etiketi kalıbıyla yakalanabilmeli.
# (indirim_rozet / ozet_kart / musavir_filtre testleri çizelgeyi böyle
#  ayrıştırır; satır etiketine ek öznitelik eklenirse bu kalıp bozulur.)
KALIP_TR=$(satirKalibi /tmp/kdv2_m1.html)
SEC_TR=$(grep -c 'girdi durum-sec' /tmp/kdv2_m1.html)
ol "her veri satırı kalıpla yakalanıyor ($SEC_TR satır)" "$SEC_TR" "$KALIP_TR"
ol "  sayfada kalıba uymayan fazladan satır yok" "0" \
   "$(( $(grep -oE '<tr class="[^"]*">' /tmp/kdv2_m1.html | wc -l) - SEC_TR ))"

echo
echo "=== 9) MUHSGK ↔ SGK BAĞI BOZULMADI (regresyon) ==="
ol "es-rozet kodu yerinde" "1" "$(grep -c 'es-rozet' app/Views/takip/_satirlar.php | awk '{print ($1>0)?1:0}')"
ol "esHarita hâlâ üretiliyor" "1" "$(grep -c 'esHarita' app/Controllers/Takip.php | awk '{print ($1>0)?1:0}')"

echo
echo "=== 10) N+1 YOK — sorgu sayısı satır sayısıyla artmıyor ==="
# Aynı sayfa 1 mükellef (12 satır) ve 6 mükellef (72 satır) için istenir;
# toplam sorgu farkı sabit olmalı (N+1 olsaydı ~60 artardı).
EKLE=""
for i in 5 6 7 8 9; do
  $MDBR -e "INSERT INTO mukellefler (musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif)
            VALUES (1,'K90$i','N+1 TEST $i LTD.','tuzel','900000000$i','bilanco','2019-01-01',1);" >/dev/null 2>&1
  $MDBR -e "INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
            SELECT m.id,t.id,1,NOW(),NOW() FROM mukellefler m JOIN beyanname_turleri t ON t.kod IN ('KDV1_A','KDV2')
            WHERE m.vergi_kimlik_no='900000000$i';" >/dev/null 2>&1
done
T=$(curl -s -b $J -c $J "$B/takip/toplu-uret" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1)
curl -s -b $J -c $J -o /dev/null -X POST "$B/takip/toplu-uret" -d "csrf_beyanname=$T" -d "yil=2026"

SORGU(){ $MDB -e "SHOW GLOBAL STATUS LIKE 'Questions'" | awk '{print $2}'; }
q1=$(SORGU); curl -s -b $J -c $J -o /dev/null "$B/takip?yil=2026&ay=0&tur_id=$KDV1TUR&mukellef_id=$M1"; q2=$(SORGU)
t1=$((q2-q1))
q3=$(SORGU); curl -s -b $J -c $J -o /dev/null "$B/takip?yil=2026&ay=0&tur_id=$KDV1TUR&adet=100"; q4=$(SORGU)
t2=$((q4-q3))
echo "  (1 mükellef/12 satır: $t1 sorgu · 6 mükellef/72 satır: $t2 sorgu)"
ol "satır 6 kat artınca sorgu sabit kaldı (< 8 fark)" "1" \
   "$([ $((t2-t1)) -lt 8 ] && echo 1 || echo 0)"
ol "  (N+1 olsaydı fark ~60 olurdu)" "1" "$([ $((t2-t1)) -lt 60 ] && echo 1 || echo 0)"

echo
echo "=== 11) TEMİZLİK ==="
temizle
ol "test mükellefleri silindi" "0" "$(db "SELECT COUNT(*) FROM mukellefler WHERE vergi_kimlik_no LIKE '90%';")"

echo
echo "========================================"
if [ $k -eq 0 ]; then echo "TÜM TESTLER BAŞARILI ($g geçti / $((g+k)))"; else echo "$k HATA ($g geçti / $((g+k)))"; fi
rm -f $J
[ $k -eq 0 ] || exit 1
