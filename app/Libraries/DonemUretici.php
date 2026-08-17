<?php

namespace App\Libraries;

/**
 * DonemUretici
 * =================================================================
 * Bir mükellefin işe başlama / terk tarihine göre HANGİ beyanname
 * dönemlerinin oluşacağını hesaplar.
 *
 * TEMEL KURAL (kesişim kuralı):
 *   Bir dönem satırı ancak mükellefin faaliyet aralığı ile beyanname
 *   döneminin aralığı KESİŞİYORSA oluşur.
 *
 *   faaliyet:  [ise_baslama_tarihi ... terk_tarihi]
 *   dönem   :  [donem_baslangic    ... donem_bitis]
 *   kesişim :  ise_baslama <= donem_bitis  VE  (terk yok VEYA terk >= donem_baslangic)
 *
 * ÖRNEK (kullanıcı senaryosu):
 *   Mükellef 01.03.2026 başladı, 31.03.2026 terk etti.
 *     - KDV1 Ocak/Şubat 2026 .............. YOK  (başlamadan önce)
 *     - KDV1 Mart 2026 .................... VAR
 *     - KDV1 Nisan 2026 ve sonrası ........ YOK  (terk sonrası)
 *     - MUHSGK Mart 2026 .................. VAR, Nisan ........ YOK
 *     - Gelir Geçici 1. Dönem (Oca-Şub-Mar) VAR  (Mart kesişiyor)
 *     - Gelir Geçici 2. Dönem (Nis-May-Haz) YOK  (kesişim yok)  <-- istenen
 *     - Yıllık Gelir Vergisi 2026 ......... VAR  (izleyen yıl Mart'ta verilir)
 * =================================================================
 */
class DonemUretici
{
    protected TatilHesaplayici $tatil;

    /** Aylık dönem adları */
    public const AYLAR = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
        5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
        9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
    ];

    /** Üç aylık dönem etiketleri */
    public const UC_AYLIK_ADLAR = [
        1 => '1. Dönem (Oca-Şub-Mar)',
        2 => '2. Dönem (Nis-May-Haz)',
        3 => '3. Dönem (Tem-Ağu-Eyl)',
        4 => '4. Dönem (Eki-Kas-Ara)',
    ];

    /** Altı aylık dönem etiketleri */
    public const ALTI_AYLIK_ADLAR = [
        1 => '1. Dönem (Oca-Haz)',
        2 => '2. Dönem (Tem-Ara)',
    ];

    public function __construct(?TatilHesaplayici $tatil = null)
    {
        $this->tatil = $tatil ?? new TatilHesaplayici();
    }

    // =================================================================
    //  ANA FONKSİYON
    // =================================================================

    /**
     * Bir mükellefin belirli bir yıl için tüm beyanname dönemlerini üretir.
     *
     * @param array $mukellef   mukellefler tablosu satırı
     * @param array $turler     mükellefe bağlı beyanname türleri (beyanname_turleri + override)
     * @param int   $yil        Üretilecek takvim yılı
     *
     * @return array<int,array> beyanname_takip tablosuna yazılabilir satırlar
     */
    public function uret(array $mukellef, array $turler, int $yil): array
    {
        $satirlar = [];

        $faalBas = substr((string) $mukellef['ise_baslama_tarihi'], 0, 10);
        $faalBit = ! empty($mukellef['terk_tarihi'])
            ? substr((string) $mukellef['terk_tarihi'], 0, 10)
            : null;

        // TAKİP BAŞLANGICI
        // Mükellefin işe başlaması eski olsa da takibi sonradan devraldıysanız,
        // bu tarihten önceki dönemler için satır oluşturulmaz.
        // (Aksi hâlde geçmiş dönemler "gecikmiş" görünür.)
        if (! empty($mukellef['takip_baslangic'])) {
            $takipBas = substr((string) $mukellef['takip_baslangic'], 0, 10);
            $faalBas  = max($faalBas, $takipBas);
        }

        foreach ($turler as $tur) {
            $periyot = $tur['periyot_override'] ?? $tur['periyot'];

            // Tür bazlı özel başlangıç/bitiş (örn. sonradan KDV mükellefi oldu)
            $turBas = ! empty($tur['baslangic_tarihi'])
                ? max($faalBas, substr((string) $tur['baslangic_tarihi'], 0, 10))
                : $faalBas;

            $turBit = $faalBit;
            if (! empty($tur['bitis_tarihi'])) {
                $tb     = substr((string) $tur['bitis_tarihi'], 0, 10);
                $turBit = $turBit === null ? $tb : min($turBit, $tb);
            }

            $donemler = $this->donemleriHesapla($periyot, $yil, $tur);

            foreach ($donemler as $d) {
                // --- KESİŞİM KURALI ---
                if (! $this->kesisiyorMu($turBas, $turBit, $d['baslangic'], $d['bitis'])) {
                    continue;
                }

                $yasal    = $this->sonTarihHesapla($tur, $d);
                $kaydirma = $this->tatil->ilkIsGunu($yasal);

                // ÖDEME SON TARİHİ
                // Bazı yükümlülüklerde beyan/onay ile ödeme tarihi farklıdır.
                // Örn. SGK: bildirge 26'sında onaylanır, primi ay sonunda ödenir.
                // Tür tanımında odeme_offset_ay boşsa beyan tarihi geçerlidir.
                $odemeSon = null;

                if (isset($tur['odeme_offset_ay']) && $tur['odeme_offset_ay'] !== null
                    && $tur['odeme_offset_ay'] !== '') {
                    $odemeYasal = $this->sonTarihHesapla([
                        'son_gun_offset_ay' => (int) $tur['odeme_offset_ay'],
                        'son_gun_tipi'      => $tur['odeme_son_gun_tipi'] ?? 'GUN',
                        'son_gun'           => $tur['odeme_son_gun'] ?? 28,
                    ], $d);

                    $odemeSon = $this->tatil->kaydir($odemeYasal);
                }

                $satirlar[] = [
                    'mukellef_id'       => (int) $mukellef['id'],
                    'beyanname_turu_id' => (int) $tur['id'],
                    'yil'               => $yil,
                    'donem_no'          => $d['no'],
                    'donem_adi'         => $d['ad'] . ' ' . $yil,
                    'donem_baslangic'   => $d['baslangic'],
                    'donem_bitis'       => $d['bitis'],
                    'yasal_son_tarih'   => $yasal,
                    'son_tarih'         => $kaydirma['tarih'],
                    'odeme_son_tarih'   => $odemeSon,
                    'kaydirma_nedeni'   => $kaydirma['kaydirildi'] ? $kaydirma['neden'] : null,
                    'durum'             => 'BEKLIYOR',
                ];
            }
        }

        return $satirlar;
    }

    // =================================================================
    //  KESİŞİM KONTROLÜ  (en kritik kural)
    // =================================================================

    /**
     * Faaliyet aralığı ile dönem aralığı kesişiyor mu?
     *
     * @param string      $faalBas   Faaliyet başlangıcı (Y-m-d)
     * @param string|null $faalBit   Terk tarihi, yoksa null (devam ediyor)
     * @param string      $donemBas  Dönem başlangıcı
     * @param string      $donemBit  Dönem bitişi
     */
    public function kesisiyorMu(string $faalBas, ?string $faalBit, string $donemBas, string $donemBit): bool
    {
        // Mükellef dönem bittikten SONRA başladıysa -> bu dönem oluşmaz
        if ($faalBas > $donemBit) {
            return false;
        }

        // Mükellef dönem başlamadan ÖNCE terk ettiyse -> bu dönem oluşmaz
        if ($faalBit !== null && $faalBit < $donemBas) {
            return false;
        }

        return true;
    }

    // =================================================================
    //  DÖNEM ARALIKLARI
    // =================================================================

    /**
     * Periyoda göre bir yıldaki dönemleri döndürür.
     *
     * @return array<int,array{no:int,ad:string,baslangic:string,bitis:string}>
     */
    public function donemleriHesapla(string $periyot, int $yil, array $tur = []): array
    {
        // "4" gibi atlanacak dönemler (geçici vergi 4. dönem kaldırıldı)
        $atlanan = [];
        if (! empty($tur['atlanan_donemler'])) {
            $atlanan = array_map('intval', array_filter(explode(',', (string) $tur['atlanan_donemler'])));
        }

        $donemler = [];

        switch ($periyot) {
            case 'AYLIK':
                for ($ay = 1; $ay <= 12; $ay++) {
                    if (in_array($ay, $atlanan, true)) {
                        continue;
                    }
                    $donemler[] = [
                        'no'        => $ay,
                        'ad'        => self::AYLAR[$ay],
                        'baslangic' => sprintf('%04d-%02d-01', $yil, $ay),
                        'bitis'     => date('Y-m-t', mktime(0, 0, 0, $ay, 1, $yil)),
                    ];
                }
                break;

            case 'UC_AYLIK':
                for ($d = 1; $d <= 4; $d++) {
                    if (in_array($d, $atlanan, true)) {
                        continue;
                    }
                    $basAy = ($d - 1) * 3 + 1;
                    $bitAy = $basAy + 2;
                    $donemler[] = [
                        'no'        => $d,
                        'ad'        => self::UC_AYLIK_ADLAR[$d],
                        'baslangic' => sprintf('%04d-%02d-01', $yil, $basAy),
                        'bitis'     => date('Y-m-t', mktime(0, 0, 0, $bitAy, 1, $yil)),
                    ];
                }
                break;

            case 'ALTI_AYLIK':
                for ($d = 1; $d <= 2; $d++) {
                    if (in_array($d, $atlanan, true)) {
                        continue;
                    }
                    $basAy = ($d - 1) * 6 + 1;
                    $bitAy = $basAy + 5;
                    $donemler[] = [
                        'no'        => $d,
                        'ad'        => self::ALTI_AYLIK_ADLAR[$d],
                        'baslangic' => sprintf('%04d-%02d-01', $yil, $basAy),
                        'bitis'     => date('Y-m-t', mktime(0, 0, 0, $bitAy, 1, $yil)),
                    ];
                }
                break;

            case 'YILLIK':
            default:
                $donemler[] = [
                    'no'        => 1,
                    'ad'        => 'Yıllık Dönem',
                    'baslangic' => sprintf('%04d-01-01', $yil),
                    'bitis'     => sprintf('%04d-12-31', $yil),
                ];
                break;
        }

        return $donemler;
    }

    // =================================================================
    //  SON TARİH HESABI
    // =================================================================

    /**
     * Kanuni son tarihi hesaplar (tatil kaydırması UYGULANMADAN).
     *
     * Formül: dönem bitiş ayı + son_gun_offset_ay  ->
     *         son_gun_tipi = AY_SONU ise ayın son günü, değilse son_gun.
     *
     * Örnekler:
     *   KDV1 Mart 2026  : bitis 2026-03-31 +1 ay -> 2026-04-28
     *   MUHSGK Mart 2026: bitis 2026-03-31 +1 ay -> 2026-04-26
     *   Gelir Geçici 1. Dönem: bitis 2026-03-31 +2 ay -> 2026-05-17
     *   Yıllık GV 2026  : bitis 2026-12-31 +3 ay -> 2027-03-31 (ay sonu)
     *   Kurumlar 2026   : bitis 2026-12-31 +4 ay -> 2027-04-30 (ay sonu)
     */
    public function sonTarihHesapla(array $tur, array $donem): string
    {
        $bitTs  = strtotime($donem['bitis']);
        $bitYil = (int) date('Y', $bitTs);
        $bitAy  = (int) date('n', $bitTs);

        $offset = (int) ($tur['son_gun_offset_ay'] ?? 1);
        $hedef  = mktime(0, 0, 0, $bitAy + $offset, 1, $bitYil);

        $hYil = (int) date('Y', $hedef);
        $hAy  = (int) date('n', $hedef);

        if (($tur['son_gun_tipi'] ?? 'GUN') === 'AY_SONU') {
            return date('Y-m-t', $hedef);
        }

        $gun    = (int) ($tur['son_gun'] ?? 28);
        $ayGunu = (int) date('t', $hedef);
        $gun    = min($gun, $ayGunu); // Şubat 30 gibi hataları engelle

        return sprintf('%04d-%02d-%02d', $hYil, $hAy, $gun);
    }

    // =================================================================
    //  YARDIMCI: Bir mükellefin bir yıl içinde aktif olduğu aylar
    //  (Evrak takip çizelgesi bu listeyi kullanır)
    // =================================================================

    /**
     * @return int[] Aktif ay numaraları (1-12)
     */
    public function aktifAylar(array $mukellef, int $yil): array
    {
        $faalBas = substr((string) $mukellef['ise_baslama_tarihi'], 0, 10);
        $faalBit = ! empty($mukellef['terk_tarihi'])
            ? substr((string) $mukellef['terk_tarihi'], 0, 10)
            : null;

        if (! empty($mukellef['takip_baslangic'])) {
            $faalBas = max($faalBas, substr((string) $mukellef['takip_baslangic'], 0, 10));
        }

        $aylar = [];

        for ($ay = 1; $ay <= 12; $ay++) {
            $bas = sprintf('%04d-%02d-01', $yil, $ay);
            $bit = date('Y-m-t', mktime(0, 0, 0, $ay, 1, $yil));

            if ($this->kesisiyorMu($faalBas, $faalBit, $bas, $bit)) {
                $aylar[] = $ay;
            }
        }

        return $aylar;
    }

    /** Mükellef verilen tarihte faal mi? */
    public function faalMi(array $mukellef, string $tarih): bool
    {
        $t   = substr($tarih, 0, 10);
        $bas = substr((string) $mukellef['ise_baslama_tarihi'], 0, 10);
        $bit = ! empty($mukellef['terk_tarihi']) ? substr((string) $mukellef['terk_tarihi'], 0, 10) : null;

        if (! empty($mukellef['takip_baslangic'])) {
            $bas = max($bas, substr((string) $mukellef['takip_baslangic'], 0, 10));
        }

        if ($t < $bas) {
            return false;
        }

        return ! ($bit !== null && $t > $bit);
    }
}
