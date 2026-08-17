#!/bin/bash
# =====================================================================
#  EVRAK TAKİP — DÖNEM KAYDIRMA · SORUMLU PERSONEL · SAYFALAMA
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234 kullanıcısı mevcut.
#  Not: Test kendi verisini kurar; başka testlerden bağımsız çalışır.
#  Kullanım:  bash tests/evrak_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/ev.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

# ---------- test verisi ----------
veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE evrak_takip; TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (1,'SMMM','Ali Yılmaz','Yılmaz',1);
UPDATE ayarlar SET deger='1' WHERE anahtar='evrak_donem_kaydirma';"

python3 - <<'PY' > /tmp/ev_veri.sql
sat=[]
for i in range(1,61):
    sor = 2 if i%2 else 4
    tip='tuzel' if i%2==0 else 'gercek'
    vkn=f"'{1000000000+i}'" if tip=='tuzel' else 'NULL'
    tck='NULL' if tip=='tuzel' else f"'{10000000000+i}'"
    sat.append(f"({i},1,{sor},'K{i:03}','MÜKELLEF {i:02} LTD.','{tip}',{vkn},{tck},'{'bilanco' if tip=='tuzel' else 'isletme'}','2020-01-01',1)")
print("INSERT INTO mukellefler (id,musavir_id,sorumlu_kullanici_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES")
print(',\n'.join(sat)+';')
PY
$MDBR < /tmp/ev_veri.sql
$MDBR -e "
INSERT INTO evrak_takip (mukellef_id,evrak_turu_id,yil,ay,durum,teslim_tarihi,created_at,updated_at)
SELECT m.id, t.id, 2026, 7, 'GELDI', '2026-08-05', NOW(), NOW()
FROM mukellefler m CROSS JOIN evrak_turleri t WHERE m.id<=10 AND t.aktif=1;
INSERT INTO evrak_takip (mukellef_id,evrak_turu_id,yil,ay,durum,created_at,updated_at)
SELECT m.id, t.id, 2026, 8, 'GELDI', NOW(), NOW()
FROM mukellefler m CROSS JOIN evrak_turleri t WHERE m.id<=3 AND t.aktif=1;"
}

veriKur
rm -f $J
curl -s -c $J -o /tmp/f.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=admin" -d "sifre=Test1234" $B/giris

say(){ grep -c 'td class="sol-sabit"' "$1"; }
sayac(){ grep -oE "$2</div><div class=\"deger\">[0-9.]+" "$1" | grep -oE '[0-9.]+$' | tr -d '.'; }

TEMMUZ=$($MDB -e "select count(*) from evrak_takip where yil=2026 and ay=7 and durum='GELDI'")
AGUSTOS=$($MDB -e "select count(*) from evrak_takip where yil=2026 and ay=8 and durum='GELDI'")

echo "=== 1) AYAR VE ŞEMA ==="
ol "evrak_donem_kaydirma ayarı var" "1" \
   "$($MDB -e "select count(*) from ayarlar where anahtar='evrak_donem_kaydirma'")"
ol "Kaydırma varsayılanı 1" "1" \
   "$($MDB -e "select deger from ayarlar where anahtar='evrak_donem_kaydirma'")"

echo ""
echo "=== 2) DÖNEM KAYDIRMA (Ağustos seçilince Temmuz gelir) ==="
curl -s -b $J -o /tmp/ev_8.html "$B/evrak?yil=2026&ay=8"
ol "Sayfa açıldı, fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/ev_8.html)"
ol "Başlık 'Temmuz 2026 Dönemi'" "1" "$(grep -c 'Temmuz 2026 Dönemi Evrak Çizelgesi' /tmp/ev_8.html)"
ol "Açıklama şeridi çıktı" "1" "$(grep -c 'ayında topladığınız' /tmp/ev_8.html)"
ol "Ay seçicide Ağustos seçili" "1" "$(grep -cE '<option value="8" selected>Ağustos' /tmp/ev_8.html)"
ol "Gelen evrak = TEMMUZ verisi" "$TEMMUZ" "$(sayac /tmp/ev_8.html 'Gelen Evrak')"
ol "JS dönem değişkeni Temmuz" "1" "$(grep -c 'var YIL = 2026, AY = 7' /tmp/ev_8.html)"

curl -s -b $J -o /tmp/ev_9.html "$B/evrak?yil=2026&ay=9"
ol "Eylül seçilince Ağustos dönemi" "1" "$(grep -c 'Ağustos 2026 Dönemi Evrak Çizelgesi' /tmp/ev_9.html)"
ol "Eylül: gelen = AĞUSTOS verisi" "$AGUSTOS" "$(sayac /tmp/ev_9.html 'Gelen Evrak')"

# Yıl sınırı: Ocak seçilince önceki yılın Aralık'ı
curl -s -b $J -o /tmp/ev_1.html "$B/evrak?yil=2027&ay=1"
ol "Ocak 2027 → Aralık 2026 dönemi" "1" "$(grep -c 'Aralık 2026 Dönemi Evrak Çizelgesi' /tmp/ev_1.html)"

echo ""
echo "=== 3) KAYDIRMA=0 (ayar kapatılınca) ==="
$MDBR -e "UPDATE ayarlar SET deger='0' WHERE anahtar='evrak_donem_kaydirma';"
curl -s -b $J -o /tmp/ev_0.html "$B/evrak?yil=2026&ay=8"
ol "Başlık 'Ağustos 2026 Dönemi'" "1" "$(grep -c 'Ağustos 2026 Dönemi Evrak Çizelgesi' /tmp/ev_0.html)"
ol "Gelen = AĞUSTOS verisi" "$AGUSTOS" "$(sayac /tmp/ev_0.html 'Gelen Evrak')"
ol "Açıklama şeridi gizli" "0" "$(grep -c 'ayında topladığınız' /tmp/ev_0.html)"
$MDBR -e "UPDATE ayarlar SET deger='1' WHERE anahtar='evrak_donem_kaydirma';"

echo ""
echo "=== 4) SAYFALAMA ==="
curl -s -b $J -o /tmp/ev_s.html "$B/evrak?yil=2026&ay=8&adet=50"
ol "İlk parçada 50 satır" "50" "$(say /tmp/ev_s.html)"
ol "data-toplam = 60" "60" "$(grep -oE 'data-toplam="[0-9]+"' /tmp/ev_s.html | grep -oE '[0-9]+')"
ol "data-ofset = 50" "50" "$(grep -oE 'data-ofset="[0-9]+"' /tmp/ev_s.html | grep -oE '[0-9]+')"
ol "Daha Fazla düğmesi var" "1" "$(grep -c 'daha-fazla-btn' /tmp/ev_s.html | awk '{print ($1>0)?1:0}')"
ol "Adet seçici var" "1" "$(grep -c 'id="adet-sec"' /tmp/ev_s.html)"
ol "Faal Mükellef sayacı TÜM kayıt" "60" "$(sayac /tmp/ev_s.html 'Faal Mükellef')"

for A in 25 100 250; do
  curl -s -b $J -o /tmp/ev_a.html "$B/evrak?yil=2026&ay=8&adet=$A"
  BEK=$A; [ 60 -lt $A ] && BEK=60
  ol "adet=$A → $BEK satır" "$BEK" "$(say /tmp/ev_a.html)"
done
# Geçersiz adet verilince sıra: çerez → Tanımlar→Ayarlar (evrak_sayfa_adedi) → kod varsayılanı
AYARADET=$($MDB -e "select deger from ayarlar where anahtar='evrak_sayfa_adedi'")
case "$AYARADET" in 25|50|100|250) BEKADET=$AYARADET ;; *) BEKADET=50 ;; esac
[ "$BEKADET" -gt 60 ] && BEKADET=60
curl -s -b $J -o /tmp/ev_a.html "$B/evrak?yil=2026&ay=8&adet=9999"
ol "Geçersiz adet → ayardaki değer ($BEKADET)" "$BEKADET" "$(say /tmp/ev_a.html)"

echo ""
echo "=== 5) SONSUZ KAYDIRMA (AJAX) ==="
curl -s -b $J -H "X-Requested-With: XMLHttpRequest" \
  "$B/evrak/daha-fazla?yil=2026&ay=8&ofset=50&adet=50" -o /tmp/ev.json
ol "durum=true" "1" "$(grep -cE '\"durum\": *true' /tmp/ev.json)"
python3 - <<'PY' > /tmp/ev_p.txt
import json
d=json.load(open('/tmp/ev.json',encoding='utf-8'))
print(d['yuklenen'], d['ofset'], d['toplam'], str(d['dahaVar']).lower(),
      d['html'].count('td class="sol-sabit"'))
PY
read YUK OFS TOP DAHA HSAT < /tmp/ev_p.txt
ol "Kalan 10 satır geldi" "10" "$YUK"
ol "Ofset 60'a ilerledi" "60" "$OFS"
ol "Toplam 60" "60" "$TOP"
ol "dahaVar=false" "false" "$DAHA"
ol "HTML satır sayısı" "10" "$HSAT"

# Tüm parçalar birleşince çakışma olmamalı
python3 - <<'PY' > /tmp/ev_b.txt
import subprocess, json, re
def curl(u, ajax=False):
    c=['curl','-s','-b','/tmp/ev.txt']
    if ajax: c+=['-H','X-Requested-With: XMLHttpRequest']
    return subprocess.run(c+[u],capture_output=True,text=True).stdout
html=curl('http://127.0.0.1:8099/evrak?yil=2026&ay=8&adet=25')
ids=re.findall(r'data-mukellef="(\d+)"', html)
ofset=int(re.search(r'data-ofset="(\d+)"',html).group(1))
tur=0
while True:
    d=json.loads(curl(f'http://127.0.0.1:8099/evrak/daha-fazla?yil=2026&ay=8&adet=25&ofset={ofset}',True))
    ids+=re.findall(r'data-mukellef="(\d+)"', d['html'])
    ofset=d['ofset']; tur+=1
    if not d['dahaVar'] or d['yuklenen']==0 or tur>20: break
u=sorted(set(ids), key=int)
print(len(u), tur+1)
PY
read BENZ TUR < /tmp/ev_b.txt
ol "Tüm mükellefler benzersiz geldi" "60" "$BENZ"
ol "3 turda tamamlandı (adet=25)" "3" "$TUR"

echo ""
echo "=== 6) SORUMLU PERSONEL FİLTRESİ ==="
ol "Filtre alanı var" "1" "$(grep -c 'name="sorumlu_kullanici_id"' /tmp/ev_s.html)"
ol "Menüde personel listeleniyor" "1" "$(python3 -c "
import re
s=open('/tmp/ev_s.html',encoding='utf-8').read()
m=re.search(r'name=\"sorumlu_kullanici_id\".*?</select>',s,re.S)
print(1 if m and len(re.findall(r'<option value=\"\d+\"',m.group(0)))>=2 else 0)")"

for P in 2 4; do
  DBS=$($MDB -e "select count(*) from mukellefler where sorumlu_kullanici_id=$P and deleted_at is null and aktif=1")
  curl -s -b $J -o /tmp/ev_p.html "$B/evrak?yil=2026&ay=8&sorumlu_kullanici_id=$P&adet=250"
  ol "Personel $P: $DBS mükellef" "$DBS" "$(say /tmp/ev_p.html)"
  ol "Personel $P: sayaç da filtreli" "$DBS" "$(sayac /tmp/ev_p.html 'Faal Mükellef')"
  ol "Personel $P: seçim korunuyor" "1" "$(python3 -c "
import re
s=open('/tmp/ev_p.html',encoding='utf-8').read()
m=re.search(r'name=\"sorumlu_kullanici_id\".*?</select>',s,re.S)
print(1 if m and re.search(r'<option value=\"$P\"\s*\n?\s*selected',m.group(0)) else 0)")"
done

# Personel filtresi + gelen evrak sayacı tutarlı mı
GEL2=$($MDB -e "select count(*) from evrak_takip e join mukellefler m on m.id=e.mukellef_id
  where e.yil=2026 and e.ay=7 and e.durum='GELDI' and m.sorumlu_kullanici_id=2")
curl -s -b $J -o /tmp/ev_p2.html "$B/evrak?yil=2026&ay=8&sorumlu_kullanici_id=2&adet=250"
ol "Gelen evrak sayacı filtreli" "$GEL2" "$(sayac /tmp/ev_p2.html 'Gelen Evrak')"

echo ""
echo "=== 7) FİLTRELER BİRLİKTE + ARAMA ==="
curl -s -b $J -o /tmp/ev_q.html -G "$B/evrak" \
  -d "yil=2026" -d "ay=8" -d "adet=250" --data-urlencode "q=MÜKELLEF 01"
ol "Arama sonucu 1 mükellef" "1" "$(say /tmp/ev_q.html)"
ol "Arama kutusu değeri korunuyor" "1" "$(grep -c 'value="MÜKELLEF 01"' /tmp/ev_q.html)"

echo ""
echo "=== 8) HÜCRE İŞARETLEME DOĞRU DÖNEME YAZILIYOR ==="
$MDBR -e "DELETE FROM evrak_takip WHERE mukellef_id=55;"
curl -s -b $J -c $J -o /tmp/ev_c.html "$B/evrak?yil=2026&ay=8"
CT=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/ev_c.html|head -1)
TUR1=$($MDB -e "select id from evrak_turleri where aktif=1 limit 1")
curl -s -b $J -c $J -o /dev/null -H "X-Requested-With: XMLHttpRequest" \
  -d "csrf_beyanname=$CT" -d "mukellef_id=55" -d "evrak_turu_id=$TUR1" \
  -d "yil=2026" -d "ay=7" -d "durum=GELDI" $B/evrak/durum
ol "Kayıt TEMMUZ dönemine yazıldı" "2026-7" \
   "$($MDB -e "select concat(yil,'-',ay) from evrak_takip where mukellef_id=55")"

echo ""
echo "=== 9) EXCEL / YAZDIRMA ==="
curl -s -b $J "$B/evrak/excel?yil=2026&ay=8" -o /tmp/ev_e.csv
ol "Excel TÜM kayıtları içeriyor" "60" "$(($(wc -l < /tmp/ev_e.csv) - 1))"
curl -s -b $J "$B/evrak/yazdir?yil=2026&ay=8" -o /tmp/ev_y.html
ol "Yazdırma Temmuz dönemi" "1" "$(grep -c 'Temmuz 2026' /tmp/ev_y.html)"
ol "Yazdırma tüm kayıtlar" "1" "$([ $(grep -c '<tr' /tmp/ev_y.html) -ge 60 ] && echo 1 || echo 0)"
curl -s -b $J "$B/evrak/excel?yil=2026&ay=8&sorumlu_kullanici_id=2" -o /tmp/ev_e2.csv
ol "Excel personel filtresine uyuyor" "30" "$(($(wc -l < /tmp/ev_e2.csv) - 1))"

echo ""
echo "=== 10) UÇ DURUMLAR ==="
ol "Negatif ofset güvenli" "1" "$(curl -s -b $J -H 'X-Requested-With: XMLHttpRequest' \
   "$B/evrak/daha-fazla?yil=2026&ay=8&ofset=-50" | grep -cE '\"durum\": *true')"
ol "Aşırı ofset boş sonuç" "0" "$(curl -s -b $J -H 'X-Requested-With: XMLHttpRequest' \
   "$B/evrak/daha-fazla?yil=2026&ay=8&ofset=99999" | python3 -c "
import json,sys;print(json.load(sys.stdin)['yuklenen'])")"
ol "Geçersiz personel id → boş liste" "0" \
   "$(curl -s -b $J "$B/evrak?yil=2026&ay=8&sorumlu_kullanici_id=99999&adet=250" | grep -c 'td class="sol-sabit"')"

echo ""
echo "=== 11) GERİYE DÖNÜK UYUMLULUK (controller eski kalırsa) ==="
# Görünüm, controller güncellenmemiş olsa bile çökmemeli.
cp app/Controllers/Evrak.php /tmp/ev_ctrl_yedek.php
python3 - <<'PYX'
s=open('/home/user/beyanname-takip/app/Controllers/Evrak.php').read()
i=s.find('    public function index()')
j=s.find('    /** AJAX: tek hücre Geldi/Gelmedi */')
eski = """    public function index()
    {
        $yil = (int) ($this->request->getGet('yil') ?? date('Y'));
        $ay  = (int) ($this->request->getGet('ay') ?? date('n'));
        $filtre = [
            'musavir_id' => $this->kapsamBelirle($this->request->getGet('musavir_id')),
            'q'          => $this->request->getGet('q'),
        ];
        $cizelge = $this->model->cizelge($yil, $ay, $filtre);
        $notModel = new AylikNotModel();
        $notlar   = [];
        foreach ($cizelge['mukellefler'] as $m) {
            $notlar[(int) $m['id']] = $notModel->notAl((int) $m['id'], $yil, $ay);
        }
        return $this->goster('evrak/index', [
            'yil' => $yil, 'ay' => $ay, 'filtre' => $filtre,
            'mukellefler' => $cizelge['mukellefler'],
            'turler' => $cizelge['turler'], 'matris' => $cizelge['matris'],
            'notlar' => $notlar, 'musavirler' => $this->secilebilirMusavirler(),
            'ozet' => $this->model->ozet($yil, $ay, $this->musavirFiltresi()),
        ], 'Aylık Evrak Takibi');
    }

"""
open('/home/user/beyanname-takip/app/Controllers/Evrak.php','w').write(s[:i]+eski+s[j:])
PYX
# PHP opcache dosya değişikliğini saniye çözünürlüğünde algılar;
# beklemezsek eski (önbellekteki) sınıf çalışır ve test yanlış sonuç verir.
touch -d "+2 seconds" app/Controllers/Evrak.php 2>/dev/null || true
sleep 2
curl -s -b $J -o /tmp/ev_eski.html "$B/evrak?yil=2026&ay=8"
ol "Eski controller: ErrorException YOK" "0" "$(grep -c 'ErrorException' /tmp/ev_eski.html)"
ol "Eski controller: Undefined variable YOK" "0" "$(grep -c 'Undefined variable' /tmp/ev_eski.html)"
ol "Eski controller: sayfa çalışıyor" "1" "$(grep -c 'Evrak Çizelgesi' /tmp/ev_eski.html)"
ol "Eski controller: uyarı şeridi çıkıyor" "1" "$(grep -c 'Eksik güncelleme' /tmp/ev_eski.html)"
ol "Eski controller: sayfalama gizli" "0" "$(grep -c 'id=\"kaydir-alani\"' /tmp/ev_eski.html)"
ol "Eski controller: adet seçici gizli" "0" "$(grep -c 'id=\"adet-sec\"' /tmp/ev_eski.html)"
cp /tmp/ev_ctrl_yedek.php app/Controllers/Evrak.php
touch app/Controllers/Evrak.php
sleep 2
curl -s -b $J -o /tmp/ev_geri.html "$B/evrak?yil=2026&ay=8"
ol "Yeni controller geri geldi" "0" "$(grep -c 'Eksik güncelleme' /tmp/ev_geri.html)"


echo ""; echo "======"
[ $k -eq 0 ] && echo "BASARILI ($g/$((g+k)))" || echo "$k HATA ($g/$((g+k)))"
