#!/bin/bash
# =====================================================================
#  YAPIŞKAN NOTLAR — MİGRATION TESTİ (phpMyAdmin uyumlu, idempotent)
#
#  Test ettikleri:
#    1) Statik: information_schema / PREPARE / EXECUTE / @değişken YOK
#    2) Sıfırdan kurulum: tablo ve kolonlar doğru tipte
#    3) İndeks (kullanici_id, arsiv_at, sabit, sira) mevcut
#    4) FK: kullanici silinince kartlar CASCADE ile gider
#    5) İdempotent: tekrar koşum hatasız, veri korunur
#    6) Varsayılanlar: renk='sari', sabit=0, sira=0
# =====================================================================
PROJE=$(cd "$(dirname "$0")/.." && pwd)
MS="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock"
GDB=sticky_mig_test
MIG="$PROJE/database/migration_sticky_not.sql"
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MS -N -B "$GDB" -e "$1" 2>/dev/null; }

echo "=== 1) STATİK ==="
KOD=$(python3 -c "
import re
s=open('$MIG',encoding='utf-8').read()
print('\n'.join(x for x in (re.sub(r'--.*\$','',l).strip() for l in s.split('\n')) if x))")
ol "information_schema kullanılmıyor" "0" "$(echo "$KOD" | grep -c 'information_schema')"
ol "PREPARE kullanılmıyor" "0" "$(echo "$KOD" | grep -c 'PREPARE')"
ol "EXECUTE kullanılmıyor" "0" "$(echo "$KOD" | grep -c 'EXECUTE')"
ol "kullanıcı değişkeni yok" "0" "$(echo "$KOD" | grep -c 'SET @')"
ol "CREATE TABLE IF NOT EXISTS (idempotent)" "1" "$(echo "$KOD" | grep -c 'CREATE TABLE IF NOT EXISTS' | awk '{print ($1>0)?1:0}')"

echo "=== 2) SIFIRDAN KURULUM ==="
$MS -e "DROP DATABASE IF EXISTS $GDB; CREATE DATABASE $GDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
python3 - "$PROJE/database/beyanname_takip.sql" > /tmp/sticky_mig_temel.sql <<'PY'
import re, sys
s = open(sys.argv[1], encoding='utf-8').read()
s = re.sub(r'CREATE DATABASE IF NOT EXISTS `beyanname_takip`\s*\n\s*DEFAULT CHARACTER SET[^;]*;', '-- (atlandı)', s)
s = s.replace('USE `beyanname_takip`;', '-- (atlandı)')
sys.stdout.write(s)
PY
$MS "$GDB" < /tmp/sticky_mig_temel.sql 2>/dev/null
ol "önce tablo yok" "0" "$(db "SHOW TABLES LIKE 'kisisel_sticky_notlar';" | wc -l | tr -d ' ')"
$MS "$GDB" < "$MIG" > /tmp/sticky_mig_1.txt 2>&1
ol "ilk koşum hatasız" "0" "$?"
ol "tablo oluştu" "1" "$(db "SHOW TABLES LIKE 'kisisel_sticky_notlar';" | wc -l | tr -d ' ')"
ol "kolon sayısı 11" "11" "$(db "SHOW COLUMNS FROM kisisel_sticky_notlar;" | wc -l | tr -d ' ')"

echo "=== 3) KOLON TİPLERİ VE VARSAYILANLAR ==="
ol "renk varsayılanı 'sari'" "sari" "$(db "SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kisisel_sticky_notlar' AND COLUMN_NAME='renk';" | tr -d "'")"
ol "sabit varsayılanı 0" "0" "$(db "SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kisisel_sticky_notlar' AND COLUMN_NAME='sabit';")"
ol "sira varsayılanı 0" "0" "$(db "SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kisisel_sticky_notlar' AND COLUMN_NAME='sira';")"
ol "kullanici_id NOT NULL" "NO" "$(db "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$GDB' AND TABLE_NAME='kisisel_sticky_notlar' AND COLUMN_NAME='kullanici_id';")"

echo "=== 4) İNDEKS ==="
ol "liste indeksi (kullanici_id,arsiv_at,sabit,sira)" "kullanici_id,arsiv_at,sabit,sira" \
   "$(db "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$GDB' AND INDEX_NAME='idx_sticky_liste';")"

echo "=== 5) VERİ VE CASCADE ==="
$MS "$GDB" -e "INSERT INTO kullanicilar (ad_soyad,kullanici_adi,eposta,sifre,rol,aktif,created_at,updated_at) VALUES ('MIG TEST','migsticky','migsticky@example.com','x','personel',1,NOW(),NOW());" 2>/dev/null
UID_=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='migsticky';")
$MS "$GDB" -e "INSERT INTO kisisel_sticky_notlar (kullanici_id,metin,created_at,updated_at) VALUES ($UID_,'MIG-1',NOW(),NOW()),($UID_,'MIG-2',NOW(),NOW());" 2>/dev/null
ol "varsayılanlarla kayıt (renk=sari)" "sari" "$(db "SELECT renk FROM kisisel_sticky_notlar WHERE metin='MIG-1';")"
$MS "$GDB" -e "DELETE FROM kullanicilar WHERE id=$UID_;" 2>/dev/null
ol "kullanıcı silinince kartlar CASCADE ile gitti" "0" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE kullanici_id=$UID_;")"

echo "=== 6) İDEMPOTENT TEKRAR KOŞUM ==="
$MS "$GDB" -e "INSERT INTO kullanicilar (ad_soyad,kullanici_adi,eposta,sifre,rol,aktif,created_at,updated_at) VALUES ('MIG2','migsticky2','migsticky2@example.com','x','personel',1,NOW(),NOW());" 2>/dev/null
U2=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='migsticky2';")
$MS "$GDB" -e "INSERT INTO kisisel_sticky_notlar (kullanici_id,metin,renk,created_at,updated_at) VALUES ($U2,'KORU',  'mavi',NOW(),NOW());" 2>/dev/null
$MS "$GDB" < "$MIG" > /tmp/sticky_mig_2.txt 2>&1
ol "ikinci koşum hatasız" "0" "$?"
ol "tekrar koşumda veri KORUNDU (renk=mavi)" "mavi" "$(db "SELECT renk FROM kisisel_sticky_notlar WHERE metin='KORU';")"
ol "tablo tek kez var" "1" "$(db "SHOW TABLES LIKE 'kisisel_sticky_notlar';" | wc -l | tr -d ' ')"

echo "=== 7) TEMİZLİK ==="
$MS -e "DROP DATABASE IF EXISTS $GDB;" 2>/dev/null
ol "geçici veritabanı silindi" "0" "$($MS -N -B -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$GDB';" 2>/dev/null)"

echo
echo "======================================================"
echo " SONUÇ: $g geçti, $k hata"
echo "======================================================"
[ "$k" -eq 0 ]
