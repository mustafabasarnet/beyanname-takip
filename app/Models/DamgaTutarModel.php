<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Beyanname türü × yıl bazında sabit damga vergisi tutarları.
 *
 * Tahakkuk tutarları çizelgeye DAMGA HARİÇ girilir; ödeme listesi
 * hesaplanırken buradaki tutar otomatik eklenir.
 */
class DamgaTutarModel extends Model
{
    protected $table         = 'damga_tutarlari';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['beyanname_turu_id', 'yil', 'tutar', 'aciklama'];

    protected $validationRules = [
        'beyanname_turu_id' => 'required|is_natural_no_zero',
        'yil'               => 'required|is_natural_no_zero',
        'tutar'             => 'required|decimal|greater_than_equal_to[0]',
    ];

    protected $validationMessages = [
        'tutar' => [
            'decimal'                => 'Tutar sayısal olmalıdır.',
            'greater_than_equal_to'  => 'Tutar negatif olamaz.',
        ],
    ];

    /** İstek başına önbellek: [yil][turId] => tutar */
    protected static array $onbellek = [];

    /**
     * Belirli yıl için tür bazlı damga tutarları.
     *
     * @return array<int,float> [beyanname_turu_id => tutar]
     */
    public function yilHaritasi(int $yil): array
    {
        if (isset(self::$onbellek[$yil])) {
            return self::$onbellek[$yil];
        }

        $rows   = $this->where('yil', $yil)->findAll();
        $harita = [];

        foreach ($rows as $r) {
            $harita[(int) $r['beyanname_turu_id']] = (float) $r['tutar'];
        }

        return self::$onbellek[$yil] = $harita;
    }

    /**
     * Tek bir tür + yıl için damga tutarı.
     * Tanımlı değilse 0 döner (o türe damga eklenmez).
     */
    public function tutarAl(int $turId, int $yil): float
    {
        return $this->yilHaritasi($yil)[$turId] ?? 0.0;
    }

    /** Yıl bazlı liste (tanım ekranı için, tür bilgisiyle) */
    public function yilListesi(int $yil): array
    {
        $turModel = new BeyannameTuruModel();
        $turler   = $turModel->orderBy('sira', 'ASC')->findAll();
        $harita   = $this->yilHaritasi($yil);

        $liste = [];

        foreach ($turler as $t) {
            $liste[] = [
                'tur_id'  => (int) $t['id'],
                'kod'     => $t['kod'],
                'ad'      => $t['ad'],
                'kisa_ad' => $t['kisa_ad'],
                'renk'    => $t['renk'],
                'periyot' => $t['periyot'],
                'aktif'   => (int) $t['aktif'],
                'tutar'   => $harita[(int) $t['id']] ?? null,
            ];
        }

        return $liste;
    }

    /**
     * Bir yılın tutarlarını topluca kaydeder.
     *
     * @param array<int,mixed> $tutarlar [turId => tutar]
     */
    public function yilKaydet(int $yil, array $tutarlar): int
    {
        $sayac = 0;
        $now   = date('Y-m-d H:i:s');

        foreach ($tutarlar as $turId => $tutar) {
            $turId = (int) $turId;
            $ham   = trim((string) $tutar);

            // Boş bırakılan satır = tanım yok -> kaydı sil
            if ($ham === '') {
                $this->where(['beyanname_turu_id' => $turId, 'yil' => $yil])->delete();

                continue;
            }

            $deger = (float) str_replace(',', '.', str_replace('.', '', $ham));

            if ($deger < 0) {
                continue;
            }

            $mevcut = $this->where(['beyanname_turu_id' => $turId, 'yil' => $yil])->first();

            if ($mevcut === null) {
                $this->insert([
                    'beyanname_turu_id' => $turId,
                    'yil'               => $yil,
                    'tutar'             => $deger,
                ]);
            } else {
                $this->update($mevcut['id'], ['tutar' => $deger, 'updated_at' => $now]);
            }

            $sayac++;
        }

        unset(self::$onbellek[$yil]);

        return $sayac;
    }

    /** Bir yılın tutarlarını başka yıla kopyala (yeni yıl açılışı) */
    public function yilKopyala(int $kaynak, int $hedef): int
    {
        $rows  = $this->where('yil', $kaynak)->findAll();
        $sayac = 0;

        foreach ($rows as $r) {
            $var = $this->where([
                'beyanname_turu_id' => $r['beyanname_turu_id'],
                'yil'               => $hedef,
            ])->first();

            if ($var === null) {
                $this->insert([
                    'beyanname_turu_id' => $r['beyanname_turu_id'],
                    'yil'               => $hedef,
                    'tutar'             => $r['tutar'],
                    'aciklama'          => $r['aciklama'],
                ]);
                $sayac++;
            }
        }

        unset(self::$onbellek[$hedef]);

        return $sayac;
    }

    /** Tanımlı yıllar */
    public function yillar(): array
    {
        $rows = $this->select('DISTINCT yil', false)->orderBy('yil', 'DESC')->findAll();

        return array_map('intval', array_column($rows, 'yil'));
    }
}
