<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Kullanıcıya özel, kayıtlı ödeme listeleri.
 *
 * Liste kalıcı bir MÜKELLEF GRUBUDUR (örn. "Mustafa Başar Mükellefleri").
 * Dönem listeye gömülü değildir; liste açılırken yıl/ay seçilir ve tutarlar
 * o döneme göre beyanname tahakkuklarından, özel ödeme kalemlerinden ve
 * (seçiliyse) muhasebe ücretinden güncel olarak hesaplanır.
 *
 * Böylece her ay için yeni liste oluşturmak gerekmez; aynı liste her dönemde
 * yeniden kullanılır.
 */
class OdemeListesiModel extends Model
{
    protected $table         = 'odeme_listeleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'kullanici_id', 'musavir_id', 'ad', 'aciklama',
        'yil', 'ay', 'ucret_dahil', 'ozel_dahil',
    ];

    protected $validationRules = [
        'kullanici_id' => 'required|is_natural_no_zero',
        'ad'           => 'required|min_length[2]|max_length[200]',
        'yil'          => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'ad' => ['required' => 'Liste adı zorunludur.'],
    ];

    // -----------------------------------------------------------------
    //  Listeleme
    // -----------------------------------------------------------------

    /**
     * Kullanıcının görebileceği listeler.
     * Yönetici tüm listeleri görür (denetim), diğerleri yalnızca kendisininkini.
     */
    public function kullaniciListeleri(int $kullaniciId, bool $admin = false): array
    {
        $b = $this->select('odeme_listeleri.*,
                            k.ad_soyad as sahip_adi,
                            mus.ad_soyad as musavir_adi, mus.renk as musavir_renk,
                            (SELECT COUNT(*) FROM odeme_listesi_mukellefleri olm
                              WHERE olm.liste_id = odeme_listeleri.id) as mukellef_sayisi')
            ->join('kullanicilar k', 'k.id = odeme_listeleri.kullanici_id', 'left')
            ->join('musavirler mus', 'mus.id = odeme_listeleri.musavir_id', 'left');

        if (! $admin) {
            $b->where('odeme_listeleri.kullanici_id', $kullaniciId);
        }

        return $b->orderBy('odeme_listeleri.yil', 'DESC')
            ->orderBy('odeme_listeleri.ay', 'DESC')
            ->orderBy('odeme_listeleri.ad', 'ASC')
            ->findAll();
    }

    /** Kullanıcı bu listeye erişebilir mi? */
    public function erisebilirMi(array $liste, int $kullaniciId, bool $admin = false): bool
    {
        return $admin || (int) $liste['kullanici_id'] === $kullaniciId;
    }

    // -----------------------------------------------------------------
    //  Mükellef seçimi
    // -----------------------------------------------------------------

    /** @return int[] Listedeki mükellef ID'leri */
    public function mukellefIdleri(int $listeId): array
    {
        $rows = $this->db->table('odeme_listesi_mukellefleri')
            ->select('mukellef_id')
            ->where('liste_id', $listeId)
            ->orderBy('sira', 'ASC')
            ->get()->getResultArray();

        return array_map('intval', array_column($rows, 'mukellef_id'));
    }

    /**
     * Listedeki mükellefleri topluca kaydeder.
     *
     * @param int[] $mukellefIdler
     */
    public function mukellefleriKaydet(int $listeId, array $mukellefIdler): void
    {
        $tbl = $this->db->table('odeme_listesi_mukellefleri');
        $tbl->where('liste_id', $listeId)->delete();

        $mukellefIdler = array_values(array_unique(array_filter(array_map('intval', $mukellefIdler))));

        if ($mukellefIdler === []) {
            return;
        }

        $satir = [];
        $sira  = 0;

        foreach ($mukellefIdler as $mid) {
            $satir[] = [
                'liste_id'    => $listeId,
                'mukellef_id' => $mid,
                'sira'        => $sira++,
            ];
        }

        $tbl->insertBatch($satir);
    }

    /**
     * Listenin varsayılan dönemi.
     * Kayıtlı değerler boşsa içinde bulunulan yıl/ay kullanılır.
     *
     * @return array{yil:int,ay:int|null}
     */
    public function varsayilanDonem(array $liste): array
    {
        return [
            'yil' => ! empty($liste['yil']) ? (int) $liste['yil'] : (int) date('Y'),
            'ay'  => $liste['ay'] !== null && $liste['ay'] !== '' ? (int) $liste['ay'] : (int) date('n'),
        ];
    }

    // -----------------------------------------------------------------
    //  Hesaplama
    // -----------------------------------------------------------------

    /**
     * Listenin belirtilen DÖNEM için güncel tutarlarını hesaplar.
     *
     * Kaynaklar:
     *   - Onaylanmış beyannamelerin tahakkuk tutarı + damga vergisi
     *   - Özel ödeme kalemleri (liste sahibinin kendi kalemleri)
     *   - İsteğe bağlı muhasebe ücreti
     *
     * @param array    $liste Liste kaydı (mükellef grubu)
     * @param int      $yil   Hesaplanacak yıl
     * @param int|null $ay    Hesaplanacak ay (null = tüm yıl)
     *
     * @return array{satirlar:array,toplam:array}
     */
    public function hesapla(array $liste, int $yil, ?int $ay = null): array
    {
        $mukellefIdler = $this->mukellefIdleri((int) $liste['id']);

        if ($mukellefIdler === []) {
            return [
                'satirlar' => [],
                'toplam'   => ['beyanname' => 0.0, 'ozel' => 0.0, 'ucret' => 0.0, 'genel' => 0.0],
            ];
        }

        $takipModel  = new BeyannameTakipModel();
        $ozelModel   = new OzelOdemeModel();
        $mukModel    = new MukellefModel();

        $ucretDahil = (int) $liste['ucret_dahil'] === 1;
        $ozelDahil  = (int) $liste['ozel_dahil'] === 1;

        // Beyanname tarafı — mevcut ödeme listesi mantığını yeniden kullan
        $sonuc = $takipModel->odemeListesi([
            'yil'         => $yil,
            'ay'          => $ay,
            'mukellef_id' => null,
            'musavir_id'  => null,
            'ozel_atla'   => true,   // özel kalemleri burada değil, aşağıda ekleriz
        ]);

        $beyanHarita = [];

        foreach ($sonuc['gruplar'] as $g) {
            $mid = (int) $g['mukellef']['id'];

            if (! in_array($mid, $mukellefIdler, true)) {
                continue;
            }

            $beyanHarita[$mid] = $g;
        }

        // Özel kalemler (yalnızca liste sahibinin kalemleri)
        $ozelHarita = [];

        if ($ozelDahil) {
            $ozelKayitlar = $ozelModel->listele([
                'yil'         => $yil,
                'ay'          => $ay,
                'kaydeden_id' => (int) $liste['kullanici_id'],
            ]);

            foreach ($ozelKayitlar as $o) {
                $mid = (int) $o['m_id'];

                if (! in_array($mid, $mukellefIdler, true)) {
                    continue;
                }

                $ozelHarita[$mid][] = $o;
            }
        }

        // Satırları listedeki sıraya göre kur
        $satirlar = [];
        $toplam   = ['beyanname' => 0.0, 'ozel' => 0.0, 'ucret' => 0.0, 'genel' => 0.0];

        foreach ($mukellefIdler as $mid) {
            $mukellef = $mukModel->find($mid);

            if ($mukellef === null) {
                continue;
            }

            $grup = $beyanHarita[$mid] ?? null;

            $beyanToplam = 0.0;
            $beyanSatir  = [];

            if ($grup !== null) {
                foreach ($grup['satirlar'] as $bs) {
                    $beyanToplam += (float) $bs['odenecek'];
                    $beyanSatir[] = $bs;
                }
            }

            $ozelToplam = 0.0;
            $ozelSatir  = $ozelHarita[$mid] ?? [];

            foreach ($ozelSatir as $os) {
                $ozelToplam += (float) $os['tutar'];
            }

            $ucret = $ucretDahil ? (float) ($mukellef['muhasebe_ucreti'] ?? 0) : 0.0;
            $genel = $beyanToplam + $ozelToplam + $ucret;

            // Hiç tutarı olmayan mükellefi listeye alma
            if ($genel <= 0 && $beyanSatir === [] && $ozelSatir === []) {
                continue;
            }

            $satirlar[] = [
                'mukellef'    => $mukellef,
                'beyannameler'=> $beyanSatir,
                'ozel'        => $ozelSatir,
                'beyan_top'   => $beyanToplam,
                'ozel_top'    => $ozelToplam,
                'ucret'       => $ucret,
                'genel'       => $genel,
            ];

            $toplam['beyanname'] += $beyanToplam;
            $toplam['ozel']      += $ozelToplam;
            $toplam['ucret']     += $ucret;
            $toplam['genel']     += $genel;
        }

        return ['satirlar' => $satirlar, 'toplam' => $toplam];
    }
}
