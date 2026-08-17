#!/bin/bash
# =====================================================================
#  SAYFALAMA / SONSUZ KAYDIRMA / ALFABE ŞERİDİ — REGRESYON TESTİ
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 var ve veritabanında bol kayıt bulunuyor.
#  Kullanım:  bash tests/sayfalama_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
J=/tmp/sp.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

rm -f $J
curl -s -c $J -o /tmp/f.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=admin" -d "sifre=Test1234" $B/giris

# Kısayollar
say(){ grep -c 'class="satir-sec"' "$1"; }
# Not: özet kartları artık çok satırlı HTML üretiyor (kartlar tıklanabilir <a>
# oldu). Bu yüzden satır sonlarını yok sayan bir okuyucu kullanılır.
oz(){ python3 - "$1" "$2" <<'PYOZ'
import re,sys
h=open(sys.argv[1],encoding='utf-8').read()
m=re.search(r'<div class="etiket">\s*'+re.escape(sys.argv[2])+r'\s*</div>\s*<div class="deger">\s*([\d.]+)',h)
print(m.group(1).replace('.','') if m else '')
PYOZ
}

echo "=== 1) BEYANNAME TAKİP — İLK PARÇA ==="
curl -s -b $J -o /tmp/sp_t.html "$B/takip?ay=0&yil=2026&adet=100"
DB_TOPLAM=$($MDB -e "select count(*) from beyanname_takip bt join mukellefler m on m.id=bt.mukellef_id where YEAR(bt.son_tarih)=2026 and m.deleted_at is null")
ol "İlk parçada 100 satır" "100" "$(say /tmp/sp_t.html)"
ol "data-toplam = DB toplamı" "$DB_TOPLAM" "$(grep -oP 'data-toplam="\K[0-9]+' /tmp/sp_t.html | head -1)"
ol "data-ofset = 100" "100" "$(grep -oP 'data-ofset="\K[0-9]+' /tmp/sp_t.html | head -1)"
ol "Özet 'Toplam' TÜM kayıtları gösteriyor" "$DB_TOPLAM" "$(oz /tmp/sp_t.html 'Toplam')"
ol "Daha Fazla düğmesi var" "1" "$(grep -c 'daha-fazla-btn' /tmp/sp_t.html | awk '{print ($1>0)?1:0}')"
ol "Adet seçici var" "1" "$(grep -c 'id=\"adet-sec\"' /tmp/sp_t.html)"

echo ""
echo "=== 2) FARKLI SAYFA ADETLERİ ==="
for a in 25 50 100 250; do
  curl -s -b $J -o /tmp/sp_a.html "$B/takip?ay=0&yil=2026&adet=$a"
  BEK=$a; [ "$DB_TOPLAM" -lt "$a" ] && BEK=$DB_TOPLAM
  ol "adet=$a → $BEK satır" "$BEK" "$(say /tmp/sp_a.html)"
done
curl -s -b $J -o /tmp/sp_a.html "$B/takip?ay=0&yil=2026&adet=9999"
ol "Geçersiz adet → varsayılan 100" "100" "$(say /tmp/sp_a.html)"

echo ""
echo "=== 3) SONSUZ KAYDIRMA — TÜM PARÇALAR ==="
python3 - "$DB_TOPLAM" <<'PY'
import subprocess, json, re, sys
hedef = int(sys.argv[1])
def curl(u, ajax=False):
    c = ['curl','-s','-b','/tmp/sp.txt']
    if ajax: c += ['-H','X-Requested-With: XMLHttpRequest']
    return subprocess.run(c+[u], capture_output=True, text=True).stdout

html = curl('http://127.0.0.1:8099/takip?ay=0&yil=2026&adet=100')
idler = re.findall(r'class="satir-sec" value="(\d+)"', html)
ofset = int(re.search(r'data-ofset="(\d+)"', html).group(1))
tur = 0
while True:
    d = json.loads(curl(f'http://127.0.0.1:8099/takip/daha-fazla?ay=0&yil=2026&adet=100&ofset={ofset}', True))
    idler += re.findall(r'class="satir-sec" value="(\d+)"', d['html'])
    ofset = d['ofset']; tur += 1
    if not d['dahaVar'] or d['yuklenen'] == 0 or tur > 50: break

sonuc = []
sonuc.append(('Tüm parçalar yüklendi', hedef, len(idler)))
sonuc.append(('Çakışma/tekrar yok', len(idler), len(set(idler))))
sonuc.append(('Tur sayısı doğru', -(-hedef//100), tur+1))
for ad, b, gg in sonuc:
    print(f"  [{'OK' if str(b)==str(gg) else 'HATA'}] {ad} (bekl:{b} ger:{gg})")
PY
# yukarıdaki python çıktısındaki hataları say
PYH=$(python3 - "$DB_TOPLAM" <<'PY'
import subprocess, json, re, sys
hedef=int(sys.argv[1])
def curl(u,ajax=False):
    c=['curl','-s','-b','/tmp/sp.txt']
    if ajax: c+=['-H','X-Requested-With: XMLHttpRequest']
    return subprocess.run(c+[u],capture_output=True,text=True).stdout
html=curl('http://127.0.0.1:8099/takip?ay=0&yil=2026&adet=100')
idler=re.findall(r'class="satir-sec" value="(\d+)"',html)
ofset=int(re.search(r'data-ofset="(\d+)"',html).group(1))
tur=0
while True:
    d=json.loads(curl(f'http://127.0.0.1:8099/takip/daha-fazla?ay=0&yil=2026&adet=100&ofset={ofset}',True))
    idler+=re.findall(r'class="satir-sec" value="(\d+)"',d['html'])
    ofset=d['ofset']; tur+=1
    if not d['dahaVar'] or d['yuklenen']==0 or tur>50: break
h=0
if len(idler)!=hedef: h+=1
if len(idler)!=len(set(idler)): h+=1
if tur+1 != -(-hedef//100): h+=1
print(h)
PY
)
g=$((g+3-PYH)); k=$((k+PYH))

echo ""
echo "=== 4) AJAX YANIT YAPISI ==="
curl -s -b $J -H "X-Requested-With: XMLHttpRequest" -o /tmp/sp.json \
  "$B/takip/daha-fazla?ay=0&yil=2026&adet=100&ofset=100"
ol "durum=true" "1" "$(grep -cE '\"durum\": *true' /tmp/sp.json)"
ol "html alanı dolu" "1" "$(python3 -c "
import json;d=json.load(open('/tmp/sp.json'));print(1 if len(d['html'])>1000 else 0)")"
ol "satirVeri 100 kayıt" "100" "$(python3 -c "
import json;print(len(json.load(open('/tmp/sp.json'))['satirVeri']))")"
ol "satirVeri turId içeriyor" "1" "$(python3 -c "
import json;d=json.load(open('/tmp/sp.json'));v=list(d['satirVeri'].values())[0]
print(1 if 'turId' in v and 'mukellef' in v and 'donem' in v else 0)")"
ol "ofset ilerledi" "200" "$(python3 -c "
import json;print(json.load(open('/tmp/sp.json'))['ofset'])")"

echo ""
echo "=== 5) FİLTRE + SAYFALAMA BİRLİKTE ==="
for D in BEKLIYOR HAZIR ONAYLANDI VERILMEYECEK; do
  DBS=$($MDB -e "select count(*) from beyanname_takip bt join mukellefler m on m.id=bt.mukellef_id where YEAR(bt.son_tarih)=2026 and bt.durum='$D' and m.deleted_at is null")
  curl -s -b $J -o /tmp/sp_d.html "$B/takip?ay=0&yil=2026&durum=$D&adet=25"
  # Sonuç 0 ise çizelge yerine "Kayıt bulunamadı" kutusu basılır; kaydırma
  # alanı (dolayısıyla data-toplam) hiç oluşmaz. Bu beklenen davranıştır.
  DT=$(grep -oP 'data-toplam="\K[0-9]+' /tmp/sp_d.html | head -1)
  [ "$DBS" = "0" ] && [ -z "$DT" ] && DT=0
  ol "durum=$D: data-toplam=$DBS" "$DBS" "$DT"
done

echo ""
echo "=== 6) FİLTREDEKİ TÜM ID'LER ==="
CT=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/sp_t.html|head -1)
curl -s -b $J -c $J -H "X-Requested-With: XMLHttpRequest" -o /tmp/sp_id.json \
  -d "csrf_beyanname=$CT" "$B/takip/tum-idler?ay=0&yil=2026"
ol "Tüm id'ler döndü" "$DB_TOPLAM" "$(python3 -c "
import json;print(json.load(open('/tmp/sp_id.json'))['adet'])")"
curl -s -b $J -o /tmp/sp_t.html "$B/takip?ay=0&yil=2026&durum=HAZIR"
CT=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/sp_t.html|head -1)
DBH=$($MDB -e "select count(*) from beyanname_takip bt join mukellefler m on m.id=bt.mukellef_id where YEAR(bt.son_tarih)=2026 and bt.durum='HAZIR' and m.deleted_at is null")
curl -s -b $J -c $J -H "X-Requested-With: XMLHttpRequest" -o /tmp/sp_id.json \
  -d "csrf_beyanname=$CT" "$B/takip/tum-idler?ay=0&yil=2026&durum=HAZIR"
ol "Filtreli id listesi" "$DBH" "$(python3 -c "
import json;print(json.load(open('/tmp/sp_id.json'))['adet'])")"

echo ""
echo "=== 7) EXCEL / YAZDIRMA SAYFALAMADAN ETKİLENMEMELİ ==="
curl -s -b $J "$B/takip/excel?ay=0&yil=2026" -o /tmp/sp_e.csv
ol "Excel TÜM kayıtları içeriyor" "$DB_TOPLAM" "$(($(wc -l < /tmp/sp_e.csv) - 1))"
curl -s -b $J "$B/takip/yazdir?ay=0&yil=2026" -o /tmp/sp_y.html
ol "Yazdırma TÜM kayıtları içeriyor" "1" "$([ $(grep -c '<tr' /tmp/sp_y.html) -ge $DB_TOPLAM ] && echo 1 || echo 0)"

echo ""
echo "=== 8) MÜKELLEF ALFABE ŞERİDİ ==="
curl -s -b $J -o /tmp/sp_m.html "$B/mukellefler"
ol "Şerit var" "1" "$(grep -c 'class=\"alfabe\"' /tmp/sp_m.html)"
ol "30 harf + Tümü = 31 bağlantı" "31" "$(python3 -c "
import re
s=open('/tmp/sp_m.html',encoding='utf-8').read()
i=s.find('class=\"alfabe\"')
print(s[i:i+8000].split('</div>')[0].count('<a href'))")"

echo ""
echo "  Türkçe harf ayrımı (Ş≠S, İ≠I, Ğ≠G, Ü≠U, Ö≠O, Ç≠C):"
for pair in "Ş:S" "İ:I" "Ğ:G" "Ü:U" "Ö:O" "Ç:C"; do
  BUYUK="${pair%%:*}"; DUZ="${pair##*:}"
  for H in "$BUYUK" "$DUZ"; do
    DBA=$($MDB -e "select count(*) from mukellefler where CONVERT(LEFT(unvan,1) USING utf8mb4) COLLATE utf8mb4_bin='$H' and deleted_at is null and aktif=1 and (terk_tarihi is null or terk_tarihi>=curdate())")
    ENC=$(python3 -c "import urllib.parse;print(urllib.parse.quote('$H'))")
    LST=$(curl -s -b $J "$B/mukellefler?harf=$ENC" | grep -c 'class="muk-sec"')
    ol "  harf=$H → $DBA kayıt" "$DBA" "$LST"
  done
done

echo ""
echo "  # grubu (sayı/sembol ile başlayanlar):"
DBH=$($MDB -e "select count(*) from mukellefler where unvan regexp '^[^A-Za-zÇĞİÖŞÜçğıöşü]' and deleted_at is null and aktif=1 and (terk_tarihi is null or terk_tarihi>=curdate())")
ol "  harf=# → $DBH kayıt" "$DBH" "$(curl -s -b $J "$B/mukellefler?harf=%23" | grep -c 'class="muk-sec"')"

echo ""
echo "  Harf + diğer filtreler birlikte:"
DBT=$($MDB -e "select count(*) from mukellefler where CONVERT(LEFT(unvan,1) USING utf8mb4) COLLATE utf8mb4_bin in ('Ç','ç') and mukellef_tipi='tuzel' and deleted_at is null and aktif=1 and (terk_tarihi is null or terk_tarihi>=curdate())")
ol "  harf=Ç + tip=tuzel" "$DBT" "$(curl -s -b $J "$B/mukellefler?harf=%C3%87&tip=tuzel" | grep -c 'class="muk-sec"')"
ol "  Harf filtresi formda korunuyor" "1" "$(curl -s -b $J "$B/mukellefler?harf=%C3%87" | grep -c 'name="harf" value="Ç"')"

echo ""
echo "=== 9) GEÇERSİZ GİRDİLER ==="
ol "Geçersiz harf yok sayıldı" "$(curl -s -b $J "$B/mukellefler" | grep -c 'class="muk-sec"')" \
   "$(curl -s -b $J "$B/mukellefler?harf=QQ" | grep -c 'class=\"muk-sec\"')"
ol "Negatif ofset → 0'a çekildi" "1" "$(curl -s -b $J -H 'X-Requested-With: XMLHttpRequest' \
   "$B/takip/daha-fazla?ay=0&yil=2026&ofset=-50" | grep -cE '\"durum\": *true')"
ol "Aşırı ofset → boş sonuç" "0" "$(curl -s -b $J -H 'X-Requested-With: XMLHttpRequest' \
   "$B/takip/daha-fazla?ay=0&yil=2026&ofset=999999" | python3 -c "
import json,sys;print(json.load(sys.stdin)['yuklenen'])")"

echo ""; echo "======"
[ $k -eq 0 ] && echo "BASARILI ($g/$((g+k)))" || echo "$k HATA ($g/$((g+k)))"
