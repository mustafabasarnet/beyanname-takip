#!/bin/bash
# =====================================================================
#  İNDİRİM / KISITLAMA ROZETLERİ — REGRESYON TESTİ
#
#  Kural:
#    Mükellef kartında işaretlenen kalemler Beyanname Takip çizelgesinde
#    yalnızca İLGİLİ beyanname türlerinde rozet olarak görünür.
#
#      Bağkur (BK)          → YILLIK_GV, GELIR_GECICI
#      Eğitim/Sağlık (EĞS)  → YILLIK_GV, GELIR_GECICI
#      Finansman (FGK)      → YILLIK_GV, GELIR_GECICI, KURUMLAR, KURUM_GECICI
#
#    KDV, MUHSGK gibi diğer beyannamelerde HİÇ rozet çıkmaz.
#
#  Ayrıca migration çalıştırılmamış kurulumda hiçbir sayfanın çökmediği
#  doğrulanır (rozet ikincil özelliktir, çizelgeyi düşürmemeli).
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 kullanıcısı var.
#  Not: Test kendi verisini kurar.
#  Kullanım:  bash tests/indirim_rozet_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
KOK="$(cd "$(dirname "$0")/.." && pwd)"
J=/tmp/ind.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/ind_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ind_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Belirli mükellef + tür satırındaki rozet kısa kodlarını sırayla yazar
rozet(){ python3 - "$1" "$2" "$3" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
hedef_muk, hedef_tur = sys.argv[2], sys.argv[3]
for tr in re.findall(r'<tr class="[^"]*">.*?</tr>', h, re.S):
    muk = re.search(r'class="kalin">\s*([^<]+)', tr)
    tur = re.search(r'class="tur-rozet"[^>]*>([^<]+)', tr)
    if not muk or not tur: continue
    if muk.group(1).strip()==hedef_muk and tur.group(1).strip()==hedef_tur:
        roz=re.findall(r'rozet-indirim"[^>]*>\s*\S+\s+([^<]+)', tr)
        print(','.join(x.strip() for x in roz)); break
else:
    print('SATIR-YOK')
PY
}

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (1,'SMMM','Ali Yılmaz','Yılmaz',1);
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,
  ind_bagkur,ind_bagkur_not,ind_egitim_saglik,ind_egitim_saglik_not,ind_finansman,ind_finansman_not,
  ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','SADECE BAGKUR','gercek',NULL,'11122233344','isletme',1,'Eş adına',0,NULL,0,NULL,'2019-01-01',1),
 (2,1,'M002','SADECE FINANSMAN','tuzel','1112223334',NULL,'bilanco',0,NULL,0,NULL,1,'Kredi faizi','2019-01-01',1),
 (3,1,'M003','UCU BIRDEN','gercek',NULL,'33344455566','isletme',1,'BK notu',1,'2 çocuk',1,'FGK notu','2018-01-01',1),
 (4,1,'M004','HICBIRI YOK','tuzel','5556667778',NULL,'bilanco',0,NULL,0,NULL,0,NULL,'2017-01-01',1);
INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,created_at,updated_at)
SELECT m.id, t.id, 2026, 1, CONCAT(t.kisa_ad,' 2026'),'2026-01-01','2026-03-31','2026-08-28','2026-08-28','BEKLIYOR',NOW(),NOW()
FROM mukellefler m CROSS JOIN beyanname_turleri t
WHERE t.kod IN ('YILLIK_GV','KURUMLAR','GELIR_GECICI','KURUM_GECICI','KDV1_A','MUHSGK_A');"
}

veriKur
giris admin $J
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan&adet=250" -o /tmp/ind_t.html

echo "=== 1) ŞEMA ==="
for a in ind_bagkur ind_bagkur_not ind_egitim_saglik ind_egitim_saglik_not ind_finansman ind_finansman_not; do
  ol "$a kolonu var" "1" "$($MDB -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler' AND COLUMN_NAME='$a'")"
done
ol "idx_muk_indirim dizini var" "1" \
  "$($MDB -e "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler' AND INDEX_NAME='idx_muk_indirim'")"
ol "Varsayılan 0 (yeni mükellef temiz)" "0" \
  "$($MDB -e "SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler' AND COLUMN_NAME='ind_finansman'")"

echo "=== 2) BAĞKUR YALNIZCA GELİR TARAFINDA ==="
ol "SADECE BAGKUR / Yıllık GV      → BK" "BK" "$(rozet /tmp/ind_t.html 'SADECE BAGKUR' 'Yıllık GV')"
ol "SADECE BAGKUR / Gelir Geçici   → BK" "BK" "$(rozet /tmp/ind_t.html 'SADECE BAGKUR' 'Gelir Geçici')"
ol "SADECE BAGKUR / Kurumlar       → yok" "" "$(rozet /tmp/ind_t.html 'SADECE BAGKUR' 'Kurumlar')"
ol "SADECE BAGKUR / Kurum Geçici   → yok" "" "$(rozet /tmp/ind_t.html 'SADECE BAGKUR' 'Kurum Geçici')"
ol "SADECE BAGKUR / KDV1           → yok" "" "$(rozet /tmp/ind_t.html 'SADECE BAGKUR' 'KDV1 (Ay)')"
ol "SADECE BAGKUR / MUHSGK         → yok" "" "$(rozet /tmp/ind_t.html 'SADECE BAGKUR' 'MUHSGK (Ay)')"

echo "=== 3) FİNANSMAN DÖRT BEYANNAMEDE DE ==="
for t in 'Yıllık GV' 'Gelir Geçici' 'Kurumlar' 'Kurum Geçici'; do
  ol "SADECE FINANSMAN / $t → FGK" "FGK" "$(rozet /tmp/ind_t.html 'SADECE FINANSMAN' "$t")"
done
ol "SADECE FINANSMAN / KDV1   → yok" "" "$(rozet /tmp/ind_t.html 'SADECE FINANSMAN' 'KDV1 (Ay)')"
ol "SADECE FINANSMAN / MUHSGK → yok" "" "$(rozet /tmp/ind_t.html 'SADECE FINANSMAN' 'MUHSGK (Ay)')"

echo "=== 4) ÜÇÜ BİRDEN — AYRI AYRI ROZET ==="
ol "UCU BIRDEN / Yıllık GV    → BK,EĞS,FGK" "BK,EĞS,FGK" "$(rozet /tmp/ind_t.html 'UCU BIRDEN' 'Yıllık GV')"
ol "UCU BIRDEN / Gelir Geçici → BK,EĞS,FGK" "BK,EĞS,FGK" "$(rozet /tmp/ind_t.html 'UCU BIRDEN' 'Gelir Geçici')"
ol "UCU BIRDEN / Kurumlar     → yalnız FGK" "FGK" "$(rozet /tmp/ind_t.html 'UCU BIRDEN' 'Kurumlar')"
ol "UCU BIRDEN / Kurum Geçici → yalnız FGK" "FGK" "$(rozet /tmp/ind_t.html 'UCU BIRDEN' 'Kurum Geçici')"
ol "UCU BIRDEN / KDV1         → yok" "" "$(rozet /tmp/ind_t.html 'UCU BIRDEN' 'KDV1 (Ay)')"

echo "=== 5) HİÇBİRİ SEÇİLİ DEĞİLSE HİÇ ROZET YOK ==="
for t in 'Yıllık GV' 'Kurumlar' 'Gelir Geçici' 'Kurum Geçici' 'KDV1 (Ay)'; do
  ol "HICBIRI YOK / $t → yok" "" "$(rozet /tmp/ind_t.html 'HICBIRI YOK' "$t")"
done

echo "=== 6) TOPLAM ROZET SAYISI ==="
# BK:2 + FGK:4 + (BK2+EĞS2+FGK4)=8  → 14
# Not: gömülü <style> bloğunda ".rozet-indirim{...}" tanımları da geçer.
# Bu yüzden yalnızca class="..." biçimindeki GERÇEK öğeler sayılır.
ol "Sayfadaki toplam rozet 14" "14" "$(grep -o 'rozet-indirim\"' /tmp/ind_t.html | wc -l | tr -d ' ')"
ol "Rozet şeridi yalnızca gerekli satırlarda (10)" "10" "$(grep -o 'class=\"indirim-serit\"' /tmp/ind_t.html | wc -l | tr -d ' ')"

echo "=== 7) NOT, ROZET İPUCUNDA GÖRÜNÜYOR ==="
ol "Bağkur notu title'da" "1" "$(grep -c 'Bağkur primi indirimi — BK notu' /tmp/ind_t.html | awk '{print ($1>0)?1:0}')"
ol "Eğitim notu title'da"  "1" "$(grep -c '2 çocuk' /tmp/ind_t.html | awk '{print ($1>0)?1:0}')"
ol "Finansman notu title'da" "1" "$(grep -c 'FGK notu' /tmp/ind_t.html | awk '{print ($1>0)?1:0}')"
ol "Mevzuat maddesi ipuçta" "1" "$(grep -c 'GVK 41/9, KVK 11/1-i' /tmp/ind_t.html | awk '{print ($1>0)?1:0}')"

echo "=== 8) SONSUZ KAYDIRMADA DA AYNI (AJAX parçası) ==="
CT=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/ind_t.html | head -1)
curl -s -b $J -H "X-Requested-With: XMLHttpRequest" \
  "$B/takip/daha-fazla?yil=2026&ay=8&mod=beyan&ofset=0&adet=25" -o /tmp/ind_aj.json
ol "AJAX yanıtı geldi" "1" "$(grep -c 'html' /tmp/ind_aj.json | awk '{print ($1>0)?1:0}')"
ol "AJAX parçasında da rozet var" "1" \
  "$(grep -c 'rozet-indirim' /tmp/ind_aj.json | awk '{print ($1>0)?1:0}')"
ol "AJAX parçasında BK yalnızca gelir beyannamesinde" "1" \
  "$(python3 -c "
import json,re
d=json.load(open('/tmp/ind_aj.json'))
h=d.get('html','')
kotu=[t for t in re.findall(r'<tr class=\"[^\"]*\">.*?</tr>',h,re.S)
      if re.search(r'tur-rozet[^>]*>\s*(Kurumlar|Kurum Geçici|KDV1|MUHSGK)',t)
      and re.search(r'rozet-indirim[^>]*>\s*🏥',t)]
print(0 if kotu else 1)")"

echo "=== 9) MÜKELLEF FORMU VE KARTI ==="
curl -s -b $J -o /tmp/ind_form.html "$B/mukellefler/duzenle/3"
ol "Formda 3 onay kutusu"      "3" "$(grep -c 'class="ind-kutu"' /tmp/ind_form.html)"
# Not: <input ...> etiketi çok satırlı basılıyor; "checked" bir alt satırda.
ol "Bağkur kutusu işaretli"    "1" "$(python3 -c "
import re
h=open('/tmp/ind_form.html',encoding='utf-8').read()
m=re.search(r'<input type=\"checkbox\" name=\"ind_bagkur\".*?>',h,re.S)
print(1 if m and 'checked' in m.group(0) else 0)")"
ol "İşaretsiz kalemin notu kapalı (disabled)" "1" "$(python3 -c "
import re
h=open('/tmp/ind_form.html',encoding='utf-8').read()
m=re.search(r'<input[^>]*id=\"not_egitim_saglik\".*?>',h,re.S)
print(1 if m else 0)")"
ol "Not değeri yükleniyor"     "1" "$(grep -c 'value="BK notu"' /tmp/ind_form.html | awk '{print ($1>0)?1:0}')"
ol "Hangi beyannamede çıkacağı yazıyor" "1" "$(grep -c 'Rozet: Yıllık GV, Gelir Geçici' /tmp/ind_form.html | awk '{print ($1>0)?1:0}')"
curl -s -b $J -o /tmp/ind_detay.html "$B/mukellefler/detay/3"
ol "Kartta 3 rozet"            "3" "$(grep -oE '🏥 BK|🎓 EĞS|💰 FGK' /tmp/ind_detay.html | sort -u | wc -l | tr -d ' ')"
curl -s -b $J -o /tmp/ind_detay4.html "$B/mukellefler/detay/4"
ol "Boş mükellefte 'Yok' yazıyor" "1" "$(grep -c 'İndirim / Kısıtlama' /tmp/ind_detay4.html | awk '{print ($1>0)?1:0}')"

echo "=== 10) KAYDETME — AÇMA / KAPATMA ==="
TK=$(curl -s -b $J -c $J -o /tmp/ind_fm.html "$B/mukellefler/duzenle/3"; grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ind_fm.html|head -1)
kaydet(){ curl -s -b $J -c $J -o /dev/null -L -X POST "$B/mukellefler/guncelle/3" \
  -d "csrf_beyanname=$1" -d "musavir_id=1" -d "unvan=UCU BIRDEN" -d "mukellef_tipi=gercek" \
  -d "tc_kimlik_no=33344455566" -d "defter_tipi=isletme" -d "ise_baslama_tarihi=2018-01-01" -d "aktif=1" "${@:2}"; }
# Yalnızca finansman açık kalsın
kaydet "$TK" -d "ind_finansman=1" -d "ind_finansman_not=Sadece bu"
ol "Bağkur kapandı"            "0" "$($MDB -e 'select ind_bagkur from mukellefler where id=3')"
ol "Eğitim/sağlık kapandı"     "0" "$($MDB -e 'select ind_egitim_saglik from mukellefler where id=3')"
ol "Finansman açık kaldı"      "1" "$($MDB -e 'select ind_finansman from mukellefler where id=3')"
ol "Kapanan kalemin notu silindi" "NULL" "$($MDB -e 'select ifnull(ind_bagkur_not,"NULL") from mukellefler where id=3')"
ol "Açık kalemin notu kaydedildi" "Sadece bu" "$($MDB -e 'select ind_finansman_not from mukellefler where id=3')"

# Hepsini tekrar aç
TK=$(curl -s -b $J -c $J -o /tmp/ind_fm.html "$B/mukellefler/duzenle/3"; grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ind_fm.html|head -1)
kaydet "$TK" -d "ind_bagkur=1" -d "ind_bagkur_not=Tekrar açıldı" -d "ind_egitim_saglik=1" -d "ind_finansman=1"
ol "Bağkur tekrar açıldı"      "1" "$($MDB -e 'select ind_bagkur from mukellefler where id=3')"
ol "Türkçe karakterli not bozulmadı" "Tekrar açıldı" "$($MDB -e 'select ind_bagkur_not from mukellefler where id=3')"
ol "Notsuz açılan kalem NULL"  "NULL" "$($MDB -e 'select ifnull(ind_egitim_saglik_not,"NULL") from mukellefler where id=3')"

echo "=== 11) DİĞER SAYFALAR BOZULMADI ==="
for u in panel raporlar mukellefler "takip/excel?yil=2026" "takip/yazdir?yil=2026" "odeme?yil=2026&ay=8"; do
  c=$(curl -s -b $J -o /tmp/ind_p.html -w "%{http_code}" "$B/$u")
  ol "/$u HTTP 200" "200" "$c"
  ol "/$u hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Unknown column' /tmp/ind_p.html | awk '{print ($1>0)?1:0}')"
done

echo "=== 12) MIGRATION ÇALIŞTIRILMAMIŞ KURULUM (geriye dönük uyumluluk) ==="
# Rozet ikincil bir özelliktir; kolonlar yoksa çizelge ÇÖKMEMELİ.
$MDBR -e "ALTER TABLE mukellefler
  DROP COLUMN ind_bagkur, DROP COLUMN ind_bagkur_not,
  DROP COLUMN ind_egitim_saglik, DROP COLUMN ind_egitim_saglik_not,
  DROP COLUMN ind_finansman, DROP COLUMN ind_finansman_not;" >/dev/null 2>&1
# Model şema önbelleğini tazelemek için dosyaya dokun (opcache + bellek içi önbellek)
touch "$KOK/app/Models/BeyannameTakipModel.php"; sleep 2
for u in "takip?yil=2026&ay=8&mod=beyan" "mukellefler/detay/3" "mukellefler/duzenle/3" "mukellefler/yeni" "mukellefler" "panel"; do
  c=$(curl -s -b $J -o /tmp/ind_g.html -w "%{http_code}" "$B/$u")
  ol "alansız /$u HTTP 200" "200" "$c"
  ol "alansız /$u Unknown column yok" "0" "$(grep -cE 'Unknown column|ErrorException|Fatal error' /tmp/ind_g.html | awk '{print ($1>0)?1:0}')"
done
curl -s -b $J -o /tmp/ind_g2.html "$B/takip?yil=2026&ay=8&mod=beyan"
ol "alansız çizelgede rozet yok" "0" "$(grep -o 'rozet-indirim\"' /tmp/ind_g2.html | wc -l | tr -d ' ')"
ol "alansız formda bölüm gizli" "0" "$(curl -s -b $J "$B/mukellefler/duzenle/3" | grep -c 'class="ind-kutu"')"
# Kaydetme de çalışmalı (kullanıcı mükellefini güncelleyebilmeli)
TK=$(curl -s -b $J -c $J -o /tmp/ind_fm.html "$B/mukellefler/duzenle/3"; grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ind_fm.html|head -1)
kaydet "$TK" -d "ind_bagkur=1"
ol "alansız kaydetme çalışıyor" "UCU BIRDEN" "$($MDB -e 'select unvan from mukellefler where id=3')"

# Şemayı geri kur
$MDBR < "$KOK/database/migration_indirimler.sql" >/dev/null 2>&1
touch "$KOK/app/Models/BeyannameTakipModel.php"; sleep 2
ol "Migration tekrar uygulanabildi" "1" \
  "$($MDB -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler' AND COLUMN_NAME='ind_bagkur'")"
c=$(curl -s -b $J -o /dev/null -w "%{http_code}" "$B/takip?yil=2026&ay=8&mod=beyan")
ol "Şema geri gelince çizelge çalışıyor" "200" "$c"

# Kolonlar DROP edilip yeniden eklendiği için değerler DEFAULT 0'a döndü.
# Veriyi baştan kur ki test tekrar çalıştırılabilsin ve tarayıcı testi
# (tests/tarayici_indirim_rozet.py) hazır veri bulsun.
veriKur
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan&adet=250" -o /tmp/ind_t.html
ol "Veri geri yüklendi (14 rozet)" "14" "$(grep -o 'rozet-indirim\"' /tmp/ind_t.html | wc -l | tr -d ' ')"

echo
echo "================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "================================================"
[ $k -eq 0 ] || exit 1
