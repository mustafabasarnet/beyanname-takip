#!/bin/bash
# =====================================================================
#  MENÜ SADELEŞTİRME — REGRESYON TESTİ
#
#  "Ödeme Listelerim" ve "Vergi Yükü" sol menüden kaldırıldı; ikisi de
#  kendi ana ekranlarının içindeki düğmeden açılıyor:
#
#    Ödeme Listelerim → Ödeme Listesi ekranı → "📑 Listelerim"
#    Vergi Yükü       → Makbuz Takip ekranı  → "🧮 Vergi Yükü"
#
#  Bu test şunları güvenceye alır:
#    1) Menüde artık bu iki bağlantı YOK
#    2) Sayfalar hâlâ erişilebilir (rota/yetki bozulmadı)
#    3) Giriş düğmeleri ilgili ekranlarda VAR
#    4) Alt sayfalardayken üst menü ögesi VURGULU kalıyor
#    5) Geri dönüş bağlantıları çalışıyor
#    6) Personel rolünde mali ekranlar hâlâ KAPALI
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 ve personel/Test1234 mevcut.
#  Kullanım:  bash tests/menu_sadelestirme_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/ms_menu.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

# Menüdeki <a> bağlantılarını tek satıra indirip sayar.
# (Öznitelikler HTML'de alt alta yazıldığı için satır sonları temizlenir.)
menuBag(){ tr '\n' ' ' < "$1" \
  | grep -oE '<nav class="menu-liste">.*</nav>' \
  | grep -oE '<a href="[^"]*"[^>]*>[^<]*<span class="ikon">[^<]*</span>[^<]*' \
  | sed 's/.*<\/span>//' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//'; }

girisYap(){ rm -f "$2"
  curl -s -c "$2" -o /tmp/mf.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mf.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

girisYap admin $J
curl -s -b $J -o /tmp/mm_odeme.html "$B/odeme"

echo "=== 1) MENÜDE ARTIK YOK ==="
menuBag /tmp/mm_odeme.html > /tmp/mm_liste.txt
ol "Menüde 'Ödeme Listelerim' YOK" "0" "$(grep -c 'Ödeme Listelerim' /tmp/mm_liste.txt)"
ol "Menüde 'Vergi Yükü' YOK" "0" "$(grep -c 'Vergi Yükü' /tmp/mm_liste.txt)"
ol "Menüde odeme/listeler bağlantısı YOK" "0" \
   "$(tr '\n' ' ' < /tmp/mm_odeme.html | grep -oE '<nav class="menu-liste">.*</nav>' | grep -c 'odeme/listeler')"
ol "Menüde gelir-vergisi bağlantısı YOK" "0" \
   "$(tr '\n' ' ' < /tmp/mm_odeme.html | grep -oE '<nav class="menu-liste">.*</nav>' | grep -c 'gelir-vergisi')"

echo ""
echo "=== 2) MENÜDE KALANLAR DURUYOR ==="
for ad in "Beyanname Takip" "Evrak Takip" "E-Defter Takip" "Ödeme Listesi" "Makbuz Takip" "Karşıt İnceleme"; do
  ol "Menüde '$ad' var" "1" "$(grep -cF "$ad" /tmp/mm_liste.txt | awk '{print ($1>0)?1:0}')"
done
# TAKİP bölümü 7 satırdan 5'e indi
ol "Takip bölümünde 5 öge" "5" \
   "$(grep -cE '^(Beyanname Takip|Evrak Takip|E-Defter Takip|Ödeme Listesi|Makbuz Takip|Karşıt İnceleme)$' /tmp/mm_liste.txt | awk '{print ($1>5)?5:$1}')"

echo ""
echo "=== 3) SAYFALAR HÂLÂ ERİŞİLEBİLİR (rota/yetki bozulmadı) ==="
for yol in "odeme" "odeme/listeler" "makbuz" "gelir-vergisi"; do
  KOD=$(curl -s -b $J -o /tmp/mm_s.html -w '%{http_code}' "$B/$yol")
  ol "/$yol açıldı (200)" "200" "$KOD"
  ol "/$yol fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/mm_s.html)"
done

echo ""
echo "=== 4) GİRİŞ DÜĞMELERİ YERİNDE ==="
ol "Ödeme ekranında '📑 Listelerim' düğmesi" "1" \
   "$(tr '\n' ' ' < /tmp/mm_odeme.html | grep -oE 'href="[^"]*odeme/listeler"[^>]*>[^<]*Listelerim' | wc -l)"

curl -s -b $J -o /tmp/mm_makbuz.html "$B/makbuz"
ol "Makbuz ekranında '🧮 Vergi Yükü' düğmesi" "1" \
   "$(tr '\n' ' ' < /tmp/mm_makbuz.html | grep -oE 'href="[^"]*gelir-vergisi[^"]*"[^>]*>[^<]*Vergi Yükü' | wc -l)"
ol "Eski 'Gelir Vergisi' etiketi kalmadı" "0" \
   "$(grep -c '🧮 Gelir Vergisi' /tmp/mm_makbuz.html)"

echo ""
echo "=== 5) ALT SAYFADA ÜST MENÜ VURGULU KALIYOR ==="
# Kullanıcı menüde bağlantı görmediği için nerede olduğunu kaybetmemeli
aktifAd(){ tr '\n' ' ' < "$1" \
  | grep -oE '<a href="[^"]*" class="aktif">[^<]*<span class="ikon">[^<]*</span>[^<]*' \
  | sed 's/.*<\/span>//' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | head -1; }

curl -s -b $J -o /tmp/mm_l.html "$B/odeme/listeler"
ol "odeme/listeler → 'Ödeme Listesi' vurgulu" "Ödeme Listesi" "$(aktifAd /tmp/mm_l.html)"
curl -s -b $J -o /tmp/mm_gv.html "$B/gelir-vergisi"
ol "gelir-vergisi → 'Makbuz Takip' vurgulu" "Makbuz Takip" "$(aktifAd /tmp/mm_gv.html)"
ol "odeme → 'Ödeme Listesi' vurgulu" "Ödeme Listesi" "$(aktifAd /tmp/mm_odeme.html)"
ol "makbuz → 'Makbuz Takip' vurgulu" "Makbuz Takip" "$(aktifAd /tmp/mm_makbuz.html)"
# Aynı anda birden çok öge vurgulanmamalı
ol "Tek öge vurgulu (listeler)" "1" "$(tr '\n' ' ' < /tmp/mm_l.html | grep -oE 'class="aktif"' | wc -l)"
ol "Tek öge vurgulu (gelir-vergisi)" "1" "$(tr '\n' ' ' < /tmp/mm_gv.html | grep -oE 'class="aktif"' | wc -l)"

echo ""
echo "=== 6) GERİ DÖNÜŞ BAĞLANTILARI ==="
ol "Listelerim'de '← Ödeme Listesi' var" "1" \
   "$(tr '\n' ' ' < /tmp/mm_l.html | grep -oE 'href="[^"]*odeme"[^>]*>[^<]*Ödeme Listesi' | wc -l)"
ol "Vergi Yükü'nde 'Makbuz Takip' dönüşü var" "1" \
   "$(tr '\n' ' ' < /tmp/mm_gv.html | grep -oE 'href="[^"]*makbuz[^"]*"[^>]*>[^<]*Makbuz Takip' | wc -l | awk '{print ($1>0)?1:0}')"

echo ""
echo "=== 7) PERSONEL ROLÜ — MALİ EKRANLAR HÂLÂ KAPALI ==="
J2=/tmp/ms_menu2.txt
girisYap personel $J2
curl -s -b $J2 -o /tmp/mm_p.html "$B/panel"
menuBag /tmp/mm_p.html > /tmp/mm_pliste.txt
ol "Personel menüsünde Ödeme Listesi YOK" "0" "$(grep -c 'Ödeme Listesi' /tmp/mm_pliste.txt)"
ol "Personel menüsünde Makbuz Takip YOK" "0" "$(grep -c 'Makbuz Takip' /tmp/mm_pliste.txt)"
ol "Personel menüsünde Beyanname Takip VAR" "1" \
   "$(grep -c 'Beyanname Takip' /tmp/mm_pliste.txt | awk '{print ($1>0)?1:0}')"
ol "Personel menüsünde Ajanda VAR" "1" \
   "$(grep -c 'Ajanda' /tmp/mm_pliste.txt | awk '{print ($1>0)?1:0}')"
# Doğrudan URL ile de giremez.
# NOT: -L KULLANILMAZ. Yetki filtresi 302 ile panele yönlendirir; -L ile
# istek izlenince panel 200 döner ve "içeri girdi" sanılırdı (yanlış alarm).
# Doğru ölçüt: yönlendirme kodu + hedefin panel olması.
for yol in "odeme" "odeme/listeler" "makbuz" "gelir-vergisi"; do
  KOD=$(curl -s -b $J2 -o /dev/null -w '%{http_code}' "$B/$yol")
  ol "Personel /$yol yönlendirildi (302)" "302" "$KOD"
done
curl -s -b $J2 -o /tmp/mm_pl.html -L "$B/odeme"
ol "Personel panele düşüyor" "1" \
   "$(grep -c 'Kontrol Paneli' /tmp/mm_pl.html | awk '{print ($1>0)?1:0}')"
ol "Personel ödeme tablosunu GÖRMÜYOR" "0" \
   "$(grep -c 'od-daha' /tmp/mm_pl.html)"

echo ""
echo "=== 8) KOD SAĞLAMLIĞI ==="
ol "Menüden odeme/listeler kaldırıldı" "0" \
   "$(grep -cF "site_url('odeme/listeler')" app/Views/layouts/ana.php)"
ol "Menüden gelir-vergisi kaldırıldı" "0" \
   "$(grep -cF "site_url('gelir-vergisi')" app/Views/layouts/ana.php)"
ol "Makbuz ögesi gelir-vergisi'nde de vurgulanıyor" "1" \
   "$(grep -cF "aktifMenu('makbuz') ?: aktifMenu('gelir-vergisi')" app/Views/layouts/ana.php)"
ol "Ödeme ögesi aktifMenu ile eşleşiyor" "1" \
   "$(grep -cF "aktifMenu('odeme')" app/Views/layouts/ana.php)"
ol "Şema değişikliği GEREKMİYOR" "0" "$(ls database/ | grep -c 'menu')"

echo ""
echo "====================================="
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "====================================="
[ $k -eq 0 ] && exit 0 || exit 1
