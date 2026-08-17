<?php

/**
 * =====================================================================
 *  GENÇ GİRİŞİMCİ İSTİSNASI TESTİ  —  php tests/genc_girisimci_testi.php
 * =====================================================================
 *  GVK mükerrer 20: istisna, faaliyete başlanan takvim yılından itibaren
 *  3 vergilendirme dönemi geçerlidir.
 * =====================================================================
 */

$gecen = 0;
$kalan = 0;

function baslik(string $s): void
{
    echo "\n\033[1;36m" . str_repeat('=', 70) . "\n  {$s}\n" . str_repeat('=', 70) . "\033[0m\n";
}

function kontrol(string $ad, $bek, $ger): void
{
    global $gecen, $kalan;
    $ok = $bek === $ger;
    $ok ? $gecen++ : $kalan++;
    echo ($ok ? "\033[0;32m  ✓\033[0m " : "\033[0;31m  ✗\033[0m ") . str_pad($ad, 50);
    echo $ok ? "\033[0;90m" . var_export($ger, true) . "\033[0m\n"
        : "\n      \033[0;31mBeklenen: " . var_export($bek, true) . " | Gerçek: " . var_export($ger, true) . "\033[0m\n";
}

/**
 * Helper'daki gencGirisimciDurum() mantığının birebir kopyası.
 * (Veritabanı gerektirmez.)
 */
function ggDurum(array $m, ?int $yil = null, int $toplamDonem = 3): array
{
    $bos = ['var' => false, 'gecerli' => false, 'donem' => null, 'toplam' => $toplamDonem,
        'baslangic' => null, 'bitis' => null, 'sinif' => 'gri'];

    if (empty($m['genc_girisimci'])) {
        return $bos;
    }

    $baslangic = ! empty($m['gg_baslangic_yili'])
        ? (int) $m['gg_baslangic_yili']
        : (! empty($m['ise_baslama_tarihi']) ? (int) date('Y', strtotime($m['ise_baslama_tarihi'])) : null);

    if ($baslangic === null) {
        return array_merge($bos, ['var' => true, 'gecerli' => true, 'sinif' => 'yesil']);
    }

    $yil   = $yil ?? (int) date('Y');
    $bitis = $baslangic + $toplamDonem - 1;
    $donem = $yil - $baslangic + 1;

    if ($donem < 1) {
        return array_merge($bos, ['var' => true, 'gecerli' => false,
            'baslangic' => $baslangic, 'bitis' => $bitis, 'sinif' => 'gri']);
    }

    if ($donem > $toplamDonem) {
        return array_merge($bos, ['var' => true, 'gecerli' => false, 'donem' => $donem,
            'baslangic' => $baslangic, 'bitis' => $bitis, 'sinif' => 'kirmizi']);
    }

    return ['var' => true, 'gecerli' => true, 'donem' => $donem, 'toplam' => $toplamDonem,
        'baslangic' => $baslangic, 'bitis' => $bitis,
        'sinif' => $donem === $toplamDonem ? 'turuncu' : 'yesil'];
}

// =====================================================================
baslik('İSTİSNA YOK');
$normal = ['genc_girisimci' => 0, 'ise_baslama_tarihi' => '2024-01-01'];
kontrol('İşaretsiz mükellefte istisna yok', false, ggDurum($normal, 2026)['var']);

// =====================================================================
baslik('3 DÖNEMLİK GEÇERLİLİK (başlangıç 2024)');
$gg = ['genc_girisimci' => 1, 'gg_baslangic_yili' => 2024, 'ise_baslama_tarihi' => '2024-03-15'];

foreach ([2024 => 1, 2025 => 2, 2026 => 3] as $y => $beklenenDonem) {
    $d = ggDurum($gg, $y);
    kontrol("{$y}: geçerli", true, $d['gecerli']);
    kontrol("{$y}: {$beklenenDonem}. dönem", $beklenenDonem, $d['donem']);
}

kontrol('Son dönem (2026) turuncu uyarı', 'turuncu', ggDurum($gg, 2026)['sinif']);
kontrol('1. dönem (2024) yeşil', 'yesil', ggDurum($gg, 2024)['sinif']);
kontrol('Bitiş yılı 2026', 2026, ggDurum($gg, 2024)['bitis']);

// =====================================================================
baslik('SÜRE DOLMASI');
$d2027 = ggDurum($gg, 2027);
kontrol('★ 2027: istisna GEÇERSİZ', false, $d2027['gecerli']);
kontrol('  işaret hâlâ var (bilgi amaçlı)', true, $d2027['var']);
kontrol('  kırmızı uyarı', 'kirmizi', $d2027['sinif']);
kontrol('  4. dönem olarak hesaplandı', 4, $d2027['donem']);
kontrol('2030: hâlâ geçersiz', false, ggDurum($gg, 2030)['gecerli']);

// =====================================================================
baslik('BAŞLANGIÇ YILI BOŞ → İŞE BAŞLAMA YILI');
$gg2 = ['genc_girisimci' => 1, 'gg_baslangic_yili' => null, 'ise_baslama_tarihi' => '2025-07-01'];
kontrol('Başlangıç işe başlamadan alındı', 2025, ggDurum($gg2, 2025)['baslangic']);
kontrol('2025: 1. dönem', 1, ggDurum($gg2, 2025)['donem']);
kontrol('2027: 3. dönem (son)', 3, ggDurum($gg2, 2027)['donem']);
kontrol('2028: süre doldu', false, ggDurum($gg2, 2028)['gecerli']);

// =====================================================================
baslik('GELECEKTE BAŞLAYAN');
$gg3 = ['genc_girisimci' => 1, 'gg_baslangic_yili' => 2027, 'ise_baslama_tarihi' => '2027-01-01'];
kontrol('2026: henüz başlamadı', false, ggDurum($gg3, 2026)['gecerli']);
kontrol('2027: başladı', true, ggDurum($gg3, 2027)['gecerli']);

// =====================================================================
baslik('AYARDAN SÜRE DEĞİŞİMİ (mevzuat değişirse)');
kontrol('5 dönemlik istisna: 2028 geçerli', true, ggDurum($gg, 2028, 5)['gecerli']);
kontrol('5 dönemlik istisna: 2029 geçersiz', false, ggDurum($gg, 2029, 5)['gecerli']);

// =====================================================================
$t = $gecen + $kalan;
echo "\n" . str_repeat('=', 70) . "\n";
echo $kalan === 0
    ? "\033[1;32m  ✓ TÜM TESTLER BAŞARILI  ({$gecen}/{$t})\033[0m\n"
    : "\033[1;31m  ✗ {$kalan} TEST BAŞARISIZ  ({$gecen}/{$t})\033[0m\n";
echo str_repeat('=', 70) . "\n";

exit($kalan === 0 ? 0 : 1);
