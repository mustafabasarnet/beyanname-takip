<?php

namespace App\Models;

use App\Libraries\SicilSureHesaplayici;
use CodeIgniter\Model;

/**
 * SİCİL — BİLDİRİM GÖREVLERİ
 *
 * Bir sicil değişikliğinden kurallar aracılığıyla üretilen, durumu takip
 * edilen görevler.
 *
 * Temel ilkeler:
 *   - Mükerrer koruma: DB unique (sicil_degisikligi_id, kural_id) + üretim
 *     öncesi kontrol (idempotent — tekrar üretimde çoğalmaz).
 *   - Son tarih üretim anında hesaplanıp YAZILIR (kural değişse bozulmaz).
 *   - "Süresi geçti/bugün/..." veri değil; görüntülemede türetilen
 *     hesaplanmış durumdur (sayaç/filtre olarak burada üretilir).
 */
class SicilGorevModel extends Model
{
    protected $table         = 'sicil_bildirim_gorevleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'sicil_degisikligi_id', 'kural_id', 'kurum_id', 'gorev_no', 'son_tarih',
        'asil_tarih', 'kaydirma_nedeni', 'durum', 'tamamlanma_tarihi',
        'yapan_id', 'not_metni',
    ];

    public const DURUMLAR = [
        'BEKLIYOR'   => 'Bekliyor',
        'HAZIR'      => 'Hazır',
        'GONDERILDI' => 'Gönderildi',
        'TAMAM'      => 'Tamamlandı',
        'GEREKSIZ'   => 'Gereksiz',
    ];

    /** Açık (henüz kapanmamış) görev durumları */
    public const ACIK_DURUMLAR = ['BEKLIYOR', 'HAZIR', 'GONDERILDI'];

    /** Durum geçiş haritası: kaynak → izinli hedefler */
    public const GECISLER = [
        'BEKLIYOR'   => ['HAZIR', 'GEREKSIZ'],
        'HAZIR'      => ['BEKLIYOR', 'GONDERILDI', 'GEREKSIZ'],
        'GONDERILDI' => ['HAZIR', 'TAMAM', 'GEREKSIZ'],
        'TAMAM'      => ['GONDERILDI'],
        'GEREKSIZ'   => ['BEKLIYOR'],
    ];

    // =================================================================
    //  ÜRETİM
    // =================================================================

    /**
     * Değişiklik için aktif kuralları işler ve görevleri üretir (idempotent).
     *
     * @param array $degisiklik sicil_degisiklikleri satırı (id, turu_id, degisiklik_tarihi)
     *
     * @return array üretilen görevler [{id, kurum_id, kurum_ad, kurum_kisa, son_tarih}]
     */
    public function degisiklikIcinUret(array $degisiklik, int $kaydedenId): array
    {
        $degId     = (int) $degisiklik['id'];
        $turId     = (int) $degisiklik['turu_id'];
        $baslangic = (string) $degisiklik['degisiklik_tarihi'];

        $kurallar = (new SicilKuralModel())->aktifKurallar($turId);
        $olusan   = [];
        $hesap    = new SicilSureHesaplayici();

        foreach ($kurallar as $kural) {
            // Mükerrer kontrol (unique öncesi anlaşılır biçimde atla)
            if ($this->gorevVarMi($degId, (int) $kural['id'])) {
                continue;
            }

            try {
                $sonuc = $hesap->hesapla(
                    $baslangic,
                    (string) $kural['sure_tipi'],
                    $kural['sure_deger'] !== null ? (int) $kural['sure_deger'] : null,
                    $kural['belirli_tarih']
                );
            } catch (\Throwable $e) {
                continue; // geçersiz kural → atla (yönetici düzeltir)
            }

            $id = (int) $this->insert([
                'sicil_degisikligi_id' => $degId,
                'kural_id'             => (int) $kural['id'],
                'kurum_id'             => (int) $kural['kurum_id'],
                'gorev_no'             => 'SG-' . date('Y') . '-' . str_pad((string) $degId, 5, '0', STR_PAD_LEFT),
                'son_tarih'            => $sonuc['son_tarih'],
                'asil_tarih'           => $sonuc['asil_tarih'],
                'kaydirma_nedeni'      => $sonuc['neden'],
                'durum'                => 'BEKLIYOR',
                'yapan_id'             => null,
            ]);

            if ($id > 0) {
                $olusan[] = [
                    'id'         => $id,
                    'kurum_id'   => (int) $kural['kurum_id'],
                    'kurum_ad'   => $kural['kurum_ad'] ?? (string) $kural['kurum_id'],
                    'kurum_kisa' => $kural['kurum_kisa'] ?? null,
                    'son_tarih'  => $sonuc['son_tarih'],
                ];
            }
        }

        return $olusan;
    }

    /** Aynı (değişiklik, kural) için görev var mı? */
    public function gorevVarMi(int $degisiklikId, int $kuralId): bool
    {
        return $this->where('sicil_degisikligi_id', $degisiklikId)
            ->where('kural_id', $kuralId)
            ->countAllResults() > 0;
    }

    // =================================================================
    //  LİSTE / DETAY
    // =================================================================

    /**
     * Görev listesi — kapsam + durum + zaman aralığı filtresiyle.
     *
     * @param array $f ['durum'(string|string[]),'aralik'(gecikti|bugun|ic3|ic7|ic15),
     *                  'kurum_id','mukellef_id','musavir_id','q']
     */
    public function listele(array $f = []): array
    {
        $b = $this->db->table('sicil_bildirim_gorevleri g')
            ->select("g.*, d.degisiklik_tarihi, d.konu AS deg_konu,
                      t.ad AS tur_ad, t.kod AS tur_kod,
                      m.unvan AS mukellef_unvan, m.vergi_kimlik_no, m.tc_kimlik_no,
                      m.musavir_id,
                      krm.ad AS kurum_ad, krm.kisa_ad AS kurum_kisa,
                      mus.ad_soyad AS musavir_adi, mus.renk AS musavir_renk,
                      kk.ad_soyad AS yapan_adi")
            ->join('sicil_degisiklikleri d', 'd.id = g.sicil_degisikligi_id')
            ->join('sicil_degisiklik_turleri t', 't.id = d.turu_id')
            ->join('mukellefler m', 'm.id = d.mukellef_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->join('kurumlar krm', 'krm.id = g.kurum_id')
            ->join('kullanicilar kk', 'kk.id = g.yapan_id', 'left')
            ->where('m.deleted_at', null)
            ->where('g.deleted_at', null);

        $this->kapsamUygula($b, $f['musavir_id'] ?? null);

        if (! empty($f['durum'])) {
            if (is_array($f['durum'])) {
                $b->whereIn('g.durum', array_values($f['durum']));
            } else {
                $b->where('g.durum', $f['durum']);
            }
        }

        if (! empty($f['kurum_id'])) {
            if (is_array($f['kurum_id'])) {
                $b->whereIn('g.kurum_id', array_map('intval', $f['kurum_id']));
            } else {
                $b->where('g.kurum_id', (int) $f['kurum_id']);
            }
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('d.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['degisiklik_id'])) {
            $b->where('g.sicil_degisikligi_id', (int) $f['degisiklik_id']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('m.unvan', $f['q'])
                ->orLike('krm.ad', $f['q'])
                ->orLike('m.vergi_kimlik_no', $f['q'])
                ->orLike('m.tc_kimlik_no', $f['q'])
              ->groupEnd();
        }

        $aralik = $f['aralik'] ?? null;

        if ($aralik !== null && $aralik !== '' && $aralik !== 'tumu') {
            $bugun = date('Y-m-d');
            $b->whereIn('g.durum', self::ACIK_DURUMLAR);

            switch ($aralik) {
                case 'gecikti':
                    $b->where('g.son_tarih <', $bugun);
                    break;
                case 'bugun':
                    $b->where('g.son_tarih', $bugun);
                    break;
                case 'ic3':
                    $b->where('g.son_tarih >=', $bugun)->where('g.son_tarih <=', date('Y-m-d', strtotime('+3 days')));
                    break;
                case 'ic7':
                    $b->where('g.son_tarih >=', $bugun)->where('g.son_tarih <=', date('Y-m-d', strtotime('+7 days')));
                    break;
                case 'ic15':
                    $b->where('g.son_tarih >=', $bugun)->where('g.son_tarih <=', date('Y-m-d', strtotime('+15 days')));
                    break;
            }
        }

        return $b->orderBy('(g.durum = "TAMAM" OR g.durum = "GEREKSIZ")', 'ASC', false)
            ->orderBy('g.son_tarih IS NULL', 'ASC', false)
            ->orderBy('g.son_tarih', 'ASC')
            ->orderBy('g.id', 'DESC')
            ->get()->getResultArray();
    }

    /** Tek görev (tüm bağlı adlarla). */
    public function detay(int $id): ?array
    {
        return $this->db->table('sicil_bildirim_gorevleri g')
            ->select("g.*, d.degisiklik_tarihi, d.konu AS deg_konu,
                      d.yeni_deger AS deg_yeni, d.eski_deger AS deg_eski,
                      t.ad AS tur_ad, t.kod AS tur_kod,
                      m.unvan AS mukellef_unvan, m.vergi_kimlik_no, m.tc_kimlik_no,
                      m.musavir_id,
                      krm.ad AS kurum_ad, krm.kisa_ad AS kurum_kisa,
                      mus.ad_soyad AS musavir_adi, mus.renk AS musavir_renk,
                      kk.ad_soyad AS yapan_adi")
            ->join('sicil_degisiklikleri d', 'd.id = g.sicil_degisikligi_id')
            ->join('sicil_degisiklik_turleri t', 't.id = d.turu_id')
            ->join('mukellefler m', 'm.id = d.mukellef_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->join('kurumlar krm', 'krm.id = g.kurum_id')
            ->join('kullanicilar kk', 'kk.id = g.yapan_id', 'left')
            ->where('g.id', $id)
            ->where('g.deleted_at', null)
            ->get()->getRowArray();
    }

    // =================================================================
    //  DURUM GEÇİŞLERİ
    // =================================================================

    /**
     * Durumu değiştirir; geçiş kuralları + otomatik damgalar.
     * GONDERILDI/TAMAM → yapan_id; TAMAM → tamamlanma; TAMAM'dan çıkış → temizle.
     */
    public function durumDegistir(int $id, string $hedef, int $kullaniciId): array
    {
        $g = $this->find($id);

        if ($g === null) {
            return ['durum' => false, 'mesaj' => 'Görev bulunamadı.', 'kayit' => null];
        }

        if (! isset(self::DURUMLAR[$hedef])) {
            return ['durum' => false, 'mesaj' => 'Geçersiz durum.', 'kayit' => null];
        }

        $kaynak = (string) $g['durum'];
        $izin   = self::GECISLER[$kaynak] ?? [];

        if ($kaynak !== $hedef && ! in_array($hedef, $izin, true)) {
            return ['durum' => false,
                    'mesaj' => "Bu geçiş yapılamaz ({$kaynak} → {$hedef}).",
                    'kayit' => null];
        }

        $veri = ['durum' => $hedef];

        if (in_array($hedef, ['GONDERILDI', 'TAMAM'], true)) {
            $veri['yapan_id'] = $kullaniciId;
        }

        if ($hedef === 'TAMAM') {
            $veri['tamamlanma_tarihi'] = date('Y-m-d H:i:s');
        } elseif ($kaynak === 'TAMAM') {
            $veri['tamamlanma_tarihi'] = null;
        }

        if (! $this->update($id, $veri)) {
            return ['durum' => false, 'mesaj' => 'Güncelleme başarısız.', 'kayit' => null];
        }

        return ['durum' => true, 'mesaj' => 'Durum güncellendi.', 'kayit' => $this->find($id)];
    }

    /** Görevi tamamla. */
    public function tamamla(int $id, int $kullaniciId): array
    {
        return $this->durumDegistir($id, 'TAMAM', $kullaniciId);
    }

    /** Görevi iptal/gereksiz yap. */
    public function iptalEt(int $id, int $kullaniciId): array
    {
        return $this->durumDegistir($id, 'GEREKSIZ', $kullaniciId);
    }

    /** Not güncelle (yetki controller'da). */
    public function notKaydet(int $id, ?string $not): bool
    {
        return $this->update($id, ['not_metni' => trim((string) $not) ?: null]);
    }

    // =================================================================
    //  SAYAÇLAR / YAKLAŞAN / GEÇEN
    // =================================================================

    /**
     * Günlük sayaçlar — gecikti/bugün/3/7/15/bekleyen/tamamlanan.
     *
     * @param int[]|null $musavirIdler kapsam (null = admin tümü)
     */
    public function sayaclar($musavirIdler = null): array
    {
        $bugun  = date('Y-m-d');
        $b3     = date('Y-m-d', strtotime('+3 days'));
        $b7     = date('Y-m-d', strtotime('+7 days'));
        $b15    = date('Y-m-d', strtotime('+15 days'));

        $b = $this->db->table('sicil_bildirim_gorevleri g')
            ->select("
                COUNT(*)                                        AS toplam,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI'))      AS bekleyen,
                SUM(g.durum = 'TAMAM')                          AS tamamlanan,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih < '{$bugun}')               AS gecikti,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih = '{$bugun}')               AS bugun,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih >= '{$bugun}' AND g.son_tarih <= '{$b3}')   AS ic3,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih >= '{$bugun}' AND g.son_tarih <= '{$b7}')   AS ic7,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih >= '{$bugun}' AND g.son_tarih <= '{$b15}')  AS ic15")
            ->join('sicil_degisiklikleri d', 'd.id = g.sicil_degisikligi_id')
            ->join('mukellefler m', 'm.id = d.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('g.deleted_at', null);

        $this->kapsamUygula($b, $musavirIdler);

        $r = $b->get()->getRowArray();

        return [
            'toplam'     => (int) $r['toplam'],
            'bekleyen'   => (int) $r['bekleyen'],
            'tamamlanan' => (int) $r['tamamlanan'],
            'gecikti'    => (int) $r['gecikti'],
            'bugun'      => (int) $r['bugun'],
            'ic3'        => (int) $r['ic3'],
            'ic7'        => (int) $r['ic7'],
            'ic15'       => (int) $r['ic15'],
        ];
    }

    /** Yaklaşan (bugün + N gün) açık görevler — dashboard. */
    public function yaklasanlar($musavirIdler = null, int $gun = 7, int $limit = 10): array
    {
        $b = $this->db->table('sicil_bildirim_gorevleri g')
            ->select("g.*, d.degisiklik_tarihi, t.ad AS tur_ad,
                      m.unvan AS mukellef_unvan,
                      krm.ad AS kurum_ad, krm.kisa_ad AS kurum_kisa")
            ->join('sicil_degisiklikleri d', 'd.id = g.sicil_degisikligi_id')
            ->join('sicil_degisiklik_turleri t', 't.id = d.turu_id')
            ->join('mukellefler m', 'm.id = d.mukellef_id')
            ->join('kurumlar krm', 'krm.id = g.kurum_id')
            ->where('m.deleted_at', null)
            ->where('g.deleted_at', null)
            ->whereIn('g.durum', self::ACIK_DURUMLAR)
            ->where('g.son_tarih <=', date('Y-m-d', strtotime("+{$gun} days")));

        $this->kapsamUygula($b, $musavirIdler);

        return $b->orderBy('g.son_tarih', 'ASC')->limit($limit)->get()->getResultArray();
    }

    /** Geciken açık görevler. */
    public function gecikmisler($musavirIdler = null, int $limit = 50): array
    {
        $b = $this->db->table('sicil_bildirim_gorevleri g')
            ->select("g.*, m.unvan AS mukellef_unvan,
                      krm.ad AS kurum_ad, krm.kisa_ad AS kurum_kisa")
            ->join('sicil_degisiklikleri d', 'd.id = g.sicil_degisikligi_id')
            ->join('mukellefler m', 'm.id = d.mukellef_id')
            ->join('kurumlar krm', 'krm.id = g.kurum_id')
            ->where('m.deleted_at', null)
            ->where('g.deleted_at', null)
            ->whereIn('g.durum', self::ACIK_DURUMLAR)
            ->where('g.son_tarih <', date('Y-m-d'));

        $this->kapsamUygula($b, $musavirIdler);

        return $b->orderBy('g.son_tarih', 'ASC')->limit($limit)->get()->getResultArray();
    }

    /** Yumuşak silme (yetki controller'da). */
    public function gorevSil(int $id): bool
    {
        return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    // =================================================================
    //  ORTAK
    // =================================================================

    protected function kapsamUygula($b, $musavirIdler): void
    {
        if ($musavirIdler === null || $musavirIdler === []) {
            return; // admin: tümü
        }

        $idler = array_values(array_map('intval', $musavirIdler));

        if ($idler !== []) {
            $b->whereIn('m.musavir_id', $idler);
        }
    }
}
