<?php

namespace App\Models;

use App\Libraries\DonemUretici;
use App\Libraries\TatilHesaplayici;
use CodeIgniter\Model;

class BeyannameTakipModel extends Model
{
    protected $table         = 'beyanname_takip';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'mukellef_id', 'beyanname_turu_id', 'yil', 'donem_no', 'donem_adi',
        'donem_baslangic', 'donem_bitis', 'yasal_son_tarih', 'son_tarih', 'odeme_son_tarih',
        'kaydirma_nedeni', 'durum', 'tahakkuk_tutari', 'damga_tutari', 'tahakkuk_fis_no',
        'odendi', 'odeme_tarihi',
        'gonderim_tarihi', 'onaylayan_id', 'onay_tarihi', 'not_metni',
    ];

    /**
     * Beyanname durumları.
     * Not: "Gönderildi" kaldırıldı — onaylanan beyanname zaten gönderilmiş
     * kabul edilir, iki ayrı adım gereksiz karmaşa yaratıyordu.
     */
    public const DURUMLAR = [
        'BEKLIYOR'     => 'Bekliyor',
        'HAZIR'        => 'Hazır',
        'ONAYLANDI'    => 'Onaylandı',
        'VERILMEYECEK' => 'Verilmeyecek',
    ];

    /** Ödemeye giren durumlar */
    public const ODENECEK_DURUMLAR = ['ONAYLANDI'];

    // =================================================================
    //  MUHSGK ↔ SGK EŞLEŞMESİ
    //
    //  Sigortalı işçi çalıştıran mükelleflerde Muhtasar ve Prim Hizmet
    //  Beyannamesi (MUHSGK) ile SGK prim bildirgesi AYNI işlemin iki
    //  parçasıdır; ikisi birlikte verilir. Kullanıcı iki ayrı satırda
    //  iki kez onaylayıp iki kez tutar giriyordu.
    //
    //  Artık MUHSGK satırı "ana" kayıttır: onayı ve tahakkuku tek
    //  ekrandan girilir, SGK satırı buna bağlı olarak güncellenir.
    //  SGK satırı çizelgede DURMAYA DEVAM EDER (rozetle işaretlenir);
    //  gizlenmez ki tutarı ve ödeme durumu görünür kalsın.
    // =================================================================

    /** Eşleşmenin "ana" tarafı olan tür kodları */
    public const MUHSGK_KODLARI = ['MUHSGK_A', 'MUHSGK_3A'];

    /** Eşleşmenin "bağlı" tarafı olan tür kodu */
    public const SGK_KODU = 'SGK';

    /**
     * Bir kaydın MUHSGK ile eşleşen SGK kaydını bulur (ya da tersi).
     *
     * Eşleşme ölçütü: AYNI mükellef + AYNI dönem (donem_baslangic / donem_bitis).
     * Üç aylık MUHSGK'da dönem üç aylıktır, SGK ise aylıktır; bu yüzden
     * dönem BAŞLANGICI değil, SGK döneminin MUHSGK dönemine düşmesi
     * aranır. Tek MUHSGK birden çok SGK satırıyla eşleşebilir.
     *
     * @param array $kayit Ana kayıt (MUHSGK ya da SGK satırı)
     *
     * @return array<int,array> Eşleşen karşı kayıtlar (yoksa boş dizi)
     */
    public function esKayitlar(array $kayit): array
    {
        $kod = (string) ($kayit['tur_kodu'] ?? '');

        if ($kod === '') {
            $tur = $this->db->table('beyanname_turleri')
                ->select('kod')->where('id', (int) $kayit['beyanname_turu_id'])
                ->get()->getRowArray();
            $kod = (string) ($tur['kod'] ?? '');
        }

        if (in_array($kod, self::MUHSGK_KODLARI, true)) {
            $aranan = [self::SGK_KODU];
        } elseif ($kod === self::SGK_KODU) {
            $aranan = self::MUHSGK_KODLARI;
        } else {
            return [];
        }

        // Dönem kesişimi: iki dönem aralığı üst üste biniyorsa eşleşir.
        // (Aylık MUHSGK'da birebir aynı ay; üç aylıkta üç SGK satırı.)
        return $this->db->table('beyanname_takip bt')
            ->select('bt.*, t.kod AS tur_kodu, t.kisa_ad AS tur_kisa, t.ad AS tur_ad')
            ->join('beyanname_turleri t', 't.id = bt.beyanname_turu_id')
            ->where('bt.mukellef_id', (int) $kayit['mukellef_id'])
            ->where('bt.id !=', (int) $kayit['id'])
            ->whereIn('t.kod', $aranan)
            ->where('bt.donem_baslangic <=', $kayit['donem_bitis'])
            ->where('bt.donem_bitis >=', $kayit['donem_baslangic'])
            ->orderBy('bt.donem_baslangic', 'ASC')
            ->get()->getResultArray();
    }

    /** Kayıt MUHSGK türlerinden biri mi? */
    public function muhsgkMi(array $kayit): bool
    {
        return in_array((string) ($kayit['tur_kodu'] ?? ''), self::MUHSGK_KODLARI, true);
    }

    /**
     * Verilen kayıtlar için eşleşme haritası üretir (çizelge rozetleri ve
     * tahakkuk penceresi için). N+1 sorgu olmasın diye tek seferde okunur.
     *
     * @param array<int,array> $kayitlar
     *
     * @return array<int,array> [kayit_id => ['rol'=>'ana'|'bagli', 'esler'=>[...]]]
     */
    public function esHarita(array $kayitlar): array
    {
        $ilgili = [];

        foreach ($kayitlar as $k) {
            $kod = (string) ($k['tur_kodu'] ?? '');

            if (in_array($kod, self::MUHSGK_KODLARI, true) || $kod === self::SGK_KODU) {
                $ilgili[] = $k;
            }
        }

        if ($ilgili === []) {
            return [];
        }

        $mukellefler = array_values(array_unique(array_map(
            static fn ($k) => (int) $k['mukellef_id'],
            $ilgili
        )));

        // İlgili mükelleflerin TÜM MUHSGK/SGK satırları tek sorguda alınır
        $tumu = $this->db->table('beyanname_takip bt')
            ->select('bt.id, bt.mukellef_id, bt.beyanname_turu_id, bt.donem_baslangic, bt.donem_bitis,
                      bt.donem_adi, bt.durum, bt.tahakkuk_tutari, bt.damga_tutari,
                      bt.tahakkuk_fis_no, bt.son_tarih, t.kod AS tur_kodu, t.kisa_ad AS tur_kisa')
            ->join('beyanname_turleri t', 't.id = bt.beyanname_turu_id')
            ->whereIn('bt.mukellef_id', $mukellefler)
            ->whereIn('t.kod', array_merge(self::MUHSGK_KODLARI, [self::SGK_KODU]))
            ->orderBy('bt.donem_baslangic', 'ASC')
            ->get()->getResultArray();

        $harita = [];

        foreach ($ilgili as $k) {
            $kod    = (string) $k['tur_kodu'];
            $anaMi  = in_array($kod, self::MUHSGK_KODLARI, true);
            $aranan = $anaMi ? [self::SGK_KODU] : self::MUHSGK_KODLARI;
            $esler  = [];

            foreach ($tumu as $a) {
                if ((int) $a['id'] === (int) $k['id']
                    || (int) $a['mukellef_id'] !== (int) $k['mukellef_id']
                    || ! in_array($a['tur_kodu'], $aranan, true)) {
                    continue;
                }

                // Dönem kesişimi
                if ($a['donem_baslangic'] <= $k['donem_bitis'] && $a['donem_bitis'] >= $k['donem_baslangic']) {
                    $esler[] = $a;
                }
            }

            if ($esler !== []) {
                $harita[(int) $k['id']] = [
                    'rol'   => $anaMi ? 'ana' : 'bagli',
                    'esler' => $esler,
                ];
            }
        }

        return $harita;
    }

    // =================================================================
    //  DÖNEM ÜRETİMİ
    // =================================================================

    /**
     * Bir mükellef için verilen yıla ait dönemleri üretir/senkronize eder.
     *
     * Kurallar:
     *  - Faaliyet aralığı ile kesişmeyen dönemler OLUŞTURULMAZ.
     *  - Zaten var olan ve kullanıcı tarafından işlenmiş (BEKLIYOR dışı)
     *    satırlar KORUNUR; sadece tarih bilgileri güncellenir.
     *  - Mükellef terk ettiği için artık geçersiz kalan ve henüz işlenmemiş
     *    (BEKLIYOR) satırlar SİLİNİR.
     *
     * @return array{eklenen:int,guncellenen:int,silinen:int,korunan:int}
     */
    public function donemleriUret(int $mukellefId, int $yil, bool $eskiyiTemizle = true): array
    {
        $mukellefModel = new MukellefModel();
        $mukellef      = $mukellefModel->find($mukellefId);

        if ($mukellef === null) {
            return ['eklenen' => 0, 'guncellenen' => 0, 'silinen' => 0, 'korunan' => 0];
        }

        $turler   = $mukellefModel->beyannameTurleri($mukellefId);
        $uretici  = new DonemUretici(new TatilHesaplayici());
        $hedefler = $uretici->uret($mukellef, $turler, $yil);

        // Mevcut satırlar: anahtar = turId-donemNo
        $mevcutRows = $this->where('mukellef_id', $mukellefId)->where('yil', $yil)->findAll();
        $mevcut     = [];

        foreach ($mevcutRows as $r) {
            $mevcut[$r['beyanname_turu_id'] . '-' . $r['donem_no']] = $r;
        }

        $eklenen = $guncellenen = $korunan = 0;
        $gorulen = [];

        foreach ($hedefler as $h) {
            $key       = $h['beyanname_turu_id'] . '-' . $h['donem_no'];
            $gorulen[] = $key;

            if (! isset($mevcut[$key])) {
                $this->insert($h);
                $eklenen++;

                continue;
            }

            $eski = $mevcut[$key];

            // Tarihler değişmişse güncelle (tatil tanımı değişmiş olabilir)
            $degisti = $eski['son_tarih'] !== $h['son_tarih']
                || $eski['yasal_son_tarih'] !== $h['yasal_son_tarih']
                || ($eski['odeme_son_tarih'] ?? null) !== ($h['odeme_son_tarih'] ?? null)
                || $eski['donem_adi'] !== $h['donem_adi'];

            if ($degisti) {
                $this->update($eski['id'], [
                    'donem_adi'       => $h['donem_adi'],
                    'donem_baslangic' => $h['donem_baslangic'],
                    'donem_bitis'     => $h['donem_bitis'],
                    'yasal_son_tarih' => $h['yasal_son_tarih'],
                    'son_tarih'       => $h['son_tarih'],
                    'odeme_son_tarih' => $h['odeme_son_tarih'],
                    'kaydirma_nedeni' => $h['kaydirma_nedeni'],
                ]);
                $guncellenen++;
            } else {
                $korunan++;
            }
        }

        // Artık geçersiz olan (terk/pasifleştirme sonrası) BEKLIYOR satırlarını sil
        $silinen = 0;

        if ($eskiyiTemizle) {
            foreach ($mevcut as $key => $r) {
                if (in_array($key, $gorulen, true)) {
                    continue;
                }

                if ($r['durum'] === 'BEKLIYOR' && empty($r['not_metni'])) {
                    $this->delete($r['id']);
                    $silinen++;
                }
            }
        }

        return compact('eklenen', 'guncellenen', 'silinen', 'korunan');
    }

    /** Tüm (veya bir müşavirin) mükellefleri için toplu üretim */
    public function topluUret(int $yil, $musavirId = null): array
    {
        $mukellefModel = new MukellefModel();
        $b             = $mukellefModel->where('aktif', 1);

        if (is_array($musavirId)) {
            if ($musavirId !== []) {
                $b->whereIn('musavir_id', array_map('intval', $musavirId));
            }
        } elseif ($musavirId) {
            $b->where('musavir_id', (int) $musavirId);
        }

        $mukellefler = $b->findAll();
        $ozet        = ['mukellef' => 0, 'eklenen' => 0, 'guncellenen' => 0, 'silinen' => 0];

        foreach ($mukellefler as $m) {
            $s = $this->donemleriUret((int) $m['id'], $yil);
            $ozet['mukellef']++;
            $ozet['eklenen'] += $s['eklenen'];
            $ozet['guncellenen'] += $s['guncellenen'];
            $ozet['silinen'] += $s['silinen'];
        }

        return $ozet;
    }

    // =================================================================
    //  SORGULAR
    // =================================================================

    /**
     * Müşavir filtresini sorguya uygular.
     * $musavirId: null = kısıtlama yok, int = tek müşavir, int[] = çoklu
     */
    protected function musavirKosulu($b, $musavirId, string $alan = 'm.musavir_id')
    {
        if (is_array($musavirId)) {
            if ($musavirId !== []) {
                $b->whereIn($alan, array_map('intval', $musavirId));
            }
        } elseif ($musavirId) {
            $b->where($alan, (int) $musavirId);
        }

        return $b;
    }

    /**
     * Beyanname takip çizelgesi (liste görünümü).
     *
     * @param array $f ['yil','ay','musavir_id','mukellef_id','tur_id','durum','q','gecikmis']
     */
    public function cizelge(array $f): array
    {
        $b = $this->cizelgeSorgusu($f);

        $b->orderBy('beyanname_takip.son_tarih', 'ASC')
            ->orderBy('m.unvan', 'ASC')
            ->orderBy('bt.sira', 'ASC');

        // Sayfalama / sonsuz kaydırma: limit verilmişse yalnızca o parça çekilir
        if (! empty($f['limit'])) {
            return $b->findAll((int) $f['limit'], (int) ($f['ofset'] ?? 0));
        }

        return $b->findAll();
    }

    /** Filtreye uyan toplam kayıt sayısı (sonsuz kaydırma için) */
    public function cizelgeSayisi(array $f): int
    {
        return (int) $this->cizelgeSorgusu($f)->countAllResults();
    }

    /**
     * Tek kaydı, çizelgedeki gibi tür bilgisiyle birlikte getirir.
     *
     * find() yalnızca beyanname_takip sütunlarını döndürür; MUHSGK ↔ SGK
     * eşleşmesi için `tur_kodu` ve `tur_kisa` da gerekir. Bu yüzden ayrı
     * bir okuyucu kullanılır.
     */
    public function cizelgeKaydi(int $id): ?array
    {
        $satir = $this->db->table('beyanname_takip bt')
            ->select('bt.*, t.kod AS tur_kodu, t.kisa_ad AS tur_kisa, t.ad AS tur_ad,
                      m.musavir_id, m.unvan AS mukellef_unvan')
            ->join('beyanname_turleri t', 't.id = bt.beyanname_turu_id')
            ->join('mukellefler m', 'm.id = bt.mukellef_id')
            ->where('bt.id', $id)
            ->get()->getRowArray();

        return $satir ?: null;
    }

    /**
     * Filtreye uyan TÜM kayıtların id listesi.
     * "Filtredeki tüm kayıtları seç" toplu işleminde kullanılır.
     *
     * @return int[]
     */
    public function cizelgeIdleri(array $f, int $azami = 5000): array
    {
        $rows = $this->cizelgeSorgusu($f)
            ->select('beyanname_takip.id')
            ->limit($azami)
            ->findAll();

        return array_map('intval', array_column($rows, 'id'));
    }

    /** cizelge() / cizelgeSayisi() / cizelgeIdleri() ortak sorgusu */
    /**
     * İndirim alanları şemada var mı? (bir kez sorulur, sonra önbellekten)
     *
     * migration_indirimler.sql çalıştırılmamış bir kurulumda bu kolonlar
     * yoktur. Sorguya koşulsuz eklenirse çizelge "Unknown column" ile
     * tamamen çöker; oysa rozet ikincil bir özelliktir. Bu yüzden alanlar
     * yalnızca gerçekten varsa SELECT'e eklenir.
     */
    protected ?bool $indirimAlanlariVar = null;

    protected function indirimAlanlariVarMi(): bool
    {
        if ($this->indirimAlanlariVar === null) {
            $this->indirimAlanlariVar = in_array(
                'ind_bagkur',
                $this->db->getFieldNames('mukellefler'),
                true
            );
        }

        return $this->indirimAlanlariVar;
    }

    protected function cizelgeSorgusu(array $f)
    {
        $indAlan = $this->indirimAlanlariVarMi()
            ? 'm.ind_bagkur, m.ind_bagkur_not,
               m.ind_egitim_saglik, m.ind_egitim_saglik_not,
               m.ind_finansman, m.ind_finansman_not,'
            : '';

        $b = $this->select('beyanname_takip.*,
                            m.unvan as mukellef_unvan, m.kod as mukellef_kod,
                            m.vergi_kimlik_no, m.tc_kimlik_no, m.terk_tarihi, m.ise_baslama_tarihi,
                            m.genc_girisimci, m.gg_baslangic_yili, m.gg_not, m.defter_tipi,
                            ' . $indAlan . '
                            bt.ad as tur_adi, bt.kisa_ad as tur_kisa, bt.kod as tur_kodu,
                            bt.renk as tur_renk, bt.periyot,
                            mus.ad_soyad as musavir_adi, mus.renk as musavir_renk')
            ->join('mukellefler m', 'm.id = beyanname_takip.mukellef_id')
            ->join('beyanname_turleri bt', 'bt.id = beyanname_takip.beyanname_turu_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->where('m.deleted_at', null);

        // -------------------------------------------------------------
        //  TARİH FİLTRESİ — iki mod
        //
        //  'beyan'  (varsayılan): Yıl+Ay SON TARİHE bakar.
        //      "Nisan 2027'de hangi beyannameleri vereceğim?"
        //      -> Kurumlar 2026 dönemi (son tarih 30.04.2027) BU LİSTEDE çıkar.
        //
        //  'donem': Yıl+Ay beyannamenin AİT OLDUĞU döneme bakar.
        //      "2026 yılına ait beyannameler neler?"
        //      -> Kurumlar 2026, son tarihi 2027'de olsa da bu listede çıkar.
        //
        //  ÖNEMLİ: Eski sürümde yıl 'yil' kolonundan, ay ise son_tarih'ten
        //  okunuyordu. Bu iki farklı eksen karıştığı için "Mayıs 2027"
        //  filtresinde son tarihi 01.05.2028 olan kayıt görünüyor, buna karşılık
        //  Nisan 2027'de verilecek Kurumlar 2026 beyannamesi hiç görünmüyordu.
        // -------------------------------------------------------------
        $mod = ($f['tarih_modu'] ?? 'beyan') === 'donem' ? 'donem' : 'beyan';

        if ($mod === 'donem') {
            if (! empty($f['yil'])) {
                $b->where('beyanname_takip.yil', (int) $f['yil']);
            }

            // Dönemin bitiş ayı (aylıkta ayın kendisi, 3 aylıkta çeyreğin son ayı)
            if (! empty($f['ay'])) {
                $b->where('MONTH(beyanname_takip.donem_bitis)', (int) $f['ay']);
            }
        } else {
            if (! empty($f['yil'])) {
                $b->where('YEAR(beyanname_takip.son_tarih)', (int) $f['yil']);
            }

            if (! empty($f['ay'])) {
                $b->where('MONTH(beyanname_takip.son_tarih)', (int) $f['ay']);
            }
        }

        if (! empty($f['musavir_id'])) {
            $this->musavirKosulu($b, $f['musavir_id']);
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('beyanname_takip.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['tur_id'])) {
            $b->where('beyanname_takip.beyanname_turu_id', (int) $f['tur_id']);
        }

        if (! empty($f['durum'])) {
            $b->where('beyanname_takip.durum', $f['durum']);
        }

        // Birden çok durumu birlikte süzmek için (örn. "Kalan" = Bekliyor+Hazır)
        if (! empty($f['durum_liste']) && is_array($f['durum_liste'])) {
            $b->whereIn('beyanname_takip.durum', $f['durum_liste']);
        }

        if (! empty($f['defter_tipi'])) {
            $b->where('m.defter_tipi', $f['defter_tipi']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('m.unvan', $f['q'])
                ->orLike('m.vergi_kimlik_no', $f['q'])
                ->orLike('m.kod', $f['q'])
              ->groupEnd();
        }

        if (! empty($f['gecikmis'])) {
            $b->where('beyanname_takip.son_tarih <', date('Y-m-d'))
              ->whereIn('beyanname_takip.durum', ['BEKLIYOR', 'HAZIR']);
        }

        return $b;
    }

    /**
     * Mükellef bazlı yıllık matris: [tur_id][donem_no] = satır
     */
    public function mukellefMatrisi(int $mukellefId, int $yil): array
    {
        $rows = $this->select('beyanname_takip.*, bt.ad as tur_adi, bt.kisa_ad as tur_kisa,
                               bt.kod as tur_kodu, bt.renk as tur_renk, bt.periyot, bt.sira')
            ->join('beyanname_turleri bt', 'bt.id = beyanname_takip.beyanname_turu_id')
            ->where('beyanname_takip.mukellef_id', $mukellefId)
            ->where('beyanname_takip.yil', $yil)
            ->orderBy('bt.sira', 'ASC')
            ->orderBy('beyanname_takip.donem_no', 'ASC')
            ->findAll();

        $matris = [];

        foreach ($rows as $r) {
            $matris[$r['beyanname_turu_id']]['tur'] = [
                'id'      => $r['beyanname_turu_id'],
                'ad'      => $r['tur_adi'],
                'kisa'    => $r['tur_kisa'],
                'kod'     => $r['tur_kodu'],
                'renk'    => $r['tur_renk'],
                'periyot' => $r['periyot'],
            ];
            $matris[$r['beyanname_turu_id']]['donemler'][(int) $r['donem_no']] = $r;
        }

        return $matris;
    }

    /** Yaklaşan son tarihler (dashboard) */
    public function yaklasanlar(int $gun = 7, $musavirId = null, int $limit = 50): array
    {
        $b = $this->select('beyanname_takip.*, m.unvan as mukellef_unvan,
                            bt.kisa_ad as tur_kisa, bt.renk as tur_renk,
                            mus.ad_soyad as musavir_adi')
            ->join('mukellefler m', 'm.id = beyanname_takip.mukellef_id')
            ->join('beyanname_turleri bt', 'bt.id = beyanname_takip.beyanname_turu_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->where('m.deleted_at', null)
            ->where('beyanname_takip.son_tarih >=', date('Y-m-d'))
            ->where('beyanname_takip.son_tarih <=', date('Y-m-d', strtotime("+{$gun} days")))
            ->whereIn('beyanname_takip.durum', ['BEKLIYOR', 'HAZIR']);

        $this->musavirKosulu($b, $musavirId);

        return $b->orderBy('beyanname_takip.son_tarih', 'ASC')->findAll($limit);
    }

    /** Süresi geçmiş ve hâlâ gönderilmemiş olanlar */
    public function gecikmisler($musavirId = null, int $limit = 100): array
    {
        $b = $this->select('beyanname_takip.*, m.unvan as mukellef_unvan,
                            bt.kisa_ad as tur_kisa, bt.renk as tur_renk,
                            mus.ad_soyad as musavir_adi')
            ->join('mukellefler m', 'm.id = beyanname_takip.mukellef_id')
            ->join('beyanname_turleri bt', 'bt.id = beyanname_takip.beyanname_turu_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->where('m.deleted_at', null)
            ->where('beyanname_takip.son_tarih <', date('Y-m-d'))
            ->whereIn('beyanname_takip.durum', ['BEKLIYOR', 'HAZIR']);

        $this->musavirKosulu($b, $musavirId);

        return $b->orderBy('beyanname_takip.son_tarih', 'ASC')->findAll($limit);
    }

    /**
     * Özet sayaçları — EKRANDAKİ FİLTRENİN AYNISI ile hesaplanır.
     *
     * Neden ayrı bir metot değil de cizelgeSorgusu() üzerinden?
     *   Eski sürümde sayaçların kendi sorgusu vardı ve yalnızca yıl + müşavir +
     *   defter tipini biliyordu. Bu yüzden kullanıcı "Beyanname Türü = KDV1" ve
     *   "Ay = Ağustos" seçtiğinde liste 12 satıra düşerken "Onaylandı" kartı
     *   tüm yılın 275'ini göstermeye devam ediyordu. Artık sayaçlar listeyle
     *   aynı sorgudan türetiliyor; ikisi asla ayrışamaz.
     *
     * DURUM ve GECİKMİŞ filtreleri BİLEREK dışarıda bırakılır:
     *   Kartların görevi dağılımı göstermektir. "Durum = Onaylandı" seçiliyken
     *   Hazır kartının 0 görünmesi bilgi kaybı olurdu. Böylece
     *   Bekliyor + Hazır + Onaylandı + Verilmeyecek = Toplam eşitliği korunur.
     *
     * @param array $f cizelge() ile aynı filtre dizisi
     */
    public function ozetCizelge(array $f): array
    {
        $temelF = $f;
        unset($temelF['durum'], $temelF['gecikmis'], $temelF['limit'], $temelF['ofset']);

        $temel = fn () => $this->cizelgeSorgusu($temelF);

        $sonuc = ['toplam' => (int) $temel()->countAllResults()];

        foreach (array_keys(self::DURUMLAR) as $d) {
            $sonuc[strtolower($d)] = (int) $temel()
                ->where('beyanname_takip.durum', $d)
                ->countAllResults();
        }

        $sonuc['gecikmis'] = (int) $temel()
            ->where('beyanname_takip.son_tarih <', date('Y-m-d'))
            ->whereIn('beyanname_takip.durum', ['BEKLIYOR', 'HAZIR'])
            ->countAllResults();

        $sonuc['bugun'] = (int) $temel()
            ->where('beyanname_takip.son_tarih', date('Y-m-d'))
            ->countAllResults();

        return $sonuc;
    }

    /**
     * Dashboard sayaçları (panel / raporlar için sade imza).
     * Tek hesap kaynağı ozetCizelge()'dir.
     */
    public function ozet(int $yil, $musavirId = null, string $mod = 'beyan', ?string $defterTipi = null): array
    {
        return $this->ozetCizelge([
            'yil'         => $yil,
            'tarih_modu'  => $mod,
            'musavir_id'  => $musavirId,
            'defter_tipi' => $defterTipi,
        ]);
    }

    /**
     * BEYANNAME TÜRÜ BAZINDA DURUM DAĞILIMI (kontrol paneli tablosu)
     *
     * "Eylül 2026'da hangi beyannameden kaç tane var, kaçı bitti?" sorusuna
     * cevap verir. Her satır bir beyanname türüdür.
     *
     * ÖNEMLİ — türler SABİT LİSTEDEN değil, o ay GERÇEKTEN VAR OLAN
     * kayıtlardan üretilir. Bu yüzden geçici vergi beyannameleri yalnızca
     * verildikleri aylarda (Şubat/Mayıs/Ağustos/Kasım gibi) tabloda görünür;
     * Eylül seçildiğinde listeden kendiliğinden düşer. Aylık türler (KDV,
     * MUHSGK) ise her ay çıkar.
     *
     * @param int      $yil
     * @param int|null $ay        null / 0 => tüm yıl
     * @param mixed    $musavirId null | int | int[]
     * @param string   $mod       'beyan' (son tarih) | 'donem' (ait olduğu dönem)
     *
     * @return array<int,array<string,mixed>>
     */
    public function turDagilimi(int $yil, ?int $ay = null, $musavirId = null, string $mod = 'beyan'): array
    {
        $b = $this->db->table('beyanname_takip bt')
            ->select("bt.beyanname_turu_id AS tur_id,
                      t.kod       AS tur_kodu,
                      t.kisa_ad   AS tur_kisa,
                      t.ad        AS tur_adi,
                      t.renk      AS tur_renk,
                      t.periyot   AS periyot,
                      COUNT(*) AS toplam,
                      SUM(bt.durum = 'ONAYLANDI')    AS onaylandi,
                      SUM(bt.durum = 'HAZIR')        AS hazir,
                      SUM(bt.durum = 'BEKLIYOR')     AS bekliyor,
                      SUM(bt.durum = 'VERILMEYECEK') AS verilmeyecek,
                      SUM(bt.son_tarih < CURDATE() AND bt.durum IN ('BEKLIYOR','HAZIR')) AS gecikmis,
                      MIN(bt.son_tarih) AS ilk_son_tarih,
                      MAX(bt.son_tarih) AS son_son_tarih")
            ->join('mukellefler m', 'm.id = bt.mukellef_id')
            ->join('beyanname_turleri t', 't.id = bt.beyanname_turu_id')
            ->where('m.deleted_at', null);

        if ($mod === 'donem') {
            $b->where('bt.yil', $yil);

            if (! empty($ay)) {
                $b->where('MONTH(bt.donem_bitis)', (int) $ay);
            }
        } else {
            $b->where('YEAR(bt.son_tarih)', $yil);

            if (! empty($ay)) {
                $b->where('MONTH(bt.son_tarih)', (int) $ay);
            }
        }

        $this->musavirKosulu($b, $musavirId);

        $rows = $b->groupBy('bt.beyanname_turu_id, t.kod, t.kisa_ad, t.ad, t.renk, t.periyot, t.sira')
            ->orderBy('t.sira', 'ASC')
            ->get()->getResultArray();

        $sonuc = [];

        foreach ($rows as $r) {
            $toplam = (int) $r['toplam'];

            // "Kalan" = henüz sonuçlanmamış iş (Bekliyor + Hazır).
            // Verilmeyecek olanlar iş yükü değildir, kalana dahil edilmez.
            $kalan = (int) $r['bekliyor'] + (int) $r['hazir'];

            // Tamamlanma oranı yalnızca TAKİP EDİLEN kayıtlar üzerinden
            // hesaplanır; "Verilmeyecek" payda dışıdır, yoksa hiç
            // bitmeyecek bir yüzde görünürdü.
            $takipli = $toplam - (int) $r['verilmeyecek'];
            $oran    = $takipli > 0 ? (int) round((int) $r['onaylandi'] / $takipli * 100) : 100;

            $sonuc[] = [
                'tur_id'        => (int) $r['tur_id'],
                'tur_kodu'      => $r['tur_kodu'],
                'tur_kisa'      => $r['tur_kisa'],
                'tur_adi'       => $r['tur_adi'],
                'tur_renk'      => $r['tur_renk'],
                'periyot'       => $r['periyot'],
                'toplam'        => $toplam,
                'onaylandi'     => (int) $r['onaylandi'],
                'hazir'         => (int) $r['hazir'],
                'bekliyor'      => (int) $r['bekliyor'],
                'verilmeyecek'  => (int) $r['verilmeyecek'],
                'gecikmis'      => (int) $r['gecikmis'],
                'kalan'         => $kalan,
                'oran'          => $oran,
                'ilk_son_tarih' => $r['ilk_son_tarih'],
                'son_son_tarih' => $r['son_son_tarih'],
            ];
        }

        return $sonuc;
    }

    /** Aylık durum grafiği verisi */
    public function aylikGrafik(int $yil, $musavirId = null, string $mod = 'beyan'): array
    {
        $b = $this->db->table('beyanname_takip bt')
            ->select('MONTH(bt.son_tarih) as ay, bt.durum, COUNT(*) as adet')
            ->join('mukellefler m', 'm.id = bt.mukellef_id')
            ->where('m.deleted_at', null);

        // Grafik "hangi ay kaç beyanname veriyorum" sorusuna cevap verir
        if ($mod === 'donem') {
            $b->where('bt.yil', $yil);
        } else {
            $b->where('YEAR(bt.son_tarih)', $yil);
        }

        $this->musavirKosulu($b, $musavirId);

        $rows = $b->groupBy('ay, bt.durum')->get()->getResultArray();
        $out  = [];

        for ($i = 1; $i <= 12; $i++) {
            $out[$i] = ['BEKLIYOR' => 0, 'HAZIR' => 0, 'ONAYLANDI' => 0, 'VERILMEYECEK' => 0];
        }

        foreach ($rows as $r) {
            $ay = (int) $r['ay'];

            if ($ay >= 1 && $ay <= 12) {
                $out[$ay][$r['durum']] = (int) $r['adet'];
            }
        }

        return $out;
    }


    // =================================================================
    //  ÖDEME LİSTESİ
    // =================================================================

    /**
     * Ödeme listesi — mükellef bazında gruplanmış.
     *
     * Tahakkuk tutarları çizelgeye DAMGA HARİÇ girilir; burada her satıra
     * ilgili beyanname türünün o yıla ait sabit damga vergisi eklenir.
     *
     * @param array $f ['yil','ay','musavir_id','mukellef_id','durum','odendi','q']
     *
     * @return array{gruplar:array,toplam:array}
     */
    public function odemeListesi(array $f): array
    {
        $b = $this->select('beyanname_takip.*,
                            m.id as m_id, m.unvan as mukellef_unvan, m.kod as mukellef_kod,
                            m.vergi_kimlik_no, m.tc_kimlik_no, m.vergi_dairesi,
                            bt.ad as tur_adi, bt.kisa_ad as tur_kisa, bt.kod as tur_kodu,
                            bt.renk as tur_renk,
                            mus.ad_soyad as musavir_adi')
            ->join('mukellefler m', 'm.id = beyanname_takip.mukellef_id')
            ->join('beyanname_turleri bt', 'bt.id = beyanname_takip.beyanname_turu_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->where('m.deleted_at', null);

        // Ödeme listesi ÖDEME son tarihine göre çalışır.
        // SGK gibi ödemesi beyandan farklı olan türlerde odeme_son_tarih doludur;
        // diğerlerinde beyan son tarihi geçerlidir (COALESCE).
        if (! empty($f['yil'])) {
            $b->where('YEAR(COALESCE(beyanname_takip.odeme_son_tarih, beyanname_takip.son_tarih))', (int) $f['yil']);
        }

        if (! empty($f['ay'])) {
            $b->where('MONTH(COALESCE(beyanname_takip.odeme_son_tarih, beyanname_takip.son_tarih))', (int) $f['ay']);
        }

        if (! empty($f['musavir_id'])) {
            $this->musavirKosulu($b, $f['musavir_id']);
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('beyanname_takip.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('m.unvan', $f['q'])
                ->orLike('m.vergi_kimlik_no', $f['q'])
                ->orLike('m.kod', $f['q'])
              ->groupEnd();
        }

        // Ödeme durumu
        if (isset($f['odendi']) && $f['odendi'] !== '' && $f['odendi'] !== null) {
            $b->where('beyanname_takip.odendi', (int) $f['odendi']);
        }

        // Varsayılan: yalnızca onaylanmış/gönderilmiş satırlar ödemeye girer
        $durumlar = $f['durumlar'] ?? self::ODENECEK_DURUMLAR;

        if ($durumlar !== []) {
            $b->whereIn('beyanname_takip.durum', $durumlar);
        }

        $rows = $b->orderBy('m.unvan', 'ASC')
            ->orderBy('beyanname_takip.son_tarih', 'ASC')
            ->orderBy('bt.sira', 'ASC')
            ->findAll();

        // ---- Damga tutarlarını ekle ve grupla ----
        $damgaModel = new DamgaTutarModel();
        $ayarlar    = (new AyarModel())->tumu();
        $damgaAcik  = (int) ($ayarlar['damga_otomatik_ekle'] ?? 1) === 1;

        $gruplar = [];
        $toplam  = ['tahakkuk' => 0.0, 'damga' => 0.0, 'genel' => 0.0, 'adet' => 0,
                    'odenen' => 0.0, 'ozel' => 0.0, 'ozel_adet' => 0];

        foreach ($rows as $r) {
            $mid = (int) $r['m_id'];

            // Efektif ödeme tarihi (SGK'da ay sonu, diğerlerinde beyan tarihi)
            $r['efektif_odeme_tarihi'] = $r['odeme_son_tarih'] ?: $r['son_tarih'];
            $yil = (int) date('Y', strtotime($r['son_tarih']));

            $tahakkuk = (float) ($r['tahakkuk_tutari'] ?? 0);

            // Kayıtlı damga varsa onu kullan (tarihsel doğruluk),
            // yoksa tanımdan oku.
            $damga = (float) ($r['damga_tutari'] ?? 0);

            if ($damga <= 0 && $damgaAcik) {
                $damga = $damgaModel->tutarAl((int) $r['beyanname_turu_id'], $yil);
            }

            if (! $damgaAcik) {
                $damga = 0.0;
            }

            $r['hesaplanan_damga'] = $damga;
            $r['odenecek']         = $tahakkuk + $damga;

            if (! isset($gruplar[$mid])) {
                $gruplar[$mid] = [
                    'mukellef' => [
                        'id'            => $mid,
                        'unvan'         => $r['mukellef_unvan'],
                        'kod'           => $r['mukellef_kod'],
                        'vkn'           => $r['vergi_kimlik_no'] ?: $r['tc_kimlik_no'],
                        'vergi_dairesi' => $r['vergi_dairesi'],
                        'musavir_adi'   => $r['musavir_adi'],
                    ],
                    'satirlar' => [],
                    'ozel'     => [],
                    // 'genel'     = yalnızca BEYANNAME toplamı (tahakkuk + damga)
                    // 'ozel'      = beyanname dışı kalemler (Bağkur, MTV…)
                    // 'genel_tum' = ikisinin toplamı — mükellefin ödeyeceği tutar
                    'toplam'   => [
                        'tahakkuk' => 0.0, 'damga' => 0.0, 'genel' => 0.0, 'adet' => 0,
                        'ozel' => 0.0, 'ozel_adet' => 0, 'genel_tum' => 0.0,
                    ],
                ];
            }

            $gruplar[$mid]['satirlar'][] = $r;
            $gruplar[$mid]['toplam']['tahakkuk'] += $tahakkuk;
            $gruplar[$mid]['toplam']['damga']    += $damga;
            $gruplar[$mid]['toplam']['genel']     += $r['odenecek'];
            $gruplar[$mid]['toplam']['genel_tum'] += $r['odenecek'];
            $gruplar[$mid]['toplam']['adet']++;

            $toplam['tahakkuk'] += $tahakkuk;
            $toplam['damga']    += $damga;
            $toplam['genel']    += $r['odenecek'];
            $toplam['adet']++;

            if ((int) $r['odendi'] === 1) {
                $toplam['odenen'] += $r['odenecek'];
            }
        }

        // ---- ÖZEL ÖDEME KALEMLERİ (Bağkur, MTV vb.) ----
        // Kayıtlı ödeme listeleri kalemleri kendisi ekler; orada atlanır.
        if (! empty($f['ozel_atla'])) {
            uasort($gruplar, static fn ($a, $b) => strcoll($a['mukellef']['unvan'], $b['mukellef']['unvan']));

            return ['gruplar' => array_values($gruplar), 'toplam' => $toplam];
        }

        $ozel = (new OzelOdemeModel())->listele([
            'yil'         => $f['yil']         ?? null,
            'ay'          => $f['ay']          ?? null,
            'musavir_id'  => $f['musavir_id']  ?? null,
            'mukellef_id' => $f['mukellef_id'] ?? null,
            'odendi'      => $f['odendi']      ?? null,
            'q'           => $f['q']           ?? null,
            'kaydeden_id' => $f['ozel_kaydeden_id'] ?? null,
        ]);

        foreach ($ozel as $o) {
            $mid   = (int) $o['m_id'];
            $tutar = (float) $o['tutar'];

            if (! isset($gruplar[$mid])) {
                $gruplar[$mid] = [
                    'mukellef' => [
                        'id'            => $mid,
                        'unvan'         => $o['mukellef_unvan'],
                        'kod'           => $o['mukellef_kod'],
                        'vkn'           => $o['vergi_kimlik_no'] ?: $o['tc_kimlik_no'],
                        'vergi_dairesi' => $o['vergi_dairesi'],
                        'musavir_adi'   => $o['musavir_adi'],
                    ],
                    'satirlar' => [],
                    'ozel'     => [],
                    'toplam'   => [
                        'tahakkuk' => 0.0, 'damga' => 0.0, 'genel' => 0.0, 'adet' => 0,
                        'ozel' => 0.0, 'ozel_adet' => 0, 'genel_tum' => 0.0,
                    ],
                ];
            }

            // DÜZELTİLEN KUSUR — MÜKERRER TOPLAM:
            // Özel kalemler (Bağkur, MTV…) eskiden 'tahakkuk' ve 'genel'
            // alanlarına ekleniyordu. Görünüm ise "Mükellef Genel Toplamı"nı
            // hesaplarken özel kalemleri BİR KEZ DAHA topluyordu; sonuçta
            // Bağkur hem beyanname ara toplamına karışıyor hem de genel
            // toplamda iki kez sayılıyordu.
            //
            // Artık ayrı tutulur:
            //   'genel'     → yalnızca beyannameler
            //   'ozel'      → yalnızca beyanname dışı kalemler
            //   'genel_tum' → ikisinin toplamı (tek doğru kaynak)
            $gruplar[$mid]['ozel'][] = $o;
            $gruplar[$mid]['toplam']['ozel']      += $tutar;
            $gruplar[$mid]['toplam']['genel_tum'] += $tutar;
            $gruplar[$mid]['toplam']['ozel_adet']++;

            // Sayfa geneli: özel kalem tahakkuk DEĞİLDİR, ayrı sayılır.
            $toplam['ozel']  = ($toplam['ozel'] ?? 0) + $tutar;
            $toplam['genel'] += $tutar;
            $toplam['adet']++;
            $toplam['ozel_adet'] = ($toplam['ozel_adet'] ?? 0) + 1;

            if ((int) $o['odendi'] === 1) {
                $toplam['odenen'] += $tutar;
            }
        }

        // Mükellef adına göre sırala
        uasort($gruplar, static fn ($a, $b) => strcoll($a['mukellef']['unvan'], $b['mukellef']['unvan']));

        return ['gruplar' => array_values($gruplar), 'toplam' => $toplam];
    }

    /**
     * Tahakkuk tutarını kaydeder ve o anki damga tutarını satıra kopyalar.
     * (Damga tanımı sonradan değişse bile geçmiş kayıt bozulmaz.)
     */
    public function tahakkukKaydet(int $id, ?float $tutar, ?string $fisNo = null): bool
    {
        $kayit = $this->find($id);

        if ($kayit === null) {
            return false;
        }

        // Tutar boşaltıldıysa damga da anlamsızdır: kayıt tümüyle temizlenir.
        if ($tutar === null) {
            return $this->tahakkukTemizle($id);
        }

        $yil   = (int) date('Y', strtotime($kayit['son_tarih']));
        $damga = (new DamgaTutarModel())->tutarAl((int) $kayit['beyanname_turu_id'], $yil);

        return $this->update($id, [
            'tahakkuk_tutari' => $tutar,
            'damga_tutari'    => $damga,
            'tahakkuk_fis_no' => $fisNo,
        ]);
    }

    /**
     * Tahakkuk bilgisini (tutar + damga + fiş no) tümüyle siler.
     *
     * Durum "Onaylandı"dan geri alındığında kullanıcı onayıyla çağrılır.
     */
    public function tahakkukTemizle(int $id): bool
    {
        if ($this->find($id) === null) {
            return false;
        }

        return $this->update($id, [
            'tahakkuk_tutari' => null,
            'damga_tutari'    => 0,
            'tahakkuk_fis_no' => null,
            // Tahakkuk yoksa "ödendi" işareti de anlamını yitirir
            'odendi'          => 0,
            'odeme_tarihi'    => null,
        ]);
    }

    /** Kaydın girilmiş tahakkuk bilgisi var mı? */
    public function tahakkukVarMi(int $id): bool
    {
        $k = $this->find($id);

        return $k !== null
            && ($k['tahakkuk_tutari'] !== null || (float) $k['damga_tutari'] > 0);
    }

    /**
     * Bir mükellefin belirli tarihten ÖNCE son tarihi dolan ve hâlâ
     * işlem görmemiş (Bekliyor) dönemlerini topluca işaretler.
     *
     * Mükellefi sonradan devraldığınızda geçmiş dönemlerin "gecikmiş"
     * görünmesini engellemek için kullanılır.
     *
     * @param string $tarih  Bu tarihten önceki son tarihler
     * @param string $durum  ONAYLANDI | VERILMEYECEK
     *
     * @return int Güncellenen satır sayısı
     */
    public function gecmisiKapat(int $mukellefId, string $tarih, string $durum = 'ONAYLANDI'): int
    {
        if (! in_array($durum, ['ONAYLANDI', 'VERILMEYECEK'], true)) {
            return 0;
        }

        $veri = ['durum' => $durum, 'updated_at' => date('Y-m-d H:i:s')];

        if ($durum === 'ONAYLANDI') {
            $veri['onay_tarihi']     = date('Y-m-d H:i:s');
            $veri['gonderim_tarihi'] = date('Y-m-d H:i:s');
        }

        $this->db->table('beyanname_takip')
            ->where('mukellef_id', $mukellefId)
            ->where('son_tarih <', substr($tarih, 0, 10))
            ->where('durum', 'BEKLIYOR')
            ->update($veri);

        return $this->db->affectedRows();
    }

    /** Ödeme işaretle / geri al */
    public function odemeIsaretle(int $id, bool $odendi, ?string $tarih = null): bool
    {
        return $this->update($id, [
            'odendi'       => $odendi ? 1 : 0,
            'odeme_tarihi' => $odendi ? ($tarih ?: date('Y-m-d')) : null,
        ]);
    }

    // =================================================================
    //  DURUM GÜNCELLEME
    // =================================================================

    public function durumDegistir(int $id, string $durum, ?int $kullaniciId = null): bool
    {
        if (! array_key_exists($durum, self::DURUMLAR)) {
            return false;
        }

        $veri = ['durum' => $durum];

        if ($durum === 'ONAYLANDI') {
            $veri['onaylayan_id']    = $kullaniciId;
            $veri['onay_tarihi']     = date('Y-m-d H:i:s');
            $veri['gonderim_tarihi'] = date('Y-m-d H:i:s');
        } elseif ($durum === 'BEKLIYOR') {
            $veri['onaylayan_id']    = null;
            $veri['onay_tarihi']     = null;
            $veri['gonderim_tarihi'] = null;
        }

        return $this->update($id, $veri);
    }

    /** Kalan gün (negatif = gecikmiş) */
    public static function kalanGun(string $sonTarih): int
    {
        $bugun = new \DateTime(date('Y-m-d'));
        $son   = new \DateTime(substr($sonTarih, 0, 10));

        return (int) $bugun->diff($son)->format('%r%a');
    }
}
