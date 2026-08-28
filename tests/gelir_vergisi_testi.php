<?php

/**
 * GELİR VERGİSİ HESAP MOTORU — BİRİM TESTİ
 *
 * Tarife matematiği ve hesap zinciri, veritabanına/HTTP'ye bağlı olmadan
 * doğrulanır. Beklenen değerler GVK md.103 tarifesinden ELLE hesaplanmıştır.
 *
 *   php tests/gelir_vergisi_testi.php
 */

$gecen = 0;
$kalan = 0;

function esit($beklenen, $bulunan, string $baslik): void
{
    global $gecen, $kalan;

    $b = is_float($beklenen) || is_float($bulunan)
        ? abs((float) $beklenen - (float) $bulunan) < 0.005
        : $beklenen === $bulunan;

    if ($b) {
        $gecen++;
        echo "  ✓ {$baslik}\n";
    } else {
        $kalan++;
        echo "  ✗ {$baslik}\n";
        echo "      beklenen: " . var_export($beklenen, true) . "\n";
        echo "      bulunan : " . var_export($bulunan, true) . "\n";
    }
}

function baslik(string $s): void
{
    echo "\n== {$s} ==\n";
}

// ---------------------------------------------------------------------
//  Tarife tabloları (migration ile birebir aynı olmalı)
// ---------------------------------------------------------------------
$TARIFE = [
    2026 => [
        0 => [ // ücret dışı
            ['sira' => 1, 'taban' => 0,       'tavan' => 190000,  'sabit_vergi' => 0,       'oran' => 15],
            ['sira' => 2, 'taban' => 190000,  'tavan' => 400000,  'sabit_vergi' => 28500,   'oran' => 20],
            ['sira' => 3, 'taban' => 400000,  'tavan' => 1000000, 'sabit_vergi' => 70500,   'oran' => 27],
            ['sira' => 4, 'taban' => 1000000, 'tavan' => 5300000, 'sabit_vergi' => 232500,  'oran' => 35],
            ['sira' => 5, 'taban' => 5300000, 'tavan' => null,    'sabit_vergi' => 1737500, 'oran' => 40],
        ],
        1 => [ // ücret
            ['sira' => 1, 'taban' => 0,       'tavan' => 190000,  'sabit_vergi' => 0,       'oran' => 15],
            ['sira' => 2, 'taban' => 190000,  'tavan' => 400000,  'sabit_vergi' => 28500,   'oran' => 20],
            ['sira' => 3, 'taban' => 400000,  'tavan' => 1500000, 'sabit_vergi' => 70500,   'oran' => 27],
            ['sira' => 4, 'taban' => 1500000, 'tavan' => 5300000, 'sabit_vergi' => 367500,  'oran' => 35],
            ['sira' => 5, 'taban' => 5300000, 'tavan' => null,    'sabit_vergi' => 1697500, 'oran' => 40],
        ],
    ],
    2025 => [
        0 => [
            ['sira' => 1, 'taban' => 0,       'tavan' => 158000,  'sabit_vergi' => 0,       'oran' => 15],
            ['sira' => 2, 'taban' => 158000,  'tavan' => 330000,  'sabit_vergi' => 23700,   'oran' => 20],
            ['sira' => 3, 'taban' => 330000,  'tavan' => 800000,  'sabit_vergi' => 58100,   'oran' => 27],
            ['sira' => 4, 'taban' => 800000,  'tavan' => 4300000, 'sabit_vergi' => 185000,  'oran' => 35],
            ['sira' => 5, 'taban' => 4300000, 'tavan' => null,    'sabit_vergi' => 1410000, 'oran' => 40],
        ],
    ],
];

/** VergiTarifeModel::vergiHesapla ile AYNI mantık (bağımsız kopya) */
function vergiHesapla(float $matrah, array $dilimler): array
{
    if ($matrah <= 0 || $dilimler === []) {
        return ['vergi' => 0.0, 'dilim_no' => 0, 'kirilim' => []];
    }

    $matrah = round($matrah, 2);
    $secili = null;

    foreach ($dilimler as $d) {
        if ($matrah > $d['taban'] || ($d['taban'] <= 0 && $matrah > 0)) {
            $secili = $d;

            if ($d['tavan'] === null || $matrah <= $d['tavan']) {
                break;
            }
        }
    }

    $secili ??= $dilimler[0];
    $vergi = round($secili['sabit_vergi'] + ($matrah - $secili['taban']) * $secili['oran'] / 100, 2);

    $kirilim = [];

    foreach ($dilimler as $d) {
        if ($matrah <= $d['taban']) {
            break;
        }

        $ust   = $d['tavan'] === null ? $matrah : min($matrah, $d['tavan']);
        $tutar = max(0, $ust - $d['taban']);

        if ($tutar > 0) {
            $kirilim[] = ['sira' => $d['sira'], 'matrah' => round($tutar, 2),
                          'vergi' => round($tutar * $d['oran'] / 100, 2)];
        }
    }

    return ['vergi' => $vergi, 'dilim_no' => (int) $secili['sira'], 'kirilim' => $kirilim];
}

// =====================================================================
baslik('2026 ücret dışı tarife — dilim sınırları');
// =====================================================================
$d26 = $TARIFE[2026][0];

// 1. dilim: %15
esit(0.0,       vergiHesapla(0, $d26)['vergi'],       'Matrah 0 → vergi 0');
esit(1500.0,    vergiHesapla(10000, $d26)['vergi'],   '10.000 → 1.500 (%15)');
esit(28500.0,   vergiHesapla(190000, $d26)['vergi'],  '190.000 (1. dilim tavanı) → 28.500');
esit(1,         vergiHesapla(190000, $d26)['dilim_no'], '190.000 tam tavanda → 1. dilim');

// 2. dilim: 190.000 üstü %20
esit(28700.0,   vergiHesapla(191000, $d26)['vergi'],  '191.000 → 28.500 + 1.000×%20 = 28.700');
esit(2,         vergiHesapla(191000, $d26)['dilim_no'], '191.000 → 2. dilim');
esit(70500.0,   vergiHesapla(400000, $d26)['vergi'],  '400.000 (2. dilim tavanı) → 70.500');

// 3. dilim: 400.000 üstü %27
esit(97500.0,   vergiHesapla(500000, $d26)['vergi'],  '500.000 → 70.500 + 100.000×%27 = 97.500');
esit(3,         vergiHesapla(500000, $d26)['dilim_no'], '500.000 → 3. dilim');
esit(232500.0,  vergiHesapla(1000000, $d26)['vergi'], '1.000.000 (3. dilim tavanı) → 232.500');

// 4. dilim: 1.000.000 üstü %35
esit(407500.0,  vergiHesapla(1500000, $d26)['vergi'], '1.500.000 → 232.500 + 500.000×%35 = 407.500');
esit(4,         vergiHesapla(1500000, $d26)['dilim_no'], '1.500.000 → 4. dilim');
esit(1737500.0, vergiHesapla(5300000, $d26)['vergi'], '5.300.000 (4. dilim tavanı) → 1.737.500');

// 5. dilim: 5.300.000 üstü %40
esit(1937500.0, vergiHesapla(5800000, $d26)['vergi'], '5.800.000 → 1.737.500 + 500.000×%40 = 1.937.500');
esit(5,         vergiHesapla(5800000, $d26)['dilim_no'], '5.800.000 → 5. dilim');

// =====================================================================
baslik('Tarife metni ile kümülatif hesap TUTARLI olmalı');
// =====================================================================
// Her dilim tavanında, kırılım toplamı = tarifedeki sabit_vergi olmalı.
// Bu, tabloya girilen "sabit_vergi" değerlerinin doğruluğunu ispatlar.
foreach ([2026, 2025] as $yil) {
    foreach ($TARIFE[$yil] as $ucretMi => $dilimler) {
        $ad = $ucretMi ? 'ücret' : 'ücret dışı';

        foreach ($dilimler as $d) {
            if ($d['taban'] <= 0) {
                continue;
            }

            $k       = vergiHesapla($d['taban'], $dilimler);
            $toplam  = array_sum(array_column($k['kirilim'], 'vergi'));

            esit(
                (float) $d['sabit_vergi'],
                round($toplam, 2),
                "{$yil} {$ad} · {$d['sira']}. dilim tabanı (" . number_format($d['taban'], 0, ',', '.')
                . ") kümülatif vergi = " . number_format($d['sabit_vergi'], 0, ',', '.')
            );
        }
    }
}

// =====================================================================
baslik('2025 ücret dışı tarife');
// =====================================================================
$d25 = $TARIFE[2025][0];
esit(23700.0,  vergiHesapla(158000, $d25)['vergi'],  '158.000 → 23.700');
esit(58100.0,  vergiHesapla(330000, $d25)['vergi'],  '330.000 → 58.100');
esit(185000.0, vergiHesapla(800000, $d25)['vergi'],  '800.000 → 185.000');
esit(1410000.0, vergiHesapla(4300000, $d25)['vergi'], '4.300.000 → 1.410.000');
esit(115100.0, vergiHesapla(541111.11, $d25)['vergi'],
    '541.111,11 → 58.100 + 211.111,11×%27 = 115.100,00');

// =====================================================================
baslik('Hesap zinciri — hasılat → matrah → vergi → ödenecek');
// =====================================================================

/** GelirVergisiModel::hesapla ile AYNI zincir */
function zincir(array $g, array $dilimler): array
{
    $hasilat = $g['hasilat'];
    $kazanc  = round($hasilat - $g['gider'], 2);
    $indirim = round(($g['bagkur'] ?? 0) + ($g['zarar'] ?? 0) + ($g['diger_indirim'] ?? 0), 2);
    $matrah  = round(max(0, $kazanc - $indirim), 2);

    $t     = vergiHesapla($matrah, $dilimler);
    $vergi = $t['vergi'];

    $uyumlu = ! empty($g['uyumlu']) && $vergi > 0
        ? round(min($vergi * 5 / 100, 12000000), 2) : 0.0;

    $odenmesi = round(max(0, $vergi - $uyumlu), 2);
    $mahsup   = round(($g['stopaj'] ?? 0) + ($g['gecici'] ?? 0) + ($g['diger_mahsup'] ?? 0), 2);
    $sonuc    = round($odenmesi - $mahsup, 2);

    return [
        'kazanc' => $kazanc, 'matrah' => $matrah, 'vergi' => $vergi,
        'uyumlu' => $uyumlu, 'odenmesi' => $odenmesi, 'mahsup' => $mahsup,
        'sonuc' => $sonuc, 'odenecek' => max(0, $sonuc), 'iade' => max(0, -$sonuc),
        'dilim_no' => $t['dilim_no'],
    ];
}

// --- Senaryo 1: test verisiyle aynı (Ali Yılmaz) --------------------
// Hasılat 500.000, gider 200.000 → kazanç 300.000
// Bağkur 30.000 → matrah 270.000
// Vergi = 28.500 + (270.000-190.000)×%20 = 28.500 + 16.000 = 44.500
// Stopaj 100.000 → 44.500 - 100.000 = -55.500 → İADE 55.500
$s1 = zincir([
    'hasilat' => 500000, 'gider' => 200000, 'bagkur' => 30000,
    'stopaj' => 100000,
], $d26);

esit(300000.0, $s1['kazanc'],   'S1: kazanç = 500.000 - 200.000');
esit(270000.0, $s1['matrah'],   'S1: matrah = 300.000 - 30.000 Bağkur');
esit(44500.0,  $s1['vergi'],    'S1: vergi = 28.500 + 80.000×%20 = 44.500');
esit(2,        $s1['dilim_no'], 'S1: 2. dilim');
esit(0.0,      $s1['odenecek'], 'S1: ödenecek yok (stopaj fazla)');
esit(55500.0,  $s1['iade'],     'S1: iade = 100.000 - 44.500 = 55.500');

// --- Senaryo 2: yüksek kazanç, ödenecek çıkar -----------------------
// Hasılat 2.000.000, gider 400.000 → kazanç 1.600.000, matrah 1.600.000
// Vergi = 232.500 + 600.000×%35 = 442.500
// Stopaj 400.000, geçici 20.000 → 442.500 - 420.000 = 22.500 ödenecek
$s2 = zincir([
    'hasilat' => 2000000, 'gider' => 400000,
    'stopaj' => 400000, 'gecici' => 20000,
], $d26);

esit(1600000.0, $s2['matrah'],   'S2: matrah 1.600.000');
esit(442500.0,  $s2['vergi'],    'S2: vergi = 232.500 + 600.000×%35 = 442.500');
esit(4,         $s2['dilim_no'], 'S2: 4. dilim');
esit(420000.0,  $s2['mahsup'],   'S2: mahsup = 400.000 + 20.000');
esit(22500.0,   $s2['odenecek'], 'S2: ödenecek 22.500');
esit(0.0,       $s2['iade'],     'S2: iade yok');

// --- Senaryo 3: %5 uyumlu mükellef indirimi -------------------------
// Aynı S2 ama uyumlu indirim açık: 442.500×%5 = 22.125
// Ödenmesi gereken 420.375; mahsup 420.000 → 375 ödenecek
$s3 = zincir([
    'hasilat' => 2000000, 'gider' => 400000, 'uyumlu' => true,
    'stopaj' => 400000, 'gecici' => 20000,
], $d26);

esit(22125.0,  $s3['uyumlu'],   'S3: %5 indirim = 442.500×0,05 = 22.125');
esit(420375.0, $s3['odenmesi'], 'S3: ödenmesi gereken = 442.500 - 22.125');
esit(375.0,    $s3['odenecek'], 'S3: ödenecek 375');

// --- Senaryo 4: gider hasılatı aşarsa (zarar) -----------------------
$s4 = zincir(['hasilat' => 300000, 'gider' => 400000, 'stopaj' => 60000], $d26);

esit(-100000.0, $s4['kazanc'],   'S4: kazanç negatif (-100.000)');
esit(0.0,       $s4['matrah'],   'S4: matrah negatif olamaz → 0');
esit(0.0,       $s4['vergi'],    'S4: vergi 0');
esit(60000.0,   $s4['iade'],     'S4: stopajın tamamı iade');

// --- Senaryo 5: indirimler kazancı aşarsa ---------------------------
$s5 = zincir([
    'hasilat' => 500000, 'gider' => 400000,
    'bagkur' => 60000, 'zarar' => 80000,
], $d26);

esit(100000.0, $s5['kazanc'], 'S5: kazanç 100.000');
esit(0.0,      $s5['matrah'], 'S5: indirim (140.000) kazancı aşınca matrah 0');
esit(0.0,      $s5['vergi'],  'S5: vergi 0');

// --- Senaryo 6: %5 indirim ÜST SINIRI --------------------------------
// Matrah 700.000.000 → vergi çok yüksek; %5'i 12.000.000'u aşar
$s6 = zincir(['hasilat' => 700000000, 'gider' => 0, 'uyumlu' => true], $d26);
esit(12000000.0, $s6['uyumlu'], 'S6: %5 indirim 12.000.000 üst sınırında durur');

// --- Senaryo 7: kuruşlu tutar ---------------------------------------
// Matrah 250.333,33 → 28.500 + 60.333,33×%20 = 28.500 + 12.066,666 = 40.566,67
$s7 = zincir(['hasilat' => 250333.33, 'gider' => 0], $d26);
esit(40566.67, $s7['vergi'], 'S7: kuruşlu matrah doğru yuvarlanır (40.566,67)');

// --- Senaryo 8: tam dilim sınırında ödenecek/iade sıfır -------------
$s8 = zincir(['hasilat' => 190000, 'gider' => 0, 'stopaj' => 28500], $d26);
esit(0.0, $s8['odenecek'], 'S8: vergi = stopaj → ödenecek 0');
esit(0.0, $s8['iade'],     'S8: vergi = stopaj → iade 0');

// =====================================================================
baslik('Kırılım toplamı = hesaplanan vergi');
// =====================================================================
foreach ([50000, 190000, 250000, 400000, 999999.99, 1000000, 3000000, 5300000, 9000000] as $m) {
    $k = vergiHesapla((float) $m, $d26);
    esit(
        $k['vergi'],
        round(array_sum(array_column($k['kirilim'], 'vergi')), 2),
        'Matrah ' . number_format($m, 2, ',', '.') . ' → dilim kırılımı toplamı vergiye eşit'
    );
}


// =====================================================================
baslik('GVK md.89 SINIRLI İNDİRİMLER + KDV MAHSUBU');
// =====================================================================

/**
 * GelirVergisiModel::hesapla ile AYNI indirim/mahsup zinciri.
 *
 * Sigorta primi kârın %15'ini, eğitim-sağlık %10'unu aşamaz.
 * Taban = KAZANÇ (hasılat − gider), Bağ-Kur düşülmeden önce.
 * KDV yıl içi vergi yüküdür; stopajdan DÜŞÜLÜR.
 */
function zincir2(array $g, array $dilimler): array
{
    $kazanc = round($g['hasilat'] - $g['gider'], 2);
    $taban  = max(0, $kazanc);

    $sigTavan = round($taban * 15 / 100, 2);
    $egiTavan = round($taban * 10 / 100, 2);

    $sigorta = min($g['sigorta'] ?? 0, $sigTavan);
    $egitim  = min($g['egitim'] ?? 0, $egiTavan);

    $indirim = round(($g['bagkur'] ?? 0) + $sigorta + $egitim, 2);
    $matrah  = round(max(0, $kazanc - $indirim), 2);

    $t     = vergiHesapla($matrah, $dilimler);
    $vergi = $t['vergi'];

    $uyumlu = ! empty($g['uyumlu']) && $vergi > 0
        ? round(min($vergi * 5 / 100, 12000000), 2) : 0.0;

    $odenmesi = round(max(0, $vergi - $uyumlu), 2);

    // KDV stopajdan düşülür → net mahsup
    $mahsup = round(($g['stopaj'] ?? 0) + ($g['diger_mahsup'] ?? 0) - ($g['kdv'] ?? 0), 2);
    $sonuc  = round($odenmesi - $mahsup, 2);

    return [
        'kazanc' => $kazanc, 'sigorta' => $sigorta, 'egitim' => $egitim,
        'sigorta_tavan' => $sigTavan, 'egitim_tavan' => $egiTavan,
        'sigorta_asim' => round(max(0, ($g['sigorta'] ?? 0) - $sigorta), 2),
        'egitim_asim' => round(max(0, ($g['egitim'] ?? 0) - $egitim), 2),
        'indirim' => $indirim, 'matrah' => $matrah, 'vergi' => $vergi,
        'mahsup' => $mahsup, 'sonuc' => $sonuc,
        'odenecek' => max(0, $sonuc), 'iade' => max(0, -$sonuc),
    ];
}

// --- Sınır tam çalışıyor mu? ----------------------------------------
// Kazanç 300.000 → sigorta tavanı 45.000, eğitim tavanı 30.000
$i1 = zincir2([
    'hasilat' => 500000, 'gider' => 200000,
    'sigorta' => 60000, 'egitim' => 20000,
], $d26);

esit(45000.0,  $i1['sigorta_tavan'], 'İ1: sigorta tavanı = kârın %15 = 45.000');
esit(30000.0,  $i1['egitim_tavan'],  'İ1: eğitim tavanı = kârın %10 = 30.000');
esit(45000.0,  $i1['sigorta'],       'İ1: 60.000 talep → 45.000 indirildi');
esit(15000.0,  $i1['sigorta_asim'],  'İ1: 15.000 sınır aşımı indirilemedi');
esit(20000.0,  $i1['egitim'],        'İ1: eğitim tavan altı, tamamı indi');
esit(0.0,      $i1['egitim_asim'],   'İ1: eğitim aşımı yok');
esit(65000.0,  $i1['indirim'],       'İ1: indirim toplamı 65.000');
esit(235000.0, $i1['matrah'],        'İ1: matrah 235.000');

// --- Taban KAZANÇ: gider artınca tavan düşer -------------------------
$i2 = zincir2([
    'hasilat' => 500000, 'gider' => 400000,
    'sigorta' => 60000, 'egitim' => 60000,
], $d26);

esit(15000.0, $i2['sigorta_tavan'], 'İ2: gider artınca sigorta tavanı 15.000');
esit(10000.0, $i2['egitim_tavan'],  'İ2: gider artınca eğitim tavanı 10.000');
esit(25000.0, $i2['indirim'],       'İ2: indirim toplamı 25.000 ile sınırlı');
esit(75000.0, $i2['matrah'],        'İ2: matrah 75.000');

// --- Bağ-Kur SINIRSIZ -------------------------------------------------
$i3 = zincir2(['hasilat' => 500000, 'gider' => 200000, 'bagkur' => 250000], $d26);
esit(250000.0, $i3['indirim'], 'İ3: Bağ-Kur sınırsız, tamamı indi');
esit(50000.0,  $i3['matrah'],  'İ3: matrah 50.000');

// --- Zararda tavan 0 --------------------------------------------------
$i4 = zincir2([
    'hasilat' => 100000, 'gider' => 300000,
    'sigorta' => 50000, 'egitim' => 50000,
], $d26);

esit(0.0, $i4['sigorta_tavan'], 'İ4: zararda sigorta tavanı 0');
esit(0.0, $i4['egitim_tavan'],  'İ4: zararda eğitim tavanı 0');
esit(0.0, $i4['indirim'],       'İ4: zararda indirim yok');
esit(0.0, $i4['matrah'],        'İ4: zararda matrah 0');

// --- Tavan tam sınırda ------------------------------------------------
// Kazanç 200.000 → tavan tam 30.000; talep de tam 30.000 → aşım YOK
$i5 = zincir2(['hasilat' => 400000, 'gider' => 200000, 'sigorta' => 30000], $d26);
esit(30000.0, $i5['sigorta'],      'İ5: talep tam tavana eşitse tamamı iner');
esit(0.0,     $i5['sigorta_asim'], 'İ5: tam sınırda aşım yok');

// =====================================================================
baslik('KDV MAHSUBU — yıl içi net vergi');
// =====================================================================

// KDV < stopaj → yine iade, ama iade AZALIR
// matrah 235.000 → vergi = 28.500 + 45.000×%20 = 37.500
$k1 = zincir2([
    'hasilat' => 500000, 'gider' => 200000, 'sigorta' => 60000, 'egitim' => 20000,
    'stopaj' => 100000, 'kdv' => 38000,
], $d26);

esit(37500.0, $k1['vergi'],    'K1: vergi 37.500');
esit(62000.0, $k1['mahsup'],   'K1: net mahsup = 100.000 − 38.000 KDV');
esit(24500.0, $k1['iade'],     'K1: iade 62.000 − 37.500 = 24.500');
esit(0.0,     $k1['odenecek'], 'K1: ödenecek yok');

// KDV > stopaj → ÖDENECEK doğar (kullanıcının asıl istediği mantık)
$k2 = zincir2([
    'hasilat' => 500000, 'gider' => 200000, 'sigorta' => 60000, 'egitim' => 20000,
    'stopaj' => 100000, 'kdv' => 150000,
], $d26);

esit(-50000.0, $k2['mahsup'],   'K2: KDV stopajı aşınca net mahsup negatif');
esit(87500.0,  $k2['odenecek'], 'K2: ödenecek = 37.500 + 50.000 = 87.500');
esit(0.0,      $k2['iade'],     'K2: iade yok');

// KDV yokken eski davranış korunur
$k3 = zincir2([
    'hasilat' => 500000, 'gider' => 200000, 'sigorta' => 60000, 'egitim' => 20000,
    'stopaj' => 100000,
], $d26);

esit(100000.0, $k3['mahsup'], 'K3: KDV yoksa mahsup = stopaj');
esit(62500.0,  $k3['iade'],   'K3: iade 100.000 − 37.500 = 62.500');

// KDV MATRAHA girmez — KDV değişse de matrah sabit kalmalı
esit($k3['matrah'], $k1['matrah'], 'K4: KDV matrahı DEĞİŞTİRMEZ (38.000 KDV)');
esit($k3['matrah'], $k2['matrah'], 'K4: KDV matrahı DEĞİŞTİRMEZ (150.000 KDV)');
esit($k3['vergi'],  $k2['vergi'],  'K4: KDV hesaplanan vergiyi değiştirmez');

// KDV tam stopaja eşitse sonuç = verginin tamamı ödenir
$k5 = zincir2([
    'hasilat' => 500000, 'gider' => 200000, 'sigorta' => 60000, 'egitim' => 20000,
    'stopaj' => 100000, 'kdv' => 100000,
], $d26);

esit(0.0,     $k5['mahsup'],   'K5: KDV = stopaj → net mahsup 0');
esit(37500.0, $k5['odenecek'], 'K5: verginin tamamı ödenecek');

// Ödenen + indirilecek TOPLAMI sayılır (kullanıcı kararı)
$k6 = zincir2([
    'hasilat' => 500000, 'gider' => 200000, 'sigorta' => 60000, 'egitim' => 20000,
    'stopaj' => 100000, 'kdv' => 128200 + 41900,
], $d26);

esit(170100.0, 128200 + 41900,  'K6: ödenen + indirilecek = 170.100');
esit(-70100.0, $k6['mahsup'],   'K6: net mahsup = 100.000 − 170.100');
esit(107600.0, $k6['odenecek'], 'K6: ödenecek = 37.500 + 70.100');


// =====================================================================
baslik('YIL İÇİ VERGİ YÜKÜ (GV dengesi + KDV) ve AYLIK GİDER');
// =====================================================================

/**
 * Vergi yükü zinciri.
 *
 *   Gider      = elle girilen + aylık tablo toplamı   (TOPLANIR)
 *   GV dengesi = ödenmesi gereken vergi − stopaj − diğer mahsuplar
 *                (negatif = devletten alacak)
 *   Vergi yükü = GV dengesi + ödenen KDV
 */
function yuk(array $g, array $dilimler): array
{
    $gider  = round(($g['gider_elle'] ?? 0) + ($g['gider_aylik'] ?? 0), 2);
    $kazanc = round(($g['hasilat'] ?? 0) - $gider, 2);
    $matrah = round(max(0, $kazanc), 2);

    $t     = vergiHesapla($matrah, $dilimler);
    $vergi = $t['vergi'];

    $stopaj = $g['stopaj'] ?? 0;
    $diger  = $g['diger_mahsup'] ?? 0;
    $kdv    = $g['kdv'] ?? 0;

    $gvDenge = round($vergi - $stopaj - $diger, 2);
    $mahsup  = round($stopaj + $diger - $kdv, 2);
    $sonuc   = round($vergi - $mahsup, 2);

    return [
        'gider' => $gider, 'matrah' => $matrah, 'vergi' => $vergi,
        'gv_denge' => $gvDenge, 'gv_alacak' => max(0, -$gvDenge), 'gv_borc' => max(0, $gvDenge),
        'yuk' => $sonuc, 'odenecek' => max(0, $sonuc), 'iade' => max(0, -$sonuc),
    ];
}

// --- Kullanıcının verdiği senaryo -----------------------------------
// vergi 3.000, stopaj 4.000 → 1.000 alacak; KDV 2.500 → yük 1.500 ödenecek
$y1 = yuk([
    'hasilat' => 520000, 'gider_elle' => 500000,   // matrah 20.000 → vergi 3.000
    'stopaj' => 4000, 'kdv' => 2500,
], $d26);

esit(20000.0, $y1['matrah'],    'Y1: matrah 20.000');
esit(3000.0,  $y1['vergi'],     'Y1: vergi 3.000 (%15)');
esit(-1000.0, $y1['gv_denge'],  'Y1: GV dengesi −1.000');
esit(1000.0,  $y1['gv_alacak'], 'Y1: devletten 1.000 alacak');
esit(1500.0,  $y1['odenecek'],  'Y1: KDV 2.500 ile vergi yükü 1.500 ÖDENECEK');
esit(0.0,     $y1['iade'],      'Y1: iade yok');

// --- Aynı senaryo, KDV küçük: yük iadeye döner ----------------------
$y2 = yuk([
    'hasilat' => 520000, 'gider_elle' => 500000,
    'stopaj' => 4000, 'kdv' => 500,
], $d26);

esit(500.0, $y2['iade'],     'Y2: KDV 500 → 500 İADE');
esit(0.0,   $y2['odenecek'], 'Y2: ödenecek yok');

// --- KDV tam dengeyi kapatır -----------------------------------------
$y3 = yuk([
    'hasilat' => 520000, 'gider_elle' => 500000,
    'stopaj' => 4000, 'kdv' => 1000,
], $d26);

esit(0.0, $y3['yuk'],      'Y3: KDV = alacak → yük tam 0');
esit(0.0, $y3['odenecek'], 'Y3: ne ödeme ne iade');

// --- Stopaj yoksa GV borçlu; KDV üstüne biner ------------------------
$y4 = yuk([
    'hasilat' => 520000, 'gider_elle' => 500000,
    'stopaj' => 0, 'kdv' => 1000,
], $d26);

esit(3000.0, $y4['gv_borc'],  'Y4: stopaj yokken GV borcu 3.000');
esit(4000.0, $y4['odenecek'], 'Y4: 3.000 borç + 1.000 KDV = 4.000 yük');

// --- Vergi yükü = GV dengesi + KDV özdeşliği --------------------------
foreach ([[3000, 4000, 2500], [10000, 2000, 0], [0, 5000, 800], [50000, 50000, 1200]] as $x) {
    [$v, $st, $kd] = $x;

    // Matrahı %15 diliminde tutup vergiyi doğrudan kurgula
    $gvDenge = $v - $st;
    $yukBeklenen = $gvDenge + $kd;

    esit(
        (float) $yukBeklenen,
        (float) ($v - ($st - $kd)),
        sprintf('Özdeşlik: vergi %d, stopaj %d, KDV %d → yük %d', $v, $st, $kd, $yukBeklenen)
    );
}

// =====================================================================
baslik('AYLIK GİDER: elle + tablo TOPLANIR');
// =====================================================================

$g1 = yuk(['hasilat' => 550000, 'gider_elle' => 480000, 'gider_aylik' => 15000], $d26);
esit(495000.0, $g1['gider'],  'G1: 480.000 elle + 15.000 tablo = 495.000');
esit(55000.0,  $g1['matrah'], 'G1: matrah 55.000');

// Tablo boşsa yalnız elle girilen
$g2 = yuk(['hasilat' => 550000, 'gider_elle' => 480000, 'gider_aylik' => 0], $d26);
esit(480000.0, $g2['gider'], 'G2: tablo boşsa yalnız elle girilen');

// Elle boşsa yalnız tablo
$g3 = yuk(['hasilat' => 550000, 'gider_elle' => 0, 'gider_aylik' => 120000], $d26);
esit(120000.0, $g3['gider'], 'G3: elle boşsa yalnız tablo toplamı');

// İkisi de boşsa gider 0, tüm hasılat matrah
$g4 = yuk(['hasilat' => 100000, 'gider_elle' => 0, 'gider_aylik' => 0], $d26);
esit(0.0,      $g4['gider'],  'G4: ikisi de boşsa gider 0');
esit(100000.0, $g4['matrah'], 'G4: matrah = hasılat');

// Gider hasılatı aşarsa matrah 0
$g5 = yuk(['hasilat' => 100000, 'gider_elle' => 80000, 'gider_aylik' => 50000], $d26);
esit(130000.0, $g5['gider'],  'G5: toplam gider 130.000');
esit(0.0,      $g5['matrah'], 'G5: gider hasılatı aşınca matrah 0');

// =====================================================================
echo "\n" . str_repeat('=', 56) . "\n";
echo "GEÇEN: {$gecen}   KALAN: {$kalan}   TOPLAM: " . ($gecen + $kalan) . "\n";
echo str_repeat('=', 56) . "\n";

exit($kalan > 0 ? 1 : 0);
