<?php

/**
 * =====================================================================
 *  İŞ MANTIĞI TESTİ (CodeIgniter'sız çalışır)
 *  Çalıştırma:  php tests/mantik_testi.php
 * =====================================================================
 *  Test edilen kurallar:
 *   1) Terk eden mükellefte dönem kesişim kuralı
 *   2) Tatil/hafta sonu -> ilk iş günü kaydırması
 *   3) Beyanname son tarih formülleri
 * =====================================================================
 */

// ---------------------------------------------------------------------
// CodeIgniter bağımlılıklarını sahte (stub) sınıflarla karşıla
// ---------------------------------------------------------------------
namespace Config {
    class Database
    {
        public static array $tatiller = [];
        public static array $ayarlar  = [];

        public static function connect()
        {
            return new FakeDb();
        }
    }

    class FakeDb
    {
        private string $tablo = '';

        public function table(string $t): self
        {
            $this->tablo = $t;

            return $this;
        }

        public function select($x = null): self { return $this; }
        public function where($x = null, $y = null): self { return $this; }
        public function get(): self { return $this; }

        public function getResultArray(): array
        {
            if ($this->tablo === 'tatiller') {
                return Database::$tatiller;
            }

            if ($this->tablo === 'ayarlar') {
                $out = [];
                foreach (Database::$ayarlar as $k => $v) {
                    $out[] = ['anahtar' => $k, 'deger' => $v];
                }

                return $out;
            }

            return [];
        }
    }
}

namespace App\Libraries {
    require_once __DIR__ . '/../app/Libraries/TatilHesaplayici.php';
    require_once __DIR__ . '/../app/Libraries/DonemUretici.php';
}

namespace {

    use App\Libraries\DonemUretici;
    use App\Libraries\TatilHesaplayici;

    // ---------------- Test altyapısı ----------------
    $GLOBALS['gecen'] = 0;
    $GLOBALS['kalan'] = 0;

    function baslik(string $s): void
    {
        echo "\n\033[1;36m" . str_repeat('=', 74) . "\n  {$s}\n" . str_repeat('=', 74) . "\033[0m\n";
    }

    function kontrol(string $ad, $beklenen, $gercek): void
    {
        $ok = $beklenen === $gercek;
        $GLOBALS[$ok ? 'gecen' : 'kalan']++;

        $isaret = $ok ? "\033[0;32m  ✓\033[0m" : "\033[0;31m  ✗\033[0m";
        echo $isaret . ' ' . str_pad($ad, 56);

        if ($ok) {
            echo "\033[0;90m" . var_export($gercek, true) . "\033[0m\n";
        } else {
            echo "\n      \033[0;31mBeklenen: " . var_export($beklenen, true)
               . "  |  Gerçek: " . var_export($gercek, true) . "\033[0m\n";
        }
    }

    // ---------------- Tatil verisini yükle ----------------
    Config\Database::$tatiller = [
        ['tarih' => '2026-01-01', 'ad' => 'Yılbaşı', 'tip' => 'RESMI', 'yarim_gun' => 0],
        ['tarih' => '2026-03-19', 'ad' => 'Ramazan Bayramı Arifesi', 'tip' => 'ARIFE', 'yarim_gun' => 1],
        ['tarih' => '2026-03-20', 'ad' => 'Ramazan Bayramı 1. Gün', 'tip' => 'DINI', 'yarim_gun' => 0],
        ['tarih' => '2026-03-21', 'ad' => 'Ramazan Bayramı 2. Gün', 'tip' => 'DINI', 'yarim_gun' => 0],
        ['tarih' => '2026-03-22', 'ad' => 'Ramazan Bayramı 3. Gün', 'tip' => 'DINI', 'yarim_gun' => 0],
        ['tarih' => '2026-04-23', 'ad' => 'Ulusal Egemenlik ve Çocuk Bayramı', 'tip' => 'RESMI', 'yarim_gun' => 0],
        ['tarih' => '2026-05-01', 'ad' => 'Emek ve Dayanışma Günü', 'tip' => 'RESMI', 'yarim_gun' => 0],
        ['tarih' => '2026-05-19', 'ad' => 'Gençlik ve Spor Bayramı', 'tip' => 'RESMI', 'yarim_gun' => 0],
        ['tarih' => '2026-05-26', 'ad' => 'Kurban Bayramı Arifesi', 'tip' => 'ARIFE', 'yarim_gun' => 1],
        ['tarih' => '2026-05-27', 'ad' => 'Kurban Bayramı 1. Gün', 'tip' => 'DINI', 'yarim_gun' => 0],
        ['tarih' => '2026-05-28', 'ad' => 'Kurban Bayramı 2. Gün', 'tip' => 'DINI', 'yarim_gun' => 0],
        ['tarih' => '2026-05-29', 'ad' => 'Kurban Bayramı 3. Gün', 'tip' => 'DINI', 'yarim_gun' => 0],
        ['tarih' => '2026-05-30', 'ad' => 'Kurban Bayramı 4. Gün', 'tip' => 'DINI', 'yarim_gun' => 0],
        ['tarih' => '2026-10-28', 'ad' => 'Cumhuriyet Bayramı Arifesi', 'tip' => 'ARIFE', 'yarim_gun' => 1],
        ['tarih' => '2026-10-29', 'ad' => 'Cumhuriyet Bayramı', 'tip' => 'RESMI', 'yarim_gun' => 0],
    ];

    Config\Database::$ayarlar = [
        'cumartesi_tatil' => '1', 'pazar_tatil' => '1',
        'arife_tatil_sayilsin' => '1', 'mali_tatil_uygula' => '0',
    ];

    // Beyanname türleri (SQL'deki ile birebir aynı)
    $TURLER = [
        ['id' => 1,  'kod' => 'KDV1_A',       'periyot' => 'AYLIK',      'son_gun_offset_ay' => 1, 'son_gun_tipi' => 'GUN',     'son_gun' => 28, 'atlanan_donemler' => null],
        ['id' => 2,  'kod' => 'KDV1_3A',      'periyot' => 'UC_AYLIK',   'son_gun_offset_ay' => 1, 'son_gun_tipi' => 'GUN',     'son_gun' => 28, 'atlanan_donemler' => null],
        ['id' => 3,  'kod' => 'KDV2',         'periyot' => 'AYLIK',      'son_gun_offset_ay' => 1, 'son_gun_tipi' => 'GUN',     'son_gun' => 21, 'atlanan_donemler' => null],
        ['id' => 4,  'kod' => 'MUHSGK_A',     'periyot' => 'AYLIK',      'son_gun_offset_ay' => 1, 'son_gun_tipi' => 'GUN',     'son_gun' => 26, 'atlanan_donemler' => null],
        ['id' => 5,  'kod' => 'MUHSGK_3A',    'periyot' => 'UC_AYLIK',   'son_gun_offset_ay' => 1, 'son_gun_tipi' => 'GUN',     'son_gun' => 26, 'atlanan_donemler' => null],
        ['id' => 6,  'kod' => 'SGK',          'periyot' => 'AYLIK',      'son_gun_offset_ay' => 1, 'son_gun_tipi' => 'AY_SONU', 'son_gun' => 30, 'atlanan_donemler' => null],
        ['id' => 7,  'kod' => 'YILLIK_GV',    'periyot' => 'YILLIK',     'son_gun_offset_ay' => 3, 'son_gun_tipi' => 'AY_SONU', 'son_gun' => 31, 'atlanan_donemler' => null],
        ['id' => 8,  'kod' => 'KURUMLAR',     'periyot' => 'YILLIK',     'son_gun_offset_ay' => 4, 'son_gun_tipi' => 'AY_SONU', 'son_gun' => 30, 'atlanan_donemler' => null],
        ['id' => 9,  'kod' => 'GELIR_GECICI', 'periyot' => 'UC_AYLIK',   'son_gun_offset_ay' => 2, 'son_gun_tipi' => 'GUN',     'son_gun' => 17, 'atlanan_donemler' => '4'],
        ['id' => 10, 'kod' => 'KURUM_GECICI', 'periyot' => 'UC_AYLIK',   'son_gun_offset_ay' => 2, 'son_gun_tipi' => 'GUN',     'son_gun' => 17, 'atlanan_donemler' => '4'],
        ['id' => 11, 'kod' => 'DAMGA',        'periyot' => 'AYLIK',      'son_gun_offset_ay' => 1, 'son_gun_tipi' => 'GUN',     'son_gun' => 26, 'atlanan_donemler' => null],
        ['id' => 12, 'kod' => 'GEKAP',        'periyot' => 'ALTI_AYLIK', 'son_gun_offset_ay' => 1, 'son_gun_tipi' => 'AY_SONU', 'son_gun' => 31, 'atlanan_donemler' => null],
        ['id' => 13, 'kod' => 'TURIZM',       'periyot' => 'AYLIK',      'son_gun_offset_ay' => 1, 'son_gun_tipi' => 'AY_SONU', 'son_gun' => 31, 'atlanan_donemler' => null],
    ];

    $turAl = static function (array $kodlar) use ($TURLER) {
        return array_values(array_filter($TURLER, static fn ($t) => in_array($t['kod'], $kodlar, true)));
    };

    $tatil   = new TatilHesaplayici();
    $uretici = new DonemUretici($tatil);

    // =================================================================
    baslik('TEST 1 — TATİL / HAFTA SONU KAYDIRMASI (ilk iş günü)');
    // =================================================================

    // 28 Şubat 2026 = Cumartesi -> 2 Mart Pazartesi
    kontrol('28.02.2026 Cmt -> ilk iş günü', '2026-03-02', $tatil->kaydir('2026-02-28'));
    kontrol('  kaydırma nedeni var mı', true, $tatil->ilkIsGunu('2026-02-28')['kaydirildi']);

    // 28 Mart 2026 = Cumartesi -> 30 Mart Pazartesi
    kontrol('28.03.2026 Cmt -> ilk iş günü', '2026-03-30', $tatil->kaydir('2026-03-28'));

    // 28 Haziran 2026 = Pazar -> 29 Haziran Pazartesi
    kontrol('28.06.2026 Paz -> ilk iş günü', '2026-06-29', $tatil->kaydir('2026-06-28'));

    // 28 Mayıs 2026 = Kurban Bayramı 2. gün -> bayram sonrası 1 Haziran Pazartesi
    kontrol('28.05.2026 Kurban Bayramı -> ilk iş günü', '2026-06-01', $tatil->kaydir('2026-05-28'));

    // 26 Mayıs = arife (yarım gün, tatil sayılıyor) -> 1 Haziran
    kontrol('26.05.2026 Arife -> ilk iş günü', '2026-06-01', $tatil->kaydir('2026-05-26'));

    // 28 Nisan 2026 = Salı, normal iş günü -> değişmez
    kontrol('28.04.2026 Salı -> değişmez', '2026-04-28', $tatil->kaydir('2026-04-28'));
    kontrol('  kaydırılmadı', false, $tatil->ilkIsGunu('2026-04-28')['kaydirildi']);

    // 20 Mart 2026 = Ramazan Bayramı 1. gün -> 23 Mart Pazartesi
    kontrol('20.03.2026 Ramazan Bayramı -> ilk iş günü', '2026-03-23', $tatil->kaydir('2026-03-20'));

    // 1 Ocak 2026 = Perşembe yılbaşı -> 2 Ocak Cuma
    kontrol('01.01.2026 Yılbaşı -> ilk iş günü', '2026-01-02', $tatil->kaydir('2026-01-01'));

    // 29 Ekim 2026 = Perşembe Cumhuriyet Bayramı -> 30 Ekim Cuma
    kontrol('29.10.2026 Cumhuriyet Byr -> ilk iş günü', '2026-10-30', $tatil->kaydir('2026-10-29'));

    // =================================================================
    baslik('TEST 2 — SON TARİH FORMÜLLERİ (kaydırma öncesi kanuni tarih)');
    // =================================================================

    $mart = ['baslangic' => '2026-03-01', 'bitis' => '2026-03-31'];
    $q1   = ['baslangic' => '2026-01-01', 'bitis' => '2026-03-31'];
    $yil26 = ['baslangic' => '2026-01-01', 'bitis' => '2026-12-31'];

    kontrol('KDV1 Mart 2026 (+1 ay, 28)',       '2026-04-28', $uretici->sonTarihHesapla($turAl(['KDV1_A'])[0], $mart));
    kontrol('KDV2 Mart 2026 (+1 ay, 21)',       '2026-04-21', $uretici->sonTarihHesapla($turAl(['KDV2'])[0], $mart));
    kontrol('MUHSGK Mart 2026 (+1 ay, 26)',     '2026-04-26', $uretici->sonTarihHesapla($turAl(['MUHSGK_A'])[0], $mart));
    kontrol('DAMGA Mart 2026 (+1 ay, 26)',      '2026-04-26', $uretici->sonTarihHesapla($turAl(['DAMGA'])[0], $mart));
    kontrol('SGK Mart 2026 (+1 ay, ay sonu)',   '2026-04-30', $uretici->sonTarihHesapla($turAl(['SGK'])[0], $mart));
    kontrol('TURIZM Mart 2026 (+1 ay, ay sonu)','2026-04-30', $uretici->sonTarihHesapla($turAl(['TURIZM'])[0], $mart));
    kontrol('Gelir Geçici 1.Dön (+2 ay, 17)',   '2026-05-17', $uretici->sonTarihHesapla($turAl(['GELIR_GECICI'])[0], $q1));
    kontrol('Yıllık GV 2026 (+3 ay, ay sonu)',  '2027-03-31', $uretici->sonTarihHesapla($turAl(['YILLIK_GV'])[0], $yil26));
    kontrol('Kurumlar 2026 (+4 ay, ay sonu)',   '2027-04-30', $uretici->sonTarihHesapla($turAl(['KURUMLAR'])[0], $yil26));

    // =================================================================
    baslik('TEST 3 ★ ANA SENARYO: 01.03.2026 başladı — 31.03.2026 terk etti');
    // =================================================================

    $mukellef = [
        'id'                 => 1,
        'unvan'              => 'TEST ŞAHIS İŞLETMESİ',
        'mukellef_tipi'      => 'gercek',
        'ise_baslama_tarihi' => '2026-03-01',
        'terk_tarihi'        => '2026-03-31',
    ];

    $secilenTurler = $turAl(['KDV1_A', 'MUHSGK_A', 'DAMGA', 'SGK', 'GELIR_GECICI', 'YILLIK_GV']);
    $satirlar2026  = $uretici->uret($mukellef, $secilenTurler, 2026);

    // Kod bazlı gruplama
    $kodMap = array_column($TURLER, 'kod', 'id');
    $grup   = [];
    foreach ($satirlar2026 as $s) {
        $grup[$kodMap[$s['beyanname_turu_id']]][] = $s;
    }

    echo "\n  \033[1m2026 yılı için üretilen dönemler:\033[0m\n";
    foreach ($grup as $kod => $lst) {
        echo '    ' . str_pad($kod, 15) . ': ';
        echo implode(', ', array_map(static fn ($x) => $x['donem_adi'] . ' → ' . date('d.m.Y', strtotime($x['son_tarih'])), $lst)) . "\n";
    }
    echo "\n";

    // --- KDV1: sadece Mart olmalı ---
    kontrol('KDV1 — toplam dönem sayısı (sadece Mart)', 1, count($grup['KDV1_A'] ?? []));
    kontrol('KDV1 — dönem no = 3 (Mart)', 3, (int) ($grup['KDV1_A'][0]['donem_no'] ?? 0));
    kontrol('KDV1 Mart — son tarih 28.04.2026', '2026-04-28', $grup['KDV1_A'][0]['son_tarih'] ?? '');

    // --- MUHSGK: sadece Mart ---
    kontrol('MUHSGK — toplam dönem sayısı (sadece Mart)', 1, count($grup['MUHSGK_A'] ?? []));
    kontrol('MUHSGK Mart — son tarih 27.04.2026 (26=Pazar)', '2026-04-27', $grup['MUHSGK_A'][0]['son_tarih'] ?? '');
    kontrol('  kaydırma nedeni dolu', true, ! empty($grup['MUHSGK_A'][0]['kaydirma_nedeni']));

    // --- DAMGA / SGK: sadece Mart ---
    kontrol('DAMGA — toplam dönem (sadece Mart)', 1, count($grup['DAMGA'] ?? []));
    kontrol('SGK — toplam dönem (sadece Mart)', 1, count($grup['SGK'] ?? []));
    kontrol('SGK Mart — son tarih 30.04.2026', '2026-04-30', $grup['SGK'][0]['son_tarih'] ?? '');

    // --- GEÇİCİ VERGİ: 1. dönem VAR, 2./3. dönem YOK  ★ kullanıcının şartı ---
    $gecici = $grup['GELIR_GECICI'] ?? [];
    $gDonemler = array_map(static fn ($x) => (int) $x['donem_no'], $gecici);
    sort($gDonemler);

    kontrol('★ Geçici Vergi — sadece 1 dönem var', 1, count($gecici));
    kontrol('★ Geçici Vergi — 1. dönem VAR', true, in_array(1, $gDonemler, true));
    kontrol('★ Geçici Vergi — 2. DÖNEM YOK', false, in_array(2, $gDonemler, true));
    kontrol('★ Geçici Vergi — 3. dönem yok', false, in_array(3, $gDonemler, true));
    kontrol('★ Geçici Vergi — 4. dönem yok (kaldırıldı)', false, in_array(4, $gDonemler, true));
    kontrol('Geçici Vergi 1.Dön son tarih 18.05.2026 (17=Paz)', '2026-05-18', $gecici[0]['son_tarih'] ?? '');

    // --- YILLIK GELİR VERGİSİ: 2026 dönemi VAR, izleyen yıl Mart'ta ★ ---
    $gv = $grup['YILLIK_GV'] ?? [];
    kontrol('★ Yıllık Gelir Vergisi — 2026 dönemi VAR', 1, count($gv));
    kontrol('★ Yıllık GV — son tarih 31.03.2027 (izleyen yıl)', '2027-03-31', $gv[0]['son_tarih'] ?? '');
    kontrol('  Yıllık GV — 2027 yılında veriliyor', '2027', substr($gv[0]['son_tarih'] ?? '', 0, 4));

    // --- 2027 yılı: hiçbir dönem oluşmamalı ---
    $satirlar2027 = $uretici->uret($mukellef, $secilenTurler, 2027);
    kontrol('★ 2027 yılı için hiç dönem yok', 0, count($satirlar2027));

    // --- 2025 yılı: hiçbir dönem oluşmamalı ---
    $satirlar2025 = $uretici->uret($mukellef, $secilenTurler, 2025);
    kontrol('2025 yılı için hiç dönem yok (henüz başlamamış)', 0, count($satirlar2025));

    // =================================================================
    baslik('TEST 4 — DİĞER SENARYOLAR');
    // =================================================================

    // Senaryo A: Yıl ortasında başlayan, devam eden mükellef
    $mA = ['id' => 2, 'ise_baslama_tarihi' => '2026-07-15', 'terk_tarihi' => null];
    $sA = $uretici->uret($mA, $turAl(['KDV1_A']), 2026);
    kontrol('A) 15.07.2026 başladı, devam: KDV1 dönem sayısı (Tem-Ara)', 6, count($sA));
    kontrol('A) İlk dönem Temmuz (no=7)', 7, (int) $sA[0]['donem_no']);

    // Senaryo B: Yıl ortasında terk
    $mB = ['id' => 3, 'ise_baslama_tarihi' => '2024-01-01', 'terk_tarihi' => '2026-08-15'];
    $sB = $uretici->uret($mB, $turAl(['KDV1_A']), 2026);
    kontrol('B) 15.08.2026 terk: KDV1 dönem sayısı (Oca-Ağu)', 8, count($sB));
    kontrol('B) Son dönem Ağustos (no=8)', 8, (int) end($sB)['donem_no']);

    // Senaryo B2: terk yılında geçici vergi dönemleri
    $sB2 = $uretici->uret($mB, $turAl(['GELIR_GECICI']), 2026);
    $b2  = array_map(static fn ($x) => (int) $x['donem_no'], $sB2);
    sort($b2);
    kontrol('B2) 15.08 terk: Geçici vergi dönemleri = [1,2,3]', [1, 2, 3], $b2);

    // Senaryo C: Kurum, tam yıl
    $mC = ['id' => 4, 'ise_baslama_tarihi' => '2020-01-01', 'terk_tarihi' => null];
    $sC = $uretici->uret($mC, $turAl(['KURUM_GECICI', 'KURUMLAR', 'GEKAP']), 2026);
    $cGrup = [];
    foreach ($sC as $s) { $cGrup[$kodMap[$s['beyanname_turu_id']]][] = $s; }
    kontrol('C) Kurum Geçici — 3 dönem (4. kaldırıldı)', 3, count($cGrup['KURUM_GECICI']));
    kontrol('C) Kurumlar Vergisi — 1 dönem', 1, count($cGrup['KURUMLAR']));
    kontrol('C) Kurumlar 2026 son tarih 30.04.2027', '2027-04-30', $cGrup['KURUMLAR'][0]['son_tarih']);
    kontrol('C) GEKAP — 2 dönem (6 aylık)', 2, count($cGrup['GEKAP']));

    // Senaryo D: Tek gün faaliyet (aynı gün başlayıp terk)
    $mD = ['id' => 5, 'ise_baslama_tarihi' => '2026-06-15', 'terk_tarihi' => '2026-06-15'];
    $sD = $uretici->uret($mD, $turAl(['KDV1_A', 'GELIR_GECICI']), 2026);
    $dGrup = [];
    foreach ($sD as $s) { $dGrup[$kodMap[$s['beyanname_turu_id']]][] = $s; }
    kontrol('D) Tek gün faaliyet: KDV1 sadece Haziran', 1, count($dGrup['KDV1_A'] ?? []));
    kontrol('D) Tek gün faaliyet: Geçici vergi sadece 2. dönem', 1, count($dGrup['GELIR_GECICI'] ?? []));
    kontrol('D) 2. dönem numarası doğru', 2, (int) $dGrup['GELIR_GECICI'][0]['donem_no']);

    // Senaryo E: Üç aylık KDV
    $mE = ['id' => 6, 'ise_baslama_tarihi' => '2026-03-01', 'terk_tarihi' => '2026-03-31'];
    $sE = $uretici->uret($mE, $turAl(['KDV1_3A', 'MUHSGK_3A']), 2026);
    kontrol('E) 3 aylık KDV+MUHSGK: sadece 1. dönem (2 satır)', 2, count($sE));
    kontrol('E) 3 Aylık KDV1 son tarih 28.04.2026', '2026-04-28', $sE[0]['son_tarih']);

    // Senaryo F: aktifAylar (evrak çizelgesi)
    kontrol('F) Mart-Mart mükellef aktif ayları', [3], $uretici->aktifAylar($mukellef, 2026));
    kontrol('F) 15.07 başlayan aktif ayları', [7,8,9,10,11,12], $uretici->aktifAylar($mA, 2026));
    kontrol('F) faalMi(2026-03-15) = true', true, $uretici->faalMi($mukellef, '2026-03-15'));
    kontrol('F) faalMi(2026-04-01) = false', false, $uretici->faalMi($mukellef, '2026-04-01'));

    // ---------------- TAKİP BAŞLANGICI ----------------
    baslik('TEST 5 — TAKİP BAŞLANGICI (sonradan devralınan mükellef)');

    // İşe başlama 2024 ama takibi 01.03.2026'da devraldık
    $mT = [
        'id'                 => 9,
        'ise_baslama_tarihi' => '2024-01-01',
        'takip_baslangic'    => '2026-03-01',
        'terk_tarihi'        => null,
    ];

    $sT = $uretici->uret($mT, $turAl(['KDV1_A']), 2026);
    $tNolar = array_map(static fn ($x) => (int) $x['donem_no'], $sT);
    sort($tNolar);

    kontrol('★ 2026: Mart-Aralık arası 10 dönem', 10, count($sT));
    kontrol('★ Ocak-Şubat dönemi YOK', false, in_array(1, $tNolar, true) || in_array(2, $tNolar, true));
    kontrol('  İlk dönem Mart (no=3)', 3, $tNolar[0]);

    // Takip başlangıcından önceki yıl hiç oluşmamalı
    kontrol('★ 2025 yılı hiç dönem yok', 0, count($uretici->uret($mT, $turAl(['KDV1_A']), 2025)));
    kontrol('  2024 yılı hiç dönem yok', 0, count($uretici->uret($mT, $turAl(['KDV1_A']), 2024)));

    // Takip başlangıcı olmadan aynı mükellef
    $mT2 = ['id' => 10, 'ise_baslama_tarihi' => '2024-01-01', 'terk_tarihi' => null];
    kontrol('  Takip başlangıcı yoksa 2025 dolu', 12, count($uretici->uret($mT2, $turAl(['KDV1_A']), 2025)));

    // aktifAylar da takip başlangıcına uymalı (evrak çizelgesi)
    kontrol('★ aktifAylar 2026 = Mart-Aralık', [3,4,5,6,7,8,9,10,11,12], $uretici->aktifAylar($mT, 2026));
    kontrol('  faalMi(2026-02-15) = false', false, $uretici->faalMi($mT, '2026-02-15'));
    kontrol('  faalMi(2026-03-15) = true', true, $uretici->faalMi($mT, '2026-03-15'));

    // Takip başlangıcı + terk birlikte
    $mT3 = [
        'id' => 11, 'ise_baslama_tarihi' => '2020-01-01',
        'takip_baslangic' => '2026-03-01', 'terk_tarihi' => '2026-06-30',
    ];
    kontrol('  Takip 03/2026 + terk 06/2026 -> 4 dönem', 4, count($uretici->uret($mT3, $turAl(['KDV1_A']), 2026)));

    // Kesişim kuralı birim testleri
    kontrol('G) kesisiyorMu: dönem sonrası başlangıç', false,
        $uretici->kesisiyorMu('2026-04-01', null, '2026-01-01', '2026-03-31'));
    kontrol('G) kesisiyorMu: dönem öncesi terk', false,
        $uretici->kesisiyorMu('2020-01-01', '2025-12-31', '2026-01-01', '2026-03-31'));
    kontrol('G) kesisiyorMu: son gün kesişim', true,
        $uretici->kesisiyorMu('2026-03-31', null, '2026-01-01', '2026-03-31'));
    kontrol('G) kesisiyorMu: ilk gün terk', true,
        $uretici->kesisiyorMu('2020-01-01', '2026-01-01', '2026-01-01', '2026-03-31'));

    // =================================================================
    $t = $GLOBALS['gecen'] + $GLOBALS['kalan'];
    echo "\n" . str_repeat('=', 74) . "\n";
    if ($GLOBALS['kalan'] === 0) {
        echo "\033[1;32m  ✓ TÜM TESTLER BAŞARILI  ({$GLOBALS['gecen']}/{$t})\033[0m\n";
    } else {
        echo "\033[1;31m  ✗ {$GLOBALS['kalan']} TEST BAŞARISIZ  (Geçen: {$GLOBALS['gecen']}/{$t})\033[0m\n";
    }
    echo str_repeat('=', 74) . "\n";

    exit($GLOBALS['kalan'] === 0 ? 0 : 1);
}
