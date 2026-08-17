<?php

namespace App\Libraries;

use CodeIgniter\I18n\Time;
use Config\Database;

/**
 * TatilHesaplayici
 * -----------------------------------------------------------------
 * Beyanname son gününün hafta sonu / resmi tatil / dini bayram
 * gününe denk gelmesi durumunda, tatil bitimini izleyen İLK İŞ GÜNÜNE
 * kaydırılmasını sağlar. (VUK Md. 18 mantığı)
 *
 * Kullanım:
 *   $t = new TatilHesaplayici();
 *   $sonuc = $t->ilkIsGunu('2026-02-28');
 *   // ['tarih' => '2026-03-02', 'kaydirildi' => true, 'neden' => 'Cumartesi, Pazar']
 */
class TatilHesaplayici
{
    /** @var array<string,array{ad:string,tip:string,yarim_gun:int}> */
    protected array $tatiller = [];

    protected bool $cumartesiTatil     = true;
    protected bool $pazarTatil         = true;
    protected bool $arifeTatilSayilsin = true;
    protected bool $maliTatilUygula    = false;

    /** Sonsuz döngü koruması */
    protected const MAX_ILERLEME = 30;

    public function __construct(?array $ayarlar = null)
    {
        $this->tatilleriYukle();
        $this->ayarlariYukle($ayarlar);
    }

    // -----------------------------------------------------------------
    // Yükleyiciler
    // -----------------------------------------------------------------

    protected function tatilleriYukle(): void
    {
        try {
            $db   = Database::connect();
            $rows = $db->table('tatiller')
                ->select('tarih, ad, tip, yarim_gun')
                ->where('aktif', 1)
                ->get()
                ->getResultArray();

            foreach ($rows as $r) {
                $key = substr((string) $r['tarih'], 0, 10);

                $this->tatiller[$key] = [
                    'ad'        => $r['ad'],
                    'tip'       => $r['tip'],
                    'yarim_gun' => (int) $r['yarim_gun'],
                ];
            }
        } catch (\Throwable $e) {
            // Tablo henüz yoksa sadece hafta sonu kuralı çalışır.
            $this->tatiller = [];
        }
    }

    protected function ayarlariYukle(?array $ayarlar): void
    {
        if ($ayarlar === null) {
            try {
                $db      = Database::connect();
                $rows    = $db->table('ayarlar')->select('anahtar, deger')->get()->getResultArray();
                $ayarlar = array_column($rows, 'deger', 'anahtar');
            } catch (\Throwable $e) {
                $ayarlar = [];
            }
        }

        $this->cumartesiTatil     = (bool) (int) ($ayarlar['cumartesi_tatil']      ?? 1);
        $this->pazarTatil         = (bool) (int) ($ayarlar['pazar_tatil']          ?? 1);
        $this->arifeTatilSayilsin = (bool) (int) ($ayarlar['arife_tatil_sayilsin'] ?? 1);
        $this->maliTatilUygula    = (bool) (int) ($ayarlar['mali_tatil_uygula']    ?? 0);
    }

    // -----------------------------------------------------------------
    // Sorgular
    // -----------------------------------------------------------------

    /** Verilen gün hafta sonu mu? */
    public function haftaSonuMu(string $tarih): bool
    {
        $gun = (int) date('N', strtotime($tarih)); // 1=Pzt ... 7=Paz

        return ($gun === 6 && $this->cumartesiTatil) || ($gun === 7 && $this->pazarTatil);
    }

    /** Verilen gün resmi/dini tatil mi? */
    public function tatilMi(string $tarih): bool
    {
        $key = substr($tarih, 0, 10);

        if (! isset($this->tatiller[$key])) {
            return false;
        }

        $t = $this->tatiller[$key];

        // Yarım gün arifeler ayara göre tatil sayılır/sayılmaz
        if ($t['yarim_gun'] === 1 && ! $this->arifeTatilSayilsin) {
            return false;
        }

        return true;
    }

    /** Tatil ise adını döndürür. */
    public function tatilAdi(string $tarih): ?string
    {
        $key = substr($tarih, 0, 10);

        return $this->tatiller[$key]['ad'] ?? null;
    }

    /** Gün, iş günü mü? (hafta sonu değil + tatil değil) */
    public function isGunuMu(string $tarih): bool
    {
        return ! $this->haftaSonuMu($tarih) && ! $this->tatilMi($tarih);
    }

    /**
     * Tatile denk gelen son günü, tatil bitimini izleyen ilk iş gününe kaydırır.
     *
     * @return array{tarih:string,kaydirildi:bool,neden:?string}
     */
    public function ilkIsGunu(string $tarih): array
    {
        $orijinal = substr($tarih, 0, 10);
        $gecerli  = $orijinal;
        $nedenler = [];
        $adim     = 0;

        while (! $this->isGunuMu($gecerli) && $adim < self::MAX_ILERLEME) {
            $nedenler[] = $this->tatilAdi($gecerli) ?? $this->gunAdi($gecerli);
            $gecerli    = date('Y-m-d', strtotime($gecerli . ' +1 day'));
            $adim++;
        }

        // Mali tatil (1-20 Temmuz) — 5604 sayılı Kanun; opsiyonel
        if ($this->maliTatilUygula) {
            $maliSonuc = $this->maliTatilKaydir($gecerli);

            if ($maliSonuc['tarih'] !== $gecerli) {
                $nedenler[] = 'Mali Tatil (1-20 Temmuz)';
                $gecerli    = $maliSonuc['tarih'];
            }
        }

        return [
            'tarih'      => $gecerli,
            'kaydirildi' => $gecerli !== $orijinal,
            'neden'      => $nedenler === [] ? null : implode(', ', array_unique($nedenler)),
        ];
    }

    /** Sadece kaydırılmış tarihi döndüren kısayol. */
    public function kaydir(string $tarih): string
    {
        return $this->ilkIsGunu($tarih)['tarih'];
    }

    /**
     * Mali tatil kuralı: Son günü 1-20 Temmuz arasına rastlayan beyannameler
     * mali tatilin son gününü izleyen 7. güne uzar (27 Temmuz).
     */
    protected function maliTatilKaydir(string $tarih): array
    {
        $ts  = strtotime($tarih);
        $ay  = (int) date('n', $ts);
        $gun = (int) date('j', $ts);
        $yil = (int) date('Y', $ts);

        if ($ay === 7 && $gun >= 1 && $gun <= 20) {
            $yeni = $this->kaydirBasit($yil . '-07-27');

            return ['tarih' => $yeni];
        }

        return ['tarih' => $tarih];
    }

    /** Mali tatil içinde tekrar mali tatil kontrolü yapmayan basit kaydırma. */
    protected function kaydirBasit(string $tarih): string
    {
        $gecerli = $tarih;
        $adim    = 0;

        while (! $this->isGunuMu($gecerli) && $adim < self::MAX_ILERLEME) {
            $gecerli = date('Y-m-d', strtotime($gecerli . ' +1 day'));
            $adim++;
        }

        return $gecerli;
    }

    /** İki tarih arasındaki iş günü sayısı (rapor/uyarı için). */
    public function isGunuSayisi(string $bas, string $bit): int
    {
        $sayac = 0;
        $g     = substr($bas, 0, 10);
        $son   = substr($bit, 0, 10);

        while ($g <= $son) {
            if ($this->isGunuMu($g)) {
                $sayac++;
            }
            $g = date('Y-m-d', strtotime($g . ' +1 day'));
        }

        return $sayac;
    }

    public function gunAdi(string $tarih): string
    {
        $gunler = [
            1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe',
            5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar',
        ];

        return $gunler[(int) date('N', strtotime($tarih))];
    }

    /** Bir yılın tatillerini dizi olarak verir (takvim görünümü için). */
    public function yilTatilleri(int $yil): array
    {
        $sonuc = [];

        foreach ($this->tatiller as $tarih => $bilgi) {
            if (str_starts_with($tarih, (string) $yil)) {
                $sonuc[$tarih] = $bilgi;
            }
        }

        ksort($sonuc);

        return $sonuc;
    }
}
