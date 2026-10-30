<?php

/**
 * MÜKELLEF MÜŞAVİR SÖZLEŞME DÖNEMLERİ — BİRİM + VERİTABANI TESTİ
 *
 * Senaryo (Atikay Ltd. Şti.):
 *   Ocak–Şubat 2026 → Ali Yılmaz (müşavir 1)
 *   Mart–Aralık 2026 → Veli Demir (müşavir 2)
 *   Yıllık ücret 12.000 TL → Ali 2.000, Veli 10.000 (ay payı)
 *   Makbuzlar: Ocak 1.000 + Şubat 1.000 (Ali kesti), Mart 1.000 (Veli kesti)
 *
 *   php tests/musavir_donem_testi.php
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

use App\Models\MakbuzModel;
use App\Models\MukellefModel;
use App\Models\MusavirDonemModel;
use App\Libraries\MakbuzIceAktar;
use App\Models\GelirVergisiModel;

$db = \Config\Database::connect();
$ok = 0;
$fail = 0;

function t(string $ad, bool $sart, string $detay = ''): void
{
    global $ok, $fail;

    if ($sart) {
        $ok++;
        echo "  [OK] $ad\n";
    } else {
        $fail++;
        echo "  [HATA] $ad  -- $detay\n";
    }
}

$yil = 2026;
$D   = MusavirDonemModel::class;

echo "=== 1) AY YARDIMCILARI (saf mantık) ===\n";
t('ayBasi 2026-03 → 2026-03-01', MusavirDonemModel::ayBasi('2026-03') === '2026-03-01');
t('ayBasi geçersiz ay (13) → null', MusavirDonemModel::ayBasi('2026-13') === null);
t('ayBasi boş → null', MusavirDonemModel::ayBasi('') === null);
t('ayBitisi 2026-02 → 2026-02-28', MusavirDonemModel::ayBitisi('2026-02') === '2026-02-28');
t('ayBitisi 2024-02 (artık yıl) → 2024-02-29', MusavirDonemModel::ayBitisi('2024-02') === '2024-02-29');

$model = new MusavirDonemModel();

$donemler = [
    ['musavir_id' => 1, 'baslangic' => '2000-01-01', 'bitis' => '2026-02-28'],
    ['musavir_id' => 2, 'baslangic' => '2026-03-01', 'bitis' => null],
];
$h = $model->ayHaritasi($donemler, 2026);
t('Ocak–Şubat müşavir 1', $h[1] === 1 && $h[2] === 1, json_encode($h));
t('Mart–Aralık müşavir 2', $h[3] === 2 && $h[12] === 2, json_encode($h));
t('Ay paylaşımı: müşavir1=2, müşavir2=10', MusavirDonemModel::paylar($h) === [1 => 2, 2 => 10], json_encode(MusavirDonemModel::paylar($h)));

$h2027 = $model->ayHaritasi($donemler, 2027);
t('2027 yılı: açık dönem tüm yıl müşavir 2', MusavirDonemModel::aySayisi($h2027, [2]) === 12);

$bosluk = $model->ayHaritasi([['musavir_id' => 1, 'baslangic' => '2026-03-01', 'bitis' => '2026-06-30']], 2026);
t('Dönem dışı aylar atanmamış (0)', $bosluk[1] === 0 && $bosluk[3] === 1 && $bosluk[7] === 0, json_encode($bosluk));

t('Ücret payı: 12.000 × 10/12 = 10.000',
  round(12000 * MusavirDonemModel::aySayisi($h, [2]) / 12, 2) === 10000.0);

// -----------------------------------------------------------------
echo "\n=== 2) VERİTABANI: FİKSTÜR (Atikay) ===\n";

// Temizlik: önceki yarım kalmış fikstürler
$db->query("DELETE FROM mukellefler WHERE unvan LIKE 'MDT-TEST%'");

$baseline = [
    'ozet_m1_ucret'   => 0.0,
    'ozet_m2_ucret'   => 0.0,
    'ozet_m1_kesilen' => 0.0,
    'ozet_m2_kesilen' => 0.0,
];

foreach ([1 => 'ozet_m1', 2 => 'ozet_m2'] as $mus => $anahtar) {
    foreach ((new MakbuzModel())->musavirOzeti($yil, $mus) as $r) {
        if ((int) $r['musavir_id'] === $mus) {
            $baseline[$anahtar . '_ucret']   = (float) $r['ucret'];
            $baseline[$anahtar . '_kesilen'] = (float) $r['kesilen'];
        }
    }
}

$musavirler = $db->table('musavirler')->select('id')->orderBy('id')->get()->getResultArray();
$ids        = array_map(static fn ($r) => (int) $r['id'], $musavirler);
t('En az iki müşavir var (1 ve 2)', in_array(1, $ids, true) && in_array(2, $ids, true));

$mukModel = new MukellefModel();
$mid = $mukModel->insert([
    'musavir_id'         => 1,
    'unvan'              => 'MDT-TEST ATIKAY LTD ŞTİ',
    'mukellef_tipi'      => 'tuzel',
    'ise_baslama_tarihi' => '2019-01-01',
    'aktif'              => 1,
], true);

t('Fikstür mükellef oluştu', (int) $mid > 0);

$ilk = $model->donemler((int) $mid);
t('Yeni mükellef için ilk dönem otomatik açıldı (müşavir 1, açık uçlu)',
  count($ilk) === 1 && (int) $ilk[0]['musavir_id'] === 1 && $ilk[0]['bitis'] === null,
  json_encode($ilk));

// Mart 2026'da müşavir değişikliği
$hata = $model->musavirDegistir((int) $mid, 2, '2026-03');
t('Mart 2026 müşavir değişikliği hatasız', $hata === null, (string) $hata);

$d = $model->donemler((int) $mid);
t('İki dönem var', count($d) === 2, 'adet: ' . count($d));
t('İlk dönem Şubat sonunda kapandı', ($d[0]['bitis'] ?? null) === '2026-02-28', json_encode($d));
t('İkinci dönem Mart 2026\'dan açık', ($d[1]['baslangic'] ?? null) === '2026-03-01' && $d[1]['bitis'] === null);
t('Güncel müşavir (mukellefler.musavir_id) = 2',
  (int) $db->table('mukellefler')->select('musavir_id')->where('id', $mid)->get()->getRow()->musavir_id === 2);

t('Tarihte müşavir: 2026-02-15 → 1', $model->musavirAt((int) $mid, '2026-02-15') === 1);
t('Tarihte müşavir: 2026-03-01 → 2 (geçiş ayı yeni müşavirin)', $model->musavirAt((int) $mid, '2026-03-01') === 2);

// Aynı müşavire tekrar geçiş: no-op
t('Aynı müşavir tekrar seçilirse değişiklik yok', $model->musavirDegistir((int) $mid, 2, '2026-05') === null
  && count($model->donemler((int) $mid)) === 2);

// Gelecek ay yasak
$gelecek = date('Y-m', strtotime('+1 month'));
t('Gelecek ay değişikliği reddedilir', $model->musavirDegistir((int) $mid, 1, $gelecek) !== null);

// Çakışan manuel dönem reddedilir
$cakisma = $model->donemEkle((int) $mid, 1, '2026-02', '2026-04');
t('Çakışan dönem reddedilir', $cakisma !== null && str_contains($cakisma, 'çakışıyor'), (string) $cakisma);

$ters = $model->donemEkle((int) $mid, 1, '2026-06', '2026-05');
t('Bitiş başlangıçtan önce reddedilir', $ters !== null, (string) $ters);

// -----------------------------------------------------------------
echo "\n=== 3) ÜCRET VE MAKBUZ DAĞILIMI ===\n";

(new MakbuzModel())->ucretYaz((int) $mid, $yil, 12000.0);

$mk = new MakbuzModel();
$makbuzEkle = static function (int $mid, string $tarih, int $kesen, float $brut) use ($db, $yil): void {
    $db->table('makbuzlar')->insert([
        'mukellef_id' => $mid,
        'musavir_id'  => $kesen,
        'yil'         => $yil,
        'ay'          => (int) date('n', strtotime($tarih)),
        'makbuz_no'   => 'MDT-' . $tarih . '-' . $kesen,
        'tarih'       => $tarih,
        'brut'        => $brut,
        'stopaj'      => 0,
        'kdv'         => 0,
        'net'         => $brut,
        'tahsil_edildi' => 0,
    ]);
};
$makbuzEkle((int) $mid, '2026-01-15', 1, 1000);
$makbuzEkle((int) $mid, '2026-02-10', 1, 1000);
$makbuzEkle((int) $mid, '2026-03-05', 2, 1000);

$satirM1 = null;
$satirM2 = null;
$satirToplam = null;

foreach ($mk->cizelge(['yil' => $yil, 'musavir_id' => 1, 'pasif_dahil' => 1]) as $r) {
    if ((int) $r['mukellef_id'] === (int) $mid) { $satirM1 = $r; }
}
foreach ($mk->cizelge(['yil' => $yil, 'musavir_id' => 2, 'pasif_dahil' => 1]) as $r) {
    if ((int) $r['mukellef_id'] === (int) $mid) { $satirM2 = $r; }
}
foreach ($mk->cizelge(['yil' => $yil, 'mukellef_id' => $mid, 'pasif_dahil' => 1]) as $r) {
    $satirToplam = $r;
}

t('Müşavir 1 satırı görünür', $satirM1 !== null);
t('Müşavir 1 hedefi = 12.000 × 2/12 = 2.000', $satirM1 !== null && abs($satirM1['ucret'] - 2000) < 0.01,
  $satirM1 ? (string) $satirM1['ucret'] : 'yok');
t('Müşavir 1 kesilen = 2.000 (Ocak+Şubat, kendi kestiği)', $satirM1 !== null && abs($satirM1['kesilen'] - 2000) < 0.01,
  $satirM1 ? (string) $satirM1['kesilen'] : 'yok');
t('Müşavir 2 hedefi = 12.000 × 10/12 = 10.000', $satirM2 !== null && abs($satirM2['ucret'] - 10000) < 0.01,
  $satirM2 ? (string) $satirM2['ucret'] : 'yok');
t('Müşavir 2 kesilen = 1.000 (yalnız Mart, kendi kestiği)', $satirM2 !== null && abs($satirM2['kesilen'] - 1000) < 0.01,
  $satirM2 ? (string) $satirM2['kesilen'] : 'yok');
t('Tam görünümde ücret toplamı 12.000 (pay değil)', $satirToplam !== null && abs($satirToplam['ucret'] - 12000) < 0.01);
t('Tam görünümde kesilen toplamı 3.000', $satirToplam !== null && abs($satirToplam['kesilen'] - 3000) < 0.01);

// Müşavir durum filtresi payı üzerinden çalışır
$tamamlandi = array_filter($mk->cizelge(['yil' => $yil, 'musavir_id' => 1, 'durum' => 'TAMAM', 'pasif_dahil' => 1]),
    static fn ($r) => (int) $r['mukellef_id'] === (int) $mid);
t('Müşavir 1 için pay (2.000) kesilen (2.000) ile TAMAM görünür', count($tamamlandi) === 1);

$ozetM1 = null;
foreach ($mk->musavirOzeti($yil, 1, true) as $r) { if ((int) $r['musavir_id'] === 1) { $ozetM1 = $r; } }
$ozetM2 = null;
foreach ($mk->musavirOzeti($yil, 2, true) as $r) { if ((int) $r['musavir_id'] === 2) { $ozetM2 = $r; } }

t('Özet: müşavir 1 hedefi +2.000 arttı',
  $ozetM1 !== null && abs($ozetM1['ucret'] - ($baseline['ozet_m1_ucret'] + 2000)) < 0.01,
  $ozetM1 ? (string) $ozetM1['ucret'] : 'yok');
t('Özet: müşavir 1 kesilen +2.000 arttı (kesen bazlı)',
  $ozetM1 !== null && abs($ozetM1['kesilen'] - ($baseline['ozet_m1_kesilen'] + 2000)) < 0.01);
t('Özet: müşavir 2 hedefi +10.000 arttı',
  $ozetM2 !== null && abs($ozetM2['ucret'] - ($baseline['ozet_m2_ucret'] + 10000)) < 0.01,
  $ozetM2 ? (string) $ozetM2['ucret'] : 'yok');
t('Özet: müşavir 2 kesilen +1.000 arttı (Ahmet\'in makbuzu Veli\'ye geçmez)',
  $ozetM2 !== null && abs($ozetM2['kesilen'] - ($baseline['ozet_m2_kesilen'] + 1000)) < 0.01);

// Gelir Vergisi: hasılat kesen bazlı, ücret projeksiyonu pay bazlı
$gv = new GelirVergisiModel();
$gvM1 = $gv->makbuzToplami(1, $yil);
t('GV hasılat (müşavir 1) makbuz kesen bazlı: Ocak+Şubat = 2.000 içinde',
  $gvM1['brut'] >= 2000 - 0.01, (string) $gvM1['brut']);

$ucretM1 = $gv->ucretToplami(1, $yil);
$bulunan = array_values(array_filter($ucretM1['mukellefler'] ?? [], static fn ($r) => (int) $r['mukellef_id'] === (int) $mid));
t('GV ücret projeksiyonu (müşavir 1): fikstür pay 2.000 ile listede',
  count($bulunan) === 1 && abs((float) $bulunan[0]['ucret'] - 2000) < 0.01,
  $bulunan ? json_encode($bulunan[0]) : 'yok');

// -----------------------------------------------------------------
echo "\n=== 4) İÇE AKTARMA: TARİHTEKİ DÖNEM MÜŞAVİRİ ===\n";
t('musavirAt(Şubat)=1, musavirAt(Mart)=2 (içe aktarmada kesen buna göre)',
  $model->musavirAt((int) $mid, '2026-02-20') === 1 && $model->musavirAt((int) $mid, '2026-03-20') === 2);

// -----------------------------------------------------------------
echo "\n=== 5) DÖNEM SİLME → GÜNCEL MÜŞAVİR SENKRONU ===\n";
$ikinci = $model->donemler((int) $mid)[1];
t('Mart dönemi silinir', $model->donemSil((int) $mid, (int) $ikinci['id']) === true);
t('Silinince güncel müşavir geri 1\'e döner',
  (int) $db->table('mukellefler')->select('musavir_id')->where('id', $mid)->get()->getRow()->musavir_id === 1);

// -----------------------------------------------------------------
echo "\n=== 6) TEMİZLİK ===\n";
$db->query("DELETE FROM mukellefler WHERE id = " . (int) $mid);
t('Fikstür silindi (dönem ve makbuzlar kaskad)',
  $db->table('mukellef_musavir_donemleri')->where('mukellef_id', $mid)->countAllResults() === 0
  && $db->table('makbuzlar')->where('mukellef_id', $mid)->countAllResults() === 0);

echo "\n========================================================\n";
echo "GEÇEN: $ok   KALAN: $fail   TOPLAM: " . ($ok + $fail) . "\n";
echo "========================================================\n";

exit($fail > 0 ? 1 : 0);
