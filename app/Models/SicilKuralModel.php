<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SİCİL — BİLDİRİM KURALLARI
 *
 * "Hangi değişiklik türü → hangi kuruma → hangi sürede bildirilmeli"
 * eşlemesi. SÜRELER YALNIZCA BU TABLODA TUTULUR.
 *
 * Bütünlük:
 *   - Aynı (degisiklik_turu_id, kurum_id) ikinci kez tanımlanamaz
 *     (DB unique + kaydet öncesi kontrol).
 *   - Kural pasife alınınca yeni görev üretilmez; geçmiş görevler durur.
 */
class SicilKuralModel extends Model
{
    protected $table         = 'sicil_bildirim_kurallari';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'degisiklik_turu_id', 'kurum_id', 'sure_tipi', 'sure_deger',
        'belirli_tarih', 'oncelik', 'aciklama', 'aktif', 'olusturan_id',
    ];

    /** SicilSureHesaplayici::TIPLER ile aynı; ENUM tutarlılığı için sabit. */
    public const SURE_TIPLERI = ['GUN', 'IS_GUNU', 'TAKVIM_GUNU', 'AY', 'BELIRLI_TARIH'];

    protected $validationRules = [
        'degisiklik_turu_id' => 'required|is_natural_no_zero',
        'kurum_id'           => 'required|is_natural_no_zero',
        'sure_tipi'          => 'required|in_list[GUN,IS_GUNU,TAKVIM_GUNU,AY,BELIRLI_TARIH]',
        'sure_deger'         => 'permit_empty|is_natural',
        'belirli_tarih'      => 'permit_empty|valid_date[Y-m-d]',
        'aktif'              => 'permit_empty|in_list[0,1]',
    ];

    /** Kural listesi (tür + kurum adlarıyla). */
    public function listele(array $f = []): array
    {
        $b = $this->db->table('sicil_bildirim_kurallari k')
            ->select('k.*, t.ad AS tur_ad, t.kod AS tur_kod,
                      krm.ad AS kurum_ad, krm.kisa_ad AS kurum_kisa,
                      kk.ad_soyad AS olusturan_adi')
            ->join('sicil_degisiklik_turleri t', 't.id = k.degisiklik_turu_id')
            ->join('kurumlar krm', 'krm.id = k.kurum_id')
            ->join('kullanicilar kk', 'kk.id = k.olusturan_id', 'left');

        if (! empty($f['degisiklik_turu_id'])) {
            if (is_array($f['degisiklik_turu_id'])) {
                $b->whereIn('k.degisiklik_turu_id', array_map('intval', $f['degisiklik_turu_id']));
            } else {
                $b->where('k.degisiklik_turu_id', (int) $f['degisiklik_turu_id']);
            }
        }

        if (! empty($f['kurum_id'])) {
            if (is_array($f['kurum_id'])) {
                $b->whereIn('k.kurum_id', array_map('intval', $f['kurum_id']));
            } else {
                $b->where('k.kurum_id', (int) $f['kurum_id']);
            }
        }

        if (array_key_exists('aktif', $f) && $f['aktif'] !== null && $f['aktif'] !== '') {
            $b->where('k.aktif', (int) $f['aktif']);
        }

        return $b->orderBy('t.sira', 'ASC')
            ->orderBy('krm.ad', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Bir tür için AKTİF kurallar (görev üretiminde kullanılır).
     *
     * @return array<int,array> kurum_id anahtarlı (rapor/tekilleştirme kolay)
     */
    public function aktifKurallar(int $degisiklikTuruId): array
    {
        $rows = $this->db->table('sicil_bildirim_kurallari k')
            ->select('k.*, krm.ad AS kurum_ad, krm.kisa_ad AS kurum_kisa')
            ->join('kurumlar krm', 'krm.id = k.kurum_id')
            ->where('k.degisiklik_turu_id', $degisiklikTuruId)
            ->where('k.aktif', 1)
            ->orderBy('k.oncelik', 'ASC')
            ->get()->getResultArray();

        $harita = [];

        foreach ($rows as $r) {
            $harita[(int) $r['kurum_id']] = $r;
        }

        return $harita;
    }

    /** Aynı (tür, kurum) çifti zaten var mı? (unique öncesi anlaşılır hata) */
    public function ciftVarMi(int $turId, int $kurumId, ?int $haricId = null): bool
    {
        $b = $this->where('degisiklik_turu_id', $turId)
            ->where('kurum_id', $kurumId);

        if ($haricId !== null) {
            $b->where('id !=', $haricId);
        }

        return $b->countAllResults() > 0;
    }

    /**
     * Kuralı kaydeder; (tür,kurum) çakışmasında false döner (mesaj verir).
     *
     * @return array{durum:bool, id:?int, hata:?string}
     */
    public function kaydet(array $veri, ?int $id = null): array
    {
        $turId  = (int) ($veri['degisiklik_turu_id'] ?? 0);
        $kurId  = (int) ($veri['kurum_id'] ?? 0);

        if ($turId > 0 && $kurId > 0 && $this->ciftVarMi($turId, $kurId, $id)) {
            return ['durum' => false, 'id' => null,
                    'hata'  => 'Bu değişiklik türü için bu kurum zaten tanımlı.'];
        }

        // BELIRLI_TARIH dışında süre değeri zorunlu; belirli tarihte değer anlamsız
        $tip = $veri['sure_tipi'] ?? 'GUN';

        if ($tip === 'BELIRLI_TARIH') {
            $veri['sure_deger'] = null;
        } elseif (empty($veri['sure_deger'])) {
            return ['durum' => false, 'id' => null, 'hata' => 'Süre değeri gerekli.'];
        }

        if ($id > 0) {
            $ok = $this->update($id, $veri);
        } else {
            $ok = (bool) $this->insert($veri);
            $id = $ok ? (int) $this->getInsertID() : null;
        }

        return ['durum' => $ok, 'id' => $id, 'hata' => $ok ? null : ($this->errors() ?: null)];
    }

    /** Kuralı silmek yerine pasife alır (geçmiş görevler korunur). */
    public function pasifeAl(int $id): bool
    {
        return $this->update($id, ['aktif' => 0]);
    }
}
