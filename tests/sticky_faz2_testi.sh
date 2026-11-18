#!/bin/bash
# =====================================================================
#  YAPIŞKAN NOTLAR — FAZ 2 (başlık, arşiv, hatırlatma, rozet, giriş penceresi)
#
#  Test ettikleri:
#    1) Sayfa: arşiv bölümü, kısayol penceresi, hatırlatma düğmesi
#    2) Başlık: kaydedilir, 40 karakter sınırı (400), yazarken duvar yenilenmez
#    3) Arşiv: arşive taşınır (listeden çıkar, arşiv listesine girer), geri alınır
#    4) Arşivden kalıcı silme
#    5) Hatırlatma: bugün/geçmiş → rozet artar; gelecek → rozete GİRMEZ
#    6) Geçersiz tarih (2026-13-45, abc) → 400
#    7) Tamam: rozetten düşürür, tarih silinmez; tarih değişince tamam sıfırlanır
#    8) Tarihsiz karta tamam → 400
#    9) Arşivdeki hatırlatma rozete girmez
#   10) Giriş penceresi: hatırlatma gönderilir; okununca o gün tekrar çıkmaz
#   11) İZOLASYON: yönetici/personel pencerede bu kullanıcının hatırlatmasını görmez
#   12) CSRF'siz tamam → 403
#   13) Migration kolonu (hatirlat_tamam_at) mevcut
#
#  Ön koşul: uygulama http://127.0.0.1:8099; migration_sticky_not.sql +
#            migration_sticky_not_faz2.sql koşulmuş.
#  Kullanım:  bash tests/sticky_faz2_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
JS=/tmp/f2_sahip.txt; JA=/tmp/f2_admin.txt; JP=/tmp/f2_personel.txt
BUGUN=$(date +%F)
YARIN=$(date -d "+10 days" +%F)
DUN=$(date -d "-3 days" +%F)
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/f2_g.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f2_g.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }
tok(){ curl -s -b "$1" -c "$1" "$B/kisisel/yapiskan" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
post(){ local jar="$1" url="$2"; shift 2; local t; t=$(tok "$jar")
  curl -s -b "$jar" -c "$jar" -X POST -H "X-Requested-With: XMLHttpRequest" \
    -o /tmp/f2_cevap.json -w '%{http_code}' -d "csrf_beyanname=$t" "$@" "$B$url"; }
alan(){ python3 -c "import json;d=json.load(open('/tmp/f2_cevap.json'));print(d.get('$1',''))" 2>/dev/null; }
# Sayfadaki menü rozeti sayısı (Kişisel Notlar)
rozet(){ curl -s -b "$1" -c "$1" "$B/kisisel/yapiskan" | python3 -c "
import re,sys
h=sys.stdin.read()
m=re.search(r'id=\"kisisel-menu-rozet\"[^>]*>(\d+)<', h)
print(m.group(1) if m else 'YOK')"; }
# Giriş penceresi JSON'u: alan adı
uyari(){ curl -s -b "$1" -c "$1" "$B/kisisel/giris-uyarisi" | python3 -c "
import json,sys
d=json.load(sys.stdin)
print(d.get('$2',''))" 2>/dev/null; }
# Duvar HTML'inde kartın hatırlatma durumu
kartDurum(){ python3 -c "
import json,re
d=json.load(open('/tmp/f2_cevap.json'))
m=re.search(r'data-id=\"$1\"[^>]*data-hat-durum=\"([a-z]+)\"', d.get('listeHtml',''))
print(m.group(1) if m else 'YOK')"; }

echo "=== 0) HAZIRLIK (geçici kullanıcı) ==="
db "DELETE FROM kisisel_sticky_notlar WHERE metin LIKE 'F2-%' OR baslik LIKE 'F2-%';" >/dev/null 2>&1
db "DELETE FROM kullanicilar WHERE kullanici_adi='f2_sahip';" >/dev/null 2>&1
db "INSERT INTO kullanicilar (ad_soyad,kullanici_adi,eposta,sifre,rol,aktif,created_at,updated_at)
    SELECT 'F2 SAHIP','f2_sahip','f2_sahip@example.com',sifre,'personel',1,NOW(),NOW()
      FROM kullanicilar WHERE kullanici_adi='personel' LIMIT 1;" >/dev/null 2>&1
SAHIP_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='f2_sahip';")
ol "geçici kullanıcı oluştu" "1" "$([ -n "$SAHIP_ID" ] && echo 1 || echo 0)"
girisYap f2_sahip $JS; girisYap admin $JA; girisYap personel $JP
ol "sahip oturumu açık" "200" "$(curl -s -b $JS -o /dev/null -w '%{http_code}' $B/kisisel/yapiskan)"
ol "migration kolonu mevcut (hatirlat_tamam_at)" "1" \
   "$(db "SHOW COLUMNS FROM kisisel_sticky_notlar LIKE 'hatirlat_tamam_at';" | wc -l | tr -d ' ' | awk '{print ($1>0)?1:0}')"

echo
echo "=== 1) SAYFA ==="
curl -s -b $JS -o /tmp/f2_sayfa.html $B/kisisel/yapiskan
ol "arşiv bölümü var" "1" "$(grep -c 'yk-arsiv-baslik' /tmp/f2_sayfa.html | awk '{print ($1>0)?1:0}')"
ol "kısayol penceresi var" "1" "$(grep -c 'id="yk-tus"' /tmp/f2_sayfa.html | awk '{print ($1>0)?1:0}')"
ol "kısayol düğmesi var" "1" "$(grep -c 'id="yk-yardim"' /tmp/f2_sayfa.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 2) EKLE + BAŞLIK ==="
post $JS /kisisel/yapiskan/ekle -d "metin=F2-1 ilk not" -d "renk=mavi" >/dev/null
ID1=$(db "SELECT id FROM kisisel_sticky_notlar WHERE metin='F2-1 ilk not' AND kullanici_id=$SAHIP_ID;")
ol "kart oluştu" "1" "$([ -n "$ID1" ] && echo 1 || echo 0)"
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" -d "baslik=F2-Başlık")
ol "başlık kaydedildi (200)" "200" "$KOD"
ol "başlık DB'de" "F2-Başlık" "$(db "SELECT baslik FROM kisisel_sticky_notlar WHERE id=$ID1;")"
ol "başlık yazılırken duvar YENİLENMEDİ (listeHtml yok)" "" "$(alan listeHtml)"
UZUN=$(python3 -c "print('b'*41)")
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" --data-urlencode "baslik=$UZUN")
ol "41 karakter başlık → 400" "400" "$KOD"
ol "  başlık değişmedi" "F2-Başlık" "$(db "SELECT baslik FROM kisisel_sticky_notlar WHERE id=$ID1;")"
post $JS /kisisel/yapiskan/ekle -d "metin=F2-2 ikinci" -d "renk=pembe" >/dev/null
ID2=$(db "SELECT id FROM kisisel_sticky_notlar WHERE metin='F2-2 ikinci' AND kullanici_id=$SAHIP_ID;")

echo
echo "=== 3) ARŞİV ==="
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID2" -d "arsiv=1")
ol "arşivle 200" "200" "$KOD"
ol "arşivlenen kart duvardan çıktı" "0" "$(alan listeHtml | grep -c "data-id=\"$ID2\"" | awk '{print ($1>0)?1:0}')"
ol "arşiv listesine girdi" "1" "$(alan arsivHtml | grep -c "data-id=\"$ID2\"" | awk '{print ($1>0)?1:0}')"
ol "arşiv sayısı 1" "1" "$(alan arsivSayi)"
ol "DB arsiv_at dolu" "1" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE id=$ID2 AND arsiv_at IS NOT NULL;")"
post $JS /kisisel/yapiskan/guncelle -d "id=$ID2" -d "arsiv=0" >/dev/null
ol "geri alınca duvara döndü" "1" "$(alan listeHtml | grep -c "data-id=\"$ID2\"" | awk '{print ($1>0)?1:0}')"
ol "geri alınca arşiv_at boş" "0" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE id=$ID2 AND arsiv_at IS NOT NULL;")"

echo
echo "=== 4) ARŞİVDEN KALICI SİL ==="
post $JS /kisisel/yapiskan/guncelle -d "id=$ID2" -d "arsiv=1" >/dev/null
KOD=$(post $JS /kisisel/yapiskan/sil -d "id=$ID2")
ol "arşivdeki kart silindi (200)" "200" "$KOD"
ol "DB'den gitti" "0" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE id=$ID2;")"

echo
echo "=== 5) HATIRLATMA VE ROZET ==="
R0=$(rozet $JS)
ol "başlangıç rozeti 0 (hatırlatma yok)" "0" "$R0"
post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" -d "hatirlat=$BUGUN" >/dev/null
ol "bugünkü hatırlatma kaydedildi" "bugun" "$(kartDurum $ID1)"
ol "rozet +1 (bugün)" "1" "$(rozet $JS)"
ID3=$(post $JS /kisisel/yapiskan/ekle -d "metin=F2-3 gelecek" -d "renk=nane" >/dev/null; db "SELECT id FROM kisisel_sticky_notlar WHERE metin='F2-3 gelecek' AND kullanici_id=$SAHIP_ID;")
post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "hatirlat=$YARIN" >/dev/null
ol "gelecek tarihli hatırlatma rozete GİRMEZ (rozet hâlâ 1)" "1" "$(rozet $JS)"
ol "gelecek hatırlatma durumu 'normal'" "normal" "$(kartDurum $ID3)"

echo
echo "=== 6) GEÇERSİZ TARİH ==="
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "hatirlat=2026-13-45")
ol "2026-13-45 → 400" "400" "$KOD"
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "hatirlat=abc")
ol "abc → 400" "400" "$KOD"
ol "  geçersiz tarih yazılmadı (eski değer korundu)" "$YARIN" "$(db "SELECT hatirlat_tarih FROM kisisel_sticky_notlar WHERE id=$ID3;")"

echo
echo "=== 7) TAMAM ==="
post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" -d "tamam=1" >/dev/null
ol "tamam → durum 'tamam'" "tamam" "$(kartDurum $ID1)"
ol "tamam → rozet 0 (düştü)" "0" "$(rozet $JS)"
ol "tamam tarihi SİLMEDİ" "$BUGUN" "$(db "SELECT hatirlat_tarih FROM kisisel_sticky_notlar WHERE id=$ID1;")"
post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" -d "hatirlat=$BUGUN" >/dev/null
ol "tarih yeniden girilince tamam SIFIRLANDI" "bugun" "$(kartDurum $ID1)"
ol "  rozet tekrar 1" "1" "$(rozet $JS)"
post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" -d "tamam=1" >/dev/null
post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "hatirlat=" >/dev/null
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "tamam=1")
ol "tarihsiz karta tamam → 400" "400" "$KOD"

echo
echo "=== 8) GEÇMİŞ TARİH VE ARŞİV ==="
post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "hatirlat=$DUN" >/dev/null
ol "geçmiş tarih → durum 'gecmis'" "gecmis" "$(kartDurum $ID3)"
ol "rozet 1 (F2-1 tamam → düşer; yalnız geçmiş F2-3 sayılır)" "1" "$(rozet $JS)"
post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "arsiv=1" >/dev/null
ol "arşivdeki hatırlatma rozetten düşer (rozet 0)" "0" "$(rozet $JS)"
post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "arsiv=0" >/dev/null

echo
echo "=== 9) GİRİŞ PENCERESİ ==="
ol "pencere açılır (goster=true)" "True" "$(uyari $JS goster)"
ol "hatırlatma sayısı >= 1" "True" "$(python3 -c "import json,subprocess;print(json.loads(subprocess.check_output(['curl','-s','-b','$JS','-c','$JS','$B/kisisel/giris-uyarisi']))['hatirlatmaSayi']>=1)")"
ol "pencerede F2 hatırlatması var" "1" "$(curl -s -b $JS $B/kisisel/giris-uyarisi | grep -c 'F2-' | awk '{print ($1>0)?1:0}')"
curl -s -b $JS -c $JS -o /dev/null -X POST -H "X-Requested-With: XMLHttpRequest" -d "$(python3 -c "print('csrf_beyanname='+'$(tok $JS)')")" $B/kisisel/uyari-okundu
ol "okununca o gün pencere TEKRAR çıkmaz" "False" "$(uyari $JS goster)"

echo
echo "=== 10) İZOLASYON ==="
ol "yönetici penceresinde sahibin hatırlatması YOK" "0" \
   "$(curl -s -b $JA -c $JA $B/kisisel/giris-uyarisi | grep -c 'F2-' | awk '{print ($1>0)?1:0}')"
ol "personel penceresinde sahibin hatırlatması YOK" "0" \
   "$(curl -s -b $JP -c $JP $B/kisisel/giris-uyarisi | grep -c 'F2-' | awk '{print ($1>0)?1:0}')"
ol "yönetici rozetinde sahibin hatırlatması YOK (rozet sayısı sahibi kapsamaz)" "0" \
   "$(curl -s -b $JA -c $JA $B/kisisel/yapiskan >/dev/null; curl -s -b $JA $B/kisisel/yapiskan | grep -c 'F2-' | awk '{print ($1>0)?1:0}')"
KOD=$(post $JA /kisisel/yapiskan/guncelle -d "id=$ID1" -d "tamam=1")
ol "yönetici sahibin hatırlatmasını tamamlayamaz (400)" "400" "$KOD"

echo
echo "=== 11) CSRF ==="
KOD=$(curl -s -b $JS -c $JS -o /dev/null -w '%{http_code}' -X POST -H "X-Requested-With: XMLHttpRequest" -d "id=$ID1" -d "tamam=1" $B/kisisel/yapiskan/guncelle)
ol "CSRF'siz tamam → 403" "403" "$KOD"

echo
echo "=== 12) TEMİZLİK ==="
db "DELETE FROM kisisel_sticky_notlar WHERE kullanici_id=$SAHIP_ID;" >/dev/null 2>&1
db "DELETE FROM kullanicilar WHERE id=$SAHIP_ID;" >/dev/null 2>&1
ol "geçici kullanıcı silindi" "0" "$(db "SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi='f2_sahip';")"

echo
echo "======================================================"
echo " SONUÇ: $g geçti, $k hata"
echo "======================================================"
[ "$k" -eq 0 ]
