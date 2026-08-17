#!/bin/bash
# =====================================================================
#  E-DEFTER BERAT TAKİBİ — REGRESYON TESTİ
#
#  Kapsam:
#   1. Şema + idempotent migration
#   2. Son tarih motoru (aylık +3 ay sonu, üç aylık +2 ay sonu, kaydırma)
#   3. Dönem üretimi (YOK olan üretilmez, faaliyet/başlangıç kesişimi)
#   4. Kontrol listesi akışı: Banka Temin → ... → Hazır → Onay
#   5. Durum otomatiği (DEVAM / HAZIR / ONAYLANDI) + berat tarihi
#   6. "Yüklenmeyecek" elle seçimi adımlarca ezilmiyor
#   7. Filtreler: aylık / üç aylık / durum / sorumlu / gecikmiş
#   8. Panel kartı yalnızca berat olan ayda görünüyor
#   9. Ayarlar son tarihi gerçekten değiştiriyor
#  10. Adım tanımları düzenlenebiliyor; HAZIR/ONAY silinemiyor
#  11. Yetki kapsamı sızmıyor
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin / musavir / personel (Test1234) kullanıcıları var.
#  Not: Test kendi verisini kurar.
#  Kullanım:  bash tests/edefter_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
KOK="$(cd "$(dirname "$0")/.." && pwd)"
J=/tmp/ed_a.txt
JM=/tmp/ed_m.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/ed_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ed_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# AJAX çağrısı (her seferinde taze CSRF alır)
aj(){ local cerez=$1; shift
  local u=$1; shift
  local t; t=$(curl -s -b "$cerez" -c "$cerez" "$B/edefter?yil=2026&ay=0" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1)
  curl -s -b "$cerez" -c "$cerez" -H "X-Requested-With: XMLHttpRequest" -d "csrf_beyanname=$t" "$@" "$u" | tr -d '\n'; }

js(){ python3 -c "import json,sys;d=json.load(open('$1'));print(d$2)" 2>/dev/null || echo "JSON-HATA"; }
adimId(){ $MDB -e "SELECT id FROM edefter_adimlari WHERE kod='$1'"; }

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE edefter_adim_durum; TRUNCATE edefter_takip;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES
 (1,'SMMM','Ali Yılmaz','Yılmaz',1),(2,'SMMM','Veli Demir','Demir',1);
UPDATE kullanicilar SET musavir_id=2 WHERE kullanici_adi='musavir';
UPDATE ayarlar SET deger='4' WHERE anahtar='edefter_aylik_ay_sonra';
UPDATE ayarlar SET deger='3' WHERE anahtar='edefter_ucaylik_ay_sonra';
UPDATE ayarlar SET deger='10' WHERE anahtar='edefter_gun_gercek';
UPDATE ayarlar SET deger='14' WHERE anahtar='edefter_gun_tuzel';
UPDATE ayarlar SET deger='4' WHERE anahtar='edefter_aralik_gercek_ay';
UPDATE ayarlar SET deger='5' WHERE anahtar='edefter_aralik_tuzel_ay';
-- Berat günü mükellef tipine göre değiştiği için gerçek/tüzel ayrı test edilir
INSERT INTO mukellefler
 (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,edefter_donem,edefter_sorumlu_id,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','ALFA TÜZEL LTD','tuzel','1112223334',NULL,'bilanco','AYLIK',3,'2019-01-01',1),
 (2,1,'M002','BETA GERÇEK KİŞİ','gercek',NULL,'22233344455','isletme','AYLIK',4,'2019-01-01',1),
 (3,1,'M003','GAMA TÜZEL LTD','tuzel','3334445556',NULL,'bilanco','UC_AYLIK',3,'2019-01-01',1),
 (4,2,'M004','DELTA GERÇEK KİŞİ','gercek',NULL,'44455566677','isletme','UC_AYLIK',NULL,'2019-01-01',1),
 (5,1,'M005','EPSILON ŞAHIS','gercek',NULL,'55566677788','isletme','YOK',NULL,'2019-01-01',1);"
}

veriKur
# Önceki koşumdan kalan deneme adımını temizle (test tekrarlanabilir olsun)
$MDBR -e "DELETE FROM edefter_adimlari WHERE kod='KASA_KONTROL';"
giris admin $J
curl -s -b $J -L "$B/edefter/toplu-uret?yil=2026" -o /dev/null

echo "=== 1) ŞEMA ==="
for t in edefter_takip edefter_adimlari edefter_adim_durum; do
  ol "$t tablosu var" "1" "$($MDB -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'")"
done
for a in edefter_donem edefter_sorumlu_id edefter_baslangic; do
  ol "mukellefler.$a" "1" "$($MDB -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mukellefler' AND COLUMN_NAME='$a'")"
done
ol "6 varsayılan adım" "6" "$($MDB -e "SELECT COUNT(*) FROM edefter_adimlari WHERE aktif=1")"
ol "Adım sırası doğru" "BANKA_TEMIN,BANKA_ISLEME,CEK_ISLEME,MIZAN,HAZIR,ONAY" \
   "$($MDB -e "SELECT GROUP_CONCAT(kod ORDER BY sira) FROM edefter_adimlari WHERE aktif=1")"
# Migration idempotent mi?
$MDBR < "$KOK/database/migration_edefter.sql" >/dev/null 2>&1
ol "Migration ikinci kez çalıştı" "6" "$($MDB -e "SELECT COUNT(*) FROM edefter_adimlari WHERE aktif=1")"

echo "=== 2) SON TARİH MOTORU (GİB berat takvimi) ==="
# Kaynak: GİB e-defter berat yükleme takvimi
#   Aylık    : dönem ayı + 4 ay | Üç aylık: dönem bitişi + 3 ay
#   Gün      : gelir vergisi mükellefi (gerçek kişi) 10, diğer (tüzel) 14
#   ARALIK   : istisna → gerçek kişi Nisan 10, tüzel Mayıs 14
# Yasal tarih üzerinden karşılaştırılır (tatil kaydırması ayrıca test edilir).
yas(){ $MDB -e "SELECT yasal_son_tarih FROM edefter_takip WHERE mukellef_id=$1 AND donem_no=$2"; }

echo "  -- Aylık / DİĞER MÜKELLEFLER (tüzel, gün 14) --"
ol "Ocak → 14.05.2026"    "2026-05-14" "$(yas 1 1)"
ol "Şubat → 14.06.2026"   "2026-06-14" "$(yas 1 2)"
ol "Mayıs → 14.09.2026"   "2026-09-14" "$(yas 1 5)"
ol "Ağustos → 14.12.2026" "2026-12-14" "$(yas 1 8)"
ol "Eylül → 14.01.2027"   "2027-01-14" "$(yas 1 9)"
ol "Kasım → 14.03.2027"   "2027-03-14" "$(yas 1 11)"
ol "ARALIK → 14.05.2027 (istisna)" "2027-05-14" "$(yas 1 12)"

echo "  -- Aylık / GELİR VERGİSİ MÜKELLEFİ (gerçek, gün 10) --"
ol "Ocak → 10.05.2026"    "2026-05-10" "$(yas 2 1)"
ol "Mart → 10.07.2026"    "2026-07-10" "$(yas 2 3)"
ol "Haziran → 10.10.2026" "2026-10-10" "$(yas 2 6)"
ol "Ekim → 10.02.2027"    "2027-02-10" "$(yas 2 10)"
ol "ARALIK → 10.04.2027 (istisna)" "2027-04-10" "$(yas 2 12)"

echo "  -- Üç aylık / DİĞER (tüzel, gün 14) --"
ol "Q1 Oca-Mar → 14.06.2026" "2026-06-14" "$(yas 3 1)"
ol "Q2 Nis-Haz → 14.09.2026" "2026-09-14" "$(yas 3 2)"
ol "Q3 Tem-Eyl → 14.12.2026" "2026-12-14" "$(yas 3 3)"
ol "Q4 Eki-Ara → 14.05.2027 (istisna)" "2027-05-14" "$(yas 3 4)"

echo "  -- Üç aylık / GELİR VERGİSİ (gerçek, gün 10) --"
ol "Q1 Oca-Mar → 10.06.2026" "2026-06-10" "$(yas 4 1)"
ol "Q2 Nis-Haz → 10.09.2026" "2026-09-10" "$(yas 4 2)"
ol "Q3 Tem-Eyl → 10.12.2026" "2026-12-10" "$(yas 4 3)"
ol "Q4 Eki-Ara → 10.04.2027 (istisna)" "2027-04-10" "$(yas 4 4)"

echo "  -- Gerçek/tüzel farkı gerçekten uygulanıyor mu --"
ol "Aynı dönem, farklı gün (14 vs 10)" "1" \
  "$([ "$(yas 1 1)" != "$(yas 2 1)" ] && echo 1 || echo 0)"
ol "Aralık istisnası tipe göre farklı" "1" \
  "$([ "$(yas 1 12)" != "$(yas 2 12)" ] && echo 1 || echo 0)"

echo "  -- Tatil / hafta sonu kaydırması --"
# 10.05.2026 Pazar'a denk gelir → 11.05 Pazartesi
ol "Ocak(gerçek) yasal 10.05" "2026-05-10" "$(yas 2 1)"
ol "Ocak(gerçek) kaydı 11.05" "2026-05-11" "$($MDB -e "SELECT son_tarih FROM edefter_takip WHERE mukellef_id=2 AND donem_no=1")"
ol "Kaydırma nedeni yazılı"   "Pazar"      "$($MDB -e "SELECT kaydirma_nedeni FROM edefter_takip WHERE mukellef_id=2 AND donem_no=1")"
# Mart dönemi → 14.07.2026 SALI; kaydırma gerekmez, neden boş kalmalı.
# (Şubat dönemi seçilemez: 14.06.2026 Pazar'a denk gelir.)
ol "Kaydırma gerekmeyende neden boş" "" \
  "$($MDB -e "SELECT IFNULL(kaydirma_nedeni,'') FROM edefter_takip WHERE mukellef_id=1 AND donem_no=3")"
ol "Kaydırma gerekmeyende tarih aynı" "1" \
  "$([ "$(yas 1 3)" = "$($MDB -e "SELECT son_tarih FROM edefter_takip WHERE mukellef_id=1 AND donem_no=3")" ] && echo 1 || echo 0)"

echo "=== 3) DÖNEM ÜRETİMİ ==="
ol "Aylık mükellef 12 dönem"      "12" "$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE mukellef_id=1")"
ol "Üç aylık mükellef 4 dönem"     "4" "$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE mukellef_id=3")"
ol "'YOK' seçili mükellef 0 dönem"  "0" "$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE mukellef_id=5")"
ol "Toplam 32 dönem"               "32" "$($MDB -e "SELECT COUNT(*) FROM edefter_takip")"
# Tekrar üretim mükerrer kayıt açmamalı
curl -s -b $J -L "$B/edefter/toplu-uret?yil=2026" -o /dev/null
ol "Tekrar üretimde mükerrer yok" "32" "$($MDB -e "SELECT COUNT(*) FROM edefter_takip")"
# Takip başlangıcı
$MDBR -e "UPDATE mukellefler SET edefter_baslangic='2026-07-01' WHERE id=2;"
curl -s -b $J -L "$B/edefter/toplu-uret?yil=2026" -o /dev/null
ol "Başlangıç öncesi dönemler silindi" "6" "$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE mukellef_id=2")"
$MDBR -e "UPDATE mukellefler SET edefter_baslangic=NULL WHERE id=2;"
curl -s -b $J -L "$B/edefter/toplu-uret?yil=2026" -o /dev/null
ol "Başlangıç kalkınca geri geldi" "12" "$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE mukellef_id=2")"

echo "=== 4) KONTROL LİSTESİ AKIŞI (büro senaryosu) ==="
TID=$($MDB -e "SELECT id FROM edefter_takip WHERE mukellef_id=1 AND donem_no=5")
bekle=( "BANKA_TEMIN:17:DEVAM" "BANKA_ISLEME:33:DEVAM" "CEK_ISLEME:50:DEVAM"
        "MIZAN:67:DEVAM" "HAZIR:83:HAZIR" "ONAY:100:ONAYLANDI" )
for x in "${bekle[@]}"; do
  kod=${x%%:*}; kalan=${x#*:}; yuzde=${kalan%%:*}; durum=${kalan##*:}
  R=$(aj $J "$B/edefter/adim" -d "takip_id=$TID" -d "adim_id=$(adimId $kod)" -d "tamam=1")
  echo "$R" > /tmp/ed_r.json
  ol "$kod → %$yuzde"       "$yuzde"  "$(js /tmp/ed_r.json "['ilerleme']")"
  ol "$kod → durum $durum"  "$durum"  "$($MDB -e "SELECT durum FROM edefter_takip WHERE id=$TID")"
done
ol "Onayda berat tarihi düşüldü" "1" \
  "$([ -n "$($MDB -e "SELECT berat_tarihi FROM edefter_takip WHERE id=$TID")" ] && echo 1 || echo 0)"

echo "=== 5) ADIM GERİ ALMA ==="
R=$(aj $J "$B/edefter/adim" -d "takip_id=$TID" -d "adim_id=$(adimId ONAY)" -d "tamam=0")
echo "$R" > /tmp/ed_r.json
ol "Onay kaldırılınca HAZIR" "HAZIR" "$($MDB -e "SELECT durum FROM edefter_takip WHERE id=$TID")"
ol "Berat tarihi temizlendi"  "NULL" "$($MDB -e "SELECT IFNULL(berat_tarihi,'NULL') FROM edefter_takip WHERE id=$TID")"
ol "İlerleme %83'e döndü"      "83"  "$(js /tmp/ed_r.json "['ilerleme']")"

echo "=== 6) 'YÜKLENMEYECEK' ELLE SEÇİMİ ==="
T2=$($MDB -e "SELECT id FROM edefter_takip WHERE mukellef_id=1 AND donem_no=6")
aj $J "$B/edefter/durum" -d "id=$T2" -d "durum=YUKLENMEYECEK" > /dev/null
ol "Durum yüklenmeyecek oldu" "YUKLENMEYECEK" "$($MDB -e "SELECT durum FROM edefter_takip WHERE id=$T2")"
aj $J "$B/edefter/adim" -d "takip_id=$T2" -d "adim_id=$(adimId BANKA_TEMIN)" -d "tamam=1" > /dev/null
ol "Adım işareti bu durumu EZMİYOR" "YUKLENMEYECEK" "$($MDB -e "SELECT durum FROM edefter_takip WHERE id=$T2")"
aj $J "$B/edefter/durum" -d "id=$T2" -d "durum=BEKLIYOR" > /dev/null
ol "Geri alınca adımdan hesaplanıyor" "DEVAM" "$($MDB -e "SELECT durum FROM edefter_takip WHERE id=$T2")"

echo "=== 7) TOPLU İŞARETLEME VE NOT ==="
T3=$($MDB -e "SELECT id FROM edefter_takip WHERE mukellef_id=3 AND donem_no=2")
R=$(aj $J "$B/edefter/hepsi" -d "takip_id=$T3" -d "tamam=1"); echo "$R" > /tmp/ed_r.json
ol "Hepsi işaretlendi → %100" "100" "$(js /tmp/ed_r.json "['ilerleme']")"
ol "Durum ONAYLANDI" "ONAYLANDI" "$($MDB -e "SELECT durum FROM edefter_takip WHERE id=$T3")"
R=$(aj $J "$B/edefter/hepsi" -d "takip_id=$T3" -d "tamam=0"); echo "$R" > /tmp/ed_r.json
ol "Hepsi temizlendi → %0" "0" "$(js /tmp/ed_r.json "['ilerleme']")"
aj $J "$B/edefter/not" -d "id=$T3" -d "not=Banka ekstresi eksik ŞÜKRÜ" > /dev/null
ol "Not kaydedildi (Türkçe)" "Banka ekstresi eksik ŞÜKRÜ" "$($MDB -e "SELECT not_metni FROM edefter_takip WHERE id=$T3")"

echo "=== 8) FİLTRELER ==="
# Not: data-id satır başına 3 kez geçer (<tr>, durum kutusu, not alanı).
# Bu yüzden yalnızca <tr ...data-id> kalıbı sayılır.
say(){ python3 -c "
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
print(len(re.findall(r'<tr[^>]*data-id=', h, re.S)))
" "$1"; }
# Not: yıl filtresi artık BERAT TARİHİ eksenindedir. 2026 dönemlerinin bir
# kısmı (Eylül-Aralık ve Q3-Q4) 2027'de yüklendiği için "2026 + tüm aylar"
# tüm dönemleri değil, 2026'da yüklenecekleri getirir. Beklenen değerler
# veritabanından dinamik okunur.
T26=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE YEAR(son_tarih)=2026")
curl -s -b $J "$B/edefter?yil=2026&ay=0" -o /tmp/ed_t.html
ol "2026'da yüklenecek tüm beratlar ($T26)" "$T26" "$(say /tmp/ed_t.html)"
A26=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE YEAR(son_tarih)=2026 AND donem_tipi='AYLIK'")
curl -s -b $J "$B/edefter?yil=2026&ay=0&donem_tipi=AYLIK" -o /tmp/ed_ay.html
ol "Aylık filtresi ($A26)" "$A26" "$(say /tmp/ed_ay.html)"
U26=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE YEAR(son_tarih)=2026 AND donem_tipi='UC_AYLIK'")
curl -s -b $J "$B/edefter?yil=2026&ay=0&donem_tipi=UC_AYLIK" -o /tmp/ed_uc.html
ol "Üç aylık filtresi ($U26)" "$U26" "$(say /tmp/ed_uc.html)"
ol "İki filtre toplamı = tümü" "$T26" "$(( $(say /tmp/ed_ay.html) + $(say /tmp/ed_uc.html) ))"
# Dönem modunda TÜM dönemler görünür (32)
TUM=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE yil=2026")
curl -s -b $J "$B/edefter?yil=2026&ay=0&mod=donem" -o /tmp/ed_tumd.html
ol "Dönem modunda 2026'nın tamamı ($TUM)" "$TUM" "$(say /tmp/ed_tumd.html)"
curl -s -b $J "$B/edefter?yil=2026&ay=8" -o /tmp/ed_a8.html
A8=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE YEAR(son_tarih)=2026 AND MONTH(son_tarih)=8")
ol "Ağustos berat ayı ($A8)" "$A8" "$(say /tmp/ed_a8.html)"

echo "=== 8b) TARİH EKSENİ TUTARLILIĞI (düzeltilen mantık hatası) ==="
# KUSUR: yıl 'yil' kolonundan, ay son_tarih'ten okunuyordu. İki eksen
# karıştığı için "2026 + Mayıs" filtresinde son tarihi 14.05.2027 olan
# 2026/Q4 dönemi de listeleniyordu.
curl -s -b $J "$B/edefter?yil=2026&ay=5" -o /tmp/ed_m26.html
BEKL26=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE YEAR(son_tarih)=2026 AND MONTH(son_tarih)=5")
ol "2026+Mayıs = berat tarihi 2026-05 ($BEKL26)" "$BEKL26" "$(say /tmp/ed_m26.html)"
ol "2026+Mayıs listesinde 2027 tarihi YOK" "0" \
  "$(grep -oE '<b>[0-9]{2}\.[0-9]{2}\.2027</b>' /tmp/ed_m26.html | wc -l | tr -d ' ')"
# Aralık dönemleri asıl beratlarının olduğu ayda görünmeli
curl -s -b $J "$B/edefter?yil=2027&ay=5" -o /tmp/ed_m27.html
BEKL27=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE YEAR(son_tarih)=2027 AND MONTH(son_tarih)=5")
ol "2027+Mayıs = berat tarihi 2027-05 ($BEKL27)" "$BEKL27" "$(say /tmp/ed_m27.html)"
ol "2026/Q4 (tüzel) Mayıs 2027'de görünüyor" "1" \
  "$(grep -c '4. Dönem 2026' /tmp/ed_m27.html | awk '{print ($1>0)?1:0}')"
ol "2026/Q4 Mayıs 2026'da GÖRÜNMÜYOR" "0" \
  "$(grep -c '4. Dönem 2026' /tmp/ed_m26.html)"
# Dönem modu: ay dönem bitişine bakar
curl -s -b $J "$B/edefter?yil=2026&ay=12&mod=donem" -o /tmp/ed_d12.html
DBEKL=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE yil=2026 AND MONTH(donem_bitis)=12")
ol "Dönem modu 2026+Aralık ($DBEKL)" "$DBEKL" "$(say /tmp/ed_d12.html)"
ol "Dönem modunda Q4 görünüyor" "1" \
  "$(grep -c '4. Dönem 2026' /tmp/ed_d12.html | awk '{print ($1>0)?1:0}')"
ol "Mod seçicisi ekranda" "1" \
  "$(grep -c 'name=\"mod\"' /tmp/ed_d12.html | awk '{print ($1>0)?1:0}')"
ol "Dönem modu seçili kalıyor" "1" \
  "$(grep -cE '<option value=\"donem\"[^>]*selected' /tmp/ed_d12.html | awk '{print ($1>0)?1:0}')"
# Özet sayaçları listeyle aynı eksende olmalı
OZ=$(python3 - /tmp/ed_m26.html <<'PYO'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
m=re.search(r'Toplam</div>\s*<div class="deger">([\d.]+)',h)
print(m.group(1).replace('.','') if m else 'YOK')
PYO
)
ol "Özet 'Toplam' listeyle aynı ($BEKL26)" "$BEKL26" "$OZ"

echo "=== 8c) VARSAYILAN AY = İÇİNDE BULUNULAN AY ==="
# Ekran açıldığında "bu ay ne yükleyeceğim" görünmeli
curl -s -b $J "$B/edefter" -o /tmp/ed_vars.html
BU_AY=$(date +%-m)
ol "Ay filtresi bu aya ayarlı ($BU_AY)" "1" \
  "$(python3 - /tmp/ed_vars.html "$BU_AY" <<'PYV'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('name="ay"')
b=h[i:h.find('</select>',i)]
m=re.search(r'<option value="(\d+)"[^>]*selected',b)
print(1 if m and m.group(1)==sys.argv[2] else 0)
PYV
)"
ol "Varsayılan mod 'berat'" "1" \
  "$(python3 - /tmp/ed_vars.html <<'PYM'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('name="mod"')
b=h[i:h.find('</select>',i)]
m=re.search(r'<option value="(\w+)"[^>]*selected',b)
print(1 if m and m.group(1)=='berat' else 0)
PYM
)"
ol "ay=0 ile 'Tüm Aylar' seçilebiliyor" "1" \
  "$(curl -s -b $J "$B/edefter?ay=0" | grep -cE '<option value=\"0\"[^>]*selected' | awk '{print ($1>0)?1:0}')"
curl -s -b $J "$B/edefter?yil=2026&ay=0&sorumlu_id=3" -o /tmp/ed_s.html
S3=$($MDB -e "SELECT COUNT(*) FROM edefter_takip et JOIN mukellefler m ON m.id=et.mukellef_id WHERE m.edefter_sorumlu_id=3 AND YEAR(et.son_tarih)=2026")
ol "Sorumlu personel filtresi ($S3)" "$S3" "$(say /tmp/ed_s.html)"
curl -s -b $J "$B/edefter?yil=2026&ay=0&durum=ONAYLANDI" -o /tmp/ed_o.html
O1=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE durum='ONAYLANDI' AND YEAR(son_tarih)=2026")
ol "Durum filtresi ($O1)" "$O1" "$(say /tmp/ed_o.html)"
curl -s -b $J "$B/edefter?yil=2026&ay=0&gecikmis=1" -o /tmp/ed_g.html
G1=$($MDB -e "SELECT COUNT(*) FROM edefter_takip WHERE son_tarih<CURDATE() AND YEAR(son_tarih)=2026 AND durum NOT IN ('ONAYLANDI','YUKLENMEYECEK')")
ol "Gecikmiş filtresi ($G1)" "$G1" "$(say /tmp/ed_g.html)"
curl -s -b $J "$B/edefter?yil=2026&ay=0&q=GAMA" -o /tmp/ed_q.html
QB=$($MDB -e "SELECT COUNT(*) FROM edefter_takip et JOIN mukellefler m ON m.id=et.mukellef_id WHERE m.unvan LIKE '%GAMA%' AND YEAR(et.son_tarih)=2026")
ol "Arama filtresi ($QB)" "$QB" "$(say /tmp/ed_q.html)"

echo "=== 9) PANEL KARTI ==="
kartVar(){ grep -c 'class="kart edk-kart"' "$1" | awk '{print ($1>0)?1:0}'; }
# Not: "edk-kart" ilk olarak gomulu <style> blogunda gecer; arama gercek
# HTML ogesinden (class="kart edk-kart") baslatilir.
kartSayi(){ python3 - "$1" "$2" <<'PYK'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('class="kart edk-kart"')
if i<0: print('KART-YOK'); raise SystemExit
b=h[i:h.find('edk-cubuk"',i)]
for m in re.finditer(r'edk-sayi[^>]*>([\d.]+)</span>\s*<span class="edk-etiket">([^<]+)', b):
    if m.group(2).strip().upper()==sys.argv[2].upper():
        print(m.group(1).replace('.','')); raise SystemExit
print('YOK')
PYK
}
curl -s -b $J "$B/panel?yil=2026&ay=8" -o /tmp/ed_p8.html
ol "Berat olan ayda kart VAR" "1" "$(kartVar /tmp/ed_p8.html)"
ol "Kart toplamı = DB($A8)" "$A8" "$(kartSayi /tmp/ed_p8.html TOPLAM)"
ol "Dönem etiketi görünüyor" "1" "$(grep -c '2026\.0' /tmp/ed_p8.html | awk '{print ($1>0)?1:0}')"
# Berat olmayan ay
BOS=$($MDB -e "SELECT t.ay FROM (SELECT 1 ay UNION SELECT 5 UNION SELECT 10) t
      WHERE NOT EXISTS (SELECT 1 FROM edefter_takip WHERE yil=2026 AND MONTH(son_tarih)=t.ay) LIMIT 1")
if [ -n "$BOS" ]; then
  curl -s -b $J "$B/panel?yil=2026&ay=$BOS" -o /tmp/ed_pb.html
  ol "Berat olmayan ayda kart YOK (ay=$BOS)" "0" "$(kartVar /tmp/ed_pb.html)"
else
  ol "Boş ay bulunamadı (atlandı)" "1" "1"
fi
ol "Panelde hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Undefined' /tmp/ed_p8.html | awk '{print ($1>0)?1:0}')"

echo "=== 9b) PANEL DÖNEM ETİKETİ ==="
# KUSUR: etiket yalnızca dönem BAŞLANGIÇ ayını yazıyordu. Üç aylık Q2
# (Nisan-Haziran) için "2026.04" görünüyor, kullanıcı bunu "Nisan dönemi"
# sanıyordu. Artık tip belirtilir ve üç aylıkta aralık gösterilir.
etiket(){ python3 - "$1" <<'PYE'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('class="kart edk-kart"')
if i<0: print('KART-YOK'); raise SystemExit
m=re.search(r'rozet gri[^>]*>([^<]+)',h[i:i+1500])
print(m.group(1).strip() if m else 'ETIKET-YOK')
PYE
}
# Eylül 2026: aylık Mayıs + üç aylık Q2 (Nis-Haz) yüklenir
curl -s -b $J "$B/panel?yil=2026&ay=9" -o /tmp/ed_pe9.html
ol "Eylül etiketi tip+aralık gösteriyor" "Aylık 2026.05 · 3 Aylık 2026.04-06" "$(etiket /tmp/ed_pe9.html)"
ol "Etiket yalnızca başlangıç ayı DEĞİL" "0" \
  "$([ "$(etiket /tmp/ed_pe9.html)" = "2026.04 · 2026.05" ] && echo 1 || echo 0)"
# Haziran 2026: aylık Şubat + üç aylık Q1 (Oca-Mar)
curl -s -b $J "$B/panel?yil=2026&ay=6" -o /tmp/ed_pe6.html
ol "Haziran etiketi" "Aylık 2026.02 · 3 Aylık 2026.01-03" "$(etiket /tmp/ed_pe6.html)"
# Tek tip varsa ön ek yazılmaz
curl -s -b $J "$B/panel?yil=2026&ay=8" -o /tmp/ed_pe8.html
ol "Tek tip varsa sade etiket" "2026.04" "$(etiket /tmp/ed_pe8.html)"
# Etiket veriyle tutarlı mı?
DB_AY=$($MDB -e "SELECT DATE_FORMAT(MIN(donem_baslangic),'%Y.%m') FROM edefter_takip
        WHERE YEAR(son_tarih)=2026 AND MONTH(son_tarih)=9 AND donem_tipi='AYLIK'")
DB_UC=$($MDB -e "SELECT CONCAT(DATE_FORMAT(MIN(donem_baslangic),'%Y.%m'),'-',DATE_FORMAT(MAX(donem_bitis),'%m'))
        FROM edefter_takip WHERE YEAR(son_tarih)=2026 AND MONTH(son_tarih)=9 AND donem_tipi='UC_AYLIK'")
ol "Etiket DB ile birebir" "Aylık $DB_AY · 3 Aylık $DB_UC" "$(etiket /tmp/ed_pe9.html)"

echo "=== 10) AYARLAR SON TARİHİ DEĞİŞTİRİYOR ==="
# Ay sayısı
$MDBR -e "UPDATE ayarlar SET deger='5' WHERE anahtar='edefter_aylik_ay_sonra';"
curl -s -b $J -L "$B/edefter/toplu-uret?yil=2026" -o /dev/null
ol "Ay ayarı 5 → Ocak 14.06.2026" "2026-06-14" "$(yas 1 1)"
$MDBR -e "UPDATE ayarlar SET deger='4' WHERE anahtar='edefter_aylik_ay_sonra';"
# Gün
$MDBR -e "UPDATE ayarlar SET deger='17' WHERE anahtar='edefter_gun_tuzel';"
curl -s -b $J -L "$B/edefter/toplu-uret?yil=2026" -o /dev/null
ol "Gün ayarı 17 → Ocak 17.05.2026" "2026-05-17" "$(yas 1 1)"
ol "Gerçek kişi etkilenmedi (10)"   "2026-05-10" "$(yas 2 1)"
$MDBR -e "UPDATE ayarlar SET deger='14' WHERE anahtar='edefter_gun_tuzel';"
# Aralık istisnası
$MDBR -e "UPDATE ayarlar SET deger='6' WHERE anahtar='edefter_aralik_tuzel_ay';"
curl -s -b $J -L "$B/edefter/toplu-uret?yil=2026" -o /dev/null
ol "Aralık ayarı 6 → 14.06.2027" "2027-06-14" "$(yas 1 12)"
$MDBR -e "UPDATE ayarlar SET deger='5' WHERE anahtar='edefter_aralik_tuzel_ay';"
curl -s -b $J -L "$B/edefter/toplu-uret?yil=2026" -o /dev/null
ol "Varsayılana dönüldü → 14.05.2027" "2027-05-14" "$(yas 1 12)"
ol "Adım işaretleri korundu" "1" \
  "$([ "$($MDB -e "SELECT COUNT(*) FROM edefter_adim_durum WHERE takip_id=$TID AND tamam=1")" -gt 0 ] && echo 1 || echo 0)"

echo "=== 11) ADIM TANIMLARI ==="
curl -s -b $J "$B/tanimlar/edefter-adimlari" -o /tmp/ed_ta.html
ol "Tanım sayfası açılıyor" "1" "$(grep -c 'E-Defter Takip Adımları' /tmp/ed_ta.html | awk '{print ($1>0)?1:0}')"
TK=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ed_ta.html|head -1)
curl -s -b $J -c $J -L -o /dev/null -X POST "$B/tanimlar/edefter-adim-kaydet" \
  -d "csrf_beyanname=$TK" -d "kod=KASA_KONTROL" -d "ad=Kasa Kontrolü" -d "ikon=💵" -d "sira=45" -d "aktif=1"
ol "Yeni adım eklendi" "1" "$($MDB -e "SELECT COUNT(*) FROM edefter_adimlari WHERE kod='KASA_KONTROL'")"
ol "Çizelgede 7 adım var" "7" "$($MDB -e "SELECT COUNT(*) FROM edefter_adimlari WHERE aktif=1")"
curl -s -b $J "$B/edefter?yil=2026&ay=0" -o /tmp/ed_t2.html
ol "Yeni adım çizelgede görünüyor" "1" "$(grep -c 'Kasa Kontrolü' /tmp/ed_t2.html | awk '{print ($1>0)?1:0}')"
# Yeni adım eklenince ilerleme paydası büyür
R=$(aj $J "$B/edefter/adim" -d "takip_id=$TID" -d "adim_id=$(adimId BANKA_TEMIN)" -d "tamam=1"); echo "$R" > /tmp/ed_r.json
ol "Payda 7'ye çıktı" "7" "$(js /tmp/ed_r.json "['adim_toplam']")"
# HAZIR/ONAY silinemez
HID=$(adimId HAZIR)
curl -s -b $J -L "$B/tanimlar/edefter-adim-sil/$HID" -o /tmp/ed_sil.html
ol "HAZIR adımı silinemiyor" "1" "$($MDB -e "SELECT aktif FROM edefter_adimlari WHERE kod='HAZIR'")"
ol "Uyarı mesajı çıkıyor" "1" "$(grep -c 'kaldırılamaz' /tmp/ed_sil.html | awk '{print ($1>0)?1:0}')"
# Normal adım pasife alınabilir
KID=$(adimId KASA_KONTROL)
curl -s -b $J -L "$B/tanimlar/edefter-adim-sil/$KID" -o /dev/null
ol "Normal adım pasife alındı" "0" "$($MDB -e "SELECT aktif FROM edefter_adimlari WHERE kod='KASA_KONTROL'")"
ol "Pasif adım çizelgeden düştü" "6" "$($MDB -e "SELECT COUNT(*) FROM edefter_adimlari WHERE aktif=1")"

echo "=== 12) YETKİ ==="
giris musavir $JM
curl -s -b $JM "$B/edefter?yil=2026&ay=0" -o /tmp/ed_mus.html
MB=$($MDB -e "SELECT COUNT(*) FROM edefter_takip et JOIN mukellefler m ON m.id=et.mukellef_id WHERE m.musavir_id=2 AND YEAR(et.son_tarih)=2026")
ol "Müşavir yalnızca kendi kayıtları ($MB)" "$MB" "$(say /tmp/ed_mus.html)"
ol "Müşavir toplamı sistemden az" "1" "$([ "$(say /tmp/ed_mus.html)" -lt "$T26" ] && echo 1 || echo 0)"
# Başkasının kaydına adım işaretleyemez
R=$(aj $JM "$B/edefter/adim" -d "takip_id=$TID" -d "adim_id=$(adimId MIZAN)" -d "tamam=1")
ol "Yetkisiz kayda müdahale reddedildi" "1" "$(echo "$R" | grep -c 'erişemezsiniz')"
giris personel $J2 2>/dev/null; giris personel /tmp/ed_p.txt
curl -s -b /tmp/ed_p.txt "$B/edefter?yil=2026&ay=0" -o /tmp/ed_per.html
ol "Personel listeyi görüyor" "1" "$(grep -c 'E-Defter Berat Takibi' /tmp/ed_per.html | awk '{print ($1>0)?1:0}')"
ol "Personel adım işaretleyebiliyor" "1" \
  "$(grep -c 'ed-kutu' /tmp/ed_per.html | awk '{print ($1>0)?1:0}')"
ol "Oturumsuz erişim engelli" "302" "$(curl -s -o /dev/null -w '%{http_code}' "$B/edefter")"
ol "CSRF'siz POST engelli" "403" \
  "$(curl -s -b $J -o /dev/null -w '%{http_code}' -d "takip_id=$TID" -d "adim_id=1" -d "tamam=1" "$B/edefter/adim")"

echo "=== 13) SAYFALAR BOZULMADI ==="
giris admin $J
for u in "edefter?yil=2026&ay=0" panel "takip?yil=2026&ay=8" mukellefler "mukellefler/duzenle/1" \
         "evrak?yil=2026&ay=8" tanimlar/ayarlar tanimlar/edefter-adimlari raporlar; do
  c=$(curl -s -b $J -o /tmp/ed_s.html -w "%{http_code}" "$B/$u")
  ol "/$u HTTP 200" "200" "$c"
  ol "/$u hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Undefined variable|Unknown column' /tmp/ed_s.html | awk '{print ($1>0)?1:0}')"
done
ol "Mükellef kartında e-defter bölümü" "1" \
  "$(curl -s -b $J "$B/mukellefler/duzenle/1" | grep -c 'E-Defter Berat Takibi' | awk '{print ($1>0)?1:0}')"
ol "Menüde E-Defter Takip var" "1" \
  "$(curl -s -b $J "$B/panel" | grep -c 'E-Defter Takip' | awk '{print ($1>0)?1:0}')"

echo
echo "================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "================================================"
[ $k -eq 0 ] || exit 1
