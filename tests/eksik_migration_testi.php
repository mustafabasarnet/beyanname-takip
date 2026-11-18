<?php

/**
 * EKSİK MIGRATION DAYANIKLILIĞI — BİRİM TESTİ
 *
 * Kullanıcı kurulumu güncellerken bazı migration'lar çalışmamış olabilir
 * (phpMyAdmin #1044, izin vb.). Bu durumda ekranların Whoops/500 vermemesi,
 * eksik bölümü boş göstermesi gerekir.
 *
 * Burada HTTP'ye çıkmadan, doğrudan model/yardımcı katmanı sınanır:
 *   1. tabloVar() doğru cevap veriyor (var / yok / dizi)
 *   2. Ücret tablosu yokken makbuz çizelgesi tutar=0 ile kurulur
 *   3. Müşavir özeti (dönem özelliği kaldırıldı) bozulmuyor
 *   4. Gelir vergisi ücret özeti boş (0) dönüyor
 *
 *   php tests/eksik_migration_testi.php
 */

$kok = dirname(__DIR__);

require $kok . '/vendor/autoload.php';

$gecen = 0;
$kalan = 0;

function esit($beklenen, $bulunan, string $baslik): void
{
    global $gecen, $kalan;

    $b = $beklenen === $bulunan;

    if ($b) {
        $gecen++;
        echo "  ✓ {$baslik}\n";
    } else {
        $kalan++;
        echo "  ✗ {$baslik} — bekl: " . var_export($beklenen, true)
            . ' ger: ' . var_export($bulunan, true) . "\n";
    }
}

// CodeIgniter'ı CLI'da ayağa kaldır (DB bağlantısı için)
require $kok . '/app/Config/Paths.php';

$paths = new Config\Paths();

if (! defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

if (! defined('FCPATH')) {
    define('FCPATH', $kok . '/public/');
}

CodeIgniter\Boot::bootConsole($paths);

helper('beyanname');

echo "\n=== 1) tabloVar() ===\n";

esit(true, tabloVar('mukellefler'), 'var olan tablo → true');
esit(false, tabloVar('olmayan_tablo_xyz'), 'olmayan tablo → false');
esit(false, tabloVar(['mukellefler', 'olmayan_tablo_xyz']), 'dizide biri yoksa → false');
esit(true, tabloVar(['mukellefler', 'musavirler']), 'dizide hepsi varsa → true');

echo "\n=== 2) Makbuz: ücret tablosu yokken çizelge ===\n";

$makbuz = new App\Models\MakbuzModel();
$yil    = (int) date('Y');

$cizelge = $makbuz->cizelge(['yil' => $yil, 'limit' => 5, 'ofset' => 0]);
esit(true, is_array($cizelge), 'çizelge() dizi dönüyor');

$satirlar = $cizelge['satirlar'] ?? $cizelge['mukellefler'] ?? [];
$sifirOk  = true;

foreach ($satirlar as $s) {
    if (isset($s['ucret']) && (float) $s['ucret'] !== 0.0) {
        $sifirOk = false;
    }
}

esit(true, $sifirOk, 'ucret alanı 0 (ücret tablosu yokken patlamıyor)');

echo "\n=== 3) Makbuz: müşavir özeti ===\n";

$ozet = $makbuz->musavirOzeti($yil);
esit(true, is_array($ozet), 'musavirOzeti() dizi dönüyor');

$calisti = true;

try {
    $makbuz->musavirOzeti($yil, 1, true);
} catch (Throwable $e) {
    $calisti = false;
    echo '     hata: ' . $e->getMessage() . "\n";
}

esit(true, $calisti, 'pasif dahil çağrı istisna atmıyor');

echo "\n=== 4) Gelir vergisi: ücret özeti ===\n";

$gv   = new App\Models\GelirVergisiModel();
$ozet = $gv->ucretToplami(1, $yil);

// Ücret tablosu ve mükellefler varsa dolu, yoksa 0 döner; önemli olan patlamaması.
esit(true, is_int($ozet['adet']), 'adet sayı');
esit(true, is_float($ozet['brut']), 'brut sayı');
// Tablolar varsa gerçek oran (20/18), yoksa 0 döner; önemli olan anahtar.
esit(true, array_key_exists('stopaj_oran', $ozet), 'stopaj_oran anahtarı var');
esit(true, array_key_exists('kdv_oran', $ozet), 'kdv_oran anahtarı var');
esit(true, is_array($ozet['mukellefler']), 'mukellefler dizisi var');

echo "\n======================================================\n";
echo "  GEÇEN: {$gecen}    KALAN: {$kalan}    TOPLAM: " . ($gecen + $kalan) . "\n";
echo "======================================================\n";

exit($kalan === 0 ? 0 : 1);
