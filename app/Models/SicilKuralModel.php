<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SİCİL — ŞABLON TODO TANIMLARI (sicil_bildirim_kurallari)
 *
 * Sade modül kavramında bu tablo, bir şablonun altında önceden tanımlı
 * yapılacak işleri ("todo tanımı") tutar:
 *
 *   degisiklik_turu_id → hangi şablonun altında
 *   ad                → todo adı (serbest metin: "Vergi Dairesine Bildirim")
 *   sure_tipi/deger   → son tarih hesaplama kuralı (süreler KODDA SABİT DEĞİL)
 *   aktif             → yeni işlemde üretilir mi
 *
 * Bütünlük:
 *   - Todo adı serbesttir; kurum bağı yoktur (kurum_id eski sürüm uyumu için
 *     nullable durur, UI/kod kullanmaz).
 *   - Mükerrer koruma görev üretimindedir: (islem, kural) unique → aynı tanım
 *     bir işlemde iki kez todo üretemez.
 *   - Bir tanım hiçbir işlemde kullanılmadıysa silinebilir; kullanıldıysa
 *     pasife alınır (geçmiş bozulmaz).
 */
class SicilKuralModel extends Model
{
    protected $table         = 'sicil_bildirim_kurallari';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'degisiklik_turu_id', 'ad', 'sure_tipi', 'sure_deger',
        'belirli_tarih', 'oncelik', 'aciklama', 'aktif', 'olusturan_id',
    ];

    /** SicilSureHesaplayici::TIPLER ile aynı; ENUM tutarlılığı için sabit. */
    public const SURE_TIPLERI = ['GUN', 'IS_GUNU', 'TAKVIM_GUNU', 'AY', 'BELIRLI_TARIH'];

    /** Süre tipi görünen adları (form + ipucu). */
    public const SURE_TIP_ADLARI = [
        'GUN'         => 'Gün',
        'IS_GUNU'     => 'İş Günü',
        'TAKVIM_GUNU' => 'Takvim Günü',
        'AY'          => 'Ay',
        'BELIRLI_TARIH' => 'Belirli Tarih',
    ];

    /** Şablonun todo tanımları (sırayla). */
    public function sablonTodoTanimlari(int $sablonId, bool $sadeceAktif = false): array
    {
        $b = $this->where('degisiklik_turu_id', $sablonId);

        if ($sadeceAktif) {
            $b->where('aktif', 1);
        }

        return $b->orderBy('oncelik', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /**
     * Görev üretimi için şablonun AKTİF todo tanımları (sıralı liste).
     *
     * @return array<int,array>
     */
    public function aktifTodoTanimlari(int $sablonId): array
    {
        return $this->where('degisiklik_turu_id', $sablonId)
            ->where('aktif', 1)
            ->orderBy('oncelik', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Bir şablonun todo tanımlarını formdan gelen satır setiyle senkronlar.
     *
     * Tasarım (Şablonlar ekranı):
     *   - Satırlar sırayla işlenir; boş adlı satırlar atlanır.
     *   - Satır id'si mevcut tanımla eşleşirse GÜNCELLENİR, eşleşmezse EKLENİR.
     *   - Formda olmayan mevcut tanımlar: hiçbir işlemde kullanılmadıysa
     *     SİLİNİR, kullanıldıysa PASİFE ALINIR (geçmiş korunur).
     *
     * Transaction DIŞINDA çağrılır — çağıran (SicilTurModel::sablonKaydet)
     * tek transaction yönetir.
     *
     * @param array $satirlar [ ['id','ad','sure_tipi','sure_deger','belirli_tarih','aktif'] ]
     *
     * @return array{durum:bool, hata:?string}
     */
    public function senkronla(int $sablonId, array $satirlar, int $kullaniciId): array
    {
        $db       = $this->db;
        $mevcut   = $this->where('degisiklik_turu_id', $sablonId)->findAll();
        $mevcutMap = [];

        foreach ($mevcut as $m) {
            $mevcutMap[(int) $m['id']] = $m;
        }

        $hatalar = [];
        $sira    = 10;

        foreach ($satirlar as $i => $s) {
            $ad = trim((string) ($s['ad'] ?? ''));

            if ($ad === '') {
                continue; // boş şablon satırı — yok sayılır
            }

            $satirId = (int) ($s['id'] ?? 0);
            $tip     = (string) ($s['sure_tipi'] ?? 'GUN');

            if (! in_array($tip, self::SURE_TIPLERI, true)) {
                $hatalar[] = ($i + 1) . '. satır: geçersiz süre türü.';
                continue;
            }

            $hamDeger = trim((string) ($s['sure_deger'] ?? ''));
            $belirli  = trim((string) ($s['belirli_tarih'] ?? ''));

            if ($tip === 'BELIRLI_TARIH') {
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $belirli)) {
                    $hatalar[] = ($i + 1) . '. satır: "Belirli Tarih" türünde hedef tarih gerekir.';
                    continue;
                }
                $deger = null;
            } else {
                $deger   = $hamDeger === '' ? null : (int) $hamDeger;
                $belirli = null;

                if ($deger === null || $deger < 1) {
                    $hatalar[] = ($i + 1) . '. satır: süre değeri gerekli (1 veya daha büyük).';
                    continue;
                }
            }

            $alan = [
                'degisiklik_turu_id' => $sablonId,
                'ad'                 => $ad,
                'sure_tipi'          => $tip,
                'sure_deger'         => $deger,
                'belirli_tarih'      => $belirli,
                'oncelik'            => $sira,
                'aktif'              => empty($s['aktif']) ? 0 : 1,
                'aciklama'           => null,
                'olusturan_id'       => $kullaniciId,
            ];
            $sira += 10;

            if ($satirId > 0 && isset($mevcutMap[$satirId])) {
                unset($mevcutMap[$satirId]);

                if (! $this->update($satirId, $alan)) {
                    $hatalar[] = ($i + 1) . '. satır güncellenemedi.';
                }
            } elseif (! $this->insert($alan)) {
                $hatalar[] = ($i + 1) . '. satır eklenemedi.';
            }
        }

        // Kaldırılmış satırlar
        foreach ($mevcutMap as $mId => $m) {
            $kullanilan = (int) $db->table('sicil_bildirim_gorevleri')
                ->where('kural_id', $mId)
                ->countAllResults();

            if ($kullanilan === 0) {
                $this->delete($mId);
            } else {
                $this->update($mId, ['aktif' => 0]);
            }
        }

        return $hatalar === []
            ? ['durum' => true, 'hata' => null]
            : ['durum' => false, 'hata' => implode(' ', $hatalar)];
    }

    /** Tanım kullanılıyor mu? (silme kararı için) */
    public function kullaniliyorMu(int $id): bool
    {
        return $this->db->table('sicil_bildirim_gorevleri')
            ->where('kural_id', $id)
            ->countAllResults() > 0;
    }

    /** Pasife al (geçmiş üretim korunur). */
    public function pasifeAl(int $id): bool
    {
        return $this->update($id, ['aktif' => 0]);
    }
}
