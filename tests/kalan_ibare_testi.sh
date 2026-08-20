#!/bin/bash
# =====================================================================
#  "KALAN" SÜTUNU — DURUM FARKINDALIĞI REGRESYON TESTİ
#
#  Sorun: Beyanname verildikten (Onaylandı) sonra bile "3 gün gecikti"
#  yazıyordu. İş bitmişken geri sayım göstermek yanlış ve kaygı verici.
#
#  Yeni davranış:
#    ONAYLANDI                  → "✓ Verildi"   (yeşil)
#    VERILMEYECEK/YUKLENMEYECEK → "Takip dışı"  (gri)
#    diğer                      → eskisi gibi geri sayım
#
#  Kapsam: helper birim testi + beyanname çizelgesi + e-defter +
#          panel + gecikmiş raporu + mükellef detayı + canlı JS.
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 mevcut.
#  Not: Test kendi verisini kurar; başka testlerden bağımsız çalışır.
#  Kullanım:  bash tests/kalan_ibare_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/ki.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
var(){ if echo "$2" | grep -qF "$3"; then echo "  [OK] $1"; g=$((g+1));
       else echo "  [HATA] $1 (bulunamadı: $3)"; k=$((k+1)); fi }
yok(){ if echo "$2" | grep -qF "$3"; then echo "  [HATA] $1 (olmamalıydı: $3)"; k=$((k+1));
       else echo "  [OK] $1"; g=$((g+1)); fi }

# ---------------------------------------------------------------------
#  1) HELPER BİRİM TESTİ (HTTP'siz — saf mantık)
# ---------------------------------------------------------------------
echo "=== 1) kalanGunMetni() BİRİM TESTİ ==="
php > /tmp/ki_birim.txt <<'PHPEOF'
<?php
define('APPPATH', __DIR__ . '/app/');
require __DIR__ . '/app/Helpers/beyanname_helper.php';

$gecmis  = date('Y-m-d', strtotime('-3 days'));
$bugun   = date('Y-m-d');
$yakin   = date('Y-m-d', strtotime('+2 days'));
$uzak    = date('Y-m-d', strtotime('+30 days'));

$sonuc = [];
// durum VERİLMEDEN → eski davranış birebir korunmalı
$sonuc['eski_gecikmis'] = kalanGunMetni($gecmis)['metin'];
$sonuc['eski_bugun']    = kalanGunMetni($bugun)['metin'];
$sonuc['eski_yakin']    = kalanGunMetni($yakin)['metin'];
$sonuc['eski_sinif']    = kalanGunMetni($gecmis)['sinif'];
// durum ile
$sonuc['onay_gecmis']   = kalanGunMetni($gecmis, 'ONAYLANDI')['metin'];
$sonuc['onay_sinif']    = kalanGunMetni($gecmis, 'ONAYLANDI')['sinif'];
$sonuc['onay_bitti']    = kalanGunMetni($gecmis, 'ONAYLANDI')['bitti'] ? '1' : '0';
$sonuc['onay_ileri']    = kalanGunMetni($uzak, 'ONAYLANDI')['metin'];
$sonuc['verilmeyecek']  = kalanGunMetni($gecmis, 'VERILMEYECEK')['metin'];
$sonuc['verilmeyecek_s']= kalanGunMetni($gecmis, 'VERILMEYECEK')['sinif'];
$sonuc['yuklenmeyecek'] = kalanGunMetni($gecmis, 'YUKLENMEYECEK')['metin'];
$sonuc['bekliyor']      = kalanGunMetni($gecmis, 'BEKLIYOR')['metin'];
$sonuc['bekliyor_bitti']= kalanGunMetni($gecmis, 'BEKLIYOR')['bitti'] ? '1' : '0';
$sonuc['hazir']         = kalanGunMetni($gecmis, 'HAZIR')['metin'];
$sonuc['devam']         = kalanGunMetni($gecmis, 'DEVAM')['metin'];
// gün değeri her koşulda korunmalı (sıralama/hesap bozulmasın)
$sonuc['gun_onay']      = (string) kalanGunMetni($gecmis, 'ONAYLANDI')['gun'];
$sonuc['gun_bekliyor']  = (string) kalanGunMetni($gecmis, 'BEKLIYOR')['gun'];

foreach ($sonuc as $ad => $d) { echo $ad . '=' . $d . "\n"; }
PHPEOF
al(){ grep "^$1=" /tmp/ki_birim.txt | cut -d= -f2-; }

ol "Durumsuz: gecikme metni korundu" "3 gün gecikti" "$(al eski_gecikmis)"
ol "Durumsuz: BUGÜN SON GÜN korundu" "BUGÜN SON GÜN" "$(al eski_bugun)"
ol "Durumsuz: kalan gün korundu" "2 gün kaldı" "$(al eski_yakin)"
ol "Durumsuz: kırmızı sınıf korundu" "kirmizi" "$(al eski_sinif)"
ol "ONAYLANDI → ✓ Verildi" "✓ Verildi" "$(al onay_gecmis)"
ol "ONAYLANDI → yeşil" "yesil" "$(al onay_sinif)"
ol "ONAYLANDI → bitti=true" "1" "$(al onay_bitti)"
ol "ONAYLANDI (süresi dolmamış) da ✓ Verildi" "✓ Verildi" "$(al onay_ileri)"
ol "VERILMEYECEK → Takip dışı" "Takip dışı" "$(al verilmeyecek)"
ol "VERILMEYECEK → gri" "gri" "$(al verilmeyecek_s)"
ol "YUKLENMEYECEK (e-defter) → Takip dışı" "Takip dışı" "$(al yuklenmeyecek)"
ol "BEKLIYOR → gecikme sürüyor" "3 gün gecikti" "$(al bekliyor)"
ol "BEKLIYOR → bitti=false" "0" "$(al bekliyor_bitti)"
ol "HAZIR → gecikme sürüyor" "3 gün gecikti" "$(al hazir)"
ol "DEVAM (e-defter) → gecikme sürüyor" "3 gün gecikti" "$(al devam)"
ol "gün değeri ONAYLANDI'da da doğru" "-3" "$(al gun_onay)"
ol "gün değeri BEKLIYOR'da da doğru" "-3" "$(al gun_bekliyor)"

# ---------------------------------------------------------------------
#  TEST VERİSİ — geçmiş son tarihli, 4 ayrı durumda kayıt
# ---------------------------------------------------------------------
veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM beyanname_takip; ALTER TABLE beyanname_takip AUTO_INCREMENT=1;
DELETE FROM mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (1,'SMMM','Ali Yılmaz','Yılmaz',1);
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,tc_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'K01','ONAYLI MUKELLEF','gercek','11111111111','isletme','2019-01-01',1),
 (2,1,'K02','BEKLEYEN MUKELLEF','gercek','22222222222','isletme','2019-01-01',1),
 (3,1,'K03','VERILMEYECEK MUKELLEF','gercek','33333333333','isletme','2019-01-01',1),
 (4,1,'K04','HAZIR MUKELLEF','gercek','44444444444','isletme','2019-01-01',1);
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
SELECT m.id,t.id,1,NOW(),NOW() FROM mukellefler m JOIN beyanname_turleri t ON t.kod='GELIR_GECICI';
INSERT INTO beyanname_takip
 (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,
  yasal_son_tarih,son_tarih,durum,damga_tutari,created_at,updated_at)
SELECT m.id,t.id,2026,2,'2. Dönem (Nis-May-Haz) 2026','2026-04-01','2026-06-30',
       DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY),
       CASE m.id WHEN 1 THEN 'ONAYLANDI' WHEN 2 THEN 'BEKLIYOR'
                 WHEN 3 THEN 'VERILMEYECEK' ELSE 'HAZIR' END,
       1085.20, NOW(), NOW()
FROM mukellefler m JOIN beyanname_turleri t ON t.kod='GELIR_GECICI';"
}
veriKur

rm -f $J
curl -s -c $J -o /tmp/f.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=admin" -d "sifre=Test1234" $B/giris

echo ""
echo "=== 2) BEYANNAME ÇİZELGESİ ==="
curl -s -b $J -o /tmp/ki_t.html "$B/takip?yil=2026&ay=0"
S=$(cat /tmp/ki_t.html)
ol "Sayfa açıldı, fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/ki_t.html)"
ol "Onaylı satırda '✓ Verildi'" "1" \
   "$(grep -c 'rozet yesil">✓ Verildi' /tmp/ki_t.html)"
ol "Verilmeyecek satırda 'Takip dışı'" "1" \
   "$(grep -c 'rozet gri">Takip dışı' /tmp/ki_t.html)"
ol "Yalnız 2 satırda gecikme (BEKLIYOR+HAZIR)" "2" \
   "$(grep -c 'rozet kirmizi">3 gün gecikti' /tmp/ki_t.html)"

# NOT: JS kodunda da 'gecikmis-satir' geçer; yalnız <tr> etiketleri sayılır.
ol "Kırmızı zemin yalnız 2 satırda" "2" \
   "$(grep -c '<tr class="gecikmis-satir"' /tmp/ki_t.html)"
ol "Kalan hücresi JS için işaretli" "4" \
   "$(grep -cF 'class="kalan-hucre"' /tmp/ki_t.html)"
ol "data-gun özniteliği yazıldı" "4" "$(grep -c 'data-gun=\"-3\"' /tmp/ki_t.html)"

echo ""
echo "=== 3) ONAYLI KAYIT SÜRESİ DOLMAMIŞSA DA 'VERİLDİ' ==="
$MDBR -e "UPDATE beyanname_takip SET son_tarih=DATE_ADD(CURDATE(), INTERVAL 5 DAY),
          yasal_son_tarih=DATE_ADD(CURDATE(), INTERVAL 5 DAY) WHERE mukellef_id=1;"
curl -s -b $J -o /tmp/ki_t2.html "$B/takip?yil=2026&ay=0"
ol "İleri tarihli onaylıda da '✓ Verildi'" "1" \
   "$(grep -c 'rozet yesil">✓ Verildi' /tmp/ki_t2.html)"
yok "'5 gün kaldı' yazmıyor" "$(cat /tmp/ki_t2.html)" '5 gün kaldı'
# geri al
$MDBR -e "UPDATE beyanname_takip SET son_tarih=DATE_SUB(CURDATE(), INTERVAL 3 DAY),
          yasal_son_tarih=DATE_SUB(CURDATE(), INTERVAL 3 DAY) WHERE mukellef_id=1;"

echo ""
echo "=== 4) DİĞER EKRANLAR ÇÖKMÜYOR ==="
for yol in "panel" "raporlar/gecikmis" "mukellefler/detay/1?yil=2026" "edefter?yil=2026&ay=8" "karsit"; do
  KOD=$(curl -s -b $J -o /tmp/ki_s.html -w '%{http_code}' "$B/$yol")
  ol "$yol açıldı (200)" "200" "$KOD"
  ol "$yol fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/ki_s.html)"
done

echo ""
echo "=== 5) GECİKMİŞ RAPORU / PANEL SAYIMI ==="
# Bu ekranlar zaten yalnız BEKLIYOR+HAZIR çeker; onaylı kayıt HİÇ görünmemeli
curl -s -b $J -o /tmp/ki_g.html "$B/raporlar/gecikmis"
ol "Gecikmiş raporunda onaylı mükellef YOK" "0" "$(grep -c 'ONAYLI MUKELLEF' /tmp/ki_g.html)"
ol "Gecikmiş raporunda verilmeyecek YOK" "0" "$(grep -c 'VERILMEYECEK MUKELLEF' /tmp/ki_g.html)"
ol "Bekleyen mükellef raporda VAR" "1" \
   "$(grep -c 'BEKLEYEN MUKELLEF' /tmp/ki_g.html | awk '{print ($1>0)?1:0}')"

# NOT: Panelde "evrakı gelmeyenler" kartı da mükellef adı listeler ve
# ONAYLI MUKELLEF'in evrağı gerçekten gelmemiştir. Bu yüzden sayfa geneli
# değil, YALNIZ gecikmiş beyanname tablosundaki satırlar denetlenir.
curl -s -b $J -o /tmp/ki_p.html "$B/panel"
ol "Panel gecikmiş kartında onaylı YOK" "0" \
   "$(tr '\n' ' ' < /tmp/ki_p.html \
      | grep -oE 'gecikmis-satir.{0,400}' \
      | grep -c 'ONAYLI MUKELLEF')"
ol "Panel gecikmiş kartında bekleyen VAR" "1" \
   "$(tr '\n' ' ' < /tmp/ki_p.html \
      | grep -oE 'gecikmis-satir.{0,400}' \
      | grep -c 'BEKLEYEN MUKELLEF' | awk '{print ($1>0)?1:0}')"

echo ""
echo "=== 6) E-DEFTER EKRANI ==="
# E-defter kendi ibarelerini kullanır: "✓ Yüklendi" / "Takip dışı"
$MDBR -e "UPDATE mukellefler SET edefter_donem='AYLIK' WHERE id IN (1,2,3);"
curl -s -b $J -o /dev/null "$B/edefter/toplu-uret?yil=2026"
$MDBR -e "UPDATE edefter_takip SET durum='BEKLIYOR';
          UPDATE edefter_takip SET durum='ONAYLANDI' WHERE mukellef_id=1;
          UPDATE edefter_takip SET durum='YUKLENMEYECEK' WHERE mukellef_id=3;"
curl -s -b $J -o /tmp/ki_e.html "$B/edefter?yil=2026&ay=8"
ol "E-defter fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/ki_e.html)"
ol "Yüklenen beratta '✓ Yüklendi'" "1" \
   "$(grep -c '✓ Yüklendi' /tmp/ki_e.html | awk '{print ($1>0)?1:0}')"
ol "Yüklenmeyecekte 'Takip dışı'" "1" \
   "$(grep -c 'Takip dışı' /tmp/ki_e.html | awk '{print ($1>0)?1:0}')"
ol "Yüklenen beratta gecikme yazmıyor" "0" \
   "$(tr '\n' ' ' < /tmp/ki_e.html | grep -oE 'onaylandi\">[^<]*gecikti' | wc -l)"

echo ""
echo "=== 7) CANLI JS GÜNCELLEMESİ (kod denetimi) ==="
ol "kalanRozetYenile tanımlı" "1" \
   "$(grep -c 'function kalanRozetYenile' app/Views/takip/index.php)"
ol "Durum değişiminde çağrılıyor" "1" \
   "$(grep -c 'kalanRozetYenile(id, yeni)' app/Views/takip/index.php)"
ol "Eş (SGK) satırında da çağrılıyor" "1" \
   "$(grep -c 'kalanRozetYenile(esId, durum)' app/Views/takip/index.php)"
ol "JS ✓ Verildi metnini kullanıyor" "1" \
   "$(grep -cF "metin = '✓ Verildi'" app/Views/takip/index.php)"
ol "JS Takip dışı metnini kullanıyor" "1" \
   "$(grep -cF "metin = 'Takip dışı'" app/Views/takip/index.php)"
ol "JS geri alındığında gecikmeyi geri kuruyor" "1" \
   "$(grep -cF "gün gecikti'" app/Views/takip/index.php | awk '{print ($1>0)?1:0}')"

echo ""
echo "=== 8) GERİYE DÖNÜK UYUM ==="
ol "Helper ikinci parametre isteğe bağlı" "1" \
   "$(grep -cF 'function kalanGunMetni(string $sonTarih, ?string $durum = null)' app/Helpers/beyanname_helper.php)"
ol "Dönen dizide bitti anahtarı var" "1" \
   "$(grep -cF "'bitti' =>" app/Helpers/beyanname_helper.php | awk '{print ($1>0)?1:0}')"
ol "Panel durum yokluğuna dayanıklı" "2" \
   "$(grep -cF "durum'] ?? null" app/Views/dashboard/index.php)"
ol "Gecikmiş raporu durum yokluğuna dayanıklı" "1" \
   "$(grep -cF "durum'] ?? null" app/Views/raporlar/gecikmis.php)"
ol "Şema değişikliği GEREKMİYOR" "0" "$(ls database/ | grep -c 'kalan_ibare')"

echo ""
echo "====================================="
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "====================================="
[ $k -eq 0 ] && exit 0 || exit 1
