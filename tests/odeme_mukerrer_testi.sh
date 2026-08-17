#!/bin/bash
# =====================================================================
#  ÖDEME LİSTESİ — MÜKERRER TOPLAM KUSURU
#
#  Doğrulanan kusur:
#    Özel ödeme kalemleri (Bağkur, MTV…) modelde grubun 'tahakkuk' ve
#    'genel' alanlarına ekleniyordu. Görünüm ise "Mükellef Genel Toplamı"nı
#    hesaplarken özel kalemleri BİR KEZ DAHA topluyordu. Sonuç:
#      - Bağkur, BEYANNAME ara toplamına karışıyordu
#      - Mükellef genel toplamında İKİ KEZ sayılıyordu
#
#  Örnek (kullanıcının bildirdiği gerçek senaryo):
#    Beyanname 40.735,35 + damga 1.085,20 = 41.820,55
#    Bağkur                                = 10.156,00
#    DOĞRU genel toplam                    = 51.976,55
#    HATALI görünen                        = 62.132,55  (Bağkur 2 kez)
#
#  Çözüm: model artık ayrı tutuyor —
#    'genel'     → yalnızca beyannameler
#    'ozel'      → beyanname dışı kalemler
#    'genel_tum' → ikisinin toplamı (tek doğru kaynak)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 kullanıcısı var.
#  Kullanım:  bash tests/odeme_mukerrer_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/mk_a.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/mk_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mk_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Sayfadan tutar okur.
#   oku <dosya> <desen>              → ilk eşleşme (sayfa geneli için)
#   okuM <dosya> <mükellef> <desen>  → O MÜKELLEFİN bloğundan
# Not: gruplar alfabetik sıralıdır; ilk eşleşme başka mükellefe ait olabilir.
oku(){ python3 - "$1" "$2" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
m=re.findall(sys.argv[2],h,re.S)
print(m[0] if m else 'YOK')
PY
}
okuM(){ python3 - "$1" "$2" "$3" <<'PYM'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find(sys.argv[2])
if i < 0:
    print('MUKELLEF-YOK'); raise SystemExit
# Bir sonraki grubun başlangıcına kadar olan bölüm
j=h.find('class="od-grup', i)
blok=h[i:j if j>i else len(h)]
m=re.findall(sys.argv[3],blok,re.S)
print(m[0] if m else 'YOK')
PYM
}

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri; TRUNCATE ozel_odemeler;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (1,'SMMM','Ali','Y',1),(2,'SMMM','Veli','D',1);
-- 1: beyanname + özel kalem (asıl senaryo)
-- 2: yalnızca beyanname
-- 3: yalnızca özel kalem (beyannamesi yok)
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,tc_kimlik_no,vergi_kimlik_no,defter_tipi,vergi_dairesi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','SEYİT ALİ BULUT','gercek','19002556955',NULL,'isletme','Mimarsinan VD','2019-01-01',1),
 (2,1,'M002','SADECE BEYANNAME LTD','tuzel',NULL,'1112223334','bilanco','Merkez VD','2019-01-01',1),
 (3,1,'M003','SADECE BAĞKUR','gercek','22233344455',NULL,'isletme','Merkez VD','2019-01-01',1);
INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,tahakkuk_tutari,damga_tutari,odendi,created_at,updated_at) VALUES
 (1,9,2026,2,'2. Dönem (Nis-May-Haz) 2026','2026-04-01','2026-06-30','2026-08-17','2026-08-17','ONAYLANDI',40735.35,1085.20,0,NOW(),NOW()),
 (2,1,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','ONAYLANDI',5000.00,0,0,NOW(),NOW());
INSERT INTO ozel_odemeler (mukellef_id,kaydeden_id,baslik,tutar,son_tarih,donem_etiketi,tekrar,tekrar_bitis,odendi,created_at,updated_at) VALUES
 (1,1,'Bağkur Primi',10156.00,'2026-08-31','Ağustos 2026','AYLIK','2026-12-31',0,NOW(),NOW()),
 (3,1,'Bağkur Primi',7500.00,'2026-08-31','Ağustos 2026','YOK',NULL,0,NOW(),NOW());"
}

veriKur
giris admin $J
curl -s -b $J "$B/odeme?yil=2026&ay=8" -o /tmp/mk_l.html

echo "=== 1) ASIL SENARYO: BEYANNAME + BAĞKUR ==="
ol "Sayfa açılıyor" "0" "$(grep -cE 'ErrorException|Fatal error' /tmp/mk_l.html | awk '{print ($1>0)?1:0}')"
ol "Beyanname ara toplamı YALNIZCA beyanname" "41.820,55" \
   "$(okuM /tmp/mk_l.html 'SEYİT ALİ BULUT' 'BEYANNAME ARA TOPLAMI.*?yesil\)">([\d.,]+)')"
ol "Diğer ödemeler ara toplamı" "10.156,00" \
   "$(okuM /tmp/mk_l.html 'SEYİT ALİ BULUT' 'DİĞER ÖDEMELER ARA TOPLAMI.*?mor\)">([\d.,]+)')"
ol "Mükellef genel toplamı (mükerrer değil)" "51.976,55" \
   "$(okuM /tmp/mk_l.html 'SEYİT ALİ BULUT' 'MÜKELLEF GENEL TOPLAMI.*?yesil\)">([\d.,]+)')"
ol "Başlık satırı toplamı" "51.976,55 ₺" \
   "$(okuM /tmp/mk_l.html 'SEYİT ALİ BULUT' 'class="od-tutar">([^<]+)')"
# Kusurlu sürümün ürettiği değer HİÇBİR YERDE olmamalı
ol "Hatalı 62.132,55 sayfada YOK" "0" "$(grep -c '62.132,55' /tmp/mk_l.html)"
ol "Bağkur beyanname toplamına karışmadı" "0" "$(grep -c '51.976,55.*BEYANNAME ARA' /tmp/mk_l.html)"

echo "=== 2) ÖZET KARTLARI ==="
# Tahakkuk kartı yalnızca beyanname tahakkuku (40.735,35 + 5.000,00)
ol "Tahakkuk kartı özel kalem içermiyor" "45.735,35" \
   "$(oku /tmp/mk_l.html 'Tahakkuk \(Damga Hariç\).*?deger"[^>]*>([\d.,]+)')"
DB_TAH=$($MDB -e "SELECT FORMAT(SUM(tahakkuk_tutari),2,'de_DE') FROM beyanname_takip
         WHERE durum='ONAYLANDI' AND MONTH(COALESCE(odeme_son_tarih,son_tarih))=8")
ol "Tahakkuk kartı = DB ($DB_TAH)" "$DB_TAH" \
   "$(oku /tmp/mk_l.html 'Tahakkuk \(Damga Hariç\).*?deger"[^>]*>([\d.,]+)')"
# Genel toplam = beyanname (46.820,55) + özel (17.656,00) = 64.476,55
ol "Sayfa genel toplamı" "64.476,55" \
   "$(oku /tmp/mk_l.html 'font-size:26px[^>]*>\s*([\d.,]+)')"

echo "=== 3) YALNIZCA BEYANNAMESİ OLAN MÜKELLEF ==="
ol "Beyanname-only doğru (5.000,00)" "1" \
   "$(grep -c '5.000,00' /tmp/mk_l.html | awk '{print ($1>0)?1:0}')"

echo "=== 4) YALNIZCA ÖZEL KALEMİ OLAN MÜKELLEF ==="
ol "Bağkur-only mükellef listede" "1" \
   "$(grep -c 'SADECE BAĞKUR' /tmp/mk_l.html | awk '{print ($1>0)?1:0}')"
ol "Bağkur-only toplamı 7.500,00" "1" \
   "$(python3 -c "
import re
h=open('/tmp/mk_l.html',encoding='utf-8').read()
i=h.find('SADECE BAĞKUR')
blok=h[i:i+700]
print(1 if '7.500,00' in blok else 0)")"
ol "Bağkur-only iki kez sayılmadı (15.000 yok)" "0" "$(grep -c '15.000,00' /tmp/mk_l.html)"

echo "=== 5) TOPLAMLARIN İÇ TUTARLILIĞI ==="
# Beyanname ara + Diğer ara = Mükellef geneli
python3 - <<'PY' > /tmp/mk_hesap.txt
import re
h=open('/tmp/mk_l.html',encoding='utf-8').read()
def f(x): return float(x.replace('.','').replace(',','.'))

# Her grup bloğunu ayrı ayrı incele: beyanname + özel = mükellef geneli
bloklar=re.split(r'(?=<div class="od-grup)',h)
tamam=True
genelller=[]
for bl in bloklar:
    m=re.search(r'MÜKELLEF GENEL TOPLAMI.*?yesil\)">([\d.,]+)',bl,re.S)
    if not m: continue
    b=re.search(r'BEYANNAME ARA TOPLAMI.*?yesil\)">([\d.,]+)',bl,re.S)
    o=re.search(r'DİĞER ÖDEMELER ARA TOPLAMI.*?mor\)">([\d.,]+)',bl,re.S)
    bt=f(b.group(1)) if b else 0.0
    ot=f(o.group(1)) if o else 0.0
    if abs(bt+ot-f(m.group(1)))>0.01: tamam=False
    genelller.append(f(m.group(1)))
print('1' if tamam else '0')

gen=re.search(r'font-size:26px[^>]*>\s*([\d.,]+)',h,re.S)
print('1' if abs(sum(genelller)-f(gen.group(1)))<0.01 else '0')
PY
ol "Beyanname + Diğer = Mükellef geneli" "1" "$(sed -n 1p /tmp/mk_hesap.txt)"
ol "Mükellef gennelleri = Sayfa geneli"  "1" "$(sed -n 2p /tmp/mk_hesap.txt)"

echo "=== 6) EXCEL ÇIKTISI ==="
curl -s -b $J "$B/odeme/excel?yil=2026&ay=8" -o /tmp/mk_e.csv
CSV=$(iconv -f WINDOWS-1254 -t UTF-8 /tmp/mk_e.csv 2>/dev/null || cat /tmp/mk_e.csv)
ol "Excel'de doğru mükellef toplamı" "1" "$(echo "$CSV" | grep -c '51.976,55' | awk '{print ($1>0)?1:0}')"
ol "Excel'de hatalı tutar yok" "0" "$(echo "$CSV" | grep -c '62.132,55')"

echo "=== 7) YAZDIRMA ÇIKTISI ==="
curl -s -b $J "$B/odeme/yazdir?yil=2026&ay=8" -o /tmp/mk_y.html
ol "Yazdırmada doğru toplam" "1" "$(grep -c '51.976,55' /tmp/mk_y.html | awk '{print ($1>0)?1:0}')"
ol "Yazdırmada hatalı tutar yok" "0" "$(grep -c '62.132,55' /tmp/mk_y.html)"

echo "=== 8) ÖDEME BİLDİRİMİ ==="
curl -s -b $J "$B/odeme/bildirim/1?yil=2026&ay=8" -o /tmp/mk_b.html
ol "Bildirim açılıyor" "200" "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/odeme/bildirim/1?yil=2026&ay=8")"
ol "Bildirimde doğru toplam" "1" "$(grep -c '51.976,55' /tmp/mk_b.html | awk '{print ($1>0)?1:0}')"
ol "Bildirimde hatalı tutar yok" "0" "$(grep -c '62.132,55' /tmp/mk_b.html)"
ol "Bildirimde Bağkur ayrı satır" "1" "$(grep -c '10.156,00' /tmp/mk_b.html | awk '{print ($1>0)?1:0}')"

echo "=== 9) AJAX SONSUZ KAYDIRMA PARÇASI ==="
curl -s -b $J -H "X-Requested-With: XMLHttpRequest" \
  "$B/odeme/daha-fazla?yil=2026&ay=8&adet=1&ofset=0" -o /tmp/mk_df.json
ol "AJAX parçasında da doğru" "1" \
  "$(python3 -c "
import json,sys
raw=open('/tmp/mk_df.json',encoding='utf-8').read()
d=json.loads(raw[raw.find('{'):raw.rfind('}')+1])
print(1 if '62.132,55' not in d['html'] else 0)")"

echo "=== 10) KAYITLI ÖDEME LİSTELERİ ETKİLENMEDİ ==="
# Bu yol ozel_atla=true kullanır, kendi toplamını satırlardan hesaplar
c=$(curl -s -b $J -o /tmp/mk_kl.html -w "%{http_code}" "$B/odeme/listeler")
ol "Listelerim sayfası açılıyor" "200" "$c"
ol "Listelerim hatasız" "0" "$(grep -cE 'ErrorException|Fatal error' /tmp/mk_kl.html | awk '{print ($1>0)?1:0}')"

echo "=== 11) ÖDENDİ İŞARETLEME SONRASI ==="
BID=$($MDB -e "SELECT id FROM beyanname_takip WHERE mukellef_id=1 LIMIT 1")
CT=$(curl -s -b $J -c $J "$B/odeme?yil=2026&ay=8" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1)
curl -s -b $J -c $J -H "X-Requested-With: XMLHttpRequest" \
  -d "csrf_beyanname=$CT" -d "id=$BID" -d "odendi=1" "$B/odeme/odendi" -o /dev/null
curl -s -b $J "$B/odeme?yil=2026&ay=8" -o /tmp/mk_l2.html
ol "Ödendi sonrası toplam değişmedi" "51.976,55" \
   "$(okuM /tmp/mk_l2.html 'SEYİT ALİ BULUT' 'MÜKELLEF GENEL TOPLAMI.*?yesil\)">([\d.,]+)')"
ol "Ödendi sonrası mükerrer yok" "0" "$(grep -c '62.132,55' /tmp/mk_l2.html)"

echo
echo "================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "================================================"
[ $k -eq 0 ] || exit 1
