#!/bin/bash
# =====================================================================
#  MALİ MÜŞAVİR FİLTRESİ — REGRESYON TESTİ
#
#  Doğrulanan kusur:
#    Görünümlerde seçili müşavir `(int) $filtre['musavir_id']` ile
#    karşılaştırılıyordu. Ancak kapsamBelirle() bu değeri DİZİ döndürür
#    (örn. [2]) ve PHP'de boş olmayan bir diziyi (int)'e çevirmek HER ZAMAN
#    1 verir. Sonuç: hangi müşavir seçilirse seçilsin açılır listede hep
#    LİSTEDEKİ İLK MÜŞAVİR "selected" görünüyordu. Liste doğru süzülüyordu
#    ama kullanıcı yanlış seçim görüyordu.
#
#  Ek düzeltme:
#    Filtre yalnızca rol='admin' iken gösteriliyordu. Birden çok müşavire
#    erişen müşavir/personel de aralarında süzebilmeli; koşul artık
#    "seçilebilir müşavir sayısı > 1".
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin / musavir / personel (şifre Test1234) kullanıcıları var.
#  Not: Test kendi verisini kurar ve sonunda geri temizler.
#  Kullanım:  bash tests/musavir_filtre_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/mf_a.txt
JM=/tmp/mf_m.txt
JP=/tmp/mf_p.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/mf_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mf_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Açılır listede seçili olan müşavir id'si (yoksa 'YOK')
secili(){ python3 - "$1" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('name="musavir_id"')
if i<0: print('SELECT-YOK'); raise SystemExit
blok=h[i:h.find('</select>',i)]
m=re.search(r'<option value="(\d+)"[^>]*selected',blok)
print(m.group(1) if m else 'YOK')
PY
}
# Filtre açılır listesi sayfada var mı
varMi(){ grep -c '<select name="musavir_id"' "$1" | awk '{print ($1>0)?1:0}'; }
# Listedeki satır sayısı
satir(){ grep -oE '<tr class="[^"]*">' "$1" | wc -l | tr -d ' '; }

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri; TRUNCATE evrak_takip;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
DELETE FROM kullanici_musavirleri WHERE kullanici_id IN (SELECT id FROM kullanicilar WHERE kullanici_adi IN ('musavir','personel'));
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES
 (1,'SMMM','Ali Yılmaz','Yılmaz',1),(2,'SMMM','Veli Demir','Demir',1);
UPDATE kullanicilar SET musavir_id=2 WHERE kullanici_adi='musavir';
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','ALFA LTD','tuzel','1112223334','bilanco','2019-01-01',1),
 (2,1,'M002','GAMA LTD','tuzel','2223334445','bilanco','2019-01-01',1),
 (3,2,'M003','BETA LTD','tuzel','5556667778','bilanco','2019-01-01',1);
INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,created_at,updated_at) VALUES
 (1,1,2026,7,'Temmuz','2026-07-01','2026-07-31','2026-08-28','2026-08-28','BEKLIYOR',NOW(),NOW()),
 (2,1,2026,7,'Temmuz','2026-07-01','2026-07-31','2026-08-28','2026-08-28','BEKLIYOR',NOW(),NOW()),
 (3,1,2026,7,'Temmuz','2026-07-01','2026-07-31','2026-08-28','2026-08-28','BEKLIYOR',NOW(),NOW());
INSERT INTO evrak_takip (mukellef_id,evrak_turu_id,yil,ay,durum,created_at,updated_at)
SELECT m.id, t.id, 2026, 7, 'GELDI', NOW(), NOW()
FROM mukellefler m CROSS JOIN evrak_turleri t WHERE t.aktif=1 LIMIT 9;"
}

veriKur
giris admin $J

echo "=== 1) FİLTRE ADMIN'DE GÖRÜNÜYOR ==="
curl -s -b $J "$B/takip?yil=2026&ay=8" -o /tmp/mf_t0.html
ol "Beyanname Takip'te filtre var" "1" "$(varMi /tmp/mf_t0.html)"
ol "Sayfada hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Undefined' /tmp/mf_t0.html | awk '{print ($1>0)?1:0}')"
ol "'Tümü' seçiliyken hiçbiri işaretli değil" "YOK" "$(secili /tmp/mf_t0.html)"
ol "Tüm kayıtlar geliyor (3)" "3" "$(satir /tmp/mf_t0.html)"

echo "=== 2) ASIL KUSUR: SEÇİLEN MÜŞAVİR DOĞRU GÖSTERİLİYOR ==="
# Kusurlu sürümde musavir_id=2 seçilse bile listede 1 görünüyordu.
for MID in 1 2; do
  curl -s -b $J "$B/takip?yil=2026&ay=8&musavir_id=$MID" -o /tmp/mf_t$MID.html
  ol "musavir_id=$MID → listede $MID seçili" "$MID" "$(secili /tmp/mf_t$MID.html)"
done
ol "İki seçim farklı sonuç veriyor" "1" \
  "$([ "$(secili /tmp/mf_t1.html)" != "$(secili /tmp/mf_t2.html)" ] && echo 1 || echo 0)"
ol "musavir_id=1 → 2 kayıt" "2" "$(satir /tmp/mf_t1.html)"
ol "musavir_id=2 → 1 kayıt" "1" "$(satir /tmp/mf_t2.html)"

echo "=== 3) DİĞER EKRANLARDA DA SEÇİM KORUNUYOR ==="
curl -s -b $J "$B/evrak?yil=2026&ay=8&musavir_id=2"        -o /tmp/mf_e.html
ol "Evrak Takip seçili=2"        "2" "$(secili /tmp/mf_e.html)"
curl -s -b $J "$B/karsit?musavir_id=2"                     -o /tmp/mf_k.html
ol "Karşıt İnceleme seçili=2"    "2" "$(secili /tmp/mf_k.html)"
curl -s -b $J "$B/odeme?yil=2026&ay=8&musavir_id=2"        -o /tmp/mf_o.html
ol "Ödeme Listesi seçili=2"      "2" "$(secili /tmp/mf_o.html)"
curl -s -b $J "$B/mukellefler?musavir_id=2"                -o /tmp/mf_mk.html
ol "Mükellefler seçili=2"        "2" "$(secili /tmp/mf_mk.html)"
# Karşıt inceleme eskiden seçimi HİÇ korumuyordu
curl -s -b $J "$B/karsit?musavir_id=1" -o /tmp/mf_k1.html
ol "Karşıt İnceleme seçili=1 (eskiden hiç korunmuyordu)" "1" "$(secili /tmp/mf_k1.html)"

echo "=== 4) ÖZET KART BAĞLANTILARI DOĞRU MÜŞAVİRİ TAŞIYOR ==="
ol "Kart bağlantısında musavir_id=2" "1" \
  "$(grep -oE 'takip\?[^\"]*durum=ONAYLANDI' /tmp/mf_t2.html | head -1 | grep -c 'musavir_id=2')"
ol "Tümü seçiliyken kartta musavir_id yok" "0" \
  "$(grep -oE 'takip\?[^\"]*durum=ONAYLANDI' /tmp/mf_t0.html | head -1 | grep -c 'musavir_id=')"

echo "=== 5) TEK MÜŞAVİRLİ KULLANICIDA FİLTRE GİZLİ ==="
giris musavir $JM
curl -s -b $JM "$B/takip?yil=2026&ay=8" -o /tmp/mf_m1.html
ol "Tek müşavirli müşavirde filtre yok" "0" "$(varMi /tmp/mf_m1.html)"
ol "Yalnızca kendi kaydını görüyor (1)" "1" "$(satir /tmp/mf_m1.html)"
giris personel $JP
curl -s -b $JP "$B/takip?yil=2026&ay=8" -o /tmp/mf_p1.html
ol "Personelde filtre yok" "0" "$(varMi /tmp/mf_p1.html)"

echo "=== 6) ÇOK MÜŞAVİRLİ KULLANICIDA FİLTRE AÇILIYOR ==="
KID=$($MDB -e "SELECT id FROM kullanicilar WHERE kullanici_adi='musavir'")
$MDBR -e "INSERT IGNORE INTO kullanici_musavirleri (kullanici_id,musavir_id,created_at)
          VALUES ($KID,1,NOW()),($KID,2,NOW());"
giris musavir $JM
curl -s -b $JM "$B/takip?yil=2026&ay=8" -o /tmp/mf_m2.html
ol "İki müşavirli kullanıcıda filtre VAR" "1" "$(varMi /tmp/mf_m2.html)"
ol "Her iki portföyü görüyor (3)" "3" "$(satir /tmp/mf_m2.html)"
ol "Seçenek sayısı 3 (Tümü + 2 müşavir)" "3" \
  "$(python3 -c "
import re
h=open('/tmp/mf_m2.html',encoding='utf-8').read()
i=h.find('name=\"musavir_id\"')
print(len(re.findall(r'<option', h[i:h.find('</select>',i)])))")"
curl -s -b $JM "$B/takip?yil=2026&ay=8&musavir_id=1" -o /tmp/mf_m3.html
ol "Çok müşavirlide seçim doğru gösteriliyor" "1" "$(secili /tmp/mf_m3.html)"
ol "Çok müşavirlide süzme çalışıyor (2)" "2" "$(satir /tmp/mf_m3.html)"

echo "=== 7) YETKİ SIZINTISI YOK ==="
$MDBR -e "DELETE FROM kullanici_musavirleri WHERE kullanici_id=$KID;"
giris musavir $JM
curl -s -b $JM "$B/takip?yil=2026&ay=8&musavir_id=1" -o /tmp/mf_z.html
ol "Yetkisiz müşavir zorlanamıyor (1 kayıt)" "1" "$(satir /tmp/mf_z.html)"
ol "Başkasının mükellefi görünmüyor" "0" "$(grep -c 'ALFA LTD' /tmp/mf_z.html)"
ol "Kendi mükellefi görünüyor" "1" "$(grep -c 'BETA LTD' /tmp/mf_z.html | awk '{print ($1>0)?1:0}')"

echo "=== 8) YARDIMCI İŞLEV (secilenMusavirId) ==="
php -r '
require "'"$(cd "$(dirname "$0")/.." && pwd)"'/app/Helpers/beyanname_helper.php";
$t = [
  ["giris"=>[2],           "bekl"=>2,    "ad"=>"tek elemanli dizi"],
  ["giris"=>[1,2],         "bekl"=>null, "ad"=>"cok elemanli dizi => null"],
  ["giris"=>[],            "bekl"=>null, "ad"=>"bos dizi => null"],
  ["giris"=>null,          "bekl"=>null, "ad"=>"null"],
  ["giris"=>"",            "bekl"=>null, "ad"=>"bos metin"],
  ["giris"=>"3",           "bekl"=>3,    "ad"=>"metin sayi"],
  ["giris"=>5,             "bekl"=>5,    "ad"=>"tam sayi"],
  ["giris"=>0,             "bekl"=>null, "ad"=>"sifir => null"],
];
$g=0;$k=0;
foreach ($t as $x) {
  $s = secilenMusavirId($x["giris"]);
  if ($s === $x["bekl"]) { echo "  [OK] {$x["ad"]}\n"; $g++; }
  else { echo "  [HATA] {$x["ad"]} (bekl:".var_export($x["bekl"],true)." ger:".var_export($s,true).")\n"; $k++; }
}
exit($k>0?1:0);
' && g=$((g+8)) || k=$((k+1))

echo "=== 9) SAYFALAR BOZULMADI ==="
giris admin $J
for u in "takip?yil=2026&ay=8" "evrak?yil=2026&ay=8" karsit "odeme?yil=2026&ay=8" mukellefler panel raporlar; do
  c=$(curl -s -b $J -o /tmp/mf_s.html -w "%{http_code}" "$B/$u")
  ol "/$u HTTP 200" "200" "$c"
  ol "/$u hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Undefined variable|Undefined function' /tmp/mf_s.html | awk '{print ($1>0)?1:0}')"
done

echo
echo "================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "================================================"
[ $k -eq 0 ] || exit 1
