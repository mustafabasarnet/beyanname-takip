#!/bin/bash
# =====================================================================
#  GİRİŞ — "BENİ HATIRLA" REGRESYON TESTİ
#
#  Kapsam:
#   1. Migration idempotent (tablo + hatirla_sure_gun ayarı)
#   2. Giriş sayfasında "Beni hatırla" kutusu
#   3. İşaretsiz giriş → kalıcı çerez OLUŞMAZ
#   4. İşaretli giriş → bt_hatirla çerezi + DB'de SHA-256 hash kaydı
#   5. Oturumsuz istek + çerez → otomatik giriş (panel 200)
#   6. Token rotasyonu: otomatik girişte eski token geçersiz olur
#   7. Çıkış → çerez silinir + DB kaydı temizlenir
#   8. Geçersiz çerez → otomatik giriş olmaz (giriş sayfasına düşer)
#   9. Süresi geçmiş çerez → otomatik giriş olmaz
#
#  Ön koşul: uygulama http://127.0.0.1:8099 adresinde çalışıyor,
#            admin / Test1234 kullanıcısı mevcut.
#  Not: Test kendi verisini kurar; çerez değerleri yalnızca geçici.
#  Kullanım:  bash tests/beni_hatirla_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
MDB="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip -N -B"
MDBR="/tmp/mdbc/usr/bin/mariadb --default-character-set=utf8mb4 --socket=/tmp/mysqlrun/m.sock beyanname_takip"
KOK="$(cd "$(dirname "$0")/.." && pwd)"
J=/tmp/bh_t.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi }

# Giriş sayfasını çeker, CSRF token'ını döndürür
tokenAl(){ curl -s -b "$J" -c "$J" "$B/giris" | grep -oP 'name="csrf_beyanname" value="\K[^"]+' | head -1; }

# Giriş yapar: $1=kullanıcı $2=beni_hatirla(1/0)
# Her çağrıda TAZE oturumla başlar (önceki girişin oturumu /giris'i
# panele yönlendirip token almayı engellemesin diye).
girisYap(){
  rm -f $J
  local t args=()
  t=$(tokenAl)
  args=(-d "csrf_beyanname=$t" -d "kimlik=$1" -d "sifre=Test1234")
  if [ "$2" = "1" ]; then
    args+=(-d "beni_hatirla=1")
  fi
  curl -s -b "$J" -c "$J" -o /dev/null "${args[@]}" "$B/giris"
}

# Çerezin değerini cookie jar'dan okur (jar satırı #HttpOnly_ ile başlar)
cerezDeger(){ grep -m1 'bt_hatirla' "$J" | awk '{print $NF}'; }

veriKur(){
$MDBR -e "
TRUNCATE hatirlanan_oturumlar;
UPDATE ayarlar SET deger='30' WHERE anahtar='hatirla_sure_gun';"
}

veriKur

echo "=== 1) MIGRATION + AYARLAR ==="
$MDBR < "$KOK/database/migration_beni_hatirla.sql" >/dev/null 2>&1
ol "hatirlanan_oturumlar tablosu var" "1" "$($MDB -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hatirlanan_oturumlar'")"
ol "hatirla_sure_gun ayarı var" "30" "$($MDB -e "SELECT deger FROM ayarlar WHERE anahtar='hatirla_sure_gun'")"

echo "=== 2) GİRİŞ SAYFASINDA KUTU ==="
rm -f $J
curl -s -c $J -b $J "$B/giris" -o /tmp/bh_g.html
ol "Beni hatırla kutusu var" "1" "$(grep -c 'name=\"beni_hatirla\"' /tmp/bh_g.html | awk '{print ($1>0)?1:0}')"

echo "=== 3) İŞARETSİZ GİRİŞ → ÇEREZ OLUŞMAZ ==="
girisYap admin 0
ol "bt_hatirla çerezi yok" "0" "$(grep -c 'bt_hatirla' $J | awk '{print ($1>0)?1:0}')"
ol "DB'de kayıt artmadı (0)" "0" "$($MDB -e "SELECT COUNT(*) FROM hatirlanan_oturumlar")"

echo "=== 4) İŞARETLİ GİRİŞ → ÇEREZ + DB HASH ==="
veriKur
girisYap admin 1
ol "bt_hatirla çerezi var" "1" "$(grep -c 'bt_hatirla' $J | awk '{print ($1>0)?1:0}')"
C1=$(cerezDeger)
ol "Çerez 64 hex karakter" "64" "${#C1}"
ol "DB'de 1 kayıt (admin)" "1|1" "$($MDB -e "SELECT CONCAT(COUNT(*),'|',kullanici_id) FROM hatirlanan_oturumlar")"
ol "DB'de ham çerez YOK (hash saklanıyor)" "0" "$($MDB -e "SELECT COUNT(*) FROM hatirlanan_oturumlar WHERE token_hash='$C1'")"
H1=$($MDB -e "SELECT token_hash FROM hatirlanan_oturumlar LIMIT 1")
ol "DB hash = SHA-256(çerez)" "1" "$([ "$H1" = "$(printf '%s' "$C1" | sha256sum | cut -d' ' -f1)" ] && echo 1 || echo 0)"

echo "=== 5) OTURUMSUZ + ÇEREZ → OTOMATİK GİRİŞ ==="
rm -f $J   # oturum çerezlerini at; yalnızca bt_hatirla gönder
curl -s -o /tmp/bh_p.html -w "%{http_code}" -H "Cookie: bt_hatirla=$C1" "$B/panel" > /tmp/bh_kod.txt
ol "Panel 200 (oturum yeniden kuruldu)" "200" "$(cat /tmp/bh_kod.txt)"
ol "Panel başlığı" "1" "$(grep -c 'Kontrol Paneli' /tmp/bh_p.html | awk '{print ($1>0)?1:0}')"

echo "=== 6) TOKEN ROTASYONU ==="
# Otomatik girişte eski token silinip yenisi üretilir
ol "Eski hash DB'de YOK" "0" "$($MDB -e "SELECT COUNT(*) FROM hatirlanan_oturumlar WHERE token_hash='$H1'")"
ol "Yeni hash DB'de VAR" "1" "$($MDB -e "SELECT COUNT(*) FROM hatirlanan_oturumlar WHERE token_hash<>'$H1'")"

echo "=== 7) ÇIKIŞ → TEMİZLİK ==="
girisYap admin 1   # çerezi jar'a geri al
C3=$(cerezDeger)
curl -s -b $J -c $J -o /dev/null "$B/cikis"
H3=$(printf '%s' "$C3" | sha256sum | cut -d' ' -f1)
ol "Çıkış sonrası sunulan token DB'den silindi" "0" "$($MDB -e "SELECT COUNT(*) FROM hatirlanan_oturumlar WHERE token_hash='$H3'")"
ol "Çerez jar'da bt_hatirla yok" "0" "$(grep -c 'bt_hatirla' $J | awk '{print ($1>0)?1:0}')"

echo "=== 8) GEÇERSİZ ÇEREZ → GİRİŞ SAYFASINA DÜŞER ==="
rm -f $J
curl -s -o /dev/null -w "%{http_code}" -H "Cookie: bt_hatirla=0123456789abcdef" "$B/panel" > /tmp/bh_kod.txt
ol "Korumalı sayfa 302 (girişe yönlendirir)" "302" "$(cat /tmp/bh_kod.txt)"

echo "=== 9) SÜRESİ GEÇMİŞ ÇEREZ → GİRİŞ YOK ==="
rm -f $J
girisYap admin 1
C2=$(cerezDeger)
H2=$(printf '%s' "$C2" | sha256sum | cut -d' ' -f1)
$MDBR -e "UPDATE hatirlanan_oturumlar SET son_kullanma='2020-01-01 00:00:00'"
rm -f $J
curl -s -o /dev/null -w "%{http_code}" -H "Cookie: bt_hatirla=$C2" "$B/panel" > /tmp/bh_kod.txt
ol "Süresi geçmiş çerezle 302" "302" "$(cat /tmp/bh_kod.txt)"
ol "Süresi geçmiş kayıt DB'den temizlendi" "0" "$($MDB -e "SELECT COUNT(*) FROM hatirlanan_oturumlar WHERE token_hash='$H2'")"

echo ""; echo "======"
[ $k -eq 0 ] && echo "BASARILI ($g/$((g+k)))" || echo "$k HATA ($g/$((g+k)))"
