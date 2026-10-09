#!/bin/bash
# =====================================================================
#  KİŞİSEL YAPIŞKAN NOTLAR (sticky) — uçtan uca test
#
#  Kullanıcı isteği: Kişisel Notlar'a sabit, tamamen kişiye özel
#  yapışkan not alanı (renkli kartlar, 📌 sabitleme, sürükle-sırala).
#
#  Test ettikleri:
#    1) Sekme: Kişisel Notlar sayfasında 📌 Yapışkan Notlar sekmesi var
#    2) Sayfa açılır; boş durum mesajı; kart sayacı
#    3) Ekle: kart oluşur, renk whitelist'e uyar, sıra en sona düşer
#    4) Metin güncelleme: kaydedilir; 1000 karakter sınırı (400)
#    5) Renk: geçerli renk kabul, geçersiz renk reddi (400)
#    6) Sabitleme: sabit kart en üstte, "Sabitlenenler" başlığı çıkar
#    7) Sürükle sıralama: verilen sıra korunur
#    8) Sil: kart kalkar; olmayan kart 404
#    9) XSS: metin <script> olarak girse de çıktıda kaçırılır
#   10) GİZLİLİK: yönetici (admin) personelin kartlarını GÖREMEZ
#   11) GİZLİLİK: müşavir personelin kartlarını GÖREMEZ / DEĞİŞTİREMEZ
#   12) GİZLİLİK: personel, başkasının kart id'sini değiştiremez/silemez
#   13) Sıralama: başkasının id'si karışık listeye girse de yok sayılır
#   14) CSRF'siz POST reddedilir (403)
#   15) Tanımsız kullanıcı (oturumsuz) sayfaya giremez (302)
#   16) Günlük sekmesi (mevcut özellik) bozulmadı
#   17) Yönetici personelin kart sayısını göremez (sayaç kapsamı)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            database/migration_sticky_not.sql koşulmuş olmalı.
#  Kullanım:  bash tests/sticky_not_testi.sh
#  Not: Test verisi "STK-" önekiyle kurulur ve sonunda temizlenir.
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
JP=/tmp/stk_personel.txt
JA=/tmp/stk_admin.txt
JM=/tmp/stk_musavir.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/stk_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/stk_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }
tok(){ curl -s -b "$1" -c "$1" "$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
# AJAX POST → gövde /tmp/stk_cevap.json, kod döner
post(){ local jar="$1" url="$2"; shift 2; local t; t=$(tok "$jar" "$B/kisisel/yapiskan")
  curl -s -b "$jar" -c "$jar" -X POST -H "X-Requested-With: XMLHttpRequest" \
    -o /tmp/stk_cevap.json -w '%{http_code}' -d "csrf_beyanname=$t" "$@" "$B$url"; }
alan(){ python3 -c "import json;d=json.load(open('/tmp/stk_cevap.json'));print(d.get('$1',''))" 2>/dev/null; }
kartSayi(){ python3 -c "import json;d=json.load(open('/tmp/stk_cevap.json'));print(d.get('listeHtml','').count('class=\"yk-kart\"'))" 2>/dev/null; }
# Duvarın kart id sırası (DOM sırası)
kartIdler(){ python3 -c "
import json,re
d=json.load(open('/tmp/stk_cevap.json'))
print(','.join(re.findall(r'data-id=\"(\d+)\"', d.get('listeHtml',''))))" 2>/dev/null; }

echo "=== 0) HAZIRLIK ==="
# Sahip olarak GEÇİCİ bir kullanıcı kullanılır (stk_test). Gerçek kullanıcıların
# notlarına dokunulmaz; test sonunda kullanıcı silinir (notlar CASCADE ile gider).
# Parola, personel kullanıcısının hash'iyle aynıdır (Test1234).
db "DELETE FROM kisisel_sticky_notlar WHERE metin LIKE 'STK-%' OR metin IN ('CSRF-SIZ','PERS-DEGISTIRDI','ADMIN-DEGISTIRDI');" > /dev/null 2>&1
db "DELETE FROM kisisel_sticky_notlar WHERE kullanici_id IN (SELECT id FROM kullanicilar WHERE kullanici_adi='stk_test');" > /dev/null 2>&1
db "DELETE FROM kullanicilar WHERE kullanici_adi='stk_test';" > /dev/null 2>&1
db "INSERT INTO kullanicilar (ad_soyad,kullanici_adi,eposta,sifre,rol,aktif,created_at,updated_at)
    SELECT 'STK TEST KULLANICI','stk_test','stk_test@example.com',sifre,'personel',1,NOW(),NOW()
      FROM kullanicilar WHERE kullanici_adi='personel' LIMIT 1;" > /dev/null 2>&1
STK_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='stk_test';")
PERS_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='personel';")
ADMIN_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='admin';")
MUS_ID=$(db "SELECT id FROM kullanicilar WHERE kullanici_adi='musavir';")
ol "geçici test kullanıcısı oluşturuldu" "1" "$([ -n "$STK_ID" ] && echo 1 || echo 0)"

JS=/tmp/stk_sahip.txt
girisYap stk_test $JS
girisYap personel $JP
girisYap admin $JA
girisYap musavir $JM
ol "sahip (stk_test) oturumu açıldı" "200" "$(curl -s -b $JS -o /dev/null -w '%{http_code}' $B/kisisel/yapiskan)"

echo
echo "=== 1) SEKME VE SAYFA ==="
curl -s -b $JS -c $JS -o /tmp/stk_sayfa.html "$B/kisisel/yapiskan"
ol "sayfa 200 (oturumlu)" "200" "$(curl -s -b $JS -o /dev/null -w '%{http_code}' $B/kisisel/yapiskan)"
ol "📌 Yapışkan Notlar sekmesi var" "1" "$(grep -c '📌 Yapışkan Notlar' /tmp/stk_sayfa.html | awk '{print ($1>0)?1:0}')"
ol "Günlük sekmesi de var" "1" "$(grep -c '📅 Günlük / To-Do' /tmp/stk_sayfa.html | awk '{print ($1>0)?1:0}')"
ol "sekmede yapışkan aktif işaretli" "1" "$(grep -c 'class="aktif" *>\|class="aktif">📌' /tmp/stk_sayfa.html | awk '{print ($1>0)?1:0}')"
ol "duvar konteyneri var" "1" "$(grep -c 'id="yk-duvar"' /tmp/stk_sayfa.html | awk '{print ($1>0)?1:0}')"
ol "arama kutusu var" "1" "$(grep -c 'id="yk-ara"' /tmp/stk_sayfa.html | awk '{print ($1>0)?1:0}')"

echo
echo "=== 2) EKLE ==="
ol "boş durum mesajı (yeni kullanıcı)" "1" "$(grep -c 'Henüz yapışkan notunuz yok' /tmp/stk_sayfa.html | awk '{print ($1>0)?1:0}')"
KOD=$(post $JS /kisisel/yapiskan/ekle -d "metin=STK-1 ilk not" -d "renk=turkuaz")
ol "ekle 200" "200" "$KOD"
ol "ekle yanıtı durum=true" "True" "$(alan durum)"
ol "duvarda 1 kart" "1" "$(kartSayi)"
ID1=$(db "SELECT id FROM kisisel_sticky_notlar WHERE metin='STK-1 ilk not' AND kullanici_id=$STK_ID;")
ol "DB'ye kullanıcı id'siyle yazıldı" "1" "$([ -n "$ID1" ] && echo 1 || echo 0)"
post $JS /kisisel/yapiskan/ekle -d "metin=STK-2 ikinci" -d "renk=pembe" > /dev/null
post $JS /kisisel/yapiskan/ekle -d "metin=STK-3 üçüncü" -d "renk=nane" > /dev/null
ID3=$(db "SELECT id FROM kisisel_sticky_notlar WHERE metin='STK-3 üçüncü' AND kullanici_id=$STK_ID;")
ol "yeni kart sıra sonuna (sira en büyük)" "1" \
   "$(db "SELECT CASE WHEN sira = (SELECT MAX(sira) FROM kisisel_sticky_notlar WHERE kullanici_id=$STK_ID) THEN 1 ELSE 0 END FROM kisisel_sticky_notlar WHERE id=$ID3;")"
ol "geçersiz renk ekleme → sarı'ya düşer" "sari" \
   "$(post $JS /kisisel/yapiskan/ekle -d "metin=STK-4 renk yok" -d "renk=kirmizi" > /dev/null; db "SELECT renk FROM kisisel_sticky_notlar WHERE metin='STK-4 renk yok' AND kullanici_id=$STK_ID;")"

echo
echo "=== 3) METİN GÜNCELLEME ==="
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" -d "metin=STK-1 güncellendi")
ol "metin güncelle 200" "200" "$KOD"
ol "DB metin değişti" "STK-1 güncellendi" "$(db "SELECT metin FROM kisisel_sticky_notlar WHERE id=$ID1;")"
ol "metin yanıtında liste YOK (imleç korunur)" "" "$(alan listeHtml)"
UZUN=$(python3 -c "print('a'*1001)")
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" --data-urlencode "metin=$UZUN")
ol "1001 karakter → 400" "400" "$KOD"
ol "  DB değişmedi" "STK-1 güncellendi" "$(db "SELECT metin FROM kisisel_sticky_notlar WHERE id=$ID1;")"
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" --data-urlencode "metin=$(python3 -c "print('b'*1000)")")
ol "tam 1000 karakter kabul" "200" "$KOD"

echo
echo "=== 4) RENK ==="
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" -d "renk=pembe")
ol "geçerli renk (pembe) 200" "200" "$KOD"
ol "DB renk = pembe" "pembe" "$(db "SELECT renk FROM kisisel_sticky_notlar WHERE id=$ID1;")"
ol "duvar yenilendi (liste var)" "1" "$([ -n "$(alan listeHtml)" ] && echo 1 || echo 0)"
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID1" -d "renk=kirmizi")
ol "geçersiz renk → 400" "400" "$KOD"
ol "  renk değişmedi" "pembe" "$(db "SELECT renk FROM kisisel_sticky_notlar WHERE id=$ID1;")"

echo
echo "=== 5) SABİTLEME ==="
KOD=$(post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "sabit=1")
ol "sabitle 200" "200" "$KOD"
ol "sabit alan DB'de 1" "1" "$(db "SELECT sabit FROM kisisel_sticky_notlar WHERE id=$ID3;")"
ol "'Sabitlenenler' başlığı çıktı" "1" "$(alan listeHtml | grep -c 'Sabitlenenler' | awk '{print ($1>0)?1:0}')"
ol "sabit kart duvarın İLK kartı" "$ID3" "$(kartIdler | cut -d, -f1)"
post $JS /kisisel/yapiskan/guncelle -d "id=$ID3" -d "sabit=0" > /dev/null
ol "sabit kaldırılınca 'Sabitlenenler' başlığı kalkar" "0" \
   "$(alan listeHtml | grep -c 'Sabitlenenler' | awk '{print ($1>0)?1:0}')"

echo
echo "=== 6) SÜRÜKLE SIRALAMA ==="
ID2=$(db "SELECT id FROM kisisel_sticky_notlar WHERE metin='STK-2 ikinci' AND kullanici_id=$STK_ID;")
ID4=$(db "SELECT id FROM kisisel_sticky_notlar WHERE metin='STK-4 renk yok' AND kullanici_id=$STK_ID;")
KOD=$(post $JS /kisisel/yapiskan/sirala -d "idler[]=$ID4" -d "idler[]=$ID2" -d "idler[]=$ID1" -d "idler[]=$ID3")
ol "sırala 200" "200" "$KOD"
ol "yeni sıra DOM'da korundu (4,2,1,3)" "$ID4,$ID2,$ID1,$ID3" "$(kartIdler)"
ol "DB sira değerleri sıralı (0..3)" "0,1,2,3" \
   "$(db "SELECT GROUP_CONCAT(sira ORDER BY sira) FROM kisisel_sticky_notlar WHERE id IN ($ID4,$ID2,$ID1,$ID3);")"

echo
echo "=== 7) SİL ==="
post $JS /kisisel/yapiskan/sil -d "id=$ID4" > /dev/null
ol "silinen kart DB'den gitti" "0" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE id=$ID4;")"
ol "duvardan da kalktı" "0" "$(alan listeHtml | grep -c "data-id=\"$ID4\"" | awk '{print ($1>0)?1:0}')"
KOD=$(post $JS /kisisel/yapiskan/sil -d "id=$ID4")
ol "olmayan kart sil → 404" "404" "$KOD"

echo
echo "=== 8) XSS (kaçış) ==="
post $JS /kisisel/yapiskan/ekle -d "metin=STK-X <script>alert(1)</script>" -d "renk=sari" > /dev/null
ol "ham <script> duvarda YOK" "0" "$(alan listeHtml | grep -c '<script>alert(1)' | awk '{print ($1>0)?1:0}')"
ol "kaçırılmış &lt;script&gt; görünüyor" "1" "$(alan listeHtml | grep -c '&lt;script&gt;' | awk '{print ($1>0)?1:0}')"
XID=$(db "SELECT id FROM kisisel_sticky_notlar WHERE metin LIKE 'STK-X%' AND kullanici_id=$STK_ID;")

echo
echo "=== 9) GİZLİLİK: YÖNETİCİ PERSONELİN KARTLARINI GÖREMEZ ==="
curl -s -b $JA -c $JA -o /tmp/stk_admin_sayfa.html "$B/kisisel/yapiskan"
ol "yönetici sayfası açılır" "200" "$(curl -s -b $JA -o /dev/null -w '%{http_code}' $B/kisisel/yapiskan)"
ol "yönetici sayfasında personelin notu YOK" "0" "$(grep -c 'STK-' /tmp/stk_admin_sayfa.html)"
ol "yönetici sayacı yalnız kendi kartlarını sayar (0)" "1" "$(grep -c '0 / 200 not' /tmp/stk_admin_sayfa.html | awk '{print ($1>0)?1:0}')"
KOD=$(post $JA /kisisel/yapiskan/guncelle -d "id=$ID1" -d "metin=ADMIN-DEGISTIRDI")
ol "yönetici personelin kartını DEĞİŞTİREMEZ (400)" "400" "$KOD"
ol "  metin değişmedi (1000 karakter, ADMIN yazısı yok)" "1" "$(db "SELECT IF(metin NOT LIKE '%ADMIN%' AND CHAR_LENGTH(metin)=1000,1,0) FROM kisisel_sticky_notlar WHERE id=$ID1;")"
KOD=$(post $JA /kisisel/yapiskan/sil -d "id=$ID1")
ol "yönetici personelin kartını SİLEMEZ (404)" "404" "$KOD"
ol "  kart hâlâ DB'de" "1" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE id=$ID1;")"
SIRA_ONCE=$(db "SELECT sira FROM kisisel_sticky_notlar WHERE id=$ID3;")
KOD=$(post $JA /kisisel/yapiskan/sirala -d "idler[]=$ID1" -d "idler[]=$ID3")
ol "yönetici sıralamada personelin id'lerini yok sayar" "0" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE id IN ($ID1,$ID3) AND kullanici_id=$ADMIN_ID;")"
ol "  yönetici sıralaması başka kullanıcının sırasını değiştirmedi" "$SIRA_ONCE" "$(db "SELECT sira FROM kisisel_sticky_notlar WHERE id=$ID3;")"

echo
echo "=== 10) GİZLİLİK: MÜŞAVİR ==="
curl -s -b $JM -c $JM -o /tmp/stk_mus_sayfa.html "$B/kisisel/yapiskan"
ol "müşavir personelin notunu göremez" "0" "$(grep -c 'STK-' /tmp/stk_mus_sayfa.html)"
KOD=$(post $JM /kisisel/yapiskan/guncelle -d "id=$XID" -d "renk=mavi")
ol "müşavir personelin rengini değiştiremez (400)" "400" "$KOD"
KOD=$(post $JM /kisisel/yapiskan/sil -d "id=$XID")
ol "müşavir personelin notunu silemez (404)" "404" "$KOD"
ol "  not hâlâ DB'de" "1" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE id=$XID;")"

echo
echo "=== 11) GİZLİLİK: PERSONEL → YÖNETİCİ KARTI ==="
ADMIN_KART=$(db "SELECT id FROM kisisel_sticky_notlar WHERE kullanici_id=$ADMIN_ID LIMIT 1;")
if [ -z "$ADMIN_KART" ]; then
  db "INSERT INTO kisisel_sticky_notlar (kullanici_id,metin,renk,sabit,sira,created_at,updated_at) VALUES ($ADMIN_ID,'STK-ADMIN gizli','mavi',0,0,NOW(),NOW());"
  ADMIN_KART=$(db "SELECT id FROM kisisel_sticky_notlar WHERE metin='STK-ADMIN gizli';")
fi
KOD=$(post $JP /kisisel/yapiskan/guncelle -d "id=$ADMIN_KART" -d "metin=PERS-DEGISTIRDI")
ol "personel (başka kullanıcı) sahibin kartını değiştiremez (400)" "400" "$KOD"
ol "  yönetici kartı değişmedi" "STK-ADMIN gizli" "$(db "SELECT metin FROM kisisel_sticky_notlar WHERE id=$ADMIN_KART;")"

echo
echo "=== 12) CSRF ve OTURUM ==="
KOD=$(curl -s -b $JP -c $JP -o /dev/null -w '%{http_code}' -X POST -H "X-Requested-With: XMLHttpRequest" \
      -d "metin=CSRF-SIZ" $B/kisisel/yapiskan/ekle)
ol "CSRF'siz ekleme reddedildi (403)" "403" "$KOD"
ol "  CSRF'siz not oluşmadı" "0" "$(db "SELECT COUNT(*) FROM kisisel_sticky_notlar WHERE metin='CSRF-SIZ';")"
KOD=$(curl -s -o /dev/null -w '%{http_code}' $B/kisisel/yapiskan)
ol "oturumsuz erişim engellendi (302)" "302" "$KOD"

echo
echo "=== 13) GÜNLÜK SEKMESİ BOZULMADI ==="
ol "günlük sayfa 200" "200" "$(curl -s -b $JP -o /dev/null -w '%{http_code}' $B/kisisel)"
ol "günlük sayfasında kayıt alanı var" "1" \
   "$(curl -s -b $JP $B/kisisel | grep -c 'kn-not-metin' | awk '{print ($1>0)?1:0}')"

echo
echo "=== 14) TEMİZLİK ==="
db "DELETE FROM kisisel_sticky_notlar WHERE metin LIKE 'STK-%' OR metin IN ('CSRF-SIZ','PERS-DEGISTIRDI','ADMIN-DEGISTIRDI');" > /dev/null 2>&1
db "DELETE FROM kisisel_sticky_notlar WHERE kullanici_id=$STK_ID;" > /dev/null 2>&1
db "DELETE FROM kullanicilar WHERE id=$STK_ID;" > /dev/null 2>&1
ol "geçici kullanıcı ve notları silindi" "0" "$(db "SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi='stk_test';")"

echo
echo "======================================================"
echo " SONUÇ: $g geçti, $k hata"
echo "======================================================"
[ "$k" -eq 0 ]
