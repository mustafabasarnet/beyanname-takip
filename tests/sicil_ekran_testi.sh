#!/bin/bash
# =====================================================================
#  SİCİL EKRANI + "TABLO VAR MI" KONTROLLERİ — REGRESYON TESTİ
#
#  Canlıda yaşandı: Sicil İşlemleri ekranı, tabloları ve kayıtları SAĞLAM
#  olan kurulumda "modülün tabloları veritabanında yok" uyarısı gösterdi;
#  kullanıcı verisini kaybetti sandı.
#
#  Neden: Sicil::index() içindeki koruma `sicil_gorevleri` (var olmayan ad)
#  arıyordu; gerçek tablo `sicil_bildirim_gorevleri`.
#
#  Bu test iki şeyi doğrular:
#    1. Kodda tabloVar()/kolonVar() ile sorulan HER tablo adı veritabanında
#       gerçekten var (yazım hatası bir daha canlıya çıkmasın)
#    2. Sicil ekranı 200 döner, yanlış uyarı basmaz; tablo varsa kayıtları
#       gösterir
#
#  Kullanım: bash tests/sicil_ekran_testi.sh
# =====================================================================
B=http://127.0.0.1:8099
KOK=$(cd "$(dirname "$0")/.." && pwd)
J=/tmp/se_a.txt
g=0; k=0
ol(){ if [ "$2" = "$3" ]; then echo "  [OK] $1"; g=$((g+1)); else echo "  [HATA] $1 (bekl:$2 ger:$3)"; k=$((k+1)); fi; }
db(){ mysql -h127.0.0.1 -ubeyanname -pTest1234 --default-character-set=utf8mb4 beyanname_takip -N -B -e "$1" 2>/dev/null; }

girisYap(){
  rm -f $J
  local T
  T=$(curl -s -c $J "$B/giris" | grep -oE 'name="csrf_beyanname" value="[^"]+"' | sed 's/.*value="//;s/"//')
  curl -s -b $J -c $J -o /dev/null -d "csrf_beyanname=$T&kimlik=admin&sifre=Test1234" "$B/giris"
}
girisYap

# ---------------------------------------------------------------------
echo "=== 1) KODDAKİ TABLO ADLARI GERÇEK Mİ? ==="
# app/ altında tabloVar('...') / tabloVar([...]) ve kolonVar('tablo', ...) içindeki adlar
ADLAR=$(grep -rhoE "tabloVar\(\s*\[?[^)]*" "$KOK/app" \
        | grep -oE "'[a-z_0-9]+'" | tr -d "'" \
        | grep -vE "^(bilanco|isletme|tuzel|gercek)$" | sort -u)

for t in $ADLAR; do
  V=$(db "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$t';")
  ol "tabloVar adı geçerli: $t" "1" "${V:-0}"
done

KOLONLAR=$(grep -rhoE "kolonVar\('[a-z_0-9]+', *'[a-z_0-9]+'\)" "$KOK/app" | sed "s/kolonVar('//; s/' *, *'/./; s/')//" | sort -u)
for kk in $KOLONLAR; do
  T=${kk%%.*}; C=${kk##*.}
  V=$(db "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='$T' AND column_name='$C';")
  # Kolon yoksa kod zaten dayanıklı; burada yalnız tablo adı yanlış olmasın
  if [ "$V" = "1" ]; then
    ol "kolonVar: $T.$C (kolon var)" "1" "1"
  else
    TT=$(db "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$T';")
    ol "kolonVar: $T tablosu var (kolon eksikse kod dayanıklı)" "1" "${TT:-0}"
  fi
done

# ---------------------------------------------------------------------
echo; echo "=== 2) SİCİL EKRANI ==="
KOD=$(curl -s -b $J -o /tmp/se.html -w '%{http_code}' "$B/sicil")
ol "/sicil açılıyor" "200" "$KOD"
ol "yanlış 'tablolar yok' uyarısı YOK" "0" "$(grep -c 'modülünün tabloları veritabanında yok' /tmp/se.html)"

if [ "$(db "SELECT COUNT(*) FROM sicil_degisiklikleri WHERE deleted_at IS NULL;")" -gt 0 ]; then
  ol "kayıtlar listeleniyor (satır var)" "1" "$([ "$(grep -c '<tr' /tmp/se.html)" -gt 0 ] && echo 1 || echo 0)"
  ol "özet kartı 'Toplam' basılıyor" "1" "$(grep -c 'Toplam' /tmp/se.html | awk '{print ($1>0)?1:0}')"
fi

# Sicil alt uçları da açılmalı
for u in "sicil?durum=TAMAM" "sicil?yil=2026"; do
  ol "GET /$u" "200" "$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/$u")"
done

# ---------------------------------------------------------------------
echo; echo "=== 3) MIGRATION GERÇEKTEN YOKSA UYARI ÇIKAR (ters yön) ==="
# Tablo adı geçici olarak yeniden adlandırılır; uyarı görünmeli, 500 olmamalı
db "ALTER TABLE sicil_bildirim_gorevleri RENAME TO sicil_bildirim_gorevleri_yedek;" >/dev/null
KOD=$(curl -s -b $J -o /tmp/se2.html -w '%{http_code}' "$B/sicil")
UYARI=$(grep -c 'modülünün tabloları veritabanında yok' /tmp/se2.html)
db "ALTER TABLE sicil_bildirim_gorevleri_yedek RENAME TO sicil_bildirim_gorevleri;" >/dev/null
ol "tablo gerçekten yokken 500 değil 200" "200" "$KOD"
ol "  uyarı bandı çıkıyor" "1" "$UYARI"
KOD=$(curl -s -b $J -o /dev/null -w '%{http_code}' "$B/sicil")
ol "tablo geri gelince ekran yine normal (200)" "200" "$KOD"

echo
echo "======================================================"
echo "  GEÇEN: $g    KALAN: $k    TOPLAM: $((g+k))"
echo "======================================================"
[ "$k" -eq 0 ] || exit 1
