<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * MALİ MÜŞAVİR AYLIK GİDER TABLOSU
 *
 * Mesleki gider ay ay girilir (KDV tablosuyla aynı biçim).
 *
 * TOPLAMA KURALI: liste toplamı, elle girilen "Toplam Mesleki Gider"
 * tutarına EKLENİR (yerine geçmez):
 *
 *     Toplam gider = elle girilen + aylık liste toplamı
 *
 * Böylece giderinin bir kısmını toplu, bir kısmını ay ay tutan büro da
 * doğru sonuç alır.
 */
class AylikGiderModel extends Model
{
    protected $table         = 'musavir_aylik_gider';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['musavir_id', 'yil', 'ay', 'tutar', 'aciklama'];

    /**
     * 12 aylık çizelge. Kaydı olmayan aylar 0 ile doldurulur.
     *
     * @return array<int,array{ay:int,tutar:float,aciklama:?string,var:bool}>
     */
    public function cizelge(int $musavirId, int $yil): array
    {
        $kayitlar = [];

        foreach ($this->where('musavir_id', $musavirId)->where('yil', $yil)->findAll() as $r) {
            $kayitlar[(int) $r['ay']] = $r;
        }

        $out = [];

        for ($ay = 1; $ay <= 12; $ay++) {
            $r = $kayitlar[$ay] ?? null;

            $out[$ay] = [
                'ay'       => $ay,
                'tutar'    => $r === null ? 0.0 : (float) $r['tutar'],
                'aciklama' => $r['aciklama'] ?? null,
                'var'      => $r !== null,
            ];
        }

        return $out;
    }

    /**
     * Yıllık gider toplamı.
     *
     * @return array{toplam:float, ay_sayisi:int}
     */
    public function yillikToplam(int $musavirId, int $yil): array
    {
        $r = $this->db->table('musavir_aylik_gider')
            ->select('COALESCE(SUM(tutar),0) AS toplam, COUNT(*) AS ay_sayisi')
            ->where('musavir_id', $musavirId)
            ->where('yil', $yil)
            ->get()->getRowArray();

        return [
            'toplam'    => (float) ($r['toplam'] ?? 0),
            'ay_sayisi' => (int) ($r['ay_sayisi'] ?? 0),
        ];
    }

    /**
     * Bir ayın kaydını yazar (varsa günceller).
     * Tutar 0 ve açıklama boşsa kayıt SİLİNİR — boş satır tutulmaz.
     */
    public function ayYaz(int $musavirId, int $yil, int $ay, float $tutar, ?string $aciklama = null): bool
    {
        if ($ay < 1 || $ay > 12) {
            return false;
        }

        $var = $this->where('musavir_id', $musavirId)
            ->where('yil', $yil)->where('ay', $ay)->first();

        if ($tutar == 0.0 && ($aciklama === null || $aciklama === '')) {
            if ($var !== null) {
                $this->delete($var['id']);
            }

            return true;
        }

        $veri = [
            'musavir_id' => $musavirId,
            'yil'        => $yil,
            'ay'         => $ay,
            'tutar'      => round($tutar, 2),
            'aciklama'   => $aciklama ?: null,
        ];

        return $var === null
            ? (bool) $this->insert($veri)
            : (bool) $this->update($var['id'], $veri);
    }

    /**
     * 12 ayı birden yazar (tablo tek gönderimde kaydedilir).
     *
     * @param array $aylar [ay => ['tutar'=>..,'aciklama'=>..]]
     */
    public function topluYaz(int $musavirId, int $yil, array $aylar): int
    {
        $sayac = 0;

        for ($ay = 1; $ay <= 12; $ay++) {
            if (! isset($aylar[$ay])) {
                continue;
            }

            $this->ayYaz(
                $musavirId,
                $yil,
                $ay,
                (float) ($aylar[$ay]['tutar'] ?? 0),
                $aylar[$ay]['aciklama'] ?? null
            );

            $sayac++;
        }

        return $sayac;
    }
}
