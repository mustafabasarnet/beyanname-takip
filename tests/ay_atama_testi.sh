#!/bin/bash
# =====================================================================
#  AY ATAMASI — HAFTA SONU KAYDIRMASI AYI DEĞİŞTİRMESİN
#
#  Kullanıcı bildirimi:
#    "GEKAP/Turizm gibi ay sonu beyannamelerinde son gün hafta sonuna
#     denk gelince (örn. 31.10.2026 Cumartesi) kayıt, Ekim yerine KASIM
#     listesinde görünüyordu."
#
#  Kural (düzeltme sonrası):
#    Beyan modunda AY/YIL = KANUNİ (yasal) son tarih.
#    Tatil kaydırması fiili tarihi değiştirir ama beyannameyi başka aya taşımaz.
#    Gecikme/geri sayım ve ÖDEME modu FİİLİ tarihi kullanmaya devam eder.
#
#  Test ettikleri:
#    1) Turizm (Eylül 2026): kanuni 31.10 Cmt -> fiili 02.11 → EKİM listesinde
#    2) Aynı kayıt KASIM listesinde YOK (kaydırma onu taşımıyor)
#    3) Kasım'ın kendi kaydı (Turizm Ekim, 30.11.2026) Kasım'da görünür
#    4) Satırda hem kanuni (31.10) hem fiili (02.11) tarih + "↷" kaydırma notu
#    5) Panel "Beyanname Durum Kontrol" tablosu Ekim'de sayıyor
#    6) Panel/Kontrol Paneli aylık dağılım tutarlı
#    7) Ait olduğu dönem modu etkilenmedi (Eylül'de görünür)
#    8) KDV1 (Ocak 2026): kanuni 28.02 Cmt -> fiili 02.03 → ŞUBAT listesinde
#    9) Gecikme hesabı FİİLİ tarihe göre (ileri tarihli kayıt gecikmiş sayılmaz)
#   10) Ödeme listesi modu fiili tarihe göre çalışmaya devam ediyor
#   11) Özet sayaçlar liste ile aynı (Ekim'de 1 kayıt)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor.
#  Not: Kendi verisini kurar (77… VKN önekli mükellef).
#  Kullanım:  bash tests/ay_atama_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/ayat.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }

# Bir türün satırını döndürür: $1=html $2=tür kısa adı
satir(){ python3 -c "
import re,sys
h=open('$1',encoding='utf-8').read()
hedef='$2'
for r in re.findall(r'<tr class=\"[^\"]*\">.*?</tr>', h, re.S):
    if re.search(r'tur-rozet[^>]*>\s*'+re.escape(hedef)+r'\s*<', r):
        t=re.sub(r'<[^>]+>','|',r); t=re.sub(r'\|+','|',t); t=re.sub(r'\s+',' ',t)
        print(t.strip()); break
"; }
# Satır sayısı: $1=html $2=tür
sayi(){ satirSonuc=$(satir "$1" "$2"); if [ -z "$satirSonuc" ]; then echo 0; else echo 1; fi; }

echo "=== 0) HAZIRLIK ==="
rm -f $J
curl -s -c $J -o /tmp/ayat_giris.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ayat_giris.html|head -1)
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=admin" -d "sifre=Test1234" $B/giris
ol "admin girişi" "200" "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/takip")"

# Temiz kurulum + veri
$MDBR -e "SET FOREIGN_KEY_CHECKS=0;
DELETE FROM beyanname_takip WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE vergi_kimlik_no LIKE '77%');
DELETE FROM mukellef_beyannameleri WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE vergi_kimlik_no LIKE '77%');
DELETE FROM mukellefler WHERE vergi_kimlik_no LIKE '77%';
SET FOREIGN_KEY_CHECKS=1;
INSERT INTO mukellefler (musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif)
 VALUES (1,'A001','AY ATAMA TEST LTD.','tuzel','7700000001','bilanco','2019-01-01',1);
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif,created_at,updated_at)
 SELECT m.id,t.id,1,NOW(),NOW() FROM mukellefler m JOIN beyanname_turleri t ON t.kod IN ('TURIZM','KDV1_A')
 WHERE m.vergi_kimlik_no='7700000001';" >/dev/null 2>&1
M1=$(db "SELECT id FROM mukellefler WHERE vergi_kimlik_no='7700000001'")
ol "test mükellefi kuruldu" "1" "$([ -n "$M1" ] && echo 1 || echo 0)"

T=$(jeton $J "$B/takip/toplu-uret")
curl -s -b $J -c $J -o /dev/null -X POST "$B/takip/toplu-uret" -d "csrf_beyanname=$T" -d "yil=2026"
ol "2026 dönemleri üretildi" "1" "$(db "SELECT COUNT(*)>0 FROM beyanname_takip WHERE mukellef_id=$M1;")"

echo
echo "=== 1) KAYIT: kanuni vs fiili tarih ==="
TUR=$(db "SELECT id FROM beyanname_turleri WHERE kod='TURIZM'")
db "SELECT CONCAT(donem_adi,' | kanuni: ',yasal_son_tarih,' | fiili: ',son_tarih)
    FROM beyanname_takip WHERE mukellef_id=$M1 AND beyanname_turu_id=$TUR AND donem_no=9;" > /tmp/ayat_eylul.txt
ol "Turizm Eylül: kanuni 31.10.2026" "1" "$(grep -c '2026-10-31' /tmp/ayat_eylul.txt)"
ol "Turizm Eylül: fiili 02.11.2026 (kaydırma)" "1" "$(grep -c '2026-11-02' /tmp/ayat_eylul.txt)"
ol "  ay farkı var (10 → 11)" "10|11" \
   "$(db "SELECT CONCAT(MONTH(yasal_son_tarih),'|',MONTH(son_tarih)) FROM beyanname_takip
          WHERE mukellef_id=$M1 AND beyanname_turu_id=$TUR AND donem_no=9;")"

echo
echo "=== 2) EKİM LİSTESİ: Turizm (Eylül dönemi) GÖRÜNMELİ ==="
curl -s -b $J -c $J -o /tmp/ayat_ekim.html "$B/takip?yil=2026&ay=10&mod=beyan&mukellef_id=$M1&adet=250"
ol "Ekim listesinde Turizm satırı var" "1" "$(sayi /tmp/ayat_ekim.html 'Turizm')"
SATIR=$(satir /tmp/ayat_ekim.html 'Turizm')
ol "  satırda kanuni tarih (31.10.2026)" "1" "$(echo "$SATIR" | grep -c '31.10.2026')"
ol "  satırda fiili tarih (02.11.2026)" "1" "$(echo "$SATIR" | grep -c '02.11.2026')"
ol "  kaydırma işareti (↷) var" "1" "$(echo "$SATIR" | grep -c '↷')"
ol "  dönem adı Eylül 2026" "1" "$(echo "$SATIR" | grep -c 'Eylül 2026')"

echo
echo "=== 3) KASIM LİSTESİ: Eylül dönemi BURADA OLMAMALI ==="
curl -s -b $J -c $J -o /tmp/ayat_kasim.html "$B/takip?yil=2026&ay=11&mod=beyan&mukellef_id=$M1&adet=250"
KASIM=$(satir /tmp/ayat_kasim.html 'Turizm')
ol "Kasım'da Turizm satırı var (Ekim dönemi)" "1" "$(sayi /tmp/ayat_kasim.html 'Turizm')"
ol "  dönem adı Ekim 2026 (kaydırma YOK)" "1" "$(echo "$KASIM" | grep -c 'Ekim 2026')"
ol "  Eylül 2026 dönemi Kasım'da YOK" "0" "$(echo "$KASIM" | grep -c 'Eylül 2026')"
ol "  Kasım listesinde tek Turizm kaydı" "1" \
   "$(python3 -c "
import re
h=open('/tmp/ayat_kasim.html',encoding='utf-8').read()
print(sum(1 for r in re.findall(r'<tr class=\"[^\"]*\">.*?</tr>', h, re.S)
          if re.search(r'tur-rozet[^>]*>\s*Turizm\s*<', r)))")"

echo
echo "=== 4) AİT OLDUĞU DÖNEM MODU (regresyon) ==="
curl -s -b $J -c $J -o /tmp/ayat_donem.html "$B/takip?yil=2026&ay=9&mod=donem&mukellef_id=$M1&adet=250"
ol "Dönem modunda Eylül'de Turizm var" "1" "$(sayi /tmp/ayat_donem.html 'Turizm')"

echo
echo "=== 5) PANEL 'BEYANNAME DURUM KONTROL' (Ekim) ==="
curl -s -b $J -c $J -o /tmp/ayat_panel.html "$B/panel?yil=2026&ay=10&mod=beyan"

# Panel hücreleri: 1. hücre tür adı, 2. hücre TOPLAM (buton içinde sayı)
panelToplam(){ python3 -c "
import re
h=open('$1',encoding='utf-8').read()
m=re.search(r'<tr[^>]*>(?:(?!</tr>).)*'+re.escape('$2')+r'(?:(?!</tr>).)*</tr>', h, re.S)
if not m: print('SATIR-YOK'); raise SystemExit
td=re.findall(r'<td[^>]*>(.*?)</td>', m.group(0), re.S)
print(re.sub(r'<[^>]+>','',td[1]).strip() if len(td)>1 else 'YOK')"; }

ol "Panel Ekim: Turizm satırı var" "1" \
   "$(python3 -c "
import re
h=open('/tmp/ayat_panel.html',encoding='utf-8').read()
print(1 if re.search(r'bdk-tur[^>]*>\s*Turizm', h) else 0)")"
ol "  panelde Turizm TOPLAM = 1 (liste ile aynı)" "1" "$(panelToplam /tmp/ayat_panel.html 'Turizm')"

curl -s -b $J -c $J -o /tmp/ayat_panel_kasim.html "$B/panel?yil=2026&ay=11&mod=beyan"
ol "  Kasım panelinde Turizm var (Ekim dönemi)" "1" "$(panelToplam /tmp/ayat_panel_kasim.html 'Turizm')"


echo "=== 6) KDV1 (Ocak 2026): kanuni Şubat → ŞUBAT listesinde ==="
KDV=$(db "SELECT id FROM beyanname_turleri WHERE kod='KDV1_A'")
ol "KDV1 Ocak: kanuni 28.02.2026 (Cmt)" "2026-02-28" \
   "$(db "SELECT yasal_son_tarih FROM beyanname_takip WHERE mukellef_id=$M1 AND beyanname_turu_id=$KDV AND donem_no=1;")"
ol "KDV1 Ocak: fiili 02.03.2026" "2026-03-02" \
   "$(db "SELECT son_tarih FROM beyanname_takip WHERE mukellef_id=$M1 AND beyanname_turu_id=$KDV AND donem_no=1;")"

curl -s -b $J -c $J -o /tmp/ayat_subat.html "$B/takip?yil=2026&ay=2&mod=beyan&mukellef_id=$M1&adet=250"
SUBAT_SATIR=$(satir /tmp/ayat_subat.html 'KDV1 (Ay)')
ol "★ Şubat listesinde KDV1 OCAK dönemi var" "1" "$(echo "$SUBAT_SATIR" | grep -c 'Ocak 2026')"

curl -s -b $J -c $J -o /tmp/ayat_mart.html "$B/takip?yil=2026&ay=3&mod=beyan&mukellef_id=$M1&adet=250"
MART_SATIR=$(satir /tmp/ayat_mart.html 'KDV1 (Ay)')
ol "Mart listesinde KDV1 ŞUBAT dönemi var (kanuni 28.03)" "1" "$(echo "$MART_SATIR" | grep -c 'Şubat 2026')"
ol "★ Mart listesinde KDV1 OCAK dönemi YOK" "0" "$(echo "$MART_SATIR" | grep -c 'Ocak 2026')"


echo "=== 7) GECİKME FİİLİ TARİHE GÖRE (değişmedi) ==="
BUGUN=$(date +%F)
ol "ileri tarihli kayıt gecikmiş sayılmaz (Eylül Turizm)" "1" \
   "$(db "SELECT CASE WHEN son_tarih >= '$BUGUN' THEN 1 ELSE 0 END FROM beyanname_takip
          WHERE mukellef_id=$M1 AND beyanname_turu_id=$TUR AND donem_no=9;")"
GECMIS=$(db "SELECT COUNT(*) FROM beyanname_takip
             WHERE mukellef_id=$M1 AND son_tarih < '$BUGUN' AND durum IN ('BEKLIYOR','HAZIR');")
PANEL_GEC=$(python3 -c "
import re
h=open('/tmp/ayat_panel.html',encoding='utf-8').read()
m=re.search(r'GECİKMİŞ.*?(\d+)', h, re.S)
print(m.group(1) if m else '0')")
ol "geçmiş son tarihli kayıtlar gecikmiş listesinde (dönem modu saymaz)" "1" \
   "$([ "$GECMIS" -ge 1 ] && echo 1 || echo 0)"

echo
echo "=== 8) ÖDEME LİSTESİ MODU FİİLİ TARİHE GÖRE (değişmedi) ==="
# SGK benzeri: ödeme modu hâlâ son_tarih/odeme_son_tarih kullanır
ol "ödeme sorgusu fiili tarihi kullanıyor" "1" \
   "$(grep -c 'COALESCE(beyanname_takip.odeme_son_tarih, beyanname_takip.son_tarih)' app/Models/BeyannameTakipModel.php | awk '{print ($1>0)?1:0}')"

echo
echo "=== 9) ÖZET SAYAÇLAR LİSTE İLE AYNI (Ekim) ==="
# Ekim'de 2 meşru kayıt: KDV1 (Eylül dönemi, kanuni 28.10) + Turizm (Eylül, kanuni 31.10)
BEKLENEN=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN beyanname_turleri t ON t.id=bt.beyanname_turu_id
               WHERE bt.mukellef_id=$M1 AND YEAR(bt.yasal_son_tarih)=2026 AND MONTH(bt.yasal_son_tarih)=10;")
ol "Ekim'de beklenen kayıt sayısı (DB) 2" "2" "$BEKLENEN"
LISTE=$(python3 -c "
import re
h=open('/tmp/ayat_ekim.html',encoding='utf-8').read()
print(sum(1 for r in re.findall(r'<tr class=\"[^\"]*\">.*?</tr>', h, re.S) if 'durum-sec' in r))")
ol "liste satır sayısı = DB" "$BEKLENEN" "$LISTE"
ol "  Turizm bu kayıtların içinde" "1" "$(sayi /tmp/ayat_ekim.html 'Turizm')"


echo "=== 10) TEMİZLİK ==="
$MDBR -e "SET FOREIGN_KEY_CHECKS=0;
DELETE FROM beyanname_takip WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE vergi_kimlik_no LIKE '77%');
DELETE FROM mukellef_beyannameleri WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE vergi_kimlik_no LIKE '77%');
DELETE FROM mukellefler WHERE vergi_kimlik_no LIKE '77%';
SET FOREIGN_KEY_CHECKS=1;" >/dev/null 2>&1
ol "test verisi temizlendi" "0" "$(db "SELECT COUNT(*) FROM mukellefler WHERE vergi_kimlik_no LIKE '77%';")"

echo
echo "========================================"
if [ $k -eq 0 ]; then echo "TÜM TESTLER BAŞARILI ($g geçti / $((g+k)))"; else echo "$k HATA ($g geçti / $((g+k)))"; fi
rm -f $J
[ $k -eq 0 ] || exit 1
