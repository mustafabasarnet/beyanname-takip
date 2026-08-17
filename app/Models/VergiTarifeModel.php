<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * GELİR VERGİSİ TARİFESİ (GVK md.103)
 *
 * Dilimler YIL BAZINDA tutulur; tarife her yıl yeniden açıklandığı için
 * geçmiş yılların hesabı sonradan bozulmaz.
 *
 * Bir dilim satırı şunu ifade eder:
 *   "TAVAN TL'nin TABAN TL'si için SABIT_VERGI TL, fazlası %ORAN"
 *
 * Son dilimde tavan NULL'dur (üst sınırsız).
 */
class VergiTarifeModel extends Model
{
    protected $table         = 'vergi_tarifeleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'yil', 'ucret_mi', 'sira', 'taban', 'tavan', 'sabit_vergi', 'oran',
    ];

    /**
     * Bir yılın dilimleri (sıralı).
     *
     * @param bool $ucret false = ücret dışı gelirler (serbest meslek buraya girer)
     */
    public function dilimler(int $yil, bool $ucret = false): array
    {
        $rows = $this->where('yil', $yil)
            ->where('ucret_mi', $ucret ? 1 : 0)
            ->orderBy('sira', 'ASC')
            ->findAll();

        foreach ($rows as &$r) {
            $r['taban']       = (float) $r['taban'];
            $r['tavan']       = $r['tavan'] === null ? null : (float) $r['tavan'];
            $r['sabit_vergi'] = (float) $r['sabit_vergi'];
            $r['oran']        = (float) $r['oran'];
        }

        unset($r);

        return $rows;
    }

    /** Tarifesi tanımlı yıllar (azalan) */
    public function tanimliYillar(): array
    {
        $rows = $this->db->table('vergi_tarifeleri')
            ->select('DISTINCT yil', false)
            ->orderBy('yil', 'DESC')
            ->get()->getResultArray();

        return array_map('intval', array_column($rows, 'yil'));
    }

    public function tarifeVarMi(int $yil, bool $ucret = false): bool
    {
        return $this->where('yil', $yil)->where('ucret_mi', $ucret ? 1 : 0)->countAllResults() > 0;
    }

    /**
     * MATRAHTAN VERGİ HESABI (artan oranlı tarife)
     *
     * Dilim satırı hazır "sabit_vergi" taşıdığı için hesap tek adımdır:
     * matrahın düştüğü dilim bulunur, sabit vergiye tabanı aşan kısmın
     * oranı eklenir. Bu, GİB'in tarife metniyle birebir aynı sonucu verir.
     *
     * @return array{vergi:float, dilim:?array, dilim_no:int, kirilim:array}
     *   kirilim: her dilimde ne kadar matrah kaldığı ve ne vergi doğduğu
     *            (ekranda gösterim için; toplamı 'vergi' ile aynı olmalıdır)
     */
    public function vergiHesapla(float $matrah, int $yil, bool $ucret = false): array
    {
        $bos = ['vergi' => 0.0, 'dilim' => null, 'dilim_no' => 0, 'kirilim' => [], 'tarife_var' => false];

        if ($matrah <= 0) {
            $bos['tarife_var'] = $this->tarifeVarMi($yil, $ucret);

            return $bos;
        }

        $dilimler = $this->dilimler($yil, $ucret);

        if ($dilimler === []) {
            return $bos;
        }

        $matrah = round($matrah, 2);
        $vergi  = 0.0;
        $secili = null;
        $sira   = 0;

        foreach ($dilimler as $d) {
            // Matrah bu dilimin tabanını aşıyorsa dilim adaydır; son aday kazanır
            if ($matrah > $d['taban'] || ($d['taban'] <= 0 && $matrah > 0)) {
                if ($d['tavan'] === null || $matrah <= $d['tavan']) {
                    $secili = $d;
                    $sira   = (int) $d['sira'];

                    break;
                }

                $secili = $d;
                $sira   = (int) $d['sira'];
            }
        }

        if ($secili === null) {
            $secili = $dilimler[0];
            $sira   = (int) $secili['sira'];
        }

        $vergi = $secili['sabit_vergi'] + ($matrah - $secili['taban']) * $secili['oran'] / 100;
        $vergi = round($vergi, 2);

        // Kırılım: dilim dilim matrah dağılımı (bilgi amaçlı)
        $kirilim = [];
        $kalan   = $matrah;

        foreach ($dilimler as $d) {
            if ($kalan <= $d['taban']) {
                break;
            }

            $ust    = $d['tavan'] === null ? $matrah : min($matrah, $d['tavan']);
            $tutar  = max(0, $ust - $d['taban']);

            if ($tutar <= 0) {
                continue;
            }

            $kirilim[] = [
                'sira'   => (int) $d['sira'],
                'taban'  => $d['taban'],
                'tavan'  => $d['tavan'],
                'oran'   => $d['oran'],
                'matrah' => round($tutar, 2),
                'vergi'  => round($tutar * $d['oran'] / 100, 2),
            ];
        }

        return [
            'vergi'      => $vergi,
            'dilim'      => $secili,
            'dilim_no'   => $sira,
            'kirilim'    => $kirilim,
            'tarife_var' => true,
        ];
    }

    /**
     * Bir yılın dilimlerini toplu kaydeder (o yılın eski satırları silinir).
     *
     * @param array $satirlar [['taban'=>..,'tavan'=>..,'sabit_vergi'=>..,'oran'=>..], ...]
     *
     * @return int Kaydedilen dilim sayısı
     */
    public function dilimleriYaz(int $yil, bool $ucret, array $satirlar): int
    {
        $this->where('yil', $yil)->where('ucret_mi', $ucret ? 1 : 0)->delete();

        $sira = 0;
        $ek   = [];

        foreach ($satirlar as $s) {
            $oran = (float) ($s['oran'] ?? 0);

            // Oranı boş bırakılan satır "silinmiş" sayılır
            if ($oran <= 0) {
                continue;
            }

            $sira++;
            $tavan = $s['tavan'] ?? null;

            $ek[] = [
                'yil'         => $yil,
                'ucret_mi'    => $ucret ? 1 : 0,
                'sira'        => $sira,
                'taban'       => round((float) ($s['taban'] ?? 0), 2),
                'tavan'       => ($tavan === null || $tavan === '' || (float) $tavan <= 0)
                                    ? null : round((float) $tavan, 2),
                'sabit_vergi' => round((float) ($s['sabit_vergi'] ?? 0), 2),
                'oran'        => $oran,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ];
        }

        if ($ek !== []) {
            $this->db->table('vergi_tarifeleri')->insertBatch($ek);
        }

        return count($ek);
    }

    /**
     * Bir yılın tarifesini başka yıla kopyalar (yeniden değerleme oranıyla).
     *
     * Hedef yılda kayıt varsa DOKUNULMAZ — elle girilmiş resmi tarife
     * yanlışlıkla ezilmesin diye.
     *
     * @param float $oran Artış yüzdesi (0 = birebir kopya)
     *
     * @return array{eklenen:int, atlanan:bool}
     */
    public function tarifeKopyala(int $kaynak, int $hedef, float $oran = 0): array
    {
        $eklenen = 0;

        foreach ([0, 1] as $ucretMi) {
            if ($this->tarifeVarMi($hedef, (bool) $ucretMi)) {
                continue;
            }

            $dilimler = $this->dilimler($kaynak, (bool) $ucretMi);

            if ($dilimler === []) {
                continue;
            }

            $carpan = 1 + $oran / 100;
            $yeni   = [];

            foreach ($dilimler as $d) {
                $yeni[] = [
                    'taban'       => round($d['taban'] * $carpan, 2),
                    'tavan'       => $d['tavan'] === null ? null : round($d['tavan'] * $carpan, 2),
                    'sabit_vergi' => round($d['sabit_vergi'] * $carpan, 2),
                    'oran'        => $d['oran'],
                ];
            }

            $eklenen += $this->dilimleriYaz($hedef, (bool) $ucretMi, $yeni);
        }

        return ['eklenen' => $eklenen, 'atlanan' => $eklenen === 0];
    }
}
