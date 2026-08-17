<?php

/**
 * =====================================================================
 *  FİLTRE MANTIĞI TESTİ  —  php tests/filtre_testi.php
 * =====================================================================
 *  Beyan dönemi / ait olduğu dönem ayrımını doğrular.
 *
 *  Kural:
 *    beyan modu : Yıl + Ay -> son_tarih  ("bu ay neyi vereceğim")
 *    donem modu : Yıl -> yil, Ay -> donem_bitis  ("hangi döneme ait")
 *
 *  Kritik senaryo:
 *    Kurumlar 2026 dönemi -> son tarih 30.04.2027
 *      beyan modu: Nisan 2027 listesinde ÇIKAR
 *      donem modu: 2026 listesinde çıkar, 2027'de çıkmaz
 * =====================================================================
 */

$gecen = 0;
$kalan = 0;

function baslik(string $s): void
{
    echo "\n\033[1;36m" . str_repeat('=', 70) . "\n  {$s}\n" . str_repeat('=', 70) . "\033[0m\n";
}

function kontrol(string $ad, $beklenen, $gercek): void
{
    global $gecen, $kalan;
    $ok = $beklenen === $gercek;
    $ok ? $gecen++ : $kalan++;

    echo ($ok ? "\033[0;32m  ✓\033[0m " : "\033[0;31m  ✗\033[0m ") . str_pad($ad, 52);
    echo $ok
        ? "\033[0;90m" . var_export($gercek, true) . "\033[0m\n"
        : "\n      \033[0;31mBeklenen: " . var_export($beklenen, true) . " | Gerçek: " . var_export($gercek, true) . "\033[0m\n";
}

/**
 * cizelge() içindeki filtre mantığının birebir kopyası.
 * (SQL yerine PHP'de simüle edilir; veritabanı gerektirmez.)
 */
function filtrele(array $kayitlar, array $f): array
{
    $mod = ($f['tarih_modu'] ?? 'beyan') === 'donem' ? 'donem' : 'beyan';

    return array_values(array_filter($kayitlar, static function ($k) use ($f, $mod) {
        if ($mod === 'donem') {
            if (! empty($f['yil']) && (int) $k['yil'] !== (int) $f['yil']) {
                return false;
            }
            if (! empty($f['ay']) && (int) date('n', strtotime($k['donem_bitis'])) !== (int) $f['ay']) {
                return false;
            }
        } else {
            if (! empty($f['yil']) && (int) date('Y', strtotime($k['son_tarih'])) !== (int) $f['yil']) {
                return false;
            }
            if (! empty($f['ay']) && (int) date('n', strtotime($k['son_tarih'])) !== (int) $f['ay']) {
                return false;
            }
        }

        return true;
    }));
}

// --------------------------------------------------------------------
// Örnek veri (gerçek üretim sonuçlarıyla aynı)
// --------------------------------------------------------------------
$kayitlar = [
    ['kod' => 'KDV1_A',       'yil' => 2027, 'donem_bitis' => '2027-03-31', 'son_tarih' => '2027-04-28'],
    ['kod' => 'MUHSGK_A',     'yil' => 2027, 'donem_bitis' => '2027-03-31', 'son_tarih' => '2027-04-26'],
    ['kod' => 'SGK',          'yil' => 2027, 'donem_bitis' => '2027-03-31', 'son_tarih' => '2027-04-30'],
    ['kod' => 'KURUMLAR',     'yil' => 2026, 'donem_bitis' => '2026-12-31', 'son_tarih' => '2027-04-30'],
    ['kod' => 'YILLIK_GV',    'yil' => 2026, 'donem_bitis' => '2026-12-31', 'son_tarih' => '2027-03-31'],
    ['kod' => 'KURUMLAR',     'yil' => 2027, 'donem_bitis' => '2027-12-31', 'son_tarih' => '2028-05-01'],
    ['kod' => 'KURUM_GECICI', 'yil' => 2027, 'donem_bitis' => '2027-03-31', 'son_tarih' => '2027-05-20'],
    ['kod' => 'KDV1_A',       'yil' => 2027, 'donem_bitis' => '2027-04-30', 'son_tarih' => '2027-05-28'],
];

$kod = static fn (array $r) => array_map(static fn ($x) => $x['kod'] . '/' . $x['yil'], $r);

// ====================================================================
baslik('BEYAN MODU — "Bu ay hangi beyannameleri vereceğim?"');
// ====================================================================

$nisan = filtrele($kayitlar, ['yil' => 2027, 'ay' => 4, 'tarih_modu' => 'beyan']);
kontrol('Nisan 2027 kayıt sayısı', 4, count($nisan));
kontrol('★ Kurumlar 2026 dönemi VAR', true, in_array('KURUMLAR/2026', $kod($nisan), true));
kontrol('  Mart 2027 KDV1 var', true, in_array('KDV1_A/2027', $kod($nisan), true));
kontrol('  2028 tarihli Kurumlar YOK', false, in_array('KURUMLAR/2027', $kod($nisan), true));

$mart = filtrele($kayitlar, ['yil' => 2027, 'ay' => 3, 'tarih_modu' => 'beyan']);
kontrol('★ Mart 2027 -> Yıllık GV 2026 VAR', true, in_array('YILLIK_GV/2026', $kod($mart), true));
kontrol('  Mart 2027 kayıt sayısı', 1, count($mart));

$mayis = filtrele($kayitlar, ['yil' => 2027, 'ay' => 5, 'tarih_modu' => 'beyan']);
kontrol('Mayıs 2027 kayıt sayısı', 2, count($mayis));
kontrol('★ 01.05.2028 tarihli kayıt ELENDİ', false, in_array('KURUMLAR/2027', $kod($mayis), true));

$mayis28 = filtrele($kayitlar, ['yil' => 2028, 'ay' => 5, 'tarih_modu' => 'beyan']);
kontrol('Mayıs 2028 -> Kurumlar 2027 VAR', true, in_array('KURUMLAR/2027', $kod($mayis28), true));

// ====================================================================
baslik('DÖNEM MODU — "Hangi döneme ait?"');
// ====================================================================

$d2026 = filtrele($kayitlar, ['yil' => 2026, 'tarih_modu' => 'donem']);
kontrol('2026 dönemi kayıt sayısı', 2, count($d2026));
kontrol('  Kurumlar 2026 VAR', true, in_array('KURUMLAR/2026', $kod($d2026), true));
kontrol('  Yıllık GV 2026 VAR', true, in_array('YILLIK_GV/2026', $kod($d2026), true));

$d2027 = filtrele($kayitlar, ['yil' => 2027, 'tarih_modu' => 'donem']);
kontrol('2027 dönemi: Kurumlar 2027 VAR', true, in_array('KURUMLAR/2027', $kod($d2027), true));
kontrol('2027 dönemi: Kurumlar 2026 YOK', false, in_array('KURUMLAR/2026', $kod($d2027), true));

$dMart = filtrele($kayitlar, ['yil' => 2027, 'ay' => 3, 'tarih_modu' => 'donem']);
kontrol('2027 Mart dönemi (aylık+3aylık)', 4, count($dMart));

// ====================================================================
baslik('İKİ MOD FARKLI SONUÇ VERMELİ');
// ====================================================================

$b = $kod(filtrele($kayitlar, ['yil' => 2027, 'ay' => 4, 'tarih_modu' => 'beyan']));
$d = $kod(filtrele($kayitlar, ['yil' => 2027, 'ay' => 4, 'tarih_modu' => 'donem']));
kontrol('Nisan 2027: beyan ≠ dönem sonucu', true, $b !== $d);
kontrol('  beyan modunda Kurumlar/2026 var', true, in_array('KURUMLAR/2026', $b, true));
kontrol('  dönem modunda Kurumlar/2026 yok', false, in_array('KURUMLAR/2026', $d, true));

// ====================================================================
$t = $gecen + $kalan;
echo "\n" . str_repeat('=', 70) . "\n";
echo $kalan === 0
    ? "\033[1;32m  ✓ TÜM FİLTRE TESTLERİ BAŞARILI  ({$gecen}/{$t})\033[0m\n"
    : "\033[1;31m  ✗ {$kalan} TEST BAŞARISIZ  ({$gecen}/{$t})\033[0m\n";
echo str_repeat('=', 70) . "\n";

exit($kalan === 0 ? 0 : 1);
