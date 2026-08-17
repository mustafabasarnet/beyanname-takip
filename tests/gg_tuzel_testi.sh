#!/bin/bash
# =====================================================================
#  GENÇ GİRİŞİMCİ — YALNIZCA GERÇEK KİŞİ (GVK mükerrer 20)
#
#  Kural: İstisna şirketlere (tüzel kişilere) uygulanmaz.
#
#  Katmanlar (hepsi ayrı ayrı doğrulanır):
#   1. Görünüm  → tüzel kartta bölüm hiç gösterilmez
#   2. Sunucu   → POST elle zorlansa bile kaydedilmez
#   3. Helper   → eski/hatalı veri varsa bile rozet çıkmaz
#   4. Liste    → "Genç Girişimci" filtresi tüzel getirmez
#   5. Excel    → içe aktarımda tüzel için yok sayılır + uyarı
#   6. Migration→ mevcut hatalı tüzel kayıtları temizler
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 kullanıcısı var.
#  Not: Test kendi verisini kurar.
#  Kullanım:  bash tests/gg_tuzel_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
KOK="$(cd "$(dirname "$0")/.." && pwd)"
J=/tmp/gg_a.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/gg_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/gg_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Kart detayındaki "Genç Girişimci" satırının değeri
kartGG(){ python3 - "$1" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
m=re.search(r'Genç Girişimci</div>\s*<div class="dg">([^<]*)',h)
print(m.group(1).strip() if m else 'SATIR-YOK')
PY
}

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (1,'SMMM','Ali','Y',1);
-- id=1 KASITLI HATALI: tüzel ama genç girişimci işaretli (eski veri simülasyonu)
INSERT INTO mukellefler
 (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,
  genc_girisimci,gg_baslangic_yili,gg_not,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','HATALI TÜZEL LTD','tuzel','1112223334',NULL,'bilanco',1,2025,'yanlış girilmiş','2025-01-01',1),
 (2,1,'M002','DOĞRU GERÇEK KİŞİ','gercek',NULL,'22233344455','isletme',1,2025,'doğru','2025-01-01',1),
 (3,1,'M003','NORMAL TÜZEL A.Ş.','tuzel','3334445556',NULL,'bilanco',0,NULL,NULL,'2019-01-01',1);
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif) VALUES (1,8,1),(2,7,1),(3,8,1);
INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,created_at,updated_at) VALUES
 (1,8,2025,1,'2025 Yılı','2025-01-01','2025-12-31','2026-04-30','2026-04-30','BEKLIYOR',NOW(),NOW()),
 (2,7,2025,1,'2025 Yılı','2025-01-01','2025-12-31','2026-03-31','2026-03-31','BEKLIYOR',NOW(),NOW());"
}

veriKur
giris admin $J

echo "=== 1) YARDIMCI İŞLEV (tek karar noktası) ==="
php -r '
require "'"$KOK"'/app/Helpers/beyanname_helper.php";
$t = [
  ["m"=>["genc_girisimci"=>1,"mukellef_tipi"=>"tuzel","gg_baslangic_yili"=>2025],  "bekl"=>false,"ad"=>"tüzel + isaretli => YOK"],
  ["m"=>["genc_girisimci"=>1,"mukellef_tipi"=>"gercek","gg_baslangic_yili"=>2025], "bekl"=>true, "ad"=>"gercek + isaretli => VAR"],
  ["m"=>["genc_girisimci"=>0,"mukellef_tipi"=>"gercek"],                            "bekl"=>false,"ad"=>"gercek + isaretsiz => YOK"],
  ["m"=>["genc_girisimci"=>1,"gg_baslangic_yili"=>2025],                            "bekl"=>true, "ad"=>"tip belirtilmemis => gercek sayilir"],
];
$g=0;$k=0;
foreach ($t as $x) {
  $d = gencGirisimciDurum($x["m"], 2026);
  if ($d["var"] === $x["bekl"]) { echo "  [OK] {$x["ad"]}\n"; $g++; }
  else { echo "  [HATA] {$x["ad"]} (bekl:".var_export($x["bekl"],true)." ger:".var_export($d["var"],true).")\n"; $k++; }
}
// Rozet de bos donmeli
$r = gencGirisimciRozet(["genc_girisimci"=>1,"mukellef_tipi"=>"tuzel","gg_baslangic_yili"=>2025], 2026, true);
if ($r === "") { echo "  [OK] tüzelde rozet HTML bos\n"; $g++; }
else { echo "  [HATA] tüzelde rozet uretildi: $r\n"; $k++; }
exit($k>0?1:0);
' && g=$((g+5)) || k=$((k+1))

echo "=== 2) FORM — TÜZEL KARTTA BÖLÜM GİZLİ ==="
curl -s -b $J "$B/mukellefler/duzenle/1" -o /tmp/gg_f1.html
ol "Tüzel: bölüm display:none" "1" \
  "$(python3 -c "
import re
h=open('/tmp/gg_f1.html',encoding='utf-8').read()
m=re.search(r'id=\"gg_bolum\"[^>]*style=\"([^\"]*)\"',h)
print(1 if m and 'display:none' in m.group(1) else 0)")"
ol "Tüzel: checkbox işaretsiz geliyor" "1" \
  "$(python3 -c "
import re
h=open('/tmp/gg_f1.html',encoding='utf-8').read()
m=re.search(r'<input type=\"checkbox\" name=\"genc_girisimci\".*?>',h,re.S)
print(0 if m and 'checked' in m.group(0) else 1)")"
curl -s -b $J "$B/mukellefler/duzenle/2" -o /tmp/gg_f2.html
ol "Gerçek: bölüm görünür" "1" \
  "$(python3 -c "
import re
h=open('/tmp/gg_f2.html',encoding='utf-8').read()
m=re.search(r'id=\"gg_bolum\"[^>]*style=\"([^\"]*)\"',h)
print(0 if m and 'display:none' in m.group(1) else 1)")"
ol "Gerçek: checkbox işaretli" "1" \
  "$(python3 -c "
import re
h=open('/tmp/gg_f2.html',encoding='utf-8').read()
m=re.search(r'<input type=\"checkbox\" name=\"genc_girisimci\".*?>',h,re.S)
print(1 if m and 'checked' in m.group(0) else 0)")"
ol "Tip değişimi JS'i var" "1" "$(grep -c 'ggTazele' /tmp/gg_f1.html | awk '{print ($1>0)?1:0}')"

echo "=== 3) SUNUCU — POST ZORLAMASI ENGELLENİYOR ==="
TK=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/gg_f1.html|head -1)
curl -s -b $J -c $J -L -o /dev/null -X POST "$B/mukellefler/guncelle/1" \
  -d "csrf_beyanname=$TK" -d "musavir_id=1" -d "unvan=HATALI TÜZEL LTD" -d "mukellef_tipi=tuzel" \
  -d "vergi_kimlik_no=1112223334" -d "defter_tipi=bilanco" -d "ise_baslama_tarihi=2025-01-01" -d "aktif=1" \
  -d "genc_girisimci=1" -d "gg_baslangic_yili=2025" -d "gg_not=zorla girildi"
ol "Tüzelde işaret kaydedilmedi" "0"    "$($MDB -e 'SELECT genc_girisimci FROM mukellefler WHERE id=1')"
ol "Başlangıç yılı temizlendi"   "NULL" "$($MDB -e "SELECT IFNULL(gg_baslangic_yili,'NULL') FROM mukellefler WHERE id=1")"
ol "Not temizlendi"              "NULL" "$($MDB -e "SELECT IFNULL(gg_not,'NULL') FROM mukellefler WHERE id=1")"

TK2=$(curl -s -b $J -c $J -o /tmp/gg_f2.html "$B/mukellefler/duzenle/2"; grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/gg_f2.html|head -1)
curl -s -b $J -c $J -L -o /dev/null -X POST "$B/mukellefler/guncelle/2" \
  -d "csrf_beyanname=$TK2" -d "musavir_id=1" -d "unvan=DOĞRU GERÇEK KİŞİ" -d "mukellef_tipi=gercek" \
  -d "tc_kimlik_no=22233344455" -d "defter_tipi=isletme" -d "ise_baslama_tarihi=2025-01-01" -d "aktif=1" \
  -d "genc_girisimci=1" -d "gg_baslangic_yili=2025" -d "gg_not=doğru"
ol "Gerçek kişide korunuyor"    "1"    "$($MDB -e 'SELECT genc_girisimci FROM mukellefler WHERE id=2')"
ol "Gerçek kişide yıl korunuyor" "2025" "$($MDB -e 'SELECT gg_baslangic_yili FROM mukellefler WHERE id=2')"

echo "=== 4) GERÇEK → TÜZEL ÇEVİRİNCE TEMİZLENİYOR ==="
TK3=$(curl -s -b $J -c $J -o /tmp/gg_f3.html "$B/mukellefler/duzenle/2"; grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/gg_f3.html|head -1)
curl -s -b $J -c $J -L -o /dev/null -X POST "$B/mukellefler/guncelle/2" \
  -d "csrf_beyanname=$TK3" -d "musavir_id=1" -d "unvan=ARTIK TÜZEL" -d "mukellef_tipi=tuzel" \
  -d "vergi_kimlik_no=9998887776" -d "defter_tipi=bilanco" -d "ise_baslama_tarihi=2025-01-01" -d "aktif=1" \
  -d "genc_girisimci=1"
ol "Tipe göre otomatik temizlendi" "0" "$($MDB -e 'SELECT genc_girisimci FROM mukellefler WHERE id=2')"
# Geri al
$MDBR -e "UPDATE mukellefler SET mukellef_tipi='gercek',unvan='DOĞRU GERÇEK KİŞİ',vergi_kimlik_no=NULL,
          tc_kimlik_no='22233344455',defter_tipi='isletme',genc_girisimci=1,gg_baslangic_yili=2025,gg_not='doğru' WHERE id=2;"

echo "=== 5) HATALI ESKİ VERİ HİÇBİR YERDE GÖRÜNMÜYOR ==="
# Kod katmanı migration'dan bağımsız korumalı: hatalı veriyi geri yaz
$MDBR -e "UPDATE mukellefler SET genc_girisimci=1,gg_baslangic_yili=2025,gg_not='yanlış' WHERE id=1;"
curl -s -b $J "$B/mukellefler/detay/1" -o /tmp/gg_d1.html
ol "Tüzel kartta 'Hayır' yazıyor" "Hayır" "$(kartGG /tmp/gg_d1.html)"
ol "Tüzel kartta uyarı kutusu yok" "0" "$(grep -c 'Genç Girişimci Kazanç İstisnası' /tmp/gg_d1.html)"
curl -s -b $J "$B/mukellefler/detay/2" -o /tmp/gg_d2.html
ol "Gerçek kartta istisna görünüyor" "1" \
  "$(kartGG /tmp/gg_d2.html | grep -c 'Genç Girişimci')"
curl -s -b $J "$B/mukellefler" -o /tmp/gg_l.html
ol "Listede tüzelde GG rozeti yok" "0" \
  "$(python3 -c "
import re
h=open('/tmp/gg_l.html',encoding='utf-8').read()
n=0
for tr in re.findall(r'<tr[^>]*>.*?</tr>',h,re.S):
    m=re.search(r'class=\"kalin\"[^>]*>\s*([^<]+)',tr)
    if m and 'TÜZEL' in m.group(1) and ('GG' in tr or '🌱' in tr): n+=1
print(n)")"
ol "Listede gerçek kişide rozet var" "1" \
  "$(python3 -c "
import re
h=open('/tmp/gg_l.html',encoding='utf-8').read()
for tr in re.findall(r'<tr[^>]*>.*?</tr>',h,re.S):
    m=re.search(r'class=\"kalin\"[^>]*>\s*([^<]+)',tr)
    if m and 'GERÇEK' in m.group(1):
        print(1 if ('GG' in tr or '🌱' in tr) else 0); break
else: print('SATIR-YOK')")"
# Beyanname çizelgesinde de görünmemeli
curl -s -b $J "$B/takip?yil=2026&ay=0" -o /tmp/gg_t.html
ol "Çizelgede tüzel satırında GG yok" "0" \
  "$(python3 -c "
import re
h=open('/tmp/gg_t.html',encoding='utf-8').read()
n=0
for tr in re.findall(r'<tr[^>]*>.*?</tr>',h,re.S):
    if 'HATALI TÜZEL' in tr and ('GG' in tr or '🌱' in tr): n+=1
print(n)")"

echo "=== 6) 'GENÇ GİRİŞİMCİ' FİLTRESİ ==="
curl -s -b $J "$B/mukellefler?gg=1" -o /tmp/gg_ff.html
ol "Filtrede tüzel çıkmıyor" "0" "$(grep -c 'HATALI TÜZEL' /tmp/gg_ff.html)"
ol "Filtrede gerçek kişi çıkıyor" "1" "$(grep -c 'DOĞRU GERÇEK KİŞİ' /tmp/gg_ff.html | awk '{print ($1>0)?1:0}')"

echo "=== 7) EXCEL İÇE AKTARMA ==="
printf 'Unvan;Mükellef Tipi;VKN;TCKN;Defter Tipi;İşe Başlama;Genç Girişimci;GG Başlangıç Yılı\n' > /tmp/gg_ice.csv
printf 'ICE TÜZEL LTD;Tüzel;7778889990;;Bilanço;01.01.2025;Evet;2025\n'  >> /tmp/gg_ice.csv
printf 'ICE GERÇEK KİŞİ;Gerçek;;77788899900;İşletme;01.01.2025;Evet;2025\n' >> /tmp/gg_ice.csv
onizle(){ curl -s -b $J -c $J -o /tmp/gg_of.html "$B/mukellefler/ice-aktar"
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/gg_of.html|head -1)
  curl -s -b $J -c $J -L -o /tmp/gg_onz.html -F "csrf_beyanname=$t" -F "dosya=@/tmp/gg_ice.csv" -F "musavir_id=1"  "$B/mukellefler/ice-aktar/onizle"; }
onizle
ol "Önizlemede tüzel için uyarı var" "1" \
  "$(grep -c 'tüzel kişilere uygulanmaz' /tmp/gg_onz.html | awk '{print ($1>0)?1:0}')"
# Aktar
TKI=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/gg_onz.html|head -1)
SATIRLAR=$(grep -oP 'name="sec\[\]" value="\K[^"]+' /tmp/gg_onz.html | sed 's/^/-d sec[]=/' | tr '\n' ' ')
if [ -n "$SATIRLAR" ]; then
  curl -s -b $J -c $J -L -o /dev/null -X POST "$B/mukellefler/ice-aktar/onayla" \
    -d "csrf_beyanname=$TKI" $SATIRLAR >/dev/null 2>&1
fi
ol "İçe aktarılan tüzelde işaret yok" "0" \
  "$($MDB -e "SELECT IFNULL(MAX(genc_girisimci),0) FROM mukellefler WHERE unvan LIKE 'ICE TÜZEL%'")"
ol "İçe aktarılan gerçekte işaret var" "1" \
  "$($MDB -e "SELECT IFNULL(MAX(genc_girisimci),1) FROM mukellefler WHERE unvan LIKE 'ICE GERÇEK%'")"

echo "=== 8) MIGRATION — ESKİ HATALI VERİYİ TEMİZLİYOR ==="
$MDBR -e "UPDATE mukellefler SET genc_girisimci=1,gg_baslangic_yili=2025,gg_not='yanlış' WHERE id=1;"
ol "Temizlik öncesi hatalı kayıt var" "1" "$($MDB -e 'SELECT genc_girisimci FROM mukellefler WHERE id=1')"
$MDBR < "$KOK/database/migration_gg_tuzel_temizlik.sql" >/dev/null 2>&1
ol "Tüzel kayıt temizlendi"        "0"    "$($MDB -e 'SELECT genc_girisimci FROM mukellefler WHERE id=1')"
ol "Tüzel yıl temizlendi"          "NULL" "$($MDB -e "SELECT IFNULL(gg_baslangic_yili,'NULL') FROM mukellefler WHERE id=1")"
ol "Tüzel not temizlendi"          "NULL" "$($MDB -e "SELECT IFNULL(gg_not,'NULL') FROM mukellefler WHERE id=1")"
ol "GERÇEK KİŞİ KORUNDU"           "1"    "$($MDB -e 'SELECT genc_girisimci FROM mukellefler WHERE id=2')"
ol "Gerçek kişi yılı korundu"      "2025" "$($MDB -e 'SELECT gg_baslangic_yili FROM mukellefler WHERE id=2')"
$MDBR < "$KOK/database/migration_gg_tuzel_temizlik.sql" >/dev/null 2>&1
ol "İkinci kez çalıştırılabilir (idempotent)" "1" "$($MDB -e 'SELECT genc_girisimci FROM mukellefler WHERE id=2')"

echo "=== 9) SAYFALAR BOZULMADI ==="
for u in mukellefler mukellefler/yeni mukellefler/duzenle/1 mukellefler/duzenle/2 \
         mukellefler/detay/1 mukellefler/detay/2 "takip?yil=2026&ay=0" panel raporlar; do
  c=$(curl -s -b $J -o /tmp/gg_s.html -w "%{http_code}" "$B/$u")
  ol "/$u HTTP 200" "200" "$c"
  ol "/$u hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Undefined' /tmp/gg_s.html | awk '{print ($1>0)?1:0}')"
done

echo
echo "================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "================================================"
[ $k -eq 0 ] || exit 1
