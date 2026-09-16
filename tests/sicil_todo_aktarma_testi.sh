#!/bin/bash
# =====================================================================
#  SİCİL — ŞABLONDAN TODO AKTARMA TESTİ
#
#  Senaryo (kullanıcı isteği):
#    "Yeni işlem eklerken şablon todo atıyor; ama şablona SONRADAN todo
#     eklediğimde eski işleme gelmiyor. Güncelle dediğimde yalnız yeni
#     todo o işleme eklensin, eski/tamamlanan kayıtlara dokunmasın."
#
#  Test ettikleri:
#    1) İşlem açılışında şablon todoları üretilir (2 todo)
#    2) Şablona yeni todo eklenince detayda "🔄 Şablondan N yeni todo ekle"
#       butonu görünür (eksik sayısı doğru, kendiliğinden eklenmez)
#    3) Buton ile ekleme: yalnız EKSİK todo eklenir
#    4) Mevcut todo'lar bozulmaz (tamamlananın durum/tarih/yapan bilgisi sabit)
#    5) Yeni todo'nun son tarihi işlem tarihinden hesaplanır
#    6) Idempotent: ikinci kez eklenmez ("eklenecek yeni todo yok")
#    7) Tamamlanmış işlem, yeni todo ile yeniden "Devam Ediyor" olur
#    8) CSRF'siz POST reddedilir (403)
#    9) Yetkisiz kullanıcı başka müşavirin mükellefine aktaramaz
#   10) Yetkili personel aktarabilir
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin|personel|musavir / Test1234 hesapları mevcut.
#  Kullanım:  bash tests/sicil_todo_aktarma_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
J=/tmp/sic_ak_oturum.txt
J2=/tmp/sic_ak_oturum2.txt
JAR=$J
S=/tmp/sic_ak_sayfa.html
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }
db(){ $MDB -e "$1"; }
tok(){ grep -oP 'name="csrf-token" content="\K[^"]+' "$1" | head -1; }
sayi(){ grep -oF "$1" "$2" | wc -l | tr -d ' '; }

girisYap(){ rm -f "$2"; curl -s -c "$2" -o /tmp/sic_ak_giris.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/sic_ak_giris.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# GET (yönlendirmeler takip edilir; sayfa $S'e iner, token tazelenir)
al(){ curl -s -L -b $JAR -c $JAR -o $S "$B$1"; }

# POST: $1 = yol, kalanı curl verisi → kod|hedef /tmp/sic_ak_cevap.txt, gövde /tmp/sic_ak_post.html
gonder(){ local url="$B$1"; shift; local t; t=$(tok $S)
  curl -s -b $JAR -c $JAR -o /tmp/sic_ak_post.html -w '%{http_code}|%{redirect_url}' \
       -d "csrf_beyanname=$t" "$@" "$url" > /tmp/sic_ak_cevap.txt; }

kod(){ cut -d'|' -f1 /tmp/sic_ak_cevap.txt; }
hedef(){ cut -d'|' -f2 /tmp/sic_ak_cevap.txt | sed "s|$B||"; }
jalin(){ grep -oP "\"$1\"\s*:\s*(\"[^\"]*\"|[0-9]+|true|false)" /tmp/sic_ak_post.html | head -1 | sed 's/.*:\s*//;s/"//g'; }

echo "=== 0) HAZIRLIK ==="
girisYap admin $J
al /sicil
ol "Admin girişi + Sicil İşlemleri açıldı" "1" "$(grep -c 'Sicil İşlemleri (' $S)"

db "DELETE FROM sicil_degisiklikleri WHERE turu_id IN (SELECT id FROM sicil_degisiklik_turleri WHERE kod IN ('AKTARMA_TEST','AKTARMA_TEST2'));
    DELETE FROM sicil_bildirim_kurallari WHERE degisiklik_turu_id IN (SELECT id FROM sicil_degisiklik_turleri WHERE kod IN ('AKTARMA_TEST','AKTARMA_TEST2'));
    DELETE FROM sicil_degisiklik_turleri WHERE kod IN ('AKTARMA_TEST','AKTARMA_TEST2');
    DELETE FROM mukellefler WHERE vergi_kimlik_no='9999999999';" >/dev/null 2>&1

MK=$(db "SELECT id FROM mukellefler WHERE musavir_id=1 AND deleted_at IS NULL ORDER BY id LIMIT 1")
ol "Test mükellefi bulundu" "1" "$([ -n "$MK" ] && echo 1 || echo 0)"

echo ""
echo "=== 1) ŞABLON (2 todo) + İŞLEM AÇILIŞI ==="
al /sicil-sablon/yeni
gonder /sicil-sablon/kaydet \
  -d "id=0" --data-urlencode "ad=AKTARMA TEST ŞABLONU" --data-urlencode "aciklama=otomatik test" -d "aktif=1" \
  -d "todo_id[]=0" --data-urlencode "todo_ad[]=Vergi Dairesine Bildirim" -d "todo_sure_tipi[]=GUN" -d "todo_sure_deger[]=10" -d "todo_belirli_tarih[]=" -d "todo_aktif[]=1" \
  -d "todo_id[]=0" --data-urlencode "todo_ad[]=SGK Bildirimi" -d "todo_sure_tipi[]=IS_GUNU" -d "todo_sure_deger[]=5" -d "todo_belirli_tarih[]=" -d "todo_aktif[]=1"
SAB=$(hedef | grep -oP '/sicil-sablon/duzenle/\K[0-9]+')
ol "Şablon oluşturuldu (2 todo tanımı)" "2" "$(db "SELECT COUNT(*) FROM sicil_bildirim_kurallari WHERE degisiklik_turu_id=$SAB")"

al /sicil/ekle
gonder /sicil/kaydet -d "id=0" -d "mukellef_id=$MK" -d "turu_id=$SAB" -d "degisiklik_tarihi=2026-09-07" --data-urlencode "aciklama=aktarma testi"
DEG=$(jalin id)
ol "İşlem oluşturuldu (JSON durum=true)" "true" "$(jalin durum)"
ol "İşleme 2 todo üretildi" "2" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG")"

al /sicil/detay/$DEG
ol "Detayda Todo Listesi (2)" "1" "$(grep -c 'Todo Listesi (2)' $S)"
ol "Eksik todo yok → aktarma butonu GÖRÜNMEZ" "0" "$(grep -c 'todo-guncelle' $S)"
ol "Bilgi notu: tüm todolar aktarılmış" "1" "$(grep -c 'Şablondaki tüm aktif todolar bu işleme aktarılmış' $S)"

echo ""
echo "=== 2) BİR TODO TAMAMLANDI + ŞABLONA YENİ TODO EKLENDİ ==="
G1=$(db "SELECT id FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG ORDER BY id LIMIT 1")
al /sicil/detay/$DEG
gonder /sicil/todo-durum -d "id=$G1" -d "durum=TAMAM"
ol "Todo tamamlandı (yapıldı)" "TAMAM" "$(db "SELECT durum FROM sicil_bildirim_gorevleri WHERE id=$G1")"
TAM_ONCE=$(db "SELECT tamamlanma_tarihi FROM sicil_bildirim_gorevleri WHERE id=$G1")
SON_ONCE=$(db "SELECT son_tarih FROM sicil_bildirim_gorevleri WHERE id=$G1")

T1=$(db "SELECT id FROM sicil_bildirim_kurallari WHERE degisiklik_turu_id=$SAB ORDER BY oncelik, id LIMIT 1")
T2=$(db "SELECT id FROM sicil_bildirim_kurallari WHERE degisiklik_turu_id=$SAB AND id<>$T1 ORDER BY oncelik, id LIMIT 1")
al /sicil-sablon/duzenle/$SAB
gonder /sicil-sablon/kaydet \
  -d "id=$SAB" --data-urlencode "ad=AKTARMA TEST ŞABLONU" --data-urlencode "aciklama=otomatik test" -d "aktif=1" \
  -d "todo_id[]=$T1" --data-urlencode "todo_ad[]=Vergi Dairesine Bildirim" -d "todo_sure_tipi[]=GUN" -d "todo_sure_deger[]=10" -d "todo_belirli_tarih[]=" -d "todo_aktif[]=1" \
  -d "todo_id[]=$T2" --data-urlencode "todo_ad[]=SGK Bildirimi" -d "todo_sure_tipi[]=IS_GUNU" -d "todo_sure_deger[]=5" -d "todo_belirli_tarih[]=" -d "todo_aktif[]=1" \
  -d "todo_id[]=0" --data-urlencode "todo_ad[]=Yeni Eklenen Bildirim" -d "todo_sure_tipi[]=GUN" -d "todo_sure_deger[]=3" -d "todo_belirli_tarih[]=" -d "todo_aktif[]=1"
ol "Şablona yeni todo eklendi (3 tanım)" "3" "$(db "SELECT COUNT(*) FROM sicil_bildirim_kurallari WHERE degisiklik_turu_id=$SAB")"
ol "İşlem hâlâ 2 todo (kendiliğinden eklenmez)" "2" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG")"

echo ""
echo "=== 3) DETAYDA AKTARMA BUTONU ==="
al /sicil/detay/$DEG
ol "Buton görünür: 🔄 Şablondan 1 yeni todo ekle" "1" "$(sayi 'Şablondan 1 yeni todo ekle' $S)"
ol "Buton eksik todo adını gösterir" "1" "$(sayi 'Yeni Eklenen Bildirim' $S | awk '{print ($1>=1)?1:0}')"
ol "Bilgi notu: henüz aktarılmadı" "1" "$(grep -c 'henüz bu işleme' $S)"
ol "Buton POST formu (todo-guncelle/$DEG)" "1" "$(sayi "sicil/todo-guncelle/$DEG" $S)"
ol "Form CSRF alanı taşıyor" "1" "$(grep -c 'name="csrf_beyanname"' $S)"
ol "Onay metni: mevcut/tamamlanan todolara dokunulmaz" "1" "$(sayi 'Mevcut ve tamamlanmış todolara dokunulmaz' $S)"

echo ""
echo "=== 4) AKTAR (güncelle) — YALNIZ EKSİK EKLENİR ==="
gonder /sicil/todo-guncelle/$DEG
ol "POST 302 ile detay sayfasına döner" "/sicil/detay/$DEG" "$(hedef)"
al "$(hedef)"
ol "Flash: 1 yeni todo eklendi" "1" "$(grep -c '1 yeni todo eklendi: Yeni Eklenen Bildirim' $S)"
ol "Flash: mevcutlara dokunulmadı" "1" "$(grep -c 'Mevcut ve tamamlanmış todolara dokunulmadı' $S)"
ol "Detayda Todo Listesi (3)" "1" "$(grep -c 'Todo Listesi (3)' $S)"
ol "Aktarma sonrası buton kaybolur" "0" "$(grep -c 'todo-guncelle' $S)"

ol "DB: işlemde 3 todo" "3" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG")"
ol "DB: yeni todo BEKLIYOR (yapılmadı)" "BEKLIYOR" "$(db "SELECT durum FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG AND ad='Yeni Eklenen Bildirim'")"
ol "DB: yeni todo son tarihi 07.09+3g = 10.09.2026" "2026-09-10" "$(db "SELECT son_tarih FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG AND ad='Yeni Eklenen Bildirim'")"
ol "DB: yeni todo kaynak tanıma bağlı (kural_id dolu)" "1" "$(db "SELECT IF(kural_id>0,1,0) FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG AND ad='Yeni Eklenen Bildirim'")"

echo ""
echo "=== 5) MEVCUT / TAMAMLANAN TODO BOZULMADI ==="
ol "Tamamlanan todo durumu TAMAM (sabit)" "TAMAM" "$(db "SELECT durum FROM sicil_bildirim_gorevleri WHERE id=$G1")"
ol "Tamamlanan todo tamamlanma tarihi sabit" "$TAM_ONCE" "$(db "SELECT tamamlanma_tarihi FROM sicil_bildirim_gorevleri WHERE id=$G1")"
ol "Tamamlanan todo son tarihi sabit" "$SON_ONCE" "$(db "SELECT son_tarih FROM sicil_bildirim_gorevleri WHERE id=$G1")"
ol "Detay: tamamlanan satır ✓ Yapıldı kalır" "1" "$(sayi '✓ Yapıldı' $S | awk '{print ($1>=1)?1:0}')"
ol "Üst durum ISLEMDE (Devam Ediyor)" "ISLEMDE" "$(db "SELECT durum FROM sicil_degisiklikleri WHERE id=$DEG")"
ol "İlerleme 1/3 tamamlandı (yeni todo paydaya girdi)" "1" "$(grep -c '1/3 tamamlandı' $S)"

echo ""
echo "=== 6) TEKRAR AKTARMA → EKLEMEZ (idempotent) ==="
gonder /sicil/todo-guncelle/$DEG
al "$(hedef)"
ol "Flash: eklenecek yeni todo yok" "1" "$(grep -c 'eklenecek yeni todo yok' $S)"
ol "DB: todo sayısı hâlâ 3" "3" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG")"

echo ""
echo "=== 7) TAMAMLANMIŞ İŞLEM + YENİ TODO → YENİDEN DEVAM EDİYOR ==="
for gid in $(db "SELECT id FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG AND durum IN ('BEKLIYOR','HAZIR','GONDERILDI')"); do
  al /sicil/detay/$DEG
  gonder /sicil/todo-durum -d "id=$gid" -d "durum=TAMAM"
done
ol "Tüm todo bitince işlem TAMAM" "TAMAM" "$(db "SELECT durum FROM sicil_degisiklikleri WHERE id=$DEG")"
T3=$(db "SELECT id FROM sicil_bildirim_kurallari WHERE degisiklik_turu_id=$SAB AND ad='Yeni Eklenen Bildirim' ORDER BY id DESC LIMIT 1")
al /sicil-sablon/duzenle/$SAB
gonder /sicil-sablon/kaydet \
  -d "id=$SAB" --data-urlencode "ad=AKTARMA TEST ŞABLONU" --data-urlencode "aciklama=otomatik test" -d "aktif=1" \
  -d "todo_id[]=$T1" --data-urlencode "todo_ad[]=Vergi Dairesine Bildirim" -d "todo_sure_tipi[]=GUN" -d "todo_sure_deger[]=10" -d "todo_belirli_tarih[]=" -d "todo_aktif[]=1" \
  -d "todo_id[]=$T2" --data-urlencode "todo_ad[]=SGK Bildirimi" -d "todo_sure_tipi[]=IS_GUNU" -d "todo_sure_deger[]=5" -d "todo_belirli_tarih[]=" -d "todo_aktif[]=1" \
  -d "todo_id[]=$T3" --data-urlencode "todo_ad[]=Yeni Eklenen Bildirim" -d "todo_sure_tipi[]=GUN" -d "todo_sure_deger[]=3" -d "todo_belirli_tarih[]=" -d "todo_aktif[]=1" \
  -d "todo_id[]=0" --data-urlencode "todo_ad[]=Sonradan Gelen Todo" -d "todo_sure_tipi[]=GUN" -d "todo_sure_deger[]=4" -d "todo_belirli_tarih[]=" -d "todo_aktif[]=1"
al /sicil/detay/$DEG
gonder /sicil/todo-guncelle/$DEG
ol "Tamamlanmış işlemde aktarma çalıştı" "/sicil/detay/$DEG" "$(hedef)"
al "$(hedef)"
ol "Flash: 1 yeni todo (Sonradan Gelen Todo)" "1" "$(grep -c '1 yeni todo eklendi: Sonradan Gelen Todo' $S)"
ol "İşlem yeniden ISLEMDE (Devam Ediyor)" "ISLEMDE" "$(db "SELECT durum FROM sicil_degisiklikleri WHERE id=$DEG")"
ol "Yeni todo açık doğdu (BEKLIYOR)" "BEKLIYOR" "$(db "SELECT durum FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG AND ad='Sonradan Gelen Todo'")"

echo ""
echo "=== 8) GÜVENLİK: CSRF ==="
CS=$(curl -s -b $J -c $J -o /dev/null -w '%{http_code}' -d "id=1" "$B/sicil/todo-guncelle/$DEG")
ol "CSRF'siz POST reddedildi (403)" "403" "$CS"

echo ""
echo "=== 9) YETKİ: BAŞKA MÜŞAVİRİN İŞLEMİNE AKTARAMAZ ==="
db "INSERT INTO mukellefler (musavir_id, unvan, mukellef_tipi, vergi_kimlik_no, ise_baslama_tarihi)
    VALUES (2, 'YETKİ TESTİ (MÜŞAVİR 2)', 'tuzel', '9999999999', '2026-01-01');
    INSERT INTO sicil_degisiklik_turleri (ad, kod, aciklama, aktif) VALUES ('AKTARMA TEST ŞABLONU 2','AKTARMA_TEST2','test',1);" >/dev/null 2>&1
MK2=$(db "SELECT id FROM mukellefler WHERE vergi_kimlik_no='9999999999' LIMIT 1")
SAB2=$(db "SELECT id FROM sicil_degisiklik_turleri WHERE kod='AKTARMA_TEST2'")
db "INSERT INTO sicil_bildirim_kurallari (degisiklik_turu_id, ad, sure_tipi, sure_deger, oncelik, aktif)
    VALUES ($SAB2, 'Yetki Testi Todo', 'GUN', 5, 10, 1);
    INSERT INTO sicil_degisiklikleri (mukellef_id, turu_id, degisiklik_tarihi, durum, kaydeden_id)
    VALUES ($MK2, $SAB2, '2026-09-07', 'ISLEMDE', 1);" >/dev/null 2>&1
DEG2=$(db "SELECT id FROM sicil_degisiklikleri WHERE mukellef_id=$MK2 ORDER BY id DESC LIMIT 1")
ol "Yetki testi işlemi hazır (todo'suz)" "0" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG2")"

girisYap musavir $J2
JAR=$J2
al /sicil
gonder /sicil/todo-guncelle/$DEG2
ol "Müşavir 1 kullanıcısı → erişim reddi (listeye döner)" "/sicil" "$(hedef)"
al "$(hedef)"
ol "Müşavir 1: 'Bu kayda erişemezsiniz' flash'ı" "1" "$(grep -c 'Bu kayda erişemezsiniz' $S)"
ol "Yetkisiz aktarma DB'ye yazmadı (0 todo)" "0" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG2")"
al /sicil/detay/$DEG2
ol "Müşavir 1 işlem detayını açamaz (listeye döner)" "1" "$(grep -c 'Bu kayda erişemezsiniz' $S)"

JAR=$J
al /sicil
gonder /sicil/todo-guncelle/$DEG2
ol "Yetkili kullanıcı (admin) ise aktarma çalışır" "1" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG2")"

echo ""
echo "=== 10) YETKİLİ PERSONEL AKTARABİLİR ==="
girisYap personel $J2
JAR=$J2
al /sicil
gonder /sicil/todo-guncelle/$DEG
ol "Personel → detay sayfasına döner" "/sicil/detay/$DEG" "$(hedef)"
al "$(hedef)"
ol "Personel kapsamındaki işleme aktarma yaptı (todo 4)" "4" "$(db "SELECT COUNT(*) FROM sicil_bildirim_gorevleri WHERE sicil_degisikligi_id=$DEG")"
ol "Personel için flash: eklenecek yeni todo yok" "1" "$(grep -c 'eklenecek yeni todo yok' $S)"
JAR=$J

echo ""
echo "=== 11) TEMİZLİK ==="
db "DELETE FROM sicil_degisiklikleri WHERE turu_id IN (SELECT id FROM sicil_degisiklik_turleri WHERE kod IN ('AKTARMA_TEST','AKTARMA_TEST2'));
    DELETE FROM sicil_bildirim_kurallari WHERE degisiklik_turu_id IN (SELECT id FROM sicil_degisiklik_turleri WHERE kod IN ('AKTARMA_TEST','AKTARMA_TEST2'));
    DELETE FROM sicil_degisiklik_turleri WHERE kod IN ('AKTARMA_TEST','AKTARMA_TEST2');
    DELETE FROM mukellefler WHERE vergi_kimlik_no='9999999999';" >/dev/null 2>&1
ol "Test verisi temizlendi" "0" "$(db "SELECT COUNT(*) FROM sicil_degisiklik_turleri WHERE kod IN ('AKTARMA_TEST','AKTARMA_TEST2')")"

echo ""
echo "========================================"
if [ $k -eq 0 ]; then echo "TÜM TESTLER BAŞARILI ($g geçti / $((g+k)))"; else echo "$k HATA ($g geçti / $((g+k)))"; fi
rm -f $J $J2
[ $k -eq 0 ] || exit 1
