#!/bin/bash
# =====================================================================
#  EXCEL/CSV'DEN MÜKELLEF AKTARMA — REGRESYON TESTİ (sunucu tarafı)
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin/Test1234, personel/Test1234, musavir/Test1234 var,
#            veritabanında id=1 "MEVCUT FIRMA LTD" (VKN 1112223334) kayıtlı.
#  Kullanım:  bash tests/ice_aktar_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ # $1=kullanici  $2=cerez dosyasi
  rm -f "$2"
  curl -s -c "$2" -o /tmp/f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris
}

# Önizleme yapar, /tmp/onz.html'e yazar
onizle(){ # $1=cerez  $2=dosya  $3=musavir_id
  curl -s -b "$1" -c "$1" -o /tmp/f.html $B/mukellefler/ice-aktar
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/f.html|head -1)
  curl -s -b "$1" -c "$1" -L -o /tmp/onz.html \
    -F "csrf_beyanname=$t" -F "dosya=@$2" -F "musavir_id=$3" $B/mukellefler/ice-aktar/onizle
}

# Önizlemedeki tüm "eklenecek" satırları onaylar
onayla(){ # $1=cerez  $2=ek parametreler
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/onz.html|head -1)
  local ids; ids=$(python3 -c "
import re;s=open('/tmp/onz.html',encoding='utf-8').read()
print(' '.join('-d satirlar[]='+v for v in re.findall(r'name=\"satirlar\[\]\"[^>]*?value=\"(\d+)\"',s,re.S)))")
  curl -s -b "$1" -c "$1" -o /dev/null -D /tmp/h.txt \
    -d "csrf_beyanname=$t" -d "secim=1" $ids $2 $B/mukellefler/ice-aktar/onayla
}

sayac(){ python3 -c "
import re;s=open('/tmp/onz.html',encoding='utf-8').read()
d=dict((m.group(1).upper(),m.group(2)) for m in re.finditer(r'<div class=\"etiket\">([^<]+)</div>\s*<div class=\"deger\">(\d+)',s))
print(d.get('$1','yok'))"; }

temizle(){ $MDB -e "DELETE FROM mukellefler WHERE id>1; DELETE FROM mukellef_beyannameleri; DELETE FROM beyanname_takip;"; }

# ---------------------------------------------------------------- test dosyaları
hazirla(){
python3 - <<'PY'
h='Kod;Ünvan;Tip;VKN;TCKN;Vergi Dairesi;Defter Tipi;İşe Başlama;Takip Başlangıcı;Terk Tarihi;Beyannameler;Genç Girişimci;GG Başlangıç Yılı;Muhasebe Ücreti;Telefon;E-posta;Yetkili Kişi;Faaliyet Konusu;NACE Kodu;SGK Sicil;Adres;Notlar'
s=[h,
 'Y001;ÖZKAN İNŞAAT LTD. ŞTİ.;Tüzel;9876543210;;Nevşehir;Bilanço;01.01.2021;;;KDV1_A,MUHSGK_A,KURUMLAR,KURUM_GECICI;Hayır;;7.500,00;0384 111 22 33;ozkan@firma.com;Mustafa Özkan;İnşaat;4120;9876543210123;Merkez;',
 'Y002;ZEYNEP ÇELİK;Gerçek;;22233344455;Ürgüp;İşletme;15.06.2024;;;KDV1_A,MUHSGK_A,YILLIK_GV,GELIR_GECICI;Evet;2024;3.250,50;;zeynep@mail.com;;Kuaför;9602;;;GG var',
 'Y003;MEVCUT ÇAKIŞAN A.Ş.;Tüzel;1112223334;;Nevşehir;Bilanço;01.01.2020;;;KDV1_A,KURUMLAR;Hayır;;;;;;;;;;',
 ';;Tüzel;5556667778;;;Bilanço;01.01.2020;;;KDV1_A;Hayır;;;;;;;;;;',
 'Y005;HATALI TARİH LTD;Tüzel;4443332221;;Kayseri;Bilanço;abc;;;KDV1_A;Hayır;;;;;;;;;;',
 'Y006;KISA VKN LTD;Tüzel;123;;Kayseri;Bilanço;01.01.2020;;;KDV1_A;Hayır;;;;;;;;;;',
 'Y007;TERK ÖNCE LTD;Tüzel;7778889990;;Kayseri;Bilanço;01.01.2025;;01.01.2020;KDV1_A;Hayır;;;;;;;;;;',
 'Y008;MÜKERRER BİR;Tüzel;3334445556;;Nevşehir;Bilanço;01.01.2022;;;KDV1_A;Hayır;;;;;;;;;;',
 'Y009;MÜKERRER İKİ;Tüzel;3334445556;;Nevşehir;Bilanço;01.01.2022;;;KDV1_A;Hayır;;;;;;;;;;',
 'Y010;TİP BOŞ ŞAHIS;;;44455566677;Avanos;;01.03.2023;;;KDV1_A,YILLIK_GV;Hayır;;;;;;;;;;',
 'Y011;TANINMAZ TÜR LTD;Tüzel;6665554443;;Kayseri;Bilanço;01.01.2020;;;KDV1_A,UYDURMA_KOD,Kurumlar;Hayır;;;;;;;;;;',
 'Y012;EXCEL TARİH LTD;Tüzel;8887776665;;Kayseri;Bilanço;44927;;;KDV1_A;Hayır;;1234,56;;bozuk-eposta;;;;;;',
 'Y013;NUMARA YER DEĞİŞTİ;Gerçek;55566677788;;Niğde;İşletme;01.05.2022;;;KDV1_A,YILLIK_GV;evet;;;;;;;;;;',
 'Y014;KISA AD İLE;Tüzel;2223334445;;Kayseri;Bilanço;01.01.2020;;;KDV1 (Ay),Kurumlar,Kurum Geçici;Hayır;;;;;;;;;;']
open('/tmp/t_zor.csv','w',encoding='utf-8-sig').write('\n'.join(s)+'\n')

# virgül ayraçlı + tırnak içinde virgül
v=[h.replace(';',','),
   'V001,"ŞAHİN GIDA, TİCARET LTD.",Tüzel,1010101010,,Nevşehir,Bilanço,01.02.2022,,,"KDV1_A,KURUMLAR",Hayır,,"1.500,00",,,,,,,,']
open('/tmp/t_virgul.csv','w',encoding='utf-8-sig').write('\n'.join(v)+'\n')

# Windows-1254 (BOM'suz)
w=h+chr(10)+'W001;ÇAĞLAYAN ŞİRKETİ ÖZĞÜR;Tüzel;2020202020;;Ürgüp;Bilanço;01.03.2021;;;KDV1_A;Hayır;;;;;;;;;;'
open('/tmp/t_w1254.csv','wb').write((w+chr(10)).encode('cp1254'))

# hatalı dosyalar
open('/tmp/t_kotu.pdf','w').write('merhaba')
open('/tmp/t_yanlis.csv','w',encoding='utf-8-sig').write('Yanlis;Basliklar;Burada\na;b;c\n')
open('/tmp/t_bos.csv','w',encoding='utf-8-sig').write(h+'\n')
open('/tmp/t_cok.csv','w',encoding='utf-8-sig').write('\n'.join(
   [h]+[f'X{i};TEST {i};Tüzel;{4000000000+i};;X;Bilanço;01.01.2022;;;KDV1_A;Hayır;;;;;;;;;;' for i in range(2001)]))
PY
}

hazirla
temizle
giris admin /tmp/t_admin.txt

echo "=== 1) ŞABLON İNDİRME ==="
curl -s -b /tmp/t_admin.txt "$B/mukellefler/sablon-indir" -o /tmp/t_sab.csv
ol "Örnekli şablon indi" "1" "$(head -1 /tmp/t_sab.csv | grep -c 'Ünvan')"
ol "Örnek satırlar var" "3" "$(($(wc -l < /tmp/t_sab.csv) - 1))"
ol "UTF-8 BOM var" "1" "$(head -c 3 /tmp/t_sab.csv | grep -c $'\xEF\xBB\xBF')"
curl -s -b /tmp/t_admin.txt "$B/mukellefler/sablon-indir?bos=1" -o /tmp/t_sab2.csv
ol "Boş şablonda veri yok" "1" "$(wc -l < /tmp/t_sab2.csv)"

echo ""
echo "=== 2) ÖNİZLEME SINIFLANDIRMASI ==="
onizle /tmp/t_admin.txt /tmp/t_zor.csv 1
ol "Eklenecek = 8"    "8"  "$(sayac EKLENECEK)"
ol "Atlanacak = 2"    "2"  "$(sayac ATLANACAK)"
ol "Hatalı = 4"       "4"  "$(sayac HATALI)"
ol "Toplam satır = 14" "14" "$(sayac 'TOPLAM SATIR')"
ol "Mevcut kayıt tespiti" "1" "$(grep -c 'Sistemde zaten kayıtlı' /tmp/onz.html)"
ol "Dosya içi mükerrer tespiti" "1" "$(grep -c 'satırında da var (mükerrer)' /tmp/onz.html)"
ol "Ünvan boş hatası" "1" "$(grep -c 'Ünvan boş olamaz' /tmp/onz.html)"
ol "Geçersiz tarih hatası" "1" "$(grep -c 'İşe başlama tarihi geçersiz' /tmp/onz.html)"
ol "Kısa VKN hatası" "1" "$(grep -c 'VKN 10 haneli olmalı' /tmp/onz.html)"
ol "Terk < başlama hatası" "1" "$(grep -c 'Terk tarihi işe başlama' /tmp/onz.html)"
ol "Tanınmayan tür uyarısı" "1" "$(grep -c 'UYDURMA_KOD' /tmp/onz.html)"
ol "Geçersiz e-posta uyarısı" "1" "$(grep -c 'E-posta geçersiz' /tmp/onz.html)"
ol "Numara yer değişimi uyarısı" "1" "$(grep -c 'TCKN olarak alındı' /tmp/onz.html)"
ol "ÖNİZLEME DB'ye YAZMADI" "1" "$($MDB -e 'select count(*) from mukellefler')"

echo ""
echo "=== 3) AKTARMA (dönem üretimi kapalı) ==="
onayla /tmp/t_admin.txt ""
ol "8 mükellef eklendi" "9" "$($MDB -e 'select count(*) from mukellefler')"
ol "Dönem üretilmedi" "0" "$($MDB -e 'select count(*) from beyanname_takip')"
ol "Türler bağlandı (4+4+1+2+2+1+2+3)" "19" "$($MDB -e 'select count(*) from mukellef_beyannameleri')"
ol "Excel seri no → 01.01.2023" "2023-01-01" "$($MDB -e "select ise_baslama_tarihi from mukellefler where kod='Y012'")"
ol "Türkçe karakter bozulmadı" "ÖZKAN İNŞAAT LTD. ŞTİ." "$($MDB -e "select unvan from mukellefler where kod='Y001'")"
ol "Para ayrıştırma (7.500,00)" "7500.00" "$($MDB -e "select muhasebe_ucreti from mukellefler where kod='Y001'")"
ol "Genç girişimci işaretlendi" "1|2024" "$($MDB -e "select concat(genc_girisimci,'|',gg_baslangic_yili) from mukellefler where kod='Y002'")"
ol "Tip boşken kimlikten tahmin" "gercek" "$($MDB -e "select mukellef_tipi from mukellefler where kod='Y010'")"
ol "Kısa ad ile tür eşleşti" "3" "$($MDB -e "select count(*) from mukellef_beyannameleri mb join mukellefler m on m.id=mb.mukellef_id where m.kod='Y014'")"
ol "Mükerrer olan eklenmedi" "1" "$($MDB -e "select count(*) from mukellefler where vergi_kimlik_no='3334445556'")"
ol "Mevcut kayıt değişmedi" "MEVCUT FIRMA LTD" "$($MDB -e "select unvan from mukellefler where id=1")"

echo ""
echo "=== 4) BOŞ SEÇİM AKTARMAYI ENGELLER ==="
temizle
onizle /tmp/t_admin.txt /tmp/t_zor.csv 1
tok=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/onz.html|head -1)
curl -s -b /tmp/t_admin.txt -c /tmp/t_admin.txt -o /dev/null -D /tmp/h.txt \
  -d "csrf_beyanname=$tok" -d "secim=1" $B/mukellefler/ice-aktar/onayla
ol "Hiç satır seçilmeyince eklenmedi" "1" "$($MDB -e 'select count(*) from mukellefler')"
ol "Aktarma ekranına döndü" "1" "$(grep -ci 'location:.*ice-aktar' /tmp/h.txt)"

echo ""
echo "=== 5) KISMİ SEÇİM ==="
onizle /tmp/t_admin.txt /tmp/t_zor.csv 1
tok=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/onz.html|head -1)
ilk2=$(python3 -c "
import re;s=open('/tmp/onz.html',encoding='utf-8').read()
v=re.findall(r'name=\"satirlar\[\]\"[^>]*?value=\"(\d+)\"',s,re.S)[:2]
print(' '.join('-d satirlar[]='+x for x in v))")
curl -s -b /tmp/t_admin.txt -c /tmp/t_admin.txt -o /dev/null \
  -d "csrf_beyanname=$tok" -d "secim=1" $ilk2 $B/mukellefler/ice-aktar/onayla
ol "Yalnızca 2 satır eklendi" "3" "$($MDB -e 'select count(*) from mukellefler')"

echo ""
echo "=== 6) DÖNEM ÜRETİMİ ==="
temizle
onizle /tmp/t_admin.txt /tmp/t_virgul.csv 1
onayla /tmp/t_admin.txt "-d donem_uret=1"
ol "Virgül ayraçlı dosya okundu" "1" "$($MDB -e "select count(*) from mukellefler where kod='V001'")"
ol "Tırnak içi virgül korundu" "ŞAHİN GIDA, TİCARET LTD." "$($MDB -e "select unvan from mukellefler where kod='V001'")"
ol "Para (1.500,00)" "1500.00" "$($MDB -e "select muhasebe_ucreti from mukellefler where kod='V001'")"
ol "Dönemler üretildi" "1" "$([ "$($MDB -e 'select count(*) from beyanname_takip')" -gt 0 ] && echo 1 || echo 0)"

echo ""
echo "=== 7) WINDOWS-1254 KODLAMA ==="
temizle
onizle /tmp/t_admin.txt /tmp/t_w1254.csv 1
onayla /tmp/t_admin.txt ""
ol "Türkçe karakter doğru çevrildi" "ÇAĞLAYAN ŞİRKETİ ÖZĞÜR" "$($MDB -e "select unvan from mukellefler where kod='W001'")"

echo ""
echo "=== 8) HATALI DOSYALAR ==="
temizle
onizle /tmp/t_admin.txt /tmp/t_kotu.pdf 1
ol "PDF reddedildi" "1" "$(grep -c 'Yalnızca CSV' /tmp/onz.html)"
onizle /tmp/t_admin.txt /tmp/t_yanlis.csv 1
ol "Yanlış başlık reddedildi" "1" "$(grep -c 'Şablon sütunları eşleşmedi' /tmp/onz.html)"
onizle /tmp/t_admin.txt /tmp/t_bos.csv 1
ol "Veri yok uyarısı" "1" "$(grep -c 'veri bulunamadı' /tmp/onz.html)"
onizle /tmp/t_admin.txt /tmp/t_cok.csv 1
ol "2000 satır sınırı" "1" "$(grep -c 'en fazla 2000' /tmp/onz.html)"
ol "Hiçbiri DB'ye yazılmadı" "1" "$($MDB -e 'select count(*) from mukellefler')"

echo ""
echo "=== 9) YETKİ ==="
giris personel /tmp/t_pers.txt
ol "Personel: sayfa kapalı" "302" "$(curl -s -b /tmp/t_pers.txt -o /dev/null -w '%{http_code}' $B/mukellefler/ice-aktar)"
ol "Personel: şablon kapalı" "302" "$(curl -s -b /tmp/t_pers.txt -o /dev/null -w '%{http_code}' $B/mukellefler/sablon-indir)"
curl -s -b /tmp/t_pers.txt -o /tmp/pl.html $B/mukellefler
ol "Personel: düğme görünmüyor" "0" "$(grep -c 'ice-aktar' /tmp/pl.html)"
ol "Oturumsuz GET engellendi" "302" "$(curl -s -o /dev/null -w '%{http_code}' $B/mukellefler/ice-aktar)"
ol "CSRF'siz POST engellendi" "403" "$(curl -s -b /tmp/t_admin.txt -o /dev/null -w '%{http_code}' -F "dosya=@/tmp/t_zor.csv" -F "musavir_id=1" $B/mukellefler/ice-aktar/onizle)"

echo ""
echo "=== 10) YETKİSİZ MÜŞAVİR ZORLAMA ==="
temizle
giris musavir /tmp/t_mus.txt
onizle /tmp/t_mus.txt /tmp/t_virgul.csv 2
onayla /tmp/t_mus.txt ""
ol "Yetkisiz müşavire atanmadı" "1" "$($MDB -e 'select distinct musavir_id from mukellefler where id>1')"

temizle
echo ""; echo "======"
[ $k -eq 0 ] && echo "BASARILI ($g/$((g+k)))" || echo "$k HATA ($g/$((g+k)))"
