<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * GÜNCELLEME LOGLARI (sürüm notları)
 *
 * Yönetici sürüm notu girer (başlık + maddeler); kullanıcı girişte okumadığı
 * güncellemeleri pencere olarak görür. "Okundu" bilgisi KİŞİ BAZLI tutulur
 * (`guncelleme_okundu`), böylece bir kullanıcının okuması diğerini etkilemez.
 *
 * İçerik biçimi — satır başındaki işaret maddenin türünü belirler:
 *     + eklenen        ~ değişen        - kaldırılan        ! düzeltilen
 * İşaretsiz satır "not" türünde normal madde sayılır.
 */
class GuncellemeModel extends Model
{
    protected $table         = 'guncellemeler';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'versiyon', 'tarih', 'baslik', 'icerik', 'aktif', 'olusturan_id',
    ];

    protected $validationRules = [
        'versiyon' => 'required|max_length[20]',
        'tarih'    => 'required|valid_date[Y-m-d]',
        'baslik'   => 'required|max_length[200]',
        'icerik'   => 'required',
    ];

    protected $validationMessages = [
        'versiyon' => ['required' => 'Sürüm etiketi zorunludur.'],
        'tarih'    => ['required' => 'Yayın tarihi zorunludur.'],
        'baslik'   => ['required' => 'Başlık zorunludur.'],
        'icerik'   => ['required' => 'En az bir madde yazmalısınız.'],
    ];

    /** Madde türleri: işaret → [görünen ad, ikon, renk anahtarı] */
    public const TURLER = [
        'eklendi'    => ['işaret' => '+', 'ad' => 'Eklendi',    'ikon' => '✨', 'renk' => 'yesil'],
        'degisti'    => ['işaret' => '~', 'ad' => 'Değişti',    'ikon' => '🔄', 'renk' => 'mavi'],
        'duzeltildi' => ['işaret' => '!', 'ad' => 'Düzeltildi', 'ikon' => '🐞', 'renk' => 'turuncu'],
        'kaldirildi' => ['işaret' => '-', 'ad' => 'Kaldırıldı', 'ikon' => '🗑', 'renk' => 'gri'],
        'not'        => ['işaret' => '',  'ad' => 'Not',        'ikon' => '•',  'renk' => 'gri'],
    ];

    // =================================================================
    //  LİSTE / OKUMA
    // =================================================================

    /**
     * Güncelleme listesi (en yeni üstte).
     *
     * @param bool $sadeceAktif yayında olanlar
     */
    public function liste(bool $sadeceAktif = true, int $limit = 100): array
    {
        $b = $this->orderBy('tarih', 'DESC')->orderBy('id', 'DESC')->limit($limit);

        if ($sadeceAktif) {
            $b->where('aktif', 1);
        }

        $satirlar = $b->findAll();

        foreach ($satirlar as &$s) {
            $s['maddeler'] = self::maddeleriCoz((string) $s['icerik']);
        }
        unset($s);

        return $satirlar;
    }

    /**
     * İçeriği madde listesine çevirir.
     *
     * @return array<int,array{tur:string, metin:string, ad:string, ikon:string, renk:string}>
     */
    public static function maddeleriCoz(string $icerik): array
    {
        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $icerik) as $ham) {
            $satir = trim((string) $ham);

            if ($satir === '') {
                continue;
            }

            $tur = 'not';

            foreach (['+', '~', '!', '-'] as $isaret) {
                if (str_starts_with($satir, $isaret)) {
                    $tur = match ($isaret) {
                        '+' => 'eklendi',
                        '~' => 'degisti',
                        '!' => 'duzeltildi',
                        '-' => 'kaldirildi',
                    };
                    $satir = trim(substr($satir, 1));

                    break;
                }
            }

            if ($satir === '') {
                continue;
            }

            $meta = self::TURLER[$tur];

            $out[] = [
                'tur'   => $tur,
                'metin' => $satir,
                'ad'    => $meta['ad'],
                'ikon'  => $meta['ikon'],
                'renk'  => $meta['renk'],
            ];
        }

        return $out;
    }

    // =================================================================
    //  OKUNDU TAKİBİ (kişi bazlı)
    // =================================================================

    /**
     * Kullanıcının OKUMADIĞI aktif güncellemeler (en yeni üstte).
     * Giriş penceresi ve menü rozeti bunu kullanır.
     */
    public function okunmamislar(int $kullaniciId, int $limit = 10): array
    {
        if ($kullaniciId <= 0) {
            return [];
        }

        $satirlar = $this->db->table('guncellemeler g')
            ->select('g.*')
            ->where('g.aktif', 1)
            ->whereNotIn('g.id', function ($qb) use ($kullaniciId) {
                $qb->select('guncelleme_id')->from('guncelleme_okundu')
                   ->where('kullanici_id', $kullaniciId);
            })
            ->orderBy('g.tarih', 'DESC')
            ->orderBy('g.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        foreach ($satirlar as &$s) {
            $s['maddeler'] = self::maddeleriCoz((string) $s['icerik']);
        }
        unset($s);

        return $satirlar;
    }

    /** Menü rozeti: okunmamış güncelleme sayısı. */
    public function okunmamisSayisi(int $kullaniciId): int
    {
        return count($this->okunmamislar($kullaniciId));
    }

    /**
     * Verilen güncellemeleri kullanıcı için "okundu" işaretler (idempotent).
     *
     * @param int[] $idler
     *
     * @return int eklenen okundu kaydı sayısı
     */
    public function okunduIsaretle(int $kullaniciId, array $idler): int
    {
        if ($kullaniciId <= 0 || $idler === []) {
            return 0;
        }

        $idler = array_values(array_unique(array_map('intval', $idler)));
        $simdi = date('Y-m-d H:i:s');
        $veri  = [];

        foreach ($idler as $id) {
            if ($id > 0) {
                $veri[] = [
                    'kullanici_id'  => $kullaniciId,
                    'guncelleme_id' => $id,
                    'okundu_at'     => $simdi,
                ];
            }
        }

        if ($veri === []) {
            return 0;
        }

        // Zaten okunmuşsa dokunma (mükerrer kayıt oluşmasın)
        return $this->db->table('guncelleme_okundu')->ignore(true)->insertBatch($veri) ? count($veri) : 0;
    }
}
