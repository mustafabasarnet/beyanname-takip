<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * MALİ MÜŞAVİR AYLIK KDV TABLOSU
 *
 * Mali müşavir her ay için iki rakam girer:
 *   odenen      → o ay vergi dairesine ödenen KDV
 *   indirilecek → o ayki indirilecek KDV
 *
 * İkisinin TOPLAMI, yıl içinde katlanılan vergi yükü sayılır ve gelir
 * vergisi hesabında stopajla birlikte mahsuba girer.
 *
 * Mantık: yıl içinde stopajdan iade doğar ama KDV ödenir. KDV yükü
 * stopajdan büyükse net ödeme, küçükse net iade çıkar.
 */
class KdvModel extends Model
{
    protected $table         = 'musavir_kdv';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'musavir_id', 'yil', 'ay', 'odenen', 'indirilecek', 'aciklama',
    ];

    /**
     * Bir müşavirin yıl içindeki 12 aylık KDV çizelgesi.
     * Kaydı olmayan aylar 0 ile doldurulur (tablo hep 12 satır).
     *
     * @return array<int,array{ay:int,odenen:float,indirilecek:float,toplam:float,aciklama:?string,var:bool}>
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

            $odenen      = $r === null ? 0.0 : (float) $r['odenen'];
            $indirilecek = $r === null ? 0.0 : (float) $r['indirilecek'];

            $out[$ay] = [
                'ay'          => $ay,
                'odenen'      => $odenen,
                'indirilecek' => $indirilecek,
                'toplam'      => round($odenen + $indirilecek, 2),
                'aciklama'    => $r['aciklama'] ?? null,
                'var'         => $r !== null,
            ];
        }

        return $out;
    }

    /**
     * Yıllık KDV toplamı.
     *
     * @return array{odenen:float,indirilecek:float,toplam:float,ay_sayisi:int}
     */
    public function yillikToplam(int $musavirId, int $yil): array
    {
        $r = $this->db->table('musavir_kdv')
            ->select('COALESCE(SUM(odenen),0) AS odenen,
                      COALESCE(SUM(indirilecek),0) AS indirilecek,
                      COUNT(*) AS ay_sayisi')
            ->where('musavir_id', $musavirId)
            ->where('yil', $yil)
            ->get()->getRowArray();

        $odenen      = (float) ($r['odenen'] ?? 0);
        $indirilecek = (float) ($r['indirilecek'] ?? 0);

        return [
            'odenen'      => $odenen,
            'indirilecek' => $indirilecek,
            'toplam'      => round($odenen + $indirilecek, 2),
            'ay_sayisi'   => (int) ($r['ay_sayisi'] ?? 0),
        ];
    }

    /**
     * Bir ayın KDV kaydını yazar (varsa günceller).
     *
     * İki değer de 0 ise kayıt SİLİNİR — boş satır tutmanın anlamı yok,
     * "kaç ay girildi" sayacı da doğru kalır.
     */
    public function ayYaz(int $musavirId, int $yil, int $ay, float $odenen, float $indirilecek, ?string $aciklama = null): bool
    {
        if ($ay < 1 || $ay > 12) {
            return false;
        }

        $var = $this->where('musavir_id', $musavirId)
            ->where('yil', $yil)->where('ay', $ay)->first();

        if ($odenen == 0.0 && $indirilecek == 0.0 && ($aciklama === null || $aciklama === '')) {
            if ($var !== null) {
                $this->delete($var['id']);
            }

            return true;
        }

        $veri = [
            'musavir_id'  => $musavirId,
            'yil'         => $yil,
            'ay'          => $ay,
            'odenen'      => round($odenen, 2),
            'indirilecek' => round($indirilecek, 2),
            'aciklama'    => $aciklama ?: null,
        ];

        return $var === null
            ? (bool) $this->insert($veri)
            : (bool) $this->update($var['id'], $veri);
    }

    /**
     * 12 ayı birden yazar (tablo formu tek gönderimde kaydedilir).
     *
     * @param array $aylar [ay => ['odenen'=>..,'indirilecek'=>..,'aciklama'=>..]]
     *
     * @return int Yazılan/silinen ay sayısı
     */
    public function topluYaz(int $musavirId, int $yil, array $aylar): int
    {
        $sayac = 0;

        for ($ay = 1; $ay <= 12; $ay++) {
            if (! isset($aylar[$ay])) {
                continue;
            }

            $a = $aylar[$ay];

            $this->ayYaz(
                $musavirId,
                $yil,
                $ay,
                (float) ($a['odenen'] ?? 0),
                (float) ($a['indirilecek'] ?? 0),
                $a['aciklama'] ?? null
            );

            $sayac++;
        }

        return $sayac;
    }

    /** Bir yılın KDV kayıtlarını başka yıla kopyalar (nadiren gerekir) */
    public function yilSil(int $musavirId, int $yil): int
    {
        return $this->where('musavir_id', $musavirId)->where('yil', $yil)->delete() ? 1 : 0;
    }
}
