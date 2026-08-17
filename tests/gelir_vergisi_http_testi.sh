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
-- 19. güncelleme: eski bölümler KESİLEN MAKBUZ kipine göre yazıldı.
UPDATE ayarlar SET deger='makbuz' WHERE anahtar='gv_varsayilan_kip';
UPDATE ayarlar SET deger='20' WHERE anahtar='gv_ucret_stopaj_oran';
UPDATE ayarlar SET deger='20' WHERE anahtar='gv_ucret_kdv_oran';
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
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "gider=200.000,00" -d "bagkur=30.000" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"

curl -s -b $J -o /tmp/gvt_h1.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "kazanç = 500.000 − 200.000"            "300.000,00" "$(hucre /tmp/gvt_h1.html c-kazanc)"
ol "indirim toplamı = 30.000 (Bağkur)"     "30.000,00"  "$(hucre /tmp/gvt_h1.html c-indirim_toplam)"
ol "matrah = 270.000"                      "270.000,00" "$(hucre /tmp/gvt_h1.html c-matrah)"
ol "vergi = 28.500 + 80.000×%20 = 44.500"  "44.500,00"  "$(hucre /tmp/gvt_h1.html c-vergi)"
ol "ödenmesi gereken = 44.500"             "44.500,00"  "$(hucre /tmp/gvt_h1.html c-odenmesi_gereken)"
# 18. güncelleme: makbuzlardaki 100.000 KDV yükümlülüğü doğar, hiç ödenmemiştir.
# mahsup = stopaj 100.000 − kalan KDV borcu 100.000 = 0 → sonuç = verginin tamamı.
ol "sonuç = 44.500 ödenecek (KDV borcu mahsubu sıfırladı)" "44.500,00" \
   "$(hucre /tmp/gvt_h1.html c-sonuc-tutar)"
# Artık ÖDENECEK durumu var; İADE metni yalnız JS dizesinde geçer (1 kez).
ol "sonuç etiketi ÖDEYECEKSİNİZ" "1" \
   "$([ "$(grep -c 'VERGİ YÜKÜ — ÖDEYECEKSİNİZ' /tmp/gvt_h1.html)" -gt 0 ] && echo 1 || echo 0)"
ol "gider veritabanına yazıldı"            "200000.00" \
   "$($MDB -e "SELECT gider FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"

# Aynı müşavir + yıl İKİNCİ kez kaydedilince MÜKERRER satır olmamalı
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
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
curl -s -b $J -c $J -o /tmp/gvt_j1.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "gider=400.000" -d "bagkur=0" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "'400.000' → 400.000,00 (binlik nokta)" "400.000,00" "$(jal /tmp/gvt_j1.json bicimli.gider)"
ol "'400.000' ile kazanç 100.000"          "100.000,00" "$(jal /tmp/gvt_j1.json bicimli.kazanc)"

T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j2.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "gider=1.234.567,89" -d "bagkur=0" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "'1.234.567,89' tam okunur" "1.234.567,89" "$(jal /tmp/gvt_j2.json bicimli.gider)"

T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j3.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
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
curl -s -b $J -c $J -o /tmp/gvt_j4.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "hasilat_elle=2.000.000" -d "gider=400.000" -d "uyumlu_indirim=1" \
  -d "stopaj_elle=400.000" -d "diger_mahsup=20.000" -d "bagkur=0" \
  "$B/gelir-vergisi/hesapla"
ol "AJAX durum true"                  "True"         "$(jal /tmp/gvt_j4.json durum)"
ol "AJAX matrah 1.600.000"            "1.600.000,00" "$(jal /tmp/gvt_j4.json bicimli.matrah)"
ol "AJAX vergi 442.500"               "442.500,00"   "$(jal /tmp/gvt_j4.json bicimli.vergi)"
ol "AJAX %5 indirim 22.125"           "22.125,00"    "$(jal /tmp/gvt_j4.json bicimli.uyumlu)"
ol "AJAX ödenmesi gereken 420.375"    "420.375,00"   "$(jal /tmp/gvt_j4.json bicimli.odenmesi_gereken)"
# mahsup = stopaj 400.000 + diğer 20.000 − kalan KDV borcu 100.000 = 320.000
ol "AJAX mahsup toplamı 320.000"      "320.000,00"   "$(jal /tmp/gvt_j4.json bicimli.mahsup_toplam)"
ol "AJAX ödenecek 100.375"            "100.375,00"   "$(jal /tmp/gvt_j4.json bicimli.odenecek)"
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
curl -s -b $J -c $J -o /tmp/gvt_j5.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "gider=700.000" -d "bagkur=0" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "zararda matrah 0"          "0,00"        "$(jal /tmp/gvt_j5.json bicimli.matrah)"
ol "zararda vergi 0"           "0,00"        "$(jal /tmp/gvt_j5.json bicimli.vergi)"
# Zararda vergi 0; mahsup = stopaj 100.000 − KDV borcu 100.000 = 0 → iade de 0.
ol "zararda KDV borcu iadeyi kapatır" "0,00" "$(jal /tmp/gvt_j5.json bicimli.iade)"

# İndirimler kazancı aşarsa matrah 0
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j6.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "gider=400.000" -d "bagkur=160.000" \
  -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "indirim kazancı aşınca matrah 0" "0,00" "$(jal /tmp/gvt_j6.json bicimli.matrah)"

# %5 indirim üst sınırı
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_j7.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
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
curl -s -b $J -c $J -o /tmp/gvt_j8.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
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
$MDBR -e "UPDATE ayarlar SET deger='tum' WHERE anahtar='gv_hasilat_kaynagi';
-- 19. güncelleme: eski bölümler KESİLEN MAKBUZ kipine göre yazıldı.
UPDATE ayarlar SET deger='makbuz' WHERE anahtar='gv_varsayilan_kip';
UPDATE ayarlar SET deger='20' WHERE anahtar='gv_ucret_stopaj_oran';
UPDATE ayarlar SET deger='20' WHERE anahtar='gv_ucret_kdv_oran';" >/dev/null
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
curl -s -b $J -c $J -o /tmp/gvt_j9.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2027" -d "hesap_kipi=makbuz" \
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
# 21. güncelleme: kırılım satırına rakamlı açıklama eklendi
# ("42.500,00 vergi − 100.000,00 stopaj"), bu yüzden tutar 4 kez geçer:
# hesaplanan vergi + ödenmesi gereken + kırılım açıklaması + sonuç satırı.
ol "gv yazdır: vergi 42.500 (4 satır)" "4" "$(grep -c '42.500,00' /tmp/gvt_gy.html | head -1)"
# Artık makbuz KDV borcu da mahsuba girdiği için sonuç değişti; tutar 1 kez geçer.
ol "gv yazdır: sonuç satırı basıldı" "1" "$(grep -c '57.500,00' /tmp/gvt_gy.html | head -1)"
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
curl -s -b $JM -c $JM -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=2" -d "yil=2026" -d "hesap_kipi=makbuz" \
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
curl -s -b $J -c $J -o /tmp/gvt_s1.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
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
curl -s -b $J -c $J -o /tmp/gvt_s2.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "hasilat_elle=500.000" -d "gider=200.000" -d "bagkur=250.000" \
  -d "sigorta_primi=0" -d "egitim_saglik=0" -d "diger_mahsup=0" \
  "$B/gelir-vergisi/hesapla"
ol "Bağ-Kur sınırsız (250.000 tamamı indi)" "250.000,00" "$(jal /tmp/gvt_s2.json bicimli.bagkur)"
ol "Bağ-Kur ile matrah 50.000"              "50.000,00"  "$(jal /tmp/gvt_s2.json bicimli.matrah)"

# Sınır tabanı KAZANÇ: gider artınca tavan da düşmeli
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_s3.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "hasilat_elle=500.000" -d "gider=400.000" -d "bagkur=0" \
  -d "sigorta_primi=60.000" -d "egitim_saglik=60.000" -d "diger_mahsup=0" \
  "$B/gelir-vergisi/hesapla"
ol "gider artınca sigorta tavanı 15.000'e düştü" "15.000,00" "$(jal /tmp/gvt_s3.json bicimli.sigorta_tavan)"
ol "gider artınca eğitim tavanı 10.000'e düştü"  "10.000,00" "$(jal /tmp/gvt_s3.json bicimli.egitim_tavan)"
ol "tavan düşünce indirim de düştü (25.000)"     "25.000,00" "$(jal /tmp/gvt_s3.json bicimli.indirim_toplam)"

# Zararda tavan 0 olmalı (negatif kârda indirim yok)
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_s4.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "hasilat_elle=100.000" -d "gider=300.000" -d "bagkur=0" \
  -d "sigorta_primi=50.000" -d "egitim_saglik=50.000" -d "diger_mahsup=0" \
  "$B/gelir-vergisi/hesapla"
ol "zararda sigorta tavanı 0" "0,00" "$(jal /tmp/gvt_s4.json bicimli.sigorta_tavan)"
ol "zararda eğitim tavanı 0"  "0,00" "$(jal /tmp/gvt_s4.json bicimli.egitim_tavan)"
ol "zararda indirim 0"        "0,00" "$(jal /tmp/gvt_s4.json bicimli.indirim_toplam)"

# Sınırlar KAYDEDİLMEZ, her hesapta yeniden uygulanır
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
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
curl -s -b $J -c $J -o /tmp/gvt_pas.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
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
# ---------------------------------------------------------------------
#  YENİ KDV MANTIĞI (18. güncelleme)
#
#  Makbuz kesilince KDV YÜKÜMLÜLÜĞÜ doğar (makbuzların KDV toplamı).
#  Ödeme = aylık tablonun AY TOPLAMI, yani "ödenen + indirilecek".
#      Kalan KDV borcu = Makbuz KDV'si − (ödenen + indirilecek)
# ---------------------------------------------------------------------
MKDV=$($MDB -e "SELECT COALESCE(SUM(kdv),0) FROM makbuzlar WHERE musavir_id=1 AND yil=2026;")
ol "makbuz KDV yükümlülüğü 110.000" "110000.00" "$MKDV"

# Şu an: ödenen 11.000+12.500 = 23.500, indirilecek 4.000+3.500 = 7.500
#        ay toplamı = 31.000  →  kalan borç = 110.000 − 31.000 = 79.000
ol "ödenen sütunu 23.500" "23500.00" \
   "$($MDB -e "SELECT SUM(odenen) FROM musavir_kdv WHERE musavir_id=1 AND yil=2026;")"
ol "ay toplamı (ödenen+indirilecek) 31.000" "31000.00" \
   "$($MDB -e "SELECT SUM(odenen)+SUM(indirilecek) FROM musavir_kdv WHERE musavir_id=1 AND yil=2026;")"

curl -s -b $J -o /tmp/gvt_kdvh.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "kalan KDV borcu 79.000"   "79.000,00"  "$(hucre /tmp/gvt_kdvh.html c-kdv)"
# net mahsup = stopaj 110.000 − kalan borç 79.000 = 31.000
ol "net mahsup = stopaj − kalan borç" "31.000,00" "$(hucre /tmp/gvt_kdvh.html c-mahsup_toplam)"
ol "indirilecek sütunu MAHSUBA GİRER" "79.000,00" "$(hucre /tmp/gvt_kdvh.html c-kdv-yuk)"

# --- KULLANICI SENARYOSU: alacak/verecek tam kapanır ------------------
# matrah 20.000 → vergi 3.000 · stopaj elle 4.000 → 1.000 GV alacağı
# makbuz KDV 110.000; AY TOPLAMI 109.000 olursa kalan borç 1.000 → yük TAM 0
# (104.000 ödenen + 5.000 indirilecek = 109.000)
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "gider=530.000" -d "bagkur=0" -d "sigorta_primi=0" -d "egitim_saglik=0" \
  -d "stopaj_elle=4.000" -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=104.000" -d "kdv[1][indirilecek]=5.000" \
  -d "kdv[2][odenen]=0" -d "kdv[2][indirilecek]=0"
curl -s -b $J -o /tmp/gvt_ks.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "SENARYO: matrah 20.000"          "20.000,00" "$(hucre /tmp/gvt_ks.html c-matrah)"
ol "SENARYO: vergi 3.000"            "3.000,00"  "$(hucre /tmp/gvt_ks.html c-vergi)"
ol "SENARYO: GV alacağı 1.000"       "1.000,00"  "$(hucre /tmp/gvt_ks.html c-gv-denge)"
ol "SENARYO: kalan KDV borcu 1.000"  "1.000,00"  "$(hucre /tmp/gvt_ks.html c-kdv-yuk)"
ol "SENARYO: vergi yükü TAM 0"       "0,00"      "$(hucre /tmp/gvt_ks.html c-sonuc-tutar)"
ol "SENARYO: 'ALACAK/VERECEK YOK'" "1" \
   "$([ "$(grep -c 'ALACAK/VERECEK YOK' /tmp/gvt_ks.html)" -gt 0 ] && echo 1 || echo 0)"

# --- İŞARET KURALI: eksi işareti KULLANILMAZ --------------------------
ol "GV dengesinde eksi işareti YOK" "0" \
   "$(python3 -c "
h=open('/tmp/gvt_ks.html',encoding='utf-8').read()
import re
m=re.search(r'id=\"c-gv-denge\"[^>]*>(.*?)</td>',h,re.S)
print(1 if '−' in m.group(1) or '-' in re.sub(r'<[^>]+>','',m.group(1)) else 0)")"
ol "lehe tutar yeşil sınıfla gösteriliyor" "1" \
   "$(python3 -c "
h=open('/tmp/gvt_ks.html',encoding='utf-8').read()
import re
m=re.search(r'id=\"c-gv-denge\"[^>]*class=\"([^\"]*)\"|class=\"([^\"]*)\"[^>]*id=\"c-gv-denge\"',h,re.S)
print(1 if m and 'yesil' in (m.group(1) or m.group(2) or '') else 0)")"

# --- Hiç KDV ödenmemişse tüm yükümlülük borç --------------------------
$MDBR -e "DELETE FROM musavir_kdv WHERE musavir_id=1 AND yil=2026;" >/dev/null
curl -s -b $J -o /tmp/gvt_kdvy.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "ödeme yoksa kalan borç = yükümlülük" "110.000,00" "$(hucre /tmp/gvt_kdvy.html c-kdv)"
# Bu noktada stopaj ELLE 4.000'dir (makbuz stopajı 110.000 değil).
# net mahsup = 4.000 − 110.000 = −106.000 → sonuç = 3.000 + 106.000 = 109.000
ol "ödeme yoksa net mahsup −106.000" "-106.000,00" "$(hucre /tmp/gvt_kdvy.html c-mahsup_toplam)"
ol "ödeme yoksa 109.000 ÖDENECEK"    "109.000,00"  "$(hucre /tmp/gvt_kdvy.html c-sonuc-tutar)"

# --- KDV tamamen ödenmişse: sadece GV alacağı kalır -------------------
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kdv[1][odenen]=110.000"
curl -s -b $J -o /tmp/gvt_kdvt.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "tamamı ödenince kalan borç 0" "0,00"     "$(hucre /tmp/gvt_kdvt.html c-kdv)"
# mahsup = stopaj 4.000 − 0 = 4.000 → sonuç = 3.000 − 4.000 = −1.000 İADE
ol "tamamı ödenince 1.000 İADE"   "1.000,00" "$(hucre /tmp/gvt_kdvt.html c-sonuc-tutar)"
ol "etiket İADE oldu" "1" \
   "$([ "$(grep -c 'İADE ALACAKSINIZ' /tmp/gvt_kdvt.html)" -gt 0 ] && echo 1 || echo 0)"

# --- FAZLA ÖDEME: KDV alacağı doğar, yükü azaltır ---------------------
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kdv[1][odenen]=115.000"
curl -s -b $J -o /tmp/gvt_kdvf.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "fazla ödemede KDV alacağı 5.000" "5.000,00" "$(hucre /tmp/gvt_kdvf.html c-kdv-yuk)"
ol "fazla ödeme etiketi" "1" \
   "$([ "$(grep -c 'Fazla Ödeme (alacak)' /tmp/gvt_kdvf.html)" -gt 0 ] && echo 1 || echo 0)"
# GV alacağı 1.000 + KDV alacağı 5.000 = 6.000 iade
ol "fazla ödemede 6.000 İADE" "6.000,00" "$(hucre /tmp/gvt_kdvf.html c-sonuc-tutar)"

# --- KDV MATRAHA GİRMEZ ------------------------------------------------
ol "KDV matrahı değiştirmez (20.000 sabit)" "20.000,00" "$(hucre /tmp/gvt_kdvf.html c-matrah)"
$MDBR -e "DELETE FROM musavir_kdv WHERE musavir_id=1 AND yil=2026;" >/dev/null

# Yıl ayrımı: 2025'e girilen KDV 2026'yı etkilemez
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2025")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2025" \
  -d "kdv[1][odenen]=500.000" -d "kdv[1][indirilecek]=0"
curl -s -b $J -o /tmp/gvt_kdvz.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "2025 KDV'si 2026'yı etkilemez" "110.000,00" "$(hucre /tmp/gvt_kdvz.html c-kdv)"
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
# 18. bölüm sigorta/eğitim alanlarını sıfırladı; yazdırma satırlarını
# görebilmek için yeniden dolduruluyor.
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "gider=200.000" -d "bagkur=0" -d "sigorta_primi=60.000" -d "egitim_saglik=20.000" \
  -d "stopaj_elle=" -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=10.000" -d "kdv[1][indirilecek]=4.000"

curl -s -b $J -o /tmp/gvt_yz.html "$B/gelir-vergisi/yazdir/1?yil=2026"
ol "yazdırmada sigorta primi satırı"      "1" "$(grep -c 'Şahıs / Hayat Sigorta Primi' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada eğitim-sağlık satırı"      "1" "$(grep -c 'Eğitim ve Sağlık Harcaması' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada kalan KDV borcu satırı"    "1" "$(grep -c 'Kalan KDV Borcu' /tmp/gvt_yz.html | head -1)"
# "Net Mahsup" ara satırı 21. güncellemede KALDIRILDI (kavramsal olarak
# yanıltıcıydı: KDV borçtur, mahsup değildir). Yerine iki kırılım satırı var.
ol "yazdırmada net mahsup satırı YOK"     "0" "$(grep -c 'Net Mahsup' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada GV dengesi kırılımı"       "1" \
   "$([ "$(grep -c 'Gelir Vergisi: Devletten Alacak\|Gelir Vergisi: Borç' /tmp/gvt_yz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada KDV kırılımı"              "1" \
   "$([ "$(grep -c 'KDV: Ödenmemiş Borç\|KDV: Fazla Ödeme' /tmp/gvt_yz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada aylık KDV tablosu"         "1" "$(grep -c 'Aylık KDV Tablosu' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada geçici vergi YOK"          "0" "$(grep -c 'Ödenen Geçici Vergi' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada geçmiş yıl zararı YOK"     "0" "$(grep -c 'Geçmiş Yıl Zararı' /tmp/gvt_yz.html | head -1)"
ol "yazdırmada sınır aşımı bilgisi"       "1" "$(grep -c 'sınır aşımı indirilemedi' /tmp/gvt_yz.html | head -1)"

curl -s -b $J -o /tmp/gvt_lz.html "$B/gelir-vergisi/liste-yazdir?yil=2026"
# 20. güncelleme: sütun adı "Kalan KDV Borcu" oldu (özet kutusu + tablo başlığı).
ol "liste yazdırmada KDV sütunu (özet+başlık)" "2" "$(grep -c 'Kalan KDV Borcu' /tmp/gvt_lz.html | head -1)"

curl -s -b $J -o /tmp/gvt_li.html "$B/gelir-vergisi?yil=2026"
ol "liste ekranında KDV sütunu" "1" \
   "$([ "$(grep -c 'Kalan KDV Borcu' /tmp/gvt_li.html)" -gt 0 ] && echo 1 || echo 0)"
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
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
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
# Kullanıcı senaryosu: matrah 20.000 · vergi 3.000 · stopaj 4.000
#   makbuz KDV 110.000, fiilen ödenen 109.000 → kalan borç 1.000
#   GV alacağı 1.000 − KDV borcu 1.000 → yük TAM 0
$MDBR -e "TRUNCATE musavir_kdv; TRUNCATE musavir_aylik_gider; TRUNCATE musavir_indirim_kalem;" >/dev/null

T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "gider=530.000" -d "bagkur=0" -d "sigorta_primi=0" -d "egitim_saglik=0" \
  -d "stopaj_elle=4.000" -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kdv[1][odenen]=109.000"

curl -s -b $J -o /tmp/gvt_vy.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "matrah 20.000"                  "20.000,00"  "$(hucre /tmp/gvt_vy.html c-matrah)"
ol "vergi 3.000"                    "3.000,00"   "$(hucre /tmp/gvt_vy.html c-vergi)"
ol "stopaj 4.000"                   "4.000,00"   "$(hucre /tmp/gvt_vy.html c-stopaj)"
ol "GV dengesi 1.000 (işaretsiz)"   "1.000,00"   "$(hucre /tmp/gvt_vy.html c-gv-denge)"
ol "kalan KDV borcu 1.000"          "1.000,00"   "$(hucre /tmp/gvt_vy.html c-kdv-yuk)"
ol "VERGİ YÜKÜ 0 (mahsuplaştı)"     "0,00"       "$(hucre /tmp/gvt_vy.html c-sonuc-tutar)"
ol "etiket ALACAK/VERECEK YOK" "1" \
   "$([ "$(grep -c 'ALACAK/VERECEK YOK' /tmp/gvt_vy.html)" -gt 0 ] && echo 1 || echo 0)"
ol "'Devletten Alacak' etiketi" "1" \
   "$([ "$(grep -c 'Devletten Alacak' /tmp/gvt_vy.html)" -gt 0 ] && echo 1 || echo 0)"

# Daha az ödenirse borç artar: 105.000 ödenirse kalan 5.000 → yük 4.000 ödenecek
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kdv[1][odenen]=105.000"
curl -s -b $J -o /tmp/gvt_vy2.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "az ödemede kalan borç 5.000"  "5.000,00" "$(hucre /tmp/gvt_vy2.html c-kdv-yuk)"
ol "az ödemede 4.000 ÖDENECEK"    "4.000,00" "$(hucre /tmp/gvt_vy2.html c-sonuc-tutar)"
ol "etiket 'ÖDEYECEKSİNİZ'" "1" \
   "$([ "$(grep -c 'VERGİ YÜKÜ — ÖDEYECEKSİNİZ' /tmp/gvt_vy2.html)" -gt 0 ] && echo 1 || echo 0)"

# Stopaj yoksa GV borçlu: 3.000 borç + 1.000 KDV borcu = 4.000
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kdv[1][odenen]=109.000"
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_vy4.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "hesap_kipi=makbuz" \
  -d "gider=530.000" -d "bagkur=0" -d "sigorta_primi=0" -d "egitim_saglik=0" \
  -d "stopaj_elle=0" -d "diger_mahsup=0" "$B/gelir-vergisi/hesapla"
ol "stopaj yokken GV borcu 3.000" "3.000,00" "$(jal /tmp/gvt_vy4.json bicimli.gv_borc)"
ol "GV borcu + KDV borcu = 4.000" "4.000,00" "$(jal /tmp/gvt_vy4.json bicimli.odenecek)"
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
echo; echo "=== 23) YILLIK ÜCRET PROJEKSİYONU (hesap kipi) ==="
# =====================================================================
ol "hesap_kipi sütunu var" "1" \
   "$($MDB -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='musavir_gelir_gider' AND column_name='hesap_kipi';")"
for a in gv_ucret_stopaj_oran gv_ucret_kdv_oran gv_varsayilan_kip; do
  ol "ayar $a tanımlı" "1" "$($MDB -e "SELECT COUNT(*) FROM ayarlar WHERE anahtar='$a';")"
done

$MDBR -e "TRUNCATE musavir_kdv; TRUNCATE musavir_aylik_gider; TRUNCATE musavir_indirim_kalem;" >/dev/null

# Müşavir 1 ücretleri: 120.000 + 96.000 + 48.000 = 264.000
ol "ücret toplamı 264.000" "264000.00" \
   "$($MDB -e "SELECT COALESCE(SUM(u.tutar),0) FROM mukellef_ucretleri u JOIN mukellefler m ON m.id=u.mukellef_id WHERE m.musavir_id=1 AND u.yil=2026;")"

# --- ÜCRET kipine geç ------------------------------------------------
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kip" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kip=ucret"
ol "kip veritabanına yazıldı" "ucret" \
   "$($MDB -e "SELECT hesap_kipi FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"

# gider/indirim alanlarını sıfırla ki hasılat-stopaj-KDV net ölçülsün
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hesap_kipi=ucret" -d "gider=100.000" -d "bagkur=20.000" \
  -d "sigorta_primi=50.000" -d "egitim_saglik=50.000" \
  -d "stopaj_elle=" -d "hasilat_elle=" -d "diger_mahsup=0" "$B/gelir-vergisi/kaydet"

curl -s -b $J -o /tmp/gvt_uc.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "hasılat ücretlerden 264.000"    "264.000,00" "$(hucre /tmp/gvt_uc.html c-hasilat)"
ol "stopaj ücretlerden 52.800 (%20)" "52.800,00" "$(hucre /tmp/gvt_uc.html c-stopaj)"
ol "KDV yükümlülüğü 52.800 (%20)"    "52.800,00" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_uc.html',encoding='utf-8').read()
m=re.search(r'id=\"kdv-t-kalan\"[^>]*>(.*?)</div>',h,re.S)
print(re.sub(r'\s+',' ',re.sub(r'<[^>]+>','',m.group(1))).strip() if m else 'YOK')")"
ol "kazanç 164.000 (264.000−100.000)" "164.000,00" "$(hucre /tmp/gvt_uc.html c-kazanc)"

# --- İNDİRİM TABANI: Bağ-Kur SONRASI (19. güncelleme) ----------------
# taban = 164.000 − 20.000 Bağkur = 144.000
#   sigorta tavanı %15 = 21.600 · eğitim tavanı %10 = 14.400
ol "sigorta tavanı 21.600 (Bağkur sonrası)" "21.600,00" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_uc.html',encoding='utf-8').read()
m=re.search(r'id=\"gv-sigorta-tavan\"[^>]*>(.*?)</b>',h,re.S)
print(re.sub(r'<[^>]+>','',m.group(1)).strip() if m else 'YOK')")"
ol "eğitim tavanı 14.400 (Bağkur sonrası)" "14.400,00" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_uc.html',encoding='utf-8').read()
m=re.search(r'id=\"gv-egitim-tavan\"[^>]*>(.*?)</b>',h,re.S)
print(re.sub(r'<[^>]+>','',m.group(1)).strip() if m else 'YOK')")"
ol "sigorta tavanla sınırlandı"  "21.600,00" "$(hucre /tmp/gvt_uc.html c-sigorta)"
ol "eğitim tavanla sınırlandı"   "14.400,00" "$(hucre /tmp/gvt_uc.html c-egitim)"
ol "indirim toplamı 56.000"      "56.000,00" "$(hucre /tmp/gvt_uc.html c-indirim_toplam)"
ol "matrah 108.000"              "108.000,00" "$(hucre /tmp/gvt_uc.html c-matrah)"
ol "vergi 16.200 (1. dilim %15)" "16.200,00"  "$(hucre /tmp/gvt_uc.html c-vergi)"

# Bağ-Kur artınca tavan DÜŞMELİ (taban Bağkur sonrası)
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /tmp/gvt_uc2.json -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "hesap_kipi=ucret" -d "gider=100.000" -d "bagkur=64.000" \
  -d "sigorta_primi=50.000" -d "egitim_saglik=50.000" -d "diger_mahsup=0" \
  "$B/gelir-vergisi/hesapla"
# taban = 164.000 − 64.000 = 100.000 → tavanlar 15.000 / 10.000
ol "Bağkur artınca sigorta tavanı 15.000" "15.000,00" "$(jal /tmp/gvt_uc2.json bicimli.sigorta_tavan)"
ol "Bağkur artınca eğitim tavanı 10.000"  "10.000,00" "$(jal /tmp/gvt_uc2.json bicimli.egitim_tavan)"
ol "indirim tabanı 100.000"               "100.000,00" "$(jal /tmp/gvt_uc2.json bicimli.indirim_taban)"

# --- ÜCRET DÖKÜMÜ EKRANDA -------------------------------------------
ol "ücret dökümü kartı var" "1" \
   "$([ "$(grep -c 'id=\"ucret-dokum\"' /tmp/gvt_uc.html)" -gt 0 ] && echo 1 || echo 0)"
# Sayfalama eklendikten sonra tbody'de bir de gizli "bulunamadı" satırı var;
# gerçek mükellef satırları data-uc-satir ile işaretlidir.
ol "dökümde 3 mükellef satırı" "3" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_uc.html',encoding='utf-8').read()
print(len(re.findall(r'data-uc-satir=', h)))")"
ol "dökümde mükellef adı" "1" \
   "$([ "$(grep -c 'ALFA TEKSTİL' /tmp/gvt_uc.html)" -gt 0 ] && echo 1 || echo 0)"
ol "dökümde stopaj sütunu (24.000)" "1" \
   "$([ "$(grep -c '24.000,00' /tmp/gvt_uc.html)" -gt 0 ] && echo 1 || echo 0)"

# --- KDV: ücretten doğan yükümlülük ----------------------------------
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kdv-kaydet" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" \
  -d "kdv[1][odenen]=30.000" -d "kdv[1][indirilecek]=10.000"
curl -s -b $J -o /tmp/gvt_uc3.html "$B/gelir-vergisi/detay/1?yil=2026"
# 52.800 yükümlülük − 40.000 ödeme = 12.800 kalan borç
ol "ücret KDV borcu 12.800" "12.800,00" "$(hucre /tmp/gvt_uc3.html c-kdv)"
ol "KDV kartı 'Ücret KDV Yükümlülüğü' diyor" "1" \
   "$([ "$(grep -c 'Ücret KDV Yükümlülüğü' /tmp/gvt_uc3.html)" -gt 0 ] && echo 1 || echo 0)"

# --- KİP DEĞİŞTİRME kayıtları KORUR ----------------------------------
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kip" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kip=makbuz"
curl -s -b $J -o /tmp/gvt_uc4.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "makbuz kipinde hasılat 550.000" "550.000,00" "$(hucre /tmp/gvt_uc4.html c-hasilat)"
ol "kip değişince gider korundu"    "100.000,00" "$(hucre /tmp/gvt_uc4.html c-gider)"
ol "kip değişince Bağkur korundu"   "20.000,00"  "$(hucre /tmp/gvt_uc4.html c-bagkur)"
ol "kip veritabanında makbuz"       "makbuz" \
   "$($MDB -e "SELECT hesap_kipi FROM musavir_gelir_gider WHERE musavir_id=1 AND yil=2026;")"
# makbuz kipinde KDV yükümlülüğü makbuzlardan (110.000)
ol "makbuz kipinde KDV borcu 70.000" "70.000,00" "$(hucre /tmp/gvt_uc4.html c-kdv)"

# Geri ücret kipine
T=$(jeton $J "$B/gelir-vergisi/detay/1?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kip" \
  -d "csrf_beyanname=$T" -d "musavir_id=1" -d "yil=2026" -d "kip=ucret"
curl -s -b $J -o /tmp/gvt_uc5.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "geri dönünce hasılat 264.000" "264.000,00" "$(hucre /tmp/gvt_uc5.html c-hasilat)"

# --- Ücreti olmayan müşavirde çökme yok -------------------------------
$MDBR -e "DELETE FROM mukellef_ucretleri WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE musavir_id=2);" >/dev/null
T=$(jeton $J "$B/gelir-vergisi/detay/2?yil=2026")
curl -s -b $J -c $J -o /dev/null -X POST "$B/gelir-vergisi/kip" \
  -d "csrf_beyanname=$T" -d "musavir_id=2" -d "yil=2026" -d "kip=ucret"
c=$(curl -s -b $J -o /tmp/gvt_uc6.html -w "%{http_code}" "$B/gelir-vergisi/detay/2?yil=2026")
ol "ücretsiz müşavirde sayfa açılır" "200" "$c"
ol "ücretsiz müşavirde hasılat 0"    "0,00" "$(hucre /tmp/gvt_uc6.html c-hasilat)"
ol "ücretsiz müşavirde döküm gizli"  "0" "$(grep -c 'id=\"ucret-dokum\"' /tmp/gvt_uc6.html)"

# --- Yazdırmada ücret dökümü ------------------------------------------
curl -s -b $J -o /tmp/gvt_ucz.html "$B/gelir-vergisi/yazdir/1?yil=2026"
ol "yazdırmada ücret dökümü tablosu" "1" \
   "$([ "$(grep -c 'Yıllık Sözleşme Ücretleri' /tmp/gvt_ucz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada projeksiyon dipnotu" "1" \
   "$([ "$(grep -c 'projeksiyon' /tmp/gvt_ucz.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada Bağkur sonrası sınır notu" "1" \
   "$([ "$(grep -c 'Bağ-Kur sonrası kazancın' /tmp/gvt_ucz.html)" -gt 0 ] && echo 1 || echo 0)"

# --- Yetki -------------------------------------------------------------
T=$(jeton $JM "$B/gelir-vergisi")
curl -s -b $JM -c $JM -o /dev/null -X POST "$B/gelir-vergisi/kip" \
  -d "csrf_beyanname=$T" -d "musavir_id=2" -d "yil=2026" -d "kip=makbuz"
ol "müşavir başkasının kipini değiştiremez" "1" \
   "$([ "$($MDB -e "SELECT COUNT(*) FROM musavir_gelir_gider WHERE musavir_id=2 AND hesap_kipi='makbuz';")" = "0" ] && echo 1 || echo 0)"
c=$(curl -s -b $JP -o /dev/null -w "%{http_code}" -X POST "$B/gelir-vergisi/kip" -d "musavir_id=1" -d "kip=makbuz")
ol "personel kip değiştiremez" "1" \
   "$([ "$c" = "302" ] || [ "$c" = "303" ] || [ "$c" = "403" ] && echo 1 || echo 0)"


# =====================================================================
echo; echo "=== 24) BAŞLIKLAR: VERGİ YÜKÜ + KALAN KDV BORCU ==="
# =====================================================================
curl -s -b $J -o /tmp/gvt_bl.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "hesap kartı 'Vergi Yükü Hesabı'" "1" \
   "$([ "$(grep -c 'Yılı Vergi Yükü Hesabı' /tmp/gvt_bl.html)" -gt 0 ] && echo 1 || echo 0)"
ol "eski 'Gelir Vergisi Hesabı' başlığı YOK" "0" \
   "$(grep -c 'Yılı Gelir Vergisi Hesabı' /tmp/gvt_bl.html | head -1)"
ol "sol menü 'Vergi Yükü'" "1" \
   "$([ "$(grep -c '>Vergi Yükü' /tmp/gvt_bl.html)" -gt 0 ] && echo 1 || echo 0)"

curl -s -b $J -o /tmp/gvt_bl2.html "$B/gelir-vergisi?yil=2026"
ol "liste başlığı 'Vergi Yükü Hesabı'" "1" \
   "$([ "$(grep -c 'Yılı Vergi Yükü Hesabı' /tmp/gvt_bl2.html)" -gt 0 ] && echo 1 || echo 0)"
ol "liste sütunu 'Kalan KDV Borcu'" "1" \
   "$([ "$(grep -c 'Kalan KDV Borcu' /tmp/gvt_bl2.html)" -gt 0 ] && echo 1 || echo 0)"
ol "listede eski 'Ödenen KDV' başlığı YOK" "0" \
   "$(grep -c '>Ödenen KDV<' /tmp/gvt_bl2.html | head -1)"

# Kaynak sütunu kipe göre rozet gösterir
# Rozet metni çok satırlı basılır ("📅 3 ücret" + satır sonu + </span>);
# bu yüzden yalnız desen aranır, kapanış etiketi beklenmez.
ol "listede kaynak rozeti (ücret)" "1" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_bl2.html',encoding='utf-8').read()
print(1 if re.search(r'📅\s*\d+\s*ücret', h) else 0)")"

curl -s -b $J -o /tmp/gvt_bl3.html "$B/gelir-vergisi/yazdir/1?yil=2026"
ol "yazdırma başlığı 'Vergi Yükü Hesabı'" "1" \
   "$([ "$(grep -c 'Vergi Yükü Hesabı' /tmp/gvt_bl3.html)" -gt 0 ] && echo 1 || echo 0)"
ol "yazdırmada 'Vergi Yükü Dökümü'" "1" \
   "$([ "$(grep -c 'Vergi Yükü Dökümü' /tmp/gvt_bl3.html)" -gt 0 ] && echo 1 || echo 0)"

curl -s -b $J -o /tmp/gvt_bl4.html "$B/gelir-vergisi/liste-yazdir?yil=2026"
ol "liste yazdırma 'Vergi Yükü Özeti'" "1" \
   "$([ "$(grep -c 'Vergi Yükü Özeti' /tmp/gvt_bl4.html)" -gt 0 ] && echo 1 || echo 0)"

# =====================================================================
echo; echo "=== 25) ÜCRET DÖKÜMÜ: KATLANIR + SAYFALAMA ==="
# =====================================================================
# Kart varsayılan KAPALI gelmeli — sayfa uzamasın
ol "ücret kartı varsayılan kapalı" "1" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_bl.html',encoding='utf-8').read()
m=re.search(r'id=\"uc-govde\"[^>]*>', h)
print(1 if m and 'display:none' in m.group(0) else 0)")"
ol "kart başlığı tıklanabilir (aria)" "1" \
   "$([ "$(grep -c 'aria-controls=\"uc-govde\"' /tmp/gvt_bl.html)" -gt 0 ] && echo 1 || echo 0)"
ol "başlıkta özet var (mükellef sayısı)" "1" \
   "$([ "$(grep -c 'uc-ac-yazi' /tmp/gvt_bl.html)" -gt 0 ] && echo 1 || echo 0)"

# Ücret dökümü SAYFANIN SONUNDA olmalı (KDV ve gider tablolarından sonra)
ol "ücret dökümü KDV tablosundan SONRA" "1" \
   "$(python3 -c "
h=open('/tmp/gvt_bl.html',encoding='utf-8').read()
print(1 if h.index('id=\"ucret-dokum\"') > h.index('id=\"kdv-form\"') else 0)")"
ol "ücret dökümü gider tablosundan SONRA" "1" \
   "$(python3 -c "
h=open('/tmp/gvt_bl.html',encoding='utf-8').read()
print(1 if h.index('id=\"ucret-dokum\"') > h.index('id=\"agider-form\"') else 0)")"

# --- Çok mükellefli senaryo: sayfalama araçları görünmeli -------------
$MDBR -e "
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif)
SELECT 100+n, 1, CONCAT('T',n), CONCAT('TEST FIRMA ',n), 'tuzel',
       LPAD(9000000000+n,10,'0'), 'bilanco', '2020-01-01', 1
FROM (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
      UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
      UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
      UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
      UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25
      UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30) x;
INSERT INTO mukellef_ucretleri (mukellef_id,yil,tutar)
SELECT id, 2026, 10000 FROM mukellefler WHERE id BETWEEN 101 AND 130;
" >/dev/null 2>&1

curl -s -b $J -o /tmp/gvt_cok.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "33 mükellef listelendi" "33" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_cok.html',encoding='utf-8').read()
print(len(re.findall(r'data-uc-satir=', h)))")"
ol "sayfalama araçları göründü (>25 satır)" "1" \
   "$([ "$(grep -c 'id=\"uc-onceki\"' /tmp/gvt_cok.html)" -gt 0 ] && echo 1 || echo 0)"
ol "arama kutusu var" "1" \
   "$([ "$(grep -c 'id=\"uc-ara\"' /tmp/gvt_cok.html)" -gt 0 ] && echo 1 || echo 0)"
ol "sayfa adedi 25" "25" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_cok.html',encoding='utf-8').read()
m=re.search(r'data-sayfa-adet=\"(\d+)\"', h)
print(m.group(1) if m else 'YOK')")"
ol "arama için ad verisi var" "33" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_cok.html',encoding='utf-8').read()
print(len(re.findall(r'data-ad=', h)))")"
ol "'bulunamadı' satırı hazır" "1" \
   "$([ "$(grep -c 'id=\"uc-bos\"' /tmp/gvt_cok.html)" -gt 0 ] && echo 1 || echo 0)"

# Hasılat da 33 mükellefi kapsamalı
ol "hasılat 33 mükellefi kapsıyor" "1" \
   "$([ "$(grep -c '33 mükellefin yıllık sözleşme ücreti' /tmp/gvt_cok.html)" -gt 0 ] && echo 1 || echo 0)"

# YAZDIRMADA sayfalama YOK — tüm satırlar çıkmalı
curl -s -b $J -o /tmp/gvt_cokz.html "$B/gelir-vergisi/yazdir/1?yil=2026"
ol "yazdırmada 33 satırın TAMAMI" "33" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_cokz.html',encoding='utf-8').read()
b=re.search(r'Yıllık Sözleşme Ücretleri.*?</table>', h, re.S)
print(len(re.findall(r'<td class=\"orta kucuk\">\d+</td>', b.group(0))) if b else 0)")"
ol "yazdırmada sayfalama düğmesi YOK" "0" \
   "$(grep -c 'uc-onceki' /tmp/gvt_cokz.html | head -1)"

# 25 ve altında sayfalama araçları gizli olmalı
$MDBR -e "DELETE FROM mukellefler WHERE id BETWEEN 101 AND 130;" >/dev/null 2>&1
curl -s -b $J -o /tmp/gvt_az.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "3 mükellefte sayfalama gizli" "0" \
   "$(grep -c 'id=\"uc-onceki\"' /tmp/gvt_az.html | head -1)"
ol "3 mükellefte kart yine katlanır" "1" \
   "$([ "$(grep -c 'aria-controls=\"uc-govde\"' /tmp/gvt_az.html)" -gt 0 ] && echo 1 || echo 0)"


# =====================================================================
echo; echo "=== 26) 'NET MAHSUP' ARA SATIRI KALDIRILDI ==="
# =====================================================================
# Kavramsal olarak yanıltıcıydı: KDV bir BORÇTUR, "mahsup" (alacak) değil.
# Sonuç DEĞİŞMEZ; yalnız ara satır gider, kırılım satırları kalır.
curl -s -b $J -o /tmp/gvt_nm.html "$B/gelir-vergisi/detay/1?yil=2026"
ol "ekranda 'Net Mahsup' başlığı YOK" "0" \
   "$(grep -c 'Net Mahsup (stopaj' /tmp/gvt_nm.html | head -1)"
ol "GV dengesi kırılımı duruyor" "1" \
   "$([ "$(grep -c 'gv-denge-etiket' /tmp/gvt_nm.html)" -gt 0 ] && echo 1 || echo 0)"
ol "KDV kırılımı duruyor" "1" \
   "$([ "$(grep -c 'gv-kdvk-etiket' /tmp/gvt_nm.html)" -gt 0 ] && echo 1 || echo 0)"
# AJAX canlı hesap bu id'yi güncellediği için değer gizli olarak KORUNUR
ol "c-mahsup_toplam gizli olarak duruyor" "1" \
   "$([ "$(grep -c 'id=\"c-mahsup_toplam\"' /tmp/gvt_nm.html)" -gt 0 ] && echo 1 || echo 0)"
ol "gizli satır display:none" "1" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_nm.html',encoding='utf-8').read()
m=re.search(r'<tr style=\"display:none\">\s*<td>ara toplam \(gizli', h, re.S)
print(1 if m else 0)")"
# Kırılım satırlarında artık rakamlı açıklama var
ol "GV kırılımında rakamlı açıklama" "1" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_nm.html',encoding='utf-8').read()
b=re.search(r'id=\"gv-denge-etiket\".*?</td>', h, re.S)
print(1 if b and 'vergi' in b.group(0) and 'stopaj' in b.group(0) else 0)")"
ol "KDV kırılımında rakamlı açıklama" "1" \
   "$(python3 -c "
import re
h=open('/tmp/gvt_nm.html',encoding='utf-8').read()
b=re.search(r'id=\"gv-kdvk-etiket\".*?</td>', h, re.S)
print(1 if b and 'ödenen' in b.group(0) else 0)")"

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
