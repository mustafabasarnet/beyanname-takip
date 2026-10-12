#!/bin/bash
# =====================================================================
#  ÖDEME LİSTESİ — AY ATAMASI (SGK ödemesi hafta sonuna denk gelince)
#
#  Kullanıcı şikâyeti: "Ödeme listesinde SGK ödemesi görünmüyor; geçen
#  aylarda görünüyor." Sebep: SGK ödemesi 31 Ekim (Cumartesi) → 2 Kasım'a
#  kaydığı için satır Kasım listesine düşüyordu.
#
#  Test ettikleri:
#    1) Migration: odeme_yasal_tarih kolonu var; mevcut SGK satırı doldu (31.10)
#    2) Eylül SGK (yasal 31.10 / gerçek 02.11) → EKİM listesinde görünür
#    3) Ekim listesinde GERÇEK ödeme tarihi (02.11.2026) yine gösterilir
#    4) Eylül SGK → KASIM listesinde YOK (hata düzeldi)
#    5) Ödemesiz/normal tür (kanuni 31.10, kaydırma 02.11) → EKİM listesinde
#    6) Kasım dönemi (kontrol) → yalnız KASIM listesinde
#    7) Yıl filtresi: üçü de 2026'da
#    8) Yetki: personel bu sayfaya giremez (302) — liste mali veri
#
#  Ön koşul: uygulama http://127.0.0.1:8099; migration_odeme_yasal.sql koşulmuş.
#  Kullanım: bash tests/odeme_ay_testi.sh
#  Not: Test satırları "ODEME-TEST" önekiyle kurulur ve sonunda silinir.
# =====================================================================
B=http://127.0.0.1:8099
PROJE=$(cd "$(dirname "$0")/.." && pwd)
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
JA=/tmp/odt_admin.txt; JP=/tmp/odt_personel.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/odt_g.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/odt_g.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }
# Ödeme listesini belirli ay için çek (tek mükellef, tüm gruplar görünsün)
liste(){ curl -s -b $JA -c $JA "$B/odeme?yil=2026&ay=$1&mukellef_id=1&adet=100" -o /tmp/odt_$1.html; cat /tmp/odt_$1.html; }

echo "=== 0) HAZIRLIK ==="
db "DELETE FROM beyanname_takip WHERE donem_adi LIKE 'ODEME-TEST%';" >/dev/null 2>&1
MUK=1
SGK=$(db "SELECT id FROM beyanname_turleri WHERE kod='SGK';")
MUHSGK=$(db "SELECT id FROM beyanname_turleri WHERE kod='MUHSGK_A';")
ol "SGK ve MUHSGK türleri bulundu" "1" "$([ -n "$SGK" ] && [ -n "$MUHSGK" ] && echo 1 || echo 0)"

# A) Eylül SGK — kanuni ödeme alanı BOŞ bırakılır; migration doldurmalı (geriye dönük)
db "INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,odeme_son_tarih,odeme_yasal_tarih,durum,tahakkuk_tutari,odendi,created_at,updated_at)
    VALUES ($MUK,$SGK,2026,91,'ODEME-TEST SGK Eylül 2026','2026-09-01','2026-09-30','2026-10-26','2026-10-26','2026-11-02',NULL,'ONAYLANDI',1000,0,NOW(),NOW());"
# B) Normal tür: kanuni son 31.10 (Cumartesi), beyan son 02.11 (kaydırılmış), ödeme alanı yok → EKİM
db "INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,odeme_son_tarih,odeme_yasal_tarih,durum,tahakkuk_tutari,odendi,created_at,updated_at)
    VALUES ($MUK,$MUHSGK,2026,92,'ODEME-TEST Normal Ekim 2026','2026-10-01','2026-10-31','2026-10-31','2026-11-02',NULL,NULL,'ONAYLANDI',500,0,NOW(),NOW());"
# C) Kontrol: Kasım dönemi SGK → yalnız KASIM
db "INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,odeme_son_tarih,odeme_yasal_tarih,durum,tahakkuk_tutari,odendi,created_at,updated_at)
    VALUES ($MUK,$SGK,2026,93,'ODEME-TEST SGK Kasım 2026','2026-10-01','2026-10-31','2026-11-26','2026-11-26','2026-11-30','2026-11-30','ONAYLANDI',700,0,NOW(),NOW());"
ol "test satırları eklendi (3)" "3" "$(db "SELECT COUNT(*) FROM beyanname_takip WHERE donem_adi LIKE 'ODEME-TEST%';")"

echo
echo "=== 1) MİGRATION (geriye dönük doldurma) ==="
$MDB < "$PROJE/database/migration_odeme_yasal.sql" >/dev/null 2>&1
ol "migration hatasız çalıştı" "0" "$?"
ol "kolon mevcut" "1" "$(db "SHOW COLUMNS FROM beyanname_takip LIKE 'odeme_yasal_tarih';" | wc -l | tr -d ' ' | awk '{print ($1>0)?1:0}')"
ol "Eylül SGK kanuni ödeme = 2026-10-31 (doldu)" "2026-10-31" \
   "$(db "SELECT odeme_yasal_tarih FROM beyanname_takip WHERE donem_adi='ODEME-TEST SGK Eylül 2026';")"
ol "gerçek ödeme son günü değişmedi (2026-11-02)" "2026-11-02" \
   "$(db "SELECT odeme_son_tarih FROM beyanname_takip WHERE donem_adi='ODEME-TEST SGK Eylül 2026';")"
$MDB < "$PROJE/database/migration_odeme_yasal.sql" >/dev/null 2>&1
ol "migration tekrar koşuldu (idempotent) — değer korundu" "2026-10-31" \
   "$(db "SELECT odeme_yasal_tarih FROM beyanname_takip WHERE donem_adi='ODEME-TEST SGK Eylül 2026';")"

echo
echo "=== 2) EKİM LİSTESİ ==="
girisYap admin $JA
ol "admin girişi" "200" "$(curl -s -b $JA -o /dev/null -w '%{http_code}' $B/odeme)"
EKIM=$(liste 10)
ol "Eylül SGK (kaydırılmış) EKİM listesinde" "1" "$(echo "$EKIM" | grep -c 'ODEME-TEST SGK Eylül 2026' | awk '{print ($1>0)?1:0}')"
ol "ekranda GERÇEK ödeme tarihi 02.11.2026 yazılı" "1" "$(echo "$EKIM" | grep -c '02.11.2026' | awk '{print ($1>0)?1:0}')"
ol "normal tür (kanuni 31.10) EKİM listesinde" "1" "$(echo "$EKIM" | grep -c 'ODEME-TEST Normal Ekim 2026' | awk '{print ($1>0)?1:0}')"
ol "Kasım SGK EKİM listesinde YOK" "0" "$(echo "$EKIM" | grep -c 'ODEME-TEST SGK Kasım 2026' | awk '{print ($1>0)?1:0}')"

echo
echo "=== 3) KASIM LİSTESİ (hata düzeldi) ==="
KASIM=$(liste 11)
ol "Eylül SGK KASIM listesinde YOK (eski hata)" "0" "$(echo "$KASIM" | grep -c 'ODEME-TEST SGK Eylül 2026' | awk '{print ($1>0)?1:0}')"
ol "Kasım SGK KASIM listesinde" "1" "$(echo "$KASIM" | grep -c 'ODEME-TEST SGK Kasım 2026' | awk '{print ($1>0)?1:0}')"
ol "normal Ekim tür KASIM listesinde YOK" "0" "$(echo "$KASIM" | grep -c 'ODEME-TEST Normal Ekim 2026' | awk '{print ($1>0)?1:0}')"

echo
echo "=== 4) YIL FİLTRESİ ==="
YIL=$(curl -s -b $JA -c $JA "$B/odeme?yil=2026&mukellef_id=1&adet=100&ay=" -o /tmp/odt_yil.html; cat /tmp/odt_yil.html)
ol "yıl genelinde üç test satırı da var" "3" \
   "$(echo "$YIL" | grep -c 'ODEME-TEST' | awk '{print $1}')"

echo
echo "=== 5) YETKİ ==="
girisYap personel $JP
ol "personel ödeme listesine giremez (302)" "302" "$(curl -s -b $JP -o /dev/null -w '%{http_code}' $B/odeme)"

echo
echo "=== 6) TEMİZLİK ==="
db "DELETE FROM beyanname_takip WHERE donem_adi LIKE 'ODEME-TEST%';" >/dev/null 2>&1
ol "test satırları silindi" "0" "$(db "SELECT COUNT(*) FROM beyanname_takip WHERE donem_adi LIKE 'ODEME-TEST%';")"

echo
echo "======================================================"
echo " SONUÇ: $g geçti, $k hata"
echo "======================================================"
[ "$k" -eq 0 ]
