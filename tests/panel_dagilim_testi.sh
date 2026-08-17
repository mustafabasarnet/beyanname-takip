#!/bin/bash
# =====================================================================
#  KONTROL PANELİ — BEYANNAME DURUM KONTROL TABLOSU
#
#  Doğrulanan davranışlar:
#   1. Tablo, seçilen ay/yılda beyanname türü bazında durum dağılımını verir.
#   2. Türler SABİT LİSTEDEN değil, o ay GERÇEKTEN VAR OLAN kayıtlardan
#      üretilir. Bu yüzden geçici vergiler yalnızca verildikleri aylarda
#      (Şubat/Mayıs/Ağustos/Kasım) görünür; Eylül'de listeden düşer.
#   3. Sayılar tıklanabilir; panelden ayrılmadan açılır pencerede mükellef
#      listesi gelir (panel/tur-listesi).
#   4. "Kalan" = Bekliyor + Hazır. "Verilmeyecek" oran hesabına girmez.
#   5. Yetki kapsamı sızmaz.
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin ve musavir kullanıcıları (şifre Test1234) var.
#  Not: Test kendi verisini kurar.
#  Kullanım:  bash tests/panel_dagilim_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/pd.txt
JM=/tmp/pd_m.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/pd_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/pd_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Tablodaki bir türün belirli sütununu okur:  hucre <dosya> <tür> <sütun>
# sütun: toplam|onaylandi|hazir|bekliyor|gecikmis|kalan|oran
hucre(){ python3 - "$1" "$2" "$3" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('bdk-tablo')
if i<0: print('TABLO-YOK'); raise SystemExit
t=h[i:h.find('</table>',i)]
sut={'toplam':0,'onaylandi':1,'hazir':2,'bekliyor':3,'gecikmis':4,'kalan':5}
for tr in re.findall(r'<tr>(.*?)</tr>',t,re.S):
    tur=re.search(r'class="bdk-tur"[^>]*>([^<]+)',tr)
    if not tur or tur.group(1).strip()!=sys.argv[2]: continue
    if sys.argv[3]=='oran':
        m=re.search(r'bdk-oran">%(\d+)',tr); print(m.group(1) if m else '?'); raise SystemExit
    v=[a or b for a,b in re.findall(r'class="bdk-say[^"]*"[^>]*>([\d.]+)</button>|class="bdk-say bos">(0)</span>',tr)]
    print(v[sut[sys.argv[3]]].replace('.','') if len(v)>sut[sys.argv[3]] else 'SUTUN-YOK'); raise SystemExit
print('SATIR-YOK')
PY
}
# Tabloda görünen tüm türler (sırayla)
turler(){ python3 - "$1" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('bdk-tablo')
print(','.join(re.findall(r'class="bdk-tur"[^>]*>([^<]+)',h[i:h.find('</table>',i)])) if i>=0 else 'TABLO-YOK')
PY
}
# TOPLAM satırındaki değer
toplamSat(){ python3 - "$1" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('bdk-tablo'); t=h[i:h.find('</table>',i)]
m=re.search(r'TOPLAM</td>\s*<td>([\d.]+)',t)
print(m.group(1).replace('.','') if m else 'YOK')
PY
}
js(){ python3 -c "import json;d=json.load(open('$1'));print(d$2)"; }

# ---------------------------------------------------------------
#  Test verisi
#   - Aylık türler (KDV1_A, MUHSGK_A, DAMGA) : her ay
#   - Geçici vergiler (GELIR/KURUM_GECICI)   : Mayıs, Ağustos, Kasım
#   - Yıllık GV → Mart, Kurumlar → Nisan
#   - TURIZM: tek kayıt, yalnız Ağustos
# ---------------------------------------------------------------
veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES
 (1,'SMMM','Ali Yılmaz','Yılmaz',1),(2,'SMMM','Veli Demir','Demir',1);"

python3 - <<'PY' > /tmp/pd_veri.sql
import calendar
m=[]
for i in range(1,21):
    tip='tuzel' if i%2==0 else 'gercek'
    vkn=f"'{1000000000+i}'" if tip=='tuzel' else 'NULL'
    tck='NULL' if tip=='tuzel' else f"'{10000000000+i}'"
    m.append(f"({i},{1 if i<=15 else 2},'K{i:03}','FİRMA {i:02} LTD.','{tip}',{vkn},{tck},"
             f"'{'bilanco' if tip=='tuzel' else 'isletme'}','2018-01-01',1)")
print("INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES")
print(',\n'.join(m)+';')

b=[]
def ekle(muk,tur,yil,dno,dad,dbas,dbit,son,durum):
    b.append(f"({muk},{tur},{yil},{dno},'{dad}','{dbas}','{dbit}','{son}','{son}','{durum}',NOW(),NOW())")

for muk in range(1,21):
    for ay in range(1,13):
        da = ay-1 if ay>1 else 12; dy = 2026 if ay>1 else 2025
        son=f"2026-{ay:02d}-28"
        bas=f"{dy}-{da:02d}-01"; bit=f"{dy}-{da:02d}-{calendar.monthrange(dy,da)[1]:02d}"
        d = 'ONAYLANDI' if ay<8 else ('HAZIR' if ay==8 and muk%4==0 else 'BEKLIYOR')
        ekle(muk,1,dy,da,f'Donem {da}',bas,bit,son,d)
        if muk%2==0: ekle(muk,4,dy,da,f'Donem {da}',bas,bit,son,d)
        if muk==1:   ekle(muk,11,dy,da,f'Donem {da}',bas,bit,son,d)

gecici=[(1,'2026-01-01','2026-03-31','2026-05-17'),
        (2,'2026-04-01','2026-06-30','2026-08-17'),
        (3,'2026-07-01','2026-09-30','2026-11-17')]
for muk in range(1,21):
    tur = 10 if muk%2==0 else 9
    for (dno,bas,bit,son) in gecici:
        ay=int(son[5:7])
        d = 'ONAYLANDI' if ay<8 else ('HAZIR' if muk%5==0 else 'BEKLIYOR')
        ekle(muk,tur,2026,dno,f'{dno}. Donem',bas,bit,son,d)

for muk in range(1,21):
    if muk%2: ekle(muk,7,2025,1,'2025 Yili','2025-01-01','2025-12-31','2026-03-31','ONAYLANDI')
    else:     ekle(muk,8,2025,1,'2025 Yili','2025-01-01','2025-12-31','2026-04-30','ONAYLANDI')

ekle(3,13,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','BEKLIYOR')

print("INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,created_at,updated_at) VALUES")
print(',\n'.join(b)+';')
PY
$MDBR < /tmp/pd_veri.sql

# Verilmeyecek örneği: oran hesabının PAYDASINDAN düşmeli.
# Not: (mukellef_id,tur,yil,donem_no) benzersizdir (uq_takip); bu yüzden yeni
# satır eklemek yerine Temmuz'da son günü dolan MEVCUT bir kaydın durumu
# değiştirilir.
$MDBR -e "UPDATE beyanname_takip SET durum='VERILMEYECEK'
          WHERE mukellef_id=20 AND beyanname_turu_id=1
            AND YEAR(son_tarih)=2026 AND MONTH(son_tarih)=7;"
}

veriKur
giris admin $J
curl -s -b $J "$B/panel?yil=2026&ay=8" -o /tmp/pd_8.html
curl -s -b $J "$B/panel?yil=2026&ay=9" -o /tmp/pd_9.html
curl -s -b $J "$B/panel?yil=2026&ay=5" -o /tmp/pd_5.html

echo "=== 1) TABLO PANELDE VAR ==="
ol "Panel açılıyor" "200" "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/panel?yil=2026&ay=8")"
ol "Tablo başlığı var" "1" "$(grep -c 'Beyanname Durum Kontrol' /tmp/pd_8.html | awk '{print ($1>0)?1:0}')"
ol "Sayfada hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Undefined variable' /tmp/pd_8.html | awk '{print ($1>0)?1:0}')"
ol "Aylık grafik hâlâ duruyor" "1" "$(grep -c 'Aylık Beyanname Dağılımı' /tmp/pd_8.html | awk '{print ($1>0)?1:0}')"
ol "Tablo grafiğin ÜSTÜNDE" "1" \
  "$(python3 -c "
h=open('/tmp/pd_8.html',encoding='utf-8').read()
print(1 if h.find('Beyanname Durum Kontrol') < h.find('Aylık Beyanname Dağılımı') else 0)")"

echo "=== 2) AĞUSTOS — GEÇİCİ VERGİLER GÖRÜNÜYOR ==="
ol "Ağustos tür listesi" "KDV1 (Ay),MUHSGK (Ay),Gelir Geçici,Kurum Geçici,Damga,Turizm" "$(turler /tmp/pd_8.html)"
ol "Gelir Geçici satırı var"  "1" "$(turler /tmp/pd_8.html | grep -c 'Gelir Geçici')"
ol "Kurum Geçici satırı var"  "1" "$(turler /tmp/pd_8.html | grep -c 'Kurum Geçici')"

echo "=== 3) EYLÜL — GEÇİCİLER KAYBOLUYOR (asıl istek) ==="
ol "Eylül tür listesi" "KDV1 (Ay),MUHSGK (Ay),Damga" "$(turler /tmp/pd_9.html)"
ol "Eylül'de Gelir Geçici YOK"  "0" "$(turler /tmp/pd_9.html | grep -c 'Gelir Geçici')"
ol "Eylül'de Kurum Geçici YOK"  "0" "$(turler /tmp/pd_9.html | grep -c 'Kurum Geçici')"
ol "Eylül'de Turizm YOK"        "0" "$(turler /tmp/pd_9.html | grep -c 'Turizm')"
ol "Eylül'de KDV1 VAR"          "1" "$(turler /tmp/pd_9.html | grep -c 'KDV1')"
ol "Mayıs'ta geçiciler VAR"     "1" "$(turler /tmp/pd_5.html | grep -c 'Gelir Geçici')"
ol "Mart'ta Yıllık GV VAR"      "1" "$(curl -s -b $J "$B/panel?yil=2026&ay=3" -o /tmp/pd_3.html; turler /tmp/pd_3.html | grep -c 'Yıllık GV')"
ol "Mart'ta Kurumlar YOK"       "0" "$(turler /tmp/pd_3.html | grep -c 'Kurumlar')"
ol "Nisan'da Kurumlar VAR"      "1" "$(curl -s -b $J "$B/panel?yil=2026&ay=4" -o /tmp/pd_4.html; turler /tmp/pd_4.html | grep -c 'Kurumlar')"

echo "=== 4) SAYILAR VERİTABANIYLA BİREBİR (Ağustos) ==="
for tur in "1:KDV1 (Ay)" "4:MUHSGK (Ay)" "9:Gelir Geçici" "10:Kurum Geçici"; do
  tid=${tur%%:*}; tad=${tur##*:}
  for d in toplam:"" onaylandi:ONAYLANDI hazir:HAZIR bekliyor:BEKLIYOR; do
    sut=${d%%:*}; kod=${d##*:}
    if [ -z "$kod" ]; then
      bekl=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip WHERE beyanname_turu_id=$tid AND YEAR(son_tarih)=2026 AND MONTH(son_tarih)=8")
    else
      bekl=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip WHERE beyanname_turu_id=$tid AND YEAR(son_tarih)=2026 AND MONTH(son_tarih)=8 AND durum='$kod'")
    fi
    ol "$tad $sut = DB($bekl)" "$bekl" "$(hucre /tmp/pd_8.html "$tad" $sut)"
  done
done

echo "=== 5) KALAN = BEKLİYOR + HAZIR ==="
for tad in "KDV1 (Ay)" "MUHSGK (Ay)" "Gelir Geçici"; do
  B_=$(hucre /tmp/pd_8.html "$tad" bekliyor); H_=$(hucre /tmp/pd_8.html "$tad" hazir)
  ol "$tad kalan = $B_+$H_" "$((B_+H_))" "$(hucre /tmp/pd_8.html "$tad" kalan)"
done

echo "=== 6) TOPLAM SATIRI ==="
AGU=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id WHERE YEAR(bt.son_tarih)=2026 AND MONTH(bt.son_tarih)=8 AND m.deleted_at IS NULL")
ol "Ağustos TOPLAM = DB($AGU)" "$AGU" "$(toplamSat /tmp/pd_8.html)"
EYL=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id WHERE YEAR(bt.son_tarih)=2026 AND MONTH(bt.son_tarih)=9 AND m.deleted_at IS NULL")
ol "Eylül TOPLAM = DB($EYL)"   "$EYL" "$(toplamSat /tmp/pd_9.html)"
ol "Ağustos ≠ Eylül (ay filtresi işliyor)" "1" \
  "$([ "$(toplamSat /tmp/pd_8.html)" != "$(toplamSat /tmp/pd_9.html)" ] && echo 1 || echo 0)"

echo "=== 7) TAMAMLANMA ORANI ==="
ol "Mayıs KDV1 %100 (hepsi onaylı)" "100" "$(hucre /tmp/pd_5.html 'KDV1 (Ay)' oran)"
ol "Ağustos KDV1 %0 (hiçbiri onaylı değil)" "0" "$(hucre /tmp/pd_8.html 'KDV1 (Ay)' oran)"
# Temmuz'da 1 adet VERILMEYECEK var; payda dışı olduğu için oran yine %100
curl -s -b $J "$B/panel?yil=2026&ay=7" -o /tmp/pd_7.html
ol "Verilmeyecek oran paydasına girmiyor (%100)" "100" "$(hucre /tmp/pd_7.html 'KDV1 (Ay)' oran)"
# Not: veri kurulumunda MEVCUT bir kaydın durumu değiştirildiği için toplam
# 20 kalır; verilmeyecek olan da bu 20'nin içindedir.
ol "Verilmeyecek toplama dahil (20)" "20" "$(hucre /tmp/pd_7.html 'KDV1 (Ay)' toplam)"
ol "Verilmeyecek kalana dahil DEĞİL (0)" "0" "$(hucre /tmp/pd_7.html 'KDV1 (Ay)' kalan)"

echo "=== 8) TIKLANABİLİR SAYILAR ==="
ol "Sayı düğmeleri var" "1" "$(grep -c 'class="bdk-say' /tmp/pd_8.html | awk '{print ($1>0)?1:0}')"
ol "Sıfır olanlar tıklanamaz" "1" "$(grep -c 'bdk-say bos' /tmp/pd_8.html | awk '{print ($1>0)?1:0}')"
ol "data-tur özniteliği var" "1" "$(grep -c 'data-tur=' /tmp/pd_8.html | awk '{print ($1>0)?1:0}')"
ol "KALAN düğmesi var" "1" "$(grep -c 'data-durum="KALAN"' /tmp/pd_8.html | awk '{print ($1>0)?1:0}')"
ol "Açılır pencere kabı var" "1" "$(grep -c 'id="bdk-pencere"' /tmp/pd_8.html | awk '{print ($1>0)?1:0}')"

echo "=== 9) AJAX LİSTE UÇ NOKTASI ==="
al(){ curl -s -b $J -H "X-Requested-With: XMLHttpRequest" \
  "$B/panel/tur-listesi?yil=2026&ay=$1&tur_id=$2&durum=$3&mod=beyan" -o /tmp/pd_l.json; }
al 8 1 KALAN
KAL=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip WHERE beyanname_turu_id=1 AND MONTH(son_tarih)=8 AND YEAR(son_tarih)=2026 AND durum IN ('BEKLIYOR','HAZIR')")
ol "KDV1 Ağustos KALAN = DB($KAL)" "$KAL" "$(js /tmp/pd_l.json "['adet']")"
ol "Yanıt durumu true" "True" "$(js /tmp/pd_l.json "['durum']")"
ol "Kayıtta mükellef adı var" "1" "$(js /tmp/pd_l.json "['kayitlar'][0]['mukellef']" | grep -c 'FİRMA')"
al 8 9 HAZIR
HZ=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip WHERE beyanname_turu_id=9 AND MONTH(son_tarih)=8 AND durum='HAZIR'")
ol "Gelir Geçici Ağustos HAZIR = DB($HZ)" "$HZ" "$(js /tmp/pd_l.json "['adet']")"
al 9 9 ""
ol "Eylül'de Gelir Geçici = 0" "0" "$(js /tmp/pd_l.json "['adet']")"
al 8 1 ONAYLANDI
ol "Ağustos KDV1 ONAYLANDI = 0" "0" "$(js /tmp/pd_l.json "['adet']")"
al 8 1 ""
TOP=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip WHERE beyanname_turu_id=1 AND MONTH(son_tarih)=8 AND YEAR(son_tarih)=2026")
ol "Durum boş = tümü ($TOP)" "$TOP" "$(js /tmp/pd_l.json "['adet']")"
curl -s -b $J -H "X-Requested-With: XMLHttpRequest" "$B/panel/tur-listesi?yil=2026&ay=8&tur_id=0" -o /tmp/pd_h.json
ol "Geçersiz tür reddediliyor" "False" "$(js /tmp/pd_h.json "['durum']")"

echo "=== 10) DÖNEM MODU (mod=donem) ==="
curl -s -b $J "$B/panel?yil=2026&ay=8&mod=donem" -o /tmp/pd_d.html
ol "Dönem modu açılıyor" "0" "$(grep -cE 'ErrorException|Fatal error' /tmp/pd_d.html | awk '{print ($1>0)?1:0}')"
ol "Dönem modu rozeti" "1" "$(grep -c 'Dönem: Ağustos' /tmp/pd_d.html | awk '{print ($1>0)?1:0}')"
DON=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id WHERE bt.yil=2026 AND MONTH(bt.donem_bitis)=8 AND m.deleted_at IS NULL")
ol "Dönem modu TOPLAM = DB($DON)" "$DON" "$(toplamSat /tmp/pd_d.html)"

echo "=== 11) BOŞ AY ==="
curl -s -b $J "$B/panel?yil=2030&ay=1" -o /tmp/pd_bos.html
ol "Boş ayda hata yok" "0" "$(grep -cE 'ErrorException|Fatal error' /tmp/pd_bos.html | awk '{print ($1>0)?1:0}')"
ol "Boş ay mesajı çıkıyor" "1" "$(grep -c 'için beyanname bulunmuyor' /tmp/pd_bos.html | awk '{print ($1>0)?1:0}')"

echo "=== 12) YETKİ KAPSAMI ==="
giris musavir $JM
MUSID=$($MDB -e "SELECT COALESCE((SELECT musavir_id FROM kullanici_musavirleri WHERE kullanici_id=(SELECT id FROM kullanicilar WHERE kullanici_adi='musavir') LIMIT 1),(SELECT musavir_id FROM kullanicilar WHERE kullanici_adi='musavir'))")
curl -s -b $JM "$B/panel?yil=2026&ay=8" -o /tmp/pd_m.html
MB=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id WHERE m.musavir_id=$MUSID AND YEAR(bt.son_tarih)=2026 AND MONTH(bt.son_tarih)=8 AND m.deleted_at IS NULL")
ol "Müşavir yalnızca kendi kayıtlarını görüyor ($MB)" "$MB" "$(toplamSat /tmp/pd_m.html)"
ol "Müşavir toplamı admin'den küçük" "1" \
  "$([ "$(toplamSat /tmp/pd_m.html)" -lt "$(toplamSat /tmp/pd_8.html)" ] && echo 1 || echo 0)"
curl -s -b $JM -H "X-Requested-With: XMLHttpRequest" \
  "$B/panel/tur-listesi?yil=2026&ay=8&tur_id=1&durum=&mod=beyan" -o /tmp/pd_lm.json
MK=$($MDB -e "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id WHERE m.musavir_id=$MUSID AND bt.beyanname_turu_id=1 AND MONTH(bt.son_tarih)=8 AND YEAR(bt.son_tarih)=2026")
ol "AJAX'ta da kapsam korunuyor ($MK)" "$MK" "$(js /tmp/pd_lm.json "['adet']")"
ol "Oturumsuz AJAX engelleniyor" "1" \
  "$(c=$(curl -s -o /dev/null -w '%{http_code}' "$B/panel/tur-listesi?yil=2026&ay=8&tur_id=1"); [ "$c" = "302" ] || [ "$c" = "401" ] && echo 1 || echo 0)"

echo "=== 13) DİĞER SAYFALAR BOZULMADI ==="
for u in "takip?yil=2026&ay=8" raporlar mukellefler "panel/takvim" "odeme?yil=2026&ay=8"; do
  c=$(curl -s -b $J -o /tmp/pd_p.html -w "%{http_code}" "$B/$u")
  ol "/$u HTTP 200" "200" "$c"
  ol "/$u hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Unknown column' /tmp/pd_p.html | awk '{print ($1>0)?1:0}')"
done

echo
echo "================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "================================================"
[ $k -eq 0 ] || exit 1
