<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * KİŞİSEL YAPIŞKAN NOTLAR (kisisel_sticky_notlar)
 *
 * Tarihe bağlı olmayan, sürekli görünür kişisel kartlar.
 *
 * GİZLİLİK (kesin kural): Her sorgu giriş yapan kullanıcının id'siyle
 * süzülür. Yönetici için bile bypass YOKTUR (Ajanda kişisel kayıt kuralıyla
 * aynı). Başka kullanıcının kartına dokunan her işlem sessizce 0 satır
 * etkiler ve controller bunu "bulunamadı" olarak döner.
 */
class StickyNotModel extends Model
{
    protected $table         = 'kisisel_sticky_notlar';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'kullanici_id', 'baslik', 'metin', 'renk', 'sabit', 'sira',
        'hatirlat_tarih', 'arsiv_at',
    ];

    /** Kart metni en fazla bu kadar karakter. */
    public const MAX_METIN = 1000;

    /** Bir kullanıcının aktif kartı üst sınırı (kötüye kullanım / performans). */
    public const MAX_KART = 200;

    /**
     * Renk şablonları (referans görseldeki palet).
     * kart  : kâğıt rengi
     * bant  : üst şerit ve raptiye (koyu ton)
     * Yazı rengi HER renkte koyu tutulur (YAZI) → kontrast ≥ 4.5:1.
     */
    public const RENKLER = [
        'turkuaz' => ['ad' => 'Gök mavisi',  'kart' => '#7dd3ec', 'bant' => '#5bbcd9'],
        'nane'    => ['ad' => 'Nane yeşili', 'kart' => '#4ee8b0', 'bant' => '#34cf98'],
        'sari'    => ['ad' => 'Limon sarısı', 'kart' => '#ffee58', 'bant' => '#f2d93a'],
        'pembe'   => ['ad' => 'Pembe',       'kart' => '#ff7fa8', 'bant' => '#f25f8d'],
        'mavi'    => ['ad' => 'Gökyüzü',     'kart' => '#79a9ec', 'bant' => '#6198de'],
        'turuncu' => ['ad' => 'Turuncu',     'kart' => '#f9b041', 'bant' => '#e89a2a'],
    ];

    /** Kart yazı rengi (tüm paletlerde ortak). */
    public const YAZI = '#1f2937';

    /** Geçerli renk anahtarı mı (whitelist). */
    public static function renkGecerli(string $renk): bool
    {
        return array_key_exists($renk, self::RENKLER);
    }

    /**
     * Kartın eğim açısı (derece, -1.0 … +1.0). Kart id'sinden türetilir:
     * sayfa her yenilendiğinde aynı açıyı alır (titreşmez).
     */
    public static function egim(int $id): float
    {
        return (((($id * 37) % 21) - 10) / 10);
    }

    /**
     * Kullanıcının aktif kartları: önce sabitler, sonra elle belirlenen sıra.
     *
     * @return array<int,array<string,mixed>>
     */
    public function liste(int $kullaniciId): array
    {
        return $this->where('kullanici_id', $kullaniciId)
            ->where('arsiv_at', null)
            ->orderBy('sabit', 'DESC')
            ->orderBy('sira', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /** Aktif kart sayısı (üst sınır kontrolü için). */
    public function aktifSayi(int $kullaniciId): int
    {
        return (int) $this->where('kullanici_id', $kullaniciId)
            ->where('arsiv_at', null)
            ->countAllResults();
    }

    /**
     * Yeni kart ekler. Sıra: en sona (mevcut en büyük sıra + 1).
     *
     * @return int yeni kartın id'si (hata/limitte 0)
     */
    public function ekle(int $kullaniciId, string $metin, string $renk = 'sari'): int
    {
        if (! self::renkGecerli($renk)) {
            $renk = 'sari';
        }

        if ($this->aktifSayi($kullaniciId) >= self::MAX_KART) {
            return 0;
        }

        $enBuyuk = (int) $this->selectMax('sira')
            ->where('kullanici_id', $kullaniciId)
            ->get()->getRow('sira');

        $id = $this->insert([
            'kullanici_id' => $kullaniciId,
            'metin'        => mb_substr($metin, 0, self::MAX_METIN),
            'renk'         => $renk,
            'sabit'        => 0,
            'sira'         => $enBuyuk + 1,
        ], true);

        return (int) $id;
    }

    /**
     * Kartı günceller — YALNIZ sahibi için. Desteklenen alanlar: metin, renk, sabit.
     *
     * @return bool kart bulundu ve güncellendi mi
     */
    public function guncelle(int $kullaniciId, int $id, array $veri): bool
    {
        $izinli = [];

        if (array_key_exists('metin', $veri)) {
            $izinli['metin'] = mb_substr((string) $veri['metin'], 0, self::MAX_METIN);
        }

        if (array_key_exists('renk', $veri)) {
            $renk = (string) $veri['renk'];
            if (! self::renkGecerli($renk)) {
                return false;
            }
            $izinli['renk'] = $renk;
        }

        if (array_key_exists('sabit', $veri)) {
            $izinli['sabit'] = ((int) $veri['sabit']) === 1 ? 1 : 0;
        }

        if ($izinli === []) {
            return false;
        }

        $sahip = $this->sahibiMi($kullaniciId, $id);

        if (! $sahip) {
            return false;
        }

        // Sahiplik yukarıda doğrulandı. Değer aynıysa MySQL "etkilenen satır"
        // olarak 0 döndürür; bu bir hata değildir (aynı rengi tekrar seçmek gibi).
        $this->builder()
            ->where('id', $id)
            ->where('kullanici_id', $kullaniciId)
            ->update($izinli + ['updated_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    /** Kart bu kullanıcıya mı ait? */
    public function sahibiMi(int $kullaniciId, int $id): bool
    {
        return $this->where('id', $id)
            ->where('kullanici_id', $kullaniciId)
            ->countAllResults() > 0;
    }

    /**
     * Kartı kalıcı siler — YALNIZ sahibi için.
     *
     * @return bool
     */
    public function sil(int $kullaniciId, int $id): bool
    {
        $this->builder()
            ->where('id', $id)
            ->where('kullanici_id', $kullaniciId)
            ->delete();

        // Başkasının ya da olmayan kart için 0 satır silinir → false (404)
        return $this->db->affectedRows() > 0;
    }

    /**
     * Sürükle-bırak sırasını kaydeder. Verilen id dizisinin SIRASI korunur.
     * Başka kullanıcıya ait ya da var olmayan id'ler sessizce atlanır.
     *
     * @param int[] $idler yeni sıra (baştan sona)
     *
     * @return int güncellenen kart sayısı
     */
    public function sirala(int $kullaniciId, array $idler): int
    {
        $idler = array_values(array_unique(array_map('intval', $idler)));
        $sayac = 0;

        foreach ($idler as $sira => $id) {
            if ($id <= 0) {
                continue;
            }

            $ok = $this->builder()
                ->where('id', $id)
                ->where('kullanici_id', $kullaniciId)
                ->update(['sira' => $sira, 'updated_at' => date('Y-m-d H:i:s')]);

            if ($ok) {
                $sayac++;
            }
        }

        return $sayac;
    }
}
