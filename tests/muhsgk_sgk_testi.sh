#!/bin/bash
# =====================================================================
#  MUHSGK ↔ SGK BİRLEŞİK GİRİŞ — REGRESYON TESTİ
#
#  Sigortalı işçi çalıştıran mükelleflerde MUHSGK ile SGK birlikte
#  verilir. Bu test, iki satırın tek ekrandan yönetilmesini doğrular:
#
#    1) Eşleşme tespiti (aynı mükellef + kesişen dönem)
#    2) MUHSGK onayı → SGK da kendiliğinden onaylanır
#    3) MUHSGK geri alınır → SGK'ya DOKUNULMAZ, kullanıcıya sorulur
#    4) Tek istekte iki tahakkuk (MUHSGK + SGK primi)
#    5) SGK tek başına onaylanırsa UYARI (engelleme yok)
#    6) Üç aylık MUHSGK → birden çok SGK satırı
#    7) Çizelge rozetleri
#    8) Yetki ve geriye dönük uyum
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 ve musavir/Test1234 mevcut.
#  Not: Test kendi verisini kurar; başka testlerden bağımsız çalışır.
#  Kullanım:  bash tests/muhsgk_sgk_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/ms.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

# JSON yanıtı development ortamında GİRİNTİLİ basılır ("durum": true);
# bu yüzden karşılaştırmadan önce tüm boşluklar atılır.
var(){ if echo "$2" | tr -d ' \n\t' | grep -q "$(echo "$3" | tr -d ' ')"; then
         echo "  [OK] $1"; g=$((g+1));
       else echo "  [HATA] $1 (bulunamadı: $3)"; k=$((k+1)); fi }
yok(){ if echo "$2" | tr -d ' \n\t' | grep -q "$(echo "$3" | tr -d ' ')"; then
         echo "  [HATA] $1 (olmamalıydı: $3)"; k=$((k+1));
       else echo "  [OK] $1"; g=$((g+1)); fi }

# ---------------------------------------------------------------------
#  TEST VERİSİ
#   Mükellef 1 : MUHSGK Aylık  + SGK   (ana senaryo)
#   Mükellef 2 : MUHSGK 3Aylık + SGK   (bir MUHSGK → üç SGK)
#   Mükellef 3 : yalnız KDV            (eşleşme OLMAMALI)
#   Mükellef 4 : başka büro            (yetki denetimi)
# ---------------------------------------------------------------------
veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM beyanname_takip; ALTER TABLE beyanname_takip AUTO_INCREMENT=1;
DELETE FROM mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES
 (1,'SMMM','Ali Yılmaz','Yılmaz',1),(2,'SMMM','Veli Demir','Demir',1);
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'S001','SIGORTALI ISCI LTD.','tuzel','3000000001','bilanco','2019-01-01',1),
 (2,1,'S002','UC AYLIK MUHSGK LTD.','tuzel','3000000002','bilanco','2019-01-01',1),
 (3,1,'S003','SADECE KDV LTD.','tuzel','3000000003','bilanco','2019-01-01',1),
 (4,2,'S004','BASKA BURO LTD.','tuzel','3000000004','bilanco','2019-01-01',1);
-- Mükellef 1: MUHSGK Aylık + SGK
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
SELECT 1,id,1,NOW(),NOW() FROM beyanname_turleri WHERE kod IN ('MUHSGK_A','SGK','KDV1_A');
-- Mükellef 2: MUHSGK Üç Aylık + SGK
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
SELECT 2,id,1,NOW(),NOW() FROM beyanname_turleri WHERE kod IN ('MUHSGK_3A','SGK');
-- Mükellef 3: yalnız KDV
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
SELECT 3,id,1,NOW(),NOW() FROM beyanname_turleri WHERE kod='KDV1_A';
-- Mükellef 4: MUHSGK + SGK (başka büro)
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
SELECT 4,id,1,NOW(),NOW() FROM beyanname_turleri WHERE kod IN ('MUHSGK_A','SGK');"
}

veriKur

# ---------- giriş ----------
rm -f $J
curl -s -c $J -o /tmp/f.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=admin" -d "sifre=Test1234" $B/giris

# Dönemleri uygulamanın kendi motoruyla üret (elle SQL yazmak yerine)
for i in 1 2 3 4; do curl -s -b $J -o /dev/null "$B/mukellefler/donem-uret/$i?yil=2026"; done

# Sayfayı çek + CSRF tazele
tz(){ curl -s -b $J -c $J -o /tmp/ms_sayfa.html "$B/takip?yil=2026&ay=8";
      CS=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/ms_sayfa.html|head -1); }
aj(){ local u="$1"; shift
      curl -s -b $J -c $J -H 'X-Requested-With: XMLHttpRequest' "$@" \
           -d "csrf_beyanname=$CS" "$B/$u" | tr -d '\n' | tr -s ' '
      tz; }
tz

# Kayıt kimlikleri
MUH=$($MDB -e "select bt.id from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
               where bt.mukellef_id=1 and t.kod='MUHSGK_A' and bt.donem_baslangic='2026-07-01'")
SGK=$($MDB -e "select bt.id from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
               where bt.mukellef_id=1 and t.kod='SGK' and bt.donem_baslangic='2026-07-01'")
KDV=$($MDB -e "select bt.id from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
               where bt.mukellef_id=1 and t.kod='KDV1_A' and bt.donem_baslangic='2026-07-01'")

echo "=== 1) EŞLEŞME TESPİTİ ==="
ol "MUHSGK kaydı üretildi" "1" "$([ -n "$MUH" ] && echo 1 || echo 0)"
ol "SGK kaydı üretildi" "1" "$([ -n "$SGK" ] && echo 1 || echo 0)"
ol "İkisi de aynı dönemde" "1" \
   "$($MDB -e "select (a.donem_baslangic=b.donem_baslangic) from beyanname_takip a, beyanname_takip b
               where a.id=$MUH and b.id=$SGK")"

echo ""
echo "=== 2) ÇİZELGE ROZETLERİ ==="
ol "Sayfa açıldı, fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/ms_sayfa.html)"
ol "MUHSGK satırında '+ SGK' rozeti" "1" \
   "$(grep -c 'rozet mavi es-rozet' /tmp/ms_sayfa.html | awk '{print ($1>0)?1:0}')"
ol "SGK satırında 'bağlı' rozeti" "1" \
   "$(grep -c 'rozet gri es-rozet' /tmp/ms_sayfa.html | awk '{print ($1>0)?1:0}')"
# Rozet sayımı SAYFADAKİ satırlarla karşılaştırılmalı.
# (Çizelge ay=8 filtresiyle açılır; tüm yılı saymak yanlış alarm üretiyordu.)
ol "Rozet sayısı = sayfadaki eşleşen satır sayısı" \
   "$($MDB -e "select count(*) from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
               where t.kod in ('MUHSGK_A','MUHSGK_3A','SGK')
                 and YEAR(bt.son_tarih)=2026 and MONTH(bt.son_tarih)=8")" \
   "$(grep -c 'es-rozet' /tmp/ms_sayfa.html)"

# Yalnız KDV'si olan mükellefin satırında rozet OLMAMALI
ol "KDV satırında eşleşme rozeti yok" "0" \
   "$(tr '\n' ' ' < /tmp/ms_sayfa.html | grep -oE 'KDV1[^<]*</span> *<span class="rozet [a-z]+ es-rozet' | wc -l)"

echo ""
echo "=== 3) MUHSGK ONAYI → SGK DA ONAYLANIR ==="
R=$(aj "takip/durum" -d "id=$MUH" -d "durum=ONAYLANDI")
var "Yanıt başarılı" "$R" '"durum":true'
var "es_var bildirildi" "$R" '"es_var":true'
var "rol=ana" "$R" '"es_rol":"ana"'
var "SGK güncellendi bilgisi döndü" "$R" '"es_guncellenen"'
ol "SGK veritabanında ONAYLANDI" "ONAYLANDI" "$($MDB -e "select durum from beyanname_takip where id=$SGK")"
ol "SGK onay tarihi yazıldı" "1" \
   "$($MDB -e "select (onay_tarihi is not null) from beyanname_takip where id=$SGK")"

# KDV satırı ETKİLENMEMELİ
ol "İlgisiz KDV satırı bekliyor" "BEKLIYOR" "$($MDB -e "select durum from beyanname_takip where id=$KDV")"

echo ""
echo "=== 4) TEK EKRANDAN İKİ TAHAKKUK ==="
R=$(aj "odeme/tahakkuk" -d "id=$MUH" -d "tutar=1.234,56" -d "fis_no=MUH-1" \
       -d "sgk_tutar=4.500,00" -d "sgk_fis_no=SGK-1")
var "Tahakkuk kaydedildi" "$R" '"durum":true'
var "SGK tarafı yazıldı" "$R" '"yazildi":true'
var "SGK tutarı yanıtta" "$R" '"tutar_f":"4.500,00"'
ol "MUHSGK tutarı DB'de" "1234.56" "$($MDB -e "select tahakkuk_tutari from beyanname_takip where id=$MUH")"
ol "SGK tutarı DB'de" "4500.00" "$($MDB -e "select tahakkuk_tutari from beyanname_takip where id=$SGK")"
ol "MUHSGK fiş no" "MUH-1" "$($MDB -e "select tahakkuk_fis_no from beyanname_takip where id=$MUH")"
ol "SGK fiş no" "SGK-1" "$($MDB -e "select tahakkuk_fis_no from beyanname_takip where id=$SGK")"
ol "Aylık MUHSGK'da kalan SGK satırı yok" "1" \
   "$(echo "$R" | tr -d ' \n' | grep -c '\"kalan\":0')"

# Türkçe binlik ayırıcı doğru çözülmeli: 4.500,00 = dört bin beş yüz
ol "Binlik ayırıcı doğru okundu (4500 ≠ 4,5)" "1" \
   "$($MDB -e "select (tahakkuk_tutari = 4500.00) from beyanname_takip where id=$SGK")"

echo ""
echo "=== 5) SGK ALANI GÖNDERİLMEZSE EŞ KAYDA DOKUNULMAZ ==="
R=$(aj "odeme/tahakkuk" -d "id=$MUH" -d "tutar=2.000,00")
yok "sgk anahtarı yanıtta yok" "$R" '"sgk"'
ol "SGK tutarı DEĞİŞMEDİ" "4500.00" "$($MDB -e "select tahakkuk_tutari from beyanname_takip where id=$SGK")"
ol "MUHSGK tutarı güncellendi" "2000.00" "$($MDB -e "select tahakkuk_tutari from beyanname_takip where id=$MUH")"

echo ""
echo "=== 6) SGK ALANI BOŞ GÖNDERİLİRSE TEMİZLENİR ==="
R=$(aj "odeme/tahakkuk" -d "id=$MUH" -d "tutar=2.000,00" -d "sgk_tutar=")
var "Silindi bilgisi döndü" "$R" '"silindi":true'
ol "SGK tutarı temizlendi" "NULL" \
   "$($MDB -e "select ifnull(tahakkuk_tutari,'NULL') from beyanname_takip where id=$SGK")"
# Geri yükle
aj "odeme/tahakkuk" -d "id=$MUH" -d "tutar=2.000,00" -d "sgk_tutar=4.500,00" >/dev/null

echo ""
echo "=== 7) MUHSGK GERİ ALINIR → SGK'YA DOKUNULMAZ, SORULUR ==="
R=$(aj "takip/durum" -d "id=$MUH" -d "durum=HAZIR")
var "es_geri_sor döndü" "$R" '"es_geri_sor"'
var "Sorulacak kayıt listelendi" "$R" '"tur":"SGK"'
yok "Kendiliğinden güncelleme YOK" "$R" '"es_guncellenen"'
ol "SGK hâlâ ONAYLANDI (dokunulmadı)" "ONAYLANDI" "$($MDB -e "select durum from beyanname_takip where id=$SGK")"

echo ""
echo "=== 8) KULLANICI ONAYIYLA SGK GERİ ALINIR ==="
R=$(aj "takip/es-durum" -d "idler[]=$SGK" -d "durum=HAZIR")
var "Yanıt başarılı" "$R" '"durum":true'
var "Güncellenen kayıt döndü" "$R" '"durum":"HAZIR"'
ol "SGK artık HAZIR" "HAZIR" "$($MDB -e "select durum from beyanname_takip where id=$SGK")"
ol "SGK tahakkuku KORUNDU" "4500.00" "$($MDB -e "select tahakkuk_tutari from beyanname_takip where id=$SGK")"

echo ""
echo "=== 9) SGK TEK BAŞINA ONAYLANIRSA UYARI (engelleme YOK) ==="
R=$(aj "takip/durum" -d "id=$SGK" -d "durum=ONAYLANDI")
var "İşlem BAŞARILI (engellenmedi)" "$R" '"durum":true'
var "rol=bagli" "$R" '"es_rol":"bagli"'
var "Uyarı metni döndü" "$R" '"es_uyari"'
ol "SGK gerçekten onaylandı" "ONAYLANDI" "$($MDB -e "select durum from beyanname_takip where id=$SGK")"
ol "MUHSGK'ya dokunulmadı" "HAZIR" "$($MDB -e "select durum from beyanname_takip where id=$MUH")"

# MUHSGK zaten onaylıyken SGK onaylanırsa uyarı ÇIKMAMALI
aj "takip/durum" -d "id=$MUH" -d "durum=ONAYLANDI" >/dev/null
R=$(aj "takip/durum" -d "id=$SGK" -d "durum=ONAYLANDI")
yok "MUHSGK onaylıyken gereksiz uyarı yok" "$R" '"es_uyari"'

echo ""
echo "=== 10) ÜÇ AYLIK MUHSGK → ÇOK SAYIDA SGK ==="
M3=$($MDB -e "select bt.id from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
              where bt.mukellef_id=2 and t.kod='MUHSGK_3A' order by bt.donem_baslangic limit 1")
M3BAS=$($MDB -e "select donem_baslangic from beyanname_takip where id=$M3")
M3BIT=$($MDB -e "select donem_bitis from beyanname_takip where id=$M3")
ES3=$($MDB -e "select count(*) from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
               where bt.mukellef_id=2 and t.kod='SGK'
                 and bt.donem_baslangic<='$M3BIT' and bt.donem_bitis>='$M3BAS'")
ol "Üç aylık dönemde 3 SGK satırı var" "3" "$ES3"

R=$(aj "takip/durum" -d "id=$M3" -d "durum=ONAYLANDI")
ol "Üç SGK satırı da onaylandı" "3" \
   "$($MDB -e "select count(*) from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
               where bt.mukellef_id=2 and t.kod='SGK' and bt.durum='ONAYLANDI'
                 and bt.donem_baslangic<='$M3BIT' and bt.donem_bitis>='$M3BAS'")"

# Tutar İLK satıra yazılır, kalan sayısı bildirilir (bölme YAPILMAZ)
R=$(aj "odeme/tahakkuk" -d "id=$M3" -d "tutar=3.000,00" -d "sgk_tutar=9.000,00")
var "Kalan SGK satırı bildirildi" "$R" '"kalan":2'
ol "Tutar yalnız BİR satıra yazıldı" "1" \
   "$($MDB -e "select count(*) from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
               where bt.mukellef_id=2 and t.kod='SGK' and bt.tahakkuk_tutari is not null")"
ol "Program tutarı kendiliğinden BÖLMEDİ" "9000.00" \
   "$($MDB -e "select tahakkuk_tutari from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
               where bt.mukellef_id=2 and t.kod='SGK' and bt.tahakkuk_tutari is not null limit 1")"

echo ""
echo "=== 11) EŞLEŞMESİ OLMAYAN TÜRLER ETKİLENMEZ ==="
K3=$($MDB -e "select bt.id from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
              where bt.mukellef_id=3 and t.kod='KDV1_A' limit 1")
R=$(aj "takip/durum" -d "id=$K3" -d "durum=ONAYLANDI")
yok "KDV satırında es_var yok" "$R" '"es_var":true'
R=$(aj "odeme/tahakkuk" -d "id=$K3" -d "tutar=500,00" -d "sgk_tutar=100,00")
var "KDV'de SGK yazılmadı" "$R" '"yazildi":false'
var "Neden açıklandı" "$R" 'MUHSGK değil'

echo ""
echo "=== 12) YETKİ ==="
# musavir kullanıcısını müşavir 1'e bağla; müşavir 2'nin kaydına dokunamamalı
$MDBR -e "UPDATE kullanicilar SET musavir_id=1 WHERE kullanici_adi='musavir';
          DELETE FROM kullanici_musavirleri
           WHERE kullanici_id=(SELECT id FROM kullanicilar WHERE kullanici_adi='musavir');"
M4=$($MDB -e "select bt.id from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
              where bt.mukellef_id=4 and t.kod='MUHSGK_A' limit 1")
S4=$($MDB -e "select bt.id from beyanname_takip bt join beyanname_turleri t on t.id=bt.beyanname_turu_id
              where bt.mukellef_id=4 and t.kod='SGK' limit 1")
J2=/tmp/ms2.txt; rm -f $J2
curl -s -c $J2 -o /tmp/f2.html $B/giris
T2=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f2.html|head -1)
curl -s -b $J2 -c $J2 -o /dev/null -d "csrf_beyanname=$T2" -d "kimlik=musavir" -d "sifre=Test1234" $B/giris
curl -s -b $J2 -c $J2 -o /tmp/ms_m.html "$B/takip?yil=2026&ay=8"
CS2=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/ms_m.html|head -1)
KOD=$(curl -s -b $J2 -c $J2 -o /dev/null -w '%{http_code}' -X POST "$B/takip/durum" \
      -H 'X-Requested-With: XMLHttpRequest' -d "csrf_beyanname=$CS2" -d "id=$M4" -d "durum=ONAYLANDI")
ol "Başka büronun MUHSGK'sı reddedildi" "403" "$KOD"
ol "Eş SGK de değişmedi" "BEKLIYOR" "$($MDB -e "select durum from beyanname_takip where id=$S4")"

# es-durum ucu da yetki denetler
curl -s -b $J2 -c $J2 -o /tmp/ms_m.html "$B/takip?yil=2026&ay=8"
CS2=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/ms_m.html|head -1)
curl -s -b $J2 -c $J2 -o /dev/null -X POST "$B/takip/es-durum" \
     -H 'X-Requested-With: XMLHttpRequest' -d "csrf_beyanname=$CS2" \
     -d "idler[]=$S4" -d "durum=ONAYLANDI"
ol "es-durum yetkisiz kaydı atladı" "BEKLIYOR" "$($MDB -e "select durum from beyanname_takip where id=$S4")"

echo ""
echo "=== 13) KOD SAĞLAMLIĞI / GERİYE DÖNÜK UYUM ==="
ol "Eşleşme tür kodları sabitte tanımlı" "1" \
   "$(grep -c 'MUHSGK_KODLARI' app/Models/BeyannameTakipModel.php | awk '{print ($1>0)?1:0}')"
ol "Satır parçası esHarita varsayılanı koyuyor" "1" \
   "$(grep -c 'esHarita = \$esHarita ?? \[\]' app/Views/takip/_satirlar.php)"
ol "esDurumIsle tahakkuk yetkisinden bağımsız" "1" \
   "$(grep -c 'try { esDurumIsle(j); }' app/Views/takip/index.php)"
ol "SGK penceresi eski şablonda çökmüyor" "1" \
   "$(grep -cF 'if (!kutu) { return; }' app/Views/takip/index.php)"
ol "es-durum rotası tanımlı" "1" "$(grep -cF 'es-durum' app/Config/Routes.php)"
ol "cizelgeKaydi tür bilgisi getiriyor" "1" \
   "$(grep -c 'function cizelgeKaydi' app/Models/BeyannameTakipModel.php)"
ol "Şema değişikliği GEREKMİYOR (migration yok)" "0" \
   "$(ls database/ | grep -c 'muhsgk' )"

# ---------------------------------------------------------------------
#  TEMİZLİK — test kendi izini siler
# ---------------------------------------------------------------------
$MDBR -e "UPDATE kullanicilar SET musavir_id=2 WHERE kullanici_adi='musavir';" >/dev/null 2>&1

echo ""
echo "====================================="
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "====================================="
[ $k -eq 0 ] && exit 0 || exit 1
