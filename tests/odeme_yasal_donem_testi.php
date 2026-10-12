<?php
/**
 * ÖDEME LİSTESİ — KANUNİ ÖDEME TARİHİ ÜRETİMİ (dönem üretici)
 * =====================================================================
 * SGK (ödeme: ay sonu, ofset 1) için her dönemde:
 *   1) odeme_yasal_tarih = ayın son günü (kaydırılmamış)
 *   2) odeme_son_tarih   = hafta sonu/tatil kaydırmalı gerçek son gün
 *   3) Ay ataması kanuni tarihe göre yapılır (Ekim'e düşen SGK → yasal ay Ekim)
 * Kullanım: php tests/odeme_yasal_donem_testi.php
 * =====================================================================
 */
$kok = dirname(__DIR__);
require $kok . '/vendor/autoload.php';
require $kok . '/app/Config/Paths.php';

$paths = new Config\Paths();
$paths->appDirectory      = $kok . '/app';
$paths->systemDirectory   = $kok . '/vendor/codeigniter4/framework/system';
$paths->writableDirectory = $kok . '/writable';
$paths->testsDirectory    = $kok . '/tests';
$paths->viewDirectory     = $kok . '/app/Views';

if (! defined('ENVIRONMENT')) { define('ENVIRONMENT', 'development'); }
if (! defined('FCPATH'))      { define('FCPATH', $kok . '/public/'); }

\CodeIgniter\Boot::bootConsole($paths);

$db = \Config\Database::connect();
$ok = 0; $fail = 0;

function t(string $ad, bool $sart, string $detay = ''): void
{
    global $ok, $fail;
    if ($sart) { $ok++; echo "  [OK] $ad\n"; }
    else       { $fail++; echo "  [HATA] $ad  -- $detay\n"; }
}

echo "=== SGK DÖNEM ÜRETİMİ: KANUNİ ÖDEME TARİHİ ===\n";

$tur = $db->table('beyanname_turleri')->where('kod', 'SGK')->get()->getRowArray();
$m   = $db->table('mukellefler')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();

t('SGK türü ve mükellef bulundu', $tur !== null && $m !== null);

if ($tur !== null && $m !== null) {
    $satirlar = (new App\Libraries\DonemUretici())->uret($m, [$tur], 2026);
    $harita   = [];
    foreach ($satirlar as $s) { $harita[$s['donem_adi']] = $s; }

    $eylul = $harita['Eylül 2026'] ?? null;
    $nisan = $harita['Nisan 2026'] ?? null;
    $ocak  = $harita['Ocak 2026'] ?? null;

    t('Eylül 2026 dönemi üretildi', $eylul !== null);

    if ($eylul !== null) {
        t('Eylül: kanuni ödeme = 31.10.2026 (kaydırılmamış)', $eylul['odeme_yasal_tarih'] === '2026-10-31',
          'bulunan: ' . var_export($eylul['odeme_yasal_tarih'], true));
        t('Eylül: gerçek ödeme son günü = 02.11.2026 (kaydırılmış)', $eylul['odeme_son_tarih'] === '2026-11-02',
          'bulunan: ' . var_export($eylul['odeme_son_tarih'], true));
        t('Eylül: beyan yasal son tarihi = 26.10.2026', $eylul['yasal_son_tarih'] === '2026-10-26');
        t('Eylül: ay ataması kanuni tarihe göre EKİM', substr($eylul['odeme_yasal_tarih'], 5, 2) === '10');
    }

    if ($nisan !== null) {
        t('Nisan: kanuni ödeme 31.05.2026 (Pazar → 1 Haziran ama ay MAYIS)',
          $nisan['odeme_yasal_tarih'] === '2026-05-31', var_export($nisan['odeme_yasal_tarih'], true));
        t('Nisan: gerçek ödeme 01.06.2026', $nisan['odeme_son_tarih'] === '2026-06-01');
    }

    if ($ocak !== null) {
        t('Ocak: kanuni ödeme 28.02.2026 (Cumartesi → 2 Mart ama ay ŞUBAT)',
          $ocak['odeme_yasal_tarih'] === '2026-02-28', var_export($ocak['odeme_yasal_tarih'], true));
    }

    // Beyanname (ödemesiz tür) satırında kanuni ödeme tarihi BOŞ olmalı
    $muhsgk = $db->table('beyanname_turleri')->where('kod', 'MUHSGK_A')->get()->getRowArray();
    if ($muhsgk !== null) {
        $bs = (new App\Libraries\DonemUretici())->uret($m, [$muhsgk], 2026);
        t('Ödemesiz tür (MUHSGK): odeme_yasal_tarih = null',
          $bs !== [] && $bs[0]['odeme_yasal_tarih'] === null);
    }
}

echo "\n======================================================\n";
echo $fail === 0 ? "TÜM TESTLER BAŞARILI ($ok geçti)\n" : "SONUÇ: $ok geçti, $fail hata\n";
echo "======================================================\n";
exit($fail === 0 ? 0 : 1);
