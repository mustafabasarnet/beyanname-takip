#!/bin/bash
# =====================================================================
#  GELİR VERGİSİ HESABI + YAZDIRMA — REGRESYON TESTİ
#
#  Kapsam:
#   1. Şema (vergi_tarifeleri, musavir_gelir_gider) + idempotent migration
#   2. Hazır tarife verisi (2024-2026, ücret / ücret dışı)
#   3. Hasılat ve stopajın MAKBUZLARDAN otomatik gelmesi
#   4. Hesap zinciri: hasılat → kazanç → matrah → vergi → ödenecek/iade
#   5. %5 uyumlu mükellef indirimi (GVK mük.121) + üst sınır
#   6. Elle hasılat / elle stopaj geçersiz kılma
#   7. AJAX canlı hesap (kaydetmeden)
#   8. Türkçe para okuma: "400.000" = 400 bin (400 DEĞİL)
#   9. Tarife düzenleme + kopyalama
#  10. Makbuz yazdırma: liste / müşavir özeti / mükellef dökümü
#  11. Gelir vergisi yazdırma: tek müşavir + liste
#  12. Yetki: personel erişemez, müşavir kapsamı sızmaz
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor.
#  Test kendi verisini kurar.
#  Kullanım:  bash tests/gelir_vergisi_http_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
KOK="$(cd "$(dirname "$0")/.." && pwd)"
J=/tmp/gvt_a.txt      # admin oturumu
JM=/tmp/gvt_m.txt     # müşavir oturumu
JP=/tmp/gvt_p.txt     # personel oturumu
g=0; k=0

ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1));
      else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/gvt_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/gvt_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf_beyanname" value="\K[^"]+' | head -1; }

# Hesap dökümündeki bir hücreyi okur: hucre <dosya> <id>
hucre(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
m=re.search(r'id=\"%s\"[^>]*>(.*?)</td>'%sys.argv[2],h,re.S)
print(re.sub(r'<[^>]+>','',m.group(1)).strip() if m else 'YOK')
" "$1" "$2"; }

# JSON alanı okur
jal(){ python3 -c "
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
k=sys.argv[2].split('.')
for x in k: d=d[x]
print(d)
" "$1" "$2" 2>/dev/null || echo "JSON-HATA"; }

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE makbuzlar; TRUNCATE mukellef_ucretleri; TRUNCATE musavir_gelir_gider;
TRUNCATE musavir_kdv;
TRUNCATE musavir_indirim_kalem;
TRUNCATE musavir_aylik_gider;
DELETE FROM mukellefler; DELETE FROM kullanici_musavirleri;
DELETE FROM kullanicilar; DELETE FROM musavirler;
SET FOREIGN_KEY_CHECKS=1;
ALTER TABLE musavirler AUTO_INCREMENT=1; ALTER TABLE mukellefler AUTO_INCREMENT=1;
ALTER TABLE makbuzlar AUTO_INCREMENT=1; ALTER TABLE kullanicilar AUTO_INCREMENT=1;

INSERT INTO musavirler (id,unvan,ad_soyad,buro_adi,tc_kimlik,renk,aktif) VALUES
 (1,'SMMM','Ali Yılmaz','Yılmaz Mali Müşavirlik','11111111111','#2563eb',1),
 (2,'SMMM','Veli Demir','Demir Danışmanlık','22222222222','#16a34a',1);

INSERT INTO kullanicilar (id,kullanici_adi,ad_soyad,eposta,sifre,rol,musavir_id,aktif) VALUES
 (1,'admin','Yönetici','admin@t.local','$HASH','admin',NULL,1),
 (2,'personel','Personel Ayşe','personel@t.local','$HASH','personel',1,1),
 (3,'musavir','Müşavir Ali','musavir@t.local','$HASH','musavir',1,1),
 (4,'fatma','Fatma Kaya','fatma@t.local','$HASH','personel',1,1);
INSERT INTO kullanici_musavirleri (kullanici_id,musavir_id) VALUES (3,1);

INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','ALFA TEKSTİL LTD ŞTİ','tuzel','1111111111',NULL,'bilanco','2019-01-01',1),
 (2,1,'M002','BETA GIDA A.Ş.','tuzel','2222222222',NULL,'bilanco','2019-01-01',1),
 (3,1,'M003','CEM ÖZKAN','gercek',NULL,'33333333333','isletme','2019-01-01',1),
 (4,2,'M004','DELTA İNŞAAT LTD','tuzel','4444444444',NULL,'bilanco','2019-01-01',1),
 (5,2,'M005','EMRE ŞAHİN','gercek',NULL,'55555555555','isletme','2019-01-01',1);

-- Müşavir 1: brüt 500.000, stopaj 100.000 (3'ü tahsil: 250.000 / 50.000)
INSERT INTO makbuzlar (mukellef_id,musavir_id,yil,ay,makbuz_no,tarih,brut,stopaj,kdv,net,tahsil_edildi,tahsil_tarihi) VALUES
 (1,1,2026,1,'A-001','2026-01-15',100000,20000,20000,100000,1,'2026-01-20'),
 (1,1,2026,4,'A-002','2026-04-15',100000,20000,20000,100000,0,NULL),
 (2,1,2026,2,'A-003','2026-02-10', 80000,16000,16000, 80000,1,'2026-02-15'),
 (2,1,2026,5,'A-004','2026-05-10', 80000,16000,16000, 80000,0,NULL),
 (3,1,2026,3,'A-005','2026-03-05', 70000,14000,14000, 70000,1,'2026-03-10'),
 (3,1,2026,6,'A-006','2026-06-05', 70000,14000,14000, 70000,0,NULL);

-- Müşavir 2: brüt 150.000, stopaj 30.000
INSERT INTO makbuzlar (mukellef_id,musavir_id,yil,ay,makbuz_no,tarih,brut,stopaj,kdv,net,tahsil_edildi,tahsil_tarihi) VALUES
 (4,2,2026,1,'V-001','2026-01-20',100000,20000,20000,100000,1,'2026-01-25'),
 (5,2,2026,2,'V-002','2026-02-20', 50000,10000,10000, 50000,0,NULL);

INSERT INTO mukellef_ucretleri (mukellef_id,yil,tutar) VALUES
 (1,2026,120000),(2,2026,96000),(3,2026,48000),(4,2026,144000),(5,2026,60000);

UPDATE ayarlar SET deger='tum' WHERE anahtar='gv_hasilat_kaynagi';
UPDATE ayarlar SET deger='5' WHERE anahtar='gv_uyumlu_oran';
UPDATE ayarlar SET deger='12000000' WHERE anahtar='gv_uyumlu_ust_sinir';
UPDATE ayarlar SET deger='15' WHERE anahtar='gv_sigorta_oran';
UPDATE ayarlar SET deger='10' WHERE anahtar='gv_egitim_saglik_oran';
" >/dev/null 2>&1
}

echo "=== HAZIRLIK ==="
HASH=$(php -r 'echo password_hash("Test1234", PASSWORD_DEFAULT);')
veriKur
giris admin    $J
giris musavir  $JM
giris personel $JP
curl -s -b $J -o /tmp/gvt_p0.html -w "%{http_code}" $B/panel >/tmp/gvt_c; ol "admin girişi" "200" "$(cat /tmp/gvt_c)"

# =====================================================================
echo; echo "=== 1) ŞEMA VE MIGRATION ==="
# =====================================================================
ol "vergi_tarifeleri tablosu var" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='vergi_tarifeleri';")"
ol "musavir_gelir_gider tablosu var" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='musavir_gelir_gider';")"
ol "musavir+yil benzersiz kısıtı var" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='musavir_gelir_gider' AND index_name='uq_musavir_yil' AND seq_in_index=1;")"
ol "yil+ucret_mi+sira benzersiz kısıtı var" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='vergi_tarifeleri' AND index_name='uq_tarife_dilim' AND seq_in_index=1;")"

# Migration idempotent mi? (İKİNCİ kez çalıştırılınca hata vermemeli)
$MDBR < "$KOK/database/migration_gelir_vergisi.sql" >/dev/null 2>/tmp/gvt_mig.err
ol "migration 2. kez çalışır (idempotent)" "0" "$(wc -c </tmp/gvt_mig.err | tr -d ' ')"

for a in gv_uyumlu_oran gv_uyumlu_ust_sinir gv_hasilat_kaynagi; do
  ol "ayar $a tanımlı" "1" "$($MDB -e "SELECT COUNT(*) FROM ayarlar WHERE anahtar='$a';")"
done

# =====================================================================
echo; echo "=== 2) HAZIR TARİFE VERİSİ ==="
# =====================================================================
for y in 2024 2025 2026; do
  ol "$y ücret dışı 5 dilim" "5" "$($MDB -e "SELECT COUNT(*) FROM vergi_tarifeleri WHERE yil=$y AND ucret_mi=0;")"
  ol "$y ücret 5 dilim"      "5" "$($MDB -e "SELECT COUNT(*) FROM vergi_tarifeleri WHERE yil=$y AND ucret_mi=1;")"
done
ol "2026 ücret dışı 3. dilim tavanı 1.000.000" "1000000.00" \
   "$($MDB -e "SELECT tavan FROM vergi_tarifeleri WHERE yil=2026 AND ucret_mi=0 AND sira=3;")"
ol "2026 ÜCRET 3. dilim tavanı 1.500.000 (farklı)" "1500000.00" \
   "$($MDB -e "SELECT tavan FROM vergi_tarifeleri WHERE yil=2026 AND ucret_mi=1 AND sira=3;")"
ol "2026 son dilim tavanı NULL (sınırsız)" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM vergi_tarifeleri WHERE yil=2026 AND ucret_mi=0 AND sira=5 AND tavan IS NULL;")"
ol "2026 son dilim oranı %40" "40.00" \
   "$($MDB -e "SELECT oran FROM vergi_tarifeleri WHERE yil=2026 AND ucret_mi=0 AND sira=5;")"

# =====================================================================
echo; echo "=== 3) SAYFALAR AÇILIYOR ==="
# =====================================================================
for u in "gelir-vergisi" "gelir-vergisi/detay/1?yil=2026" "gelir-vergisi/tarife?yil=2026" \
         "gelir-vergisi/yazdir/1?yil=2026" "gelir-vergisi/liste-yazdir?yil=2026" \
         "makbuz/yazdir?yil=2026" "makbuz/yazdir?yil=2026&bicim=ozet" "makbuz/detay-yazdir/1?yil=2026"; do
  c=$(curl -s -b $J -o /tmp/gvt_s.html -w "%{http_code}" "$B/$u")
  ol "GET /$u" "200" "$c"
  ol "  /$u hatasız" "0" "$(grep -ciE 'Whoops|Fatal error|Uncaught' /tmp/gvt_s.html)"
done

# =====================================================================
echo; echo "=== 4) HASILAT VE STOPAJ MAKBUZLARDAN GELİYOR ==="
# =====================================================================
curl -s -b $J -o /tmp/gvt_d1.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "müşavir 1 hasılatı = 500.000 (makbuz brütü)" "500.000,00" "$(hucre /tmp/gvt_d1.html c-hasilat)"
ol "müşavir 1 stopajı = 100.000 (makbuzlardan)"  "100.000,00" "$(hucre /tmp/gvt_d1.html c-stopaj)"
ol "gider girilmeden 0,00"                        "0,00"       "$(hucre /tmp/gvt_d1.html c-gider)"
ol "gider yokken matrah = hasılat"                "500.000,00" "$(hucre /tmp/gvt_d1.html c-matrah)"

curl -s -b $J -o /tmp/gvt_d2.html "$B/gelir-vergisi/detay/2?yil=2026"
ol "müşavir 2 hasılatı = 150.000" "150.000,00" "$(hucre /tmp/gvt_d2.html c-hasilat)"
ol "müşavir 2 stopajı = 30.000"   "30.000,00"  "$(hucre /tmp/gvt_d2.html c-stopaj)"

# =====================================================================
echo; echo "=== 5) HESAP ZİNCİRİ (gider kaydı) ==="
# =====================================================================
# Hasılat 500.000 − gider 200.000 = kazanç 300.000
# − Bağkur 30.000 → matrah 270.000
# vergi = 28.500 + (270.000−190.000)×%20 = 44.500
# stopaj 100.000 → 100.000 − 44.500 = 55.500 İADE
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=200.000,00" -d "bagkur=30.000" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"

curl -s -b $J -o /tmp/gvt_h1.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "kazanç = 500.000 − 200.000"            "300.000,00" "$(hucre /tmp/gvt_h1.html c-kazanc)"
ol "indirim toplamı = 30.000 (Bağkur)"     "30.000,00"  "$(hucre /tmp/gvt_h1.html c-indirim_toplam)"
ol "matrah = 270.000"                      "270.000,00" "$(hucre /tmp/gvt_h1.html c-matrah)"
ol "vergi = 28.500 + 80.000×%20 = 44.500"  "44.500,00"  "$(hucre /tmp/gvt_h1.html c-vergi)"
ol "ödenmesi gereken = 44.500"             "44.500,00"  "$(hucre /tmp/gvt_h1.html c-odenmesi_gereken)"
ol "sonuç = 55.500 iade"                   "55.500,00"  "$(hucre /tmp/gvt_h1.html c-sonuc-tutar)"
# 18. güncellemede etiket "YIL İÇİ VERGİ YÜKÜ — İADE ALACAKSINIZ" oldu.
# Metin 2 kez geçer: sunucunun bastığı satır + AJAX'ın kullandığı JS dizesi.
ol "sonuç etiketi İADE (html+js)"          "2" \
   "$(grep -c 'VERGİ YÜKÜ — İADE ALACAKSINIZ' /tmp/gvt_h1.html | head -1)"
ol "gider veritabanına yazıldı"            "200000.00" \
   "$($MDB -e "SELECT gider FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"

# Aynı müşavir + yıl İKİNCİ kez kaydedilince MÜKERRER satır olmamalı
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=210.000" -d "bagkur=30.000" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"
ol "tekrar kayıtta mükerrer satır yok" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"
ol "tekrar kayıtta gider güncellendi" "210000.00" \
   "$($MDB -e "SELECT gider FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"

# =====================================================================
echo; echo "=== 6) TÜRKÇE PARA OKUMA (400.000 = 400 bin, 400 DEĞİL) ==="
# =====================================================================
# GERÇEK KUSUR: eski paraCoz() virgül yoksa noktayı ondalık sayıyordu;
# "400.000" girildiğinde hesap 400 TL ile yapılıyordu.
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j1.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=400.000" -d "bagkur=0" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "'400.000' → 400.000,00 (binlik nokta)" "400.000,00" "$(jal /tmp/gvt_j1.json bicimli.gider)"
ol "'400.000' ile kazanç 100.000"          "100.000,00" "$(jal /tmp/gvt_j1.json bicimli.kazanc)"

T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j2.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=1.234.567,89" -d "bagkur=0" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "'1.234.567,89' tam okunur" "1.234.567,89" "$(jal /tmp/gvt_j2.json bicimli.gider)"

T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j3.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=1234,50" -d "bagkur=0" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "'1234,50' ondalık olarak okunur" "1.234,50" "$(jal /tmp/gvt_j3.json bicimli.gider)"

# =====================================================================
echo; echo "=== 7) AJAX CANLI HESAP (kaydetmeden) ==="
# =====================================================================
# Hasılat elle 2.000.000, gider 400.000 → matrah 1.600.000
# vergi = 232.500 + 600.000×%35 = 442.500
# %5 indirim = 22.125 → ödenmesi gereken 420.375
# stopaj 400.000 + diğer mahsup 20.000 = 420.000 → ödenecek 375
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j4.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hasilat_elle=2.000.000" -d "gider=400.000" -d "uyumlu_indirim=1" \
  -d "stopaj_elle=400.000" -d "diger_mahsup=20.000" -d "bagkur=0" \
  "$B/gelir-vergisi/hesapla"
ol "AJAX durum true"                  "True"         "$(jal /tmp/gvt_j4.json durum)"
ol "AJAX matrah 1.600.000"            "1.600.000,00" "$(jal /tmp/gvt_j4.json bicimli.matrah)"
ol "AJAX vergi 442.500"               "442.500,00"   "$(jal /tmp/gvt_j4.json bicimli.vergi)"
ol "AJAX %5 indirim 22.125"           "22.125,00"    "$(jal /tmp/gvt_j4.json bicimli.uyumlu)"
ol "AJAX ödenmesi gereken 420.375"    "420.375,00"   "$(jal /tmp/gvt_j4.json bicimli.odenmesi_gereken)"
ol "AJAX mahsup toplamı 420.000"      "420.000,00"   "$(jal /tmp/gvt_j4.json bicimli.mahsup_toplam)"
ol "AJAX ödenecek 375"                "375,00"       "$(jal /tmp/gvt_j4.json bicimli.odenecek)"
ol "AJAX 4. dilim"                    "4"            "$(jal /tmp/gvt_j4.json dilim_no)"
ol "AJAX elle hasılat geçerli"        "2.000.000,00" "$(jal /tmp/gvt_j4.json bicimli.hasilat)"
ol "AJAX elle stopaj geçerli"         "400.000,00"   "$(jal /tmp/gvt_j4.json bicimli.stopaj)"
# AJAX kaydetmemeli
ol "AJAX veritabanını DEĞİŞTİRMEZ"    "210000.00" \
   "$($MDB -e "SELECT gider FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"

# =====================================================================
echo; echo "=== 8) SINIR DURUMLARI ==="
# =====================================================================
# Gider hasılatı aşarsa matrah 0, stopajın tamamı iade
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j5.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=700.000" -d "bagkur=0" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "zararda matrah 0"          "0,00"        "$(jal /tmp/gvt_j5.json bicimli.matrah)"
ol "zararda vergi 0"           "0,00"        "$(jal /tmp/gvt_j5.json bicimli.vergi)"
ol "zararda stopaj tamamı iade" "100.000,00" "$(jal /tmp/gvt_j5.json bicimli.iade)"

# İndirimler kazancı aşarsa matrah 0
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j6.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=400.000" -d "bagkur=160.000" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "indirim kazancı aşınca matrah 0" "0,00" "$(jal /tmp/gvt_j6.json bicimli.matrah)"

# %5 indirim üst sınırı
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j7.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hasilat_elle=700.000.000" -d "gider=0" -d "uyumlu_indirim=1" \
  -d "bagkur=0" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "%5 indirim 12.000.000'da durur" "12.000.000,00" "$(jal /tmp/gvt_j7.json bicimli.uyumlu)"

# Tarifesiz yıl → vergi 0 ama çökme yok
c=$(curl -s -b $J -o /tmp/gvt_bos.html -w "%{http_code}" "$B/gelir-vergisi/detay/1?yil=2019")
ol "tarifesiz yıl açılır (çökmez)" "200" "$c"
ol "tarifesiz yıl uyarısı gösterilir" "1" \
   "$(grep -c 'tarifesi tanımlı değil' /tmp/gvt_bos.html | head -1)"

# Dilim sınırı: matrah tam 190.000 → vergi tam 28.500
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j8.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hasilat_elle=190.000" -d "gider=0" -d "bagkur=0" \
  -d "diger_mahsup=0" -d "stopaj_elle=0" \
  "$B/gelir-vergisi/hesapla"
ol "matrah 190.000 → vergi 28.500 (1. dilim tavanı)" "28.500,00" "$(jal /tmp/gvt_j8.json bicimli.vergi)"
ol "190.000 tam tavanda 1. dilim" "1" "$(jal /tmp/gvt_j8.json dilim_no)"

# =====================================================================
echo; echo "=== 9) HASILAT KAYNAĞI AYARI (tahsil esası) ==="
# =====================================================================
$MDBR -e "UPDATE ayarlar SET deger='tahsil' WHERE anahtar='gv_hasilat_kaynagi';" >/dev/null
curl -s -b $J -o /tmp/gvt_th.html "$B/gelir-vergisi/detay/1?yil=2026"
# Tahsil edilen: 100.000 + 80.000 + 70.000 = 250.000, stopaj 50.000
ol "tahsil esasında hasılat 250.000" "250.000,00" "$(hucre /tmp/gvt_th.html c-hasilat)"
ol "tahsil esasında stopaj 50.000"   "50.000,00"  "$(hucre /tmp/gvt_th.html c-stopaj)"
$MDBR -e "UPDATE ayarlar SET deger='tum' WHERE anahtar='gv_hasilat_kaynagi';" >/dev/null
curl -s -b $J -o /tmp/gvt_tt.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "ayar geri alınınca hasılat 500.000" "500.000,00" "$(hucre /tmp/gvt_tt.html c-hasilat)"

# =====================================================================
echo; echo "=== 10) TARİFE DÜZENLEME VE KOPYALAMA ==="
# =====================================================================
T=$(jeton $J "$B/gelir-vergisi/tarife?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/tarife/kopyala" \
  -d "csrf_beyanname=$T" -d "kaynak_yil=2026" -d "hedef_yil=2027" -d "oran=10"
ol "2027'ye kopyalandı (10 satır)" "10" "$($MDB -e "SELECT COUNT(*) FROM vergi_tarifeleri WHERE yil=2027;")"
ol "kopyada %10 artış (190.000→209.000)" "209000.00" \
   "$($MDB -e "SELECT tavan FROM vergi_tarifeleri WHERE yil=2027 AND ucret_mi=0 AND sira=1;")"
ol "kopyada oranlar değişmez" "40.00" \
   "$($MDB -e "SELECT oran FROM vergi_tarifeleri WHERE yil=2027 AND ucret_mi=0 AND sira=5;")"

# Mevcut tarifenin üzerine kopyalama YAPILMAMALI
T=$(jeton $J "$B/gelir-vergisi/tarife?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/tarife/kopyala" \
  -d "csrf_beyanname=$T" -d "kaynak_yil=2025" -d "hedef_yil=2027" -d "oran=50"
ol "dolu yıla kopyalama engellenir" "209000.00" \
   "$($MDB -e "SELECT tavan FROM vergi_tarifeleri WHERE yil=2027 AND ucret_mi=0 AND sira=1;")"

# Elle dilim kaydetme
T=$(jeton $J "$B/gelir-vergisi/tarife?yil=2027")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/tarife/kaydet" \
  -d "csrf_beyanname=$T" -d "yil=2027" -d "tip=ucret_disi" \
  -d "dilim[1][taban]=0"       -d "dilim[1][tavan]=200.000" -d "dilim[1][sabit_vergi]=0"      -d "dilim[1][oran]=15" \
  -d "dilim[2][taban]=200.000" -d "dilim[2][tavan]="        -d "dilim[2][sabit_vergi]=30.000" -d "dilim[2][oran]=25"
ol "elle 2 dilim kaydedildi" "2" "$($MDB -e "SELECT COUNT(*) FROM vergi_tarifeleri WHERE yil=2027 AND ucret_mi=0;")"
ol "elle dilim tavanı 200.000 (binlik doğru)" "200000.00" \
   "$($MDB -e "SELECT tavan FROM vergi_tarifeleri WHERE yil=2027 AND ucret_mi=0 AND sira=1;")"
ol "son dilim tavanı NULL yapıldı" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM vergi_tarifeleri WHERE yil=2027 AND ucret_mi=0 AND sira=2 AND tavan IS NULL;")"
ol "ücret tarifesi etkilenmedi" "5" "$($MDB -e "SELECT COUNT(*) FROM vergi_tarifeleri WHERE yil=2027 AND ucret_mi=1;")"

# Yeni tarifeyle hesap: matrah 300.000 → 30.000 + 100.000×%25 = 55.000
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2027")
curl -s -b $J -c $J -o /tmp/gvt_j9.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2027" \
  -d "hasilat_elle=300.000" -d "gider=0" -d "bagkur=0" \
  -d "diger_mahsup=0" -d "stopaj_elle=0" \
  "$B/gelir-vergisi/hesapla"
ol "elle girilen tarife hesaba yansır (55.000)" "55.000,00" "$(jal /tmp/gvt_j9.json bicimli.vergi)"
$MDBR -e "DELETE FROM vergi_tarifeleri WHERE yil=2027;" >/dev/null

# =====================================================================
echo; echo "=== 11) MAKBUZ YAZDIRMA ==="
# =====================================================================
curl -s -b $J -o /tmp/gvt_y1.html "$B/makbuz/yazdir?yil=2026"
# NOT: mükellef adları <td class="kalin"> içinde; satır sayısı bu şekilde güvenilir sayılır.
ol "yazdır: 5 mükellef satırı" "5" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_y1.html',encoding='utf-8').read()
tb=re.search(r'<tbody>(.*?)</tbody>',h,re.S).group(1)
print(len(re.findall(r'<tr[^>]*>',tb)))")"
# Toplamlar İKİ yerde görünür: üstteki özet kutusu + tablo tfoot'u. Bu beklenen davranış.
ol "yazdır: sözleşme toplamı 468.000 (özet+tfoot)" "2" "$(grep -c '468.000,00' /tmp/gvt_y1.html | head -1)"
ol "yazdır: kesilen toplamı 650.000 (özet+tfoot)"  "2" "$(grep -c '650.000,00' /tmp/gvt_y1.html | head -1)"
ol "yazdır: imza bloğu var"           "1" "$(grep -c 'Hazırlayan' /tmp/gvt_y1.html | head -1)"
ol "yazdır: stil gömülü (harici css yok)" "0" "$(grep -c 'stil.css' /tmp/gvt_y1.html | head -1)"
# 'yazdirma-gizle' 2 kez geçer: @media print kuralı + araç çubuğu div'i.
ol "yazdır: araç çubuğu @media print ile gizlenir" "2" "$(grep -c 'yazdirma-gizle' /tmp/gvt_y1.html | head -1)"

curl -s -b $J -o /tmp/gvt_y2.html "$B/makbuz/yazdir?yil=2026&bicim=ozet"
ol "özet biçimi: 2 müşavir satırı" "2" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_y2.html',encoding='utf-8').read()
print(len(re.findall(r'<td class=\"kalin\">(?:Ali Yılmaz|Veli Demir)</td>', h)))")"
ol "özet: müşavir 1 kesilen 500.000" "1" "$(grep -c '500.000,00' /tmp/gvt_y2.html | head -1)"
ol "özet: stopaj sütunu var"         "1" "$(grep -ci '>Stopaj<' /tmp/gvt_y2.html | head -1)"

# Filtre çıktıya taşınıyor mu?
curl -s -b $J -o /tmp/gvt_y3.html "$B/makbuz/yazdir?yil=2026&musavir_id=2"
ol "müşavir filtresi çıktıya taşınır (2 satır)" "2" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_y3.html',encoding='utf-8').read()
print(len(re.findall(r'<td class=\"kalin\">(?:DELTA|EMRE)', h)))")"
ol "filtreli çıktıda ALFA yok" "0" "$(grep -c 'ALFA TEKSTİL' /tmp/gvt_y3.html | head -1)"

curl -s -b $J -o /tmp/gvt_y4.html "$B/makbuz/yazdir?yil=2026&durum=ASIM"
ol "durum filtresi çıktıya taşınır" "1" "$(grep -c 'Ücreti aşmış' /tmp/gvt_y4.html | head -1)"

# Mükellef makbuz dökümü
curl -s -b $J -o /tmp/gvt_y5.html "$B/makbuz/detay-yazdir/1?yil=2026"
# Ünvan hem <title> hem <h1> içinde geçer.
ol "döküm: ALFA başlığı (title+h1)" "2" "$(grep -c 'ALFA TEKSTİL' /tmp/gvt_y5.html | head -1)"
ol "döküm: 2 makbuz satırı" "2" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_y5.html',encoding='utf-8').read()
print(len(re.findall(r'A-00[12]', h)))")"
# 200.000,00: özet kutusu (kesilen) + tfoot brüt + tfoot net = 3 kez.
ol "döküm: brüt/net toplamı 200.000" "3" "$(grep -c '200.000,00' /tmp/gvt_y5.html | head -1)"

# =====================================================================
echo; echo "=== 12) GELİR VERGİSİ YAZDIRMA ==="
# =====================================================================
# Kayıtlı değer: gider 210.000, bağkur 30.000 → kazanç 290.000, matrah 260.000
# vergi = 28.500 + 70.000×%20 = 42.500 → stopaj 100.000 → iade 57.500
curl -s -b $J -o /tmp/gvt_gy.html "$B/gelir-vergisi/yazdir/1?yil=2026"
ol "gv yazdır: müşavir adı (title+başlık)" "2" "$(grep -c 'Ali Yılmaz' /tmp/gvt_gy.html | head -1)"
ol "gv yazdır: matrah 260.000"   "1" "$(grep -c '260.000,00' /tmp/gvt_gy.html | head -1)"
# 42.500,00 iki kez: "Hesaplanan Gelir Vergisi" ve "Ödenmesi Gereken" satırları (indirim yok).
ol "gv yazdır: vergi 42.500 (hesaplanan+ödenmesi gereken)" "2" "$(grep -c '42.500,00' /tmp/gvt_gy.html | head -1)"
# 57.500,00 iki kez: vergi yükü sonuç satırı + GV dengesi kırılım satırı.
ol "gv yazdır: iade 57.500 (sonuç+kırılım)" "2" "$(grep -c '57.500,00' /tmp/gvt_gy.html | head -1)"
# Başlıklar ekranda CSS text-transform ile büyür; kaynakta normal yazımlıdır.
ol "gv yazdır: dilim tablosu"    "1" "$(grep -c 'Dilim Dağılımı' /tmp/gvt_gy.html | head -1)"
ol "gv yazdır: aylık döküm"      "1" "$(grep -c 'Aylık Makbuz Dağılımı' /tmp/gvt_gy.html | head -1)"
ol "gv yazdır: yasal dipnot"     "1" "$(grep -c 'resmi beyan yerine geçmez' /tmp/gvt_gy.html | head -1)"
ol "gv yazdır: stil gömülü"      "0" "$(grep -c 'stil.css' /tmp/gvt_gy.html | head -1)"

curl -s -b $J -o /tmp/gvt_gl.html "$B/gelir-vergisi/liste-yazdir?yil=2026"
ol "gv liste yazdır: 2 müşavir" "2" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_gl.html',encoding='utf-8').read()
print(len(re.findall(r'<td class=\"kalin\">(?:Ali Yılmaz|Veli Demir)</td>', h)))")"
ol "gv liste: toplam satırı"    "1" "$(grep -c 'TOPLAM (2 müşavir)' /tmp/gvt_gl.html | head -1)"

# =====================================================================
echo; echo "=== 13) YETKİ ==="
# =====================================================================
c=$(curl -s -b $JP -o /tmp/gvt_p1.html -w "%{http_code}" "$B/gelir-vergisi")
ol "personel gelir vergisine giremez (yönlendirilir)" "1" \
   "$([ "$c" = "302" ] || [ "$c" = "303" ] || [ "$c" = "403" ] && echo 1 || echo 0)"
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" "$B/gelir-vergisi/detay/1?yil=2026")
ol "personel hesap ekranına giremez" "1" \
   "$([ "$c" = "302" ] || [ "$c" = "303" ] || [ "$c" = "403" ] && echo 1 || echo 0)"
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" "$B/makbuz/yazdir?yil=2026")
ol "personel makbuz yazdıramaz" "1" \
   "$([ "$c" = "302" ] || [ "$c" = "303" ] || [ "$c" = "403" ] && echo 1 || echo 0)"

# Müşavir (yalnız müşavir 1'e yetkili) başkasının hesabını göremez
curl -s -b $JM -o /tmp/gvt_m1.html -w "%{http_code}" "$B/gelir-vergisi/detay/2?yil=2026" >/tmp/gvt_c
ol "müşavir başka müşavirin hesabına giremez" "1" \
   "$([ "$(cat /tmp/gvt_c)" = "302" ] || [ "$(cat /tmp/gvt_c)" = "303" ] && echo 1 || echo 0)"
curl -s -b $JM -o /tmp/gvt_m2.html "$B/gelir-vergisi"
ol "müşavir listesinde yalnız kendi kaydı" "0" "$(grep -c 'Veli Demir' /tmp/gvt_m2.html | head -1)"
ol "müşavir listesinde kendisi var" "1" \
   "$([ "$(grep -c 'Ali Yılmaz' /tmp/gvt_m2.html)" -gt 0 ] && echo 1 || echo 0)"

# Müşavir başkasının hesabını KAYDEDEMEZ
T=$(jeton $JM "$B/gelir-vergisi")
curl -s -b $JM -c $JM -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=2" -d "yil=2026" \
  -d "gider=999.999" -d "bagkur=0" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"
ol "müşavir başkasının giderini yazamaz" "0" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_gelir_gider WHERE musavir_id=2 AND gider=999999;")"

# Tarife düzenleme yalnız admin
T=$(jeton $JM "$B/gelir-vergisi/tarife?yil=2026")
curl -s -b $JM -c $JM -o /dev/null -X POST "$B/gelir-vergisi/tarife/kaydet" \
  -d "csrf_beyanname=$T" -d "yil=2026" -d "tip=ucret_disi" \
  -d "dilim[1][taban]=0" -d "dilim[1][tavan]=1" -d "dilim[1][sabit_vergi]=0" -d "dilim[1][oran]=99"
ol "müşavir tarifeyi bozamaz" "5" "$($MDB -e "SELECT COUNT(*) FROM vergi_tarifeleri WHERE yil=2026 AND ucret_mi=0;")"

# =====================================================================
echo; echo "=== 14) MAKBUZ EKLENİNCE HASILAT KENDİLİĞİNDEN ARTAR ==="
# =====================================================================
$MDBR -e "INSERT INTO makbuzlar (mukellef_id,musavir_id,yil,ay,makbuz_no,tarih,brut,stopaj,kdv,net,tahsil_edildi)
          VALUES (1,1,2026,7,'A-007','2026-07-15',50000,10000,10000,50000,0);" >/dev/null
curl -s -b $J -o /tmp/gvt_yeni.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "yeni makbuzla hasılat 550.000" "550.000,00" "$(hucre /tmp/gvt_yeni.html c-hasilat)"
ol "yeni makbuzla stopaj 110.000"  "110.000,00" "$(hucre /tmp/gvt_yeni.html c-stopaj)"
ol "gider kaydı korundu (210.000)" "210.000,00" "$(hucre /tmp/gvt_yeni.html c-gider)"

# =====================================================================

# =====================================================================
echo; echo "=== 15) SINIRLI İNDİRİMLER (GVK md.89) ==="
# =====================================================================
# Hasılat 550.000 (7 makbuz), gider 250.000 → kazanç 300.000
#   sigorta tavanı = 300.000 × %15 = 45.000
#   eğitim  tavanı = 300.000 × %10 = 30.000
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_s1.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hasilat_elle=500.000" -d "gider=200.000" -d "bagkur=0" \
  -d "sigorta_primi=60.000" -d "egitim_saglik=20.000" -d "diger_mahsup=0" \
  "$B/gelir-vergisi/hesapla"
ol "sigorta tavanı kârın %15'i = 45.000"    "45.000,00" "$(jal /tmp/gvt_s1.json bicimli.sigorta_tavan)"
ol "eğitim tavanı kârın %10'u = 30.000"     "30.000,00" "$(jal /tmp/gvt_s1.json bicimli.egitim_tavan)"
ol "sigorta 60.000 talep → 45.000 indirildi" "45.000,00" "$(jal /tmp/gvt_s1.json bicimli.sigorta)"
ol "sigorta aşımı 15.000 raporlandı"         "15.000,00" "$(jal /tmp/gvt_s1.json bicimli.sigorta_asim)"
ol "eğitim 20.000 tavan altı → tamamı indi"  "20.000,00" "$(jal /tmp/gvt_s1.json bicimli.egitim)"
ol "eğitim aşımı yok"                        "0,00"      "$(jal /tmp/gvt_s1.json bicimli.egitim_asim)"
ol "indirim toplamı 65.000 (0+45.000+20.000)" "65.000,00" "$(jal /tmp/gvt_s1.json bicimli.indirim_toplam)"
ol "matrah 235.000 (300.000−65.000)"          "235.000,00" "$(jal /tmp/gvt_s1.json bicimli.matrah)"
# vergi = 28.500 + (235.000−190.000)×%20 = 37.500
ol "vergi 37.500"                             "37.500,00" "$(jal /tmp/gvt_s1.json bicimli.vergi)"

# Bağ-Kur SINIRSIZ olmalı — tavanı yok
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_s2.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hasilat_elle=500.000" -d "gider=200.000" -d "bagkur=250.000" \
  -d "sigorta_primi=0" -d "egitim_saglik=0" -d "diger_mahsup=0" \
  "$B/gelir-vergisi/hesapla"
ol "Bağ-Kur sınırsız (250.000 tamamı indi)" "250.000,00" "$(jal /tmp/gvt_s2.json bicimli.bagkur)"
ol "Bağ-Kur ile matrah 50.000"              "50.000,00"  "$(jal /tmp/gvt_s2.json bicimli.matrah)"

# Sınır tabanı KAZANÇ: gider artınca tavan da düşmeli
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_s3.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hasilat_elle=500.000" -d "gider=400.000" -d "bagkur=0" \
  -d "sigorta_primi=60.000" -d "egitim_saglik=60.000" -d "diger_mahsup=0" \
  "$B/gelir-vergisi/hesapla"
ol "gider artınca sigorta tavanı 15.000'e düştü" "15.000,00" "$(jal /tmp/gvt_s3.json bicimli.sigorta_tavan)"
ol "gider artınca eğitim tavanı 10.000'e düştü"  "10.000,00" "$(jal /tmp/gvt_s3.json bicimli.egitim_tavan)"
ol "tavan düşünce indirim de düştü (25.000)"     "25.000,00" "$(jal /tmp/gvt_s3.json bicimli.indirim_toplam)"

# Zararda tavan 0 olmalı (negatif kârda indirim yok)
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_s4.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hasilat_elle=100.000" -d "gider=300.000" -d "bagkur=0" \
  -d "sigorta_primi=50.000" -d "egitim_saglik=50.000" -d "diger_mahsup=0" \
  "$B/gelir-vergisi/hesapla"
ol "zararda sigorta tavanı 0" "0,00" "$(jal /tmp/gvt_s4.json bicimli.sigorta_tavan)"
ol "zararda eğitim tavanı 0"  "0,00" "$(jal /tmp/gvt_s4.json bicimli.egitim_tavan)"
ol "zararda indirim 0"        "0,00" "$(jal /tmp/gvt_s4.json bicimli.indirim_toplam)"

# Sınırlar KAYDEDİLMEZ, her hesapta yeniden uygulanır
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=200.000" -d "bagkur=0" -d "sigorta_primi=60.000" -d "egitim_saglik=20.000" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"
ol "TALEP edilen tutar ham saklanır (60.000)" "60000.00" \
   "$($MDB -e "SELECT sigorta_primi FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"
ol "eğitim harcaması saklandı (20.000)" "20000.00" \
   "$($MDB -e "SELECT egitim_saglik FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"

# =====================================================================
echo; echo "=== 16) PASİF ALANLAR EKRANDA YOK ==="
# =====================================================================
curl -s -b $J -o /tmp/gvt_pas.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "Geçmiş Yıl Zararı alanı kaldırıldı"  "0" "$(grep -c 'name="gecmis_yil_zarari"' /tmp/gvt_pas.html)"
ol "Ödenen Geçici Vergi alanı kaldırıldı" "0" "$(grep -c 'name="gecici_vergi"' /tmp/gvt_pas.html)"
ol "Diğer İndirimler alanı kaldırıldı"    "0" "$(grep -c 'name="diger_indirim"' /tmp/gvt_pas.html)"
ol "Yeni sigorta alanı var"               "1" "$(grep -c 'name="sigorta_primi"' /tmp/gvt_pas.html)"
ol "Yeni eğitim-sağlık alanı var"         "1" "$(grep -c 'name="egitim_saglik"' /tmp/gvt_pas.html)"

# Pasif alan POST edilse bile hesaba GİRMEMELİ
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_pas.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hasilat_elle=500.000" -d "gider=200.000" -d "bagkur=0" \
  -d "sigorta_primi=0" -d "egitim_saglik=0" -d "diger_mahsup=0" \
  -d "gecmis_yil_zarari=999.999" -d "gecici_vergi=888.888" -d "diger_indirim=777.777" \
  "$B/gelir-vergisi/hesapla"
ol "pasif alan POST edilse de matrah değişmez" "300.000,00" "$(jal /tmp/gvt_pas.json bicimli.matrah)"
ol "pasif alan POST edilse de indirim 0"       "0,00"       "$(jal /tmp/gvt_pas.json bicimli.indirim_toplam)"

# Pasif sütunlar veritabanında DURUYOR (geçmiş kayıt korunsun)
ol "gecmis_yil_zarari sütunu duruyor" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='musavir_gelir_gider' AND column_name='gecmis_yil_zarari';")"
ol "gecici_vergi sütunu duruyor" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='musavir_gelir_gider' AND column_name='gecici_vergi';")"
ol "pasif sütunlara yazılmıyor (0 kaldı)" "0.00" \
   "$($MDB -e "SELECT gecici_vergi FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"

# =====================================================================
echo; echo "=== 17) AYLIK KDV TABLOSU ==="
# =====================================================================
ol "musavir_kdv tablosu var" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='musavir_kdv';")"
ol "musavir+yil+ay benzersiz kısıtı" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='musavir_kdv' AND index_name='uq_kdv_musavir_donem' AND seq_in_index=1;")"

curl -s -b $J -o /tmp/gvt_kdvf.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "KDV tablosu 12 ay girdisi (ödenen)" "12" \
   "$(grep -o 'name="kdv\[[0-9]*\]\[odenen\]"' /tmp/gvt_kdvf.html | wc -l | tr -d ' ')"
ol "KDV tablosu 12 ay girdisi (indirilecek)" "12" \
   "$(grep -o 'name="kdv\[[0-9]*\]\[indirilecek\]"' /tmp/gvt_kdvf.html | wc -l | tr -d ' ')"
ol "Ocak ve Aralık etiketleri var" "1" \
   "$([ "$(grep -c 'Ocak' /tmp/gvt_kdvf.html)" -gt 0 ] && [ "$(grep -c 'Aralık' /tmp/gvt_kdvf.html)" -gt 0 ] && echo 1 || echo 0)"

# Kaydetme
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=10.000" -d "kdv[1][indirilecek]=4.000" \
  -d "kdv[2][odenen]=12.500" -d "kdv[2][indirilecek]=3.500" \
  -d "kdv[3][odenen]=8.000"  -d "kdv[3][indirilecek]=0"
ol "3 ay kaydedildi" "3" "$($MDB -e "SELECT COUNT(*) FROM musavir_kdv WHERE musavir_id=1 AND yil=2026;")"
ol "Ocak ödenen 10.000 (binlik doğru)" "10000.00" \
   "$($MDB -e "SELECT odenen FROM musavir_kdv WHERE musavir_id=1 AND yil=2026 AND ay=1;")"
ol "Şubat indirilecek 3.500" "3500.00" \
   "$($MDB -e "SELECT indirilecek FROM musavir_kdv WHERE musavir_id=1 AND yil=2026 AND ay=2;")"
ol "yıllık KDV toplamı 38.000" "38000.00" \
   "$($MDB -e "SELECT SUM(odenen)+SUM(indirilecek) FROM musavir_kdv WHERE musavir_id=1 AND yil=2026;")"

# Boş ay kaydedilmez (gereksiz satır tutulmaz)
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=10.000" -d "kdv[1][indirilecek]=4.000" \
  -d "kdv[2][odenen]=12.500" -d "kdv[2][indirilecek]=3.500" \
  -d "kdv[3][odenen]=8.000"  -d "kdv[3][indirilecek]=0" \
  -d "kdv[4][odenen]=0"      -d "kdv[4][indirilecek]=0"
ol "boş ay satır oluşturmaz" "3" "$($MDB -e "SELECT COUNT(*) FROM musavir_kdv WHERE musavir_id=1 AND yil=2026;")"

# Var olan ay güncellenir, mükerrer olmaz
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=11.000" -d "kdv[1][indirilecek]=4.000"
ol "aynı ay mükerrer olmaz" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_kdv WHERE musavir_id=1 AND yil=2026 AND ay=1;")"
ol "aynı ay güncellendi (11.000)" "11000.00" \
   "$($MDB -e "SELECT odenen FROM musavir_kdv WHERE musavir_id=1 AND yil=2026 AND ay=1;")"

# Sıfırlanan ay silinir
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[3][odenen]=0" -d "kdv[3][indirilecek]=0"
ol "sıfırlanan ay silinir" "0" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_kdv WHERE musavir_id=1 AND yil=2026 AND ay=3;")"

# =====================================================================
echo; echo "=== 18) KDV MAHSUBU — YIL İÇİ NET VERGİ ==="
# =====================================================================
# DİKKAT: 14. bölüm 7. makbuzu eklediği için hasılat 550.000'dir (500.000 değil).
# Kayıtlı: gider 200.000, sigorta talep 60.000, eğitim 20.000
#   kazanç        = 550.000 − 200.000 = 350.000
#   sigorta tavanı= 350.000 × %15 = 52.500 → 52.500 indirilir
#   eğitim tavanı = 350.000 × %10 = 35.000 → 20.000 indirilir
#   indirim       = 72.500  →  matrah = 277.500
#   vergi = 28.500 + (277.500−190.000)×%20 = 46.000
# Şu an KDV: Ocak 11.000+4.000, Şubat 12.500+3.500 = 31.000
ol "güncel KDV toplamı 31.000" "31000.00" \
   "$($MDB -e "SELECT SUM(odenen)+SUM(indirilecek) FROM musavir_kdv WHERE musavir_id=1 AND yil=2026;")"

# stopaj 110.000 (7 makbuz) − KDV 31.000 = net mahsup 79.000
curl -s -b $J -o /tmp/gvt_kdvh.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "hesapta KDV satırı 31.000" "31.000,00" "$(hucre /tmp/gvt_kdvh.html c-kdv)"
ol "net mahsup = stopaj − KDV"  "79.000,00" "$(hucre /tmp/gvt_kdvh.html c-mahsup_toplam)"

# KDV stopajı AŞARSA ödenecek çıkmalı — kullanıcının asıl istediği mantık
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=200.000" -d "kdv[1][indirilecek]=0" \
  -d "kdv[2][odenen]=0" -d "kdv[2][indirilecek]=0"
curl -s -b $J -o /tmp/gvt_kdvb.html "$B/gelir-vergisi/detay/1?yil=2026"
# stopaj 110.000 − KDV 200.000 = −90.000 net mahsup
# sonuç = 46.000 + 90.000 = 136.000 ÖDENECEK
ol "KDV stopajı aşınca net mahsup negatif" "-90.000,00" "$(hucre /tmp/gvt_kdvb.html c-mahsup_toplam)"
ol "KDV stopajı aşınca ÖDENECEK çıkar"     "136.000,00" "$(hucre /tmp/gvt_kdvb.html c-sonuc-tutar)"
ol "etiket ÖDENECEK oldu" "1" \
   "$([ "$(grep -c 'VERGİ YÜKÜ — ÖDEYECEKSİNİZ' /tmp/gvt_kdvb.html)" -gt 0 ] && echo 1 || echo 0)"

# KDV yoksa hesap eski gibi çalışmalı
$MDBR -e "DELETE FROM musavir_kdv WHERE musavir_id=1 AND yil=2026;" >/dev/null
curl -s -b $J -o /tmp/gvt_kdvy.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "KDV silinince mahsup = stopaj" "110.000,00" "$(hucre /tmp/gvt_kdvy.html c-mahsup_toplam)"
# sonuç = 46.000 − 110.000 = −64.000 → İADE
ol "KDV silinince tekrar İADE"     "64.000,00"  "$(hucre /tmp/gvt_kdvy.html c-sonuc-tutar)"

# KDV yalnızca MAHSUPTA sayılır, MATRAHA girmez — KDV değişse de matrah sabit
ol "KDV matraha girmez (277.500 sabit)" "277.500,00" "$(hucre /tmp/gvt_kdvy.html c-matrah)"

# Yıl ayrımı: 2025'e girilen KDV 2026'yı etkilemez
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2025")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2025" \
  -d "kdv[1][odenen]=500.000" -d "kdv[1][indirilecek]=0"
curl -s -b $J -o /tmp/gvt_kdvz.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "2025 KDV'si 2026'yı etkilemez" "110.000,00" "$(hucre /tmp/gvt_kdvz.html c-mahsup_toplam)"
$MDBR -e "DELETE FROM musavir_kdv WHERE yil=2025;" >/dev/null

# Yetki: müşavir başkasının KDV'sini yazamaz
T=$(jeton $JM "$B/gelir-vergisi")
curl -s -b $JM -c $JM -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=2" -d "yil=2026" \
  -d "kdv[1][odenen]=99.999" -d "kdv[1][indirilecek]=0"
ol "müşavir başkasının KDV'sini yazamaz" "0" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_kdv WHERE musavir_id=2 AND yil=2026;")"

c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "musavir_id=1" -d "yil=2026")
ol "personel KDV kaydedemez" "1" \
   "$([ "$c" = "302" ] || [ "$c" = "303" ] || [ "$c" = "403" ] && echo 1 || echo 0)"

# =====================================================================
echo; echo "=== 19) YAZDIRMADA YENİ ALANLAR ==="
# =====================================================================
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=10.000" -d "kdv[1][indirilecek]=4.000"

curl -s -b $J -o /tmp/gvt_yz.html "$B/gelir-vergisi/yazdir/1?yil=2026"
ol "yazdırmada sigorta primi satırı"      "1" "$(grep -c 'Şahıs / Hayat Sigorta Primi' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada eğitim-sağlık satırı"      "1" "$(grep -c 'Eğitim ve Sağlık Harcaması' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada KDV satırı"                "1" "$(grep -c 'Yıl İçinde Ödenen KDV' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada net mahsup satırı"         "1" "$(grep -c 'Net Mahsup' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada aylık KDV tablosu"         "1" "$(grep -c 'Aylık KDV Tablosu' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada geçici vergi YOK"          "0" "$(grep -c 'Ödenen Geçici Vergi' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada geçmiş yıl zararı YOK"     "0" "$(grep -c 'Geçmiş Yıl Zararı' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada sınır aşımı bilgisi"       "1" "$(grep -c 'sınır aşımı indirilemedi' /tmp/gvt_yz.html | head -1)"

curl -s -b $J -o /tmp/gvt_lz.html "$B/gelir-vergisi/liste-yazdir?yil=2026"
# 'Ödenen KDV' iki kez geçer: üstteki özet kutusu + tablo sütun başlığı.
ol "liste yazdırmada KDV sütunu (özet+başlık)" "2" "$(grep -c 'Ödenen KDV' /tmp/gvt_lz.html | head -1)"

curl -s -b $J -o /tmp/gvt_li.html "$B/gelir-vergisi?yil=2026"
ol "liste ekranında KDV sütunu" "1" \
   "$([ "$(grep -c 'Ödenen KDV' /tmp/gvt_li.html)" -gt 0 ] && echo 1 || echo 0)"
$MDBR -e "DELETE FROM musavir_kdv;" >/dev/null


# =====================================================================
echo; echo "=== 20) İNDİRİM KALEMİ LİSTESİ (belge belge) ==="
# =====================================================================
ol "musavir_indirim_kalem tablosu var" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='musavir_indirim_kalem';")"

$MDBR -e "TRUNCATE musavir_indirim_kalem;
TRUNCATE musavir_aylik_gider;" >/dev/null

# Temiz başlangıç: gider 200.000, elle sigorta 10.000 / eğitim 7.500
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=200.000" -d "bagkur=0" -d "sigorta_primi=10.000" -d "egitim_saglik=7.500" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"

# --- Liste BOŞKEN elle girilen tutar kullanılır ----------------------
curl -s -b $J -o /tmp/gvt_k0.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "liste boşken eğitim = elle 7.500"   "7.500,00"  "$(hucre /tmp/gvt_k0.html c-egitim)"
ol "liste boşken sigorta = elle 10.000" "10.000,00" "$(hucre /tmp/gvt_k0.html c-sigorta)"
ol "liste boşken kutu düzenlenebilir" "0" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_k0.html',encoding='utf-8').read()
m=re.search(r'id=\"gv-egitim\"[^>]*>',h,re.S)
print(1 if 'readonly' in m.group(0) else 0)")"

# --- Kalem ekleme ----------------------------------------------------
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kalem=egitim_saglik" \
  -d "tur=egitim" -d "tarih=2026-02-14" --data-urlencode "aciklama=Özel okul 2. taksit" -d "tutar=12.000"
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kalem=egitim_saglik" \
  -d "tur=saglik" -d "tarih=2026-05-09" --data-urlencode "aciklama=Hastane faturası" -d "tutar=8.500"
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kalem=egitim_saglik" \
  -d "tur=egitim" -d "tarih=2026-09-20" --data-urlencode "aciklama=Kurs ücreti" -d "tutar=4.000"

ol "3 kalem kaydedildi" "3" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2026 AND kalem='egitim_saglik';")"
ol "kalem toplamı 24.500 (binlik doğru)" "24500.00" \
   "$($MDB -e "SELECT SUM(tutar) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2026 AND kalem='egitim_saglik';")"
ol "tarih doğru saklandı" "2026-02-14" \
   "$($MDB -e "SELECT tarih FROM musavir_indirim_kalem WHERE musavir_id=1 AND kalem='egitim_saglik' ORDER BY tarih LIMIT 1;")"
ol "Türkçe açıklama bozulmadı" "Özel okul 2. taksit" \
   "$($MDB -e "SELECT aciklama FROM musavir_indirim_kalem WHERE musavir_id=1 AND tarih='2026-02-14';")"
ol "tür saklandı (saglik)" "saglik" \
   "$($MDB -e "SELECT tur FROM musavir_indirim_kalem WHERE musavir_id=1 AND tarih='2026-05-09';")"

# --- Liste DOLUNCA öncelik listeye geçer -----------------------------
curl -s -b $J -o /tmp/gvt_k1.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "liste dolunca eğitim = 24.500 (elle 7.500 değil)" "24.500,00" "$(hucre /tmp/gvt_k1.html c-egitim)"
ol "sigorta hâlâ elle 10.000 (listesi boş)"           "10.000,00" "$(hucre /tmp/gvt_k1.html c-sigorta)"
ol "indirim toplamı 34.500"                            "34.500,00" "$(hucre /tmp/gvt_k1.html c-indirim_toplam)"
ol "eğitim kutusu salt okunur oldu" "1" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_k1.html',encoding='utf-8').read()
m=re.search(r'id=\"gv-egitim\"[^>]*>',h,re.S)
print(1 if 'readonly' in m.group(0) else 0)")"
ol "sigorta kutusu düzenlenebilir kaldı" "0" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_k1.html',encoding='utf-8').read()
m=re.search(r'id=\"gv-sigorta\"[^>]*>',h,re.S)
print(1 if 'readonly' in m.group(0) else 0)")"
ol "listeden geldiği bilgisi gösteriliyor" "1" \
   "$([ "$(grep -c 'belgelik liste' /tmp/gvt_k1.html)" -gt 0 ] && echo 1 || echo 0)"
# NOT: 'data-kalem-satir' JS seçicisinde de geçer; yalnız <tr> öğeleri sayılır.
ol "3 belge satırı listelendi" "3" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_k1.html',encoding='utf-8').read()
print(len(re.findall(r'<tr[^>]*data-kalem-satir=', h)))")"

# Elle girilen değer SİLİNMEZ, saklı kalır (liste boşalınca geri döner)
ol "elle girilen 7.500 veritabanında duruyor" "7500.00" \
   "$($MDB -e "SELECT egitim_saglik FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"

# --- Kalem düzenleme --------------------------------------------------
KID=$($MDB -e "SELECT id FROM musavir_indirim_kalem WHERE musavir_id=1 AND tarih='2026-09-20' LIMIT 1;")
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kalem=egitim_saglik" -d "id=$KID" \
  -d "tur=egitim" -d "tarih=2026-09-20" --data-urlencode "aciklama=Kurs ücreti (güncellendi)" -d "tutar=6.000"
ol "düzenlemede mükerrer satır yok" "3" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2026 AND kalem='egitim_saglik';")"
ol "kalem güncellendi (6.000)" "6000.00" \
   "$($MDB -e "SELECT tutar FROM musavir_indirim_kalem WHERE id=$KID;")"
curl -s -b $J -o /tmp/gvt_k2.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "düzenleme hesaba yansıdı (26.500)" "26.500,00" "$(hucre /tmp/gvt_k2.html c-egitim)"

# --- Kalem silme ------------------------------------------------------
curl -s -b $J -o /dev/null "$B/gelir-vergisi/kalem-sil/$KID"
ol "kalem silindi" "2" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2026 AND kalem='egitim_saglik';")"
curl -s -b $J -o /tmp/gvt_k3.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "silme hesaba yansıdı (20.500)" "20.500,00" "$(hucre /tmp/gvt_k3.html c-egitim)"

# --- SİGORTA kalemi + sınır aşımı -------------------------------------
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kalem=sigorta" \
  -d "tur=hayat" -d "tarih=2026-01-10" --data-urlencode "aciklama=Hayat sigortası" -d "tutar=30.000"
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kalem=sigorta" \
  -d "tur=sahis" -d "tarih=2026-07-01" --data-urlencode "aciklama=Tamamlayıcı sağlık" -d "tutar=25.000"

# DİKKAT: hasılat 550.000'dir (14. bölüm 7. makbuzu ekler), 500.000 değil.
# kazanç = 550.000 − 200.000 = 350.000 → sigorta tavanı 350.000 × %15 = 52.500
# Liste 55.000 → 52.500 iner, 2.500 aşım
curl -s -b $J -o /tmp/gvt_k4.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "sigorta listesi tavanla sınırlandı (52.500)" "52.500,00" "$(hucre /tmp/gvt_k4.html c-sigorta)"
# Aşım tutarı KENDİ öğesinden okunur; sayfada başka yerdeki tutara takılmasın.
ol "sigorta aşımı 2.500 raporlandı" "2.500,00" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_k4.html',encoding='utf-8').read()
m=re.search(r'id=\"gv-sigorta-asim-tutar\"[^>]*>(.*?)</b>', h, re.S)
print(re.sub(r'<[^>]+>','',m.group(1)).strip() if m else 'YOK')")"
ol "iki kalem birbirine karışmıyor" "2" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2026 AND kalem='sigorta';")"
ol "eğitim toplamı sigortadan etkilenmedi" "20.500,00" "$(hucre /tmp/gvt_k4.html c-egitim)"

# --- Yıl ayrımı --------------------------------------------------------
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2025")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2025" -d "kalem=egitim_saglik" \
  -d "tur=egitim" -d "tarih=2025-03-03" --data-urlencode "aciklama=Geçen yıl" -d "tutar=99.000"
curl -s -b $J -o /tmp/gvt_k5.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "2025 kalemi 2026'yı etkilemez" "20.500,00" "$(hucre /tmp/gvt_k5.html c-egitim)"

# --- Yıldan yıla kopyalama --------------------------------------------
$MDBR -e "DELETE FROM musavir_indirim_kalem WHERE yil=2024;" >/dev/null
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2024")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kopyala" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "kaynak_yil=2026" -d "hedef_yil=2024" -d "kalem=egitim_saglik"
ol "2 kalem 2024'e kopyalandı" "2" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2024 AND kalem='egitim_saglik';")"
ol "kopyada tarih hedef yıla kaydı" "2024-02-14" \
   "$($MDB -e "SELECT tarih FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2024 ORDER BY tarih LIMIT 1;")"
ol "kopyada tutar korundu" "20500.00" \
   "$($MDB -e "SELECT SUM(tutar) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2024;")"
$MDBR -e "DELETE FROM musavir_indirim_kalem WHERE yil IN (2024,2025);" >/dev/null

# --- Geçersiz giriş reddi ---------------------------------------------
ONCE=$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2026;")
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kalem=egitim_saglik" \
  -d "tur=egitim" -d "tarih=2026-03-03" -d "tutar=0"
ol "sıfır tutar reddedildi" "$ONCE" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2026;")"
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kalem=egitim_saglik" \
  -d "tur=egitim" -d "tarih=" -d "tutar=5.000"
ol "boş tarih reddedildi" "$ONCE" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2026;")"
# Geçersiz kalem adı → varsayılana düşer, çökme olmaz
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
c=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code}" -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kalem=UYDURMA" \
  -d "tur=UYDURMA" -d "tarih=2026-04-04" -d "tutar=1.000")
ol "geçersiz kalem/tür çökmez" "1" "$([ "$c" = "302" ] || [ "$c" = "303" ] && echo 1 || echo 0)"
ol "geçersiz kalem varsayılana düştü" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=1 AND tarih='2026-04-04' AND kalem='egitim_saglik' AND tur='egitim';")"
$MDBR -e "DELETE FROM musavir_indirim_kalem WHERE tarih='2026-04-04';" >/dev/null

# --- Yetki -------------------------------------------------------------
T=$(jeton $JM "$B/gelir-vergisi")
curl -s -b $JM -c $JM -o /dev/null -X POST "$B/gelir-vergisi/kalem-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=2" -d "yil=2026" -d "kalem=egitim_saglik" \
  -d "tur=egitim" -d "tarih=2026-06-06" -d "tutar=50.000"
ol "müşavir başkasına kalem yazamaz" "0" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE musavir_id=2;")"

# Başkasının kalemini SİLEMEZ
BASKA=$($MDB -e "SELECT id FROM musavir_indirim_kalem WHERE musavir_id=1 AND kalem='sigorta' LIMIT 1;")
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" "$B/gelir-vergisi/kalem-sil/$BASKA")
ol "personel kalem silemez" "1" \
   "$([ "$c" = "302" ] || [ "$c" = "303" ] || [ "$c" = "403" ] && echo 1 || echo 0)"
ol "personel denemesinde kalem duruyor" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_indirim_kalem WHERE id=$BASKA;")"

c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" -X POST "$B/gelir-vergisi/kalem-kaydet" -d "musavir_id=1")
ol "personel kalem ekleyemez" "1" \
   "$([ "$c" = "302" ] || [ "$c" = "303" ] || [ "$c" = "403" ] && echo 1 || echo 0)"

# --- Yazdırmada belge listesi -----------------------------------------
curl -s -b $J -o /tmp/gvt_kz.html "$B/gelir-vergisi/yazdir/1?yil=2026"
ol "yazdırmada eğitim belge listesi" "1" \
   "$([ "$(grep -c 'Eğitim ve Sağlık Harcamaları' /tmp/gvt_kz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada sigorta belge listesi" "1" \
   "$([ "$(grep -c 'Şahıs / Hayat Sigorta Primleri' /tmp/gvt_kz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada belge açıklaması" "1" \
   "$([ "$(grep -c 'Hastane faturası' /tmp/gvt_kz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada 'hesaba giren' notu" "1" \
   "$([ "$(grep -c 'hesaba giren' /tmp/gvt_kz.html)" -gt 0 ] && echo 1 || echo 0)"

# --- Liste boşalınca elle değere geri döner ---------------------------
$MDBR -e "DELETE FROM musavir_indirim_kalem WHERE musavir_id=1 AND yil=2026 AND kalem='egitim_saglik';" >/dev/null
curl -s -b $J -o /tmp/gvt_k6.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "liste boşalınca elle 7.500'e döndü" "7.500,00" "$(hucre /tmp/gvt_k6.html c-egitim)"
ol "kutu tekrar düzenlenebilir" "0" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_k6.html',encoding='utf-8').read()
m=re.search(r'id=\"gv-egitim\"[^>]*>',h,re.S)
print(1 if 'readonly' in m.group(0) else 0)")"
$MDBR -e "TRUNCATE musavir_indirim_kalem;
TRUNCATE musavir_aylik_gider;" >/dev/null


# =====================================================================
echo; echo "=== 21) YIL İÇİ VERGİ YÜKÜ (GV dengesi + KDV) ==="
# =====================================================================
# Kullanıcının verdiği senaryo: vergi 3.000 · stopaj 4.000 · KDV 2.500
#   GV dengesi = 3.000 − 4.000 = −1.000  (devletten ALACAK)
#   Vergi yükü = −1.000 + 2.500 = 1.500  (ÖDENECEK)
$MDBR -e "TRUNCATE musavir_kdv; TRUNCATE musavir_aylik_gider; TRUNCATE musavir_indirim_kalem;" >/dev/null

# Matrah 20.000 → vergi 20.000×%15 = 3.000  (hasılat 550.000 − gider 530.000)
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=530.000" -d "bagkur=0" -d "sigorta_primi=0" -d "egitim_saglik=0" \
  -d "stopaj_elle=4.000" -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=1.500" -d "kdv[1][indirilecek]=1.000"

curl -s -b $J -o /tmp/gvt_vy.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "matrah 20.000"                     "20.000,00" "$(hucre /tmp/gvt_vy.html c-matrah)"
ol "vergi 3.000"                       "3.000,00"  "$(hucre /tmp/gvt_vy.html c-vergi)"
ol "stopaj 4.000"                      "4.000,00"  "$(hucre /tmp/gvt_vy.html c-stopaj)"
ol "KDV 2.500 (ödenen+indirilecek)"    "2.500,00"  "$(hucre /tmp/gvt_vy.html c-kdv)"
ol "GV dengesi −1.000 (alacak)"        "−1.000,00" "$(hucre /tmp/gvt_vy.html c-gv-denge)"
ol "kırılımda KDV 2.500"               "2.500,00"  "$(hucre /tmp/gvt_vy.html c-kdv-yuk)"
ol "VERGİ YÜKÜ 1.500"                  "1.500,00"  "$(hucre /tmp/gvt_vy.html c-sonuc-tutar)"
ol "etiket 'ÖDEYECEKSİNİZ'" "1" \
   "$([ "$(grep -c 'VERGİ YÜKÜ — ÖDEYECEKSİNİZ' /tmp/gvt_vy.html)" -gt 0 ] && echo 1 || echo 0)"
ol "'Devletten Alacak' etiketi" "1" \
   "$([ "$(grep -c 'Devletten Alacak' /tmp/gvt_vy.html)" -gt 0 ] && echo 1 || echo 0)"

# KDV küçükse yük iadeye döner: KDV 500 → −1.000 + 500 = −500 iade
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=500" -d "kdv[1][indirilecek]=0"
curl -s -b $J -o /tmp/gvt_vy2.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "KDV 500 iken yük 500 İADE" "500,00" "$(hucre /tmp/gvt_vy2.html c-sonuc-tutar)"
ol "etiket 'İADE ALACAKSINIZ'" "1" \
   "$([ "$(grep -c 'VERGİ YÜKÜ — İADE ALACAKSINIZ' /tmp/gvt_vy2.html)" -gt 0 ] && echo 1 || echo 0)"

# KDV tam dengeyi kapatırsa sıfır
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=1.000" -d "kdv[1][indirilecek]=0"
curl -s -b $J -o /tmp/gvt_vy3.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "KDV = alacak ise yük 0" "0,00" "$(hucre /tmp/gvt_vy3.html c-sonuc-tutar)"

# GV borçluysa: stopaj 0 → denge +3.000, KDV 1.000 → yük 4.000
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_vy4.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "gider=530.000" -d "bagkur=0" -d "sigorta_primi=0" -d "egitim_saglik=0" \
  -d "stopaj_elle=0" -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "stopaj yokken GV borcu 3.000" "3.000,00" "$(jal /tmp/gvt_vy4.json bicimli.gv_borc)"
ol "GV borcu + KDV = 4.000 yük"   "4.000,00" "$(jal /tmp/gvt_vy4.json bicimli.odenecek)"
$MDBR -e "TRUNCATE musavir_kdv;" >/dev/null

# =====================================================================
echo; echo "=== 22) AYLIK GİDER TABLOSU ==="
# =====================================================================
ol "musavir_aylik_gider tablosu var" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='musavir_aylik_gider';")"
ol "musavir+yil+ay benzersiz kısıtı" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='musavir_aylik_gider' AND index_name='uq_gider_musavir_donem' AND seq_in_index=1;")"

curl -s -b $J -o /tmp/gvt_ag0.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "gider tablosu 12 ay girdisi" "12" \
   "$(grep -o 'name="agider\[[0-9]*\]\[tutar\]"' /tmp/gvt_ag0.html | wc -l | tr -d ' ')"

# Elle gider 530.000 kayıtlı; tabloya 15.000 ekleniyor → 545.000 olmalı
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/gider-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "agider[1][tutar]=5.000"  --data-urlencode "agider[1][aciklama]=Ocak kira" \
  -d "agider[2][tutar]=7.500"  --data-urlencode "agider[2][aciklama]=Şubat personel" \
  -d "agider[3][tutar]=2.500"  --data-urlencode "agider[3][aciklama]=Mart aidat" \
  -d "agider[4][tutar]=0"
ol "3 ay kaydedildi" "3" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_aylik_gider WHERE musavir_id=1 AND yil=2026;")"
ol "boş ay satır oluşturmadı" "0" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_aylik_gider WHERE musavir_id=1 AND yil=2026 AND ay=4;")"
ol "tablo toplamı 15.000 (binlik doğru)" "15000.00" \
   "$($MDB -e "SELECT SUM(tutar) FROM musavir_aylik_gider WHERE musavir_id=1 AND yil=2026;")"
ol "Türkçe açıklama bozulmadı" "Şubat personel" \
   "$($MDB -e "SELECT aciklama FROM musavir_aylik_gider WHERE musavir_id=1 AND yil=2026 AND ay=2;")"

# --- TOPLAMA KURALI: elle + tablo -------------------------------------
curl -s -b $J -o /tmp/gvt_ag1.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "gider = elle 530.000 + tablo 15.000" "545.000,00" "$(hucre /tmp/gvt_ag1.html c-gider)"
ol "elle girilen kutu değişmedi (530.000)" "530000.00" \
   "$($MDB -e "SELECT gider FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"
ol "gider kırılım notu gösteriliyor" "1" \
   "$([ "$(grep -c 'aylık tablo' /tmp/gvt_ag1.html)" -gt 0 ] && echo 1 || echo 0)"
# hasılat 550.000 − 545.000 = 5.000 matrah → vergi 750
ol "matrah 5.000" "5.000,00" "$(hucre /tmp/gvt_ag1.html c-matrah)"
ol "vergi 750"    "750,00"   "$(hucre /tmp/gvt_ag1.html c-vergi)"

# Var olan ay güncellenir, mükerrer olmaz
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/gider-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "agider[1][tutar]=6.000" --data-urlencode "agider[1][aciklama]=Ocak kira"
ol "aynı ay mükerrer olmaz" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_aylik_gider WHERE musavir_id=1 AND yil=2026 AND ay=1;")"
ol "aynı ay güncellendi (6.000)" "6000.00" \
   "$($MDB -e "SELECT tutar FROM musavir_aylik_gider WHERE musavir_id=1 AND yil=2026 AND ay=1;")"

# Sıfırlanan ay silinir
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/gider-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "agider[3][tutar]=0"
ol "sıfırlanan ay silindi" "0" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_aylik_gider WHERE musavir_id=1 AND yil=2026 AND ay=3;")"

# Yıl ayrımı
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2025")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/gider-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2025" -d "agider[1][tutar]=99.000"
curl -s -b $J -o /tmp/gvt_ag2.html "$B/gelir-vergisi/detay/1?yil=2026"
# 2026: elle 530.000 + (6.000 + 7.500) = 543.500
ol "2025 gideri 2026'yı etkilemez" "543.500,00" "$(hucre /tmp/gvt_ag2.html c-gider)"
$MDBR -e "DELETE FROM musavir_aylik_gider WHERE yil=2025;" >/dev/null

# Yetki
T=$(jeton $JM "$B/gelir-vergisi")
curl -s -b $JM -c $JM -o /dev/null -X POST "$B/gelir-vergisi/gider-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=2" -d "yil=2026" -d "agider[1][tutar]=88.000"
ol "müşavir başkasına gider yazamaz" "0" \
   "$($MDB -e "SELECT COUNT(*) FROM musavir_aylik_gider WHERE musavir_id=2;")"
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" -X POST "$B/gelir-vergisi/gider-kaydet" -d "musavir_id=1")
ol "personel gider kaydedemez" "1" \
   "$([ "$c" = "302" ] || [ "$c" = "303" ] || [ "$c" = "403" ] && echo 1 || echo 0)"

# Yazdırmada gider tablosu ve vergi yükü
curl -s -b $J -o /tmp/gvt_agz.html "$B/gelir-vergisi/yazdir/1?yil=2026"
ol "yazdırmada aylık gider tablosu" "1" \
   "$([ "$(grep -c 'Aylık Gider Tablosu' /tmp/gvt_agz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada gider açıklaması" "1" \
   "$([ "$(grep -c 'Ocak kira' /tmp/gvt_agz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada vergi yükü satırı" "1" \
   "$([ "$(grep -c 'YIL İÇİ VERGİ YÜKÜ' /tmp/gvt_agz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada GV dengesi satırı" "1" \
   "$([ "$(grep -c 'Devletten Alacak\|Gelir Vergisi: Borç' /tmp/gvt_agz.html)" -gt 0 ] && echo 1 || echo 0)"

$MDBR -e "TRUNCATE musavir_aylik_gider;" >/dev/null

# =====================================================================
#  TEMİZLİK
#
#  Bu test `musavir` kullanıcısını musavir_id=1'e bağlar. Diğer testler
#  (makbuz_testi, odeme_kompakt_testi…) aynı kullanıcıyı musavir_id=2 ile
#  kullandığı için, artık kalan kullanici_musavirleri satırı sonraki
#  koşuları YANLIŞ yere düşürüyordu. Kendi izimizi siliyoruz.
# =====================================================================
$MDBR -e "DELETE FROM kullanici_musavirleri;" >/dev/null 2>&1
$MDBR -e "DELETE FROM vergi_tarifeleri WHERE yil=2027;" >/dev/null 2>&1
$MDBR -e "DELETE FROM musavir_kdv;" >/dev/null 2>&1
$MDBR -e "TRUNCATE musavir_indirim_kalem;
TRUNCATE musavir_aylik_gider;" >/dev/null 2>&1

echo
echo "======================================================"
echo " GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "======================================================"
[ $k -eq 0 ] || exit 1
