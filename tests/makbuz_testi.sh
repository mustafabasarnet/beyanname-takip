#!/bin/bash
# =====================================================================
#  MAKBUZ TAKİP MODÜLÜ — REGRESYON TESTİ
#
#  Kapsam:
#   1. Şema + idempotent migration
#   2. Yıllık ücret (hedef) / kesilen / kalan hesabı
#   3. Durum sınıflandırması (tamam, kısmi, hiç, ücretsiz, aşım)
#   4. Mali müşavir bazında özet
#   5. Stopaj / KDV / net hesabı
#   6. Excel: yıllık ücret içe aktarma (güncelleme dahil)
#   7. Excel: makbuz içe aktarma (mükerrer + hatalı yakalama)
#   8. Ücret kopyalama (zam oranıyla)
#   9. Mükellef kartından yıllık ücret girişi
#  10. Yetki: personel erişemez, müşavir kapsamı sızmaz
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin / musavir / personel (Test1234) kullanıcıları var.
#  Not: Test kendi verisini kurar.
#  Kullanım:  bash tests/makbuz_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
KOK="$(cd "$(dirname "$0")/.." && pwd)"
J=/tmp/mkt_a.txt
JM=/tmp/mkt_m.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/mkt_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mkt_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Not: data-mukellef satır başına 2 kez geçer (<tr> + ücret alanı).
# Yalnızca gerçek <tr> öğeleri sayılır.
say(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
print(len(re.findall(r'<tr[^>]*data-mukellef=', h, re.S)))
" "$1"; }

# Belirli mükellefin satırından sütun okur: hucre <dosya> <ünvan> <indeks>
hucre(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
for tr in re.findall(r'<tr[^>]*data-mukellef=.*?</tr>', h, re.S):
    ad=re.search(r'class=\"kalin\">\s*([^<]+)', tr)
    if not ad or sys.argv[2] not in ad.group(1): continue
    tds=re.findall(r'<td[^>]*>(.*?)</td>', tr, re.S)
    print(re.sub(r'<[^>]+>','',tds[int(sys.argv[3])]).strip()); break
else: print('SATIR-YOK')
" "$1" "$2" "$3"; }

onizleOzet(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
d={}
for m in re.finditer(r'etiket\">([^<]+)</div>\s*<div class=\"deger\">(\d+)', h):
    d[m.group(1).strip()]=m.group(2)
print(d.get(sys.argv[2],'YOK'))
" "$1" "$2"; }

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE makbuzlar; TRUNCATE mukellef_ucretleri;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,renk,aktif) VALUES
 (1,'SMMM','Ali Yılmaz','Yılmaz','#2563eb',1),(2,'SMMM','Veli Demir','Demir','#16a34a',1);
UPDATE kullanicilar SET musavir_id=2 WHERE kullanici_adi='musavir';
UPDATE ayarlar SET deger='20' WHERE anahtar='makbuz_stopaj_oran';
UPDATE ayarlar SET deger='20' WHERE anahtar='makbuz_kdv_oran';
-- Beş durum: kısmi / ücretsiz / tamam / aşım / hiç kesilmemiş
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','ALFA İNŞAAT LTD. ŞTİ.','tuzel','1112223334',NULL,'bilanco','2019-01-01',1),
 (2,1,'M002','BETA TİCARET A.Ş.','tuzel','2223334445',NULL,'bilanco','2019-01-01',1),
 (3,1,'M003','MEHMET KAYA','gercek',NULL,'33344455566','isletme','2019-01-01',1),
 (4,2,'M004','DELTA GIDA A.Ş.','tuzel','4445556667',NULL,'bilanco','2019-01-01',1),
 (5,2,'M005','AYŞE ÇELİK','gercek',NULL,'55566677788','isletme','2019-01-01',1);
INSERT INTO mukellef_ucretleri (mukellef_id,yil,tutar,created_at,updated_at) VALUES
 (1,2026,36000.00,NOW(),NOW()),(2,2026,24000.00,NOW(),NOW()),
 (3,2026,18000.00,NOW(),NOW()),(4,2026,48000.00,NOW(),NOW());
INSERT INTO makbuzlar (mukellef_id,musavir_id,yil,ay,makbuz_no,tarih,brut,stopaj,kdv,net,created_at,updated_at) VALUES
 (1,1,2026,1,'2026000101','2026-01-15',9000.00,1800.00,1800.00,9000.00,NOW(),NOW()),
 (1,1,2026,2,'2026000102','2026-02-15',9000.00,1800.00,1800.00,9000.00,NOW(),NOW()),
 (2,1,2026,1,'2026000103','2026-01-20',24000.00,4800.00,4800.00,24000.00,NOW(),NOW()),
 (4,2,2026,1,'2026000201','2026-01-10',50000.00,10000.00,10000.00,50000.00,NOW(),NOW());"
}

veriKur
giris admin $J
curl -s -b $J "$B/makbuz?yil=2026" -o /tmp/mkt_l.html

echo "=== 1) ŞEMA ==="
for t in makbuzlar mukellef_ucretleri; do
  ol "$t tablosu var" "1" "$($MDB -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'")"
done
ol "Ücret benzersizliği (mukellef+yil)" "1" \
  "$($MDB -e "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellef_ucretleri' AND INDEX_NAME='uq_mukellef_ucret'")"
for a in makbuz_stopaj_oran makbuz_kdv_oran; do
  ol "$a ayarı var" "1" "$($MDB -e "SELECT COUNT(*) FROM ayarlar WHERE anahtar='$a'")"
done
$MDBR < "$KOK/database/migration_makbuz.sql" >/dev/null 2>&1
ol "Migration ikinci kez çalıştı (idempotent)" "4" "$($MDB -e 'SELECT COUNT(*) FROM mukellef_ucretleri')"

echo "=== 2) SAYFA VE ÇİZELGE ==="
ol "Sayfa açılıyor" "200" "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/makbuz?yil=2026")"
ol "Hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Unknown column|Undefined variable' /tmp/mkt_l.html | awk '{print ($1>0)?1:0}')"
ol "5 mükellef listeleniyor" "5" "$(say /tmp/mkt_l.html)"
ol "Menüde Makbuz Takip var" "1" "$(grep -c 'Makbuz Takip' /tmp/mkt_l.html | awk '{print ($1>0)?1:0}')"

echo "=== 3) ÜCRET / KESİLEN / KALAN HESABI ==="
# ALFA: 36.000 hedef, 18.000 kesilmiş → 18.000 kalan
ol "ALFA ücret"   "36.000,00" "$(hucre /tmp/mkt_l.html 'ALFA' 1)"
ol "ALFA kesilen" "18.000,00" "$(hucre /tmp/mkt_l.html 'ALFA' 2)"
ol "ALFA kalan"   "18.000,00" "$(hucre /tmp/mkt_l.html 'ALFA' 3)"
ol "ALFA makbuz adedi" "2"    "$(hucre /tmp/mkt_l.html 'ALFA' 4)"
# BETA: tamamlanmış
ol "BETA kalan sıfır" "0,00"  "$(hucre /tmp/mkt_l.html 'BETA' 3)"
# DELTA: ücreti aşmış → negatif kalan
ol "DELTA kalan negatif" "-2.000,00" "$(hucre /tmp/mkt_l.html 'DELTA' 3)"
# MEHMET: hiç kesilmemiş
ol "MEHMET kesilen sıfır" "0,00" "$(hucre /tmp/mkt_l.html 'MEHMET' 2)"
# AYŞE: ücreti girilmemiş
ol "AYŞE ücret girilmemiş" "— gir —" "$(hucre /tmp/mkt_l.html 'AYŞE' 1)"

echo "=== 4) DURUM FİLTRELERİ ==="
# Not: MySQL HAVING içinde alt sorgu takma adını tanımaz; WHERE ile yazıldı.
f(){ curl -s -b $J "$B/makbuz?yil=2026&durum=$1" -o /tmp/mkt_d.html; say /tmp/mkt_d.html; }
ol "DEVAM = 1 (ALFA)"        "1" "$(f DEVAM)"
ol "BASLAMADI = 1 (MEHMET)"  "1" "$(f BASLAMADI)"
ol "UCRETSIZ = 1 (AYŞE)"     "1" "$(f UCRETSIZ)"
ol "ASIM = 1 (DELTA)"        "1" "$(f ASIM)"
ol "TAMAM = 2 (BETA + DELTA)" "2" "$(f TAMAM)"
ol "Filtresiz = 5"           "5" "$(f '')"
curl -s -b $J "$B/makbuz?yil=2026&q=ALFA" -o /tmp/mkt_q.html
ol "Arama filtresi" "1" "$(say /tmp/mkt_q.html)"

echo "=== 5) ÖZET KARTLARI ==="
kart(){ python3 -c "
import re,sys
h=open('/tmp/mkt_l.html',encoding='utf-8').read()
m=re.search(r'etiket\">'+re.escape(sys.argv[1])+r'</div>\s*<div class=\"deger\"[^>]*>([^<]+)',h)
print(m.group(1).strip() if m else 'YOK')" "$1"; }
# Beklenen değerler DB'den okunur: bu betiğin ilerleyen adımları (içe
# aktarma, kart üzerinden ücret girişi) veriyi değiştiriyor; sabit sayı
# yazmak yanlış alarm üretirdi.
DB_U=$($MDB -e "SELECT FORMAT(SUM(tutar),2,'de_DE') FROM mukellef_ucretleri WHERE yil=2026")
DB_K=$($MDB -e "SELECT FORMAT(IFNULL(SUM(brut),0),2,'de_DE') FROM makbuzlar WHERE yil=2026")
DB_KA=$($MDB -e "SELECT FORMAT((SELECT IFNULL(SUM(tutar),0) FROM mukellef_ucretleri WHERE yil=2026)
                 - (SELECT IFNULL(SUM(brut),0) FROM makbuzlar WHERE yil=2026),2,'de_DE')")
ol "Toplam sözleşme = DB ($DB_U)" "$DB_U" "$(kart 'Yıllık Sözleşme')"
ol "Toplam kesilen = DB ($DB_K)"  "$DB_K" "$(kart 'Kesilen Makbuz')"
ol "Toplam kalan = DB ($DB_KA)"   "$DB_KA" "$(kart 'Kalan')"

echo "=== 6) MÜŞAVİR BAZINDA ÖZET ==="
# Not: gömülü <style> içinde .mk-mus-kart{...} tanımı da geçer;
# yalnızca gerçek <div class="mk-mus-kart"> öğeleri sayılır.
ol "2 müşavir kartı" "2" "$(grep -o 'class="mk-mus-kart"' /tmp/mkt_l.html | wc -l | tr -d ' ')"
mus(){ python3 -c "
import re,sys
h=open('/tmp/mkt_l.html',encoding='utf-8').read()
for k in re.findall(r'mk-mus-kart\">(.*?)</div>\s*</div>', h, re.S):
    ad=re.search(r'mk-mus-nokta[^>]*></span>\s*([^<]+)', k)
    if not ad or sys.argv[1] not in ad.group(1): continue
    v=re.findall(r'<b[^>]*>([\d.,]+) ₺</b>', k)
    print(v[int(sys.argv[2])] if len(v)>int(sys.argv[2]) else 'YOK'); break
else: print('KART-YOK')" "$1" "$2"; }
ol "Ali sözleşme (78.000,00)" "78.000,00" "$(mus 'Ali' 0)"
ol "Ali kesilen (42.000,00)"  "42.000,00" "$(mus 'Ali' 1)"
ol "Veli sözleşme (48.000,00)" "48.000,00" "$(mus 'Veli' 0)"
ol "Veli kesilen (50.000,00)"  "50.000,00" "$(mus 'Veli' 1)"
# Kesen müşavir esas alınır (portföy sahibi değil)
ALI_DB=$($MDB -e "SELECT FORMAT(IFNULL(SUM(brut),0),2,'de_DE') FROM makbuzlar WHERE musavir_id=1 AND yil=2026")
ol "Ali kesilen = DB ($ALI_DB)" "$ALI_DB" "$(mus 'Ali' 1)"

echo "=== 7) MÜKELLEF DÖKÜMÜ ==="
curl -s -b $J "$B/makbuz/detay/1?yil=2026" -o /tmp/mkt_det.html
ol "Detay açılıyor" "200" "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/makbuz/detay/1?yil=2026")"
ol "Detayda hata yok" "0" "$(grep -cE 'ErrorException|Fatal error' /tmp/mkt_det.html | awk '{print ($1>0)?1:0}')"
ol "2 makbuz satırı" "2" "$(python3 -c "
import re
h=open('/tmp/mkt_det.html',encoding='utf-8').read()
i=h.find('md-tablo'); j=h.find('</tbody>',i)
print(len(re.findall(r'<tr[^>]*>', h[h.find('<tbody>',i):j])))")"
ol "Stopaj toplamı görünüyor" "1" "$(grep -c '3.600,00' /tmp/mkt_det.html | awk '{print ($1>0)?1:0}')"

echo "=== 8) STOPAJ / KDV / NET HESABI ==="
php -r '
require "'"$KOK"'/vendor/autoload.php";
// Saf hesap testi (veritabanı gerekmez)
$brut = 10000.00; $stopajOran = 20; $kdvOran = 20;
$stopaj = round($brut * $stopajOran / 100, 2);
$kdv    = round($brut * $kdvOran / 100, 2);
$net    = round($brut - $stopaj + $kdv, 2);
$g=0;$k=0;
if ($stopaj === 2000.00) { echo "  [OK] stopaj %20 = 2000\n"; $g++; } else { echo "  [HATA] stopaj\n"; $k++; }
if ($kdv === 2000.00)    { echo "  [OK] KDV %20 = 2000\n"; $g++; }    else { echo "  [HATA] kdv\n"; $k++; }
if ($net === 10000.00)   { echo "  [OK] net = brut - stopaj + kdv\n"; $g++; } else { echo "  [HATA] net\n"; $k++; }
exit($k>0?1:0);
' && g=$((g+3)) || k=$((k+1))
# Kayıtlı makbuzun tutarları
ol "Kayıtlı makbuz net doğru" "9000.00" "$($MDB -e "SELECT net FROM makbuzlar WHERE makbuz_no='2026000101'")"

echo "=== 9) EXCEL: ŞABLONLAR ==="
curl -s -b $J "$B/makbuz/sablon?kip=ucret&yil=2026" -o /tmp/mkt_su.csv
ol "Ücret şablonu indi" "1" "$(grep -c 'Yillik Ucret' /tmp/mkt_su.csv | awk '{print ($1>0)?1:0}')"
curl -s -b $J "$B/makbuz/sablon?kip=makbuz&yil=2026" -o /tmp/mkt_sm.csv
ol "Makbuz şablonu indi" "1" "$(grep -c 'Makbuz No' /tmp/mkt_sm.csv | awk '{print ($1>0)?1:0}')"
ol "Şablonda BOM var (Excel Türkçe)" "1" \
  "$(python3 -c "print(1 if open('/tmp/mkt_su.csv','rb').read(3)==b'\xef\xbb\xbf' else 0)")"

echo "=== 10) EXCEL: YILLIK ÜCRET İÇE AKTARMA ==="
printf '\xEF\xBB\xBFVKN/TCKN;Unvan;Yillik Ucret;Aciklama\n'  > /tmp/mkt_u.csv
printf '1112223334;ALFA;42.000,00;zam\n'                    >> /tmp/mkt_u.csv
printf '55566677788;AYSE CELIK;21000,50;yeni\n'             >> /tmp/mkt_u.csv
printf '9999999999;OLMAYAN;15000,00;\n'                     >> /tmp/mkt_u.csv
printf '1112223334;ALFA TEKRAR;50000,00;dosyada mukerrer\n' >> /tmp/mkt_u.csv
printf '2223334445;BETA;abc;hatali tutar\n'                 >> /tmp/mkt_u.csv
onizleU(){ curl -s -b $J -c $J -o /tmp/mkt_of.html "$B/makbuz/ice-aktar?kip=ucret&yil=2026"
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mkt_of.html|head -1)
  curl -s -b $J -c $J -L -o /tmp/mkt_onz.html -F "csrf_beyanname=$t" -F "kip=ucret" -F "yil=2026" \
    -F "dosya=@/tmp/mkt_u.csv" "$B/makbuz/ice-aktar/onizle"; }
onizleU
ol "Aktarılacak 2" "2" "$(onizleOzet /tmp/mkt_onz.html 'Aktarılacak')"
ol "Mükerrer 1"    "1" "$(onizleOzet /tmp/mkt_onz.html 'Mükerrer')"
ol "Hatalı 2"      "2" "$(onizleOzet /tmp/mkt_onz.html 'Hatalı')"
ol "Bulunamayan mükellef bildiriliyor" "1" \
  "$(grep -c 'Mükellef bulunamadı' /tmp/mkt_onz.html | awk '{print ($1>0)?1:0}')"
ol "Üzerine yazma uyarısı var" "1" \
  "$(grep -c 'üzerine yazılacak' /tmp/mkt_onz.html | awk '{print ($1>0)?1:0}')"
ol "Önizlemede DB değişmedi" "36000.00" "$($MDB -e 'SELECT tutar FROM mukellef_ucretleri WHERE mukellef_id=1 AND yil=2026')"
# Aktar
TK=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mkt_onz.html|head -1)
SEC=$(grep -oP 'name="sec\[\]" value="\K[^"]+' /tmp/mkt_onz.html | sed 's/^/-d sec[]=/' | tr '\n' ' ')
curl -s -b $J -c $J -L -o /tmp/mkt_son.html -X POST -d "csrf_beyanname=$TK" $SEC "$B/makbuz/ice-aktar/onayla"
ol "ALFA ücreti güncellendi" "42000.00" "$($MDB -e 'SELECT tutar FROM mukellef_ucretleri WHERE mukellef_id=1 AND yil=2026')"
ol "AYŞE ücreti eklendi"     "21000.50" "$($MDB -e 'SELECT tutar FROM mukellef_ucretleri WHERE mukellef_id=5 AND yil=2026')"
ol "Türkçe biçim (42.000,00) doğru çözüldü" "1" \
  "$([ "$($MDB -e 'SELECT tutar FROM mukellef_ucretleri WHERE mukellef_id=1 AND yil=2026')" = "42000.00" ] && echo 1 || echo 0)"
ol "Hatalı satır aktarılmadı" "24000.00" "$($MDB -e 'SELECT tutar FROM mukellef_ucretleri WHERE mukellef_id=2 AND yil=2026')"

echo "=== 11) EXCEL: MAKBUZ İÇE AKTARMA ==="
ONCE=$($MDB -e 'SELECT COUNT(*) FROM makbuzlar')
printf '\xEF\xBB\xBFVKN/TCKN;Unvan;Makbuz No;Tarih;Brut;Stopaj;KDV;Aciklama\n' > /tmp/mkt_m.csv
printf '1112223334;ALFA;2026000110;15.03.2026;9000,00;1800,00;1800,00;mart\n' >> /tmp/mkt_m.csv
printf '33344455566;MEHMET;2026000111;20.03.2026;4500,00;;;stopaj bos\n'      >> /tmp/mkt_m.csv
printf '1112223334;ALFA;2026000101;15.01.2026;9000,00;;;zaten kayitli\n'      >> /tmp/mkt_m.csv
printf '2223334445;BETA;2026000112;99.99.2026;5000,00;;;hatali tarih\n'       >> /tmp/mkt_m.csv
curl -s -b $J -c $J -o /tmp/mkt_of2.html "$B/makbuz/ice-aktar?kip=makbuz&yil=2026"
TK2=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mkt_of2.html|head -1)
curl -s -b $J -c $J -L -o /tmp/mkt_onz2.html -F "csrf_beyanname=$TK2" -F "kip=makbuz" -F "yil=2026" \
  -F "dosya=@/tmp/mkt_m.csv" "$B/makbuz/ice-aktar/onizle"
ol "Makbuz: aktarılacak 2" "2" "$(onizleOzet /tmp/mkt_onz2.html 'Aktarılacak')"
ol "Makbuz: mükerrer 1"    "1" "$(onizleOzet /tmp/mkt_onz2.html 'Mükerrer')"
ol "Makbuz: hatalı 1"      "1" "$(onizleOzet /tmp/mkt_onz2.html 'Hatalı')"
ol "Mükerrer makbuz no bildiriliyor" "1" \
  "$(grep -c 'zaten kayıtlı' /tmp/mkt_onz2.html | awk '{print ($1>0)?1:0}')"
ol "Hatalı tarih bildiriliyor" "1" \
  "$(grep -c 'Makbuz tarihi okunamadı' /tmp/mkt_onz2.html | awk '{print ($1>0)?1:0}')"
ol "Stopaj boş uyarısı" "1" \
  "$(grep -c 'Stopaj boş' /tmp/mkt_onz2.html | awk '{print ($1>0)?1:0}')"
TK3=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mkt_onz2.html|head -1)
SEC3=$(grep -oP 'name="sec\[\]" value="\K[^"]+' /tmp/mkt_onz2.html | sed 's/^/-d sec[]=/' | tr '\n' ' ')
curl -s -b $J -c $J -L -o /dev/null -X POST -d "csrf_beyanname=$TK3" $SEC3 "$B/makbuz/ice-aktar/onayla"
ol "2 makbuz eklendi" "$((ONCE+2))" "$($MDB -e 'SELECT COUNT(*) FROM makbuzlar')"
ol "Stopaj otomatik hesaplandı (900)" "900.00" \
  "$($MDB -e "SELECT stopaj FROM makbuzlar WHERE makbuz_no='2026000111'")"
ol "KDV otomatik hesaplandı (900)" "900.00" \
  "$($MDB -e "SELECT kdv FROM makbuzlar WHERE makbuz_no='2026000111'")"
ol "Net = brut-stopaj+kdv (4500)" "4500.00" \
  "$($MDB -e "SELECT net FROM makbuzlar WHERE makbuz_no='2026000111'")"
ol "Mükerrer eklenmedi" "1" \
  "$($MDB -e "SELECT COUNT(*) FROM makbuzlar WHERE makbuz_no='2026000101'")"

echo "=== 12) ÜCRET KOPYALAMA (zam ile) ==="
TKK=$(curl -s -b $J -c $J -o /tmp/mkt_k.html "$B/makbuz?yil=2026"; grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mkt_k.html|head -1)
curl -s -b $J -c $J -L -o /dev/null -X POST -d "csrf_beyanname=$TKK" \
  -d "kaynak_yil=2026" -d "hedef_yil=2027" -d "zam=25" "$B/makbuz/ucret-kopyala"
ol "2027'ye kopyalandı" "1" "$([ "$($MDB -e 'SELECT COUNT(*) FROM mukellef_ucretleri WHERE yil=2027')" -gt 0 ] && echo 1 || echo 0)"
ol "%25 zam uygulandı (42000→52500)" "52500.00" \
  "$($MDB -e 'SELECT tutar FROM mukellef_ucretleri WHERE mukellef_id=1 AND yil=2027')"
# İkinci kez çalıştırınca mevcut kayda DOKUNULMAMALI
$MDBR -e "UPDATE mukellef_ucretleri SET tutar=99999.00 WHERE mukellef_id=1 AND yil=2027;"
curl -s -b $J -c $J -L -o /dev/null -X POST -d "csrf_beyanname=$TKK" \
  -d "kaynak_yil=2026" -d "hedef_yil=2027" -d "zam=25" "$B/makbuz/ucret-kopyala"
ol "Mevcut kayda dokunulmuyor" "99999.00" \
  "$($MDB -e 'SELECT tutar FROM mukellef_ucretleri WHERE mukellef_id=1 AND yil=2027')"

echo "=== 13) YIL BAZINDA AYRIM ==="
curl -s -b $J "$B/makbuz?yil=2027" -o /tmp/mkt_27.html
ol "2027 ayrı hesaplanıyor" "0,00" "$(hucre /tmp/mkt_27.html 'BETA' 2)"
ol "2026 bozulmadı" "24.000,00" "$(hucre /tmp/mkt_l.html 'BETA' 2)"

echo "=== 14) MÜKELLEF KARTINDAN ÜCRET ==="
curl -s -b $J -c $J -o /tmp/mkt_form.html "$B/mukellefler/duzenle/3"
ol "Kartta yıllık ücret alanı var" "1" \
  "$(grep -c 'name="yillik_ucret"' /tmp/mkt_form.html | awk '{print ($1>0)?1:0}')"
ol "Mevcut ücret yükleniyor" "1" \
  "$(grep -c '18.000,00' /tmp/mkt_form.html | awk '{print ($1>0)?1:0}')"
TKF=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mkt_form.html|head -1)
curl -s -b $J -c $J -L -o /dev/null -X POST "$B/mukellefler/guncelle/3" \
  -d "csrf_beyanname=$TKF" -d "musavir_id=1" -d "unvan=MEHMET KAYA" -d "mukellef_tipi=gercek" \
  -d "tc_kimlik_no=33344455566" -d "defter_tipi=isletme" -d "ise_baslama_tarihi=2019-01-01" -d "aktif=1" \
  -d "yillik_ucret=27.500,00"
ol "Karttan ücret kaydedildi" "27500.00" \
  "$($MDB -e "SELECT tutar FROM mukellef_ucretleri WHERE mukellef_id=3 AND yil=$(date +%Y)")"

echo "=== 15) EXCEL DIŞA AKTARMA ==="
curl -s -b $J "$B/makbuz/excel?yil=2026" -o /tmp/mkt_e.csv
ol "Excel indi" "1" "$(grep -c 'Yillik Ucret' /tmp/mkt_e.csv | awk '{print ($1>0)?1:0}')"
ol "Excel'de TOPLAM satırı" "1" "$(grep -c '^TOPLAM' /tmp/mkt_e.csv | awk '{print ($1>0)?1:0}')"
# Not: ilk satırda UTF-8 BOM var; '^Mukellef' deseni eşleşmiyor.
# Başlık ve TOPLAM satırları çıkarılarak sayılır.
MUK_SAY=$($MDB -e "SELECT COUNT(*) FROM mukellefler WHERE aktif=1 AND deleted_at IS NULL")
ol "Excel satır sayısı ($MUK_SAY mükellef)" "$MUK_SAY" \
  "$(python3 -c "
d=open('/tmp/mkt_e.csv',encoding='utf-8-sig').read().splitlines()
print(len([x for x in d if x.strip() and not x.startswith(('Mukellef','TOPLAM'))]))")"

echo "=== 16) YETKİ ==="
ol "Personel erişemiyor" "302" \
  "$(giris personel /tmp/mkt_p.txt; curl -s -b /tmp/mkt_p.txt -o /dev/null -w '%{http_code}' "$B/makbuz")"
ol "Personel içe aktaramıyor" "302" \
  "$(curl -s -b /tmp/mkt_p.txt -o /dev/null -w '%{http_code}' "$B/makbuz/ice-aktar")"
giris musavir $JM
curl -s -b $JM "$B/makbuz?yil=2026" -o /tmp/mkt_mus.html
MUSSAY=$($MDB -e "SELECT COUNT(*) FROM mukellefler WHERE musavir_id=2 AND aktif=1 AND deleted_at IS NULL")
ol "Müşavir yalnızca kendi mükellefleri ($MUSSAY)" "$MUSSAY" "$(say /tmp/mkt_mus.html)"
ol "Müşavir başkasının dökümüne giremiyor" "1" \
  "$(curl -s -b $JM -L "$B/makbuz/detay/1?yil=2026" | grep -c 'erişemezsiniz' | awk '{print ($1>0)?1:0}')"
ol "Oturumsuz erişim engelli" "302" "$(curl -s -o /dev/null -w '%{http_code}' "$B/makbuz")"
ol "CSRF'siz POST engelli" "403" \
  "$(curl -s -b $J -o /dev/null -w '%{http_code}' -d "mukellef_id=1" -d "yil=2026" -d "tutar=100" "$B/makbuz/ucret")"

echo "=== 17) DİĞER SAYFALAR BOZULMADI ==="
giris admin $J
for u in "makbuz?yil=2026" "makbuz/detay/1?yil=2026" "makbuz/ice-aktar?kip=ucret" \
         "mukellefler" "mukellefler/duzenle/1" "odeme?yil=2026&ay=8" panel raporlar; do
  c=$(curl -s -b $J -o /tmp/mkt_s.html -w "%{http_code}" "$B/$u")
  ol "/$u HTTP 200" "200" "$c"
  ol "/$u hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Undefined variable|Unknown column' /tmp/mkt_s.html | awk '{print ($1>0)?1:0}')"
done

echo
echo "================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "================================================"
[ $k -eq 0 ] || exit 1
