#!/bin/bash
# =====================================================================
#  MAKBUZ TAKİP — FORMDAN MAKBUZ GİRİŞİ TESTİ
#
#  Kullanıcı isteği:
#    "Makbuz Takip ekranında makbuz şu an sadece Excel ile eklenebiliyor;
#     önceki hesaplamalara ve yapıya dokunmadan formdan da girilebilsin."
#
#  Test ettikleri:
#    1) /makbuz ekranında "➕ Makbuz Ekle" butonu + modal formu var
#    2) Form ile kayıt: tutarlar sunucuda hesaplanır (net = brüt − stopaj + KDV)
#    3) Stopaj/KDV boş bırakılırsa oranlardan otomatik hesaplanır
#    4) Elle girilen stopaj/KDV aynen korunur
#    5) Yıl, makbuz tarihinden türetilir (boş bırakılırsa)
#    6) donus=liste → /makbuz listesine döner (mükellef detayına değil)
#    7) Mükerrer makbuz no engellenir; "zorla=1" ile kaydedilir
#    8) Girilen makbuz çizelgeye yansır (kesilen = brüt toplamı; hesaplama değişmedi)
#    9) CSRF'siz POST reddedilir
#   10) Yetki: başka müşavirin mükellefine form girişi yapılamaz (personel zaten kapalı)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor.
#  Kullanım:  bash tests/makbuz_form_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/mkf_admin.txt
JM=/tmp/mkf_musavir.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/mkf_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mkf_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }
# Çizelge HTML'inden bir mükellefin satırındaki "Kesilen" tutarını okur
cizelgeKesilen(){ python3 -c "
import re,sys
h=open('$1',encoding='utf-8').read()
m=re.search(r'<tr[^>]*data-mukellef=\"$2\".*?</tr>', h, re.S)
if not m: print('SATIR YOK'); sys.exit()
t=m.group(0)
# Kesilen sütunu 3. <td> (mükellef, ücret, kesilen)
tds=re.findall(r'<td[^>]*>(.*?)</td>', t, re.S)
hucre=re.sub(r'<[^>]+>','',tds[2]).strip()
print(hucre)"; }

# POST: $1=oturum $2=veri... → yönlendirme adresi /tmp/mkf_hedef.txt
kaydet(){ local jar="$1"; shift; local t; t=$(jeton "$jar" "$B/makbuz")
  curl -s -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}|%{redirect_url}' \
    -d "csrf_beyanname=$t" "$@" "$B/makbuz/kaydet" > /tmp/mkf_hedef.txt; }
hedef(){ cut -d'|' -f2 /tmp/mkf_hedef.txt | sed "s|$B||"; }
kod(){ cut -d'|' -f1 /tmp/mkf_hedef.txt; }

YIL=$(date +%Y)
BUGUN=$(date +%F)
STOPAJ_ORAN=$(db "SELECT deger FROM ayarlar WHERE anahtar='makbuz_stopaj_oran' LIMIT 1;")
KDV_ORAN=$(db "SELECT deger FROM ayarlar WHERE anahtar='makbuz_kdv_oran' LIMIT 1;")
echo "=== 0) HAZIRLIK ==="
echo "  ayar: stopaj %$STOPAJ_ORAN · KDV %$KDV_ORAN · yıl $YIL"
girisYap admin $J
girisYap musavir $JM
# Yetki testi ortama bağımlı olmasın: 'musavir' kullanıcısının ERİŞEMEDİĞİ
# bir müşavir bulunur ve altına geçici bir mükellef konur.
MUS_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='musavir' LIMIT 1;")
IZIN=$(db "SELECT IFNULL(GROUP_CONCAT(musavir_id), '') FROM kullanici_musavirleri WHERE kullanici_id=$MUS_ID;")
if [ -z "$IZIN" ]; then
  # Köprü tablo boşsa birincil müşavir (uygulamadaki geriye dönük uyumluluk)
  IZIN=$(db "SELECT IFNULL(musavir_id, 1) FROM kullanicilar WHERE id=$MUS_ID;")
fi
[ -z "$IZIN" ] && IZIN=1
DIS_MUS=$(db "SELECT id FROM musavirler WHERE id NOT IN ($IZIN) ORDER BY id LIMIT 1;")
[ -z "$DIS_MUS" ] && DIS_MUS=2

MK=$(db "SELECT id FROM mukellefler WHERE musavir_id IN ($IZIN) AND deleted_at IS NULL ORDER BY id LIMIT 1;")
MK2=$(db "SELECT id FROM mukellefler WHERE musavir_id=$DIS_MUS AND deleted_at IS NULL ORDER BY id LIMIT 1;")
if [ -z "$MK2" ]; then
  db "INSERT INTO mukellefler (musavir_id, unvan, mukellef_tipi, vergi_kimlik_no, ise_baslama_tarihi)
      VALUES ($DIS_MUS, 'MKF TEST YETKİSİZ', 'tuzel', '8888888888', '2026-01-01');" >/dev/null 2>&1
  MK2=$(db "SELECT id FROM mukellefler WHERE vergi_kimlik_no='8888888888' LIMIT 1;")
fi
echo "  kapsam: musavir kullanıcısı → müşavir [$IZIN] · yetkisiz mükellef müşavir $DIS_MUS"
ol "test mükellefleri bulundu (kapsam içi + kapsam dışı)" "1" \
   "$([ -n "$MK" ] && [ -n "$MK2" ] && echo 1 || echo 0)"

db "DELETE FROM makbuzlar WHERE aciklama LIKE 'MKF-TEST%';" >/dev/null 2>&1

echo
echo "=== 1) EKRANDA FORM VAR MI? ==="
curl -s -b $J -c $J -o /tmp/mkf_index.html "$B/makbuz?yil=$YIL"
ol "➕ Makbuz Ekle butonu" "1" "$(grep -c 'id="mk-ekle-ac"' /tmp/mkf_index.html | awk '{print ($1>0)?1:0}')"
ol "makbuz ekle modalı" "1" "$(grep -c 'id="mk-ekle-modal"' /tmp/mkf_index.html | awk '{print ($1>0)?1:0}')"
ol "form POST makbuz/kaydet" "1" "$(grep -c 'action="[^"]*makbuz/kaydet"' /tmp/mkf_index.html | awk '{print ($1>0)?1:0}')"
ol "mükellef arama kutusu" "1" "$(grep -c 'id="mk-ara"' /tmp/mkf_index.html | awk '{print ($1>0)?1:0}')"
ol "canlı net önizlemesi" "1" "$(grep -c 'id="mk-o-net"' /tmp/mkf_index.html | awk '{print ($1>0)?1:0}')"
ol "mükerrer onay kutusu (zorla)" "1" "$(grep -c 'name="zorla"' /tmp/mkf_index.html | awk '{print ($1>0)?1:0}')"
ol "donus=liste gizli alanı" "1" "$(grep -c 'name="donus" value="liste"' /tmp/mkf_index.html | awk '{print ($1>0)?1:0}')"
ol "Excel girişi hâlâ duruyor (regresyon)" "1" \
   "$(grep -c 'makbuz/ice-aktar?kip=makbuz' /tmp/mkf_index.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 2) FORM İLE KAYIT (otomatik stopaj/KDV) ==="
kaydet $J -d "id=0" -d "mukellef_id=$MK" -d "yil=$YIL" -d "tarih=$BUGUN" \
  -d "makbuz_no=MKF-001" -d "brut=10000" -d "stopaj=" -d "kdv=" -d "donus=liste" \
  -d "aciklama=MKF-TEST otomatik oran"
ol "kayıt başarılı → listeye döner" "/makbuz?yil=$YIL" "$(hedef)"
MID=$(db "SELECT id FROM makbuzlar WHERE aciklama='MKF-TEST otomatik oran' LIMIT 1;")
ol "DB'de kayıt oluştu" "1" "$([ -n "$MID" ] && echo 1 || echo 0)"
ol "stopaj orandan hesaplandı (%$STOPAJ_ORAN)" \
   "$(python3 -c "print('%.2f' % (10000 * $STOPAJ_ORAN / 100))")" "$(db "SELECT stopaj FROM makbuzlar WHERE id=$MID;")"
ol "KDV orandan hesaplandı (%$KDV_ORAN)" \
   "$(python3 -c "print('%.2f' % (10000 * $KDV_ORAN / 100))")" "$(db "SELECT kdv FROM makbuzlar WHERE id=$MID;")"
ol "net = brüt − stopaj + KDV" \
   "$(python3 -c "print('%.2f' % (10000 - 10000 * $STOPAJ_ORAN / 100 + 10000 * $KDV_ORAN / 100))")" \
   "$(db "SELECT net FROM makbuzlar WHERE id=$MID;")"
ol "yıl doğru kaydedildi" "$YIL" "$(db "SELECT yil FROM makbuzlar WHERE id=$MID;")"
ol "ay tarihten türetildi" "$(date -d "$BUGUN" +%-m)" "$(db "SELECT ay FROM makbuzlar WHERE id=$MID;")"
ol "makbuz no kaydedildi" "MKF-001" "$(db "SELECT makbuz_no FROM makbuzlar WHERE id=$MID;")"
ol "müşavir portföyden geldi (müşavir 1)" "1" "$(db "SELECT musavir_id FROM makbuzlar WHERE id=$MID;")"

echo
echo "=== 3) ELLE GİRİLEN STOPAJ/KDV KORUNUR ==="
kaydet $J -d "id=0" -d "mukellef_id=$MK" -d "yil=$YIL" -d "tarih=$BUGUN" \
  -d "makbuz_no=MKF-002" -d "brut=5000" -d "stopaj=900" -d "kdv=1000" -d "donus=liste" \
  -d "aciklama=MKF-TEST elle tutar"
MID2=$(db "SELECT id FROM makbuzlar WHERE aciklama='MKF-TEST elle tutar' LIMIT 1;")
ol "elle girilen stopaj aynen kaydedildi" "900.00" "$(db "SELECT stopaj FROM makbuzlar WHERE id=$MID2;")"
ol "elle girilen KDV aynen kaydedildi" "1000.00" "$(db "SELECT kdv FROM makbuzlar WHERE id=$MID2;")"
ol "net elle tutarlarla: 5000-900+1000 = 5100" "5100.00" "$(db "SELECT net FROM makbuzlar WHERE id=$MID2;")"

echo
echo "=== 4) TÜRKÇE BİÇİMLİ TUTAR (1.250,50) ==="
kaydet $J -d "id=0" -d "mukellef_id=$MK" -d "yil=$YIL" -d "tarih=$BUGUN" \
  -d "makbuz_no=MKF-003" -d "brut=1.250,50" -d "stopaj=0" -d "kdv=0" -d "donus=liste" \
  -d "aciklama=MKF-TEST virgüllü tutar"
MID3=$(db "SELECT id FROM makbuzlar WHERE aciklama='MKF-TEST virgüllü tutar' LIMIT 1;")
ol "1.250,50 → 1250.50 okundu" "1250.50" "$(db "SELECT brut FROM makbuzlar WHERE id=$MID3;")"

echo
echo "=== 5) MÜKERRER KORUMA (aynı makbuz no) ==="
ONCE=$(db "SELECT COUNT(*) FROM makbuzlar WHERE aciklama LIKE 'MKF-TEST%';")
kaydet $J -d "id=0" -d "mukellef_id=$MK" -d "yil=$YIL" -d "tarih=$BUGUN" \
  -d "makbuz_no=MKF-001" -d "brut=10000" -d "donus=liste" \
  -d "aciklama=MKF-TEST mukerrer deneme"
ol "mükerrer kayıt ENGELLENDİ (kayıt sayısı sabit)" "$ONCE" \
   "$(db "SELECT COUNT(*) FROM makbuzlar WHERE aciklama LIKE 'MKF-TEST%';")"
ol "  mükerrer satır oluşmadı" "0" \
   "$(db "SELECT COUNT(*) FROM makbuzlar WHERE aciklama='MKF-TEST mukerrer deneme';")"
curl -s -b $J -c $J -o /tmp/mkf_mesaj.html "$B/makbuz?yil=$YIL"
ol "  kullanıcıya açıklayıcı hata gösterildi" "1" \
   "$(grep -c 'zaten kayıtlı' /tmp/mkf_mesaj.html | awk '{print ($1>0)?1:0}')"

kaydet $J -d "id=0" -d "mukellef_id=$MK" -d "yil=$YIL" -d "tarih=$BUGUN" \
  -d "makbuz_no=MKF-001" -d "brut=10000" -d "zorla=1" -d "donus=liste" \
  -d "aciklama=MKF-TEST bilincli mukerrer"
ol "zorla=1 ile kaydedildi (bilinçli tekrar)" "1" \
   "$(db "SELECT COUNT(*) FROM makbuzlar WHERE aciklama='MKF-TEST bilincli mukerrer';")"
db "DELETE FROM makbuzlar WHERE aciklama='MKF-TEST bilincli mukerrer';" >/dev/null

echo
echo "=== 6) ÇİZELGEYE YANSIMA (hesaplama değişmedi) ==="
KESILEN=$(db "SELECT COALESCE(SUM(brut),0) FROM makbuzlar WHERE mukellef_id=$MK AND yil=$YIL;")
SAYI=$(db "SELECT COUNT(*) FROM makbuzlar WHERE mukellef_id=$MK AND yil=$YIL;")
# Oturum ve çizelge taze olsun (POST'lardan sonra yeniden giriş)
curl -s -b $J -c $J -o /tmp/mkf_ciz.html "$B/makbuz?yil=$YIL"
ol "çizelge sayfası açıldı" "1" \
   "$(grep -c 'data-mukellef=' /tmp/mkf_ciz.html | awk '{print ($1>0)?1:0}')"
ol "mükellef satırı çizelgede" "1" \
   "$(grep -c "data-mukellef=\"$MK\"" /tmp/mkf_ciz.html | awk '{print ($1>0)?1:0}')"
ol "  kesilen tutar = DB brüt toplamı" \
   "$(python3 -c "print(f'{float('$KESILEN'):,.2f}'.replace(',','X').replace('.',',').replace('X','.'))")" \
   "$(cizelgeKesilen /tmp/mkf_ciz.html "$MK")"
ol "  makbuz adedi DB ile aynı" "$SAYI" \
   "$(python3 -c "
import re
h=open('/tmp/mkf_ciz.html',encoding='utf-8').read()
m=re.search(r'<tr[^>]*data-mukellef=\"$MK\".*?</tr>', h, re.S)
tds=re.findall(r'<td[^>]*>(.*?)</td>', m.group(0), re.S) if m else []
print(re.sub(r'<[^>]+>','',tds[4]).strip() if len(tds)>4 else 'YOK')")"
DETAY_SAYI=$(curl -s -b $J "$B/makbuz/detay/$MK?yil=$YIL" | grep -c 'class="md-tahsil-kutu"')
ol "mükellef detayında $SAYI makbuz satırı" "$SAYI" "$DETAY_SAYI"

echo
echo "=== 7) TAHHİL İŞARETİ VE DİĞER ALANLAR ==="
kaydet $J -d "id=0" -d "mukellef_id=$MK" -d "yil=$YIL" -d "tarih=$BUGUN" \
  -d "makbuz_no=MKF-004" -d "brut=2000" -d "stopaj=0" -d "kdv=0" \
  -d "tahsil_edildi=1" -d "tahsil_tarihi=$BUGUN" -d "musavir_id=1" -d "donus=liste" \
  -d "aciklama=MKF-TEST tahsil"
MID4=$(db "SELECT id FROM makbuzlar WHERE aciklama='MKF-TEST tahsil' LIMIT 1;")
ol "tahsil edildi=1" "1" "$(db "SELECT tahsil_edildi FROM makbuzlar WHERE id=$MID4;")"
ol "tahsil tarihi" "$BUGUN" "$(db "SELECT tahsil_tarihi FROM makbuzlar WHERE id=$MID4;")"
ol "kaydeden kullanıcı yazıldı" "1" "$(db "SELECT kaydeden_id FROM makbuzlar WHERE id=$MID4;")"

echo
echo "=== 8) GÜVENLİK ==="
ol "CSRF'siz POST reddedildi (403)" "403" \
   "$(curl -s -b $J -o /dev/null -w '%{http_code}' -d "mukellef_id=$MK" -d "brut=100" "$B/makbuz/kaydet")"
ONCE=$(db "SELECT COUNT(*) FROM makbuzlar WHERE aciklama LIKE 'MKF-TEST%';")
kaydet $JM -d "id=0" -d "mukellef_id=$MK2" -d "yil=$YIL" -d "tarih=$BUGUN" \
  -d "makbuz_no=MKF-YETKI" -d "brut=1000" -d "donus=liste" -d "aciklama=MKF-TEST yetki deneme"
ol "yetkisiz müşavir kaydı ENGELLENDİ" "0" \
   "$(db "SELECT COUNT(*) FROM makbuzlar WHERE aciklama='MKF-TEST yetki deneme';")"
ol "  kayıt sayısı değişmedi" "$ONCE" "$(db "SELECT COUNT(*) FROM makbuzlar WHERE aciklama LIKE 'MKF-TEST%';")"
ol "personel makbuz ekranına erişemez" "0" \
   "$(rm -f /tmp/mkf_p.txt; curl -s -c /tmp/mkf_p.txt -o /tmp/mkf_pg.html $B/giris
      T=$(grep -oP 'name=\"csrf_beyanname\" value=\"\K[^\"]+' /tmp/mkf_pg.html|head -1)
      curl -s -b /tmp/mkf_p.txt -c /tmp/mkf_p.txt -o /dev/null -d \"csrf_beyanname=$T\" -d 'kimlik=personel' -d 'sifre=Test1234' $B/giris
      curl -s -b /tmp/mkf_p.txt -c /tmp/mkf_p.txt -o /tmp/mkf_panel.html -L "$B/makbuz?yil=$YIL"
      grep -c 'mk-ekle-modal' /tmp/mkf_panel.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 9) TEMİZLİK ==="
db "DELETE FROM makbuzlar WHERE aciklama LIKE 'MKF-TEST%';" >/dev/null
db "DELETE FROM makbuzlar WHERE mukellef_id IN (SELECT id FROM mukellefler WHERE vergi_kimlik_no='8888888888');" >/dev/null
db "DELETE FROM mukellefler WHERE vergi_kimlik_no='8888888888';" >/dev/null
ol "test verisi temizlendi" "0" "$(db "SELECT COUNT(*) FROM makbuzlar WHERE aciklama LIKE 'MKF-TEST%';")"
ol "  geçici mükellef silindi" "0" "$(db "SELECT COUNT(*) FROM mukellefler WHERE vergi_kimlik_no='8888888888';")"

echo
echo "========================================"
if [ $k -eq 0 ]; then echo "TÜM TESTLER BAŞARILI ($g geçti / $((g+k)))"; else echo "$k HATA ($g geçti / $((k+g)))"; fi
rm -f $J $JM
[ $k -eq 0 ] || exit 1
