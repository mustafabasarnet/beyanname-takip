#!/bin/bash
# =====================================================================
#  ÖDEME BİLDİRİMİ — E-POSTA GÖNDERME REGRESYON TESTİ
#
#  Kapsam:
#   1. Migration idempotent (mail ayarları DB'de)
#   2. Bildirim sayfasında "Mail Gönder" düğmesi + e-posta bilgisi
#   3. E-postasız mükellefte "karttan ekleyin" uyarısı
#   4. POST akışı:
#        - mail_etkin kapalıyken  → "E-posta gönderimi kapalı"
#        - alıcı e-postası yoksa  → "geçerli bir e-posta tanımlı değil"
#        - gönderici yoksa        → "Gönderici e-posta adresi tanımlı değil"
#        - ayarlar tamamsa        → gönderim denenir (sonuç başarı veya hata,
#                                   ikisi de hattın çalıştığını kanıtlar)
#   5. Yetki: personel erişemez, CSRF'siz POST engellenir
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin / musavir / personel (Test1234) kullanıcıları var.
#  Not: Test kendi verisini kurar; e-posta gerçekten GÖNDERİLMEZ
#       (SMTP/MTA olmadan mail() hata verir — hata mesajı beklenir).
#  Kullanım:  bash tests/odeme_mail_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
KOK="$(cd "$(dirname "$0")/.." && pwd)"
J=/tmp/omt_a.txt
JP=/tmp/omt_p.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

giris(){ rm -f "$2"; curl -s -c "$2" -o /tmp/omt_f.html $B/giris
  local t; t=$(grep -oP 'name="csrf_beyanname" value="\K[^"]+' /tmp/omt_f.html|head -1)
  curl -s -b "$2" -c "$2" -o /dev/null -d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234" $B/giris; }

# Bildirim sayfasını çeker ve taze CSRF döndürür (satır sonu token)
sayfa(){ # $1=çerez $2=mükellef $3=çıktı dosyası
  curl -s -b "$1" -c "$1" "$B/odeme/bildirim/$2?yil=2026&ay=8" -o "$3"
  grep -oP 'name="csrf_beyanname" value="\K[^"]+' "$3" | head -1
}

veriKur(){
$MDBR -e "
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE beyanname_takip; TRUNCATE mukellef_beyannameleri; TRUNCATE ozel_odemeler;
DELETE FROM mukellefler; ALTER TABLE mukellefler AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
INSERT IGNORE INTO musavirler (id,unvan,ad_soyad,buro_adi,aktif) VALUES (1,'SMMM','Ali Yılmaz','Yılmaz',1);
INSERT INTO mukellefler (id,musavir_id,kod,unvan,mukellef_tipi,vergi_kimlik_no,tc_kimlik_no,defter_tipi,vergi_dairesi,ise_baslama_tarihi,eposta,aktif) VALUES
 (1,1,'M001','ALFA İNŞAAT LTD.','tuzel','1112223334',NULL,'bilanco','Merkez VD','2019-01-01','alfa@ornek.com',1),
 (2,1,'M002','BETA TİCARET','tuzel','2223334445',NULL,'bilanco','Merkez VD','2019-01-01','',1);
INSERT INTO mukellef_beyannameleri (mukellef_id,beyanname_turu_id,aktif) VALUES (1,1,1),(2,1,1);
INSERT INTO beyanname_takip (mukellef_id,beyanname_turu_id,yil,donem_no,donem_adi,donem_baslangic,donem_bitis,yasal_son_tarih,son_tarih,durum,tahakkuk_tutari,damga_tutari,created_at,updated_at) VALUES
 (1,1,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','ONAYLANDI',45000.00,791.00,NOW(),NOW()),
 (2,1,2026,7,'Temmuz 2026','2026-07-01','2026-07-31','2026-08-28','2026-08-28','ONAYLANDI',10000.00,791.00,NOW(),NOW());
-- Test başlangıç ayarları
UPDATE ayarlar SET deger='0' WHERE anahtar='mail_etkin';
UPDATE ayarlar SET deger='' WHERE anahtar='mail_gonderici_eposta';
UPDATE ayarlar SET deger='' WHERE anahtar='mail_gonderici_ad';
"
}

veriKur

echo "=== 1) MIGRATION + AYARLAR ==="
$MDBR < "$KOK/database/migration_odeme_mail.sql" >/dev/null 2>&1
for a in mail_etkin mail_gonderici_eposta mail_gonderici_ad mail_konu; do
  ol "ayar: $a var" "1" "$($MDB -e "SELECT COUNT(*) FROM ayarlar WHERE anahtar='$a'")"
done
ol "mail_konu şablonu" "Ödeme Bildirimi — {donem}" "$($MDB -e "SELECT deger FROM ayarlar WHERE anahtar='mail_konu'")"

echo "=== 2) BİLDİRİM SAYFASI — DÜĞME ==="
giris admin $J
T=$(sayfa $J 1 /tmp/omt_b1.html)
ol "Mail Gönder düğmesi var" "1" "$(grep -c 'Mail Gönder' /tmp/omt_b1.html | awk '{print ($1>0)?1:0}')"
ol "Form eylem bildirim-mail" "1" "$(grep -c 'odeme/bildirim-mail/1' /tmp/omt_b1.html | awk '{print ($1>0)?1:0}')"
ol "Mükellef e-postası görünüyor" "1" "$(grep -c 'alfa@ornek.com' /tmp/omt_b1.html | awk '{print ($1>0)?1:0}')"

T2=$(sayfa $J 2 /tmp/omt_b2.html)
ol "E-postasızda kart uyarısı" "1" "$(grep -c 'Mükellef kartından ekleyin' /tmp/omt_b2.html | awk '{print ($1>0)?1:0}')"

echo "=== 3) POST — AYAR KAPALIYKEN ==="
curl -s -b $J -c $J -L -d "csrf_beyanname=$T2" "$B/odeme/bildirim-mail/2?yil=2026&ay=8" -o /tmp/omt_r1.html
ol "Kapalı mesajı" "1" "$(grep -c 'E-posta gönderimi kapalı' /tmp/omt_r1.html | awk '{print ($1>0)?1:0}')"

echo "=== 4) POST — E-POSTASIZ MÜKELLEF ==="
$MDBR -e "UPDATE ayarlar SET deger='1' WHERE anahtar='mail_etkin';"
curl -s -b $J -c $J -L -d "csrf_beyanname=$T2" "$B/odeme/bildirim-mail/2?yil=2026&ay=8" -o /tmp/omt_r2.html
ol "Alıcı e-posta yok uyarısı" "1" "$(grep -c 'geçerli bir e-posta tanımlı değil' /tmp/omt_r2.html | awk '{print ($1>0)?1:0}')"

echo "=== 5) POST — GÖNDERİCİ YOK ==="
curl -s -b $J -c $J -L -d "csrf_beyanname=$T" "$B/odeme/bildirim-mail/1?yil=2026&ay=8" -o /tmp/omt_r3.html
ol "Gönderici yok uyarısı" "1" "$(grep -c 'Gönderici e-posta adresi tanımlı değil' /tmp/omt_r3.html | awk '{print ($1>0)?1:0}')"

echo "=== 6) POST — AYARLAR TAMAM (GÖNDERİM DENENİR) ==="
$MDBR -e "UPDATE ayarlar SET deger='muhasebe@buro.test' WHERE anahtar='mail_gonderici_eposta';
          UPDATE ayarlar SET deger='Test Büro' WHERE anahtar='mail_gonderici_ad';"
T=$(sayfa $J 1 /tmp/omt_b3.html)
curl -s -b $J -c $J -L -d "csrf_beyanname=$T" "$B/odeme/bildirim-mail/1?yil=2026&ay=8&ucret=1" -o /tmp/omt_r4.html
# MTA olmadığı için mail() hata verir; ama hata mesajı hattın çalıştığını gösterir.
ol "Gönderim denendi (başarı veya hata mesajı)" "1" \
   "$(grep -cE 'gönderildi: alfa@ornek.com|gönderilemedi' /tmp/omt_r4.html | awk '{print ($1>0)?1:0}')"
ol "Hata ayrıntısı döndü" "1" "$(grep -c 'Unable to send email' /tmp/omt_r4.html | awk '{print ($1>0)?1:0}')"

echo "=== 7) YETKİ ==="
# Personel: kendi oturumundan geçerli CSRF (panelden) ile POST → auth filtresi 302 verir
giris personel $JP
TP=$(curl -s -b $JP -c $JP "$B/panel" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1)
[ -z "$TP" ] && TP=$(curl -s -b $JP -c $JP "$B/panel" | grep -oP 'name="csrf_beyanname" value="\K[^"]+' | head -1)
# Not: CI4 POST sonrası yönlendirmelerde 303 (See Other) kullanır.
ol "Personel POST'a erişemez" "303" "$(curl -s -b $JP -c $JP -o /dev/null -w '%{http_code}' -d "csrf_beyanname=$TP" "$B/odeme/bildirim-mail/1")"
ol "Personel bildirim sayfasına erişemez" "302" "$(curl -s -b $JP -o /dev/null -w '%{http_code}' "$B/odeme/bildirim/1")"
# Admin oturumuyla CSRF'siz POST → 403
ol "CSRF'siz POST engelli" "403" "$(curl -s -b $J -o /dev/null -w '%{http_code}' -X POST "$B/odeme/bildirim-mail/1")"

echo ""; echo "======"
[ $k -eq 0 ] && echo "BASARILI ($g/$((g+k)))" || echo "$k HATA ($g/$((g+k)))"
