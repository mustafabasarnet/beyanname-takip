<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * KİŞİSEL GÜNLÜK NOT + TO-DO — tamamen sahibine özel.
 *
 * GÜVENLİK İLKESİ: hiçbir sorgu "kullanıcı" filtreli çağrılmazsa kayıt
 * dönmez. Yönetici dahil hiçbir rol başkasının kaydına erişemez; tüm
 * metotlar kullaniciId ister ve yalnız o kullanıcının satırlarını döner.
 */
class KisiselNotModel extends Model
{
    protected $table         = 'kisisel_notlar';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'kullanici_id', 'tur', 'tarih', 'baslik', 'metin',
        'tamamlandi', 'tamamlandi_tarihi',
    ];

    /** Bir kullanıcının belirli gündeki günlük notu (yoksa null) */
    public function gunNotu(int $kullaniciId, string $tarih): ?array
    {
        return $this->where('kullanici_id', $kullaniciId)
            ->where('tur', 'not')
            ->where('tarih', $tarih)
            ->first();
    }

    /** Günlük notu kaydeder/günceller (aynı güne tek not). */
    public function gunNotuKaydet(int $kullaniciId, string $tarih, string $metin): int
    {
        $mevcut = $this->gunNotu($kullaniciId, $tarih);

        if ($metin === '') {
            // Boş gönderildiyse kaydı sil (temizlik)
            if ($mevcut !== null) {
                $this->delete((int) $mevcut['id']);
            }

            return 0;
        }

        if ($mevcut !== null) {
            $this->update((int) $mevcut['id'], ['metin' => $metin]);

            return (int) $mevcut['id'];
        }

        return (int) $this->insert([
            'kullanici_id' => $kullaniciId,
            'tur'          => 'not',
            'tarih'        => $tarih,
            'metin'        => $metin,
        ]);
    }

    /** Geçmiş günlük notlar (en yeni üstte) */
    public function gecmisNotlar(int $kullaniciId, int $limit = 20): array
    {
        return $this->where('kullanici_id', $kullaniciId)
            ->where('tur', 'not')
            ->orderBy('tarih', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /** Açık (tamamlanmamış) görevler — önce en yeni */
    public function acikGorevler(int $kullaniciId): array
    {
        return $this->where('kullanici_id', $kullaniciId)
            ->where('tur', 'gorev')
            ->where('tamamlandi', 0)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /** Tamamlanmış görevler — en yeni üstte */
    public function bitenGorevler(int $kullaniciId, int $limit = 50): array
    {
        return $this->where('kullanici_id', $kullaniciId)
            ->where('tur', 'gorev')
            ->where('tamamlandi', 1)
            ->orderBy('tamamlandi_tarihi', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /** Görev ekle */
    public function gorevEkle(int $kullaniciId, string $baslik, ?string $metin): int
    {
        return (int) $this->insert([
            'kullanici_id' => $kullaniciId,
            'tur'          => 'gorev',
            'baslik'       => $baslik,
            'metin'        => $metin === '' ? null : $metin,
            'tamamlandi'   => 0,
        ]);
    }

    /**
     * Görevi tamamlar / geri açar.
     *
     * @return bool başarı (kayıt yoksa veya başkasına aitse false)
     */
    public function gorevTersCevir(int $kullaniciId, int $gorevId): bool
    {
        $g = $this->where('id', $gorevId)
            ->where('kullanici_id', $kullaniciId)
            ->where('tur', 'gorev')
            ->first();

        if ($g === null) {
            return false;
        }

        $tamam = (int) $g['tamamlandi'] === 1 ? 0 : 1;

        return $this->update((int) $g['id'], [
            'tamamlandi'        => $tamam,
            'tamamlandi_tarihi' => $tamam === 1 ? date('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * Görevi / geçmiş notu siler. Yalnız sahibi silebilir.
     */
    public function kisiselSil(int $kullaniciId, int $id): bool
    {
        $kayit = $this->where('id', $id)->where('kullanici_id', $kullaniciId)->first();

        if ($kayit === null) {
            return false;
        }

        return $this->delete($id);
    }
}
