<?php
/**
 * YAPIŞKAN NOT RENK KONTRASTI (WCAG 2.1 — AA)
 * =====================================================================
 * Her kâğıt rengi için, kart üzerindeki yazının ve üst şeridin (bant)
 * okunurluğunu ölçer. Eşik: 4.5:1 (normal metin).
 *
 * Sınanan çiftler:
 *   1) Kart yazısı (#1f2937)          ↔ kâğıt rengi
 *   2) Sabit etiketi / ikonlar         ↔ üst şerit (bant)
 *   3) Alt satır (sayaç, %70 opaklık)  ↔ kâğıt rengi (karışımdan hesaplanır)
 *
 * Değerler StickyNotModel.php kaynağından okunur (tek doğru kaynak).
 * Kullanım: php tests/sticky_kontrast_testi.php
 * =====================================================================
 */

$kok = dirname(__DIR__);
$kaynak = file_get_contents($kok . '/app/Models/StickyNotModel.php');

// RENKLER dizisini ve YAZI sabitini kaynaktan çıkar
preg_match_all("/'([a-z]+)'\s*=>\s*\['ad'\s*=>\s*'[^']*',\s*'kart'\s*=>\s*'(#[0-9a-fA-F]{6})',\s*'bant'\s*=>\s*'(#[0-9a-fA-F]{6})'\]/",
    $kaynak, $m, PREG_SET_ORDER);
preg_match("/YAZI\s*=\s*'(#[0-9a-fA-F]{6})'/", $kaynak, $y);

function parlaklik(string $hex): float
{
    $h = ltrim($hex, '#');
    $kanal = static function (float $c): float {
        $c /= 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * $kanal(hexdec(substr($h, 0, 2)))
         + 0.7152 * $kanal(hexdec(substr($h, 2, 2)))
         + 0.0722 * $kanal(hexdec(substr($h, 4, 2)));
}

function kontrast(string $a, string $b): float
{
    $l1 = parlaklik($a);
    $l2 = parlaklik($b);

    return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
}

/** Yarı saydam metni zemin üzerine karıştırır (alpha: 0..1). */
function karistir(string $on, string $zemin, float $alfa): string
{
    $o = array_map(fn ($i) => hexdec(substr(ltrim($on, '#'), $i, 2)), [0, 2, 4]);
    $z = array_map(fn ($i) => hexdec(substr(ltrim($zemin, '#'), $i, 2)), [0, 2, 4]);
    $r = '';

    foreach ([0, 1, 2] as $i) {
        $r .= str_pad(dechex((int) round($o[$i] * $alfa + $z[$i] * (1 - $alfa))), 2, '0', STR_PAD_LEFT);
    }

    return '#' . $r;
}

$g = 0;
$k = 0;

function ol(string $ad, float $oran, float $esik = 4.5): void
{
    global $g, $k;

    if ($oran >= $esik) {
        echo "  [OK] $ad → {$oran}:1\n";
        $g++;
    } else {
        echo "  [HATA] $ad → {$oran}:1 (eşik $esik)\n";
        $k++;
    }
}

$yazi = $y[1] ?? '#1f2937';

// Alt satır opaklığı görünüm dosyasından okunur (CSS ile birebir aynı olmalı)
$gorunum = file_get_contents($kok . '/app/Views/kisisel/yapiskan.php');
preg_match('/\.yk-alt\{[^}]*rgba\(31,41,55,\s*([0-9.]+)\)/', $gorunum, $op);
$alt_opaklik = isset($op[1]) ? (float) $op[1] : 0.7;
echo '  ' . (isset($op[1]) ? '[OK]' : '[HATA]') . " alt satır opaklığı kaynaktan okundu ($alt_opaklik)\n";
isset($op[1]) ? $g++ : $k++;

echo "=== 0) KAYNAK OKUMA ===\n";
echo '  ' . (count($m) === 6 ? '[OK]' : '[HATA]') . ' 6 renk tanımı bulundu (' . count($m) . ")\n";
echo '  ' . (isset($y[1]) ? '[OK]' : '[HATA]') . " yazı rengi tanımlı ($yazi)\n";
if (count($m) === 6) {
    $g++;
} else {
    $k++;
}

echo "\n=== 1) KART YAZISI ↔ KÂĞIT RENGİ ===\n";
foreach ($m as $r) {
    ol(sprintf('%-8s kart %s', $r[1], $r[2]), kontrast($yazi, $r[2]));
}

echo "\n=== 2) SABİT ETİKETİ / İKON ↔ ÜST ŞERİT ===\n";
foreach ($m as $r) {
    ol(sprintf('%-8s bant %s', $r[1], $r[3]), kontrast($yazi, $r[3]));
}

echo "\n=== 3) ALT SATIR (%90 opaklık) ↔ KÂĞIT ===\n";
foreach ($m as $r) {
    $karisik = karistir($yazi, $r[2], $alt_opaklik);
    ol(sprintf('%-8s alt satır', $r[1]), kontrast($karisik, $r[2]));
}

echo "\n=== 3b) HATIRLATMA ROZETLERİ (Faz 2) — görünüm dosyasından ===\n";
$gorunumCss = file_get_contents($kok . '/app/Views/kisisel/yapiskan.php');
$rozetler = ['gecmis' => '#ffffff', 'bugun' => '#ffffff', 'yakin' => '#78350f'];
foreach ($rozetler as $ad => $beklenenYazi) {
    if (preg_match('/\\.yk-hat-' . $ad . '\\{background:(#[0-9a-fA-F]{3,6});color:(#[0-9a-fA-F]{3,6})/', $gorunumCss, $r)) {
        $zemin = strlen($r[1]) === 4 ? '#' . str_repeat($r[1][1], 2) . str_repeat($r[1][2], 2) . str_repeat($r[1][3], 2) : $r[1];
        $yazi  = strlen($r[2]) === 4 ? '#' . str_repeat($r[2][1], 2) . str_repeat($r[2][2], 2) . str_repeat($r[2][3], 2) : $r[2];
        ol(sprintf('rozet %-7s %s üzerine %s', $ad, $zemin, $yazi), kontrast($yazi, $zemin));
    } else {
        echo "  [HATA] rozet $ad CSS'te bulunamadı\n";
        $k++;
    }
}

echo "\n=== 4) ALGI — 6 RENK ARASI AYIRT EDİLEBİLİRLİK ===\n";
$kartlar = array_column($m, 2);
$ayni = count($kartlar) - count(array_unique($kartlar));
echo '  ' . ($ayni === 0 ? '[OK]' : '[HATA]') . " her renk farklı ($ayni çakışma)\n";
$ayni === 0 ? $g++ : $k++;

echo "\n======================================================\n";
echo $k === 0
    ? "TÜM TESTLER BAŞARILI ($g geçti)\n"
    : "SONUÇ: $g geçti, $k hata\n";
echo "======================================================\n";

exit($k === 0 ? 0 : 1);
