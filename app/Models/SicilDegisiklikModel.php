<?php

namespace App\Models;

use App\Libraries\SicilSureHesaplayici;
use CodeIgniter\Model;

/**
 * SİCİL — SİCİL DEĞİŞİKLİKLERİ (çekirdek kayıt / geçmiş)
 *
 * Bir mükellefin gerçekleşen değişikliği kalıcı geçmiş olarak saklanır.
 * Oluşturma TRANSACTION'lıdır: değişiklik + kurallardan üretilen görevler
 * tek adımda, ya hep ya hiç kaydedilir.
 *
 * Bütünlük ilkeleri:
 *   - Eski/yeni bilgiler değişse de geçmiş korunur; silme yalnız soft.
 *   - Değişiklik TARİHİ güncellenirse AÇIK görevlerin son tarihi kurallarına
 *     göre yeniden hesaplanır (kapanmış görevlere dokunulmaz).
 *   - Tür değiştirilemez (yeni değişiklik açılır — izlenebilirlik).
 */
class SicilDegisiklikModel extends Model
{
    protected $table         = 'sicil_degisiklikleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'mukellef_id', 'turu_id', 'degisiklik_tarihi', 'konu', 'aciklama',
        'eski_deger', 'yeni_deger', 'detay_alanlar', 'referans_no',
        'durum', 'kaydeden_id',
    ];

    public const DURUMLAR = [
        'BEKLIYOR' => 'Bekliyor',
        'ISLEMDE'  => 'İşlemde',
        'TAMAM'    => 'Tamamlandı',
    ];

    /** Formdan güncellenebilecek alanlar (kritik alanlar hariç: tür, mükellef) */
    public const GUNCELLENEBILIR = [
        'degisiklik_tarihi', 'konu', 'aciklama', 'eski_deger', 'yeni_deger',
        'detay_alanlar', 'referans_no',
    ];

    // =================================================================
    //  OLUŞTURMA (transaction + görev üretimi)
    // =================================================================

    /**
     * Değişikliği kaydeder ve aktif kurallardan görevleri üretir (atomik).
     *
     * @return array{durum:bool, id:?int, hata:?string,
     *               olusan_gorevler:array, kural_yoksa:bool}
     */
    public function olustur(array $veri, int $kaydedenId): array
    {
        $veri['kaydeden_id'] = $kaydedenId;
        $veri['durum']       = 'BEKLIYOR';

        $db = $this->db;
        $db->transBegin();

        try {
            $ok = $this->insert($veri);

            if (! $ok) {
                $db->transRollback();

                return ['durum' => false, 'id' => null,
                        'hata'  => $this->errors() ? implode(' ', $this->errors()) : 'Kayıt başarısız.',
                        'olusan_gorevler' => [], 'kural_yoksa' => false];
            }

            $id      = (int) $this->getInsertID();
            $kayit   = $this->find($id);
            $gorevler = (new SicilGorevModel())->degisiklikIcinUret($kayit, $kaydedenId);

            // Üst durum: görev oluştuysa İŞLEMDE (takip gerekir), yoksa BEKLIYOR
            $this->update($id, ['durum' => $gorevler === [] ? 'BEKLIYOR' : 'ISLEMDE']);

            $db->transCommit();

            return [
                'durum'           => true,
                'id'              => $id,
                'hata'            => null,
                'olusan_gorevler' => $gorevler,
                'kural_yoksa'     => $gorevler === [],
            ];
        } catch (\Throwable $e) {
            $db->transRollback();

            return ['durum' => false, 'id' => null, 'hata' => $e->getMessage(),
                    'olusan_gorevler' => [], 'kural_yoksa' => false];
        }
    }

    // =================================================================
    //  GÜNCELLEME
    // =================================================================

    /**
     * Değişikliği günceller.
     *
     * Tarih değiştiyse AÇIK görevlerin son tarihi kuralından yeniden
     * hesaplanır; kapanmış (TAMAM/GEREKSIZ) görevler aynen kalır.
     */
    public function guncelle(int $id, array $veri, int $kullaniciId): array
    {
        $mevcut = $this->find($id);

        if ($mevcut === null) {
            return ['durum' => false, 'hata' => 'Değişiklik bulunamadı.'];
        }

        // Yalnız güncellenebilir alanları al
        $temiz = array_intersect_key($veri, array_flip(self::GUNCELLENEBILIR));
        $temiz = array_filter($temiz, static fn ($v) => $v !== null);

        $eskiTarih = (string) $mevcut['degisiklik_tarihi'];
        $yeniTarih = (string) ($temiz['degisiklik_tarihi'] ?? $eskiTarih);

        $db = $this->db;
        $db->transBegin();

        try {
            if ($temiz !== []) {
                if (! $this->update($id, $temiz)) {
                    $db->transRollback();

                    return ['durum' => false, 'hata' => $this->errors() ? implode(' ', $this->errors()) : 'Güncelleme başarısız.'];
                }
            }

            // Tarih değiştiyse açık görevlerin son tarihini yeniden hesapla
            if ($yeniTarih !== $eskiTarih) {
                $gorevModel = new SicilGorevModel();
                $acik       = $gorevModel->where('sicil_degisikligi_id', $id)
                    ->whereIn('durum', SicilGorevModel::ACIK_DURUMLAR)
                    ->where('kural_id !=', null)
                    ->findAll();

                $kurallar = (new SicilKuralModel())->findAll();
                $kuralMap = [];

                foreach ($kurallar as $k) {
                    $kuralMap[(int) $k['id']] = $k;
                }

                $hesap = new SicilSureHesaplayici();

                foreach ($acik as $g) {
                    $kural = $kuralMap[(int) $g['kural_id']] ?? null;

                    if ($kural === null || (int) $kural['aktif'] !== 1) {
                        continue;
                    }

                    $sonuc = $hesap->hesapla(
                        $yeniTarih,
                        (string) $kural['sure_tipi'],
                        $kural['sure_deger'] !== null ? (int) $kural['sure_deger'] : null,
                        $kural['belirli_tarih']
                    );

                    $gorevModel->update((int) $g['id'], [
                        'son_tarih'       => $sonuc['son_tarih'],
                        'asil_tarih'      => $sonuc['asil_tarih'],
                        'kaydirma_nedeni' => $sonuc['neden'],
                    ]);
                }
            }

            // Üst durumu görevlerden türet
            $this->durumTure(id: $id);

            $db->transCommit();

            return ['durum' => true, 'hata' => null];
        } catch (\Throwable $e) {
            $db->transRollback();

            return ['durum' => false, 'hata' => $e->getMessage()];
        }
    }

    /**
     * Üst durumu görevlerden türetir (elle değiştirilmez):
     *  - açık görev varsa → ISLEMDE
     *  - tümü kapalıysa (TAMAM/GEREKSIZ) → TAMAM
     *  - görev yoksa → BEKLIYOR
     */
    public function durumTure(int $id): string
    {
        $acik = (new SicilGorevModel())->where('sicil_degisikligi_id', $id)
            ->whereIn('durum', SicilGorevModel::ACIK_DURUMLAR)
            ->countAllResults();

        $durum = $acik > 0 ? 'ISLEMDE' : 'BEKLIYOR';

        $toplamGorev = (new SicilGorevModel())->where('sicil_degisikligi_id', $id)->countAllResults();

        if ($toplamGorev > 0 && $acik === 0) {
            $durum = 'TAMAM';
        }

        $this->update($id, ['durum' => $durum]);

        return $durum;
    }

    // =================================================================
    //  LİSTE / DETAY / GEÇMİŞ
    // =================================================================

    /**
     * Liste — kapsam + filtrelerle. Açık görev sayısı ve en yakın son tarih
     * alt sorguyla eklenir.
     *
     * @param array $f ['yil','turu_id'(int|int[]),'durum','mukellef_id','musavir_id','q']
     */
    public function listele(array $f = []): array
    {
        $b = $this->listeBuilder();

        $this->kapsamUygula($b, $f['musavir_id'] ?? null);

        if (! empty($f['mukellef_id'])) {
            $b->where('d.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['turu_id'])) {
            if (is_array($f['turu_id'])) {
                $b->whereIn('d.turu_id', array_map('intval', $f['turu_id']));
            } else {
                $b->where('d.turu_id', (int) $f['turu_id']);
            }
        }

        if (! empty($f['durum'])) {
            $b->where('d.durum', $f['durum']);
        }

        if (! empty($f['yil'])) {
            $b->where('YEAR(d.degisiklik_tarihi)', (int) $f['yil']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('m.unvan', $f['q'])
                ->orLike('m.vergi_kimlik_no', $f['q'])
                ->orLike('m.tc_kimlik_no', $f['q'])
                ->orLike('d.konu', $f['q'])
                ->orLike('d.referans_no', $f['q'])
              ->groupEnd();
        }

        return $b->orderBy('d.degisiklik_tarihi', 'DESC')
            ->orderBy('d.id', 'DESC')
            ->get()->getResultArray();
    }

    /** Detay — görevleriyle birlikte. */
    public function detay(int $id, array $gorevFiltre = []): ?array
    {
        $satir = $this->listeBuilder()
            ->where('d.id', $id)
            ->get()->getRowArray();

        if ($satir === null) {
            return null;
        }

        $satir['gorevler'] = (new SicilGorevModel())->listele(
            array_merge($gorevFiltre, ['degisiklik_id' => $id])
        );

        return $satir;
    }

    /** Kronolojik geçmiş: bir mükellefin değişiklikleri (en yeni üstte). */
    public function gecmis(int $mukellefId, $musavirIdler = null): array
    {
        return $this->listele(['mukellef_id' => $mukellefId, 'musavir_id' => $musavirIdler]);
    }

    /** Özet sayaçlar (liste üstü). */
    public function ozet($musavirIdler = null): array
    {
        $satirlar = $this->listele(['musavir_id' => $musavirIdler]);
        $acik     = 0;

        foreach ($satirlar as $s) {
            $acik += (int) $s['acik_gorev'];
        }

        return [
            'toplam'     => count($satirlar),
            'acik_gorev' => $acik,
            'bekliyor'   => count(array_filter($satirlar, static fn ($x) => $x['durum'] === 'BEKLIYOR')),
            'islemde'    => count(array_filter($satirlar, static fn ($x) => $x['durum'] === 'ISLEMDE')),
            'tamam'      => count(array_filter($satirlar, static fn ($x) => $x['durum'] === 'TAMAM')),
        ];
    }

    /** Yumuşak silme (yalnız admin — yetki controller'da). */
    public function sicilSil(int $id): bool
    {
        return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Liste sorgusunun ortak başlangıcı (join + soft delete).
     */
    protected function listeBuilder()
    {
        return $this->db->table('sicil_degisiklikleri d')
            ->select("d.*, t.ad AS tur_ad, t.kod AS tur_kod,
                      m.unvan AS mukellef_unvan, m.vergi_kimlik_no, m.tc_kimlik_no,
                      m.musavir_id,
                      mus.ad_soyad AS musavir_adi, mus.renk AS musavir_renk,
                      kk.ad_soyad AS kaydeden_adi,
                      (SELECT COUNT(*) FROM sicil_bildirim_gorevleri ag
                        WHERE ag.sicil_degisikligi_id = d.id
                          AND ag.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                          AND ag.deleted_at IS NULL) AS acik_gorev,
                      (SELECT MIN(ag2.son_tarih) FROM sicil_bildirim_gorevleri ag2
                        WHERE ag2.sicil_degisikligi_id = d.id
                          AND ag2.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                          AND ag2.deleted_at IS NULL) AS en_yakin_son")
            ->join('sicil_degisiklik_turleri t', 't.id = d.turu_id')
            ->join('mukellefler m', 'm.id = d.mukellef_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->join('kullanicilar kk', 'kk.id = d.kaydeden_id', 'left')
            ->where('m.deleted_at', null)
            ->where('d.deleted_at', null);
    }

    protected function kapsamUygula($b, $musavirIdler): void
    {
        if ($musavirIdler === null || $musavirIdler === []) {
            return;
        }

        $idler = array_values(array_map('intval', $musavirIdler));

        if ($idler !== []) {
            $b->whereIn('m.musavir_id', $idler);
        }
    }
}
