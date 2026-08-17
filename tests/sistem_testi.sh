#!/bin/bash
# =====================================================================
#  SİSTEM MODÜLÜ REGRESYON TESTİ
#  Yedekleme · Geri yükleme · Toplu silme · Çöp kutusu · Yetki
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin / personel / musavir kullanıcıları (şifre Test1234) var.
#  Kullanım:  bash tests/sistem_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock"
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Sayfayı çekip form token'ını döndürür
tok(){ curl -s -b "$1" -c "$1" -o /tmp/f.html "$2"; grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1; }
mtok(){ curl -s -b "$1" -c "$1" -o /tmp/f.html "$2"; grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/f.html|head -1; }

# ---- temiz başlangıç verisi ----
veriKur(){
$MDBR beyanname_takip -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE evrak_takip; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','ÖZKAN İNŞAAT LTD. ŞTİ.','tuzel','1112223334',NULL,'bilanco','2021-01-01',1),
 (2,1,'M002','AYŞE ÇELİK','gercek',NULL,'22233344455','isletme','2022-06-15',1),
 (3,1,'M003','MEHMET KAYA','gercek',NULL,'33344455566','serbest_meslek','2020-03-01',1),
 (4,2,'M004','DEMİR TİCARET A.Ş.','tuzel','5556667778',NULL,'bilanco','2019-01-01',1);
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif) VALUES (1,1,1),(2,1,1),(3,1,1),(4,1,1);
INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,created_at,updated_at) VALUES
 (1,1,2025,1,'Ocak 2025','2025-01-01','2025-01-31','2025-02-28','2025-02-28','ONAYLANDI',NOW(),NOW()),
 (1,1,2025,2,'Şubat 2025','2025-02-01','2025-02-28','2025-03-28','2025-03-28','ONAYLANDI',NOW(),NOW()),
 (1,1,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','BEKLIYOR',NOW(),NOW()),
 (2,1,2025,1,'Ocak 2025','2025-01-01','2025-01-31','2025-02-28','2025-02-28','HAZIR',NOW(),NOW()),
 (2,1,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','BEKLIYOR',NOW(),NOW()),
 (3,1,2025,3,'Mart 2025','2025-03-01','2025-03-31','2025-04-28','2025-04-28','VERILMEYECEK',NOW(),NOW()),
 (4,1,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','BEKLIYOR',NOW(),NOW());
UPDATE beyanname_takip SET tahakkuk_tutari=1500.50, damga_tutari=791 WHERE id=1;
INSERT INTO evrak_takip (mukellef_id,evrak_turu_id,yil,ay,durum,created_at,updated_at) VALUES
 (1,1,2025,1,'GELDI',NOW(),NOW()),(1,1,2025,2,'GELMEDI',NOW(),NOW()),(2,1,2026,7,'GELDI',NOW(),NOW());"
}

veriKur
giris admin /tmp/s_admin.txt
A=/tmp/s_admin.txt

echo "=== 1) SAYFA ERİŞİMİ (yönetici) ==="
for u in sistem/yedekleme sistem/geri-yukleme sistem/veri-yonetimi sistem/cop-kutusu; do
  ol "GET $u" "200" "$(curl -s -b $A -o /dev/null -w '%{http_code}' $B/$u)"
done

echo ""
echo "=== 2) YEDEK ALMA ==="
T=$(tok $A $B/sistem/yedekleme)
TBL=$(grep -oP 'name="tablolar\[\]" class="tablo-sec"\s+value="\K[^"]+' /tmp/f.html | sed 's/^/-d tablolar[]=/' | tr '\n' ' ')
curl -s -b $A -c $A -o /tmp/s_yedek.sql -D /tmp/s_h.txt -d "csrf_beyanname=$T" $TBL $B/sistem/yedek-indir
ol "Content-Disposition attachment" "1" "$(grep -ci 'content-disposition: attachment' /tmp/s_h.txt)"
ol ".sql uzantılı dosya adı" "1" "$(grep -ci 'filename=\"yedek_.*\.sql\"' /tmp/s_h.txt)"
ol "Dosya boş değil" "1" "$([ $(wc -c < /tmp/s_yedek.sql) -gt 10000 ] && echo 1 || echo 0)"
ol "CREATE TABLE var" "1" "$(grep -c 'CREATE TABLE' /tmp/s_yedek.sql | awk '{print ($1>0)?1:0}')"
ol "INSERT INTO var" "1" "$(grep -c 'INSERT INTO' /tmp/s_yedek.sql | awk '{print ($1>0)?1:0}')"
ol "FK kontrolü kapatılıyor" "1" "$(grep -c 'SET FOREIGN_KEY_CHECKS = 0' /tmp/s_yedek.sql)"
ol "Türkçe karakter korunmuş" "1" "$(grep -c 'ÖZKAN İNŞAAT' /tmp/s_yedek.sql | awk '{print ($1>0)?1:0}')"
# Not: tablo sayısı sürümle birlikte artar (e-defter modülü +3 tablo getirdi).
# Sabit sayı yerine CANLI ŞEMADAKİ tablo sayısı ile karşılaştırılır.
CANLI_TABLO=$($MDB -e "select count(*) from information_schema.tables where table_schema='beyanname_takip' and table_type='BASE TABLE'")
ol "Tüm tablolar yedeklendi ($CANLI_TABLO)" "$CANLI_TABLO" "$(grep -c '^CREATE TABLE' /tmp/s_yedek.sql)"

echo ""
echo "=== 3) YEDEK BAŞKA VERİTABANINA YÜKLENEBİLİYOR MU ==="
$MDBR -e "DROP DATABASE IF EXISTS yedek_dogrulama; CREATE DATABASE yedek_dogrulama CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$MDBR yedek_dogrulama < /tmp/s_yedek.sql 2>/tmp/s_err.txt
ol "Yükleme hatasız" "0" "$(grep -ci 'error' /tmp/s_err.txt)"
ol "Tablo sayısı ($CANLI_TABLO)" "$CANLI_TABLO" "$($MDBR -N -B -e "select count(*) from information_schema.tables where table_schema='yedek_dogrulama'")"
for t in mukellefler beyanname_takip evrak_takip kullanicilar tatiller; do
  ol "  $t satır sayısı aynı" "$($MDB -e "select count(*) from $t")" \
     "$($MDBR yedek_dogrulama -N -B -e "select count(*) from $t")"
done
ol "Türkçe karakter" "ÖZKAN İNŞAAT LTD. ŞTİ." "$($MDBR yedek_dogrulama -N -B -e 'select unvan from mukellefler where id=1')"
ol "Ondalık değer" "1500.50" "$($MDBR yedek_dogrulama -N -B -e 'select tahakkuk_tutari from beyanname_takip where id=1')"
ol "NULL korundu" "NULL" "$($MDBR yedek_dogrulama -N -B -e 'select ifnull(tahakkuk_tutari,"NULL") from beyanname_takip where id=2')"
$MDBR -e "DROP DATABASE yedek_dogrulama;"

echo ""
echo "=== 4) YALNIZCA ŞEMA YEDEĞİ ==="
T=$(tok $A $B/sistem/yedekleme)
curl -s -b $A -c $A -o /tmp/s_sema.sql -d "csrf_beyanname=$T" -d "tablolar[]=mukellefler" -d "sema_only=1" $B/sistem/yedek-indir
ol "CREATE TABLE var" "1" "$(grep -c 'CREATE TABLE' /tmp/s_sema.sql)"
ol "INSERT yok" "0" "$(grep -c 'INSERT INTO' /tmp/s_sema.sql)"

echo ""
echo "=== 5) GERİ YÜKLEME GÜVENLİĞİ ==="
$MDB -e "UPDATE mukellefler SET unvan='BOZULDU' WHERE id=1; DELETE FROM mukellefler WHERE id=4;"
T=$(tok $A $B/sistem/geri-yukleme)
curl -s -b $A -c $A -L -o /tmp/r.html -F "csrf_beyanname=$T" -F "yedek=@/tmp/s_yedek.sql" -F "onay=evet" $B/sistem/geri-yukle
ol "Yanlış onay reddedildi" "1" "$(grep -c 'Onay metnini doğru yazmadınız' /tmp/r.html)"
ol "  veri değişmedi" "3" "$($MDB -e 'select count(*) from mukellefler')"

printf 'CREATE TABLE x(a int);\nDROP DATABASE beyanname_takip;\n' > /tmp/s_kotu.sql
T=$(tok $A $B/sistem/geri-yukleme)
curl -s -b $A -c $A -L -o /tmp/r.html -F "csrf_beyanname=$T" -F "yedek=@/tmp/s_kotu.sql" -F "onay=GERİ YÜKLE" $B/sistem/geri-yukle
ol "DROP DATABASE reddedildi" "1" "$(grep -c 'izin verilmeyen bir SQL' /tmp/r.html)"
ol "  veritabanı duruyor" "1" "$($MDBR -N -B -e "select count(*) from information_schema.schemata where schema_name='beyanname_takip'")"

printf 'merhaba dunya, bu sql degil\n' > /tmp/s_metin.sql
T=$(tok $A $B/sistem/geri-yukleme)
curl -s -b $A -c $A -L -o /tmp/r.html -F "csrf_beyanname=$T" -F "yedek=@/tmp/s_metin.sql" -F "onay=GERİ YÜKLE" $B/sistem/geri-yukle
ol "SQL olmayan dosya reddedildi" "1" "$(grep -c 'SQL yedeği gibi görünmüyor' /tmp/r.html)"

echo ""
echo "=== 6) GERÇEK GERİ YÜKLEME ==="
T=$(tok $A $B/sistem/geri-yukleme)
curl -s -b $A -c $A -o /dev/null -D /tmp/s_h.txt -F "csrf_beyanname=$T" -F "yedek=@/tmp/s_yedek.sql" -F "onay=GERİ YÜKLE" $B/sistem/geri-yukle
ol "Çıkışa yönlendirildi" "1" "$(grep -ci 'location:.*cikis' /tmp/s_h.txt)"
ol "Mükellef sayısı geri geldi" "4" "$($MDB -e 'select count(*) from mukellefler')"
ol "Bozulan kayıt düzeldi" "ÖZKAN İNŞAAT LTD. ŞTİ." "$($MDB -e 'select unvan from mukellefler where id=1')"
ol "Beyanname sayısı" "7" "$($MDB -e 'select count(*) from beyanname_takip')"
ol "Tahakkuk korundu" "1500.50" "$($MDB -e 'select tahakkuk_tutari from beyanname_takip where id=1')"
curl -s -b $A -c $A -o /dev/null $B/cikis
ol "Oturum kapandı" "302" "$(curl -s -b $A -o /dev/null -w '%{http_code}' $B/panel)"

giris admin /tmp/s_admin.txt

echo ""
echo "=== 7) MÜKELLEF TOPLU SİLME (çöp kutusuna) ==="
CT=$(mtok $A $B/mukellefler)
ol "Listede seçim kutusu var" "4" "$(grep -c 'class=\"muk-sec\"' /tmp/f.html)"
R=$(curl -s -b $A -c $A -H "X-Requested-With: XMLHttpRequest" -d "csrf_beyanname=$CT" -d "idler[]=2" -d "idler[]=3" $B/sistem/mukellef-toplu-sil | tr -d '\n')
ol "AJAX başarılı" "1" "$(echo "$R" | grep -c '\"durum\": *true')"
ol "Faal mükellef" "2" "$($MDB -e 'select count(*) from mukellefler where deleted_at is null')"
ol "Çöp kutusunda" "2" "$($MDB -e 'select count(*) from mukellefler where deleted_at is not null')"
ol "Beyanname KORUNDU" "3" "$($MDB -e 'select count(*) from beyanname_takip where mukellef_id in (2,3)')"

echo ""
echo "=== 8) ÇÖP KUTUSU: GERİ YÜKLEME ==="
T=$(tok $A $B/sistem/cop-kutusu)
ol "Çöp kutusunda 2 satır" "2" "$(grep -c 'class=\"cop-sec\"' /tmp/f.html)"
curl -s -b $A -c $A -o /dev/null -d "csrf_beyanname=$T" -d "idler[]=2" $B/sistem/cop-geri-yukle
ol "Geri yüklendi" "3" "$($MDB -e 'select count(*) from mukellefler where deleted_at is null')"
ol "Çöpte 1 kaldı" "1" "$($MDB -e 'select count(*) from mukellefler where deleted_at is not null')"

echo ""
echo "=== 9) ÇÖP KUTUSU: KALICI SİLME ==="
T=$(tok $A $B/sistem/cop-kutusu)
curl -s -b $A -c $A -L -o /tmp/r.html -d "csrf_beyanname=$T" -d "idler[]=3" -d "onay=evet" $B/sistem/cop-kalici-sil
ol "Onaysız reddedildi" "1" "$(grep -c 'SİL yazmadınız' /tmp/r.html)"
ol "  kayıt duruyor" "1" "$($MDB -e 'select count(*) from mukellefler where id=3')"

T=$(tok $A $B/sistem/cop-kutusu)
curl -s -b $A -c $A -L -o /tmp/r.html -d "csrf_beyanname=$T" -d "idler[]=3" -d "onay=SİL" $B/sistem/cop-kalici-sil
ol "Onaylı kalıcı silindi" "0" "$($MDB -e 'select count(*) from mukellefler where id=3')"
ol "CASCADE: beyanname gitti" "0" "$($MDB -e 'select count(*) from beyanname_takip where mukellef_id=3')"

echo ""
echo "=== 10) BEYANNAME FİLTRELİ TEMİZLİK ==="
CT=$(mtok $A $B/sistem/veri-yonetimi)
R=$(curl -s -b $A -c $A -H "X-Requested-With: XMLHttpRequest" -d "csrf_beyanname=$CT" $B/sistem/beyanname-onizle | tr -d '\n')
ol "Filtresiz önizleme reddedildi" "1" "$(echo "$R" | grep -c 'En az bir filtre')"

# Beklenen adet DB'den okunur — önceki testler kayıt sayısını değiştirmiş olabilir
BEK=$($MDB -e "select count(*) from beyanname_takip bt join mukellefler m on m.id=bt.mukellef_id where bt.yil=2025 and m.deleted_at is null")
CT=$(mtok $A $B/sistem/veri-yonetimi)
R=$(curl -s -b $A -c $A -H "X-Requested-With: XMLHttpRequest" -d "csrf_beyanname=$CT" -d "yil=2025" $B/sistem/beyanname-onizle | tr -d '\n')
GER=$(echo "$R" | grep -oP '"adet": *\K[0-9]+')
ol "2025 önizleme adedi DB ile aynı" "$BEK" "$GER"
ol "Önizleme DB'ye dokunmadı" "$BEK" "$($MDB -e "select count(*) from beyanname_takip bt join mukellefler m on m.id=bt.mukellef_id where bt.yil=2025 and m.deleted_at is null")"

T=$(tok $A $B/sistem/veri-yonetimi)
curl -s -b $A -c $A -L -o /tmp/r.html -d "csrf_beyanname=$T" -d "onay=SİL" $B/sistem/beyanname-sil
ol "Filtresiz silme reddedildi" "1" "$(grep -c 'En az bir filtre' /tmp/r.html)"
ONCE=$($MDB -e 'select count(*) from beyanname_takip')
ol "  hiç kayıt silinmedi" "$ONCE" "$($MDB -e 'select count(*) from beyanname_takip')"

T=$(tok $A $B/sistem/veri-yonetimi)
curl -s -b $A -c $A -L -o /tmp/r.html -d "csrf_beyanname=$T" -d "yil=2025" -d "onay=SİL" $B/sistem/beyanname-sil
ol "2025 silindi" "0" "$($MDB -e 'select count(*) from beyanname_takip where yil=2025')"
ol "2026 dokunulmadı" "3" "$($MDB -e 'select count(*) from beyanname_takip where yil=2026')"

echo ""
echo "=== 11) EVRAK FİLTRELİ TEMİZLİK ==="
T=$(tok $A $B/sistem/veri-yonetimi)
curl -s -b $A -c $A -L -o /tmp/r.html -d "csrf_beyanname=$T" -d "onay=SİL" $B/sistem/evrak-sil
ol "Yılsız silme reddedildi" "1" "$(grep -c 'Yıl seçmelisiniz' /tmp/r.html)"
T=$(tok $A $B/sistem/veri-yonetimi)
curl -s -b $A -c $A -L -o /tmp/r.html -d "csrf_beyanname=$T" -d "evrak_yil=2025" -d "onay=SİL" $B/sistem/evrak-sil
ol "2025 evrak silindi" "0" "$($MDB -e 'select count(*) from evrak_takip where yil=2025')"
ol "2026 evrak duruyor" "1" "$($MDB -e 'select count(*) from evrak_takip where yil=2026')"

echo ""
echo "=== 12) YETKİ: PERSONEL VE MÜŞAVİR ENGELLİ ==="
M0=$($MDB -e 'select count(*) from mukellefler'); B0=$($MDB -e 'select count(*) from beyanname_takip')
for ROL in personel musavir; do
  giris $ROL /tmp/s_$ROL.txt
  J=/tmp/s_$ROL.txt
  for u in sistem/yedekleme sistem/geri-yukleme sistem/veri-yonetimi sistem/cop-kutusu; do
    ol "$ROL: GET $u engellendi" "302" "$(curl -s -b $J -o /dev/null -w '%{http_code}' $B/$u)"
  done
  CT=$(mtok $J $B/mukellefler)
  ol "$ROL: menüde sistem linki yok" "0" "$(grep -c 'sistem/yedekleme' /tmp/f.html)"
  ol "$ROL: seçim kutusu yok" "0" "$(grep -c 'class=\"muk-sec\"' /tmp/f.html)"
  ol "$ROL: POST yedek-indir engellendi" "303" "$(curl -s -b $J -o /dev/null -w '%{http_code}' -d "csrf_beyanname=$CT" -d "tablolar[]=mukellefler" $B/sistem/yedek-indir)"
  ol "$ROL: POST mükellef-sil engellendi" "403" "$(curl -s -b $J -o /dev/null -w '%{http_code}' -H 'X-Requested-With: XMLHttpRequest' -d "csrf_beyanname=$CT" -d "idler[]=1" $B/sistem/mukellef-toplu-sil)"
  ol "$ROL: POST beyanname-sil engellendi" "303" "$(curl -s -b $J -o /dev/null -w '%{http_code}' -d "csrf_beyanname=$CT" -d "yil=2026" -d "onay=SİL" $B/sistem/beyanname-sil)"
  ol "$ROL: POST kalıcı-sil engellendi" "303" "$(curl -s -b $J -o /dev/null -w '%{http_code}' -d "csrf_beyanname=$CT" -d "tumu=1" -d "onay=SİL" $B/sistem/cop-kalici-sil)"
done
ol "Hiçbir mükellef silinmedi" "$M0" "$($MDB -e 'select count(*) from mukellefler')"
ol "Hiçbir beyanname silinmedi" "$B0" "$($MDB -e 'select count(*) from beyanname_takip')"

echo ""
echo "=== 13) OTURUMSUZ + CSRF ==="
ol "Oturumsuz GET" "302" "$(curl -s -o /dev/null -w '%{http_code}' $B/sistem/yedekleme)"
ol "Oturumsuz POST" "403" "$(curl -s -o /dev/null -w '%{http_code}' -d "tablolar[]=mukellefler" $B/sistem/yedek-indir)"
ol "CSRF'siz POST" "403" "$(curl -s -b $A -o /dev/null -w '%{http_code}' -d "yil=2026" -d "onay=SİL" $B/sistem/beyanname-sil)"

veriKur
echo ""; echo "======"
[ $k -eq 0 ] && echo "BASARILI ($g/$((g+k)))" || echo "$k HATA ($g/$((g+k)))"
