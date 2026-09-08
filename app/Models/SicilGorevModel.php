<?php

namespace App\Models;

use App\Libraries\SicilSureHesaplayici;
use CodeIgniter\Model;

/**
 * SİCİL — İŞLEM TODO'LARI (sicil_bildirim_gorevleri)
 *
 * Sade modül kavramında bu tablo, bir işlemin (sicil_degisiklikleri)
 * altında otomatik üretilen TODO listesidir:
 *
 *   sicil_degisikligi_id → hangi işlemden üretildi
 *   kural_id             → üretim kaynağı şablon todo tanımı
 *   ad                   → üretim ANINDA kopyalanan todo adı (şablon sonradan
 *                          değişse bile geçmiş işlem bozulmaz)
 *   son_tarih            → süre kuralıyla hesaplanmış son bildirim tarihi
 *
 * Temel ilkeler:
 *   - Mükerrer koruma: DB unique (sicil_degisikligi_id, kural_id) + üretim
 *     öncesi kontrol → aynı tanım bir işlemde iki kez üretilemez.
 *   - Durum: yalnız BEKLIYOR (yapılmadı) ↔ TAMAM (yapıldı); opsiyonel
 *     GEREKSIZ (takip dışı). Eski HAZIR/GONDERILDI değerleri "açık" sayılır.
 *   - "Süresi geçti/bugün/yaklaşan" veri değil; görüntüleme anında tarihten
 *     türetilir (sayaç/filtre burada üretilir).
 */
class SicilGorevModel extends Model
{
    protected $table         = 'sicil_bildirim_gorevleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'sicil_degisikligi_id', 'kural_id', 'ad', 'son_tarih',
        'asil_tarih', 'kaydirma_nedeni', 'durum', 'tamamlanma_tarihi',
        'yapan_id', 'not_metni',
    ];

    /** Görev durumu görünen adları (eski HAZIR/GONDERILDI gizlenir). */
    public const DURUMLAR = [
        'BEKLIYOR'   => 'Yapılmadı',
        'TAMAM'      => 'Yapıldı',
        'GEREKSIZ'   => 'Takip dışı',
    ];

    /** Açık (henüz kapanmamış) durumlar — eski sürüm değerleri de dahil. */
    public const ACIK_DURUMLAR = ['BEKLIYOR', 'HAZIR', 'GONDERILDI'];

    // =================================================================
    //  ÜRETİM
    // =================================================================

    /**
     * İşlem için şablonun AKTİF todo tanımlarını işler, todo satırlarını üretir
     * (idempotent — tekrar çağrıldığında çoğalmaz).
     *
     * @param array $degisiklik sicil_degisiklikleri satırı (id, turu_id, degisiklik_tarihi)
     *
     * @return array üretilen todolar [{id, ad, son_tarih, durum}]
     */
    public function islemIcinUret(array $degisiklik, int $kaydedenId): array
    {
        $degId     = (int) $degisiklik['id'];
        $sablonId  = (int) $degisiklik['turu_id'];
        $baslangic = (string) $degisiklik['degisiklik_tarihi'];

        $tanimlar = (new SicilKuralModel())->aktifTodoTanimlari($sablonId);
        $olusan   = [];

        foreach ($tanimlar as $tanim) {
            // Mükerrer kontrol (unique öncesi anlaşılır biçimde atla)
            if ($this->todoVarMi($degId, (int) $tanim['id'])) {
                continue;
            }

            $sonuc = self::tarihHesapla($baslangic, $tanim);

            if ($sonuc === null) {
                continue; // geçersiz tanım → atla (yönetici düzeltir)
            }

            $id = (int) $this->insert([
                'sicil_degisikligi_id' => $degId,
                'kural_id'             => (int) $tanim['id'],
                'ad'                   => $tanim['ad'] ?: 'Todo',
                'son_tarih'            => $sonuc['son_tarih'],
                'asil_tarih'           => $sonuc['asil_tarih'],
                'kaydirma_nedeni'      => $sonuc['neden'],
                'durum'                => 'BEKLIYOR',
                'yapan_id'             => null,
            ]);

            if ($id > 0) {
                $olusan[] = [
                    'id'        => $id,
                    'ad'        => $tanim['ad'] ?: 'Todo',
                    'son_tarih' => $sonuc['son_tarih'],
                    'durum'     => 'BEKLIYOR',
                ];
            }
        }

        return $olusan;
    }

    /**
     * Bir şablon todo tanımından son tarihi hesaplar (tek nokta).
     *
     * BELIRLI_TARIH özel kuralı: tanımda ay.gün sabittir, YIL işlemin
     * yılından alınır — böylece şablon çok yıllık kullanılabilir
     * (örn. "30.09" tanımı her işlem yılında o yılın 30 Eylül'ünü verir).
     *
     * @return array{son_tarih:string, asil_tarih:?string, neden:?string}|null
     */
    public static function tarihHesapla(string $baslangic, array $tanim): ?array
    {
        try {
            $hesap = new SicilSureHesaplayici();
            $tip   = (string) ($tanim['sure_tipi'] ?? 'GUN');
            $deger = isset($tanim['sure_deger']) && $tanim['sure_deger'] !== null
                ? (int) $tanim['sure_deger'] : null;

            if ($tip === 'BELIRLI_TARIH') {
                $hedef = self::belirliTarihBirlestir($baslangic, (string) ($tanim['belirli_tarih'] ?? ''));

                return $hesap->hesapla($baslangic, $tip, null, $hedef);
            }

            return $hesap->hesapla($baslangic, $tip, $deger);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Ay.gün tanımını işlem yılıyla birleştirir (29 Şubat taşması son güne çekilir). */
    protected static function belirliTarihBirlestir(string $baslangic, string $hedef): string
    {
        if (! preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $hedef, $m)) {
            return $hedef;
        }

        $yil    = (int) substr($baslangic, 0, 4);
        $ay     = (int) $m[1];
        $gun    = (int) $m[2];
        $sonGun = (int) date('t', mktime(0, 0, 0, $ay, 1, $yil));

        return sprintf('%04d-%02d-%02d', $yil, $ay, min($gun, $sonGun));
    }

    /** Aynı (işlem, todo tanımı) çifti için satır var mı? */
    public function todoVarMi(int $degisiklikId, int $tanimId): bool
    {
        return $this->where('sicil_degisikligi_id', $degisiklikId)
            ->where('kural_id', $tanimId)
            ->countAllResults() > 0;
    }

    // =================================================================
    //  LİSTE / DETAY
    // =================================================================

    /**
     * Bir işlemin todo listesi (şablon sırası korunur: id artan).
     * Yapan kullanıcı adı eklenir; kurum bilgisi kullanılmaz.
     */
    public function islemTodoListesi(int $degisiklikId): array
    {
        return $this->db->table('sicil_bildirim_gorevleri g')
            ->select("g.*, kk.ad_soyad AS yapan_adi,
                      (g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')) AS acik_mi")
            ->join('kullanicilar kk', 'kk.id = g.yapan_id', 'left')
            ->where('g.sicil_degisikligi_id', $degisiklikId)
            ->where('g.deleted_at', null)
            ->orderBy('g.id', 'ASC')
            ->get()->getResultArray();
    }

    // =================================================================
    //  DURUM GEÇİŞLERİ (checkbox + takip dışı)
    // =================================================================

    /**
     * Todo durumunu değiştirir.
     *
     * İzinli geçişler (sade modül):
     *   herhangi bir açık durum → TAMAM      (yapıldı)
     *   TAMAM / GEREKSIZ        → BEKLIYOR   (geri aç)
     *   açık durum               → GEREKSIZ   (takip dışı)
     *   aynı durum tekrarı → noop (başarılı)
     *
     * @return array{durum:bool, mesaj:string, kayit:?array}
     */
    public function durumDegistir(int $id, string $hedef, int $kullaniciId): array
    {
        $g = $this->find($id);

        if ($g === null) {
            return ['durum' => false, 'mesaj' => 'Todo bulunamadı.', 'kayit' => null];
        }

        if (! in_array($hedef, ['BEKLIYOR', 'TAMAM', 'GEREKSIZ'], true)) {
            return ['durum' => false, 'mesaj' => 'Geçersiz durum.', 'kayit' => null];
        }

        $kaynak = (string) $g['durum'];
        $acik   = in_array($kaynak, self::ACIK_DURUMLAR, true);

        if ($kaynak !== $hedef) {
            $izinli = match ($hedef) {
                'TAMAM'    => $acik || $kaynak === 'GEREKSIZ',
                'BEKLIYOR' => $kaynak === 'TAMAM' || $kaynak === 'GEREKSIZ',
                'GEREKSIZ' => $acik,
                default    => false,
            };

            if (! $izinli) {
                return ['durum' => false,
                        'mesaj' => "Bu geçiş yapılamaz ({$kaynak} → {$hedef}).",
                        'kayit' => null];
            }
        }

        $veri = ['durum' => $hedef];

        if ($hedef === 'TAMAM' || $hedef === 'GEREKSIZ') {
            $veri['yapan_id']           = $kullaniciId;
            $veri['tamamlanma_tarihi']  = $hedef === 'TAMAM' ? date('Y-m-d H:i:s') : null;
        } elseif ($hedef === 'BEKLIYOR') {
            // geri açıldı → yapan/tamamlanma temizlenir
            $veri['yapan_id']          = null;
            $veri['tamamlanma_tarihi'] = null;
        }

        if (! $this->update($id, $veri)) {
            return ['durum' => false, 'mesaj' => 'Güncelleme başarısız.', 'kayit' => null];
        }

        return ['durum' => true, 'mesaj' => 'Durum güncellendi.', 'kayit' => $this->find($id)];
    }

    /** Todo'yu tamamlar (checkbox işaretlenince). */
    public function tamamla(int $id, int $kullaniciId): array
    {
        return $this->durumDegistir($id, 'TAMAM', $kullaniciId);
    }

    /** Todo'yu takip dışı yapar. */
    public function gereksizYap(int $id, int $kullaniciId): array
    {
        return $this->durumDegistir($id, 'GEREKSIZ', $kullaniciId);
    }

    // =================================================================
    //  SAYAÇLAR (dashboard + liste üstü kartlar)
    // =================================================================

    /**
     * Günlük sayaçlar — gecikti / bugün / 3 / 7 / 15 / açık / tamamlanan.
     *
     * @param int[]|null $musavirIdler kapsam (null = admin tümü)
     */
    public function sayaclar($musavirIdler = null): array
    {
        $bugun = date('Y-m-d');
        $b3    = date('Y-m-d', strtotime('+3 days'));
        $b7    = date('Y-m-d', strtotime('+7 days'));
        $b15   = date('Y-m-d', strtotime('+15 days'));

        $b = $this->db->table('sicil_bildirim_gorevleri g')
            ->select("
                COUNT(*)                                             AS toplam,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI'))            AS bekleyen,
                SUM(g.durum = 'TAMAM')                               AS tamamlanan,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih IS NOT NULL AND g.son_tarih < '{$bugun}') AS gecikti,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih = '{$bugun}')                    AS bugun,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih >= '{$bugun}' AND g.son_tarih <= '{$b3}')  AS ic3,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih >= '{$bugun}' AND g.son_tarih <= '{$b7}')  AS ic7,
                SUM(g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')
                    AND g.son_tarih >= '{$bugun}' AND g.son_tarih <= '{$b15}') AS ic15")
            ->join('sicil_degisiklikleri d', 'd.id = g.sicil_degisikligi_id')
            ->join('mukellefler m', 'm.id = d.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('d.deleted_at', null)
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
