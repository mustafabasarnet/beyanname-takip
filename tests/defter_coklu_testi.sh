#!/bin/bash
# =====================================================================
#  DEFTER TİPİ ÇOKLU SEÇİM — REGRESYON TESTİ
#
#  Kapsam:
#   1. Görünüm: <select> yerine onay kutulu panel (data-coklu="defter_tipi")
#   2. Tek değer (eski bağlantı: defter_tipi=bilanco) hâlâ çalışır
#   3. Çoklu değer (defter_tipi=bilanco,isletme) → liste ve sayaçlar DB ile aynı
#   4. Hiç seçim yok / hepsi seçili → "Tümü" (filtre uygulanmaz)
#   5. Geçersiz değer yok sayılır (enjeksiyon/bozuk adres)
#   6. Son seçim çerezden hatırlanır, "Temizle" (parametresiz form) çerezi siler
#   7. Excel (CSV) ve Yazdır aynı süzgeci taşır; Yazdır'da tip adları " / " ile
#   8. Üst bilgi şeridi seçili tiplerin adlarını yazar
#   9. Yetki: müşavir kullanıcısı kapsamı dışına çıkamaz
#
#  Ön koşul: uygulama http://127.0.0.1:8099'da çalışıyor; admin / musavir
#            kullanıcıları (şifre Test1234). Test kendi verisini kurar/temizler.
#  Kullanım:  bash tests/defter_coklu_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/dct_a.txt      # admin
JM=/tmp/dct_m.txt     # müşavir (kapsam: müşavir 2)
g=0; k=0

ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1" 2>/dev/null; }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/dct_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/dct_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Sayfadaki defter tipi kutusunun kapalı düğme metni
ozet(){ python3 - "$1" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('data-coklu="defter_tipi"')
if i<0: print('KUTU-YOK'); raise SystemExit
m=re.search(r'cs-ozet">([^<]*)<', h[i:i+900])
print(m.group(1) if m else 'OZET-YOK')
PY
}
# İşaretli kutu sayısı (defter tipi kutusunda)
isaretli(){ python3 - "$1" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('data-coklu="defter_tipi"')
if i<0: print(-1); raise SystemExit
blok=h[i:i+4000]
print(len(re.findall(r'value="[a-z_]+"\s+checked', blok)))
PY
}
# "Toplam" kartı değeri
kart(){ python3 - "$1" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
m=re.search(r'etiket">Toplam<.*?deger">([\d.]+)<', h, re.S)
print(m.group(1).replace('.','') if m else 'YOK')
PY
}

echo "=== HAZIRLIK ==="
giris admin $J
giris musavir $JM
db "UPDATE kullanici_musavirleri SET musavir_id=2 WHERE kullanici_id=(SELECT id FROM kullanicilar WHERE kullanici_adi='musavir');"
db "UPDATE kullanicilar SET musavir_id=2 WHERE kullanici_adi='musavir';"

# --- fikstür: 4 mükellef, farklı defter tipleri, aynı ay/yıl beyannameleri ---
db "SET FOREIGN_KEY_CHECKS=0;
    DELETE FROM beyanname_takip WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE kod LIKE 'DCT-%');
    DELETE FROM mukellefler WHERE kod LIKE 'DCT-%';
    SET FOREIGN_KEY_CHECKS=1;"
db "INSERT INTO mukellefler (musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,'DCT-1','DCT BILANCO A','tuzel','9100000001','bilanco','2019-01-01',1),
 (1,'DCT-2','DCT ISLETME B','gercek',NULL,'isletme','2019-01-01',1),
 (1,'DCT-3','DCT BILANCO C','tuzel','9100000003','bilanco','2019-01-01',1),
 (2,'DCT-4','DCT ISLETME D','gercek',NULL,'isletme','2019-01-01',1);"
db "UPDATE mukellefler SET tc_kimlik_no='91000000020' WHERE kod='DCT-2';
    UPDATE mukellefler SET tc_kimlik_no='91000000040' WHERE kod='DCT-4';"
TUR=$(db "SELECT id FROM beyanname_turleri ORDER BY id LIMIT 1")
db "INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,created_at,updated_at)
 SELECT id,$TUR,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','BEKLIYOR',NOW(),NOW()
 FROM mukellefler WHERE kod LIKE 'DCT-%';"
BEK_TUM=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id
  WHERE bt.yil=2026 AND bt.donem_no=7 AND m.kod LIKE 'DCT-%';")
BEK_BIL=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id
  WHERE bt.yil=2026 AND bt.donem_no=7 AND m.kod LIKE 'DCT-%' AND m.defter_tipi='bilanco';")
BEK_ISL=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id
  WHERE bt.yil=2026 AND bt.donem_no=7 AND m.kod LIKE 'DCT-%' AND m.defter_tipi='isletme';")
echo "  fikstür: tümü=$BEK_TUM bilanço=$BEK_BIL işletme=$BEK_ISL (tür=$TUR)"

# ---------------------------------------------------------------------
echo; echo "=== 1) GÖRÜNÜM ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan" -o /tmp/dct1.html
ol "defter tipi çoklu seçim kutusu var" "1" "$(grep -c 'data-coklu="defter_tipi"' /tmp/dct1.html | awk '{print ($1>0)?1:0}')"
ol "eski <select name=defter_tipi> kaldırıldı" "0" "$(grep -c '<select name="defter_tipi"' /tmp/dct1.html)"
ol "panelde 5 defter tipi onay kutusu" "5" "$(python3 - <<'PY'
import re
h=open('/tmp/dct1.html',encoding='utf-8').read()
i=h.find('data-coklu="defter_tipi"'); blok=h[i:i+4000]
print(len(re.findall(r'name="defter_tipi\[\]"', blok)))
PY
)"
ol "Ara / Tümünü Seç / Temizle / Uygula düğmeleri" "4" "$(python3 - <<'PY'
import re
h=open('/tmp/dct1.html',encoding='utf-8').read()
i=h.find('data-coklu="defter_tipi"'); blok=h[i:i+4000]
print(sum([blok.count('cs-ara'), blok.count('cokluTumu(this, true)'),
           blok.count('cokluTumu(this, false)'), blok.count('cokluUygula(this)')]))
PY
)"
ol "kapalı hâl: Tümü" "Tümü" "$(ozet /tmp/dct1.html)"

# ---------------------------------------------------------------------
echo; echo "=== 2) TEK DEĞER (ESKİ BAĞLANTI) ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan&defter_tipi=bilanco" -o /tmp/dct2.html
ol "tek değer: Toplam = DB bilanço ($BEK_BIL)" "$BEK_BIL" "$(kart /tmp/dct2.html)"
ol "tek değer: özet tipin adı" "Bilanço (Yevmiye)" "$(ozet /tmp/dct2.html)"
ol "tek değer: kutu işaretli (1)" "1" "$(isaretli /tmp/dct2.html)"

# ---------------------------------------------------------------------
echo; echo "=== 3) ÇOKLU DEĞER ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan&defter_tipi=bilanco,isletme" -o /tmp/dct3.html
BEK_IKI=$((BEK_BIL + BEK_ISL))
ol "çoklu: Toplam = DB bilanço+işletme ($BEK_IKI)" "$BEK_IKI" "$(kart /tmp/dct3.html)"
ol "çoklu: özet '2 defter tipi seçili'" "2 defter tipi seçili" "$(ozet /tmp/dct3.html)"
ol "çoklu: iki kutu işaretli" "2" "$(isaretli /tmp/dct3.html)"
ol "çoklu: bilgi şeridinde iki tip adı" "1" \
   "$(grep -c 'Bilanço (Yevmiye) / İşletme Defteri' /tmp/dct3.html | awk '{print ($1>0)?1:0}')"
ol "çoklu: satır sayısı listede = $BEK_IKI" "1" \
   "$(python3 - "$BEK_IKI" <<'PY'
import re,sys
h=open('/tmp/dct3.html',encoding='utf-8').read()
n=len(re.findall(r'DCT (BILANCO|ISLETME)', h))
print(1 if n>=int(sys.argv[1]) else 0)
PY
)"

# ---------------------------------------------------------------------
echo; echo "=== 4) TÜMÜ / HEPSİ SEÇİLİ ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan&defter_tipi=isletme,bilanco,serbest_meslek,basit_usul,diger" -o /tmp/dct4.html
ol "hepsi seçili: özet Tümü" "Tümü" "$(ozet /tmp/dct4.html)"
ol "hepsi seçili: filtre uygulanmadı (bilgi şeridi yok)" "0" \
   "$(grep -c 'Yalnızca' /tmp/dct4.html | awk '{print ($1>0)?1:0}')"
ol "hepsi seçili: kutu 'dolu' görünmüyor" "0" "$(python3 - <<'PY'
h=open('/tmp/dct4.html',encoding='utf-8').read()
i=h.find('data-coklu="defter_tipi"'); print(1 if 'coklu-dugme dolu' in h[i:i+900] else 0)
PY
)"

# ---------------------------------------------------------------------
echo; echo "=== 5) GEÇERSİZ / BOZUK DEĞER ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan&defter_tipi=../../etc/passwd" -o /tmp/dct5.html
ol "geçersiz değer yok sayıldı (özet Tümü)" "Tümü" "$(ozet /tmp/dct5.html)"
ol "geçersiz değer: sayfa 200 ve çizelge açıldı" "1" \
   "$(grep -c 'Toplam' /tmp/dct5.html | awk '{print ($1>0)?1:0}')"

# ---------------------------------------------------------------------
echo; echo "=== 6) ÇEREZ: HATIRLA / TEMİZLE ==="
JAR=/tmp/dct_c.txt; rm -f $JAR
giris admin $JAR
curl -s -b $JAR -c $JAR "$B/takip?yil=2026&ay=8&mod=beyan&defter_tipi=bilanco,isletme" -o /dev/null
# Çerez değeri tarayıcıda yüzde-kodlanır (bilanco%2Cisletme); PHP tarafı çözer.
ol "seçim çereze yazıldı" "bilanco,isletme" \
   "$(grep 'bt_f_defter_tipi' $JAR | awk '{print $7}' | python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')"
# Hatırlama yalnız HİÇ filtre parametresi olmayan açılışta devreye girer
# (yan menüden "Beyanname Takip"e tıklama). Filtre çubuğu gönderildiğinde
# ekrandaki seçim geçerlidir — bkz. BaseController::cokluHatirla().
curl -s -b $JAR -c $JAR "$B/takip" -o /tmp/dct6.html
ol "menüden açılışta seçim hatırlandı" "2 defter tipi seçili" "$(ozet /tmp/dct6.html)"
curl -s -b $JAR -c $JAR "$B/takip?yil=2026&ay=8&mod=beyan&defter_tipi=" -o /tmp/dct6b.html
ol "Temizle (boş parametre): özet Tümü" "Tümü" "$(ozet /tmp/dct6b.html)"
curl -s -b $JAR -c $JAR "$B/takip" -o /tmp/dct6c.html
ol "Temizle sonrası hatırlama bırakıldı" "Tümü" "$(ozet /tmp/dct6c.html)"

# ---------------------------------------------------------------------
echo; echo "=== 7) EXCEL VE YAZDIR AYNI SÜZGEÇ ==="
curl -s -b $J "$B/takip/excel?yil=2026&ay=8&mod=beyan&defter_tipi=bilanco,isletme" -o /tmp/dct7.csv
ol "CSV: bilanço+işletme satırları" "1" \
   "$(python3 - "$BEK_IKI" <<'PY'
import sys
n=sum(1 for l in open('/tmp/dct7.csv',encoding='utf-8-sig') if 'DCT ' in l and ('Bilanço' in l or 'İşletme' in l))
print(1 if n>=int(sys.argv[1]) else 0)
PY
)"
ol "CSV: süzgeç dışı tip yok" "0" \
   "$(grep -c 'Serbest Meslek' /tmp/dct7.csv | head -1)"
curl -s -b $J "$B/takip/yazdir?yil=2026&ay=8&mod=beyan&defter_tipi=bilanco,isletme" -o /tmp/dct7.html
ol "Yazdır: süzgeç metni iki tipi içeriyor" "1" \
   "$(grep -c 'Bilanço (Yevmiye) / İşletme Defteri' /tmp/dct7.html | awk '{print ($1>0)?1:0}')"

# ---------------------------------------------------------------------
echo; echo "=== 8) YETKİ: MÜŞAVİR KAPSAMI ==="
curl -s -b $JM "$B/takip?yil=2026&ay=8&mod=beyan&defter_tipi=bilanco,isletme" -o /tmp/dct8.html
ol "müşavir: yalnız kendi mükellefi (DCT ISLETME D) görünür" "1" \
   "$(grep -c 'DCT ISLETME D' /tmp/dct8.html | awk '{print ($1>0)?1:0}')"
ol "müşavir: kapsam dışı DCT BILANCO A görünmez" "0" \
   "$(grep -c 'DCT BILANCO A' /tmp/dct8.html | awk '{print ($1>0)?1:0}')"
ol "müşavir: Toplam = DB (kendi kapsamı) " \
   "$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id
          WHERE bt.yil=2026 AND bt.donem_no=7 AND m.kod='DCT-4';")" "$(kart /tmp/dct8.html)"

# ---------------------------------------------------------------------
echo; echo "=== TEMİZLİK ==="
db "SET FOREIGN_KEY_CHECKS=0;
    DELETE FROM beyanname_takip WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE kod LIKE 'DCT-%');
    DELETE FROM mukellefler WHERE kod LIKE 'DCT-%';
    SET FOREIGN_KEY_CHECKS=1;"
rm -f /tmp/dct*.html /tmp/dct7.csv /tmp/dct_c.txt

echo
echo "======================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "======================================================"
[ "$k" -eq 0 ] || exit 1
