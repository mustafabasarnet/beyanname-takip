#!/bin/bash
# =====================================================================
#  AYLIK TEKRAR EDEN ÖDEMELER + YAZDIRMA DÜZENİ — REGRESYON TESTİ
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 kullanıcısı mevcut.
#  Not: Test kendi mükelleflerini (id 1-2-3) kurar; başka testler
#       AUTO_INCREMENT'i ilerletmiş olsa bile bağımsız çalışır.
#  Kullanım:  bash tests/tekrar_yazdirma_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/ty.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

rm -f $J
curl -s -c $J -o /tmp/f.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=admin" -d "sifre=Test1234" $B/giris

tok(){ curl -s -b $J -c $J -o /tmp/f.html "$1"; grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1; }

# ---- Test kendi verisini kurar (id 1-2-3 sabit) ----
veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE ozel_odemeler; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (1,'SMMM','Ali Yılmaz','Yılmaz',1);
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,vergi_dairesi,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','ÖZKAN İNŞAAT LTD. ŞTİ.','tuzel','1112223334','Nevşehir','bilanco','2021-01-01',1),
 (2,1,'M002','AYŞE ÇELİK','gercek','2223334445','Ürgüp','isletme','2022-06-15',1),
 (3,1,'M003','MEHMET KAYA','gercek','3334445556','Avanos','serbest_meslek','2020-03-01',1);
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif) VALUES (1,1,1),(1,8,1),(2,1,1),(3,1,1);
INSERT IGNORE INTO damga_tutarlari (beyanname_turu_id,yil,tutar) VALUES (1,2026,791.00);
INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,tahakkuk_tutari,damga_tutari,created_at,updated_at) VALUES
 (1,1,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','ONAYLANDI',12500.75,791,NOW(),NOW()),
 (1,8,2026,1,'2026 Yılı','2026-01-01','2026-12-31','2026-08-28','2026-08-28','ONAYLANDI',45000.00,791,NOW(),NOW()),
 (2,1,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','ONAYLANDI',3200.50,791,NOW(),NOW()),
 (3,1,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','ONAYLANDI',1800.00,791,NOW(),NOW());"
}

veriKur

# Kalem ekler: $1=mukellef $2=baslik $3=tutar $4=tarih $5=tekrar $6=bitis
ekle(){
  local t; t=$(tok "$B/odeme?yil=2026&ay=8")
  curl -s -b $J -c $J -L -o /tmp/r.html \
    -d "csrf_beyanname=$t" -d "mukellef_id=$1" -d "baslik=$2" -d "tutar=$3" \
    -d "son_tarih=$4" -d "tekrar=${5:-YOK}" -d "tekrar_bitis=${6:-}" \
    -d "durum=ONAYLANDI" $B/odeme/ozel-kaydet
}

temizle(){ $MDBR -e "DELETE FROM ozel_odemeler;"; }

echo "=== 1) ŞEMA ==="
for s in tekrar_bitis tekrar_kaynak_id; do
  ol "$s sütunu var" "1" "$($MDB -e "select count(*) from information_schema.columns
     where table_schema=database() and table_name='ozel_odemeler' and column_name='$s'")"
done

echo ""
echo "=== 2) TEKRARLI KALEM İZLEYEN AYLARDA OLUŞUYOR ==="
temizle
ekle 1 "Bağkur Primi" "5.500,00" "2026-08-01" "AYLIK"
ol "Ekleme sonrası kalem oluştu" "1" "$([ $($MDB -e 'select count(*) from ozel_odemeler') -ge 1 ] && echo 1 || echo 0)"

# Ay ay gezerek üretimi tetikle
for AY in 9 10 11 12; do curl -s -b $J -o /dev/null "$B/odeme?yil=2026&ay=$AY"; done
for AY in 1 2 3; do curl -s -b $J -o /dev/null "$B/odeme?yil=2027&ay=$AY"; done

for AY in 8 9 10 11 12; do
  ol "2026-$AY ayında Bağkur var" "1" \
     "$($MDB -e "select count(*) from ozel_odemeler where YEAR(son_tarih)=2026 and MONTH(son_tarih)=$AY and baslik='Bağkur Primi'")"
done
for AY in 1 2 3; do
  ol "2027-$AY ayında Bağkur var" "1" \
     "$($MDB -e "select count(*) from ozel_odemeler where YEAR(son_tarih)=2027 and MONTH(son_tarih)=$AY and baslik='Bağkur Primi'")"
done
ol "Tutar korunuyor" "5500.00" "$($MDB -e "select distinct tutar from ozel_odemeler")"
ol "Dönem etiketi doğru" "Kasım 2026" \
   "$($MDB -e "select donem_etiketi from ozel_odemeler where YEAR(son_tarih)=2026 and MONTH(son_tarih)=11")"
ol "Kaynak zinciri kuruldu" "1" \
   "$($MDB -e "select count(distinct tekrar_kaynak_id) from ozel_odemeler where tekrar_kaynak_id is not null")"

echo ""
echo "=== 3) MÜKERRER ÜRETİM KORUMASI ==="
ONCE=$($MDB -e "select count(*) from ozel_odemeler")
for i in 1 2 3; do curl -s -b $J -o /dev/null "$B/odeme?yil=2026&ay=10"; done
curl -s -b $J -o /dev/null -L "$B/odeme/tekrar-uret?yil=2026&ay=10"
curl -s -b $J -o /dev/null -L "$B/odeme/tekrar-uret?yil=2026&ay=11"
ol "Tekrar tekrar açınca mükerrer olmuyor" "$ONCE" "$($MDB -e 'select count(*) from ozel_odemeler')"

echo ""
echo "=== 4) BİTİŞ TARİHİNE UYUM ==="
temizle
ekle 2 "MTV Taksit" "1.200,00" "2026-08-15" "AYLIK" "2026-10-31"
for AY in 9 10 11 12; do curl -s -b $J -o /dev/null "$B/odeme?yil=2026&ay=$AY"; done
ol "Ağustos var" "1" "$($MDB -e "select count(*) from ozel_odemeler where MONTH(son_tarih)=8")"
ol "Eylül var"   "1" "$($MDB -e "select count(*) from ozel_odemeler where MONTH(son_tarih)=9")"
ol "Ekim var"    "1" "$($MDB -e "select count(*) from ozel_odemeler where MONTH(son_tarih)=10")"
ol "Kasım YOK (bitiş geçti)" "0" "$($MDB -e "select count(*) from ozel_odemeler where MONTH(son_tarih)=11")"
ol "Aralık YOK" "0" "$($MDB -e "select count(*) from ozel_odemeler where MONTH(son_tarih)=12")"
ol "Bitiş tarihi kopyalara geçti" "2026-10-31" \
   "$($MDB -e "select distinct tekrar_bitis from ozel_odemeler where tekrar_kaynak_id is not null")"

echo ""
echo "=== 5) AY SONU TAŞMASI (31'i olmayan aylar) ==="
temizle
ekle 1 "Ay Sonu Testi" "100,00" "2026-08-31" "AYLIK"
for AY in 9 10 11; do curl -s -b $J -o /dev/null "$B/odeme?yil=2026&ay=$AY"; done
ol "Eylül 30'a çekildi" "2026-09-30" "$($MDB -e "select son_tarih from ozel_odemeler where MONTH(son_tarih)=9")"
ol "Ekim 31 kaldı"      "2026-10-31" "$($MDB -e "select son_tarih from ozel_odemeler where MONTH(son_tarih)=10")"

echo ""
echo "=== 6) TEKRARI DURDURMA ==="
temizle
ekle 3 "Oda Aidatı" "300,00" "2026-08-05" "AYLIK"
for AY in 9 10 11 12; do curl -s -b $J -o /dev/null "$B/odeme?yil=2026&ay=$AY"; done
ONCE=$($MDB -e "select count(*) from ozel_odemeler")
$MDBR -e "UPDATE ozel_odemeler SET odendi=1 WHERE MONTH(son_tarih)=8;"
KAYNAK=$($MDB -e "select id from ozel_odemeler where tekrar_kaynak_id is null limit 1")
curl -s -b $J -o /dev/null -L "$B/odeme/tekrar-durdur/$KAYNAK"
ol "Gelecek ödenmemişler silindi" "1" "$($MDB -e 'select count(*) from ozel_odemeler')"
ol "Ödenmiş kalem korundu" "1" "$($MDB -e 'select count(*) from ozel_odemeler where odendi=1')"
ol "Tekrar kapatıldı" "YOK" "$($MDB -e 'select tekrar from ozel_odemeler limit 1')"
curl -s -b $J -o /dev/null "$B/odeme?yil=2027&ay=6"
ol "Durdurunca yeniden üretmiyor" "1" "$($MDB -e 'select count(*) from ozel_odemeler')"

echo ""
echo "=== 7) TEKRARSIZ KALEM ÇOĞALMAMALI ==="
temizle
ekle 1 "Tek Seferlik Ceza" "750,00" "2026-08-20" "YOK"
for AY in 9 10 11; do curl -s -b $J -o /dev/null "$B/odeme?yil=2026&ay=$AY"; done
ol "Sadece 1 kayıt" "1" "$($MDB -e 'select count(*) from ozel_odemeler')"

echo ""
echo "=== 8) YAZDIRMA — DETAYLI (yatay) ==="
temizle
$MDBR -e "INSERT INTO ozel_odemeler (mukellef_id,baslik,tutar,son_tarih,donem_etiketi,durum,tekrar,kaydeden_id,created_at,updated_at) VALUES
 (1,'Bağkur Primi',5500.00,'2026-08-01','Ağustos 2026','ONAYLANDI','AYLIK',1,NOW(),NOW()),
 (1,'MTV 2. Taksit',3200.00,'2026-08-31','Ağustos 2026','ONAYLANDI','YOK',1,NOW(),NOW()),
 (2,'Bağkur Primi',4100.00,'2026-08-01','Ağustos 2026','ONAYLANDI','AYLIK',1,NOW(),NOW()),
 (3,'Oda Aidatı',300.00,'2026-08-10','Ağustos 2026','ONAYLANDI','YOK',1,NOW(),NOW());"

curl -s -b $J "$B/odeme/yazdir?yil=2026&ay=8&bicim=detay" -o /tmp/ty_d.html
ol "HTTP 200 + fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/ty_d.html)"
ol "Yatay A4 tanımlı" "1" "$(grep -c 'A4 landscape' /tmp/ty_d.html)"
ol "Tek tablo (grup tablosu yok)" "1" "$(grep -c '<table' /tmp/ty_d.html)"
ol "VKN sütunu var" "1" "$(grep -c 'VKN / TCKN' /tmp/ty_d.html)"
ol "Vergi dairesi sütunu var" "1" "$(grep -c 'Vergi Dairesi' /tmp/ty_d.html)"
ol "Ara toplam satırları" "3" "$(grep -c '<tr class="ara-toplam"' /tmp/ty_d.html)"
ol "Genel toplam var" "1" "$(grep -c 'GENEL TOPLAM' /tmp/ty_d.html)"
ol "Özel kalem rozeti" "1" "$([ $(grep -c 'rozet ozel' /tmp/ty_d.html) -ge 4 ] && echo 1 || echo 0)"
ol "İmza alanı" "1" "$(grep -c 'Teslim Alan' /tmp/ty_d.html)"
ol "Biçim seçici (yazdırmada gizli)" "1" "$(grep -c 'class="arac-cubugu yazdirma-gizle"' /tmp/ty_d.html)"

echo ""
echo "=== 9) YAZDIRMA — ÖZET (çapraz) ==="
curl -s -b $J "$B/odeme/yazdir?yil=2026&ay=8&bicim=ozet" -o /tmp/ty_o.html
ol "HTTP 200 + fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/ty_o.html)"
ol "Çapraz başlık" "1" "$(grep -c '<h1>Ödeme Listesi — Özet</h1>' /tmp/ty_o.html)"
python3 - <<'PY' > /tmp/ty_p.txt
import re, html
s = open('/tmp/ty_o.html', encoding='utf-8').read()
h = re.search(r'<thead>(.*?)</thead>', s, re.S).group(1)
sut = [re.sub(r'\s+',' ',html.unescape(re.sub(r'<[^>]+>','',x))).strip()
       for x in re.findall(r'<th[^>]*>(.*?)</th>', h, re.S)]
print('SUTUN|' + '|'.join(sut))
govde = s.split('<tbody>')[1].split('</tbody>')[0]
for tr in re.findall(r'<tr>(.*?)</tr>', govde, re.S):
    td = [re.sub(r'\s+',' ',html.unescape(re.sub(r'<[^>]+>',' ',x))).strip()
          for x in re.findall(r'<td[^>]*>(.*?)</td>', tr, re.S)]
    print('SATIR|' + td[1].split('  ')[0] + '|' + td[-1])
PY
ol "Beyanname türü sütunu (KDV1)" "1" "$(grep -c 'KDV1' /tmp/ty_p.txt)"
ol "Özel kalem sütunu (Bağkur)" "1" "$(grep -c 'Bağkur Primi' /tmp/ty_p.txt)"
ol "Son sütun TOPLAM" "1" "$(grep -c 'TOPLAM$' /tmp/ty_p.txt)"
ol "3 mükellef satırı" "3" "$(grep -c '^SATIR' /tmp/ty_p.txt)"

# Toplamları DB ile karşılaştır
BEY=$($MDB -e "select ifnull(sum(tahakkuk_tutari+damga_tutari),0) from beyanname_takip
  where YEAR(ifnull(odeme_son_tarih,son_tarih))=2026 and MONTH(ifnull(odeme_son_tarih,son_tarih))=8 and durum='ONAYLANDI'")
OZL=$($MDB -e "select ifnull(sum(tutar),0) from ozel_odemeler where YEAR(son_tarih)=2026 and MONTH(son_tarih)=8")
BEKLENEN=$(python3 -c "
v=$BEY+$OZL
print(f'{v:,.2f}'.replace(',','#').replace('.',',').replace('#','.'))")
GERCEK_D=$(grep -oE '[0-9][0-9.]*,[0-9]{2} ₺' /tmp/ty_d.html | tail -1 | sed 's/ ₺//')
GERCEK_O=$(grep -oE '[0-9][0-9.]*,[0-9]{2} ₺' /tmp/ty_o.html | tail -1 | sed 's/ ₺//')
ol "Detay genel toplam = DB" "$BEKLENEN" "$GERCEK_D"
ol "Özet genel toplam = DB" "$BEKLENEN" "$GERCEK_O"
ol "İki biçim aynı toplamı veriyor" "$GERCEK_D" "$GERCEK_O"

echo ""
echo "=== 10) YETKİ (personel özel kalem yönetimi) ==="
rm -f /tmp/typ.txt
curl -s -c /tmp/typ.txt -o /tmp/f.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
curl -s -b /tmp/typ.txt -c /tmp/typ.txt -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=personel" -d "sifre=Test1234" $B/giris
ol "Personel ödeme listesine giremiyor" "302" "$(curl -s -b /tmp/typ.txt -o /dev/null -w '%{http_code}' $B/odeme)"
ol "Personel tekrar üretemiyor" "302" "$(curl -s -b /tmp/typ.txt -o /dev/null -w '%{http_code}' "$B/odeme/tekrar-uret?yil=2026&ay=8")"
ol "Personel yazdıramıyor" "302" "$(curl -s -b /tmp/typ.txt -o /dev/null -w '%{http_code}' "$B/odeme/yazdir?yil=2026&ay=8")"

temizle
echo ""; echo "======"
[ $k -eq 0 ] && echo "BASARILI ($g/$((g+k)))" || echo "$k HATA ($g/$((g+k)))"
