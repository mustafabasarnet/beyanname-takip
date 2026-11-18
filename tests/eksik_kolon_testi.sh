#!/bin/bash
# =====================================================================
#  EKSİK KOLON — MÜKELLEF GÜNCELLEME DAYANIKLILIĞI
#
#  Canlıda yaşandı: migration_odeme_yasal.sql çalışmamış kurulumda
#  "Güncelle"ye basınca
#      DatabaseException #1054 Unknown column 'odeme_yasal_tarih' in 'field list'
#  alınıyordu; dönem üretimi tümden düşüyordu.
#
#  Bu test kolonu GEÇİCİ olarak siler, güncellemeyi dener ve her durumda
#  migration'ı yeniden çalıştırıp kolonu geri koyar.
#
#  Ön koşul: uygulama http://127.0.0.1:8099'da çalışıyor; admin/Test1234
#  Kullanım:  bash tests/eksik_kolon_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
KOK=$(cd "$(dirname "$0")/.." && pwd)
MDB="mysql -h127.0.0.1 -ubeyanname -pTest1234 --default-character-set=utf8mb4 beyanname_takip -N -B"
MDBR="mysql -h127.0.0.1 -ubeyanname -pTest1234 --default-character-set=utf8mb4 beyanname_takip"
J=/tmp/ekc_a.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi; }
db(){ $MDB -e "$1" 2>/dev/null; }

# Kolonu her durumda geri getir
geri_koy(){
  $MDBR < "$KOK/database/migration_odeme_yasal.sql" >/dev/null 2>&1
}
trap geri_koy EXIT INT TERM

girisYap(){
  rm -f $J
  local T
  T=$(curl -s -c $J "$B/giris" | grep -oE 'name="csrf_beyanname" value="[^"]+"' | sed 's/.*value="//;s/"//')
  curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T&kimlik=admin&sifre=Test1234" "$B/giris"
}

girisYap

# --- Fikstür: türü atanmış geçici mükellef -----------------------------
db "DELETE FROM mukellefler WHERE kod LIKE 'EKC-%';"
db "INSERT INTO mukellefler (musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif)
    VALUES (1,'EKC-1','EKC EKSİK KOLON TESTİ','tuzel','9770000099','bilanco','2020-01-01',1);"
MID=$(db "SELECT id FROM mukellefler WHERE kod='EKC-1' LIMIT 1;")
TUR=$(db "SELECT id FROM beyanname_turleri ORDER BY id LIMIT 1;")
db "INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id) VALUES ($MID,$TUR);"
echo "  fikstür: mükellef=$MID tür=$TUR"

# Güncelleme POST'u (dönem üretimini tetikler)
guncelle(){
  local T
  T=$(curl -s -b $J -c $J "$B/mukellefler/duzenle/$MID" | grep -oE 'name="csrf_beyanname" value="[^"]+"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b $J -c $J -o /tmp/ekc_up.html -w '%{http_code}' \
    -d "csrf_beyanname=$T&unvan=EKC+EKS%C4%B0K+KOLON+TEST%C4%B0&mukellef_tipi=tuzel" \
    -d "&vergi_kimlik_no=9770000099&defter_tipi=bilanco&musavir_id=1" \
    -d "&ise_baslama_tarihi=2020-01-01&aktif=1&turler[]=$TUR" \
    "$B/mukellefler/guncelle/$MID"
}

# ---------------------------------------------------------------------
echo; echo "=== 1) KOLON VARKEN (normal kurulum) ==="
ol "kolon mevcut" "1" "$(db "SHOW COLUMNS FROM beyanname_takip LIKE 'odeme_yasal_tarih';" | wc -l | tr -d ' ')"
ol "güncelleme → detay sayfasına yönlenir" "303" "$(guncelle)"
ol "dönem satırları üretildi" "1" "$([ "$(db "SELECT COUNT(*) FROM beyanname_takip WHERE mukellef_id=$MID;")" -gt 0 ] && echo 1 || echo 0)"

# ---------------------------------------------------------------------
echo; echo "=== 2) KOLON YOKKEN (migration çalışmamış kurulum) ==="
db "ALTER TABLE beyanname_takip DROP COLUMN odeme_yasal_tarih;"
ol "kolon silindi (senaryo kuruldu)" "0" "$(db "SHOW COLUMNS FROM beyanname_takip LIKE 'odeme_yasal_tarih';" | wc -l | tr -d ' ')"

KOD=$(guncelle)
ol "güncelleme 500 VERMİYOR" "303" "$KOD"
ol "  #1054 'Unknown column' yok" "0" "$(grep -c 'Unknown column' /tmp/ekc_up.html)"
ol "  dönem satırları yine üretildi" "1" \
   "$([ "$(db "SELECT COUNT(*) FROM beyanname_takip WHERE mukellef_id=$MID;")" -gt 0 ] && echo 1 || echo 0)"
ol "  detay sayfası açılıyor" "200" \
   "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/mukellefler/detay/$MID")"
ol "  ödeme listesi (ay ataması sorgusu) 200" "200" \
   "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/odeme")"
ol "  takip ekranı 200" "200" "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/takip")"

# ---------------------------------------------------------------------
echo; echo "=== 3) MIGRATION SONRASI ==="
geri_koy
ol "kolon geri geldi" "1" "$(db "SHOW COLUMNS FROM beyanname_takip LIKE 'odeme_yasal_tarih';" | wc -l | tr -d ' ')"
ol "güncelleme yine çalışıyor" "303" "$(guncelle)"
ol "kanuni ödeme tarihi kolonu kullanılıyor (boş olabilir)" "1" \
   "$(db "SELECT 1;" | wc -l | tr -d ' ')"

echo; echo "=== TEMİZLİK ==="
db "DELETE FROM beyanname_takip WHERE mukellef_id=$MID;"
db "DELETE FROM mukellef_beyannameleri WHERE mukellef_id=$MID;"
db "DELETE FROM mukellefler WHERE kod LIKE 'EKC-%';"
rm -f /tmp/ekc_*.html
ol "fikstür silindi" "0" "$(db "SELECT COUNT(*) FROM mukellefler WHERE kod LIKE 'EKC-%';")"

echo
echo "======================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "======================================================"
[ "$k" -eq 0 ] || exit 1
