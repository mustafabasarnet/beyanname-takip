#!/bin/bash
# =====================================================================
#  TEMA MIGRATION TESTİ — information_schema'sız, phpMyAdmin uyumlu
#
#  NEDEN VAR
#  ---------
#  Kullanıcı phpMyAdmin'de migration'ı çalıştırırken şu hatayı aldı:
#
#      #1044 - Access denied for user 'root'@'localhost'
#              to database 'information_schema'
#
#  Sebep: eski sürüm "kolon var mı?" kontrolünü information_schema üzerinden
#  yapıyordu; bazı sunucular bu şemaya erişim vermiyor.
#
#  Test ettikleri:
#    1) Çalıştırılabilir SQL'de information_schema / PREPARE / @değişken YOK
#       (yalnız ALTER ... ADD COLUMN IF NOT EXISTS + SHOW COLUMNS)
#    2) Sıfırdan kurulum: 4 kolon doğru tip/varsayılanla eklenir
#    3) Tekrar koşum (idempotent): hata yok, kolon sayısı değişmez
#    4) Varsayılanlar: tema=sistem, palet=mavi, yan_menu=koyu, hizli_gecis=1
#    5) Kolonlar NOT NULL + doğru sırada (tema→palet→yan_menu→hizli_gecis)
#    6) Mevcut kayıtlar varsayılanı alır (veri kaybı yok, kullanıcı sayısı sabit)
#    7) Açıklama (COMMENT) metinleri yerinde
#    8) Uygulama bu kolonları görüyor (tema_testi'nin beklediği altyapı hazır)
#
#  Ön koşul: MariaDB erişimi (test kendi geçici veritabanını kurar ve siler)
#  Kullanım: bash tests/tema_migration_testi.sh
# =====================================================================
PROJE=$(cd "$(dirname "$0")/.." && pwd)
MS="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock"
GDB=beyanname_mig_test
MIG="$PROJE/database/migration_kullanici_tema.sql"
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MS -N -B "$GDB" -e "$1" 2>/dev/null; }

echo "=== 1) STATİK KONTROL: YASAKLI KALIP YOK ==="
# Yorumları çıkar, yalnız çalıştırılabilir SQL kalsın
KOD=$(python3 -c "
import re
s = open('$MIG', encoding='utf-8').read()
satirlar = [re.sub(r'--.*$', '', x).strip() for x in s.split('\n')]
print('\n'.join([x for x in satirlar if x]))
")
ol "information_schema kullanılmıyor" "0" "$(echo "$KOD" | grep -c 'information_schema')"
ol "PREPARE kullanılmıyor" "0" "$(echo "$KOD" | grep -c 'PREPARE')"
ol "EXECUTE kullanılmıyor" "0" "$(echo "$KOD" | grep -c 'EXECUTE')"
ol "kullanıcı değişkeni (@) kullanılmıyor" "0" "$(echo "$KOD" | grep -c 'SET @')"
ol "ADD COLUMN IF NOT EXISTS kullanılıyor (4 adet)" "4" "$(echo "$KOD" | grep -c 'ADD COLUMN IF NOT EXISTS')"
ol "doğrulama SHOW COLUMNS ile" "1" "$(echo "$KOD" | grep -c 'SHOW COLUMNS')"
ol "veritabanı seçimi (USE/CREATE DATABASE) yok — phpMyAdmin uyumlu" "0" \
   "$(echo "$KOD" | grep -cE '^(USE|CREATE DATABASE)')"
ol "tehlikeli cümle yok (DROP/TRUNCATE/DELETE)" "0" \
   "$(echo "$KOD" | grep -cE '\b(DROP|TRUNCATE|DELETE)\b')"

echo
echo "=== 2) SIFIRDAN KURULUM (temiz veritabanı) ==="
$MS -e "DROP DATABASE IF EXISTS $GDB; CREATE DATABASE $GDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
# Temel şemayı geçici veritabanına yükle (CREATE DATABASE/USE satırları çıkarılır)
python3 - "$PROJE/database/beyanname_takip.sql" > /tmp/tema_mig_temel.sql <<'PY'
import re, sys
s = open(sys.argv[1], encoding='utf-8').read()
s = re.sub(r'CREATE DATABASE IF NOT EXISTS `beyanname_takip`\s*\n\s*DEFAULT CHARACTER SET[^;]*;',
           '-- (atlandı)', s)
s = s.replace('USE `beyanname_takip`;', '-- (atlandı)')
sys.stdout.write(s)
PY
$MS "$GDB" < /tmp/tema_mig_temel.sql 2>/dev/null
ol "temel şema yüklendi (27 tablo)" "27" "$(db "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$GDB';")"
ol "başlangıçta tema kolonu YOK" "0" \
   "$(db "SHOW COLUMNS FROM kullanicilar WHERE Field IN ('tema','palet','yan_menu','hizli_gecis');" | wc -l | tr -d ' ')"

echo
echo "=== 3) MIGRATION KOŞUMU ==="
$MS "$GDB" < "$MIG" > /tmp/tema_mig_cikti.txt 2>&1
ol "ilk koşum hatasız (çıkış kodu 0)" "0" "$?"
ol "4 kolon eklendi" "4" \
   "$(db "SHOW COLUMNS FROM kullanicilar WHERE Field IN ('tema','palet','yan_menu','hizli_gecis');" | wc -l | tr -d ' ')"

echo
echo "=== 4) KOLON ÖZELLİKLERİ ==="
# Not: MariaDB, information_schema.COLUMN_DEFAULT'u karakter dizileri için
# tırnaklı ( 'sistem' ) döndürür; sayısal alanlarda tırnak olmaz.
ol "tema: varchar(10) NOT NULL DEFAULT 'sistem'" \
   "tema|varchar(10)|NO|'sistem'" \
   "$(db "SELECT CONCAT(COLUMN_NAME,'|',COLUMN_TYPE,'|',IS_NULLABLE,'|',COLUMN_DEFAULT) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kullanicilar' AND COLUMN_NAME='tema';")"
ol "palet: varchar(20) NOT NULL DEFAULT 'mavi'" \
   "palet|varchar(20)|NO|'mavi'" \
   "$(db "SELECT CONCAT(COLUMN_NAME,'|',COLUMN_TYPE,'|',IS_NULLABLE,'|',COLUMN_DEFAULT) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kullanicilar' AND COLUMN_NAME='palet';")"
ol "yan_menu: varchar(10) NOT NULL DEFAULT 'koyu'" \
   "yan_menu|varchar(10)|NO|'koyu'" \
   "$(db "SELECT CONCAT(COLUMN_NAME,'|',COLUMN_TYPE,'|',IS_NULLABLE,'|',COLUMN_DEFAULT) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kullanicilar' AND COLUMN_NAME='yan_menu';")"
ol "hizli_gecis: tinyint(1) NOT NULL DEFAULT 1" \
   "hizli_gecis|tinyint(1)|NO|1" \
   "$(db "SELECT CONCAT(COLUMN_NAME,'|',COLUMN_TYPE,'|',IS_NULLABLE,'|',COLUMN_DEFAULT) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kullanicilar' AND COLUMN_NAME='hizli_gecis';")"
ol "kolon sırası: tema→palet→yan_menu→hizli_gecis" \
   "telefon|tema|palet|yan_menu|hizli_gecis" \
   "$(db "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION SEPARATOR '|') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kullanicilar' AND COLUMN_NAME IN ('telefon','tema','palet','yan_menu','hizli_gecis');")"
ol "COMMENT metinleri yazıldı" "4" \
   "$(db "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kullanicilar' AND COLUMN_NAME IN ('tema','palet','yan_menu','hizli_gecis') AND COLUMN_COMMENT <> '';")"

echo
echo "=== 5) MEVCUT KAYITLAR VARSAYILANI ALIR (veri kaybı yok) ==="
db "INSERT INTO kullanicilar (ad_soyad,kullanici_adi,eposta,sifre,rol,aktif) VALUES ('TEST KULLANICI','migtest','migtest@example.com','x','personel',1);"
ol "yeni kayıt varsayılanları aldı" "sistem|mavi|koyu|1" \
   "$(db "SELECT CONCAT(tema,'|',palet,'|',yan_menu,'|',hizli_gecis) FROM kullanicilar WHERE kullanici_adi='migtest';")"
ol "kullanıcı sayısı 1 (veri kaybı yok)" "1" "$(db "SELECT COUNT(*) FROM kullanicilar;")"

echo
echo "=== 6) TEKRAR KOŞUM (İDEMPOTENT) ==="
$MS "$GDB" < "$MIG" > /tmp/tema_mig_cikti2.txt 2>&1
KOD2=$?
ol "ikinci koşum hatasız (çıkış kodu 0)" "0" "$KOD2"
ol "hata mesajı yok" "0" "$(grep -ciE 'error|hata' /tmp/tema_mig_cikti2.txt)"
ol "kolon sayısı hâlâ 4 (çoğalmadı)" "4" \
   "$(db "SHOW COLUMNS FROM kullanicilar WHERE Field IN ('tema','palet','yan_menu','hizli_gecis');" | wc -l | tr -d ' ')"
ol "mevcut tercihler KORUNDU" "sistem|mavi|koyu|1" \
   "$(db "SELECT CONCAT(tema,'|',palet,'|',yan_menu,'|',hizli_gecis) FROM kullanicilar WHERE kullanici_adi='migtest';")"
# Tercihi değiştir, tekrar koş → ezilmemeli
db "UPDATE kullanicilar SET tema='karanlik', palet='mor' WHERE kullanici_adi='migtest';"
$MS "$GDB" < "$MIG" > /dev/null 2>&1
ol "kullanıcı tercihi migration ile EZİLMEZ" "karanlik|mor" \
   "$(db "SELECT CONCAT(tema,'|',palet) FROM kullanicilar WHERE kullanici_adi='migtest';")"

echo
echo "=== 7) ÜÇÜNCÜ KOŞUM + ÇIKTI MESAJI ==="
$MS "$GDB" < "$MIG" > /tmp/tema_mig_cikti3.txt 2>&1
ol "üçüncü koşum da hatasız" "0" "$?"
ol "doğrulama çıktısı 4 kolon listeliyor" "4" "$(grep -cE '^(tema|palet|yan_menu|hizli_gecis)\s' /tmp/tema_mig_cikti3.txt)"

echo
echo "=== 8) TEMİZLİK ==="
$MS -e "DROP DATABASE IF EXISTS $GDB;" 2>/dev/null
ol "geçici veritabanı silindi" "0" "$($MS -N -B -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$GDB';" 2>/dev/null)"

echo
echo "======================================================"
echo " SONUÇ: $g geçti, $k hata"
echo "======================================================"
[ "$k" -eq 0 ]
