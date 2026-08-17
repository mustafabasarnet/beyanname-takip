<?php

namespace App\Models;

use App\Libraries\DonemUretici;
use CodeIgniter\Model;

class MukellefModel extends Model
{
    protected $table         = 'mukellefler';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $allowedFields = [
        'musavir_id', 'sorumlu_kullanici_id', 'kod', 'unvan', 'mukellef_tipi', 'vergi_kimlik_no', 'tc_kimlik_no',
        'vergi_dairesi', 'defter_tipi',
        // E-defter berat takibi
        'edefter_donem', 'edefter_sorumlu_id', 'edefter_baslangic',
        'genc_girisimci', 'gg_baslangic_yili', 'gg_not',
        // Yıllık/geçici beyannamelerde takip edilen indirim ve kısıtlamalar
        'ind_bagkur', 'ind_bagkur_not',
        'ind_egitim_saglik', 'ind_egitim_saglik_not',
        'ind_finansman', 'ind_finansman_not',
        'faaliyet_konusu', 'nace_kodu', 'sgk_isyeri_sicil',
        'muhasebe_ucreti', 'ucret_aciklama',
        'ise_baslama_tarihi', 'takip_baslangic', 'terk_tarihi', 'terk_nedeni', 'telefon', 'eposta',
        'yetkili_kisi', 'adres', 'notlar', 'aktif',
    ];

    protected $validationRules = [
        'musavir_id'         => 'required|is_natural_no_zero',
        'unvan'              => 'required|min_length[2]|max_length[250]',
        'mukellef_tipi'      => 'required|in_list[gercek,tuzel]',
        'ise_baslama_tarihi' => 'required|valid_date[Y-m-d]',
        'takip_baslangic'    => 'permit_empty|valid_date[Y-m-d]',
        'terk_tarihi'        => 'permit_empty|valid_date[Y-m-d]',
        'muhasebe_ucreti'    => 'permit_empty|decimal|greater_than_equal_to[0]',
        'gg_baslangic_yili'  => 'permit_empty|is_natural_no_zero',
        'vergi_kimlik_no'    => 'permit_empty|numeric|exact_length[10]',
        'tc_kimlik_no'       => 'permit_empty|numeric|exact_length[11]',
        'eposta'             => 'permit_empty|valid_email',
    ];

    protected $validationMessages = [
        'musavir_id'         => ['required' => 'Mali müşavir seçimi zorunludur.'],
        'unvan'              => ['required' => 'Mükellef ünvanı zorunludur.'],
        'ise_baslama_tarihi' => [
            'required'   => 'İşe başlama tarihi zorunludur.',
            'valid_date' => 'Geçerli bir işe başlama tarihi giriniz.',
        ],
        'vergi_kimlik_no' => ['exact_length' => 'Vergi kimlik no 10 haneli olmalıdır.'],
        'muhasebe_ucreti' => [
            'decimal'               => 'Muhasebe ücreti sayısal olmalıdır.',
            'greater_than_equal_to' => 'Muhasebe ücreti negatif olamaz.',
        ],
        'tc_kimlik_no'    => ['exact_length' => 'TC kimlik no 11 haneli olmalıdır.'],
    ];

    // -----------------------------------------------------------------
    //  Listeleme
    // -----------------------------------------------------------------

    /**
     * Yetkiye göre filtrelenmiş mükellef listesi.
     *
     * @param array $filtre ['musavir_id'=>, 'q'=>, 'durum'=>'aktif|terk|pasif', 'tip'=>]
     */
    /**
     * Yetkiye göre filtrelenmiş mükellef listesi.
     *
     * @param array $filtre ['musavir_id'=>, 'q'=>, 'durum'=>'aktif|terk|pasif', 'tip'=>,
     *                       'harf'=>'A', 'limit'=>100, 'ofset'=>0]
     */
    public function listele(array $filtre = []): array
    {
        $b = $this->select('mukellefler.*, musavirler.ad_soyad as musavir_adi,
                            musavirler.renk as musavir_renk, k.ad_soyad as sorumlu_adi')
            ->join('musavirler', 'musavirler.id = mukellefler.musavir_id', 'left')
            ->join('kullanicilar k', 'k.id = mukellefler.sorumlu_kullanici_id', 'left');

        $this->listeKosullari($b, $filtre);

        $b->orderBy('mukellefler.unvan', 'ASC');

        // Sayfalama: limit verilmişse yalnızca o parça çekilir
        if (! empty($filtre['limit'])) {
            return $b->findAll((int) $filtre['limit'], (int) ($filtre['ofset'] ?? 0));
        }

        return $b->findAll();
    }

    /** Filtreye uyan toplam mükellef sayısı (sayfalama için) */
    public function listeSayisi(array $filtre = []): int
    {
        $b = $this->builder();
        $this->listeKosullari($b, $filtre);

        // Soft delete koşulunu elle ekliyoruz (builder() model kapsamı dışında)
        $b->where('mukellefler.deleted_at', null);

        return (int) $b->countAllResults();
    }

    /**
     * Alfabe şeridi için harf dağılımı: ['A' => 12, 'B' => 5, ...]
     * Harf filtresi HARİÇ diğer filtreler uygulanır ki şerit tutarlı kalsın.
     */
    public function harfDagilimi(array $filtre = []): array
    {
        unset($filtre['harf']);

        // İlk harf ikili (binary) olarak alınır ki Ş ile S ayrı sayılsın
        $b = $this->builder()
            ->select('CONVERT(LEFT(mukellefler.unvan, 1) USING utf8mb4)
                      COLLATE utf8mb4_bin AS harf, COUNT(*) AS adet', false);

        $this->listeKosullari($b, $filtre);
        $b->where('mukellefler.deleted_at', null);

        $rows = $b->groupBy('harf')->get()->getResultArray();

        $out = [];

        foreach ($rows as $r) {
            $h = $this->harfNormalle((string) $r['harf']);
            $out[$h] = ($out[$h] ?? 0) + (int) $r['adet'];
        }

        return $out;
    }

    /**
     * Türkçe alfabe. Sayı veya sembolle başlayan ünvanlar '#' altında toplanır.
     */
    public const ALFABE = [
        'A', 'B', 'C', 'Ç', 'D', 'E', 'F', 'G', 'Ğ', 'H', 'I', 'İ', 'J', 'K', 'L',
        'M', 'N', 'O', 'Ö', 'P', 'R', 'S', 'Ş', 'T', 'U', 'Ü', 'V', 'Y', 'Z', '#',
    ];

    /** Bir harfi Türkçe alfabedeki karşılığına indirger */
    protected function harfNormalle(string $h): string
    {
        $h = trim($h);

        if ($h === '') {
            return '#';
        }

        $h = mb_substr($h, 0, 1, 'UTF-8');

        // Türkçe büyütme: i→İ ve ı→I (mb_strtoupper bunu yanlış yapar)
        $h = str_replace(
            ['i', 'ı', 'ş', 'ğ', 'ü', 'ö', 'ç'],
            ['İ', 'I', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'],
            $h
        );

        $h = mb_strtoupper($h, 'UTF-8');

        return in_array($h, self::ALFABE, true) ? $h : '#';
    }

    /**
     * Ünvanın ilk harfi için ikili (binary) karşılaştırma koşulu.
     * Türkçe Ş/S, İ/I, Ğ/G, Ü/U, Ö/O, Ç/C ayrımını korur.
     */
    protected function harfKosulu(string $harf): string
    {
        $db     = $this->db;
        $buyuk  = $db->escape($harf);
        $kucuk  = $db->escape($this->kucult($harf));
        $ilk    = 'CONVERT(LEFT(mukellefler.unvan, 1) USING utf8mb4) COLLATE utf8mb4_bin';

        return '(' . $ilk . ' = ' . $buyuk . ' OR ' . $ilk . ' = ' . $kucuk . ')';
    }

    /** Alfabe dışı (sayı, sembol) karakterle başlayanlar */
    protected function harfDisiKosulu(): string
    {
        $parcalar = [];

        foreach (self::ALFABE as $h) {
            if ($h === '#') {
                continue;
            }

            $parcalar[] = 'NOT ' . $this->harfKosulu($h);
        }

        return '(' . implode(' AND ', $parcalar) . ')';
    }

    /** Türkçe küçültme (I→ı, İ→i dahil) */
    protected function kucult(string $h): string
    {
        $h = str_replace(['I', 'İ', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'], ['ı', 'i', 'ş', 'ğ', 'ü', 'ö', 'ç'], $h);

        return mb_strtolower($h, 'UTF-8');
    }

    /** listele() / listeSayisi() / harfDagilimi() ortak WHERE koşulları */
    protected function listeKosullari($b, array $filtre): void
    {
        // musavir_id: tek ID veya ID dizisi olabilir (çoklu müşavir erişimi)
        if (! empty($filtre['musavir_id'])) {
            if (is_array($filtre['musavir_id'])) {
                $b->whereIn('mukellefler.musavir_id', array_map('intval', $filtre['musavir_id']));
            } else {
                $b->where('mukellefler.musavir_id', (int) $filtre['musavir_id']);
            }
        }

        if (! empty($filtre['genc_girisimci'])) {
            // İstisna yalnızca gerçek kişilerde geçerli (GVK mük. 20);
            // eski veride yanlış işaretlenmiş tüzel kayıt varsa listelenmez.
            $b->where('mukellefler.genc_girisimci', 1)
              ->where('mukellefler.mukellef_tipi', 'gercek');
        }

        if (! empty($filtre['sorumlu_kullanici_id'])) {
            $b->where('mukellefler.sorumlu_kullanici_id', (int) $filtre['sorumlu_kullanici_id']);
        }

        if (! empty($filtre['tip'])) {
            $b->where('mukellefler.mukellef_tipi', $filtre['tip']);
        }

        // Alfabe şeridi
        //
        // ÖNEMLİ: Tablo utf8mb4_unicode_ci olduğu için varsayılan LIKE
        // Ş=S, İ=I, Ğ=G sayar; "Ş" seçilince "S" ile başlayanlar da gelirdi.
        // Bu yüzden ilk harf utf8mb4_bin ile karşılaştırılır. Büyük/küçük
        // ayrımını kaybetmemek için her iki biçim de kontrol edilir.
        if (! empty($filtre['harf'])) {
            $harf = $this->harfNormalle((string) $filtre['harf']);

            if ($harf === '#') {
                // Alfabe dışı (sayı/sembol) ile başlayanlar
                $b->where($this->harfDisiKosulu(), null, false);
            } else {
                $b->where($this->harfKosulu($harf), null, false);
            }
        }

        if (! empty($filtre['q'])) {
            $q = trim($filtre['q']);
            $b->groupStart()
                ->like('mukellefler.unvan', $q)
                ->orLike('mukellefler.vergi_kimlik_no', $q)
                ->orLike('mukellefler.tc_kimlik_no', $q)
                ->orLike('mukellefler.kod', $q)
              ->groupEnd();
        }

        $durum = $filtre['durum'] ?? 'aktif';

        if ($durum === 'aktif') {
            $b->where('mukellefler.aktif', 1)
              ->groupStart()->where('mukellefler.terk_tarihi', null)
              ->orWhere('mukellefler.terk_tarihi >=', date('Y-m-d'))->groupEnd();
        } elseif ($durum === 'terk') {
            $b->where('mukellefler.terk_tarihi IS NOT NULL', null, false)
              ->where('mukellefler.terk_tarihi <', date('Y-m-d'));
        } elseif ($durum === 'pasif') {
            $b->where('mukellefler.aktif', 0);
        }
    }

    /** Mükellefin bağlı olduğu beyanname türleri (tür bilgileriyle birlikte) */
    public function beyannameTurleri(int $mukellefId, bool $sadeceAktif = true): array
    {
        $b = $this->db->table('mukellef_beyannameleri mb')
            ->select('bt.*, mb.id as baglanti_id, mb.periyot_override, mb.baslangic_tarihi,
                      mb.bitis_tarihi, mb.aciklama as baglanti_aciklama, mb.aktif as baglanti_aktif')
            ->join('beyanname_turleri bt', 'bt.id = mb.beyanname_turu_id')
            ->where('mb.mukellef_id', $mukellefId);

        if ($sadeceAktif) {
            $b->where('mb.aktif', 1)->where('bt.aktif', 1);
        }

        return $b->orderBy('bt.sira', 'ASC')->get()->getResultArray();
    }

    /** Mükellefe bağlı tür ID listesi */
    public function turIdListesi(int $mukellefId): array
    {
        $rows = $this->db->table('mukellef_beyannameleri')
            ->select('beyanname_turu_id')
            ->where('mukellef_id', $mukellefId)
            ->where('aktif', 1)
            ->get()->getResultArray();

        return array_map('intval', array_column($rows, 'beyanname_turu_id'));
    }

    /**
     * Mükellefin beyanname türü bağlantılarını topluca kaydeder.
     *
     * @param int[] $turIdler
     */
    public function turleriKaydet(int $mukellefId, array $turIdler): void
    {
        $tbl     = $this->db->table('mukellef_beyannameleri');
        $mevcut  = [];
        $rows    = $tbl->where('mukellef_id', $mukellefId)->get()->getResultArray();

        foreach ($rows as $r) {
            $mevcut[(int) $r['beyanname_turu_id']] = $r;
        }

        $turIdler = array_map('intval', $turIdler);
        $now      = date('Y-m-d H:i:s');

        // Ekle / yeniden aktifleştir
        foreach ($turIdler as $tid) {
            if (isset($mevcut[$tid])) {
                if ((int) $mevcut[$tid]['aktif'] === 0) {
                    $tbl->where('id', $mevcut[$tid]['id'])->update(['aktif' => 1, 'updated_at' => $now]);
                }
            } else {
                $tbl->insert([
                    'mukellef_id'       => $mukellefId,
                    'beyanname_turu_id' => $tid,
                    'aktif'             => 1,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }

        // Kaldırılanları pasifleştir
        foreach ($mevcut as $tid => $r) {
            if (! in_array($tid, $turIdler, true) && (int) $r['aktif'] === 1) {
                $tbl->where('id', $r['id'])->update(['aktif' => 0, 'updated_at' => $now]);
            }
        }
    }

    // -----------------------------------------------------------------
    //  Durum / istatistik
    // -----------------------------------------------------------------

    /** Mükellefin bugüne göre durum etiketi */
    public function durumEtiketi(array $m): array
    {
        if ((int) $m['aktif'] === 0) {
            return ['metin' => 'Pasif', 'sinif' => 'gri'];
        }

        if (! empty($m['terk_tarihi'])) {
            return $m['terk_tarihi'] < date('Y-m-d')
                ? ['metin' => 'Terk (' . date('d.m.Y', strtotime($m['terk_tarihi'])) . ')', 'sinif' => 'kirmizi']
                : ['metin' => 'Terk Edecek', 'sinif' => 'turuncu'];
        }

        return ['metin' => 'Faal', 'sinif' => 'yesil'];
    }

    public function istatistik($musavirId = null): array
    {
        $bugun = date('Y-m-d');

        $temel = fn () => $this->builder()->where('deleted_at', null);

        $uygula = static function ($b) use ($musavirId) {
            if (is_array($musavirId)) {
                if ($musavirId !== []) {
                    $b->whereIn('musavir_id', array_map('intval', $musavirId));
                }
            } elseif ($musavirId) {
                $b->where('musavir_id', (int) $musavirId);
            }

            return $b;
        };

        $toplam = $uygula($temel())->countAllResults();

        $faal = $uygula($temel()->where('aktif', 1)
            ->groupStart()->where('terk_tarihi', null)
            ->orWhere('terk_tarihi >=', $bugun)->groupEnd())->countAllResults();

        $terk = $uygula($temel()->where('terk_tarihi IS NOT NULL', null, false)
            ->where('terk_tarihi <', $bugun))->countAllResults();

        $tuzel = $uygula($temel()->where('mukellef_tipi', 'tuzel')->where('aktif', 1))->countAllResults();

        return [
            'toplam' => $toplam,
            'faal'   => $faal,
            'terk'   => $terk,
            'tuzel'  => $tuzel,
            'gercek' => max(0, $faal - $tuzel),
        ];
    }

    /** Mükellefin bir yıl içinde aktif olduğu aylar */
    public function aktifAylar(array $mukellef, int $yil): array
    {
        return (new DonemUretici())->aktifAylar($mukellef, $yil);
    }
}
