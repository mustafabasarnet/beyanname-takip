#!/bin/bash
# =====================================================================
#  KİŞİSEL TO-DO — GİRİŞ HATIRLATMASI TESTİ
#
#  Senaryo (kullanıcı isteği):
#    "Kişisel notlardaki To-Do'da son tarihe göre hatırlatıcı olsun;
#     yapılmayanlar / dünden kalanlar ilk girişte popup olarak uyarılsın."
#
#  Test ettikleri:
#    1) Gecikmiş + bugün + yaklaşan gruplandırması doğru
#    2) Yalnız tamamlanmamış ve son tarihi OLAN görevler listelenir
#    3) Son tarihi belirlenmemiş açık görevler yalnız sayaç olarak döner
#    4) "Anladım" → aynı gün tekrar gösterilmez (kalıcı; oturum yenilense de)
#    5) Yaklaşan gün ayarı (0 → yaklaşan grubu boş; 7 → 7 gün ilerisi)
#    6) Ayar kapalıyken hiç gösterilmez
#    7) Kullanıcı izolasyonu: herkes YALNIZ kendi görevlerini görür
#    8) CSRF'siz POST reddedilir
#    9) Popup HTML + JS her sayfada yükleniyor (layout) ve ajanda sırası var
#   10) Popup'tan "yapıldı" işaretleme (gorev-ters) çalışır ve görev listeden düşer
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin / personel / musavir = Test1234 hesapları mevcut.
#  Kullanım:  bash tests/kisisel_uyari_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
JA=/tmp/ks_oturum_admin.txt
JP=/tmp/ks_oturum_personel.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
jeton(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
jal(){ python3 -c "
import json,sys
try:
    d=json.load(open('$1',encoding='utf-8'))
except Exception:
    print('YOK'); sys.exit()
v=d.get('$2')
print(json.dumps(v,ensure_ascii=False) if isinstance(v,(list,dict)) else v)"; }
juzunluk(){ python3 -c "
import json
try:
    d=json.load(open('$1',encoding='utf-8'))
except Exception:
    print(-1); exit()
print(len(d.get('$2') or []))"; }
jad(){ python3 -c "
import json
d=json.load(open('$1',encoding='utf-8'))
print(','.join(g.get('baslik','') for g in (d.get('$2') or [])))"; }
# Yalnız bu testin verisini sayar (kurulumdaki gerçek görevler karışmasın)
jKS(){ python3 -c "
import json
d=json.load(open('$1',encoding='utf-8'))
print(len([g for g in (d.get('$2') or []) if g.get('baslik','').startswith('KS-TEST')]))"; }
jadKS(){ python3 -c "
import json
d=json.load(open('$1',encoding='utf-8'))
print(','.join(g.get('baslik','') for g in (d.get('$2') or []) if g.get('baslik','').startswith('KS-TEST')))"; }
# Bu testin görevlerinin tamamı (3 grup toplamı)
jToplamKS(){ python3 -c "
import json
d=json.load(open('$1',encoding='utf-8'))
print(sum(len([g for g in (d.get(gr) or []) if g.get('baslik','').startswith('KS-TEST')])
          for gr in ('gecikmis','bugun','yaklasan')))"; }

girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/ks_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/ks_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Görev ekle (AJAX) — $1=oturum $2=başlık $3=son_tarih $4=öncelik
gorevEkle(){ local T; T=$(jeton "$1" "$B/kisisel")
  curl -s -b "$1" -c "$1" -o /dev/null -X POST "$B/kisisel/gorev-ekle" \
    -d "csrf_beyanname=$T" --data-urlencode "baslik=$2" -d "son_tarih=$3" -d "oncelik=${4:-normal}" -d "etiket=test"; }

echo "=== 0) HAZIRLIK ==="
girisYap admin $JA
ol "Admin girişi" "1" "$(curl -s -b $JA -o /dev/null -w '%{http_code}' $B/kisisel | awk '{print ($1==200)?1:0}')"

db "DELETE FROM kisisel_notlar WHERE baslik LIKE 'KS-TEST%';" >/dev/null 2>&1
BUGUN=$(date +%F)
DUN=$(date -d 'yesterday' +%F)
IKI_GUN_ONCE=$(date -d '2 days ago' +%F)
UC_GUN_SONRA=$(date -d "+3 days" +%F)
YEDI_GUN_SONRA=$(date -d '+7 days' +%F)
ON_GUN_SONRA=$(date -d '+10 days' +%F)

gorevEkle $JA "KS-TEST dün kalan"      "$DUN"          acil
gorevEkle $JA "KS-TEST iki gün önce"   "$IKI_GUN_ONCE" normal
gorevEkle $JA "KS-TEST bugün"          "$BUGUN"        yuksek
gorevEkle $JA "KS-TEST üç gün sonra"   "$UC_GUN_SONRA" normal
gorevEkle $JA "KS-TEST on gün sonra"   "$ON_GUN_SONRA" dusuk
# Son tarihsiz görev (yalnız sayaç olmalı)
gorevEkle $JA "KS-TEST tarihsiz" "" normal
ol "6 test görevi eklendi" "6" "$(db "SELECT COUNT(*) FROM kisisel_notlar WHERE baslik LIKE 'KS-TEST%' AND tur='gorev';")"

echo
echo "=== 1) HATIRLATMA: GRUPLAMA (gecikmiş / bugün / yaklaşan) ==="
db "DELETE FROM kisisel_notlar WHERE baslik LIKE 'KS-TEST gecmis%';" >/dev/null 2>&1
curl -s -b $JA -o /tmp/ks_u1.json "$B/kisisel/giris-uyarisi"
ol "gösterilecek" "True" "$(jal /tmp/ks_u1.json goster)"
ol "gecikmiş grubu 2 görev" "2" "$(jKS /tmp/ks_u1.json gecikmis)"
ol "  gecikmiş sırası: en eski önce" "KS-TEST iki gün önce,KS-TEST dün kalan" "$(jadKS /tmp/ks_u1.json gecikmis)"
ol "bugün grubu 1 görev" "1" "$(jKS /tmp/ks_u1.json bugun)"
ol "  bugün: doğru görev" "KS-TEST bugün" "$(jadKS /tmp/ks_u1.json bugun)"
ol "yaklaşan grubu 1 görev (≤3 gün)" "1" "$(jKS /tmp/ks_u1.json yaklasan)"
ol "  yaklaşan: 3 gün sonra" "KS-TEST üç gün sonra" "$(jadKS /tmp/ks_u1.json yaklasan)"
ol "10 gün sonraki görev yaklaşan listesinde YOK" "0" "$(jadKS /tmp/ks_u1.json yaklasan | grep -c 'on gün sonra')"
ol "toplam 4 görev (bu testin verisi)" "4" "$(jToplamKS /tmp/ks_u1.json)"
ol "tarihsiz açık görev sayacı DB ile tutarlı" \
   "$(db "SELECT COUNT(*) FROM kisisel_notlar WHERE kullanici_id=1 AND tur='gorev' AND tamamlandi=0 AND (son_tarih IS NULL OR son_tarih='');")" \
   "$(jal /tmp/ks_u1.json tarihsiz)"
ol "gecikmiş bayrağı doğru (dün kalan)" "True" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
print([g['gecikmis'] for g in d['gecikmis'] if g['baslik']=='KS-TEST dün kalan'][0])")"
ol "gecikme günü doğru (2 gün önce)" "2" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
print([g['gecikme_gun'] for g in d['gecikmis'] if g['baslik']=='KS-TEST iki gün önce'][0])")"
ol "gecikmiş görevde kalan_gun = 0" "0" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
print([g['kalan_gun'] for g in d['gecikmis'] if g['baslik']=='KS-TEST dün kalan'][0])")"
ol "bugünkü görevde kalan_gun = 0 ve gecikme = 0" "0|0" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
g=[g for g in d['bugun'] if g['baslik']=='KS-TEST bugün'][0]
print(str(g['kalan_gun'])+'|'+str(g['gecikme_gun']))")"
ol "yaklaşan görevde kalan_gun POZİTİF (3)" "3" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
g=[g for g in d['yaklasan'] if g['baslik']=='KS-TEST üç gün sonra'][0]
print(g['kalan_gun'])")"
ol "yaklaşan görevde gecikme_gun = 0" "0" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
g=[g for g in d['yaklasan'] if g['baslik']=='KS-TEST üç gün sonra'][0]
print(g['gecikme_gun'])")"
ol "hiçbir görevde NEGATİF gün yok" "0" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
hepsi=[g for gr in ('gecikmis','bugun','yaklasan') for g in (d.get(gr) or [])]
print(sum(1 for g in hepsi if g['kalan_gun']<0 or g['gecikme_gun']<0))")"
ol "öncelik bilgisi taşınıyor (acil)" "acil" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
print([g['oncelik'] for g in d['gecikmis'] if g['baslik']=='KS-TEST dün kalan'][0])")"
ol "etiket bilgisi taşınıyor" "test" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
print([g['etiket'] for g in d['gecikmis'] if g['baslik']=='KS-TEST dün kalan'][0])")"
ol "son tarih TR biçiminde" "$(date -d "$DUN" +%d.%m.%Y)" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u1.json',encoding='utf-8'))
print([g['son_tarih'] for g in d['gecikmis'] if g['baslik']=='KS-TEST dün kalan'][0])")"

echo
echo "=== 2) TAMAMLANAN GÖREV HATIRLATMADA ÇIKMAZ ==="
GID=$(db "SELECT id FROM kisisel_notlar WHERE baslik='KS-TEST dün kalan'")
T=$(jeton $JA "$B/kisisel")
curl -s -b $JA -c $JA -o /dev/null -X POST "$B/kisisel/gorev-ters" -d "csrf_beyanname=$T" -d "id=$GID"
ol "görev yapıldı işaretlendi" "1" "$(db "SELECT tamamlandi FROM kisisel_notlar WHERE id=$GID;")"
curl -s -b $JA -o /tmp/ks_u2.json "$B/kisisel/giris-uyarisi"
ol "gecikmiş gruptan düştü (2 → 1)" "1" "$(jKS /tmp/ks_u2.json gecikmis)"
ol "toplam 3'e düştü" "3" "$(jToplamKS /tmp/ks_u2.json)"

echo
echo "=== 3) GÜNDE BİR KEZ: Anladım → tekrar gösterilmez ==="
T=$(jeton $JA "$B/kisisel")
ol "CSRF'siz okundu POST reddedildi" "403" \
   "$(curl -s -b $JA -o /dev/null -w '%{http_code}' -X POST "$B/kisisel/uyari-okundu")"
curl -s -b $JA -c $JA -o /tmp/ks_ok.json -X POST "$B/kisisel/uyari-okundu" -d "csrf_beyanname=$T"
ol "okundu kaydedildi (durum=true)" "True" "$(jal /tmp/ks_ok.json durum)"
curl -s -b $JA -o /tmp/ks_u3.json "$B/kisisel/giris-uyarisi"
ol "aynı gün TEKRAR gösterilmez" "False" "$(jal /tmp/ks_u3.json goster)"
ol "okundu DB'ye yazıldı (kisisel_uyari_okundu)" "1" \
   "$(db "SELECT COUNT(*) FROM kisisel_uyari_okundu WHERE kullanici_id=1 AND tarih=CURDATE();")"
# Oturum tamamen yenilenip yeniden girilse de gösterilmemeli (kalıcı davranış)
girisYap admin $JA
curl -s -b $JA -o /tmp/ks_u7.json "$B/kisisel/giris-uyarisi"
ol "YENİ OTURUMDA da gösterilmez (kalıcı, günde bir)" "False" "$(jal /tmp/ks_u7.json goster)"

echo
echo "=== 4) AYARLAR ==="
# Yaklaşan gün = 0 → 'yaklaşan' grubu boş olmalı
$MDBR -e "UPDATE ayarlar SET deger='0' WHERE anahtar='kisisel_uyari_gun';" >/dev/null
db "DELETE FROM kisisel_uyari_okundu WHERE kullanici_id=1;"   # gösterim bekleniyor: okundu kaydı temizlenir
curl -s -b $JA -o /tmp/ks_u4.json "$B/kisisel/giris-uyarisi"
ol "kisisel_uyari_gun=0 → yaklaşan boş" "0" "$(jKS /tmp/ks_u4.json yaklasan)"
ol "  gecikmiş+bugün yine listelenir" "2" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u4.json',encoding='utf-8'))
print(len([g for g in d['gecikmis']+d['bugun'] if g['baslik'].startswith('KS-TEST')]))")"

# Yaklaşan gün = 10 → 10 gün sonraki görev de girer
$MDBR -e "UPDATE ayarlar SET deger='10' WHERE anahtar='kisisel_uyari_gun';" >/dev/null
db "DELETE FROM kisisel_uyari_okundu WHERE kullanici_id=1;"
curl -s -b $JA -o /tmp/ks_u5.json "$B/kisisel/giris-uyarisi"
ol "kisisel_uyari_gun=10 → yaklaşan 2 görev" "2" "$(jKS /tmp/ks_u5.json yaklasan)"
ol "  10 gün sonraki görev listede" "KS-TEST on gün sonra" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_u5.json',encoding='utf-8'))
print(','.join(g['baslik'] for g in d['yaklasan'] if 'on gün' in g['baslik']))")"
ol "dönen 'gun' alanı 10" "10" "$(jal /tmp/ks_u5.json gun)"

# Ayar kapalı → hiç gösterme
$MDBR -e "UPDATE ayarlar SET deger='0' WHERE anahtar='kisisel_giris_uyari';" >/dev/null
db "DELETE FROM kisisel_uyari_okundu WHERE kullanici_id=1;"
curl -s -b $JA -o /tmp/ks_u6.json "$B/kisisel/giris-uyarisi"
ol "ayar kapalıyken gösterilmez" "False" "$(jal /tmp/ks_u6.json goster)"
$MDBR -e "UPDATE ayarlar SET deger='1' WHERE anahtar='kisisel_giris_uyari'; UPDATE ayarlar SET deger='3' WHERE anahtar='kisisel_uyari_gun';" >/dev/null

echo
echo "=== 5) KULLANICI İZOLASYONU (herkes yalnız kendi görevini görür) ==="
girisYap personel $JP
db "DELETE FROM kisisel_uyari_okundu WHERE kullanici_id=2;" >/dev/null
gorevEkle $JP "KS-TEST personel gecikmiş" "$DUN" normal
curl -s -b $JP -o /tmp/ks_p1.json "$B/kisisel/giris-uyarisi"
ol "personel: yalnız KENDİ görevi" "KS-TEST personel gecikmiş" "$(jad /tmp/ks_p1.json gecikmis)"
ol "personel: admin'in görevleri görünmez" "0" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_p1.json',encoding='utf-8'))
hepsi=[g['baslik'] for gr in ('gecikmis','bugun','yaklasan') for g in (d.get(gr) or [])]
print(sum(1 for b in hepsi if b in ('KS-TEST dün kalan','KS-TEST bugün','KS-TEST iki gün önce')))")"
ol "personel toplam 1 görev" "1" "$(jToplamKS /tmp/ks_p1.json)"

db "DELETE FROM kisisel_uyari_okundu WHERE kullanici_id=1;"
curl -s -b $JA -o /tmp/ks_a1.json "$B/kisisel/giris-uyarisi"
ol "admin: personelin görevi görünmez" "0" "$(python3 -c "
import json
d=json.load(open('/tmp/ks_a1.json',encoding='utf-8'))
hepsi=[g['baslik'] for gr in ('gecikmis','bugun','yaklasan') for g in (d.get(gr) or [])]
print(sum(1 for b in hepsi if 'personel' in b))")"

echo
echo "=== 6) POPUP SAYFAYA GÖMÜLÜ MÜ (layout + sıra) ==="
curl -s -b $JA -o /tmp/ks_panel.html "$B/panel"
ol "popup kutusu sayfada (ks-uyari-ort)" "1" "$(grep -c 'id="ks-uyari-ort"' /tmp/ks_panel.html)"
ol "başlık: Kişisel To-Do Hatırlatması" "1" "$(grep -c 'Kişisel To-Do Hatırlatması' /tmp/ks_panel.html)"
ol "giriş-uyarisi fetch ediliyor" "1" "$(grep -c 'kisisel/giris-uyarisi' /tmp/ks_panel.html)"
ol "uyari-okundu POST ediliyor" "1" "$(grep -c 'kisisel/uyari-okundu' /tmp/ks_panel.html)"
ol "Ajanda sırası korunuyor (aj-uyari-ort)" "1" "$(grep -c 'id="aj-uyari-ort"' /tmp/ks_panel.html)"
ol "Ajanda sırasını bekleyen fonksiyon var" "1" "$(grep -c 'ajandaAcikMi' /tmp/ks_panel.html | awk '{print ($1>0)?1:0}')"
ol "görev tamamlama çağrısı var (gorev-ters)" "1" "$(grep -c 'kisisel/gorev-ters' /tmp/ks_panel.html)"
ol "Kişisel Notlar sayfası açılıyor" "200" "$(curl -s -b $JA -o /tmp/ks_kisisel.html -w '%{http_code}' $B/kisisel)"
ol "  popup kişisel sayfada da var" "1" "$(grep -c 'id="ks-uyari-ort"' /tmp/ks_kisisel.html)"
curl -s -o /tmp/ks_giris.html "$B/giris"
ol "giriş ekranında popup YOK (yalnız giriş sonrası)" "0" "$(grep -c 'ks-uyari-ort' /tmp/ks_giris.html)"
ol "ayarlar ekranında yeni kart görünür" "1" "$(curl -s -b $JA "$B/tanimlar/ayarlar" | grep -c 'Kişisel Notlar ve To-Do' | awk '{print ($1>0)?1:0}')"
ol "ayar alanı: Girişte To-Do Hatırlatması" "1" "$(curl -s -b $JA "$B/tanimlar/ayarlar" | grep -c 'Girişte To-Do Hatırlatması' | awk '{print ($1>0)?1:0}')"

echo
echo "=== 7) TEMİZLİK ==="
db "DELETE FROM kisisel_notlar WHERE baslik LIKE 'KS-TEST%';" >/dev/null
db "DELETE FROM kisisel_uyari_okundu WHERE kullanici_id IN (1,2);" >/dev/null
ol "test verisi temizlendi" "0" "$(db "SELECT COUNT(*) FROM kisisel_notlar WHERE baslik LIKE 'KS-TEST%';")"

echo
echo "========================================"
if [ $k -eq 0 ]; then echo "TÜM TESTLER BAŞARILI ($g geçti / $((g+k)))"; else echo "$k HATA ($g geçti / $((g+k)))"; fi
rm -f $JA $JP
[ $k -eq 0 ] || exit 1
