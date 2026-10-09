#!/bin/bash
# =====================================================================
#  YAPIŞKAN NOTLAR FAZ 2 — MİGRATION TESTİ (phpMyAdmin uyumlu, idempotent)
#  1) Statik: information_schema / PREPARE / @değişken yok
#  2) Sıfırdan: temel tablo + Faz 1 + Faz 2 koşar
#  3) hatirlat_tamam_at eklenir, NULL varsayılanlı, DATETIME
#  4) Tekrar koşum hatasız; mevcut "tamam" değeri KORUNUR
# =====================================================================
PROJE=$(cd "$(dirname "$0")/.." && pwd)
MS="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock"
GDB=sticky_f2_mig
MIG1="$PROJE/database/migration_sticky_not.sql"
MIG2="$PROJE/database/migration_sticky_not_faz2.sql"
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MS -N -B "$GDB" -e "$1" 2>/dev/null; }

echo "=== 1) STATİK ==="
KOD=$(python3 -c "
import re
s=open('$MIG2',encoding='utf-8').read()
print('\n'.join(x for x in (re.sub(r'--.*\$','',l).strip() for l in s.split('\n')) if x))")
ol "information_schema kullanılmıyor" "0" "$(echo "$KOD" | grep -c 'information_schema')"
ol "PREPARE/EXECUTE yok" "0" "$(echo "$KOD" | grep -cE 'PREPARE|EXECUTE')"
ol "kullanıcı değişkeni yok" "0" "$(echo "$KOD" | grep -c 'SET @')"
ol "ADD COLUMN IF NOT EXISTS (idempotent)" "1" "$(echo "$KOD" | grep -c 'ADD COLUMN IF NOT EXISTS' | awk '{print ($1>0)?1:0}')"

echo "=== 2) SIFIRDAN KURULUM ==="
$MS -e "DROP DATABASE IF EXISTS $GDB; CREATE DATABASE $GDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
python3 - "$PROJE/database/beyanname_takip.sql" > /tmp/sticky_f2_temel.sql <<'PY'
import re, sys
s = open(sys.argv[1], encoding='utf-8').read()
s = re.sub(r'CREATE DATABASE IF NOT EXISTS `beyanname_takip`\s*\n\s*DEFAULT CHARACTER SET[^;]*;', '-- (atlandı)', s)
s = s.replace('USE `beyanname_takip`;', '-- (atlandı)')
sys.stdout.write(s)
PY
$MS "$GDB" < /tmp/sticky_f2_temel.sql 2>/dev/null
$MS "$GDB" < "$MIG1" > /dev/null 2>&1
ol "Faz 1 tablosu kuruldu" "1" "$(db "SHOW TABLES LIKE 'kisisel_sticky_notlar';" | wc -l | tr -d ' ')"
ol "Faz 2 öncesi kolon YOK" "0" "$(db "SHOW COLUMNS FROM kisisel_sticky_notlar LIKE 'hatirlat_tamam_at';" | wc -l | tr -d ' ')"
$MS "$GDB" < "$MIG2" > /tmp/sticky_f2_1.txt 2>&1
ol "Faz 2 ilk koşum hatasız" "0" "$?"
ol "kolon eklendi" "1" "$(db "SHOW COLUMNS FROM kisisel_sticky_notlar LIKE 'hatirlat_tamam_at';" | wc -l | tr -d ' ')"

echo "=== 3) KOLON ÖZELLİKLERİ ==="
ol "tip DATETIME" "datetime" "$(db "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kisisel_sticky_notlar' AND COLUMN_NAME='hatirlat_tamam_at';")"
ol "NULL'a izin veriyor" "YES" "$(db "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kisisel_sticky_notlar' AND COLUMN_NAME='hatirlat_tamam_at';")"
ol "varsayılan NULL" "NULL" "$(db "SELECT IFNULL(COLUMN_DEFAULT,'NULL') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kisisel_sticky_notlar' AND COLUMN_NAME='hatirlat_tamam_at';")"
ol "hatirlat_tarih'ten sonra konumlandı" "1" "$(db "SELECT CASE WHEN (SELECT ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kisisel_sticky_notlar' AND COLUMN_NAME='hatirlat_tamam_at') = (SELECT ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kisisel_sticky_notlar' AND COLUMN_NAME='hatirlat_tarih')+1 THEN 1 ELSE 0 END;")"

echo "=== 4) VERİ VE TEKRAR KOŞUM ==="
$MS "$GDB" -e "INSERT INTO kullanicilar (ad_soyad,kullanici_adi,eposta,sifre,rol,aktif,created_at,updated_at) VALUES ('F2 MIG','f2mig','f2mig@example.com','x','personel',1,NOW(),NOW());" 2>/dev/null
UID_=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='f2mig';")
$MS "$GDB" -e "INSERT INTO kisisel_sticky_notlar (kullanici_id,metin,hatirlat_tarih,hatirlat_tamam_at,created_at,updated_at) VALUES ($UID_,'F2-MIG',CURDATE(),NOW(),NOW(),NOW());" 2>/dev/null
$MS "$GDB" < "$MIG2" > /tmp/sticky_f2_2.txt 2>&1
ol "tekrar koşum hatasız" "0" "$?"
ol "tekrar koşumda 'tamam' değeri KORUNDU" "1" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE metin='F2-MIG' AND hatirlat_tamam_at IS NOT NULL;")"
ol "kolon tek kez var" "1" "$(db "SHOW COLUMNS FROM kisisel_sticky_notlar LIKE 'hatirlat_tamam_at';" | wc -l | tr -d ' ')"

echo "=== 5) TEMİZLİK ==="
$MS -e "DROP DATABASE IF EXISTS $GDB;" 2>/dev/null
ol "geçici veritabanı silindi" "0" "$($MS -N -B -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$GDB';" 2>/dev/null)"

echo
echo "======================================================"
echo " SONUÇ: $g geçti, $k hata"
echo "======================================================"
[ "$k" -eq 0 ]
