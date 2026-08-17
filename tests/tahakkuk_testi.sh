#!/bin/bash
# =====================================================================
#  TAHAKKUK / DURUM REGRESYON TESTİ (sunucu tarafı)
#  Durum "Onaylandı"dan geri alındığında tahakkuk bilgisinin
#  davranışını doğrular.
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin / Test1234 kullanıcısı mevcut ve beyanname_takip
#            tablosunda id = 1, 2, 3 kayıtları bulunuyor.
#            (Başka testler AUTO_INCREMENT'i ilerlettiyse önce sıfırlayın:
#             DELETE FROM beyanname_takip; ALTER TABLE beyanname_takip AUTO_INCREMENT=1;)
#  Kullanım:  bash tests/tahakkuk_testi.sh
# =====================================================================
B=http://127.0.0.1:8099; J=/tmp/th.txt; rm -f $J
g=0;k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
tok(){ grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1; }
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"

curl -s -c $J -b $J -o /tmp/f.html $B/giris
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$(tok)" -d "kimlik=admin" -d "sifre=Test1234" $B/giris
curl -s -b $J -c $J -o /tmp/f.html "$B/takip?ay=0&yil=2026"
ol "Girisli takip sayfasi" "1" "$(grep -c 'Beyanname Takip' /tmp/f.html | awk '{print ($1>0)?1:0}')"

# CSRF token AJAX icin
CT=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/f.html | head -1)
[ -z "$CT" ] && CT=$(tok)
echo "  csrf=$CT"

aj(){ local u="$1"; shift; curl -s -b $J -c $J -H "X-Requested-With: XMLHttpRequest" "$@" -d "csrf_beyanname=$CT" "$u" | tr -d "\n" | tr -s " "; }

echo ""
echo "=== 1) ONAYLANDI + TAHAKKUK GIRISI ==="
R=$(aj $B/takip/durum -d "id=2" -d "durum=ONAYLANDI"); echo "  $R"
R=$(aj $B/odeme/tahakkuk -d "id=2" -d "tutar=1.234,56" -d "fis_no=A123"); echo "  $R"
ol "DB tutar" "1234.56" "$($MDB -e 'select tahakkuk_tutari from beyanname_takip where id=2')"
ol "DB damga" "791.00" "$($MDB -e 'select damga_tutari from beyanname_takip where id=2')"

echo ""
echo "=== 2) DURUM GERI ALINDI -> tahakkuk_kaldi=true ==="
R=$(aj $B/takip/durum -d "id=2" -d "durum=BEKLIYOR"); echo "  $R"
ol "tahakkuk_kaldi bayragi" "1" "$(echo "$R" | grep -c '"tahakkuk_kaldi": *true')"
ol "tahakkuk_f dolu" "1" "$(echo "$R" | grep -c '"tahakkuk_f": *"1.234,56"')"
ol "Veri hala DB'de (Kalsin secenegi)" "1234.56" "$($MDB -e 'select tahakkuk_tutari from beyanname_takip where id=2')"

echo ""
echo "=== 3) CIZELGEDE 'pasif' ROZETI ==="
curl -s -b $J -c $J -o /tmp/f.html "$B/takip?ay=0&yil=2026"
ol "atil sinifi var" "1" "$(grep -c 'tahakkuk-hucre atil' /tmp/f.html | awk '{print ($1>0)?1:0}')"
ol "pasif notu var" "1" "$(grep -c 'pasif' /tmp/f.html | awk '{print ($1>0)?1:0}')"
CT=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/f.html | head -1)

echo ""
echo "=== 4) TAHAKKUK SIL ==="
R=$(aj $B/takip/tahakkuk-sil -d "id=2"); echo "  $R"
ol "silindi mesaji" "1" "$(echo "$R" | grep -c 'silindi')"
ol "DB tutar NULL" "NULL" "$($MDB -e 'select ifnull(tahakkuk_tutari,"NULL") from beyanname_takip where id=2')"
ol "DB damga 0" "0.00" "$($MDB -e 'select damga_tutari from beyanname_takip where id=2')"
ol "DB fis NULL" "NULL" "$($MDB -e 'select ifnull(tahakkuk_fis_no,"NULL") from beyanname_takip where id=2')"

echo ""
echo "=== 5) TUTAR BOSALTINCA DAMGA DA SIFIRLANIR ==="
R=$(aj $B/takip/durum -d "id=3" -d "durum=ONAYLANDI"); R=$(aj $B/odeme/tahakkuk -d "id=3" -d "tutar=500" -d "fis_no=B9"); ol "once damga 791" "791.00" "$($MDB -e 'select damga_tutari from beyanname_takip where id=3')"
R=$(aj $B/odeme/tahakkuk -d "id=3" -d "tutar=" -d "fis_no="); echo "  $R"
ol "sonra damga 0" "0.00" "$($MDB -e 'select damga_tutari from beyanname_takip where id=3')"
ol "tutar NULL" "NULL" "$($MDB -e 'select ifnull(tahakkuk_tutari,"NULL") from beyanname_takip where id=3')"

echo ""
echo "=== 6) TOPLU DURUM -> tahakkuk_kalanlar ==="
R=$(aj $B/takip/durum -d "id=1" -d "durum=ONAYLANDI"); R=$(aj $B/odeme/tahakkuk -d "id=1" -d "tutar=100"); R=$(aj $B/takip/toplu-durum -d "idler[]=1" -d "idler[]=2" -d "durum=HAZIR"); echo "  $R"
ol "kalanlar icinde id=1" "1" "$(echo "$R" | grep -cE '"tahakkuk_kalanlar": *\[ *1 *\]')"

echo ""
echo "=== 7) TOPLU SIL ==="
R=$(aj $B/takip/tahakkuk-sil -d "idler[]=1"); echo "  $R"
ol "DB temiz" "NULL" "$($MDB -e 'select ifnull(tahakkuk_tutari,"NULL") from beyanname_takip where id=1')"

echo ""; echo "======"
[ $k -eq 0 ] && echo "BASARILI ($g/$((g+k)))" || echo "$k HATA ($g/$((g+k)))"
