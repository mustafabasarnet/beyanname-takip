<?php

namespace App\Models;

use App\Libraries\TatilHesaplayici;
use CodeIgniter\Model;

/**
 * E-DEFTER BERAT TAKİBİ
 *
 * Mükellefin kartında seçilen döneme göre (AYLIK / UC_AYLIK) berat
 * dönemlerini üretir ve büro iş akışına göre adım adım takip eder.
 *
 * SON TARİH KURALI (Ayarlar'dan değiştirilebilir):
 *   Aylık    : ilgili ayı izleyen N. ayın son günü      (edefter_aylik_ay_sonra, varsayılan 3)
 *              örn. Mayıs 2026 → 31.08.2026
 *   Üç aylık : dönem bitişini izleyen N. ayın son günü  (edefter_ucaylik_ay_sonra, varsayılan 2)
 *              örn. 1. dönem (Oca-Mar) → 31.05.2026
 *   Bulunan tarihe tatil/hafta sonu kaydırması uygulanır.
 */
class EdefterTakipModel extends Model
{
    protected $table         = 'edefter_takip';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'mukellef_id', 'donem_tipi', 'yil', 'donem_no', 'donem_adi',
        'donem_baslangic', 'donem_bitis', 'yasal_son_tarih', 'son_tarih',
        'kaydirma_nedeni', 'durum', 'berat_tarihi', 'not_metni',
    ];

    public const DURUMLAR = [
        'BEKLIYOR'      => 'Bekliyor',
        'DEVAM'         => 'Devam Ediyor',
        'HAZIR'         => 'Hazır',
        'ONAYLANDI'     => 'Onaylandı',
        'YUKLENMEYECEK' => 'Yüklenmeyecek',
    ];

    public const DONEM_TIPLERI = [
        'AYLIK'    => 'Aylık',
        'UC_AYLIK' => 'Üç Aylık',
    ];

    /** Sayfa başına gösterilebilecek kayıt sayıları */
    public const SAYFA_ADETLERI = [25, 50, 100, 250];

    // =================================================================
    //  DÖNEM ÜRETİMİ
    // =================================================================

    /** Ayar okuma (varsayılanlı) */
    protected function ayar(string $anahtar, $varsayilan)
    {
        static $onbellek = [];

        if (! array_key_exists($anahtar, $onbellek)) {
            $onbellek[$anahtar] = (new AyarModel())->oku($anahtar, $varsayilan);
        }

        return $onbellek[$anahtar];
    }

    /**
     * Bir dönemin son tarihini hesaplar (kaydırma öncesi + sonrası).
     *
     * @return array{yasal:string,son:string,neden:?string}
     */
    public function sonTarihHesapla(
        string $donemBitis,
        string $donemTipi,
        string $mukellefTipi = 'tuzel'
    ): array {
        // ---------------------------------------------------------------
        //  Berat yükleme günü mükellef tipine göre değişir:
        //    Gelir vergisi mükellefi (gerçek kişi) → ayın 10'u
        //    Diğer mükellefler (kurumlar/tüzel)    → ayın 14'ü
        // ---------------------------------------------------------------
        $gercekKisi = $mukellefTipi === 'gercek';
        $gun        = $gercekKisi
            ? (int) $this->ayar('edefter_gun_gercek', 10)
            : (int) $this->ayar('edefter_gun_tuzel', 14);

        $bitisAy = (int) date('n', strtotime($donemBitis));

        // ---------------------------------------------------------------
        //  Kaç ay sonra?
        //
        //  Normal aylar:
        //    Aylık    → dönem ayı + 4    (örn. Ocak → Mayıs)
        //    Üç aylık → dönem bitişi + 3 (örn. Q1/Mart → Haziran)
        //
        //  ARALIK'ta biten dönemler İSTİSNADIR: berat, yıllık beyannamenin
        //  verileceği ayı takip eden ayda yüklenir.
        //    Gerçek kişi → gelir vergisi beyanı Mart  → Nisan  (+4)
        //    Tüzel kişi  → kurumlar beyanı      Nisan → Mayıs  (+5)
        //  Bu kural hem aylık hem üç aylık (Q4) için geçerlidir.
        // ---------------------------------------------------------------
        if ($bitisAy === 12) {
            $ayEkle = $gercekKisi
                ? (int) $this->ayar('edefter_aralik_gercek_ay', 4)
                : (int) $this->ayar('edefter_aralik_tuzel_ay', 5);
        } elseif ($donemTipi === 'UC_AYLIK') {
            $ayEkle = (int) $this->ayar('edefter_ucaylik_ay_sonra', 3);
        } else {
            $ayEkle = (int) $this->ayar('edefter_aylik_ay_sonra', 4);
        }

        // Ayın 1'i baz alınır ki 31'den ilerlerken ay taşması olmasın
        $baz   = date('Y-m-01', strtotime($donemBitis));
        $hedefAy = date('Y-m-01', strtotime($baz . ' +' . $ayEkle . ' month'));

        // İstenen gün o ayda yoksa (örn. 30 Şubat) ayın son gününe düşülür
        $ayinSonu = (int) date('t', strtotime($hedefAy));
        $hedef    = date('Y-m-', strtotime($hedefAy)) . sprintf('%02d', min($gun, $ayinSonu));

        $tatil = new TatilHesaplayici();
        $sonuc = $tatil->ilkIsGunu($hedef);

        return [
            'yasal' => $hedef,
            'son'   => $sonuc['tarih'],
            'neden' => $sonuc['kaydirildi'] ? $sonuc['neden'] : null,
        ];
    }

    /**
     * Verilen yıl için bir mükellefin e-defter dönemlerini üretir.
     *
     * Kurallar:
     *  - edefter_donem = YOK ise hiçbir dönem üretilmez, işlenmemiş
     *    (BEKLIYOR) satırlar temizlenir.
     *  - Faaliyet aralığı (işe başlama / terk) ile kesişmeyen dönem üretilmez.
     *  - edefter_baslangic verilmişse öncesi üretilmez.
     *  - Kullanıcı tarafından işlenmiş satırlar KORUNUR; yalnızca tarihleri
     *    güncellenir (ayar veya tatil tanımı değişmiş olabilir).
     *
     * @return array{eklenen:int,guncellenen:int,silinen:int,korunan:int}
     */
    public function donemleriUret(int $mukellefId, int $yil, bool $eskiyiTemizle = true): array
    {
        $sonuc    = ['eklenen' => 0, 'guncellenen' => 0, 'silinen' => 0, 'korunan' => 0];
        $mukellef = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null) {
            return $sonuc;
        }

        $tip = $mukellef['edefter_donem'] ?? 'YOK';

        // Mevcut satırlar: anahtar = tip-donemNo
        $mevcutRows = $this->where('mukellef_id', $mukellefId)->where('yil', $yil)->findAll();
        $mevcut     = [];

        foreach ($mevcutRows as $r) {
            $mevcut[$r['donem_tipi'] . '-' . $r['donem_no']] = $r;
        }

        $hedefler = $tip === 'YOK' ? [] : $this->hedefDonemler($mukellef, $yil, $tip);
        $gorulen  = [];

        foreach ($hedefler as $h) {
            $anahtar   = $h['donem_tipi'] . '-' . $h['donem_no'];
            $gorulen[] = $anahtar;

            if (! isset($mevcut[$anahtar])) {
                $this->insert($h + ['mukellef_id' => $mukellefId, 'durum' => 'BEKLIYOR']);
                $sonuc['eklenen']++;

                continue;
            }

            $eski    = $mevcut[$anahtar];
            $degisti = $eski['son_tarih'] !== $h['son_tarih']
                || $eski['yasal_son_tarih'] !== $h['yasal_son_tarih']
                || $eski['donem_adi'] !== $h['donem_adi'];

            if ($degisti) {
                $this->update($eski['id'], [
                    'donem_adi'       => $h['donem_adi'],
                    'donem_baslangic' => $h['donem_baslangic'],
                    'donem_bitis'     => $h['donem_bitis'],
                    'yasal_son_tarih' => $h['yasal_son_tarih'],
                    'son_tarih'       => $h['son_tarih'],
                    'kaydirma_nedeni' => $h['kaydirma_nedeni'],
                ]);
                $sonuc['guncellenen']++;
            } else {
                $sonuc['korunan']++;
            }
        }

        // Artık geçersiz kalan ve HENÜZ İŞLENMEMİŞ satırları sil.
        // İşlenmiş olanlar (adım işaretli / durum ilerlemiş) korunur —
        // kullanıcı emeği silinmemeli.
        if ($eskiyiTemizle) {
            foreach ($mevcut as $anahtar => $r) {
                if (in_array($anahtar, $gorulen, true)) {
                    continue;
                }

                if ($r['durum'] !== 'BEKLIYOR' || $this->adimIsaretliMi((int) $r['id'])) {
                    continue;
                }

                $this->delete($r['id']);
                $sonuc['silinen']++;
            }
        }

        return $sonuc;
    }

    /** Bu takip kaydında işaretlenmiş adım var mı? */
    protected function adimIsaretliMi(int $takipId): bool
    {
        return $this->db->table('edefter_adim_durum')
            ->where('takip_id', $takipId)->where('tamam', 1)
            ->countAllResults() > 0;
    }

    /**
     * Bir yıl için üretilmesi gereken dönemleri döndürür.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function hedefDonemler(array $mukellef, int $yil, string $tip): array
    {
        $basla = $mukellef['ise_baslama_tarihi'] ?? null;
        $terk  = $mukellef['terk_tarihi'] ?? null;
        $eBas  = $mukellef['edefter_baslangic'] ?? null;
        $out   = [];

        $araliklar = $tip === 'UC_AYLIK'
            ? [[1, 1, 3], [2, 4, 6], [3, 7, 9], [4, 10, 12]]
            : array_map(static fn ($a) => [$a, $a, $a], range(1, 12));

        foreach ($araliklar as [$no, $ilkAy, $sonAy]) {
            $dBas = sprintf('%04d-%02d-01', $yil, $ilkAy);
            $dBit = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $yil, $sonAy)));

            // Faaliyet aralığıyla kesişmeyen dönem oluşmaz
            if ($basla !== null && $basla > $dBit) {
                continue;
            }

            if ($terk !== null && $terk !== '' && $terk < $dBas) {
                continue;
            }

            // E-defter takibi sonradan başlatıldıysa öncesi üretilmez
            if ($eBas !== null && $eBas !== '' && $eBas > $dBit) {
                continue;
            }

            $t = $this->sonTarihHesapla($dBit, $tip, $mukellef['mukellef_tipi'] ?? 'tuzel');

            $out[] = [
                'donem_tipi'      => $tip,
                'yil'             => $yil,
                'donem_no'        => $no,
                'donem_adi'       => $tip === 'UC_AYLIK'
                    ? $no . '. Dönem ' . $yil . ' (' . ayKisa($ilkAy) . '-' . ayKisa($sonAy) . ')'
                    : ayAdi($ilkAy) . ' ' . $yil,
                'donem_baslangic' => $dBas,
                'donem_bitis'     => $dBit,
                'yasal_son_tarih' => $t['yasal'],
                'son_tarih'       => $t['son'],
                'kaydirma_nedeni' => $t['neden'],
            ];
        }

        return $out;
    }

    /**
     * Tüm e-defter mükellefleri için toplu dönem üretimi.
     *
     * @return array{mukellef:int,eklenen:int,guncellenen:int,silinen:int}
     */
    public function topluUret(int $yil, $musavirId = null): array
    {
        $b = $this->db->table('mukellefler')
            ->select('id')
            ->where('deleted_at', null)
            ->where('aktif', 1)
            ->whereIn('edefter_donem', ['AYLIK', 'UC_AYLIK']);

        if (is_array($musavirId) && $musavirId !== []) {
            $b->whereIn('musavir_id', array_map('intval', $musavirId));
        } elseif (! is_array($musavirId) && $musavirId) {
            $b->where('musavir_id', (int) $musavirId);
        }

        $ozet = ['mukellef' => 0, 'eklenen' => 0, 'guncellenen' => 0, 'silinen' => 0];

        foreach ($b->get()->getResultArray() as $m) {
            $s = $this->donemleriUret((int) $m['id'], $yil);
            $ozet['mukellef']++;
            $ozet['eklenen']     += $s['eklenen'];
            $ozet['guncellenen'] += $s['guncellenen'];
            $ozet['silinen']     += $s['silinen'];
        }

        return $ozet;
    }

    // =================================================================
    //  SORGULAR
    // =================================================================

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

    /** cizelge() / cizelgeSayisi() ortak sorgusu */
    protected function cizelgeSorgusu(array $f)
    {
        $b = $this->select('edefter_takip.*,
                            m.unvan as mukellef_unvan, m.kod as mukellef_kod,
                            m.vergi_kimlik_no, m.tc_kimlik_no, m.defter_tipi,
                            m.edefter_sorumlu_id,
                            k.ad_soyad as sorumlu_adi,
                            mus.ad_soyad as musavir_adi, mus.renk as musavir_renk')
            ->join('mukellefler m', 'm.id = edefter_takip.mukellef_id')
            ->join('kullanicilar k', 'k.id = m.edefter_sorumlu_id', 'left')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->where('m.deleted_at', null);

        // -------------------------------------------------------------
        //  TARİH FİLTRESİ — iki mod, TEK EKSEN
        //
        //  'berat' (varsayılan): Yıl+Ay BERATIN YÜKLENECEĞİ tarihe bakar.
        //      "Mayıs 2026'da hangi beratları yükleyeceğim?"
        //      -> 2025/Aralık dönemi (son tarih 14.05.2026) BU LİSTEDE çıkar.
        //
        //  'donem': Yıl+Ay defterin AİT OLDUĞU döneme bakar.
        //      "2026 yılına ait defterler neler?"
        //
        //  ÖNEMLİ — düzeltilen kusur: eski sürümde yıl 'yil' kolonundan,
        //  ay ise son_tarih'ten okunuyordu. İki farklı eksen karıştığı için
        //  "2026 + Mayıs" filtresinde son tarihi 14.05.2027 olan 2026/Q4
        //  dönemi de listeleniyordu. Artık ikisi hep aynı eksende çalışır.
        // -------------------------------------------------------------
        $mod = ($f['tarih_modu'] ?? 'berat') === 'donem' ? 'donem' : 'berat';

        if ($mod === 'donem') {
            if (! empty($f['yil'])) {
                $b->where('edefter_takip.yil', (int) $f['yil']);
            }

            if (! empty($f['ay'])) {
                $b->where('MONTH(edefter_takip.donem_bitis)', (int) $f['ay']);
            }
        } else {
            if (! empty($f['yil'])) {
                $b->where('YEAR(edefter_takip.son_tarih)', (int) $f['yil']);
            }

            if (! empty($f['ay'])) {
                $b->where('MONTH(edefter_takip.son_tarih)', (int) $f['ay']);
            }
        }

        if (! empty($f['donem_tipi'])) {
            $b->where('edefter_takip.donem_tipi', $f['donem_tipi']);
        }

        if (! empty($f['durum'])) {
            $b->where('edefter_takip.durum', $f['durum']);
        }

        if (! empty($f['durum_liste']) && is_array($f['durum_liste'])) {
            $b->whereIn('edefter_takip.durum', $f['durum_liste']);
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('edefter_takip.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['sorumlu_id'])) {
            $b->where('m.edefter_sorumlu_id', (int) $f['sorumlu_id']);
        }

        if (! empty($f['musavir_id'])) {
            $this->musavirKosulu($b, $f['musavir_id']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('m.unvan', $f['q'])
                ->orLike('m.vergi_kimlik_no', $f['q'])
                ->orLike('m.kod', $f['q'])
              ->groupEnd();
        }

        if (! empty($f['gecikmis'])) {
            $b->where('edefter_takip.son_tarih <', date('Y-m-d'))
              ->whereNotIn('edefter_takip.durum', ['ONAYLANDI', 'YUKLENMEYECEK']);
        }

        return $b;
    }

    /** Çizelge listesi (adım işaretleri dahil) */
    public function cizelge(array $f): array
    {
        $b = $this->cizelgeSorgusu($f)
            ->orderBy('edefter_takip.son_tarih', 'ASC')
            ->orderBy('m.unvan', 'ASC');

        $rows = ! empty($f['limit'])
            ? $b->findAll((int) $f['limit'], (int) ($f['ofset'] ?? 0))
            : $b->findAll();

        return $this->adimlariBagla($rows);
    }

    public function cizelgeSayisi(array $f): int
    {
        return (int) $this->cizelgeSorgusu($f)->countAllResults();
    }

    /**
     * Satırlara adım işaretlerini ekler.
     * Tek sorguda çekilir; satır başına sorgu açılmaz.
     */
    protected function adimlariBagla(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $idler = array_map(static fn ($r) => (int) $r['id'], $rows);

        $isaret = $this->db->table('edefter_adim_durum')
            ->select('takip_id, adim_id, tamam, tamam_tarihi, tamamlayan_id')
            ->whereIn('takip_id', $idler)
            ->get()->getResultArray();

        $harita = [];

        foreach ($isaret as $i) {
            $harita[(int) $i['takip_id']][(int) $i['adim_id']] = [
                'tamam'         => (int) $i['tamam'] === 1,
                'tarih'         => $i['tamam_tarihi'],
                'tamamlayan_id' => $i['tamamlayan_id'],
            ];
        }

        $adimlar = (new EdefterAdimModel())->aktifler();
        $toplam  = count($adimlar);

        foreach ($rows as &$r) {
            $tid          = (int) $r['id'];
            $r['adimlar'] = [];
            $tamamSayi    = 0;

            foreach ($adimlar as $a) {
                $aid   = (int) $a['id'];
                $bilgi = $harita[$tid][$aid] ?? null;
                $ok    = $bilgi !== null && $bilgi['tamam'];

                if ($ok) {
                    $tamamSayi++;
                }

                $r['adimlar'][] = [
                    'id'    => $aid,
                    'kod'   => $a['kod'],
                    'ad'    => $a['ad'],
                    'ikon'  => $a['ikon'],
                    'tamam' => $ok,
                    'tarih' => $bilgi['tarih'] ?? null,
                ];
            }

            $r['adim_tamam']  = $tamamSayi;
            $r['adim_toplam'] = $toplam;
            $r['ilerleme']    = $toplam > 0 ? (int) round($tamamSayi / $toplam * 100) : 0;
        }

        unset($r);

        return $rows;
    }

    /** Tek kayıt + adımları */
    public function detay(int $id): ?array
    {
        $rows = $this->adimlariBagla($this->cizelgeSorgusu(['mukellef_id' => 0])
            ->where('edefter_takip.id', $id)->findAll());

        return $rows[0] ?? null;
    }

    // =================================================================
    //  ADIM İŞARETLEME
    // =================================================================

    /**
     * Bir adımı işaretler / kaldırır ve kaydın durumunu yeniden hesaplar.
     *
     * @return array{durum:string,ilerleme:int,adim_tamam:int,adim_toplam:int}
     */
    public function adimIsaretle(int $takipId, int $adimId, bool $tamam, ?int $kullaniciId = null): array
    {
        $tablo = $this->db->table('edefter_adim_durum');
        $var   = $tablo->where('takip_id', $takipId)->where('adim_id', $adimId)->get()->getRowArray();

        $veri = [
            'tamam'         => $tamam ? 1 : 0,
            'tamamlayan_id' => $tamam ? $kullaniciId : null,
            'tamam_tarihi'  => $tamam ? date('Y-m-d H:i:s') : null,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        if ($var === null) {
            $tablo->insert($veri + [
                'takip_id'   => $takipId,
                'adim_id'    => $adimId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $tablo->where('id', $var['id'])->update($veri);
        }

        return $this->durumYenile($takipId);
    }

    /**
     * Adım işaretlerine bakarak kaydın durumunu günceller.
     *
     * Kural:
     *   Onay adımı işaretli            → ONAYLANDI
     *   Hazır adımı işaretli           → HAZIR
     *   En az bir adım işaretli        → DEVAM
     *   Hiçbiri                        → BEKLIYOR
     *
     * "Yüklenmeyecek" kullanıcı tarafından elle seçilir; adım işaretlemesi
     * bu durumu EZMEZ (bilinçli bir karardır).
     */
    public function durumYenile(int $takipId): array
    {
        $kayit = $this->find($takipId);

        if ($kayit === null) {
            return ['durum' => 'BEKLIYOR', 'ilerleme' => 0, 'adim_tamam' => 0, 'adim_toplam' => 0];
        }

        $adimModel = new EdefterAdimModel();
        $adimlar   = $adimModel->aktifler();
        $toplam    = count($adimlar);

        $isaretli = $this->db->table('edefter_adim_durum')
            ->select('adim_id')
            ->where('takip_id', $takipId)->where('tamam', 1)
            ->get()->getResultArray();

        $isaretliIdler = array_map(static fn ($r) => (int) $r['adim_id'], $isaretli);

        // Yalnızca AKTİF adımlar sayılır (pasifleştirilen adım ilerlemeyi şişirmesin)
        $aktifIdler = array_map(static fn ($a) => (int) $a['id'], $adimlar);
        $sayilan    = array_intersect($isaretliIdler, $aktifIdler);
        $tamamSayi  = count($sayilan);

        $onayId  = $adimModel->idBul(EdefterAdimModel::ONAY_KODU);
        $hazirId = $adimModel->idBul(EdefterAdimModel::HAZIR_KODU);

        if ($kayit['durum'] === 'YUKLENMEYECEK') {
            $durum = 'YUKLENMEYECEK';
        } elseif ($onayId !== null && in_array($onayId, $sayilan, true)) {
            $durum = 'ONAYLANDI';
        } elseif ($hazirId !== null && in_array($hazirId, $sayilan, true)) {
            $durum = 'HAZIR';
        } elseif ($tamamSayi > 0) {
            $durum = 'DEVAM';
        } else {
            $durum = 'BEKLIYOR';
        }

        $guncelle = ['durum' => $durum];

        // Onay adımı ilk kez işaretlendiğinde berat tarihi düşülür,
        // geri alınınca temizlenir.
        if ($durum === 'ONAYLANDI' && empty($kayit['berat_tarihi'])) {
            $guncelle['berat_tarihi'] = date('Y-m-d');
        } elseif ($durum !== 'ONAYLANDI' && ! empty($kayit['berat_tarihi'])) {
            $guncelle['berat_tarihi'] = null;
        }

        $this->update($takipId, $guncelle);

        return [
            'durum'       => $durum,
            'durum_ad'    => self::DURUMLAR[$durum] ?? $durum,
            'ilerleme'    => $toplam > 0 ? (int) round($tamamSayi / $toplam * 100) : 0,
            'adim_tamam'  => $tamamSayi,
            'adim_toplam' => $toplam,
        ];
    }

    /** Tüm adımları tek seferde işaretler (satır sonundaki "hepsi" düğmesi) */
    public function hepsiniIsaretle(int $takipId, bool $tamam, ?int $kullaniciId = null): array
    {
        foreach ((new EdefterAdimModel())->aktifler() as $a) {
            $this->adimIsaretle($takipId, (int) $a['id'], $tamam, $kullaniciId);
        }

        return $this->durumYenile($takipId);
    }

    // =================================================================
    //  ÖZET / PANEL
    // =================================================================

    /**
     * Panel kartı için özet.
     * "Bu ay hangi beratlar yüklenecek, kaçı bitti?"
     */
    public function ozet(int $yil, ?int $ay = null, $musavirId = null, string $mod = 'berat'): array
    {
        $temel = function () use ($yil, $ay, $musavirId, $mod) {
            $b = $this->db->table('edefter_takip et')
                ->join('mukellefler m', 'm.id = et.mukellef_id')
                ->where('m.deleted_at', null);

            // Sayaçlar listeyle AYNI eksende olmalı (bkz. cizelgeSorgusu).
            if ($mod === 'donem') {
                $b->where('et.yil', $yil);

                if (! empty($ay)) {
                    $b->where('MONTH(et.donem_bitis)', (int) $ay);
                }
            } else {
                $b->where('YEAR(et.son_tarih)', $yil);

                if (! empty($ay)) {
                    $b->where('MONTH(et.son_tarih)', (int) $ay);
                }
            }

            return $this->musavirKosulu($b, $musavirId);
        };

        $sonuc = ['toplam' => (int) $temel()->countAllResults()];

        foreach (array_keys(self::DURUMLAR) as $d) {
            $sonuc[strtolower($d)] = (int) $temel()->where('et.durum', $d)->countAllResults();
        }

        $sonuc['gecikmis'] = (int) $temel()
            ->where('et.son_tarih <', date('Y-m-d'))
            ->whereNotIn('et.durum', ['ONAYLANDI', 'YUKLENMEYECEK'])
            ->countAllResults();

        // "Kalan" = henüz yüklenmemiş iş. Yüklenmeyecek olanlar sayılmaz.
        $sonuc['kalan']   = $sonuc['toplam'] - $sonuc['onaylandi'] - $sonuc['yuklenmeyecek'];
        $takipli          = $sonuc['toplam'] - $sonuc['yuklenmeyecek'];
        $sonuc['oran']    = $takipli > 0 ? (int) round($sonuc['onaylandi'] / $takipli * 100) : 100;
        $sonuc['verilen'] = $sonuc['onaylandi'];

        return $sonuc;
    }

    /**
     * Panel kartında gösterilecek dönem etiketi.
     * Seçilen ayda son tarihi dolan dönemlerin adı (örn. "2026.05").
     */
    public function donemEtiketi(int $yil, ?int $ay, $musavirId = null): ?string
    {
        // Panel kartı "bu ay ne yüklenecek" sorusuna cevap verir; bu yüzden
        // hem yıl hem ay BERAT SON TARİHİ ekseninde süzülür.
        $b = $this->db->table('edefter_takip et')
            ->select('et.donem_tipi, et.yil, et.donem_no,
                      MIN(et.donem_baslangic) AS bas, MAX(et.donem_bitis) AS bit')
            ->join('mukellefler m', 'm.id = et.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('YEAR(et.son_tarih)', $yil);

        if (! empty($ay)) {
            $b->where('MONTH(et.son_tarih)', (int) $ay);
        }

        $this->musavirKosulu($b, $musavirId);

        // Aylık önce, üç aylık sonra; her grup kendi içinde tarih sırasında
        $rows = $b->groupBy('et.donem_tipi, et.yil, et.donem_no')
            ->orderBy('et.donem_tipi', 'ASC')
            ->orderBy('bas', 'ASC')->get()->getResultArray();

        if ($rows === []) {
            return null;
        }

        // Etiket dönem TİPİNE göre yazılır.
        //
        // Düzeltilen kusur: eskiden yalnızca dönemin BAŞLANGIÇ ayı basılıyordu.
        // Üç aylık Q2 (Nisan-Haziran) için "2026.04" görünüyor, kullanıcı bunu
        // "Nisan dönemi" sanıyordu. Artık dönem tipi ve aralık belirtilir:
        //   Aylık    → "Aylık 2026.05"
        //   Üç aylık → "3 Aylık 2026.04-06"
        // Tek tip varsa ön ek yazılmaz (kart başlığı zaten dar).
        $aylik = [];
        $ucAy  = [];

        foreach ($rows as $r) {
            if ($r['donem_tipi'] === 'UC_AYLIK') {
                $ucAy[date('Y.m', strtotime($r['bas'])) . '-' . date('m', strtotime($r['bit']))] = true;
            } else {
                $aylik[date('Y.m', strtotime($r['bas']))] = true;
            }
        }

        $parcalar = [];
        $ikiTip   = $aylik !== [] && $ucAy !== [];

        if ($aylik !== []) {
            $parcalar[] = ($ikiTip ? 'Aylık ' : '') . implode(', ', array_keys($aylik));
        }

        if ($ucAy !== []) {
            $parcalar[] = ($ikiTip ? '3 Aylık ' : '') . implode(', ', array_keys($ucAy));
        }

        return implode(' · ', $parcalar);
    }

    /** Yaklaşan beratlar (panel listesi) */
    public function yaklasanlar(int $gun = 10, $musavirId = null, int $limit = 10): array
    {
        $b = $this->cizelgeSorgusu([])
            ->where('edefter_takip.son_tarih >=', date('Y-m-d'))
            ->where('edefter_takip.son_tarih <=', date('Y-m-d', strtotime("+{$gun} days")))
            ->whereNotIn('edefter_takip.durum', ['ONAYLANDI', 'YUKLENMEYECEK']);

        $this->musavirKosulu($b, $musavirId);

        return $b->orderBy('edefter_takip.son_tarih', 'ASC')->findAll($limit);
    }
}
