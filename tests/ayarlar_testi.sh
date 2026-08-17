#!/bin/bash
# =====================================================================
#  SİSTEM AYARLARI — REGRESYON TESTİ
#
#  Amaç: Veritabanındaki HER ayarın arayüzde düzenlenebilir olması.
#        (Yeni bir ayar eklenip ekrana konmazsa bu test kırılır.)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde, admin/Test1234
#  Kullanım:  bash tests/ayarlar_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/ay.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

rm -f $J
curl -s -c $J -o /tmp/f.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=admin" -d "sifre=Test1234" $B/giris

# Test öncesi mevcut değerleri yedekle
$MDBR -e "DROP TABLE IF EXISTS ayarlar_yedek; CREATE TABLE ayarlar_yedek AS SELECT * FROM ayarlar;"

curl -s -b $J -o /tmp/ay_sayfa.html $B/tanimlar/ayarlar

echo "=== 1) SAYFA AÇILIYOR ==="
ol "Fatal/exception yok" "0" "$(grep -ciE 'fatal error|uncaught|ErrorException' /tmp/ay_sayfa.html)"
ol "Kaydet düğmesi var" "1" "$(grep -c 'Ayarları Kaydet' /tmp/ay_sayfa.html)"

echo ""
echo "=== 2) VERİTABANINDAKİ HER AYAR EKRANDA MI? ==="
EKSIK=0
for K in $($MDB -e "select anahtar from ayarlar order by anahtar"); do
  if grep -q "ayar\[$K\]" /tmp/ay_sayfa.html; then
    echo "  [OK] $K düzenlenebilir"; g=$((g+1))
  else
    echo "  [HATA] $K ekranda YOK"; k=$((k+1)); EKSIK=$((EKSIK+1))
  fi
done
ol "Hiç eksik ayar yok" "0" "$EKSIK"

TOPLAM=$($MDB -e "select count(*) from ayarlar")
ol "Ekrandaki alan sayısı = ayar sayısı" "$TOPLAM" \
   "$(grep -oE 'name="ayar\[[a-z_]+\]"' /tmp/ay_sayfa.html | sort -u | wc -l)"

echo ""
echo "=== 3) DEĞERLER DOĞRU YÜKLENİYOR MU ==="
$MDBR -e "UPDATE ayarlar SET deger='2' WHERE anahtar='evrak_donem_kaydirma';
          UPDATE ayarlar SET deger='100' WHERE anahtar='evrak_sayfa_adedi';
          UPDATE ayarlar SET deger='11' WHERE anahtar='karsit_uyari_gun';"
curl -s -b $J -o /tmp/ay_sayfa.html $B/tanimlar/ayarlar
ol "Kaydırma seçili değeri 2" "1" \
   "$(python3 -c "
import re;s=open('/tmp/ay_sayfa.html',encoding='utf-8').read()
m=re.search(r'name=\"ayar\[evrak_donem_kaydirma\]\".*?</select>',s,re.S)
print(1 if m and re.search(r'value=\"2\"\s*selected',m.group(0)) else 0)")"
ol "Adet seçili değeri 100" "1" \
   "$(python3 -c "
import re;s=open('/tmp/ay_sayfa.html',encoding='utf-8').read()
m=re.search(r'name=\"ayar\[evrak_sayfa_adedi\]\".*?</select>',s,re.S)
print(1 if m and re.search(r'value=\"100\"\s*selected',m.group(0)) else 0)")"
ol "Karşıt uyarı günü 11" "1" "$(grep -c 'name="ayar\[karsit_uyari_gun\]" class="girdi" min="1" max="60"' /tmp/ay_sayfa.html)"

echo ""
echo "=== 4) KAYDETME ÇALIŞIYOR MU ==="
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ay_sayfa.html|head -1)
curl -s -b $J -c $J -L -o /tmp/ay_son.html \
  -d "csrf_beyanname=$T" \
  -d "ayar[evrak_donem_kaydirma]=1" \
  -d "ayar[evrak_sayfa_adedi]=25" \
  -d "ayar[karsit_uyari_gun]=7" \
  -d "ayar[gg_istisna_donem]=3" \
  -d "ayar[uyari_gun_sayisi]=5" \
  --data-urlencode "ayar[firma_adi]=SOYGÜDEN MÜŞAVİRLİK" \
  -d "ayar[cumartesi_tatil]=1" -d "ayar[pazar_tatil]=1" \
  -d "ayar[damga_otomatik_ekle]=1" \
  $B/tanimlar/ayarlar
ol "Başarı mesajı" "1" "$(grep -c 'Ayarlar kaydedildi' /tmp/ay_son.html)"
ol "  kaydırma=1" "1" "$($MDB -e "select deger from ayarlar where anahtar='evrak_donem_kaydirma'")"
ol "  adet=25" "25" "$($MDB -e "select deger from ayarlar where anahtar='evrak_sayfa_adedi'")"
ol "  karsit=7" "7" "$($MDB -e "select deger from ayarlar where anahtar='karsit_uyari_gun'")"
ol "  Türkçe karakter bozulmadı" "SOYGÜDEN MÜŞAVİRLİK" "$($MDB -e "select deger from ayarlar where anahtar='firma_adi'")"

echo ""
echo "=== 5) İŞARETSİZ CHECKBOX'LAR 0 OLUYOR MU ==="
for K in arife_tatil_sayilsin mali_tatil_uygula otomatik_donem_uret bildirim_ucret_varsayilan; do
  ol "  $K = 0" "0" "$($MDB -e "select deger from ayarlar where anahtar='$K'")"
done
ol "  İşaretliler 1 kaldı (cumartesi)" "1" "$($MDB -e "select deger from ayarlar where anahtar='cumartesi_tatil'")"
ol "  İşaretliler 1 kaldı (damga)" "1" "$($MDB -e "select deger from ayarlar where anahtar='damga_otomatik_ekle'")"

echo ""
echo "=== 6) AYARLAR GERÇEKTEN İŞE YARIYOR MU ==="
# Evrak sayfasına yansıma
$MDBR -e "UPDATE ayarlar SET deger='2' WHERE anahtar='evrak_donem_kaydirma';"
curl -s -b $J -o /tmp/ay_ev.html "$B/evrak?yil=2026&ay=8"
ol "kaydirma=2 → Haziran dönemi" "1" "$(grep -c 'Haziran 2026 Dönemi' /tmp/ay_ev.html)"
$MDBR -e "UPDATE ayarlar SET deger='1' WHERE anahtar='evrak_donem_kaydirma';"
curl -s -b $J -o /tmp/ay_ev.html "$B/evrak?yil=2026&ay=8"
ol "kaydirma=1 → Temmuz dönemi" "1" "$(grep -c 'Temmuz 2026 Dönemi' /tmp/ay_ev.html)"

# Sayfa adedi yansıması (çerez yokken ayar geçerli olmalı)
$MDBR -e "UPDATE ayarlar SET deger='25' WHERE anahtar='evrak_sayfa_adedi';"
MUK=$($MDB -e "select count(*) from mukellefler where deleted_at is null and aktif=1")
if [ "$MUK" -gt 25 ]; then
  curl -s -b $J -o /tmp/ay_ev2.html "$B/evrak?yil=2026&ay=8"
  ol "evrak_sayfa_adedi=25 uygulandı" "25" "$(grep -c 'td class="sol-sabit"' /tmp/ay_ev2.html)"
else
  echo "  [ATLA] evrak_sayfa_adedi testi (yalnızca $MUK mükellef var, 25'ten az)"
fi

echo ""
echo "=== 7) BİLİNMEYEN AYAR OTOMATİK GÖRÜNÜYOR MU ==="
$MDBR -e "INSERT INTO ayarlar (anahtar,deger,aciklama) VALUES ('test_yeni_ayar','deneme','Test amaçlı geçici ayar')
          ON DUPLICATE KEY UPDATE deger='deneme';"
curl -s -b $J -o /tmp/ay_yeni.html $B/tanimlar/ayarlar
ol "Yeni ayar 'Diğer Ayarlar'da çıktı" "1" "$(grep -c 'ayar\[test_yeni_ayar\]' /tmp/ay_yeni.html)"
ol "Açıklaması gösteriliyor" "1" "$(grep -c 'Test amaçlı geçici ayar' /tmp/ay_yeni.html)"
$MDBR -e "DELETE FROM ayarlar WHERE anahtar='test_yeni_ayar';"

echo ""
echo "=== 8) YETKİ ==="
rm -f /tmp/ay_p.txt
curl -s -c /tmp/ay_p.txt -o /tmp/f.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
curl -s -b /tmp/ay_p.txt -c /tmp/ay_p.txt -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=personel" -d "sifre=Test1234" $B/giris
ol "Personel ayarlara giremiyor" "302" "$(curl -s -b /tmp/ay_p.txt -o /dev/null -w '%{http_code}' $B/tanimlar/ayarlar)"
# CSRF'siz POST 403, geçerli CSRF ile 302 (yetki reddi) — ikisi de engellenmiş demektir
POSTKOD=$(curl -s -b /tmp/ay_p.txt -o /dev/null -w '%{http_code}' -d "ayar[firma_adi]=X" $B/tanimlar/ayarlar)
ol "Personel ayar kaydedemiyor ($POSTKOD)" "1" "$([ "$POSTKOD" = "302" ] || [ "$POSTKOD" = "403" ] && echo 1 || echo 0)"
ol "  Personel değişiklik yapamadı" "SOYGÜDEN MÜŞAVİRLİK" "$($MDB -e "select deger from ayarlar where anahtar='firma_adi'")"

# Yedeği geri yükle
$MDBR -e "DELETE FROM ayarlar; INSERT INTO ayarlar SELECT * FROM ayarlar_yedek; DROP TABLE ayarlar_yedek;"

echo ""; echo "======"
[ $k -eq 0 ] && echo "BASARILI ($g/$((g+k)))" || echo "$k HATA ($g/$((g+k)))"
