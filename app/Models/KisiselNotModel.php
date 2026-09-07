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
    /** Görev öncelikleri (ağırlık sırası: acil > yuksek > normal > dusuk) */
    public const ONCELIKLER = ['dusuk', 'normal', 'yuksek', 'acil'];

    protected $table         = 'kisisel_notlar';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'kullanici_id', 'tur', 'tarih', 'baslik', 'metin', 'oncelik', 'etiket',
        'son_tarih', 'tamamlandi', 'tamamlandi_tarihi',
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

    /** Açık (tamamlanmamış) görevler — öncelik, sonra son tarih, sonra yeni */
    public function acikGorevler(int $kullaniciId): array
    {
        return $this->where('kullanici_id', $kullaniciId)
            ->where('tur', 'gorev')
            ->where('tamamlandi', 0)
            ->orderBy("FIELD(oncelik,'acil','yuksek','normal','dusuk')", 'ASC', false)
            ->orderBy('son_tarih', 'ASC', false)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /** Tamamlanmış görevler — en yeni üstte */
    public function bitenGorevler(int $kullaniciId, int $limit = 100): array
    {
        return $this->where('kullanici_id', $kullaniciId)
            ->where('tur', 'gorev')
            ->where('tamamlandi', 1)
            ->orderBy('tamamlandi_tarihi', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /** Görev öncelik/etiket/son_tarih girdilerini temizler */
    protected function temizleGorevAlani(string $oncelik, ?string $etiket, ?string $sonTarih): array
    {
        $oncelik = in_array($oncelik, self::ONCELIKLER, true) ? $oncelik : 'normal';
        $etiket  = trim((string) $etiket);

        if ($etiket === '') {
            $etiket = null;
        } else {
            $etiket = mb_substr($etiket, 0, 60);
        }

        $sonTarih = trim((string) $sonTarih);
        $sonTarih = preg_match('/^\d{4}-\d{2}-\d{2}$/', $sonTarih) ? $sonTarih : null;

        return [$oncelik, $etiket, $sonTarih];
    }

    /** Görev ekle */
    public function gorevEkle(
        int $kullaniciId,
        string $baslik,
        ?string $metin = null,
        string $oncelik = 'normal',
        ?string $etiket = null,
        ?string $sonTarih = null
    ): int {
        [$oncelik, $etiket, $sonTarih] = $this->temizleGorevAlani($oncelik, $etiket, $sonTarih);

        return (int) $this->insert([
            'kullanici_id' => $kullaniciId,
            'tur'          => 'gorev',
            'baslik'       => $baslik,
            'metin'        => trim((string) $metin) === '' ? null : trim((string) $metin),
            'oncelik'      => $oncelik,
            'etiket'       => $etiket,
            'son_tarih'    => $sonTarih,
            'tamamlandi'   => 0,
        ]);
    }

    /**
     * Görevi günceller (yalnız sahibi).
     *
     * @return bool kayıt yoksa / başkasına aitse false
     */
    public function gorevGuncelle(int $kullaniciId, int $gorevId, array $veri): bool
    {
        $g = $this->where('id', $gorevId)
            ->where('kullanici_id', $kullaniciId)
            ->where('tur', 'gorev')
            ->first();

        if ($g === null) {
            return false;
        }

        $yeni = [];

        if (array_key_exists('baslik', $veri)) {
            $baslik = trim((string) $veri['baslik']);

            if ($baslik === '') {
                return false;
            }

            $yeni['baslik'] = $baslik;
        }

        if (array_key_exists('metin', $veri)) {
            $m = trim((string) $veri['metin']);
            $yeni['metin'] = $m === '' ? null : $m;
        }

        if (array_key_exists('oncelik', $veri) || array_key_exists('etiket', $veri) || array_key_exists('son_tarih', $veri)) {
            [$o, $e, $s] = $this->temizleGorevAlani(
                (string) ($veri['oncelik'] ?? $g['oncelik'] ?? 'normal'),
                (string) ($veri['etiket'] ?? $g['etiket'] ?? ''),
                (string) ($veri['son_tarih'] ?? $g['son_tarih'] ?? '')
            );
            $yeni['oncelik']   = $o;
            $yeni['etiket']    = $e;
            $yeni['son_tarih'] = $s;
        }

        if ($yeni === []) {
            return true;
        }

        return $this->update($gorevId, $yeni);
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
