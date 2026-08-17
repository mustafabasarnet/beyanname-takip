#!/bin/bash
# =====================================================================
#  ÖZET KART (SAYAÇ) REGRESYON TESTİ
#
#  Doğrulanan kusur:
#    Beyanname Takip ekranında özet kartları (Toplam/Gecikmiş/Hazır/
#    Onaylandı/Verilmeyecek) yalnızca YIL + MÜŞAVİR + DEFTER TİPİ
#    filtrelerini biliyordu. Kullanıcı "Beyanname Türü = KDV1" ve
#    "Ay = Ağustos" seçtiğinde liste 3 satıra düşerken "Onaylandı"
#    kartı tüm yılın toplamını (örn. 275) göstermeye devam ediyordu.
#
#  Kural:
#    Sayaçlar cizelgeSorgusu() üzerinden, listeyle AYNI filtreyle
#    hesaplanır. Yalnızca "durum" ve "gecikmis" hesaba katılmaz; böylece
#    Bekliyor+Hazır+Onaylandı+Verilmeyecek = Toplam eşitliği korunur ve
#    durum süzgeci açıkken bile dağılım görünür kalır.
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin ve musavir kullanıcıları (şifre Test1234) var.
#            musavir kullanıcısı musavir_id=2'ye bağlı olmalı.
#  Not: Test kendi verisini kurar; başka testlerden bağımsız çalışır.
#  Kullanım:  bash tests/ozet_kart_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/ozk.txt
JM=/tmp/ozk_m.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/ozk_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ozk_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# ---------------------------------------------------------------
#  Test verisi: 3 mükellef x 3 tür (KDV1_A=1, MUHSGK_A=4, DAMGA=11)
#  x 12 ay = 108 kayıt. Ağustos ayında tür bazında BELİRGİN FARKLI
#  durum dağılımı kurulur ki "tür seçince sayaç değişiyor mu"
#  sorusu net ölçülebilsin.
# ---------------------------------------------------------------
veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES
 (1,'SMMM','Ali Yılmaz','Yılmaz',1),(2,'SMMM','Veli Demir','Demir',1);
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','ÖZKAN İNŞAAT LTD. ŞTİ.','tuzel','1112223334',NULL,'bilanco','2019-01-01',1),
 (2,1,'M002','AYŞE ÇELİK','gercek',NULL,'22233344455','isletme','2020-06-15',1),
 (3,2,'M003','DEMİR TİCARET A.Ş.','tuzel','5556667778',NULL,'bilanco','2018-01-01',1);"

python3 - <<'PY' > /tmp/ozk_veri.sql
import calendar
rows=[]
for muk in (1,2,3):
    for tur in (1,4,11):
        for ay in range(1,13):                 # son tarih ayı 2026-01..12
            d_ay  = ay-1 if ay>1 else 12
            d_yil = 2026 if ay>1 else 2025
            son   = f"2026-{ay:02d}-28"
            bas   = f"{d_yil}-{d_ay:02d}-01"
            bit   = f"{d_yil}-{d_ay:02d}-{calendar.monthrange(d_yil,d_ay)[1]:02d}"
            if ay == 8:                        # ölçüm ayı: tür bazlı farklı
                if   tur == 1:  d = 'ONAYLANDI' if muk in (1,2) else 'HAZIR'
                elif tur == 4:  d = 'HAZIR' if muk == 1 else 'BEKLIYOR'
                else:           d = 'VERILMEYECEK' if muk == 3 else 'ONAYLANDI'
            elif ay < 8:        d = 'ONAYLANDI'
            else:               d = 'BEKLIYOR'
            rows.append(f"({muk},{tur},{d_yil},{d_ay},'D{d_ay}','{bas}','{bit}','{son}','{son}','{d}',NOW(),NOW())")
print("INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,"
      "donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,created_at,updated_at) VALUES")
print(',\n'.join(rows)+';')
PY
$MDBR < /tmp/ozk_veri.sql
}

# Bir kartın değerini HTML'den okur:  kart <dosya> <etiket>
kart(){ python3 - "$1" "$2" <<'PY'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
i=h.find('stat-grid'); j=h.find('<!-- ============ TOPLU')
blok=h[i:j if j>i else len(h)]
for m in re.finditer(r'<div class="etiket">([^<]+)</div>\s*<div class="deger">([\d.]+)',blok):
    if m.group(1).strip()==sys.argv[2]:
        print(m.group(2).replace('.','')); break
else: print('YOK')
PY
}
# Listedeki satır sayısı
satir(){ grep -oE '<tr class="[^"]*">' "$1" | wc -l | tr -d ' '; }
# DB'den sayım
db(){ $MDB -e "$1"; }

veriKur
giris admin $J

echo "=== 1) TÜR + AY FİLTRESİ SAYACA YANSIYOR MU (asıl kusur) ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&tur_id=1&mod=beyan" -o /tmp/ozk_kdv.html
YIL_ONAY=$(db "SELECT COUNT(*) FROM beyanname_takip WHERE YEAR(son_tarih)=2026 AND durum='ONAYLANDI'")
ol "Yıl geneli Onaylandı (kusurlu sürümün gösterdiği) 67" "67" "$YIL_ONAY"
ol "Ağustos+KDV1 Onaylandı yıl toplamı DEĞİL" "1" "$([ "$(kart /tmp/ozk_kdv.html Onaylandı)" != "$YIL_ONAY" ] && echo 1 || echo 0)"
ol "Ağustos+KDV1 Toplam=3"        "3" "$(kart /tmp/ozk_kdv.html Toplam)"
ol "Ağustos+KDV1 Onaylandı=2"     "2" "$(kart /tmp/ozk_kdv.html Onaylandı)"
ol "Ağustos+KDV1 Hazır=1"         "1" "$(kart /tmp/ozk_kdv.html Hazır)"
ol "Ağustos+KDV1 Bekliyor=0"      "0" "$(kart /tmp/ozk_kdv.html Bekliyor)"
ol "Ağustos+KDV1 Verilmeyecek=0"  "0" "$(kart /tmp/ozk_kdv.html Verilmeyecek)"
ol "Ağustos+KDV1 liste 3 satır"   "3" "$(satir /tmp/ozk_kdv.html)"

echo "=== 2) BAŞKA TÜR SEÇİNCE SAYAÇ DEĞİŞİYOR MU ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&tur_id=4&mod=beyan" -o /tmp/ozk_sgk.html
ol "Ağustos+MUHSGK Toplam=3"       "3" "$(kart /tmp/ozk_sgk.html Toplam)"
ol "Ağustos+MUHSGK Bekliyor=2"     "2" "$(kart /tmp/ozk_sgk.html Bekliyor)"
ol "Ağustos+MUHSGK Hazır=1"        "1" "$(kart /tmp/ozk_sgk.html Hazır)"
ol "Ağustos+MUHSGK Onaylandı=0"    "0" "$(kart /tmp/ozk_sgk.html Onaylandı)"

curl -s -b $J "$B/takip?yil=2026&ay=8&tur_id=11&mod=beyan" -o /tmp/ozk_dmg.html
ol "Ağustos+DAMGA Onaylandı=2"      "2" "$(kart /tmp/ozk_dmg.html Onaylandı)"
ol "Ağustos+DAMGA Verilmeyecek=1"   "1" "$(kart /tmp/ozk_dmg.html Verilmeyecek)"
ol "Ağustos+DAMGA Hazır=0"          "0" "$(kart /tmp/ozk_dmg.html Hazır)"
ol "İki tür farklı sonuç veriyor"   "1" \
   "$([ "$(kart /tmp/ozk_sgk.html Onaylandı)" != "$(kart /tmp/ozk_dmg.html Onaylandı)" ] && echo 1 || echo 0)"

echo "=== 3) TÜR=TÜMÜ ESKİ DAVRANIŞI KORUYOR MU ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan" -o /tmp/ozk_tumtur.html
ol "Ağustos+Tümü Toplam=9"       "9" "$(kart /tmp/ozk_tumtur.html Toplam)"
ol "Ağustos+Tümü Onaylandı=4"    "4" "$(kart /tmp/ozk_tumtur.html Onaylandı)"
ol "Ağustos+Tümü Hazır=2"        "2" "$(kart /tmp/ozk_tumtur.html Hazır)"
ol "Ağustos+Tümü = tür toplamı"  "9" \
   "$(( $(kart /tmp/ozk_kdv.html Toplam) + $(kart /tmp/ozk_sgk.html Toplam) + $(kart /tmp/ozk_dmg.html Toplam) ))"

curl -s -b $J "$B/takip?yil=2026&ay=0&mod=beyan" -o /tmp/ozk_tumay.html
ol "Tüm aylar Toplam=108"     "108" "$(kart /tmp/ozk_tumay.html Toplam)"
ol "Tüm aylar Onaylandı=67"    "67" "$(kart /tmp/ozk_tumay.html Onaylandı)"
ol "Tüm aylar Bekliyor=38"     "38" "$(kart /tmp/ozk_tumay.html Bekliyor)"

echo "=== 4) TOPLAM = DURUMLARIN TOPLAMI (tutarlılık) ==="
for f in /tmp/ozk_kdv.html /tmp/ozk_sgk.html /tmp/ozk_dmg.html /tmp/ozk_tumtur.html /tmp/ozk_tumay.html; do
  T=$(kart $f Toplam)
  S=$(( $(kart $f Bekliyor) + $(kart $f Hazır) + $(kart $f Onaylandı) + $(kart $f Verilmeyecek) ))
  ol "$(basename $f): $T = B+H+O+V" "$T" "$S"
done

echo "=== 5) SAYAÇLAR VERİTABANIYLA BİREBİR ==="
for tur in 1 4 11; do
  for d in BEKLIYOR:Bekliyor HAZIR:Hazır ONAYLANDI:Onaylandı VERILMEYECEK:Verilmeyecek; do
    kod=${d%%:*}; etk=${d##*:}
    curl -s -b $J "$B/takip?yil=2026&ay=8&tur_id=$tur&mod=beyan" -o /tmp/ozk_x.html
    bekl=$(db "SELECT COUNT(*) FROM beyanname_takip WHERE YEAR(son_tarih)=2026 AND MONTH(son_tarih)=8 AND beyanname_turu_id=$tur AND durum='$kod'")
    ol "tür$tur $etk = DB($bekl)" "$bekl" "$(kart /tmp/ozk_x.html $etk)"
  done
done

echo "=== 6) DURUM SÜZGECİ AÇIKKEN KARTLAR DAĞILIMI GÖSTERİR ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&tur_id=1&mod=beyan&durum=ONAYLANDI" -o /tmp/ozk_dur.html
ol "Durum=ONAYLANDI iken Toplam hâlâ 3" "3" "$(kart /tmp/ozk_dur.html Toplam)"
ol "Durum=ONAYLANDI iken Hazır 0 DEĞİL"  "1" "$(kart /tmp/ozk_dur.html Hazır)"
ol "Durum=ONAYLANDI iken liste 2 satır"  "2" "$(satir /tmp/ozk_dur.html)"
ol "Süzgeç notu görünüyor" "1" "$(grep -c 'ozet-not' /tmp/ozk_dur.html | head -1 | awk '{print ($1>0)?1:0}')"

curl -s -b $J "$B/takip?yil=2026&ay=8&tur_id=1&mod=beyan&gecikmis=1" -o /tmp/ozk_gec.html
ol "Gecikmiş süzgeci açıkken Toplam hâlâ 3" "3" "$(kart /tmp/ozk_gec.html Toplam)"
ol "Gecikmiş süzgeci açıkken Onaylandı 2"   "2" "$(kart /tmp/ozk_gec.html Onaylandı)"

echo "=== 7) KARTLAR TIKLANABİLİR FİLTRE DÜĞMESİ ==="
ol "Onaylandı kartı durum bağlantısı taşıyor" "1" \
   "$(grep -c 'durum=ONAYLANDI' /tmp/ozk_kdv.html | awk '{print ($1>0)?1:0}')"
ol "Hazır kartı durum bağlantısı taşıyor" "1" \
   "$(grep -c 'durum=HAZIR' /tmp/ozk_kdv.html | awk '{print ($1>0)?1:0}')"
ol "Gecikmiş kartı gecikmis=1 taşıyor" "1" \
   "$(grep -c 'gecikmis=1' /tmp/ozk_kdv.html | awk '{print ($1>0)?1:0}')"
ol "Kart bağlantısı tür filtresini koruyor" "1" \
   "$(grep -oE 'takip\?[^\"]*durum=ONAYLANDI' /tmp/ozk_kdv.html | head -1 | grep -c 'tur_id=1')"
ol "Kart bağlantısı ay filtresini koruyor" "1" \
   "$(grep -oE 'takip\?[^\"]*durum=ONAYLANDI' /tmp/ozk_kdv.html | head -1 | grep -c 'ay=8')"
ol "Aktif kart 'secili' sınıfı alıyor" "1" \
   "$(grep -c 'stat yesil secili' /tmp/ozk_dur.html | awk '{print ($1>0)?1:0}')"
ol "Pasif kartlar 'sonuk' sınıfı alıyor" "1" \
   "$(grep -c 'sonuk' /tmp/ozk_dur.html | awk '{print ($1>0)?1:0}')"
ol "Süzgeç yokken Toplam kartı secili" "1" \
   "$(grep -c 'class="stat  secili"' /tmp/ozk_kdv.html | awk '{print ($1>0)?1:0}')"

echo "=== 8) 'TÜM AYLAR' KART BAĞLANTISINDA KAYBOLMUYOR (ay=0 tuzağı) ==="
# array_filter ay=null'ı siler; adreste ay yoksa ayBelirle() bugünün ayına döner.
LINK=$(grep -oE 'takip\?[^"]*durum=ONAYLANDI' /tmp/ozk_tumay.html | head -1)
ol "Tüm Aylar bağlantısı ay=0 içeriyor" "1" "$(echo "$LINK" | grep -c 'ay=0')"
curl -s -b $J "$B/takip?yil=2026&ay=0&mod=beyan&durum=ONAYLANDI" -o /tmp/ozk_tumay2.html
ol "Tıklayınca ay Tüm Aylar kalıyor" "1" \
   "$(grep -cE '<option value="0"[^>]*selected' /tmp/ozk_tumay2.html | awk '{print ($1>0)?1:0}')"
ol "Tüm Aylar+Onaylandı toplamı 108" "108" "$(kart /tmp/ozk_tumay2.html Toplam)"

echo "=== 9) DEFTER TİPİ VE ARAMA DA SAYACA YANSIYOR ==="
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan&defter_tipi=isletme" -o /tmp/ozk_def.html
BEKL=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id WHERE YEAR(bt.son_tarih)=2026 AND MONTH(bt.son_tarih)=8 AND m.defter_tipi='isletme'")
ol "Defter tipi=işletme Toplam=DB($BEKL)" "$BEKL" "$(kart /tmp/ozk_def.html Toplam)"

# Not: curl ham UTF-8 karakterli URL'yi reddeder (kod 000). Tarayıcı adres
# çubuğunda otomatik yüzde-kodlama yapar; testte de kodlanmış hali kullanılır.
curl -s -b $J "$B/takip?yil=2026&ay=8&mod=beyan&q=AY%C5%9EE" -o /tmp/ozk_ara.html
ARABEKL=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id WHERE YEAR(bt.son_tarih)=2026 AND MONTH(bt.son_tarih)=8 AND m.unvan LIKE '%AYŞE%'")
ol "Arama 'AYŞE' Toplam=DB($ARABEKL)" "$ARABEKL" "$(kart /tmp/ozk_ara.html Toplam)"

echo "=== 10) DÖNEM MODU (mod=donem) ==="
curl -s -b $J "$B/takip?yil=2026&ay=7&tur_id=1&mod=donem" -o /tmp/ozk_don.html
DONBEKL=$(db "SELECT COUNT(*) FROM beyanname_takip WHERE yil=2026 AND MONTH(donem_bitis)=7 AND beyanname_turu_id=1")
ol "Dönem modu Temmuz+KDV1 Toplam=DB($DONBEKL)" "$DONBEKL" "$(kart /tmp/ozk_don.html Toplam)"
ol "Dönem modu bağlantısı mod=donem koruyor" "1" \
   "$(grep -oE 'takip\?[^"]*durum=ONAYLANDI' /tmp/ozk_don.html | head -1 | grep -c 'mod=donem')"

echo "=== 11) YETKİ KAPSAMI SIZMIYOR ==="
# Not: 'musavir' kullanıcısının bağlı olduğu müşavir id'si testler arasında
# değişebiliyor (ice_aktar_testi 1, sistem_testi 2 bekliyor). Bu test hangi
# değer olursa olsun çalışsın diye beklenen sayı DB'den dinamik okunur.
giris musavir $JM
MUSID=$(db "SELECT COALESCE((SELECT musavir_id FROM kullanici_musavirleri WHERE kullanici_id=(SELECT id FROM kullanicilar WHERE kullanici_adi='musavir') LIMIT 1),(SELECT musavir_id FROM kullanicilar WHERE kullanici_adi='musavir'))")
curl -s -b $JM "$B/takip?yil=2026&ay=0&mod=beyan" -o /tmp/ozk_mus.html
MUSBEKL=$(db "SELECT COUNT(*) FROM beyanname_takip bt JOIN mukellefler m ON m.id=bt.mukellef_id WHERE m.musavir_id=$MUSID AND YEAR(bt.son_tarih)=2026")
ol "Müşavir (id=$MUSID) yalnızca kendi kayıtlarını sayıyor ($MUSBEKL)" "$MUSBEKL" "$(kart /tmp/ozk_mus.html Toplam)"
ol "Müşavir toplamı sistem toplamından küçük" "1" \
   "$([ "$(kart /tmp/ozk_mus.html Toplam)" -lt 108 ] && echo 1 || echo 0)"

echo "=== 12) DİĞER SAYFALAR BOZULMADI (ozet() eski imza) ==="
for u in panel raporlar "takip/excel?yil=2026" "takip/yazdir?yil=2026"; do
  c=$(curl -s -b $J -o /tmp/ozk_p.html -w "%{http_code}" "$B/$u")
  ol "/$u HTTP 200" "200" "$c"
  ol "/$u hata yok" "0" "$(grep -cE 'ErrorException|Fatal error|Call to undefined member|Undefined variable' /tmp/ozk_p.html | awk '{print ($1>0)?1:0}')"
done

echo
echo "================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "================================================"
[ $k -eq 0 ] || exit 1
