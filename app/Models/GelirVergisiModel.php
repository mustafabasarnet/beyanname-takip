<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * MALİ MÜŞAVİR GELİR VERGİSİ HESABI
 *
 * Hasılat, Makbuz Takip modülündeki makbuzlardan OTOMATİK gelir; kullanıcı
 * yalnızca gider ve mahsup kalemlerini girer. Böylece yeni makbuz kesildikçe
 * hesap kendiliğinden güncellenir.
 *
 * Hesap akışı (serbest meslek kazancı — GVK md.65 vd.):
 *
 *   Hasılat (kesilen makbuzların BRÜT toplamı)
 *   − Gider                                        = KAZANÇ
 *   − Bağ-Kur / SGK primi              (sınırsız)
 *   − Şahıs/hayat sigorta primi        (kârın %15'i)
 *   − Eğitim ve sağlık harcaması       (kârın %10'u) = MATRAH
 *
 *   Matrah → GVK md.103 tarifesi                   = Hesaplanan vergi
 *   − %5 uyumlu mükellef indirimi (mük.121)        = Ödenmesi gereken vergi
 *   − Stopaj (makbuzlardan otomatik)
 *   − Diğer mahsuplar
 *   + Yıl içinde ödenen KDV yükü                   = ÖDENECEK / İADE
 *
 * KDV MANTIĞI: Stopaj yıl içinde kesilir ve iade doğurur; KDV ise yıl
 * içinde ÖDENİR. Bu yüzden KDV tablosundaki yıllık toplam stopajdan
 * düşülür — "yıl içinde net ne kadar vergi ödeyeceğim" tek rakamda çıkar.
 * KDV, gelir vergisi MATRAHINA girmez; yalnızca mahsup aşamasında sayılır.
 */
class GelirVergisiModel extends Model
{
    protected $table         = 'musavir_gelir_gider';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    /**
     * PASİF ALANLAR (16. güncelleme)
     *
     * gecmis_yil_zarari / gecici_vergi / diger_indirim sütunları veritabanında
     * DURUYOR (geçmiş kayıt bozulmasın) ama artık okunmuyor, yazılmıyor ve
     * hesaba katılmıyor. Büro bu kalemleri kullanmadığı için ekrandan kaldırıldı.
     */
    protected $allowedFields = [
        'musavir_id', 'yil', 'hesap_kipi', 'gider', 'bagkur', 'sigorta_primi',
        'egitim_saglik', 'diger_mahsup', 'uyumlu_indirim', 'stopaj_elle',
        'hasilat_elle', 'aciklama', 'kaydeden_id',
    ];

    /** Elle girilen alanların varsayılanları */
    public const BOS_KAYIT = [
        'hesap_kipi'     => 'ucret',
        'gider'          => 0.0,
        'bagkur'         => 0.0,
        'sigorta_primi'  => 0.0,
        'egitim_saglik'  => 0.0,
        'diger_mahsup'   => 0.0,
        'uyumlu_indirim' => 0,
        'stopaj_elle'    => null,
        'hasilat_elle'   => null,
        'aciklama'       => null,
    ];

    protected function ayar(string $anahtar, $varsayilan)
    {
        static $onbellek = [];

        if (! array_key_exists($anahtar, $onbellek)) {
            $onbellek[$anahtar] = (new AyarModel())->oku($anahtar, $varsayilan);
        }

        return $onbellek[$anahtar];
    }

    public function uyumluOran(): float
    {
        return (float) $this->ayar('gv_uyumlu_oran', 5);
    }

    public function uyumluUstSinir(): float
    {
        return (float) $this->ayar('gv_uyumlu_ust_sinir', 12000000);
    }

    /** Yıllık sözleşme ücretinden stopaj oranı (%) — projeksiyon kipi */
    public function ucretStopajOran(): float
    {
        return (float) $this->ayar('gv_ucret_stopaj_oran', 20);
    }

    /** Yıllık sözleşme ücretinden KDV oranı (%) — projeksiyon kipi */
    public function ucretKdvOran(): float
    {
        return (float) $this->ayar('gv_ucret_kdv_oran', 20);
    }

    /** Yeni kayıtlarda varsayılan hesap kipi */
    public function varsayilanKip(): string
    {
        return $this->ayar('gv_varsayilan_kip', 'ucret') === 'makbuz' ? 'makbuz' : 'ucret';
    }

    /** Şahıs/hayat sigorta primi indirim üst oranı (GVK 89/1) — kârın %15'i */
    public function sigortaOran(): float
    {
        return (float) $this->ayar('gv_sigorta_oran', 15);
    }

    /** Eğitim ve sağlık harcaması indirim üst oranı (GVK 89/2) — kârın %10'u */
    public function egitimSaglikOran(): float
    {
        return (float) $this->ayar('gv_egitim_saglik_oran', 10);
    }

    /**
     * Hasılat kaynağı: 'tum' = kesilen tüm makbuzlar, 'tahsil' = yalnız
     * tahsil edilenler. Ayarlar'dan değiştirilebilir.
     */
    public function hasilatKaynagi(): string
    {
        return $this->ayar('gv_hasilat_kaynagi', 'tum') === 'tahsil' ? 'tahsil' : 'tum';
    }

    // =================================================================
    //  GİDER / MAHSUP KAYDI
    // =================================================================

    /** Müşavirin ilgili yıl kaydı (yoksa boş varsayılanlar) */
    public function kayitAl(int $musavirId, int $yil): array
    {
        $r = $this->where('musavir_id', $musavirId)->where('yil', $yil)->first();

        if ($r === null) {
            return ['hesap_kipi' => $this->varsayilanKip()] + self::BOS_KAYIT
                + ['musavir_id' => $musavirId, 'yil' => $yil, 'id' => null];
        }

        foreach (['gider', 'bagkur', 'sigorta_primi', 'egitim_saglik', 'diger_mahsup'] as $a) {
            $r[$a] = (float) $r[$a];
        }

        $r['uyumlu_indirim'] = (int) $r['uyumlu_indirim'];
        $r['hesap_kipi']     = ($r['hesap_kipi'] ?? 'ucret') === 'makbuz' ? 'makbuz' : 'ucret';
        $r['stopaj_elle']    = $r['stopaj_elle'] === null ? null : (float) $r['stopaj_elle'];
        $r['hasilat_elle']   = $r['hasilat_elle'] === null ? null : (float) $r['hasilat_elle'];

        return $r;
    }

    /** Gider/mahsup kaydını yazar (varsa günceller) */
    public function kayitYaz(int $musavirId, int $yil, array $veri): bool
    {
        $temiz = [];

        foreach (array_keys(self::BOS_KAYIT) as $alan) {
            if (array_key_exists($alan, $veri)) {
                $temiz[$alan] = $veri[$alan];
            }
        }

        $temiz['musavir_id'] = $musavirId;
        $temiz['yil']        = $yil;

        if (array_key_exists('kaydeden_id', $veri)) {
            $temiz['kaydeden_id'] = $veri['kaydeden_id'];
        }

        $var = $this->where('musavir_id', $musavirId)->where('yil', $yil)->first();

        if ($var === null) {
            return (bool) $this->insert($temiz);
        }

        return (bool) $this->update($var['id'], $temiz);
    }

    // =================================================================
    //  MAKBUZLARDAN HASILAT
    // =================================================================

    /**
     * Müşavirin yıl içinde KESTİĞİ makbuzların toplamı.
     *
     * Makbuzu KESEN müşavir esas alınır (mükellefin portföy sahibi değil) —
     * vergi, makbuzu kesen kişinin kazancıdır.
     *
     * @return array{brut:float, stopaj:float, kdv:float, net:float, adet:int,
     *               tahsil_brut:float, tahsil_stopaj:float, tahsil_adet:int}
     */
    public function makbuzToplami(int $musavirId, int $yil): array
    {
        $r = $this->db->table('makbuzlar mk')
            ->select('COUNT(*) AS adet,
                      COALESCE(SUM(mk.brut),0)   AS brut,
                      COALESCE(SUM(mk.stopaj),0) AS stopaj,
                      COALESCE(SUM(mk.kdv),0)    AS kdv,
                      COALESCE(SUM(mk.net),0)    AS net,
                      COALESCE(SUM(CASE WHEN mk.tahsil_edildi=1 THEN mk.brut   ELSE 0 END),0) AS tahsil_brut,
                      COALESCE(SUM(CASE WHEN mk.tahsil_edildi=1 THEN mk.stopaj ELSE 0 END),0) AS tahsil_stopaj,
                      COALESCE(SUM(CASE WHEN mk.tahsil_edildi=1 THEN mk.kdv    ELSE 0 END),0) AS tahsil_kdv,
                      COALESCE(SUM(CASE WHEN mk.tahsil_edildi=1 THEN 1 ELSE 0 END),0)         AS tahsil_adet')
            ->join('mukellefler m', 'm.id = mk.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('mk.musavir_id', $musavirId)
            ->where('mk.yil', $yil)
            ->get()->getRowArray();

        return [
            'adet'          => (int) ($r['adet'] ?? 0),
            'brut'          => (float) ($r['brut'] ?? 0),
            'stopaj'        => (float) ($r['stopaj'] ?? 0),
            'kdv'           => (float) ($r['kdv'] ?? 0),
            'net'           => (float) ($r['net'] ?? 0),
            'tahsil_brut'   => (float) ($r['tahsil_brut'] ?? 0),
            'tahsil_stopaj' => (float) ($r['tahsil_stopaj'] ?? 0),
            'tahsil_kdv'    => (float) ($r['tahsil_kdv'] ?? 0),
            'tahsil_adet'   => (int) ($r['tahsil_adet'] ?? 0),
        ];
    }

    /**
     * YILLIK SÖZLEŞME ÜCRETİ PROJEKSİYONU
     *
     * Mükellef kartlarına girilen yıllık ücretler "makbuza dönüşmüş" kabul
     * edilir; hasılat, stopaj ve KDV bunlardan hesaplanır. Böylece mali
     * müşavir yıl sonunu beklemeden yıllık vergi yükünü görebilir.
     *
     * Ücret BRÜT kabul edilir:
     *     stopaj = ücret × gv_ucret_stopaj_oran
     *     kdv    = ücret × gv_ucret_kdv_oran
     *
     * Kapsam (kullanıcı kararı): o yıl ÜCRETİ GİRİLMİŞ tüm mükellefler —
     * terk etmiş (pasif) olsalar da ücret kaydı varsa sayılır.
     *
     * @return array{brut:float,stopaj:float,kdv:float,net:float,adet:int,mukellefler:array}
     */
    public function ucretToplami(int $musavirId, int $yil): array
    {
        $rows = $this->db->table('mukellef_ucretleri u')
            ->select('u.mukellef_id, u.tutar, m.unvan, m.kod, m.aktif,
                      m.vergi_kimlik_no, m.tc_kimlik_no')
            ->join('mukellefler m', 'm.id = u.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('m.musavir_id', $musavirId)
            ->where('u.yil', $yil)
            ->where('u.tutar >', 0)
            ->orderBy('u.tutar', 'DESC')
            ->get()->getResultArray();

        $stopajOran = $this->ucretStopajOran();
        $kdvOran    = $this->ucretKdvOran();

        $brut = 0.0;
        $liste = [];

        foreach ($rows as $r) {
            $t = (float) $r['tutar'];
            $brut += $t;

            $liste[] = [
                'mukellef_id' => (int) $r['mukellef_id'],
                'unvan'       => $r['unvan'],
                'kod'         => $r['kod'],
                'aktif'       => (int) $r['aktif'],
                'vkn'         => $r['vergi_kimlik_no'] ?: $r['tc_kimlik_no'],
                'ucret'       => $t,
                'stopaj'      => round($t * $stopajOran / 100, 2),
                'kdv'         => round($t * $kdvOran / 100, 2),
            ];
        }

        $brut = round($brut, 2);

        // Toplamlar satır bazında değil, TOPLAM üzerinden yuvarlanır;
        // böylece kuruş farkları birikmez.
        $stopaj = round($brut * $stopajOran / 100, 2);
        $kdv    = round($brut * $kdvOran / 100, 2);

        return [
            'brut'        => $brut,
            'stopaj'      => $stopaj,
            'kdv'         => $kdv,
            'net'         => round($brut - $stopaj + $kdv, 2),
            'adet'        => count($liste),
            'stopaj_oran' => $stopajOran,
            'kdv_oran'    => $kdvOran,
            'mukellefler' => $liste,
        ];
    }

    /** Müşavirin aylık makbuz dağılımı (grafik / döküm için) */
    public function aylikDagilim(int $musavirId, int $yil): array
    {
        $rows = $this->db->table('makbuzlar mk')
            ->select('mk.ay, COUNT(*) AS adet, COALESCE(SUM(mk.brut),0) AS brut,
                      COALESCE(SUM(mk.stopaj),0) AS stopaj')
            ->join('mukellefler m', 'm.id = mk.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('mk.musavir_id', $musavirId)
            ->where('mk.yil', $yil)
            ->groupBy('mk.ay')
            ->get()->getResultArray();

        $out = [];

        for ($a = 1; $a <= 12; $a++) {
            $out[$a] = ['ay' => $a, 'adet' => 0, 'brut' => 0.0, 'stopaj' => 0.0];
        }

        foreach ($rows as $r) {
            $ay = (int) $r['ay'];

            if ($ay >= 1 && $ay <= 12) {
                $out[$ay] = [
                    'ay'     => $ay,
                    'adet'   => (int) $r['adet'],
                    'brut'   => (float) $r['brut'],
                    'stopaj' => (float) $r['stopaj'],
                ];
            }
        }

        return $out;
    }

    // =================================================================
    //  HESAP MOTORU
    // =================================================================

    /**
     * Bir müşavirin yıllık gelir vergisi hesabı.
     *
     * @param array|null $gecici Kaydedilmemiş "canlı" değerler (AJAX önizleme).
     *                           Verilirse veritabanı kaydının yerine geçer.
     */
    public function hesapla(int $musavirId, int $yil, ?array $gecici = null): array
    {
        $kayit   = $gecici === null ? $this->kayitAl($musavirId, $yil) : ($gecici + self::BOS_KAYIT);
        $makbuz  = $this->makbuzToplami($musavirId, $yil);
        $ucret   = $this->ucretToplami($musavirId, $yil);
        $kaynak  = $this->hasilatKaynagi();

        // HESAP KİPİ
        //   ucret  → yıllık sözleşme ücretleri makbuza dönüşmüş sayılır
        //            (yıllık projeksiyon; varsayılan)
        //   makbuz → yalnızca fiilen kesilen makbuzlar
        $kip = ($kayit['hesap_kipi'] ?? 'ucret') === 'makbuz' ? 'makbuz' : 'ucret';

        // --- Hasılat -------------------------------------------------
        if ($kip === 'ucret') {
            $otoHasilat = $ucret['brut'];
            $otoStopaj  = $ucret['stopaj'];
        } else {
            $otoHasilat = $kaynak === 'tahsil' ? $makbuz['tahsil_brut'] : $makbuz['brut'];
            $otoStopaj  = $kaynak === 'tahsil' ? $makbuz['tahsil_stopaj'] : $makbuz['stopaj'];
        }

        $hasilatElle = $kayit['hasilat_elle'] ?? null;
        $stopajElle  = $kayit['stopaj_elle'] ?? null;

        $hasilat = ($hasilatElle !== null && $hasilatElle !== '') ? (float) $hasilatElle : $otoHasilat;
        $stopaj  = ($stopajElle !== null && $stopajElle !== '') ? (float) $stopajElle : $otoStopaj;

        // --- Kazanç ---------------------------------------------------
        //
        // Gider iki kaynaktan gelir ve TOPLANIR (kullanıcı kararı):
        //   elle girilen "Toplam Mesleki Gider" + aylık gider tablosu
        // Böylece bir kısmını toplu, bir kısmını ay ay tutan büro da doğru
        // sonuç alır. Liste, elle girileni EZMEZ.
        $giderElle  = (float) ($kayit['gider'] ?? 0);
        $giderAylik = (new AylikGiderModel())->yillikToplam($musavirId, $yil);

        $gider  = round($giderElle + $giderAylik['toplam'], 2);
        $kazanc = round($hasilat - $gider, 2);

        // --- İndirimler (GVK md.89) -----------------------------------
        //
        // Bağ-Kur/SGK primi sınırsız indirilir. Şahıs sigorta primi ile
        // eğitim-sağlık harcaması ise "beyan edilecek kâr"ın belirli bir
        // yüzdesini AŞAMAZ.
        //
        // TABAN (19. güncellemede değişti — kullanıcı kararı):
        //   Kazanç − Bağ-Kur/SGK primi
        // Yani önce Bağ-Kur indirilir, kalan tutarın %15 / %10'u tavan olur.
        //
        // Sınır KAYDEDİLMEZ, her hesapta yeniden uygulanır — gider, hasılat
        // veya Bağ-Kur değişince tavan da değişmelidir.
        $bagkur = (float) ($kayit['bagkur'] ?? 0);

        // ÖNCELİK KURALI (kullanıcı kararı):
        //   Kalem listesinde satır VARSA → liste toplamı esas alınır
        //   Liste BOŞSA                  → elle girilen tutar kullanılır
        // Böylece liste kullanmayan büro etkilenmez, eski kayıt bozulmaz.
        $kalemler = (new IndirimKalemModel())->toplamlar($musavirId, $yil);

        $sigortaListe = $kalemler['sigorta'];
        $egitimListe  = $kalemler['egitim_saglik'];

        $sigortaElle = (float) ($kayit['sigorta_primi'] ?? 0);
        $egitimElle  = (float) ($kayit['egitim_saglik'] ?? 0);

        $sigortaTalep = $sigortaListe['adet'] > 0 ? $sigortaListe['toplam'] : $sigortaElle;
        $egitimTalep  = $egitimListe['adet'] > 0 ? $egitimListe['toplam'] : $egitimElle;

        // Bağ-Kur düşüldükten SONRAKİ tutar taban alınır
        $taban = max(0, round($kazanc - $bagkur, 2));

        $sigortaTavan = round($taban * $this->sigortaOran() / 100, 2);
        $egitimTavan  = round($taban * $this->egitimSaglikOran() / 100, 2);

        $sigorta = min($sigortaTalep, $sigortaTavan);
        $egitim  = min($egitimTalep, $egitimTavan);

        // Sınır yüzünden kullanılamayan kısım (ekranda uyarı olarak gösterilir)
        $sigortaAsim = round(max(0, $sigortaTalep - $sigorta), 2);
        $egitimAsim  = round(max(0, $egitimTalep - $egitim), 2);

        $indirimToplam = round($bagkur + $sigorta + $egitim, 2);

        // Matrah negatif olmaz; indirimler kazancı aşarsa matrah sıfırlanır
        $matrah = round(max(0, $kazanc - $indirimToplam), 2);

        // --- Tarife --------------------------------------------------
        $tarife = new VergiTarifeModel();
        $t      = $tarife->vergiHesapla($matrah, $yil, false);
        $vergi  = $t['vergi'];

        // --- %5 uyumlu mükellef indirimi (GVK mük.121) ---------------
        $uyumluAcik  = ! empty($kayit['uyumlu_indirim']);
        $uyumluOran  = $this->uyumluOran();
        $uyumluSinir = $this->uyumluUstSinir();
        $uyumlu      = 0.0;

        if ($uyumluAcik && $vergi > 0) {
            $uyumlu = round(min($vergi * $uyumluOran / 100, $uyumluSinir), 2);
        }

        $odenmesiGereken = round(max(0, $vergi - $uyumlu), 2);

        // --- Mahsuplar ve KDV -----------------------------------------
        //
        // KDV MANTIĞI (18. güncellemede düzeltildi):
        //
        //   Makbuz kesildiğinde KDV YÜKÜMLÜLÜĞÜ doğar (makbuzların KDV toplamı).
        //   Mali müşavir bu KDV'nin bir kısmını/tamamını yıl içinde öder;
        //   aylık KDV tablosuna FİİLEN ÖDEDİĞİ tutarı girer. Ödeme, tablodaki
        //   AY TOPLAMIDIR: "ödenen + indirilecek" (tfoot genel toplamı).
        //
        //     Kalan KDV borcu = Makbuz KDV yükümlülüğü − Ödenen KDV (ay toplamı)
        //
        //   Negatif çıkarsa (fazla ödeme) KDV ALACAĞI olur ve yükü azaltır.
        //
        // Örnek: makbuzlarda 4.000 KDV var, 3.000'i ödenmiş → 1.000 borç kaldı.
        //        Gelir vergisinden 1.000 alacak varsa ikisi mahsuplaşır → 0.
        $digerMahsup = (float) ($kayit['diger_mahsup'] ?? 0);

        // Ödenen KDV = aylık tablonun AY TOPLAMI, yani "ödenen + indirilecek".
        //
        // Kullanıcı kararı: tablodaki iki sütun birlikte o ayın KDV ödemesini
        // oluşturur; tfoot'taki genel toplam mahsuba girer.
        $kdv       = (new KdvModel())->yillikToplam($musavirId, $yil);
        $kdvOdenen = $kdv['toplam'];   // odenen + indirilecek

        // Yükümlülük, hasılat/stopajla AYNI kaynaktan gelir.
        //   ucret kipinde  → yıllık ücretlerden doğan KDV
        //   makbuz kipinde → kesilen makbuzların KDV'si (tahsil ayarına uyar)
        $kdvYukumluluk = $kip === 'ucret'
            ? $ucret['kdv']
            : ($kaynak === 'tahsil' ? $makbuz['tahsil_kdv'] : $makbuz['kdv']);

        // Kalan borç: − ise fazla ödeme (KDV alacağı)
        $kdvKalan = round($kdvYukumluluk - $kdvOdenen, 2);

        // Net mahsup: stopaj + diğer − kalan KDV borcu
        $mahsupTop = round($stopaj + $digerMahsup - $kdvKalan, 2);

        $sonuc = round($odenmesiGereken - $mahsupTop, 2);

        // --- Yıl içi vergi yükü ---------------------------------------
        //
        // Kullanıcının asıl görmek istediği rakam: "yıl içinde devlete net
        // ne kadar ödeyeceğim?" Gelir vergisi ile KDV birlikte değerlendirilir.
        //
        //   Gelir vergisi dengesi = Ödenmesi gereken vergi − Stopaj − Diğer
        //     negatifse devletten ALACAK, pozitifse BORÇ
        //   Vergi yükü            = Gelir vergisi dengesi + Kalan KDV borcu
        //
        // Örnek: vergi 3.000, stopaj 4.000 → 1.000 alacak;
        //        makbuz KDV 4.000, ödenen 3.000 → 1.000 KDV borcu kaldı;
        //        yük = −1.000 + 1.000 = 0 (alacak-verecek yok).
        // Bu, $sonuc ile aynı sayıdır; ayrı alanlar ekranda kırılımı
        // gösterebilmek için tutulur.
        $gvDenge = round($odenmesiGereken - $stopaj - $digerMahsup, 2);

        return [
            // Yıl içi vergi yükü kırılımı
            'gv_denge'   => $gvDenge,            // − ise devletten alacak
            'gv_alacak'  => max(0, -$gvDenge),
            'gv_borc'    => max(0, $gvDenge),
            'vergi_yuku' => $sonuc,              // GV dengesi + KDV

            'yil'        => $yil,
            'musavir_id' => $musavirId,

            // Gelir tarafı
            'hasilat'      => $hasilat,
            'hasilat_oto'  => $otoHasilat,
            'hasilat_elle' => $hasilatElle,
            'kaynak'       => $kaynak,
            'kip'          => $kip,
            'makbuz'       => $makbuz,
            'ucret'        => $ucret,
            'ucret_brut'   => $ucret['brut'],
            'ucret_stopaj' => $ucret['stopaj'],
            'ucret_kdv'    => $ucret['kdv'],
            'ucret_adet'   => $ucret['adet'],
            'ucret_stopaj_oran' => $ucret['stopaj_oran'],
            'ucret_kdv_oran'    => $ucret['kdv_oran'],

            // Gider / matrah
            'gider'           => $gider,
            'gider_elle'      => $giderElle,
            'gider_aylik'     => $giderAylik['toplam'],
            'gider_ay_sayisi' => $giderAylik['ay_sayisi'],
            'kazanc'          => $kazanc,
            'bagkur'         => $bagkur,
            'indirim_toplam' => $indirimToplam,
            'matrah'         => $matrah,

            // Sınırlı indirimler (GVK md.89)
            'sigorta'        => $sigorta,
            'sigorta_talep'  => $sigortaTalep,
            'sigorta_liste'  => $sigortaListe['adet'] > 0,
            'sigorta_adet'   => $sigortaListe['adet'],
            'sigorta_turler' => $sigortaListe['turler'],
            'sigorta_tavan'  => $sigortaTavan,
            'sigorta_asim'   => $sigortaAsim,
            'sigorta_oran'   => $this->sigortaOran(),
            'indirim_taban'  => $taban,   // kazanç − Bağ-Kur
            'egitim'         => $egitim,
            'egitim_talep'   => $egitimTalep,
            'egitim_liste'   => $egitimListe['adet'] > 0,
            'egitim_adet'    => $egitimListe['adet'],
            'egitim_turler'  => $egitimListe['turler'],
            'egitim_tavan'   => $egitimTavan,
            'egitim_asim'    => $egitimAsim,
            'egitim_oran'    => $this->egitimSaglikOran(),

            // Vergi
            'vergi'         => $vergi,
            'dilim_no'      => $t['dilim_no'],
            'dilim'         => $t['dilim'],
            'kirilim'       => $t['kirilim'],
            'tarife_var'    => $t['tarife_var'],
            'ortalama_oran' => $matrah > 0 ? round($vergi / $matrah * 100, 2) : 0.0,

            // İndirim
            'uyumlu_acik'  => $uyumluAcik,
            'uyumlu_oran'  => $uyumluOran,
            'uyumlu'       => $uyumlu,
            'uyumlu_sinir' => $uyumluSinir,

            'odenmesi_gereken' => $odenmesiGereken,

            // Mahsup
            'stopaj'        => $stopaj,
            'stopaj_oto'    => $otoStopaj,
            'stopaj_elle'   => $stopajElle,
            'diger_mahsup'  => $digerMahsup,
            'mahsup_toplam' => $mahsupTop,

            // KDV
            'kdv'               => $kdvKalan,          // kalan borç (− ise alacak)
            'kdv_yukumluluk'    => $kdvYukumluluk,     // makbuzlardan doğan KDV
            'kdv_kalan'         => $kdvKalan,
            'kdv_borc'          => max(0, $kdvKalan),
            'kdv_alacak'        => max(0, -$kdvKalan), // fazla ödeme
            'kdv_odenen'        => $kdvOdenen,          // ödenen + indirilecek
            'kdv_odenen_sutun'  => $kdv['odenen'],      // yalnız "ödenen" sütunu
            'kdv_indirilecek'   => $kdv['indirilecek'], // yalnız "indirilecek" sütunu
            'kdv_ay_sayisi'   => $kdv['ay_sayisi'],

            // Sonuç: pozitif = ödenecek, negatif = iade
            'sonuc'    => $sonuc,
            'odenecek' => max(0, $sonuc),
            'iade'     => max(0, -$sonuc),

            'kayit' => $kayit,
        ];
    }

    /**
     * Birden çok müşavir için hesap (liste ekranı).
     *
     * @param int[]|null $musavirIdler null = tümü
     */
    public function toplu(int $yil, ?array $musavirIdler = null): array
    {
        $b = $this->db->table('musavirler')->select('id, ad_soyad, renk');

        // NOT: musavirler tablosunda `deleted_at` YOKTUR (mukellefler'de vardır).
        // Sütunlar sürüme göre değişebildiği için varlık kontrolüyle eklenir.
        $alanlar = $this->db->getFieldNames('musavirler');

        if (in_array('deleted_at', $alanlar, true)) {
            $b->where('deleted_at', null);
        }

        if (in_array('aktif', $alanlar, true)) {
            $b->where('aktif', 1);
        }

        if ($musavirIdler !== null && $musavirIdler !== []) {
            $b->whereIn('id', array_map('intval', $musavirIdler));
        }

        $out = [];

        foreach ($b->orderBy('ad_soyad', 'ASC')->get()->getResultArray() as $m) {
            $h = $this->hesapla((int) $m['id'], $yil);

            $h['ad_soyad'] = $m['ad_soyad'];
            $h['renk']     = $m['renk'] ?: '#94a3b8';
            $out[]         = $h;
        }

        return $out;
    }

    /** Liste toplamı */
    public function topluOzet(array $satirlar): array
    {
        $o = [
            'musavir' => count($satirlar), 'hasilat' => 0.0, 'gider' => 0.0,
            'matrah' => 0.0, 'vergi' => 0.0, 'stopaj' => 0.0, 'kdv' => 0.0,
            'odenecek' => 0.0, 'iade' => 0.0, 'adet' => 0,
        ];

        foreach ($satirlar as $s) {
            $o['hasilat']  += $s['hasilat'];
            $o['gider']    += $s['gider'];
            $o['matrah']   += $s['matrah'];
            $o['vergi']    += $s['vergi'];
            $o['stopaj']   += $s['stopaj'];
            $o['kdv']      += $s['kdv'];
            $o['odenecek'] += $s['odenecek'];
            $o['iade']     += $s['iade'];
            $o['adet']     += $s['makbuz']['adet'];
        }

        foreach ($o as $k => $v) {
            if (is_float($v)) {
                $o[$k] = round($v, 2);
            }
        }

        return $o;
    }
}
