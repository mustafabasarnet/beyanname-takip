#!/bin/bash
# =====================================================================
#  EVRAK TAKİBİ — "BU MÜKELLEFTE YOK" (TAKİP DIŞI / MUAFİYET)
#
#  Kapsam:
#    1) Şema (tablo, ENUM, ayar)
#    2) Kalıcı muafiyet (mükellef kartı) — tüm aylar
#    3) Dönemsel istisna (sağ tık) — yalnız o ay, kalıcıyı EZER
#    4) Sayaçlar: muaf hücreler toplamdan DÜŞÜLÜR
#    5) "Tümü geldi" muaf türü atlar
#    6) Excel / Yazdırma çıktısı
#    7) Panel "evrakı gelmeyenler" listesi
#    8) Yetki (başka müşavirin mükellefine muafiyet konamaz)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor.
#  Kullanım:  bash tests/evrak_muafiyet_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
J=/tmp/evm.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
# JSON yanıtı CI_ENVIRONMENT=development iken GİRİNTİLİ basılır
# ("durum": true). Bu yüzden karşılaştırmadan önce tüm boşluklar atılır.
var(){ if echo "$2" | tr -d ' \n\t' | grep -q "$(echo "$3" | tr -d ' ')"; then
         echo "  [OK] $1"; g=$((g+1));
       else echo "  [HATA] $1 (bulunamadı: $3)"; k=$((k+1)); fi }

# Çok satırlı HTML etiketinde öznitelik arar (attribute'lar alt alta yazılır)
etiket(){ tr '\n' ' ' < "$1" | grep -oE "$2" | wc -l; }

# ---------------------------------------------------------------------
#  TEST VERİSİ
#  6 mükellef, müşavir 1. Evrak türleri kurulum şemasından gelir.
# ---------------------------------------------------------------------
veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE evrak_takip; TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri;
DELETE FROM mukellef_evrak_muafiyet;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (1,'SMMM','Ali Yılmaz','Yılmaz',1);
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (2,'SMMM','Veli Demir','Demir',1);
UPDATE ayarlar SET deger='1' WHERE anahtar='evrak_donem_kaydirma';
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,defter_tipi,ise_baslama_tarihi,aktif) VALUES
 (1,1,'M001','BANKASIZ LTD.','tuzel','1000000001','bilanco','2020-01-01',1),
 (2,1,'M002','CEKSIZ LTD.','tuzel','1000000002','bilanco','2020-01-01',1),
 (3,1,'M003','NORMAL LTD.','tuzel','1000000003','bilanco','2020-01-01',1),
 (4,1,'M004','TAM MUAF LTD.','tuzel','1000000004','bilanco','2020-01-01',1),
 (5,1,'M005','DONEMSEL LTD.','tuzel','1000000005','bilanco','2020-01-01',1),
 (6,2,'M006','BASKA BURO LTD.','tuzel','1000000006','bilanco','2020-01-01',1);"
}

veriKur

TUR_TOPLAM=$($MDB -e "select count(*) from evrak_turleri where aktif=1")
BANKA=$($MDB -e "select id from evrak_turleri where kisa_ad='Banka' limit 1")
CEK=$($MDB -e "select id from evrak_turleri where kisa_ad='Çek/Senet' limit 1")
ALIS=$($MDB -e "select id from evrak_turleri where kisa_ad='Alış Fat.' limit 1")

# ---------- giriş ----------
rm -f $J
curl -s -c $J -o /tmp/f.html $B/giris
T=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T" -d "kimlik=admin" -d "sifre=Test1234" $B/giris

# Sayfadan güncel CSRF çerezi/hash'i almak için yardımcı
tazele(){ curl -s -b $J -c $J -o /tmp/evm_sayfa.html "$B/evrak?yil=2026&ay=8";
          CS=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/evm_sayfa.html|head -1); }

# AJAX POST (CSRF her istekte tazelenir)
gonder(){ # $1=yol  $2..=veri
  local yol="$1"; shift
  tazele
  curl -s -b $J -c $J -X POST "$B/$yol" -H 'X-Requested-With: XMLHttpRequest' \
       -d "csrf_beyanname=$CS" "$@"
}

sayac(){ grep -oE "$2</div><div class=\"deger\"[^>]*>[0-9.]+" "$1" | grep -oE '[0-9.]+$' | tr -d '.'; }

echo "=== 1) ŞEMA ==="
ol "mukellef_evrak_muafiyet tablosu var" "1" \
   "$($MDB -e "select count(*) from information_schema.tables where table_schema=database() and table_name='mukellef_evrak_muafiyet'")"
ol "evrak_takip.durum ENUM'unda YOK var" "1" \
   "$($MDB -e "select locate('YOK',column_type)>0 from information_schema.columns where table_schema=database() and table_name='evrak_takip' and column_name='durum'")"
ol "uq_muafiyet benzersiz anahtarı var" "1" \
   "$($MDB -e "select count(*) from information_schema.statistics where table_schema=database() and table_name='mukellef_evrak_muafiyet' and index_name='uq_muafiyet' and non_unique=0" | head -1 | awk '{print ($1>0)?1:0}')"
ol "evrak_muaf_etiket ayarı var" "1" \
   "$($MDB -e "select count(*) from ayarlar where anahtar='evrak_muaf_etiket'")"

echo ""
echo "=== 2) BAŞLANGIÇ DURUMU (muafiyet yokken) ==="
curl -s -b $J -o /tmp/evm_0.html "$B/evrak?yil=2026&ay=8"
ol "Sayfa açıldı, fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/evm_0.html)"
# 6 mükellefin 5'i müşavir 1'de; admin hepsini görür => 6 mükellef
ol "Faal mükellef 6" "6" "$(sayac /tmp/evm_0.html 'Faal Mükellef')"
ol "Beklenen hücre = 6 x tür" "$((6*TUR_TOPLAM))" \
   "$(grep -oE '[0-9]+ hücrenin' /tmp/evm_0.html | grep -oE '[0-9]+')"
ol "Takip Dışı kartı YOK (sayı 0)" "0" "$(grep -c 'Takip Dışı</div>' /tmp/evm_0.html)"
ol "Efsanede takip dışı rozeti var" "1" "$(grep -c 'rozet gri" title="Bu mükellefte' /tmp/evm_0.html)"

echo ""
echo "=== 3) KALICI MUAFİYET (AJAX — tüm aylar) ==="
Y=$(gonder "evrak/kalici-muaf" -d "mukellef_id=1" -d "evrak_turu_id=$BANKA" -d "isaretle=1" -d "aciklama=Banka hesabı yok")
var "Yanıt başarılı" "$Y" '"durum":true'
var "yeni_durum=YOK" "$Y" '"yeni_durum":"YOK"'
var "kalici=true" "$Y" '"kalici":true'
ol "Tabloya yazıldı" "1" \
   "$($MDB -e "select count(*) from mukellef_evrak_muafiyet where mukellef_id=1 and evrak_turu_id=$BANKA")"
ol "Açıklama saklandı" "Banka hesabı yok" \
   "$($MDB -e "select aciklama from mukellef_evrak_muafiyet where mukellef_id=1 and evrak_turu_id=$BANKA")"

# Çeki olmayan mükellef
gonder "evrak/kalici-muaf" -d "mukellef_id=2" -d "evrak_turu_id=$CEK" -d "isaretle=1" >/dev/null
ol "İkinci muafiyet yazıldı" "2" "$($MDB -e "select count(*) from mukellef_evrak_muafiyet")"

# Mükerrer istek yeni satır AÇMAMALI
gonder "evrak/kalici-muaf" -d "mukellef_id=1" -d "evrak_turu_id=$BANKA" -d "isaretle=1" -d "aciklama=Guncel not" >/dev/null
ol "Mükerrer istek satır çoğaltmadı" "2" "$($MDB -e "select count(*) from mukellef_evrak_muafiyet")"
ol "Mükerrer istek açıklamayı güncelledi" "Guncel not" \
   "$($MDB -e "select aciklama from mukellef_evrak_muafiyet where mukellef_id=1 and evrak_turu_id=$BANKA")"

echo ""
echo "=== 4) ÇİZELGEDE GÖRÜNÜM ==="
curl -s -b $J -o /tmp/evm_1.html "$B/evrak?yil=2026&ay=8"
ol "Fatal yok" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/evm_1.html)"
ol "Taralı (yok) hücre sayısı 2" "2" "$(grep -oE '<td class="evrak-hucre yok[^"]*"' /tmp/evm_1.html | wc -l)"
ol "Kalıcı imi (yok kalici) 2 hücrede" "2" "$(grep -oE 'evrak-hucre yok kalici' /tmp/evm_1.html | wc -l)"
ol "Hücre data-durum=YOK" "2" "$(grep -oE 'data-durum="YOK"' /tmp/evm_1.html | wc -l)"
# NOT: Aynı metin sayfanın JS bölümünde de geçer; yalnızca title="..."
# içindekiler sayılmalı (yanlış alarm kaynağıydı).
ol "İpucunda kalıcı açıklaması" "2" "$(grep -oE 'title="[^"]*Bu mükellefte yok \(kalıcı\)' /tmp/evm_1.html | wc -l)"
ol "Açıklama metni ipucuna işlendi" "1" "$(grep -c 'Guncel not' /tmp/evm_1.html)"

echo ""
echo "=== 5) SAYAÇLAR — MUAF HÜCRE TOPLAMDAN DÜŞÜLÜR ==="
BEK=$((6*TUR_TOPLAM-2))
ol "Beklenen hücre 2 azaldı" "$BEK" \
   "$(grep -oE '[0-9]+ hücrenin' /tmp/evm_1.html | grep -oE '[0-9]+')"
ol "Takip Dışı kartı çıktı" "1" "$(grep -c 'Takip Dışı</div>' /tmp/evm_1.html)"
ol "Takip Dışı sayacı 2" "2" "$(grep -oE 'id="muaf-sayac">[0-9.]+' /tmp/evm_1.html | grep -oE '[0-9.]+$' | tr -d '.')"
ol "Bekleyen = beklenen (hiç evrak gelmedi)" "$BEK" "$(sayac /tmp/evm_1.html 'Bekleyen')"

echo ""
echo "=== 6) DÖNEMSEL İSTİSNA (yalnız seçili ay) ==="
Y=$(gonder "evrak/donem-muaf" -d "mukellef_id=5" -d "evrak_turu_id=$ALIS" -d "yil=2026" -d "ay=7" -d "isaretle=1")
var "Dönemsel istisna kabul edildi" "$Y" '"yeni_durum":"YOK"'
var "kalici=false (yalnız bu ay)" "$Y" '"kalici":false'
ol "evrak_takip'e YOK yazıldı" "1" \
   "$($MDB -e "select count(*) from evrak_takip where mukellef_id=5 and evrak_turu_id=$ALIS and yil=2026 and ay=7 and durum='YOK'")"
ol "Kalıcı tabloya YAZILMADI" "0" \
   "$($MDB -e "select count(*) from mukellef_evrak_muafiyet where mukellef_id=5")"

# Temmuz dönemi (Ağustos seçimi) etkilenmeli
curl -s -b $J -o /tmp/evm_2.html "$B/evrak?yil=2026&ay=8"
ol "Temmuz döneminde 3 takip dışı hücre" "3" "$(grep -oE '<td class="evrak-hucre yok[^"]*"' /tmp/evm_2.html | wc -l)"
ol "Dönemsel hücrede kalıcı imi YOK" "1" "$(grep -oE 'evrak-hucre yok"' /tmp/evm_2.html | wc -l)"
ol "data-donemsel=1 bir hücrede" "1" "$(grep -oE 'data-donemsel="1"' /tmp/evm_2.html | wc -l)"

# Ağustos dönemi (Eylül seçimi) ETKİLENMEMELİ — kalıcı 2 tanesi görünür
curl -s -b $J -o /tmp/evm_3.html "$B/evrak?yil=2026&ay=9"
ol "Ağustos döneminde yalnız 2 kalıcı muaf" "2" "$(grep -oE '<td class="evrak-hucre yok[^"]*"' /tmp/evm_3.html | wc -l)"
ol "Ağustos döneminde dönemsel istisna yok" "0" "$(grep -oE 'data-donemsel="1"' /tmp/evm_3.html | wc -l)"

echo ""
echo "=== 7) DÖNEMSEL İSTİSNA KALICIYI EZER ==="
# Mükellef 1'in bankası kalıcı muaf; Temmuz'da bunu takibe geri al
Y=$(gonder "evrak/durum" -d "mukellef_id=1" -d "evrak_turu_id=$BANKA" -d "yil=2026" -d "ay=7" -d "durum=GELDI")
var "Kalıcı muaf hücreye GELDI yazılabildi" "$Y" '"yeni_durum":"GELDI"'
curl -s -b $J -o /tmp/evm_4.html "$B/evrak?yil=2026&ay=8"
ol "Temmuz'da takip dışı 2'ye düştü" "2" "$(grep -oE '<td class="evrak-hucre yok[^"]*"' /tmp/evm_4.html | wc -l)"
ol "Gelen evrak 1 oldu" "1" "$(sayac /tmp/evm_4.html 'Gelen Evrak')"
# Ağustos dönemi hâlâ kalıcı muaf görmeli (kayıt yok)
curl -s -b $J -o /tmp/evm_5.html "$B/evrak?yil=2026&ay=9"
ol "Ağustos'ta kalıcı muafiyet sürüyor" "2" "$(grep -oE '<td class="evrak-hucre yok[^"]*"' /tmp/evm_5.html | wc -l)"

# Temmuz kaydını sil -> kalıcıya geri dön
Y=$(gonder "evrak/donem-muaf" -d "mukellef_id=1" -d "evrak_turu_id=$BANKA" -d "yil=2026" -d "ay=7" -d "isaretle=0")
var "Geri alma kalıcı ayara döndürdü" "$Y" '"yeni_durum":"YOK"'
var "kalici=true bildirildi" "$Y" '"kalici":true'
ol "Temmuz kaydı silindi" "0" \
   "$($MDB -e "select count(*) from evrak_takip where mukellef_id=1 and evrak_turu_id=$BANKA and yil=2026 and ay=7")"

echo ""
echo "=== 8) 'TÜMÜ GELDİ' MUAF TÜRÜ ATLAR ==="
Y=$(gonder "evrak/tumu" -d "mukellef_id=1" -d "yil=2026" -d "ay=7" -d "durum=GELDI")
ol "Muaf tür hariç işaretlendi" "$((TUR_TOPLAM-1))" \
   "$($MDB -e "select count(*) from evrak_takip where mukellef_id=1 and yil=2026 and ay=7 and durum='GELDI'")"
ol "Banka türüne kayıt AÇILMADI" "0" \
   "$($MDB -e "select count(*) from evrak_takip where mukellef_id=1 and evrak_turu_id=$BANKA and yil=2026 and ay=7")"
var "Sayı yanıtta bildirildi" "$Y" "$((TUR_TOPLAM-1)) evrak güncellendi"

curl -s -b $J -o /tmp/evm_6.html "$B/evrak?yil=2026&ay=8"
# Öznitelikler HTML'de alt alta yazıldığı için tek satıra indirilerek aranır
ol "Muaf hücre hâlâ takip dışı" "1" \
   "$(etiket /tmp/evm_6.html 'data-mukellef="1" +data-tur="'"$BANKA"'" +data-durum="YOK"')"
ol "Gelen evrak = tür-1" "$((TUR_TOPLAM-1))" "$(sayac /tmp/evm_6.html 'Gelen Evrak')"

echo ""
echo "=== 9) MÜKELLEF KARTI (kalıcı ayar formu) ==="
curl -s -b $J -o /tmp/evm_form.html "$B/mukellefler/duzenle/1"
ol "Form açıldı" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/evm_form.html)"
ol "Takip Edilmeyen Evrak Türleri kartı var" "1" "$(grep -c 'Takip Edilmeyen Evrak Türleri' /tmp/evm_form.html)"
ol "Gizli bayrak alanı var" "1" "$(grep -c 'name="evrak_muaf_gonderildi"' /tmp/evm_form.html)"
ol "Tür sayısı kadar onay kutusu" "$TUR_TOPLAM" "$(grep -oE 'name="evrak_muaf\[\]"' /tmp/evm_form.html | wc -l)"
ol "Banka kutusu işaretli geldi" "1" \
   "$(grep -oE 'name="evrak_muaf\[\]" value="'"$BANKA"'" checked' /tmp/evm_form.html | wc -l)"
ol "Açıklama kutusunda mevcut not" "1" \
   "$(etiket /tmp/evm_form.html 'name="evrak_muaf_not\['"$BANKA"'\]" +value="Guncel not"')"

# Form üzerinden ÇEK türünü de ekle, BANKA'yı kaldır
CT=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/evm_form.html|head -1)
curl -s -b $J -c $J -o /dev/null -X POST "$B/mukellefler/guncelle/1" \
  -d "csrf_beyanname=$CT" -d "musavir_id=1" -d "kod=M001" -d "unvan=BANKASIZ LTD." \
  -d "mukellef_tipi=tuzel" -d "vergi_kimlik_no=1000000001" -d "defter_tipi=bilanco" \
  -d "ise_baslama_tarihi=2020-01-01" -d "aktif=1" \
  -d "evrak_muaf_gonderildi=1" -d "evrak_muaf[]=$CEK" -d "evrak_muaf_not[$CEK]=Cek kullanmiyor"
ol "Form: BANKA muafiyeti kaldırıldı" "0" \
   "$($MDB -e "select count(*) from mukellef_evrak_muafiyet where mukellef_id=1 and evrak_turu_id=$BANKA")"
ol "Form: ÇEK muafiyeti eklendi" "1" \
   "$($MDB -e "select count(*) from mukellef_evrak_muafiyet where mukellef_id=1 and evrak_turu_id=$CEK")"
ol "Form: açıklama yazıldı" "Cek kullanmiyor" \
   "$($MDB -e "select aciklama from mukellef_evrak_muafiyet where mukellef_id=1 and evrak_turu_id=$CEK")"

# GERÇEK KUSUR REGRESYONU
# Beyanname türleri çelişki JS'i genel '.tur-kutu' seçicisi kullandığı için
# evrak muafiyet kutularını da mükellef tipine göre KİLİTLİYORDU (kutular
# soluk + "Şahıs" rozeti). İki ızgara artık kimlikleriyle ayrılır.
ol "Beyanname ızgarası kimlikli" "1" "$(grep -c 'id="beyanname-tur-grid"' /tmp/evm_form.html)"
ol "Evrak muafiyet ızgarası kimlikli" "1" "$(grep -c 'id="evrak-muaf-grid"' /tmp/evm_form.html)"
ol "Çelişki JS'i belge geneli taramıyor" "0" \
   "$(grep -c "document.querySelectorAll('.tur-kutu')" app/Views/mukellefler/form.php)"
ol "Çelişki JS'i ızgarayla sınırlı" "1" \
   "$(grep -c "izgara.querySelectorAll('.tur-kutu')" app/Views/mukellefler/form.php)"
ol "Muafiyet kutusunda kilit sınıfı yok" "0" \
   "$(tr '\n' ' ' < /tmp/evm_form.html | grep -oE 'tur-kutu[^"]*pasif[^"]*" data-evrak-muaf' | wc -l)"

echo ""
echo "=== 10) BOŞ KAYIT TEMİZLİĞİ (geldi kayıtları korunur) ==="
$MDBR -e "
INSERT INTO evrak_takip (mukellef_id,evrak_turu_id,yil,ay,durum,created_at,updated_at) VALUES
 (3,$BANKA,2026,5,'GELMEDI',NOW(),NOW()),
 (3,$BANKA,2026,6,'GELDI',NOW(),NOW()),
 (3,$BANKA,2026,7,'GELMEDI',NOW(),NOW());"
gonder "evrak/kalici-muaf" -d "mukellef_id=3" -d "evrak_turu_id=$BANKA" -d "isaretle=1" >/dev/null
ol "Boş (GELMEDI) kayıtlar silindi" "0" \
   "$($MDB -e "select count(*) from evrak_takip where mukellef_id=3 and evrak_turu_id=$BANKA and durum='GELMEDI'")"
ol "GELDI kaydı KORUNDU" "1" \
   "$($MDB -e "select count(*) from evrak_takip where mukellef_id=3 and evrak_turu_id=$BANKA and durum='GELDI'")"

echo ""
echo "=== 11) TÜM TÜRLERİ MUAF MÜKELLEF ==="
for t in $($MDB -e "select id from evrak_turleri where aktif=1"); do
  gonder "evrak/kalici-muaf" -d "mukellef_id=4" -d "evrak_turu_id=$t" -d "isaretle=1" >/dev/null
done
ol "4 nolu mükellefin tüm türleri muaf" "$TUR_TOPLAM" \
   "$($MDB -e "select count(*) from mukellef_evrak_muafiyet where mukellef_id=4")"
curl -s -b $J -o /tmp/evm_7.html "$B/evrak?yil=2026&ay=9"
ol "Sayfa açıldı" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/evm_7.html)"
ol "Satırı hâlâ listede (kaybolmadı)" "1" "$(grep -c 'TAM MUAF LTD' /tmp/evm_7.html)"

echo ""
echo "=== 12) PANEL: EVRAKI GELMEYENLER ==="
curl -s -b $J -o /tmp/evm_panel.html "$B/panel?yil=2026&ay=8"
ol "Panel açıldı" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/evm_panel.html)"
ol "Tüm türleri muaf mükellef uyarıda YOK" "0" "$(grep -c 'TAM MUAF LTD' /tmp/evm_panel.html)"

echo ""
echo "=== 13) EXCEL ÇIKTISI ==="
curl -s -b $J -o /tmp/evm.csv "$B/evrak/excel?yil=2026&ay=9"
ol "CSV indirildi" "1" "$([ -s /tmp/evm.csv ] && echo 1 || echo 0)"
ol "Takip dışı etiketi CSV'de var" "1" "$(grep -c 'Takip dışı' /tmp/evm.csv | awk '{print ($1>0)?1:0}')"
ol "TAM MUAF satırında tür sayısı kadar 'Takip dışı'" "$TUR_TOPLAM" \
   "$(grep 'TAM MUAF' /tmp/evm.csv | grep -o 'Takip dışı' | wc -l)"
ol "NORMAL LTD satırı bozulmadı" "1" "$(grep -c 'NORMAL LTD' /tmp/evm.csv)"

echo ""
echo "=== 14) YAZDIRMA ÇIKTISI ==="
curl -s -b $J -o /tmp/evm_print.html "$B/evrak/yazdir?yil=2026&ay=9"
ol "Yazdırma açıldı" "0" "$(grep -ciE 'fatal error|uncaught' /tmp/evm_print.html)"
ol "Takip dışı hücreler taralı sınıfta" "1" \
   "$(grep -oE 'evrak-hucre yok' /tmp/evm_print.html | wc -l | awk '{print ($1>0)?1:0}')"
ol "Alt açıklama şeridi var" "1" "$(grep -c 'eksik evrak sayılmaz' /tmp/evm_print.html)"
ol "Başlıkta takip dışı sayısı" "1" "$(grep -c 'hücre takip dışı' /tmp/evm_print.html)"

echo ""
echo "=== 15) YETKİ ==="
# musavir kullanıcısını müşavir 1'e bağla, müşavir 2'nin mükellefine dokunamasın
$MDBR -e "UPDATE kullanicilar SET musavir_id=1 WHERE kullanici_adi='musavir';
          DELETE FROM kullanici_musavirleri WHERE kullanici_id=(SELECT id FROM kullanicilar WHERE kullanici_adi='musavir');"
J2=/tmp/evm2.txt; rm -f $J2
curl -s -c $J2 -o /tmp/f2.html $B/giris
T2=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f2.html|head -1)
curl -s -b $J2 -c $J2 -o /dev/null -d "csrf_beyanname=$T2" -d "kimlik=musavir" -d "sifre=Test1234" $B/giris
curl -s -b $J2 -c $J2 -o /tmp/evm_m.html "$B/evrak?yil=2026&ay=8"
CS2=$(grep -oP 'name="csrf-token" content="\K[^"]+' /tmp/evm_m.html|head -1)
KOD=$(curl -s -b $J2 -c $J2 -o /tmp/evm_yetki.json -w '%{http_code}' -X POST "$B/evrak/kalici-muaf" \
      -H 'X-Requested-With: XMLHttpRequest' -d "csrf_beyanname=$CS2" \
      -d "mukellef_id=6" -d "evrak_turu_id=$BANKA" -d "isaretle=1")
ol "Başka büronun mükellefi reddedildi (403)" "403" "$KOD"
ol "Yetkisiz muafiyet yazılmadı" "0" "$($MDB -e "select count(*) from mukellef_evrak_muafiyet where mukellef_id=6")"

echo ""
echo "=== 16) GERİYE DÖNÜK UYUM ==="
ol "Model tablo yokluğuna dayanıklı (kullanilabilir var)" "1" \
   "$(grep -c 'function kullanilabilir' app/Models/EvrakMuafiyetModel.php)"
ol "yokDestekliMi ENUM denetimi var" "1" \
   "$(grep -c 'function yokDestekliMi' app/Models/EvrakTakipModel.php)"
ol "Satır parçası muafiyet varsayılanı koyuyor" "1" \
   "$(grep -c 'muafiyet = \$muafiyet ?? \[\]' app/Views/evrak/_satirlar.php)"
ol "Migration idempotent (INSERT ... ON DUPLICATE)" "1" \
   "$(grep -c 'ON DUPLICATE KEY UPDATE' database/migration_evrak_muafiyet.sql)"
ol "Migration CREATE TABLE IF NOT EXISTS" "1" \
   "$(grep -c 'CREATE TABLE IF NOT EXISTS' database/migration_evrak_muafiyet.sql)"
ol "Ana şemaya tablo eklendi" "1" \
   "$(grep -c 'mukellef_evrak_muafiyet' database/beyanname_takip.sql | awk '{print ($1>0)?1:0}')"
ol "Ana şemada ENUM güncel" "1" \
   "$(grep -c "ENUM('GELMEDI','GELDI','YOK')" database/beyanname_takip.sql | awk '{print ($1>0)?1:0}')"

# ---------------------------------------------------------------------
#  TEMİZLİK — bu test kendi izini siler
# ---------------------------------------------------------------------
$MDBR -e "DELETE FROM mukellef_evrak_muafiyet;
          UPDATE kullanicilar SET musavir_id=2 WHERE kullanici_adi='musavir';" >/dev/null 2>&1

echo ""
echo "====================================="
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "====================================="
[ $k -eq 0 ] && exit 0 || exit 1
