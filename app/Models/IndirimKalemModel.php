<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * İNDİRİM KALEMLERİ — belge belge liste
 *
 * Mali müşavir eğitim-sağlık harcamalarını ve şahıs/hayat sigorta primlerini
 * tek tutar yerine satır satır girer: tarih · tür · açıklama · tutar.
 *
 * Listenin toplamı gelir vergisi hesabına aktarılır; mevzuat sınırı
 * (GVK 89/1 %15, 89/2 %10) hesap aşamasında ayrıca uygulanır.
 *
 * ÖNCELİK: liste doluysa liste toplamı, boşsa elle girilen tutar kullanılır
 * (bkz. GelirVergisiModel::hesapla).
 */
class IndirimKalemModel extends Model
{
    protected $table         = 'musavir_indirim_kalem';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'musavir_id', 'yil', 'kalem', 'tur', 'tarih', 'aciklama', 'tutar', 'kaydeden_id',
    ];

    /** Kalem türleri */
    public const KALEMLER = [
        'egitim_saglik' => 'Eğitim ve Sağlık Harcaması',
        'sigorta'       => 'Şahıs / Hayat Sigorta Primi',
    ];

    /** Alt tür seçenekleri — kaleme göre değişir */
    public const TURLER = [
        'egitim_saglik' => [
            'egitim' => 'Eğitim',
            'saglik' => 'Sağlık',
            'diger'  => 'Diğer',
        ],
        'sigorta' => [
            'hayat' => 'Hayat Sigortası',
            'sahis' => 'Şahıs Sigortası',
            'diger' => 'Diğer',
        ],
    ];

    protected $validationRules = [
        'musavir_id' => 'required|is_natural_no_zero',
        'yil'        => 'required|is_natural_no_zero',
        'tarih'      => 'required|valid_date[Y-m-d]',
        'tutar'      => 'required|decimal',
    ];

    protected $validationMessages = [
        'tarih' => ['required' => 'Harcama tarihi zorunludur.'],
        'tutar' => ['required' => 'Tutar zorunludur.'],
    ];

    /** Geçerli kalem adı mı? */
    public static function kalemGecerli(?string $kalem): string
    {
        return isset(self::KALEMLER[$kalem]) ? $kalem : 'egitim_saglik';
    }

    /** Kaleme uygun tür mü? Değilse ilk seçenek döner. */
    public static function turGecerli(string $kalem, ?string $tur): string
    {
        $secenek = self::TURLER[$kalem] ?? self::TURLER['egitim_saglik'];

        return isset($secenek[$tur]) ? $tur : array_key_first($secenek);
    }

    /**
     * Bir müşavirin yıl içindeki kalemleri (tarihe göre sıralı).
     *
     * @param string|null $kalem null = her iki kalem birlikte
     */
    public function listele(int $musavirId, int $yil, ?string $kalem = null): array
    {
        $b = $this->where('musavir_id', $musavirId)->where('yil', $yil);

        if ($kalem !== null) {
            $b->where('kalem', $kalem);
        }

        $rows = $b->orderBy('tarih', 'ASC')->orderBy('id', 'ASC')->findAll();

        foreach ($rows as &$r) {
            $r['tutar'] = (float) $r['tutar'];
        }

        unset($r);

        return $rows;
    }

    /**
     * Bir kalemin yıllık toplamı ve satır sayısı.
     *
     * @return array{toplam:float, adet:int, turler:array<string,float>}
     */
    public function toplam(int $musavirId, int $yil, string $kalem): array
    {
        $r = $this->db->table('musavir_indirim_kalem')
            ->select('COALESCE(SUM(tutar),0) AS toplam, COUNT(*) AS adet')
            ->where('musavir_id', $musavirId)
            ->where('yil', $yil)
            ->where('kalem', $kalem)
            ->get()->getRowArray();

        // Tür bazında kırılım (rapor / yazdırma için)
        $turler = [];

        foreach ($this->db->table('musavir_indirim_kalem')
            ->select('tur, COALESCE(SUM(tutar),0) AS tutar')
            ->where('musavir_id', $musavirId)
            ->where('yil', $yil)
            ->where('kalem', $kalem)
            ->groupBy('tur')
            ->get()->getResultArray() as $t) {
            $turler[$t['tur']] = (float) $t['tutar'];
        }

        return [
            'toplam' => (float) ($r['toplam'] ?? 0),
            'adet'   => (int) ($r['adet'] ?? 0),
            'turler' => $turler,
        ];
    }

    /**
     * Her iki kalemin toplamı tek çağrıda (hesap motoru bunu kullanır).
     *
     * @return array{egitim_saglik:array, sigorta:array}
     */
    public function toplamlar(int $musavirId, int $yil): array
    {
        $out = [];

        foreach (array_keys(self::KALEMLER) as $k) {
            $out[$k] = $this->toplam($musavirId, $yil, $k);
        }

        return $out;
    }

    /** Tek satır ekler/günceller */
    public function kalemKaydet(array $veri, ?int $id = null)
    {
        $veri['kalem'] = self::kalemGecerli($veri['kalem'] ?? null);
        $veri['tur']   = self::turGecerli($veri['kalem'], $veri['tur'] ?? null);

        // Yıl verilmemişse tarihten türetilir
        if (empty($veri['yil']) && ! empty($veri['tarih'])) {
            $veri['yil'] = (int) date('Y', strtotime($veri['tarih']));
        }

        return $id !== null && $id > 0 ? $this->update($id, $veri) : $this->insert($veri);
    }

    /** Bir yılın kalemlerini başka yıla kopyalar (yinelenen harcamalar için) */
    public function yilKopyala(int $musavirId, int $kaynak, int $hedef, string $kalem): int
    {
        $satirlar = $this->listele($musavirId, $kaynak, $kalem);

        if ($satirlar === []) {
            return 0;
        }

        $ek  = [];
        $now = date('Y-m-d H:i:s');

        foreach ($satirlar as $s) {
            // Tarih hedef yıla kaydırılır, gün/ay korunur
            $tarih = date('Y-m-d', strtotime($s['tarih']));
            $yeni  = $hedef . substr($tarih, 4);

            // 29 Şubat gibi geçersiz tarihler ay sonuna çekilir
            if (! checkdate((int) substr($yeni, 5, 2), (int) substr($yeni, 8, 2), $hedef)) {
                $yeni = date('Y-m-t', strtotime($hedef . substr($tarih, 4, 3) . '-01'));
            }

            $ek[] = [
                'musavir_id' => $musavirId,
                'yil'        => $hedef,
                'kalem'      => $s['kalem'],
                'tur'        => $s['tur'],
                'tarih'      => $yeni,
                'aciklama'   => $s['aciklama'],
                'tutar'      => $s['tutar'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->db->table('musavir_indirim_kalem')->insertBatch($ek);

        return count($ek);
    }
}
