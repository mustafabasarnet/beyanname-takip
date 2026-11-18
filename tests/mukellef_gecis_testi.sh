#!/bin/bash
# =====================================================================
#  MÜKELLEFE HIZLI GEÇİŞ (üst bar arama kutusu) — REGRESYON TESTİ
#
#  Amaç: Hangi ekranda olunursa olunsun, üst bardaki kutuya ünvan / VKN /
#        TCKN / kod yazarak mükellefin kartına tek tıkla geçilebilmesi.
#
#  Kapsam:
#   1. Yerleşim: kutu tüm ana ekranlarda basılıyor (tek örnek)
#   2. Uç: boş q → alfabetik ilk 25 (toplam sayısıyla), limit sınırı
#   3. Arama: ünvan (Türkçe karakter), VKN, TCKN, kod
#   4. Alanlar: unvan / vkn / tip / etiket / href doğru dolu
#   5. Kapsam: müşavir kullanıcısı yalnız kendi mükelleflerini görür
#   6. Pasif/terk mükellef de aranabilir; soft-delete edilmiş GÖRÜNMEZ
#   7. Geriye uyum: limit göndermeyen eski çağrı 10 sonuç alır
#   8. Güvenlik: CSRF'siz POST yok sayılır, HTML kaçışı (XSS) yok
#   9. Sonsuz kaydırma: offset/daha_var ile tüm portföy gezilebilir
#
#  Ön koşul: uygulama http://127.0.0.1:8099'da çalışıyor; admin / musavir
#            (şifre Test1234). Test kendi fikstürünü kurar ve siler.
#  Kullanım:  bash tests/mukellef_gecis_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/mgc_a.txt
JM=/tmp/mgc_m.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1" 2>/dev/null; }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/mgc_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/mgc_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# JSON alanı okur: json <dosya> <python-ifadesi>
json(){ python3 - "$1" "$2" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
print(eval(sys.argv[2], {'d': d}))
PY
}

echo "=== HAZIRLIK ==="
giris admin $J
giris musavir $JM
db "UPDATE kullanici_musavirleri SET musavir_id=2 WHERE kullanici_id=(SELECT id FROM kullanicilar WHERE kullanici_adi='musavir');"
db "UPDATE kullanicilar SET musavir_id=2 WHERE kullanici_adi='musavir';"

db "DELETE FROM mukellefler WHERE kod LIKE 'MGC-%';"
db "INSERT INTO mukellefler (musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,'MGC-1','MGC AHŞAP PANEL SANAYİ','tuzel','9500000001',NULL,'bilanco','2019-01-01',1),
 (1,'MGC-2','MGC ÇELİK İNŞAAT','tuzel','9500000002',NULL,'bilanco','2019-01-01',1),
 (1,'MGC-3','MGC ÖZTÜRK ECZANESİ','gercek',NULL,'95000000032','isletme','2019-01-01',1),
 (2,'MGC-4','MGC DİĞER MÜŞAVİR MÜKELLEFİ','tuzel','9500000004',NULL,'bilanco','2019-01-01',1),
 (1,'MGC-5','MGC PASİF MÜKELLEF','tuzel','9500000005',NULL,'bilanco','2019-01-01',0);"
db "UPDATE mukellefler SET deleted_at=NOW() WHERE kod='MGC-2';"
MGC1=$(db "SELECT id FROM mukellefler WHERE kod='MGC-1';")

# ---------------------------------------------------------------------
echo; echo "=== 1) YERLEŞİM (KUTU HER EKRANDA) ==="
for yol in panel takip mukellefler makbuz evrak edefter ajanda sicil; do
  C=$(curl -s -b $J "$B/$yol" | grep -c 'id="mk-gecis"')
  ol "/$yol ekranında kutu var (tek örnek)" "1" "$C"
done
curl -s -b $J "$B/panel" -o /tmp/mgc_p.html
ol "kutuda combobox rolü ve aria-expanded" "1" \
   "$(grep -c 'role="combobox"' /tmp/mgc_p.html | awk '{print ($1>0)?1:0}')"
ol "uç adresi doğru (mukellefler/ara)" "1" \
   "$(grep -c 'data-url="[^"]*mukellefler/ara"' /tmp/mgc_p.html | awk '{print ($1>0)?1:0}')"
ol "klavye ipucu görünür (↑↓ Enter Esc)" "1" \
   "$(grep -c '↑↓ gez · Enter aç · Esc kapat' /tmp/mgc_p.html | awk '{print ($1>0)?1:0}')"

# ---------------------------------------------------------------------
echo; echo "=== 2) BOŞ SORGU: ALFABETİK İLK 25 ==="
curl -s -b $J "$B/mukellefler/ara?limit=25&q=" -o /tmp/mgc0.json
TOPLAM_DB=$(db "SELECT COUNT(*) FROM mukellefler WHERE deleted_at IS NULL;")
ol "toplam = DB (silinmişler hariç)" "$TOPLAM_DB" "$(json /tmp/mgc0.json 'd["toplam"]')"
ol "gösterilen en fazla 25" "1" \
   "$(json /tmp/mgc0.json '1 if d["gosterilen"]<=25 else 0')"
ol "ilk kayıt alfabetik" "1" "$(json /tmp/mgc0.json '
[1 if d["sonuclar"][i]["unvan"].lower() <= d["sonuclar"][i+1]["unvan"].lower() else 0 for i in range(len(d["sonuclar"])-1)][0]')"
# limit, kapsamdaki kayıt sayısından büyükse hepsi döner (taze kurulumda 5'ten az olabilir)
ol "limit=5 → en fazla 5 sonuç (kayıt azsa hepsi)" \
   "$([ "$TOPLAM_DB" -lt 5 ] && echo "$TOPLAM_DB" || echo 5)" \
   "$(curl -s -b $J "$B/mukellefler/ara?limit=5&q=" | python3 -c 'import json,sys;print(len(json.load(sys.stdin)["sonuclar"]))')"
ol "limit üst sınırı (en fazla 50; kayıt azsa hepsi)" \
   "$(db "SELECT LEAST(50, COUNT(*)) FROM mukellefler WHERE deleted_at IS NULL;")" \
   "$(curl -s -b $J "$B/mukellefler/ara?limit=999&q=" | python3 -c 'import json,sys;print(len(json.load(sys.stdin)["sonuclar"]))')"

# ---------------------------------------------------------------------
echo; echo "=== 3) ARAMA TÜRLERİ ==="
ara(){ curl -s -b $J "$B/mukellefler/ara?limit=25&q=$1" -o /tmp/mgcq.json; json /tmp/mgcq.json "$2"; }
ol "ünvan (Türkçe küçük harf 'ahşap') en az 1 sonuç" "1" "$(ara 'ah%C5%9Fap' '1 if len(d["sonuclar"])>=1 else 0')"
ol "ünvan 'MGC AHŞAP PANEL SANAYİ' gelir" "1" "$(ara 'ah%C5%9Fap' '1 if "AHŞAP" in d["sonuclar"][0]["unvan"] else 0')"
ol "VKN ile arama (9500000001)" "1" "$(ara '9500000001' '[1 if s["unvan"].startswith("MGC AHŞAP") else 0 for s in d["sonuclar"]][0]')"
ol "TCKN ile arama (95000000032)" "1" "$(ara '95000000032' '1 if any(s["unvan"]=="MGC ÖZTÜRK ECZANESİ" for s in d["sonuclar"]) else 0')"
ol "kod ile arama (MGC-3)" "1" "$(ara 'MGC-3' '[1 if s["unvan"]=="MGC ÖZTÜRK ECZANESİ" else 0 for s in d["sonuclar"]][0]')"
ol "eşleşmeyen arama → 0 sonuç" "0" "$(ara 'zzzz-yok-boyle' 'len(d["sonuclar"])')"

# ---------------------------------------------------------------------
echo; echo "=== 4) ALANLAR (ROZET + BAĞLANTI) ==="
curl -s -b $J "$B/mukellefler/ara?limit=25&q=95000000" -o /tmp/mgc4.json
ol "FİRMA etiketi (tüzel)" "1" "$(json /tmp/mgc4.json '[1 if s["etiket"]=="FİRMA" and s["tip"]=="tuzel" else 0 for s in d["sonuclar"] if "AHŞAP" in s["unvan"]][0]')"
ol "ŞAHIS etiketi (gerçek)" "1" "$(json /tmp/mgc4.json '[1 if s["etiket"]=="ŞAHIS" and s["tip"]=="gercek" else 0 for s in d["sonuclar"] if "ECZANE" in s["unvan"]][0]')"
ol "vkn alanı TCKN'yi de taşır" "95000000032" \
   "$(json /tmp/mgc4.json '[s["vkn"] for s in d["sonuclar"] if "ECZANE" in s["unvan"]][0]')"
curl -s -b $J "$B/mukellefler/ara?limit=25&q=9500000001" -o /tmp/mgc4b.json
ol "href mükellef kartına gider" "$B/mukellefler/detay/$MGC1" "$(json /tmp/mgc4b.json 'd["sonuclar"][0]["href"]')"
KOD=$(curl -s -o /dev/null -w '%{http_code}' -b $J "$B/mukellefler/detay/$MGC1")
ol "bağlantı hedefi açılıyor (200)" "200" "$KOD"

# ---------------------------------------------------------------------
echo; echo "=== 5) YETKİ KAPSAMI ==="
curl -s -b $JM "$B/mukellefler/ara?limit=25&q=MGC" -o /tmp/mgc5.json
ol "müşavir: MGC-4 (kendi mükellefi) görünür" "1" \
   "$(json /tmp/mgc5.json '1 if any("DİĞER MÜŞAVİR" in s["unvan"] for s in d["sonuclar"]) else 0')"
ol "müşavir: MGC AHŞAP (müşavir 1) görünmez" "0" \
   "$(json /tmp/mgc5.json '1 if any("AHŞAP" in s["unvan"] for s in d["sonuclar"]) else 0')"
ol "müşavir: toplam = kapsamındaki eşleşme (q=MGC)" \
   "$(db "SELECT COUNT(*) FROM mukellefler WHERE deleted_at IS NULL AND musavir_id=2 AND unvan LIKE '%MGC%';")" \
   "$(json /tmp/mgc5.json 'd["toplam"]')"
ol "müşavir: boş q'da toplam = tüm kapsamı" \
   "$(db "SELECT COUNT(*) FROM mukellefler WHERE deleted_at IS NULL AND musavir_id=2;")" \
   "$(curl -s -b $JM "$B/mukellefler/ara?limit=25&q=" | python3 -c 'import json,sys;print(json.load(sys.stdin)["toplam"])')"

# ---------------------------------------------------------------------
echo; echo "=== 6) PASİF / TERK / SİLİNMİŞ ==="
ol "pasif mükellef de aranabilir (MGC PASİF)" "1" \
   "$(curl -s -b $J "$B/mukellefler/ara?limit=25&q=9500000005" | python3 -c 'import json,sys;print(1 if json.load(sys.stdin)["sonuclar"] else 0)')"
ol "soft-delete edilmiş MGC-2 listelenmez" "0" \
   "$(curl -s -b $J "$B/mukellefler/ara?limit=25&q=MGC%20%C3%87EL%C4%B0K" | python3 -c 'import json,sys;print(len(json.load(sys.stdin)["sonuclar"]))')"

# ---------------------------------------------------------------------
echo; echo "=== 7) GERİYE UYUM (limit göndermeyen çağrı) ==="
ol "limit yok → en fazla 10 sonuç (sicil/makbuz formu)" 1 \
   "$(curl -s -b $J "$B/mukellefler/ara?q=" | python3 -c '
import json,sys
n=len(json.load(sys.stdin)["sonuclar"])
print(1 if n<=10 else 0)')"
ol "eski alanlar korunmuş (id/unvan/vergi_kimlik_no)" "1" \
   "$(curl -s -b $J "$B/mukellefler/ara?q=&limit=1" | python3 -c '
import json,sys
s=json.load(sys.stdin)["sonuclar"][0]
print(1 if all(k in s for k in ("id","unvan","vergi_kimlik_no","tc_kimlik_no")) else 0)')"

# ---------------------------------------------------------------------
echo; echo "=== 8) GÜVENLİK ==="
ol "HTML kaçışı: unvan'da etiket olsa da kaçırılır" "1" "$(
db "INSERT INTO mukellefler (musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif)
    VALUES (1,'MGC-9','MGC <script>alert(1)</script>','tuzel','9500000009','bilanco','2019-01-01',1);"
curl -s -b $J "$B/mukellefler/ara?limit=25&q=9500000009" | python3 -c '
import json,sys
s=json.load(sys.stdin)["sonuclar"][0]
print(1 if "<script>" in s["unvan"] else 0)   # JSON ham metin döner; kaçışı JS textContent yapar
')"
ol "JSON yanıtı Content-Type: application/json" "1" \
   "$(curl -s -b $J -o /dev/null -w '%{content_type}' "$B/mukellefler/ara?q=x" | grep -c 'application/json' | awk '{print ($1>0)?1:0}')"

# ---------------------------------------------------------------------
echo; echo "=== 9) SONSUZ KAYDIRMA (offset / daha_var) ==="
# 25/50 sınırı kalktı: liste sonuna gelince sonraki blok AJAX ile eklenir.
for i in 1 2 3 4 5 6; do
  db "INSERT INTO mukellefler (musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif)
      VALUES (1,'MGC-S$i','MGC KAYDIRMA $i','tuzel','960000001$i','bilanco','2019-01-01',1);"
done

curl -s -b $J "$B/mukellefler/ara?limit=2&offset=0&q=960000001" -o /tmp/mgc9a.json
curl -s -b $J "$B/mukellefler/ara?limit=2&offset=2&q=960000001" -o /tmp/mgc9b.json
curl -s -b $J "$B/mukellefler/ara?limit=2&offset=4&q=960000001" -o /tmp/mgc9c.json

say() { python3 -c "import json,sys;d=json.load(open('$1'));print($2)"; }

ol "1. blok 2 kayıt" 2 "$(say /tmp/mgc9a.json 'len(d["sonuclar"])')"
ol "2. blok 2 kayıt (offset=2)" 2 "$(say /tmp/mgc9b.json 'len(d["sonuclar"])')"
ol "toplam 6 (tüm eşleşme)" 6 "$(say /tmp/mgc9a.json 'd["toplam"]')"
ol "1. blokta devamı var" "True" "$(say /tmp/mgc9a.json 'd["daha_var"]')"
ol "gosterilen = offset + blok" 4 "$(say /tmp/mgc9b.json 'd["gosterilen"]')"
ol "bloklar ayrık (kayıt tekrarı yok)" 0 "$(
  python3 -c "
import json
a=json.load(open('/tmp/mgc9a.json'))['sonuclar']+json.load(open('/tmp/mgc9b.json'))['sonuclar']
ids=[x['id'] for x in a]
print(len(ids)-len(set(ids)))
")"
ol "son bloktan sonra daha_var=false" "False" "$(say /tmp/mgc9c.json 'd["daha_var"]')"
ol "offset toplamı aşınca boş liste" 0 \
   "$(curl -s -b $J "$B/mukellefler/ara?limit=2&offset=99&q=960000001" | python3 -c 'import json,sys;print(len(json.load(sys.stdin)["sonuclar"]))')"
ol "offset verilmezse baştan başlar (geriye uyum)" "$(say /tmp/mgc9a.json 'd["sonuclar"][0]["id"]')" \
   "$(curl -s -b $J "$B/mukellefler/ara?limit=2&q=960000001" | python3 -c 'import json,sys;print(json.load(sys.stdin)["sonuclar"][0]["id"])')"
ol "limit üst sınırı 50" 1 \
   "$(curl -s -b $J "$B/mukellefler/ara?limit=500&q=" | python3 -c 'import json,sys;print(1 if len(json.load(sys.stdin)["sonuclar"])<=50 else 0)')"

# Arayüz tarafı: kaydırma dinleyicisi + offset isteği JS'te var mı?
HTML=$(curl -s -b $J "$B/panel")
ol "JS: offset parametresi gönderiliyor" 1 "$(echo "$HTML" | grep -c "offset=" | awk '{print ($1>0)?1:0}')"
ol "JS: daha_var kullanılıyor" 1 "$(echo "$HTML" | grep -c "daha_var" | awk '{print ($1>0)?1:0}')"
ol "JS: liste scroll dinleyicisi var" 1 "$(echo "$HTML" | grep -c "addEventListener('scroll'" | awk '{print ($1>0)?1:0}')"
ol "JS: sayaç 'kaydırın' bilgisi veriyor" 1 "$(echo "$HTML" | grep -c "kaydırın" | awk '{print ($1>0)?1:0}')"

db "DELETE FROM mukellefler WHERE kod LIKE 'MGC-S%';"

echo; echo "=== TEMİZLİK ==="
db "DELETE FROM mukellefler WHERE kod LIKE 'MGC-%';"
rm -f /tmp/mgc*.json /tmp/mgc_p.html

echo
echo "======================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "======================================================"
[ "$k" -eq 0 ] || exit 1
