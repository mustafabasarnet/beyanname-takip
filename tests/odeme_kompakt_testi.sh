#!/bin/bash
# =====================================================================
#  ÖDEME LİSTESİ — KOMPAKT / KATLANABİLİR TASARIM
#
#  Sorun: her mükellef ayrı kart + tablo olarak basılıyordu; beyannameler
#  onaylandıkça sayfa metrelerce uzuyordu.
#
#  Çözüm:
#   1. Her mükellef TEK SATIR (ünvan + kalem sayısı + toplam + durum rozeti)
#   2. Detay tıklanınca açılır; varsayılan KAPALI
#   3. Mükellef grubu bazında sayfalama + sonsuz kaydırma
#   4. Genel toplam HER ZAMAN tüm listeden hesaplanır (sayfayla değişmez)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 kullanıcısı var.
#  Not: Test kendi verisini kurar.
#  Kullanım:  bash tests/odeme_kompakt_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/ok_a.txt
JM=/tmp/ok_m.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/ok_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ok_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Sayımlar — CSS tanımları değil, GERÇEK öğeler sayılır
grupSay(){ grep -o 'class="od-grup' "$1" | wc -l | tr -d ' '; }
kapaliSay(){ grep -o 'aria-expanded="false"' "$1" | wc -l | tr -d ' '; }
acikSay(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
# hidden özniteliği OLMAYAN od-govde = açık
print(len([m for m in re.findall(r'<div class=\"od-govde\"[^>]*>', h) if 'hidden' not in m]))
" "$1"; }
# JSON gövdesini ayıkla (geliştirme kipinde debugbar eklenebiliyor)
js(){ python3 -c "
import json,sys
raw=open(sys.argv[1],encoding='utf-8').read().strip()
son=raw.rfind('}')
d=json.loads(raw[raw.find('{'):son+1])
print(eval('d'+sys.argv[2]))
" "$1" "$2"; }

# <button id="od-daha"> etiketi çok satırlı basılır; tek satır grep bulamaz.
dahaGizli(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
m=re.search(r'<button[^>]*id=\"od-daha\".*?>', h, re.S)
print(1 if m and 'display:none' in m.group(0) else 0)
" "$1"; }

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri; TRUNCATE ozel_odemeler;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (1,'SMMM','Ali','Y',1),(2,'SMMM','Veli','D',1);"

python3 - <<'PY' > /tmp/ok_veri.sql
import random
random.seed(7)
harf="ABCÇDEFGĞHIİJKLMNOÖPRSŞTUÜVYZ"
m=[]
for i in range(1,41):
    h=harf[(i-1)%len(harf)]
    tip='tuzel' if i%2==0 else 'gercek'
    vkn=f"'{1000000000+i}'" if tip=='tuzel' else 'NULL'
    tck='NULL' if tip=='tuzel' else f"'{10000000000+i}'"
    m.append(f"({i},{1 if i%4 else 2},'K{i:03}','{h}FİRMA {i:02} LTD. ŞTİ.','{tip}',{vkn},{tck},"
             f"'{'bilanco' if tip=='tuzel' else 'isletme'}','Merkez VD','2019-01-01',1)")
print("INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,vergi_dairesi,ise_baslama_tarihi,aktif) VALUES")
print(',\n'.join(m)+';')
b=[]
for muk in range(1,41):
    for tur in random.sample([1,3,4,11],random.randint(3,4)):
        tut=round(random.uniform(500,25000),2)
        odendi = 1 if muk%5==0 else 0     # her 5. mükellefin TÜM kalemleri ödenmiş
        b.append(f"({muk},{tur},2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28',"
                 f"'ONAYLANDI',{tut},{odendi},NOW(),NOW())")
print("INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,tahakkuk_tutari,odendi,created_at,updated_at) VALUES")
print(',\n'.join(b)+';')
o=[]
for muk in [1,2,3,7]:
    o.append(f"({muk},1,'Bağkur Primi',4250.00,'2026-08-31','Temmuz 2026','AYLIK',NULL,0,NOW(),NOW())")
print("INSERT INTO ozel_odemeler (mukellef_id,kaydeden_id,baslik,tutar,son_tarih,donem_etiketi,tekrar,tekrar_bitis,odendi,created_at,updated_at) VALUES")
print(',\n'.join(o)+';')
PY
$MDBR < /tmp/ok_veri.sql
}

veriKur
giris admin $J
curl -s -b $J "$B/odeme?yil=2026&ay=8&adet=25" -o /tmp/ok_l.html

echo "=== 1) SAYFA AÇILIYOR ==="
ol "HTTP 200" "200" "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/odeme?yil=2026&ay=8")"
ol "Hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Undefined variable' /tmp/ok_l.html | awk '{print ($1>0)?1:0}')"
ol "Kompakt liste kabı var" "1" "$(grep -c 'id="od-liste"' /tmp/ok_l.html | awk '{print ($1>0)?1:0}')"

echo "=== 2) KATLANABİLİR YAPI — VARSAYILAN KAPALI ==="
ol "25 mükellef grubu basıldı" "25" "$(grupSay /tmp/ok_l.html)"
ol "Hepsi kapalı (aria-expanded=false)" "25" "$(kapaliSay /tmp/ok_l.html)"
ol "Hiçbir detay açık değil" "0" "$(acikSay /tmp/ok_l.html)"
# Not: gömülü <style> içinde .od-bas[aria-expanded="true"] seçicileri de geçer.
# Yalnızca GERÇEK <button ... aria-expanded="true"> öğeleri sayılır.
ol "Açık kalan yok (aria-expanded=true)" "0" \
  "$(python3 -c "
import re
h=open('/tmp/ok_l.html',encoding='utf-8').read()
print(len(re.findall(r'<button[^>]*aria-expanded=\"true\"', h, re.S)))")"
ol "Tümünü Aç düğmesi var" "1" "$(grep -c 'id="od-tumunu"' /tmp/ok_l.html | awk '{print ($1>0)?1:0}')"

echo "=== 3) BAŞLIK SATIRI ÖZET BİLGİ TAŞIYOR ==="
ol "Kalem sayısı gösteriliyor" "1" "$(grep -c 'od-adet' /tmp/ok_l.html | awk '{print ($1>0)?1:0}')"
ol "Grup toplamı gösteriliyor" "25" "$(grep -o 'class="od-tutar"' /tmp/ok_l.html | wc -l | tr -d ' ')"
# 5,10,15... numaralı mükelleflerin tüm kalemleri ödenmiş → "tamam" rozeti
TAMAM=$($MDB -e "SELECT COUNT(*) FROM (
  SELECT bt.mukellef_id FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id
  WHERE bt.durum='ONAYLANDI' AND MONTH(COALESCE(bt.odeme_son_tarih,bt.son_tarih))=8
  GROUP BY bt.mukellef_id HAVING SUM(bt.odendi=0)=0
    AND (SELECT COUNT(*) FROM ozel_odemeler o WHERE o.mukellef_id=bt.mukellef_id AND o.odendi=0)=0
) t")
ol "Tamamlanan mükelleflerde ✓ rozeti" "1" \
  "$([ "$(grep -o 'od-rozet tamam' /tmp/ok_l.html | wc -l | tr -d ' ')" -gt 0 ] && echo 1 || echo 0)"
ol "Özel kalemli mükellefte +N rozeti" "1" \
  "$([ "$(grep -o 'od-rozet ozel' /tmp/ok_l.html | wc -l | tr -d ' ')" -gt 0 ] && echo 1 || echo 0)"

echo "=== 4) DETAY İÇERİĞİ KORUNDU ==="
ol "Beyanname tablosu var" "1" "$(grep -c 'BEYANNAME ARA TOPLAMI' /tmp/ok_l.html | awk '{print ($1>0)?1:0}')"
ol "Özel ödeme bölümü var" "1" "$(grep -c 'DİĞER ÖDEMELER ARA TOPLAMI' /tmp/ok_l.html | awk '{print ($1>0)?1:0}')"
ol "Mükellef genel toplamı var" "1" "$(grep -c 'MÜKELLEF GENEL TOPLAMI' /tmp/ok_l.html | awk '{print ($1>0)?1:0}')"
ol "Bildirim düğmesi var" "25" "$(grep -o 'odeme/bildirim/' /tmp/ok_l.html | wc -l | tr -d ' ')"
ol "Bildirim bağlantısı filtre taşıyor" "1" \
  "$(grep -oE 'odeme/bildirim/[0-9]+\?[^\"]*' /tmp/ok_l.html | head -1 | grep -c 'yil=2026')"
ol "Ödendi kutuları var" "1" "$(grep -c 'odendi-kutu' /tmp/ok_l.html | awk '{print ($1>0)?1:0}')"
ol "Tekrar durdurma bağlantısı korundu" "1" \
  "$(grep -c 'odeme/tekrar-durdur' /tmp/ok_l.html | awk '{print ($1>0)?1:0}')"

echo "=== 5) SAYFALAMA ==="
TUM=$($MDB -e "SELECT COUNT(DISTINCT mukellef_id) FROM (
  SELECT mukellef_id FROM beyanname_takip WHERE durum='ONAYLANDI' AND MONTH(COALESCE(odeme_son_tarih,son_tarih))=8
  UNION SELECT mukellef_id FROM ozel_odemeler WHERE MONTH(son_tarih)=8) t")
ol "Toplam mükellef sayısı ($TUM)" "$TUM" \
  "$(python3 -c "
import re
h=open('/tmp/ok_l.html',encoding='utf-8').read()
m=re.search(r'id=\"od-gosterilen\">(\d+)</b>\s*/\s*<b>([\d.]+)',h)
print(m.group(2).replace('.','') if m else 'YOK')")"
ol "Sayfada 25 gösteriliyor" "25" \
  "$(python3 -c "
import re
h=open('/tmp/ok_l.html',encoding='utf-8').read()
m=re.search(r'id=\"od-gosterilen\">(\d+)</b>',h)
print(m.group(1) if m else 'YOK')")"
ol "Daha Fazla düğmesi görünür" "0" "$(dahaGizli /tmp/ok_l.html)"
# adet=50 → hepsi tek sayfada
curl -s -b $J "$B/odeme?yil=2026&ay=8&adet=50" -o /tmp/ok_50.html
ol "adet=50 ile $TUM grup basıldı" "$TUM" "$(grupSay /tmp/ok_50.html)"
ol "Hepsi sığınca düğme gizli" "1" "$(dahaGizli /tmp/ok_50.html)"

echo "=== 6) AJAX SONSUZ KAYDIRMA ==="
curl -s -b $J -H "X-Requested-With: XMLHttpRequest" \
  "$B/odeme/daha-fazla?yil=2026&ay=8&adet=25&ofset=25" -o /tmp/ok_df.json
ol "Yanıt başarılı" "True" "$(js /tmp/ok_df.json "['durum']")"
ol "Kalan 15 grup geldi" "15" "$(js /tmp/ok_df.json "['adet']")"
ol "Ofset ilerledi" "40" "$(js /tmp/ok_df.json "['ofset']")"
ol "Toplam doğru" "$TUM" "$(js /tmp/ok_df.json "['toplam']")"
ol "Daha fazlası yok" "False" "$(js /tmp/ok_df.json "['dahaVar']")"
ol "Gelen gruplar da kapalı" "15" \
  "$(js /tmp/ok_df.json "['html'].count('aria-expanded=\\\"false\\\"')")"
ol "AJAX'ta bildirim filtresi korunuyor" "True" \
  "$(js /tmp/ok_df.json "['yil=2026' in d['html']]" 2>/dev/null || js /tmp/ok_df.json "['html'].find('yil=2026')>=0")"
# Mükerrer grup gelmemeli
ol "İlk sayfa + AJAX = toplam" "$TUM" \
  "$(( $(grupSay /tmp/ok_l.html) + $(js /tmp/ok_df.json "['adet']") ))"

echo "=== 7) GENEL TOPLAM SAYFADAN ETKİLENMİYOR ==="
gt(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
m=re.search(r'GENEL TOPLAM.*?font-size:26px[^>]*>\s*([\d.,]+)',h,re.S)
print(m.group(1) if m else 'YOK')
" "$1"; }
ol "adet=25 ve adet=50 aynı toplam" "$(gt /tmp/ok_50.html)" "$(gt /tmp/ok_l.html)"
DB_TOP=$($MDB -e "SELECT FORMAT(ROUND(SUM(tahakkuk_tutari),2),2,'de_DE') FROM beyanname_takip
         WHERE durum='ONAYLANDI' AND MONTH(COALESCE(odeme_son_tarih,son_tarih))=8")
OZ_TOP=$($MDB -e "SELECT IFNULL(SUM(tutar),0) FROM ozel_odemeler WHERE MONTH(son_tarih)=8")
ol "Beyanname adedi tüm listeden" \
  "$($MDB -e "SELECT COUNT(*) FROM beyanname_takip WHERE durum='ONAYLANDI' AND MONTH(COALESCE(odeme_son_tarih,son_tarih))=8")" \
  "$(python3 -c "
import re
h=open('/tmp/ok_l.html',encoding='utf-8').read()
m=re.search(r'(\d+) beyanname',h); print(m.group(1) if m else 'YOK')")"
# Hem ÖZET KARTI hem GENEL TOPLAM tüm listeyi göstermeli (sayfalanan sayıyı değil)
ol "Özet kartı mükellef sayısı ($TUM)" "$TUM" \
  "$(python3 -c "
import re
h=open('/tmp/ok_l.html',encoding='utf-8').read()
print(re.findall(r'([\d.]+) mükellef',h)[0].replace('.',''))")"
ol "Genel toplam mükellef sayısı ($TUM)" "$TUM" \
  "$(python3 -c "
import re
h=open('/tmp/ok_l.html',encoding='utf-8').read()
print(re.findall(r'([\d.]+) mükellef',h)[-1].replace('.',''))")"

echo "=== 8) FİLTRELER ÇALIŞMAYA DEVAM EDİYOR ==="
curl -s -b $J "$B/odeme?yil=2026&ay=8&odendi=0&adet=250" -o /tmp/ok_od0.html
ODENMEMIS=$($MDB -e "SELECT COUNT(DISTINCT mukellef_id) FROM beyanname_takip
            WHERE durum='ONAYLANDI' AND odendi=0 AND MONTH(COALESCE(odeme_son_tarih,son_tarih))=8")
ol "Ödenmedi filtresi grup azaltıyor" "1" \
  "$([ "$(grupSay /tmp/ok_od0.html)" -le "$TUM" ] && [ "$(grupSay /tmp/ok_od0.html)" -gt 0 ] && echo 1 || echo 0)"
curl -s -b $J "$B/odeme?yil=2026&ay=8&q=AF%C4%B0RMA&adet=250" -o /tmp/ok_q.html
ol "Arama filtresi çalışıyor" "1" \
  "$([ "$(grupSay /tmp/ok_q.html)" -lt "$TUM" ] && echo 1 || echo 0)"
curl -s -b $J "$B/odeme?yil=2026&ay=1&adet=250" -o /tmp/ok_bos.html
ol "Boş ayda 'bulunamadı' mesajı" "1" \
  "$(grep -c 'ödenecek beyanname bulunamadı' /tmp/ok_bos.html | awk '{print ($1>0)?1:0}')"
ol "Boş ayda grup yok" "0" "$(grupSay /tmp/ok_bos.html)"

echo "=== 9) YETKİ ==="
giris musavir $JM
curl -s -b $JM "$B/odeme?yil=2026&ay=8&adet=250" -o /tmp/ok_mus.html
MUS=$($MDB -e "SELECT COUNT(DISTINCT bt.mukellef_id) FROM beyanname_takip bt
      JOIN mukellefler m ON m.id=bt.mukellef_id
      WHERE m.musavir_id=2 AND bt.durum='ONAYLANDI' AND MONTH(COALESCE(bt.odeme_son_tarih,bt.son_tarih))=8")
ol "Müşavir yalnızca kendi mükellefleri ($MUS)" "$MUS" "$(grupSay /tmp/ok_mus.html)"
ol "Müşavir toplamı sistemden az" "1" "$([ "$(grupSay /tmp/ok_mus.html)" -lt "$TUM" ] && echo 1 || echo 0)"
ol "Personel ödeme listesine giremiyor" "302" \
  "$(giris personel /tmp/ok_p.txt; curl -s -b /tmp/ok_p.txt -o /dev/null -w '%{http_code}' "$B/odeme")"
ol "Oturumsuz AJAX engelli" "302" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$B/odeme/daha-fazla?yil=2026&ay=8")"

echo "=== 10) DİĞER ÖDEME SAYFALARI BOZULMADI ==="
giris admin $J
for u in "odeme?yil=2026&ay=8" "odeme/excel?yil=2026&ay=8" "odeme/yazdir?yil=2026&ay=8" \
         "odeme/bildirim/1?yil=2026&ay=8" "odeme/listeler" panel; do
  c=$(curl -s -b $J -o /tmp/ok_s.html -w "%{http_code}" "$B/$u")
  ol "/$u HTTP 200" "200" "$c"
  ol "/$u hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Undefined variable' /tmp/ok_s.html | awk '{print ($1>0)?1:0}')"
done

echo
echo "================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "================================================"
[ $k -eq 0 ] || exit 1
