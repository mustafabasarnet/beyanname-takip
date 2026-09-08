<?php

namespace App\Libraries;

use App\Libraries\TatilHesaplayici;

/**
 * SİCİL — BİLDİRİM SÜRESİ HESAPLAYICI
 *
 * sicil_bildirim_kurallari.sure_tipi değerlerine göre son bildirim tarihini
 * hesaplar. SÜRELER KODDA SABİT DEĞİLDİR — yalnızca kural tablosundaki
 * değerler okunur; bu sınıf yalnızca "nasıl hesaplanacağını" bilir.
 *
 * Tipler:
 *   GUN / TAKVIM_GUNU : takvim günü ekle (son gün tatilse ilk iş gününe kayar)
 *   IS_GUNU           : yalnız iş günü say (hafta sonu + tatil atlanır)
 *   AY                : takvim ayı ekle (ay sonu taşması son güne çekilir)
 *   BELIRLI_TARIH     : kuraldaki belirli tarih (tatilse ilk iş gününe kayar)
 *
 * Sonuç {son_tarih, asil_tarih, neden} — üretim anında göreve YAZILIR ve
 * saklanır; kural sonradan değişse bile geçmiş görevler bozulmaz.
 */
class SicilSureHesaplayici
{
    /** Kural tablosundaki geçerli süre tipleri */
    public const TIPLER = ['GUN', 'IS_GUNU', 'TAKVIM_GUNU', 'AY', 'BELIRLI_TARIH'];

    protected TatilHesaplayici $tatil;

    public function __construct(?TatilHesaplayici $tatil = null)
    {
        $this->tatil = $tatil ?? new TatilHesaplayici();
    }

    /**
     * Son tarihi hesaplar.
     *
     * @param string      $baslangic   Y-m-d — değişiklik/tescil tarihi
     * @param string      $sureTipi    GUN|IS_GUNU|TAKVIM_GUNU|AY|BELIRLI_TARIH
     * @param int|null    $sureDeger   Gün/ay adedi (BELIRLI_TARIH te NULL)
     * @param string|null $belirliTarih Y-m-d (yalnız BELIRLI_TARIH)
     *
     * @return array{son_tarih:string, asil_tarih:?string, kaydirildi:bool, neden:?string}
     */
    public function hesapla(string $baslangic, string $sureTipi, ?int $sureDeger = null, ?string $belirliTarih = null): array
    {
        $baslangic = substr($baslangic, 0, 10);

        if (! in_array($sureTipi, self::TIPLER, true)) {
            throw new \InvalidArgumentException("Geçersiz süre tipi: {$sureTipi}");
        }

        switch ($sureTipi) {
            case 'GUN':
            case 'TAKVIM_GUNU':
                return $this->takvimGunEkle($baslangic, max(0, (int) ($sureDeger ?? 0)));
            case 'IS_GUNU':
                return $this->isGunuEkle($baslangic, max(0, (int) ($sureDeger ?? 0)));
            case 'AY':
                return $this->ayEkle($baslangic, max(0, (int) ($sureDeger ?? 0)));
            case 'BELIRLI_TARIH':
                $hedef = substr((string) $belirliTarih, 0, 10);

                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hedef)) {
                    throw new \InvalidArgumentException('Belirli tarih türünde hedef tarih gerekir (Y-m-d).');
                }

                return $this->sonuKaydir($hedef, $hedef);
            default:
                throw new \InvalidArgumentException("Süre tipi desteklenmiyor: {$sureTipi}");
        }
    }

    /** N takvim günü ekler; son gün tatilse ilk iş gününe kaydırır. */
    protected function takvimGunEkle(string $baslangic, int $gun): array
    {
        $asil = date('Y-m-d', strtotime($baslangic . " +{$gun} days"));

        return $this->sonuKaydir($asil, $asil);
    }

    /** N iş günü ekler (hafta sonu + resmi tatil atlanır); sonuç hep iş günü. */
    protected function isGunuEkle(string $baslangic, int $gun): array
    {
        $gecerli = $baslangic;
        $kalan   = $gun;

        while ($kalan > 0) {
            $gecerli = date('Y-m-d', strtotime($gecerli . ' +1 day'));

            if ($this->tatil->isGunuMu($gecerli)) {
                $kalan--;
            }
        }

        // Yalnız iş günleri sayıldığı için ek kaydırmaya gerek yok; yine de
        // tutarlılık için sonu iş günü olarak garanti altına al.
        return [
            'son_tarih'   => $gecerli,
            'asil_tarih'  => $gecerli,
            'kaydirildi'  => false,
            'neden'       => "{$gun} iş günü",
        ];
    }

    /** N takvim ayı ekler; ay sonu taşması hedef ayın son gününe çekilir. */
    protected function ayEkle(string $baslangic, int $ay): array
    {
        $ts      = strtotime($baslangic . ' 00:00:00');
        $yil     = (int) date('Y', $ts);
        $mevcutA = (int) date('n', $ts);
        $gun     = (int) date('j', $ts);

        $toplamAy = $mevcutA - 1 + $ay;
        $hedefYil  = $yil + intdiv($toplamAy, 12);
        $hedefAy   = $toplamAy % 12 + 1;
        $sonGun    = (int) date('t', mktime(0, 0, 0, $hedefAy, 1, $hedefYil));

        $asil = sprintf('%04d-%02d-%02d', $hedefYil, $hedefAy, min($gun, $sonGun));

        return $this->sonuKaydir($asil, $asil);
    }

    /** Son gün hafta sonu/tatilse ilk iş gününe kaydırır (neden doldurur). */
    protected function sonuKaydir(string $son, string $asil): array
    {
        $sonuc = $this->tatil->ilkIsGunu($son);

        return [
            'son_tarih'   => $sonuc['tarih'],
            'asil_tarih'  => $asil,
            'kaydirildi'  => $sonuc['kaydirildi'],
            'neden'       => $sonuc['neden'],
        ];
    }
}
