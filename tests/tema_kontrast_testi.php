<?php
/**
 * TEMA KONTRAST TESTİ (WCAG 2.1 — AA)
 * =====================================================================
 * NEDEN VAR
 * ---------
 * Kullanıcı, karanlık temada bazı rozetlerin METİNSİZ göründüğünü bildirdi
 * (örnek: ajanda "Yüksek" öncelik rozeti — açık zemin + açık metin).
 * Sebep: rozet zemini/metni sabit renklerle yazılmıştı ve karanlık tema
 * yalnız bazı değişkenleri çeviriyordu.
 *
 * Bu test, rozet ve etiketlerin **zemin ↔ metin** çiftlerini tema.css ve
 * stil.css'ten okuyup WCAG kontrast oranını hesaplar:
 *
 *     oran = (L1 + 0.05) / (L2 + 0.05)      L = bağıl parlaklık
 *
 * Eşik: 4.5 (normal metin, AA) — rozet metinleri 10.5-13px olduğu için
 * "büyük metin" istisnası (3.0) kullanılmaz.
 *
 * Kapsam: AÇIK ve KARANLIK tema ayrı ayrı sınanır; palet ve yan menü
 * seçenekleri rozet renklerini etkilemediği için tek seferlik kontrol edilir.
 *
 * Kullanım: php tests/tema_kontrast_testi.php
 * =====================================================================
 */

$kok   = dirname(__DIR__);
$stil  = file_get_contents($kok . '/public/assets/css/stil.css');
$tema  = file_get_contents($kok . '/public/assets/css/tema.css');

$g = 0; $k = 0;

function ol(string $ad, string $bekl, string $ger): void
{
    global $g, $k;

    if ($bekl === $ger) {
        echo "  [OK] $ad\n";
        $g++;

        return;
    }

    echo "  [HATA] $ad (bekl:$bekl ger:$ger)\n";
    $k++;
}

/** CSS metninden :root bloğundaki değişkenleri çıkarır. */
function degiskenler(string $css, string $blok = ':root{'): array
{
    $yer = strpos($css, $blok);

    if ($yer === false) {
        return [];
    }

    $son = strpos($css, '}', $yer);
    $govde = substr($css, $yer, $son - $yer);

    preg_match_all('/--([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,8})/', $govde, $m, PREG_SET_ORDER);

    $out = [];

    foreach ($m as $s) {
        $out[$s[1]] = strtolower($s[2]);
    }

    return $out;
}

/** Seçicinin (ör. karanlık tema) değişkenlerini çıkarır — ana :root'u ezmez. */
function katman(array $taban, string $css, string $secici): array
{
    $yer = strpos($css, $secici);

    if ($yer === false) {
        return $taban;
    }

    $bas = strpos($css, '{', $yer);
    $son = strpos($css, '}', $bas);
    $govde = substr($css, $bas, $son - $bas);

    preg_match_all('/--([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,8})/', $govde, $m, PREG_SET_ORDER);

    foreach ($m as $s) {
        $taban[$s[1]] = strtolower($s[2]);
    }

    return $taban;
}

/** #rgb / #rrggbb → [r,g,b] */
function rgbDizisi(string $hex): array
{
    $h = ltrim($hex, '#');

    if (strlen($h) === 3) {
        $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }

    return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
}

/** WCAG bağıl parlaklık */
function parlaklik(string $hex): float
{
    $kanal = static function (float $c): float {
        $c /= 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };

    [$r, $g, $b] = rgbDizisi($hex);

    return 0.2126 * $kanal($r) + 0.7152 * $kanal($g) + 0.0722 * $kanal($b);
}

/** İki renk arasındaki kontrast oranı */
function kontrast(string $a, string $b): float
{
    $l1 = parlaklik($a);
    $l2 = parlaklik($b);

    return ($l1 > $l2) ? ($l1 + 0.05) / ($l2 + 0.05) : ($l2 + 0.05) / ($l1 + 0.05);
}

/** Değişken değerini çözer (yoksa hata) */
function renk(array $v, string $ad): string
{
    return $v[$ad] ?? '#000000';
}

// ---------------------------------------------------------------------
//  Tema değişken tabloları
// ---------------------------------------------------------------------
$acik = degiskenler($stil, ':root{');
$acikPalet = katman($acik, $tema, ':root[data-palet="mavi"]');

$karanlik = katman($acik, $tema, ':root[data-tema="karanlik"]');
$karanlik = katman($karanlik, $tema, ':root[data-tema="karanlik"][data-palet="mavi"]');

// ---------------------------------------------------------------------
//  Sınanacak çiftler: [görünen ad, zemin değişkeni, metin değişkeni]
//  (stil.css / modül stillerinde birebir kullanılan eşleşmeler)
// ---------------------------------------------------------------------
/*
 * Üçüncü eleman eşik kategorisidir:
 *   'normal' → 4.5 (WCAG AA, normal metin)
 *   'muted'  → 3.0 (bilinçli olarak soluk/muted tasarlanmış öğeler:
 *                   üstü çizili "Verilmeyecek" rozeti gibi)
 */
$ciftler = [
    // Ajanda — ÖNCELİK rozetleri
    ['Ajanda: Düşük öncelik',        'gri-100',            'gri-600',           'normal'],
    ['Ajanda: Normal öncelik',       'ana-acik',           'ana-metin', 'normal'],
    ['Ajanda: Yüksek öncelik',       'turuncu-acik',       'sari-metin-koyu', 'normal'],
    ['Ajanda: Acil öncelik',         'kirmizi-acik',       'kirmizi-metin', 'normal'],
    // Ajanda — GÖRÜNÜRLÜK rozetleri
    ['Ajanda: Kişisel görünürlük',   'etiket-mor-acik',    'etiket-mor-metin', 'normal'],
    ['Ajanda: Genel görünürlük',     'etiket-yesil-acik',  'etiket-yesil-metin', 'normal'],
    ['Ajanda: Görev görünürlük',     'sari-acik',          'etiket-sari-metin', 'normal'],
    ['Ajanda: Müşavir görünürlük',   'etiket-indigo-acik', 'etiket-indigo-metin', 'normal'],
    // Ajanda — DURUM rozeti
    ['Ajanda: Yapıldı durumu',       'etiket-yesil-acik',  'etiket-yesil-metin', 'normal'],
    // Beyanname takip rozetleri
    ['Beyanname: Bekliyor',          'gri-200',            'gri-700', 'normal'],
    ['Beyanname: Hazır',             'sari-acik',          'sari-metin', 'normal'],
    ['Beyanname: Onaylandı',         'ana-acik',           'ana-uzeri', 'normal'],
    ['Beyanname: Gönderildi',        'yesil-acik',         'yesil-metin', 'normal'],
    ['Beyanname: Gecikmiş',          'kirmizi-acik',       'kirmizi-metin', 'normal'],
    ['Beyanname: Verilmeyecek',      'gri-100',            'gri-500', 'muted'],
    // Kişisel notlar
    ['Kişisel: Etiket çipi',         'etiket-indigo-acik', 'etiket-indigo-metin', 'normal'],
    ['Kişisel: Giriş uyarı etiketi', 'etiket-mavi-acik',   'etiket-mavi-metin', 'normal'],
    ['Kişisel: Öncelik (turuncu)',   'turuncu-acik',       'sari-metin-koyu', 'normal'],
    // Gelir vergisi kalem rozetleri
    ['Gelir vergisi: Hayat sigorta', 'etiket-mor-acik',    'etiket-mor-metin', 'normal'],
    ['Gelir vergisi: Sağlık/eğitim', 'etiket-yesil-acik',  'etiket-yesil-metin', 'normal'],
    ['Gelir vergisi: Şahıs sigorta', 'etiket-pembe-acik',  'etiket-pembe-metin', 'normal'],
    // Uyarı kutuları
    ['Uyarı: Başarılı',              'yesil-acik',         'yesil-metin-koyu', 'normal'],
    ['Uyarı: Hata',                  'kirmizi-acik',       'kirmizi-metin', 'normal'],
    ['Uyarı: Bilgi',                 'ana-acik',           'ana-metin', 'normal'],
    ['Uyarı: Dikkat',                'sari-acik',          'sari-metin-koyu', 'normal'],
    // Genel metin / yüzey
    ['Gövde metni → yüzey',          'yuzey',              'gri-800', 'normal'],
    ['İkincil metin → yüzey',        'yuzey',              'gri-500', 'normal'],
    ['Yan menü metni',               'yan-bg-1',           'yan-metin', 'normal'],
    ['Yan menü başlığı',             'yan-bg-1',           'yan-baslik', 'normal'],
];

echo "=== 1) AÇIK TEMA (varsayılan — bugünkü görünüm) ===\n";
foreach ($ciftler as [$ad, $zeminVar, $metinVar, $kategori]) {
    $esik  = $kategori === 'muted' ? 3.0 : 4.5;
    $zemin = renk($acikPalet, $zeminVar);
    $metin = renk($acikPalet, $metinVar);
    $oran  = round(kontrast($zemin, $metin), 2);

    $durum = $oran >= $esik ? 'geçti' : sprintf('%.1f altı', $esik);

    ol(sprintf('%-30s %s üzerine %s → %.2f:1 (eşik %.1f)', $ad, $zemin, $metin, $oran, $esik),
       'geçti', $durum);
}

echo "\n=== 2) KARANLIK TEMA (asıl düzeltilen durum) ===\n";

foreach ($ciftler as [$ad, $zeminVar, $metinVar, $kategori]) {
    $esik  = $kategori === 'muted' ? 3.0 : 4.5;
    $zemin = renk($karanlik, $zeminVar);
    $metin = renk($karanlik, $metinVar);
    $oran  = round(kontrast($zemin, $metin), 2);

    $durum = $oran >= $esik ? 'geçti' : sprintf('%.1f altı', $esik);

    ol(sprintf('%-30s %s üzerine %s → %.2f:1 (eşik %.1f)', $ad, $zemin, $metin, $oran, $esik),
       'geçti', $durum);
}

echo "\n=== 3) SABİT RENK TARAMASI (karanlıkta açık kalan zemin) ===\n";
/*
 * Karanlık temada okunmayan öğelerin kök nedeni, view'lerde kalan SABİT
 * açık renklerdir. Bu bölüm onları tarar: parlaklığı yüksek (açık) sabit
 * renkler "background:" bağlamında kullanılıyorsa ve bir var() yedeği
 * DEĞİLSE hata verir.
 */
$muaf = ['yazdir', 'error_'];   // yazdırma ve CI hata sayfaları tema kullanmaz
$hata = [];

$dosyalar = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($kok . '/app/Views', FilesystemIterator::SKIP_DOTS)
);

foreach ($dosyalar as $dosya) {
    $ad = $dosya->getFilename();

    foreach ($muaf as $atla) {
        if (str_contains($ad, $atla)) {
            continue 2;
        }
    }

    if (! str_ends_with($ad, '.php')) {
        continue;
    }

    $icerik = file_get_contents($dosya->getPathname());
    $satirlar = explode("\n", $icerik);

    foreach ($satirlar as $no => $satir) {
        if (! preg_match_all('/background:[^;"\']*?(#[0-9a-fA-F]{6})/', $satir, $m)) {
            continue;
        }

        foreach ($m[1] as $i => $hex) {
            $hex = strtolower($hex);

            // var(--x, #fff) yedeği ise sorun değil
            if (preg_match('/var\(--[a-z0-9-]+,\s*' . preg_quote($hex, '/') . '/i', $satir)) {
                continue;
            }

            // ÇOK AÇIK renk (parlaklık > 0.75) → karanlıkta okunmaz
            if (parlaklik($hex) > 0.75) {
                $kisa = $dosya->getPathname();
                $kisa = substr($kisa, strpos($kisa, 'app/Views'));
                $hata[] = "$kisa:" . ($no + 1) . "  $hex  " . trim(substr($satir, 0, 70));
            }
        }
    }
}

ol('view\'lerde sabit açık zemin yok', '0', (string) count($hata));

foreach ($hata as $h) {
    echo "        → $h\n";
}

echo "\n=== 4) DEĞİŞKEN TANIM TUTARLILIĞI ===\n";
/*
 * Etiket/durum değişkenleri hem açık (stil.css :root) hem karanlık
 * (tema.css) hem de yazdırma bloğunda tanımlı olmalı; eksik tanım
 * karanlıkta renk kaybına yol açar.
 */
$zorunlu = [
    'etiket-mor-acik', 'etiket-mor-metin', 'etiket-yesil-acik', 'etiket-yesil-metin',
    'etiket-sari-metin', 'etiket-indigo-acik', 'etiket-indigo-metin',
    'etiket-pembe-acik', 'etiket-pembe-metin', 'etiket-mavi-acik', 'etiket-mavi-metin',
    'yesil-cok-acik', 'yesil-kenar-acik', 'yesil-dolu-kenar',
    'ana-kenar-acik', 'gok-mavi-acik', 'gok-kenar',
    'takvim-haftasonu', 'kutu-ust',
];

$yazdirBlok = '';
$yer = strpos($tema, '@media print');

if ($yer !== false) {
    $yazdirBlok = substr($tema, $yer);
}

foreach ($zorunlu as $ad) {
    $acikVar   = isset($acik[$ad]);
    $koyuVar   = isset($karanlik[$ad]);
    $yazdirVar = str_contains($yazdirBlok, '--' . $ad . ':');

    ol(sprintf('%-22s açık:%s karanlık:%s yazdırma:%s', $ad,
        $acikVar ? '✓' : '✗', $koyuVar ? '✓' : '✗', $yazdirVar ? '✓' : '✗'),
       'açık:✓ karanlık:✓ yazdırma:✓',
       sprintf('açık:%s karanlık:%s yazdırma:%s',
           $acikVar ? '✓' : '✗', $koyuVar ? '✓' : '✗', $yazdirVar ? '✓' : '✗'));
}

echo "\n======================================================\n";

// Süit (test_kos.sh) bu ifadeyi yakalar
echo $k === 0
    ? "TÜM TESTLER BAŞARILI ($g geçti)\n"
    : "SONUÇ: $g geçti, $k hata\n";

echo "======================================================\n";

exit($k === 0 ? 0 : 1);
