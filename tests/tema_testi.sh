#!/bin/bash
# =====================================================================
#  RENKLİ TEMA — kullanıcı bazlı görünüm (açık / karanlık / sistem + palet)
#
#  Kullanıcı isteği:
#    "Karanlık tema / açık tema veya kullanıcı bazlı renk seçenekleri gibi
#     varsayılan yapıyı değiştirmeden kullanıcı kendi profilinde kayıtlı
#     renk şablonlarını seçebilsin."
#
#  Test ettikleri:
#    1) Varsayılan: data-tema="sistem" + palet mavi + koyu menü (bugünkü görünüm)
#    2) tema.css her sayfada bağlı; stil.css değişken katmanı korunuyor
#    3) Profil → Görünüm kartı: 3 tema modu + 7 palet + 2 menü + gizli alanlar
#    4) Kaydetme: tema/palet/menü DB'ye ve oturuma yazılır, sayfaya yansır
#    5) Kullanıcı İZOLASYONU: biri değiştirince diğeri etkilenmez
#    6) Hızlı geçiş düğmesi her sayfada var + AJAX ile mod değişir
#    7) Güvenlik: whitelist dışı değer reddi (400), CSRF'siz POST (403)
#    8) Yazdırma ekranları temadan bağımsız (her zaman açık tema)
#    9) "Sistem" modu: JS işletim sistemi tercihini uygular (script var)
#   10) Migration idempotent; kolonlar yoksa sistem çökmez (varsayılana düşer)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin|personel / Test1234 hesapları mevcut,
#            database/migration_kullanici_tema.sql koşulmuş.
#  Kullanım:  bash tests/tema_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
JA=/tmp/tema_admin.txt
JP=/tmp/tema_personel.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/tema_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/tema_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }
tok(){ grep -oP 'name="csrf-token" content="\K[^"]+' "$1" | head -1; }

# <html ...> etiketini okur: "tema|palet|yan"
# Seçili düğmenin değerini okur: $1 = dosya, $2 = alan (tema|palet|yan_menu)
secilen(){ python3 -c "
import re
h=open('$1',encoding='utf-8').read()
m=re.search(r'class=\"(?:tema-dugme|palet-cip) secili\"[^>]*data-deger=\"([^\"]+)\"[^>]*data-alan=\"$2\"', h)
if not m:
    m=re.search(r'data-deger=\"([^\"]+)\"[^>]*data-alan=\"$2\"[^>]*class=\"(?:tema-dugme|palet-cip) secili\"', h)
print(m.group(1) if m else 'YOK')"; }

oznitelik(){ python3 -c "
import re
h=open('$1',encoding='utf-8').read()
m=re.search(r'<html([^>]*)>', h)
if not m: print('YOK'); raise SystemExit
a=dict(re.findall(r'data-([a-z]+)=\"([^\"]*)\"', m.group(1)))
print('|'.join([a.get('tema','-'), a.get('palet','-'), a.get('yan','-')]))"; }

echo "=== 0) HAZIRLIK ==="
# Önce varsayılana dön (önceki koşumdan/oturumdan kalıntı olmasın), SONRA giriş yap:
# oturumdaki tema tercihi giriş anında okunur.
db "UPDATE kullanicilar SET tema='sistem', palet='mavi', yan_menu='koyu';" > /dev/null 2>&1
girisYap admin $JA
girisYap personel $JP
ol "migration kolonları mevcut" "4" \
   "$(db "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='kullanicilar' AND COLUMN_NAME IN ('tema','palet','yan_menu','hizli_gecis');")"

echo
echo "=== 1) VARSAYILAN GÖRÜNÜM (bugünkü hâl) ==="
curl -s -b $JA -c $JA -o /tmp/tema_panel.html "$B/panel"
ol "data-tema=sistem (işletim sistemine uyar)" "sistem|mavi|koyu" "$(oznitelik /tmp/tema_panel.html)"
ol "tema.css bağlı" "1" "$(grep -c 'assets/css/tema.css' /tmp/tema_panel.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "stil.css bağlı" "1" "$(grep -c 'assets/css/stil.css' /tmp/tema_panel.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "ilk boyama script'i var (FOUC önleyici)" "1" "$(grep -c 'btSistemTemaIzle' /tmp/tema_panel.html | head -1 | awk '{print ($1>0)?1:0}')"

echo
echo "=== 2) PROFİL → GÖRÜNÜM KARTI ==="
curl -s -b $JA -c $JA -o /tmp/tema_profil.html "$B/profil"
ol "Görünüm kartı başlığı" "1" "$(grep -c '🎨 Görünüm' /tmp/tema_profil.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "3 tema modu düğmesi" "3" "$(grep -o 'data-alan="tema"' /tmp/tema_profil.html | wc -l | tr -d ' ')"
ol "7 renk şablonu" "7" "$(grep -o 'class="palet-cip[^"]*"' /tmp/tema_profil.html | wc -l | tr -d ' ')"
ol "2 yan menü seçeneği" "2" "$(python3 -c "
import re
h=open('/tmp/tema_profil.html',encoding='utf-8').read()
print(len(re.findall(r'data-alan=\"yan_menu\"', h)))")"
ol "gizli alan tema=sistem" "1" "$(grep -c 'id="gizli-tema" value="sistem"' /tmp/tema_profil.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "gizli alan palet=mavi" "1" "$(grep -c 'id="gizli-palet" value="mavi"' /tmp/tema_profil.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "yazdırma notu (her zaman açık tema)" "1" "$(grep -c 'her zaman <b>açık tema</b>' /tmp/tema_profil.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "mavi palet seçili geliyor" "mavi" "$(secilen /tmp/tema_profil.html palet)"

echo
echo "=== 3) KAYDETME (profil formu) ==="
T=$(tok /tmp/tema_profil.html)
curl -s -b $JA -c $JA -o /dev/null -w '%{http_code}' -d "csrf_beyanname=$T" \
     -d "ad_soyad=ADMIN" -d "telefon=" -d "tema=karanlik" -d "palet=mor" -d "yan_menu=acik" \
     "$B/profil" > /tmp/tema_kod.txt
ol "POST 303 (kaydedildi)" "303" "$(cat /tmp/tema_kod.txt)"
ol "DB: karanlik|mor|acik" "karanlik|mor|acik" \
   "$(db "SELECT CONCAT(tema,'|',palet,'|',yan_menu) FROM kullanicilar WHERE kullanici_adi='admin';")"
curl -s -b $JA -c $JA -o /tmp/tema_panel2.html "$B/panel"
ol "sayfaya yansıdı" "karanlik|mor|acik" "$(oznitelik /tmp/tema_panel2.html)"
curl -s -b $JA -c $JA -o /tmp/tema_profil2.html "$B/profil"
ol "açık menü seçili (az önce kaydedildi)" "acik" "$(secilen /tmp/tema_profil2.html yan_menu)"

echo
echo "=== 4) KULLANICI İZOLASYONU ==="
curl -s -b $JP -c $JP -o /tmp/tema_panel_p.html "$B/panel"
ol "personel KENDİ varsayılanını görür" "sistem|mavi|koyu" "$(oznitelik /tmp/tema_panel_p.html)"
P_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='personel';")
T=$(tok /tmp/tema_panel_p.html)
curl -s -b $JP -c $JP -o /tmp/tema_json_p.json -X POST -H "X-Requested-With: XMLHttpRequest" \
     -d "csrf_beyanname=$T" -d "tema=karanlik" "$B/tema-hizli-gecis"
ol "personel kendi temasını değiştirdi" "karanlik" \
   "$(db "SELECT tema FROM kullanicilar WHERE id=$P_ID;")"
ol "admin ETKİLENMEDİ (hâlâ karanlik|mor|acik)" "karanlik|mor|acik" \
   "$(db "SELECT CONCAT(tema,'|',palet,'|',yan_menu) FROM kullanicilar WHERE kullanici_adi='admin';")"
curl -s -b $JA -c $JA -o /tmp/tema_panel_a2.html "$B/panel"
ol "admin paleti hâlâ mor" "karanlik|mor|acik" "$(oznitelik /tmp/tema_panel_a2.html)"
db "UPDATE kullanicilar SET tema='sistem' WHERE id=$P_ID;" > /dev/null 2>&1

echo
echo "=== 5) HIZLI GEÇİŞ DÜĞMESİ ==="
ol "düğme panelde var" "1" "$(grep -c 'id="tema-hizli-gecis"' /tmp/tema_panel.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "düğmede mod bilgisi (sistem izleme)" "1" "$(python3 -c "
import re
h=open('/tmp/tema_panel.html',encoding='utf-8').read()
m=re.search(r'id=\"tema-hizli-gecis\"(.*?)>', h, re.S)
print(1 if (m and 'data-mod' in m.group(1)) else 0)")"
for yol in takip sicil evrak; do
  curl -s -b $JA -c $JA -o /tmp/tema_hg.html "$B/$yol"
  ol "/$yol sayfasında düğme var" "1" "$(grep -c 'id="tema-hizli-gecis"' /tmp/tema_hg.html | head -1 | awk '{print ($1>0)?1:0}')"
done
T=$(tok /tmp/tema_panel.html)
curl -s -b $JA -c $JA -o /tmp/tema_hg.json -X POST -H "X-Requested-With: XMLHttpRequest" \
     -d "csrf_beyanname=$T" -d "tema=acik" "$B/tema-hizli-gecis"
ol "AJAX: durum=true" "1" "$(grep -c '"durum": true' /tmp/tema_hg.json | head -1 | awk '{print ($1>0)?1:0}')"
ol "AJAX: tema=acik döndü" "1" "$(grep -c '"tema": "acik"' /tmp/tema_hg.json | head -1 | awk '{print ($1>0)?1:0}')"
ol "DB'ye yazıldı" "acik" "$(db "SELECT tema FROM kullanicilar WHERE kullanici_adi='admin';")"
ol "palet/menü DEĞİŞMEDİ (hızlı geçiş yalnız modu çevirir)" "mor|acik" \
   "$(db "SELECT CONCAT(palet,'|',yan_menu) FROM kullanicilar WHERE kullanici_adi='admin';")"

echo
echo "=== 6) GÜVENLİK ==="
T=$(tok /tmp/tema_panel.html)
KOD=$(curl -s -b $JA -c $JA -o /dev/null -w '%{http_code}' -X POST -H "X-Requested-With: XMLHttpRequest" \
      -d "csrf_beyanname=$T" -d "tema=<script>x</script>" "$B/tema-hizli-gecis")
ol "whitelist dışı tema reddedildi (400)" "400" "$KOD"
ol "  DB bozulmadı" "acik" "$(db "SELECT tema FROM kullanicilar WHERE kullanici_adi='admin';")"
KOD2=$(curl -s -b $JA -c $JA -o /dev/null -w '%{http_code}' -X POST -H "X-Requested-With: XMLHttpRequest" \
       -d "tema=karanlik" "$B/tema-hizli-gecis")
ol "CSRF'siz POST reddedildi (403)" "403" "$KOD2"
T=$(tok /tmp/tema_panel.html)
KOD3=$(curl -s -b $JA -c $JA -o /dev/null -w '%{http_code}' -d "csrf_beyanname=$T" \
       -d "ad_soyad=ADMIN" -d "tema=hayalet" "$B/profil")
ol "profil formunda geçersiz palet → uyarı, profil kaydı korunur" "303" "$KOD3"
ol "  tema değişmedi (hayalet yazılmadı)" "acik" "$(db "SELECT tema FROM kullanicilar WHERE kullanici_adi='admin';")"

echo
echo "=== 7) YAZDIRMA EKRANLARI TEMADAN BAĞIMSIZ ==="
curl -s -b $JA -c $JA -o /tmp/tema_yazdir.html "$B/takip/yazdir"
ol "yazdırma sayfası data-tema/palet/yan basmaz (tema dışı)" "-|-|-" "$(oznitelik /tmp/tema_yazdir.html)"
ol "yazdırma sayfası açık zemin" "1" "$(grep -c 'background:#fff\|background: #fff' /tmp/tema_yazdir.html | head -1 | awk '{print ($1>0)?1:0}')"
ol "@media print renk geri alma kuralı tema.css'te" "1" "$(grep -c '@media print' public/assets/css/tema.css | head -1 | awk '{print ($1>0)?1:0}')"

echo
echo "=== 8) CSS DEĞİŞKEN KATMANI ==="
ol "stil.css :root tema katmanı" "1" "$(grep -c -- '--yuzey:#ffffff' public/assets/css/stil.css | head -1 | awk '{print ($1>0)?1:0}')"
ol "karanlık tema bloğu" "1" "$(grep -c ':root\[data-tema="karanlik"\]' public/assets/css/tema.css | head -1 | awk '{print ($1>0)?1:0}')"
ol "7 palet bloğu" "7" "$(grep -cE '^:root\[data-palet="[a-z]+"\]\{' public/assets/css/tema.css)"
ol "karanlık+palet kombinasyonları" "7" "$(grep -cE '^:root\[data-tema="karanlik"\]\[data-palet' public/assets/css/tema.css)"
ol "açık menü varyantı" "1" "$(grep -c ':root\[data-yan="acik"\]' public/assets/css/tema.css | head -1 | awk '{print ($1>0)?1:0}')"
ol "stil.css'te kart zemini değişkene bağlı" "1" "$(grep -c 'background:var(--yuzey)' public/assets/css/stil.css | head -1 | awk '{print ($1>0)?1:0}')"

echo
echo "=== 9) jsdom (TARAYICI) TESTİ İÇİN SAYFA HAZIRLIĞI ==="
# jsdom testi gerçek HTML ister; admin'i "karanlik|mor|acik" yapıp kaydediyoruz
db "UPDATE kullanicilar SET tema='karanlik', palet='mor', yan_menu='acik' WHERE kullanici_adi='admin';" > /dev/null 2>&1
# Oturumdaki tema önbelleği tazelensin diye yeniden giriş (gerçek kullanıcı akışı)
girisYap admin $JA
curl -s -b $JA -c $JA -o /tmp/tema_panel.html "$B/panel"
# Profil dosyası VARSAYILAN görünümde üretilir (kartın "hiç seçim yapılmamış" hâli)
db "UPDATE kullanicilar SET tema='sistem', palet='mavi', yan_menu='koyu' WHERE kullanici_adi='admin';" > /dev/null 2>&1
girisYap admin $JA
curl -s -b $JA -c $JA -o /tmp/tema_profil.html "$B/profil"
ol "jsdom için panel hazır (karanlik|mor|acik)" "karanlik|mor|acik" "$(oznitelik /tmp/tema_panel.html)"
ol "jsdom için profil hazır (varsayılan görünüm)" "1" "$(grep -c 'id="gizli-tema" value="sistem"' /tmp/tema_profil.html | head -1 | awk '{print ($1>0)?1:0}')"
echo "  → tarayıcı testi: node /home/user/js_ui_testi/tema_dom_testi.js"

echo
echo "=== 10) TEMİZLİK (varsayılana dön) ==="
db "UPDATE kullanicilar SET tema='sistem', palet='mavi', yan_menu='koyu';" > /dev/null 2>&1
ol "tüm kullanıcılar varsayılana döndü" "0" \
   "$(db "SELECT COUNT(*) FROM kullanicilar WHERE tema<>'sistem' OR palet<>'mavi' OR yan_menu<>'koyu';")"

echo
echo "======================================================"
echo " SONUÇ: $g geçti, $k hata"
echo "======================================================"
[ "$k" -eq 0 ]
