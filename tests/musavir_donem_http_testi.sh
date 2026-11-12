#!/bin/bash
# =====================================================================
#  MÜKELLEF MÜŞAVİR DÖNEMLERİ — HTTP / YETKİ / MAKBUZ REGRESYON TESTİ
#
#  Kapsam:
#   1. CSRF: dönem ekle/sil tokensiz → 403
#   2. Mükellef düzenleme ile müşavir değişikliği → dönem kaydı + senkron
#   3. Gelecek ay ve çakışan dönem reddi (flash mesajı)
#   4. Makbuz: kesen müşavir zorunlu; tarihteki dönem müşavirinden uyarı
#   5. AJAX /makbuz/donem-musavir (tarihe göre varsayılan müşavir)
#   6. İzolasyon: müşavir kullanıcısı kapsam dışına dönem yazamaz
#   7. Dönem silme → güncel müşavir senkronu
#   8. Migration idempotent (iki kez çalışır, çoğalmaz)
#
#  Ön koşul: 127.0.0.1:8099 çalışıyor; admin / musavir (Test1234).
#  Musavir kullanıcısı müşavir 1'e bağlanır (test kendi bağını kurar).
#  Kullanım: bash tests/musavir_donem_http_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
KOK="$(cd "$(dirname "$0")/.." && pwd)"
J=/tmp/mdh_a.txt      # admin
JM=/tmp/mdh_m.txt     # müşavir kullanıcısı (kapsam: müşavir 1)
g=0; k=0

ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1" 2>/dev/null; }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/mdh_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mdh_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf_beyanname" value="\K[^"]+' | head -1; }

# POST → yönlendirme hedefi (302 ise) ; sonuç /tmp/mdh_post.txt'ye "kod|hedef"
post(){ local jar="$1" url="$2"; shift 2
  local t; t=$(jeton "$jar" "$B/makbuz?yil=2026")
  curl -s -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}|%{redirect_url}' \
    -H "Referer: $B/makbuz" -d "csrf_beyanname=$t" "$@" "$url" > /tmp/mdh_post.txt; }
postCsrfsiz(){ local jar="$1" url="$2"; shift 2
  curl -s -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' "$@" "$url"; }
sayfa(){ curl -s -b "$1" "$2"; }   # GET

# ---------------------------------------------------------------------
echo "=== HAZIRLIK ==="
giris admin $J
giris musavir $JM
db "UPDATE kullanici_musavirleri SET musavir_id=1 WHERE kullanici_id=3;"
db "UPDATE kullanicilar SET musavir_id=1 WHERE id=3;"

db "DELETE FROM mukellefler WHERE unvan LIKE 'MDH-TEST%';"
db "INSERT INTO mukellefler (musavir_id, unvan, kod, mukellef_tipi, ise_baslama_tarihi, aktif)
    VALUES (1, 'MDH-TEST ATIKAY LTD', 'MDH-A', 'tuzel', '2019-01-01', 1);"
A=$(db "SELECT id FROM mukellefler WHERE unvan='MDH-TEST ATIKAY LTD' LIMIT 1;")
db "INSERT INTO mukellefler (musavir_id, unvan, kod, mukellef_tipi, ise_baslama_tarihi, aktif)
    VALUES (2, 'MDH-TEST VELI MUKELLEF', 'MDH-B', 'tuzel', '2019-01-01', 1);"
Bm=$(db "SELECT id FROM mukellefler WHERE unvan='MDH-TEST VELI MUKELLEF' LIMIT 1;")
# Dönem kayıtları (mükellef oluşturmanın ilk dönemi)
db "INSERT INTO mukellef_musavir_donemleri (mukellef_id, musavir_id, baslangic, bitis) VALUES ($A,1,'2000-01-01',NULL), ($Bm,2,'2000-01-01',NULL);"
db "INSERT INTO mukellef_ucretleri (mukellef_id, yil, tutar) VALUES ($A,2026,12000);"
db "DELETE FROM makbuzlar WHERE makbuz_no LIKE 'MDH-%';"

echo "  fikstür: Atikay=$A (müşavir 1), Veli mükellef=$Bm (müşavir 2)"
NEXT=$(date -d "$(date +%Y-%m-01) +1 month" +%Y-%m)

# ---------------------------------------------------------------------
echo; echo "=== 1) CSRF ==="
ol "dönem ekle: token yok → 403" "403" \
   "$(postCsrfsiz $J $B/mukellefler/donem/ekle/$A -d musavir_id=2 -d baslangic=2026-03)"
ol "dönem sil: token yok → 403" "403" \
   "$(postCsrfsiz $J $B/mukellefler/donem/sil/$A/1 -d x=1)"

# ---------------------------------------------------------------------
echo; echo "=== 2) MÜŞAVİR DEĞİŞİKLİĞİ (mükellef düzenle) ==="
post $J $B/mukellefler/guncelle/$A -d unvan="MDH-TEST ATIKAY LTD" -d mukellef_tipi=tuzel \
  -d ise_baslama_tarihi=2019-01-01 -d kod=MDH-A -d musavir_id=2 -d musavir_gecerlilik=2026-03 \
  -d "yillik_ucret=12.000,00"
ol "güncelleme yönlendirildi (detay)" "1" "$(grep -c "mukellefler/detay/$A" /tmp/mdh_post.txt)"
ol "dönem: 2 kayıt (Ocak–Şubat Ali, Mart– Veli)" "2" \
   "$(db "SELECT COUNT(*) FROM mukellef_musavir_donemleri WHERE mukellef_id=$A;")"
ol "Ali dönemi Şubat sonunda kapandı" "2026-02-28" \
   "$(db "SELECT bitis FROM mukellef_musavir_donemleri WHERE mukellef_id=$A AND musavir_id=1;")"
ol "Veli dönemi Mart 2026'da başladı" "2026-03-01" \
   "$(db "SELECT baslangic FROM mukellef_musavir_donemleri WHERE mukellef_id=$A AND musavir_id=2;")"
ol "güncel müşavir senkron = 2" "2" "$(db "SELECT musavir_id FROM mukellefler WHERE id=$A;")"

echo; echo "  --- gelecek ay / aynı müşavir ---"
post $J $B/mukellefler/guncelle/$A -d unvan="MDH-TEST ATIKAY LTD" -d mukellef_tipi=tuzel \
  -d ise_baslama_tarihi=2019-01-01 -d kod=MDH-A -d musavir_id=1 -d musavir_gecerlilik=$NEXT
HTML=$(sayfa $J "$(cut -d'|' -f2 /tmp/mdh_post.txt)")
ol "gelecek ay değişikliği hata mesajı ile reddedilir" "1" "$(echo "$HTML" | grep -c 'Gelecek bir ay' | awk '{print ($1>0)?1:0}')"
ol "gelecek ay: DB değişmedi (güncel = 2)" "2" "$(db "SELECT musavir_id FROM mukellefler WHERE id=$A;")"
ol "gelecek ay: DB dönem sayısı değişmedi (2)" "2" "$(db "SELECT COUNT(*) FROM mukellef_musavir_donemleri WHERE mukellef_id=$A;")"

post $J $B/mukellefler/guncelle/$A -d unvan="MDH-TEST ATIKAY LTD" -d mukellef_tipi=tuzel \
  -d ise_baslama_tarihi=2019-01-01 -d kod=MDH-A -d musavir_id=2 -d musavir_gecerlilik=2026-05
ol "aynı müşavir (2) tekrar → dönem sayısı değişmez (2)" "2" \
   "$(db "SELECT COUNT(*) FROM mukellef_musavir_donemleri WHERE mukellef_id=$A;")"

# ---------------------------------------------------------------------
echo; echo "=== 3) DÖNEM EKLEME KURALLARI ==="
post $J $B/mukellefler/donem/ekle/$A -d musavir_id=1 -d baslangic=2026-02 -d bitis=
HTML=$(sayfa $J "$(cut -d'|' -f2 /tmp/mdh_post.txt)")
ol "çakışan dönem reddedilir (mesaj)" "1" "$(echo "$HTML" | grep -c 'çakışıyor' | awk '{print ($1>0)?1:0}')"
ol "çakışan dönem DB'ye yazılmadı (2)" "2" "$(db "SELECT COUNT(*) FROM mukellef_musavir_donemleri WHERE mukellef_id=$A;")"

post $J $B/mukellefler/donem/ekle/$A -d musavir_id=99999 -d baslangic=2025-01 -d bitis=2025-03
HTML=$(sayfa $J "$(cut -d'|' -f2 /tmp/mdh_post.txt)")
ol "yetkisiz/geçersiz müşavir reddedilir" "1" "$(echo "$HTML" | grep -c 'Geçerli bir mali müşavir' | awk '{print ($1>0)?1:0}')"

# ---------------------------------------------------------------------
echo; echo "=== 4) MAKBUZ: KESEN MÜŞAVİR ZORUNLU + DÖNEM UYARISI ==="
post $J $B/makbuz/kaydet -d id=0 -d mukellef_id=$A -d yil=2026 -d tarih=2026-01-15 \
  -d makbuz_no=MDH-000 -d brut=1000 -d donus=liste
HTML=$(sayfa $J "$(cut -d'|' -f2 /tmp/mdh_post.txt)")
ol "kesen müşavir boş → kayıt reddedilir" "1" "$(echo "$HTML" | grep -c 'Kesen mali müşavir seçiniz' | awk '{print ($1>0)?1:0}')"
ol "kesen müşavir boş → DB'ye yazılmadı" "0" "$(db "SELECT COUNT(*) FROM makbuzlar WHERE makbuz_no='MDH-000';")"

# Ocak: dönem müşaviri Ali (1) — Ali kesti → uyarı YOK
post $J $B/makbuz/kaydet -d id=0 -d mukellef_id=$A -d musavir_id=1 -d yil=2026 -d tarih=2026-01-15 \
  -d makbuz_no=MDH-001 -d brut=1000 -d donus=liste
HTML=$(sayfa $J "$(cut -d'|' -f2 /tmp/mdh_post.txt)")
ol "Ocak makbuzu Ali'ye kaydedildi (DB)" "1" "$(db "SELECT COUNT(*) FROM makbuzlar WHERE makbuz_no='MDH-001' AND musavir_id=1;")"
ol "Ocak (dönem uyumlu): uyarı yok" "0" "$(echo "$HTML" | grep -c 'tarihinde bu mükellefin müşaviri' | awk '{print ($1>0)?1:0}')"

# Ocak makbuzunu Veli adına kesmek → uyarı VAR (engellemez)
post $J $B/makbuz/kaydet -d id=0 -d mukellef_id=$A -d musavir_id=2 -d yil=2026 -d tarih=2026-01-20 \
  -d makbuz_no=MDH-002 -d brut=1000 -d donus=liste
HTML=$(sayfa $J "$(cut -d'|' -f2 /tmp/mdh_post.txt)")
ol "Ocak makbuzu Veli adına: yine kaydedilir (engel yok)" "1" "$(db "SELECT COUNT(*) FROM makbuzlar WHERE makbuz_no='MDH-002';")"
ol "Ocak makbuzu Veli adına: dönem uyarısı gösterilir" "1" \
   "$(echo "$HTML" | grep -c 'tarihinde bu mükellefin müşaviri' | awk '{print ($1>0)?1:0}')"

# Mart: dönem müşaviri Veli (2) — Veli kesti → uyarı YOK
post $J $B/makbuz/kaydet -d id=0 -d mukellef_id=$A -d musavir_id=2 -d yil=2026 -d tarih=2026-03-05 \
  -d makbuz_no=MDH-003 -d brut=1000 -d donus=liste
ol "Mart makbuzu Veli'ye kaydedildi" "1" "$(db "SELECT COUNT(*) FROM makbuzlar WHERE makbuz_no='MDH-003' AND musavir_id=2;")"

# ---------------------------------------------------------------------
echo; echo "=== 5) AJAX: TARİHTEKİ DÖNEM MÜŞAVİRİ ==="
ol "10 Şubat → müşavir 1" '{"musavir_id":1}' \
   "$(curl -s -b $J "$B/makbuz/donem-musavir?mukellef_id=$A&tarih=2026-02-10" | tr -d ' \n')"
ol "05 Mart → müşavir 2" '{"musavir_id":2}' \
   "$(curl -s -b $J "$B/makbuz/donem-musavir?mukellef_id=$A&tarih=2026-03-05" | tr -d ' \n')"
ol "geçersiz tarih → null" '{"musavir_id":null}' \
   "$(curl -s -b $J "$B/makbuz/donem-musavir?mukellef_id=$A&tarih=abc" | tr -d ' \n')"

# ---------------------------------------------------------------------
echo; echo "=== 6) İZOLASYON (müşavir kullanıcısı, kapsam: müşavir 1) ==="
ol "kapsam dışı mükellefe dönem eklenemez (Veli'nin mükellefi)" "1" \
   "$(post $JM $B/mukellefler/donem/ekle/$Bm -d musavir_id=1 -d baslangic=2026-01; sayfa $JM "$(cut -d'|' -f2 /tmp/mdh_post.txt)" | grep -c 'Mükellef bulunamadı' | awk '{print ($1>0)?1:0}')"
post $JM $B/mukellefler/donem/ekle/$A -d musavir_id=1 -d baslangic=2025-06 -d bitis=2025-07
ol "Atikay artık müşavir 2'de → müşavir 1 erişemez" "1" "$(sayfa $JM "$(cut -d'|' -f2 /tmp/mdh_post.txt)" | grep -c 'Mükellef bulunamadı' | awk '{print ($1>0)?1:0}')"
ol "kapsam dışı mükellef için makbuz tarih müşaviri = boş" '{"musavir_id":null}' \
   "$(curl -s -b $JM "$B/makbuz/donem-musavir?mukellef_id=$Bm&tarih=2026-03-05" | tr -d ' \n')"
ol "kapsam dışı mükellefe makbuz kaydedilemez" "1" \
   "$(post $JM $B/makbuz/kaydet -d id=0 -d mukellef_id=$Bm -d musavir_id=2 -d yil=2026 -d tarih=2026-03-05 -d makbuz_no=MDH-X -d brut=1 -d donus=liste; sayfa $JM "$(cut -d'|' -f2 /tmp/mdh_post.txt)" | grep -c 'erişemezsiniz' | awk '{print ($1>0)?1:0}')"
ol "müşavir kullanıcısı Veli'nin mükellefinin makbuzunu yazamadı" "0" \
   "$(db "SELECT COUNT(*) FROM makbuzlar WHERE makbuz_no='MDH-X';")"

# ---------------------------------------------------------------------
echo; echo "=== 7) DETAY VE FORM GÖRÜNÜMÜ ==="
ol "mükellef detayında Müşavir Dönemleri kartı" "1" \
   "$(sayfa $J $B/mukellefler/detay/$A | grep -c 'id="musavir-donemleri"' | awk '{print ($1>0)?1:0}')"
ol "detayda dönem listesi (Ali ve Veli)" "1" \
   "$(sayfa $J $B/mukellefler/detay/$A | grep -c 'Veli Demir' | awk '{print ($1>0)?1:0}')"
ol "düzenleme formunda geçerlilik ayı alanı" "1" \
   "$(sayfa $J $B/mukellefler/duzenle/$A | grep -c 'name="musavir_gecerlilik"' | awk '{print ($1>0)?1:0}')"
ol "makbuz formunda kesen müşavir zorunlu" "1" \
   "$(sayfa $J "$B/makbuz?yil=2026" | grep -c 'id="mk-musavir" required' | awk '{print ($1>0)?1:0}')"

# ---------------------------------------------------------------------
echo; echo "=== 8) DÖNEM SİLME → GÜNCEL MÜŞAVİR SENKRONU ==="
VEL_D=$(db "SELECT id FROM mukellef_musavir_donemleri WHERE mukellef_id=$A AND musavir_id=2;")
ol "dönem sil: token yok → 403" "403" "$(postCsrfsiz $J $B/mukellefler/donem/sil/$A/$VEL_D -d x=1)"
post $J $B/mukellefler/donem/sil/$A/$VEL_D
ol "Veli dönemi silindi" "0" \
   "$(db "SELECT COUNT(*) FROM mukellef_musavir_donemleri WHERE mukellef_id=$A AND musavir_id=2;")"
ol "silinince güncel müşavir = 1" "1" "$(db "SELECT musavir_id FROM mukellefler WHERE id=$A;")"
post $JM $B/mukellefler/donem/ekle/$A -d musavir_id=2 -d baslangic=2025-06 -d bitis=2025-07
HTML=$(sayfa $JM "$(cut -d'|' -f2 /tmp/mdh_post.txt)")
ol "kapsam dışı müşavir seçilemez (müşavir 2)" "1" "$(echo "$HTML" | grep -c 'Geçerli bir mali müşavir' | awk '{print ($1>0)?1:0}')"
ol "kapsam dışı seçimde DB'ye dönem yazılmadı (1)" "1" "$(db "SELECT COUNT(*) FROM mukellef_musavir_donemleri WHERE mukellef_id=$A;")"

# ---------------------------------------------------------------------
echo; echo "=== 9) MİGRATION İDEMPOTENT ==="
$MDBR < "$KOK/database/migration_musavir_donem.sql" >/dev/null 2>/tmp/mdh_mig.err; M1=$?
ILK=$(db "SELECT COUNT(*) FROM mukellef_musavir_donemleri;")
$MDBR < "$KOK/database/migration_musavir_donem.sql" >/dev/null 2>>/tmp/mdh_mig.err; M2=$?
ikinci=$(db "SELECT COUNT(*) FROM mukellef_musavir_donemleri;")
ol "migration iki kez hatasız çalışır" "0|0" "$M1|$M2"
ol "migration ikinci çalıştırmada satır eklemez" "$ILK" "$ikinci"
ol "her mükellefin en az bir dönemi var" "0" \
   "$(db "SELECT COUNT(*) FROM mukellefler m WHERE m.deleted_at IS NULL AND m.musavir_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM mukellef_musavir_donemleri d WHERE d.mukellef_id=m.id);")"

# ---------------------------------------------------------------------
echo; echo "=== TEMİZLİK ==="
db "DELETE FROM makbuzlar WHERE makbuz_no LIKE 'MDH-%';"
db "DELETE FROM mukellefler WHERE unvan LIKE 'MDH-TEST%';"
db "UPDATE kullanici_musavirleri SET musavir_id=1 WHERE kullanici_id=3;"

echo
echo "========================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "========================================================"
[ "$k" -eq 0 ]
