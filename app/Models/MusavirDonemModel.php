<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * MÜKELLEF — MALİ MÜŞAVİR SÖZLEŞME DÖNEMLERİ
 *
 * Bir mükellef yıl içinde müşavir değiştirebilir (örn. Atikay Ltd.:
 * Ocak–Şubat Ahmet, Mart–Aralık Mahmut). Makbuz hasılatı makbuzu KESEN
 * müşavirde kalır; yıllık sözleşme ücreti (hedef) ise bu dönemlere AY
 * BAZINDA bölünür: her müşavir yalnız kendi döneminin payını görür.
 *
 * Kurallar:
 *  - Dönemler AY hassasiyetindedir: baslangic = ayın 1'i,
 *    bitis = ayın son günü (NULL = açık uçlu).
 *  - Dönemler çakışamaz (aynı ay iki müşavirde olamaz).
 *  - Geçiş ayı YENİ müşavirin sayılır.
 *  - Gelecek bir ay başlatılamaz (güncel müşavir hep geçmiş/bu ay dönemlerinden seçilir).
 *  - Hiçbir dönemin kapsamadığı aylar "atanmamış" (0) sayılır.
 *  - mukellefler.musavir_id = başlamış en son dönemin müşavirine senkronize tutulur
 *    (liste, portföy ve filtreler bu "güncel" kolonu kullanmaya devam eder).
 */
class MusavirDonemModel extends Model
{
    protected $table         = 'mukellef_musavir_donemleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['mukellef_id', 'musavir_id', 'baslangic', 'bitis'];

    /** Mükellef oluşturulurken açılan ilk dönemin (geçmişe açık) başlangıcı */
    public const ILK_BASLANGIC = '2000-01-01';

    /** Açık uçlu dönemin ay anahtarı (üst sınır) */
    private const ACIK_UC = 999999;

    // -----------------------------------------------------------------
    //  Ay yardımcıları (statik — modelden bağımsız test edilebilir)
    // -----------------------------------------------------------------

    /** 'YYYY-MM' veya 'YYYY-MM-DD' → o ayın 1'i (YYYY-MM-01); geçersizse null */
    public static function ayBasi(?string $ay): ?string
    {
        if ($ay === null || ! preg_match('/^(\d{4})-(\d{2})/', trim($ay), $m)) {
            return null;
        }

        $y  = (int) $m[1];
        $mo = (int) $m[2];

        if ($mo < 1 || $mo > 12 || $y < 1900 || $y > 2999) {
            return null;
        }

        return sprintf('%04d-%02d-01', $y, $mo);
    }

    /** 'YYYY-MM' veya tarih → o ayın son günü; geçersizse null */
    public static function ayBitisi(?string $ay): ?string
    {
        $bas = self::ayBasi($ay);

        return $bas === null ? null : date('Y-m-t', strtotime($bas));
    }

    /** Tarih → ay anahtarı (yıl*12 + ay-1). Karşılaştırmayı kolaylaştırır. */
    public static function ayAnahtari(string $tarih): int
    {
        return (int) date('Y', strtotime($tarih)) * 12 + (int) date('n', strtotime($tarih)) - 1;
    }

    /** Bu ayın 'YYYY-MM' karşılığı (gelecek ay kontrolü için) */
    public static function buAy(): string
    {
        return date('Y-m');
    }

    /** Dönemin [başlangıç anahtarı, bitiş anahtarı] aralığı */
    private static function aralik(array $d): array
    {
        $s = self::ayAnahtari((string) $d['baslangic']);
        $e = empty($d['bitis']) ? self::ACIK_UC : self::ayAnahtari((string) $d['bitis']);

        return [$s, $e];
    }

    /** Ay etiketi: 2026-03-01 → "Mart 2026" */
    public static function ayEtiketi(?string $tarih): string
    {
        if (empty($tarih)) {
            return 'açık';
        }

        $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz',
                  'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

        return $aylar[(int) date('n', strtotime($tarih)) - 1] . ' ' . date('Y', strtotime($tarih));
    }

    // -----------------------------------------------------------------
    //  Okuma
    // -----------------------------------------------------------------

    /** Mükellefin dönemleri (eskiden yeniye) */
    public function donemler(int $mukellefId): array
    {
        return $this->where('mukellef_id', $mukellefId)
            ->orderBy('baslangic', 'ASC')
            ->findAll();
    }

    /** Birden çok mükellefin dönemleri: [mukellef_id => [dönemler]] */
    public function topluDonemler(array $mukellefIdler): array
    {
        $ids = array_values(array_unique(array_map('intval', $mukellefIdler)));
        $out = [];

        if ($ids === []) {
            return $out;
        }

        foreach ($this->whereIn('mukellef_id', $ids)->orderBy('baslangic', 'ASC')->findAll() as $d) {
            $out[(int) $d['mukellef_id']][] = $d;
        }

        return $out;
    }

    /**
     * Dönemlerden yılın 12 ayının müşavirini çıkarır.
     *
     * @return array<int,int> [1..12 => musavir_id, 0 = atanmamış]
     */
    public function ayHaritasi(array $donemler, int $yil): array
    {
        $harita = array_fill(1, 12, 0);
        $yilBas = $yil * 12;

        usort($donemler, static fn ($a, $b) => strcmp((string) $a['baslangic'], (string) $b['baslangic']));

        foreach ($donemler as $d) {
            [$s, $e] = self::aralik($d);

            for ($k = max($s, $yilBas); $k <= min($e, $yilBas + 11); $k++) {
                $harita[$k - $yilBas + 1] = (int) $d['musavir_id'];
            }
        }

        return $harita;
    }

    /**
     * Mükellef → yıllık ay haritası: [mukellef_id => [1..12 => musavir_id]]
     */
    public function dagilimHaritasi(array $mukellefIdler, int $yil): array
    {
        $donemler = $this->topluDonemler($mukellefIdler);
        $guncel   = $this->donemsizGuncelMusavirler($mukellefIdler, $donemler);
        $out      = [];

        foreach ($mukellefIdler as $mid) {
            $mid = (int) $mid;

            // Hiç dönemi olmayan mükellef (ör. dönem öncesi kayıt): güncel müşavir tüm yıl
            $out[$mid] = isset($donemler[$mid])
                ? $this->ayHaritasi($donemler[$mid], $yil)
                : array_fill(1, 12, (int) ($guncel[$mid] ?? 0));
        }

        return $out;
    }

    /**
     * Dönemi hiç olmayan mükelleflerin güncel müşavirleri: [mukellef_id => musavir_id]
     */
    protected function donemsizGuncelMusavirler(array $mukellefIdler, array $donemler): array
    {
        $bos = array_values(array_filter(
            array_map('intval', $mukellefIdler),
            static fn ($mid) => $mid > 0 && ! isset($donemler[$mid])
        ));

        if ($bos === []) {
            return [];
        }

        $out = [];

        foreach ($this->db->table('mukellefler')->select('id, musavir_id')
                     ->whereIn('id', $bos)->get()->getResultArray() as $r) {
            $out[(int) $r['id']] = (int) $r['musavir_id'];
        }

        return $out;
    }

    /**
     * Bir ay haritasında, verilen müşavir(ler)in kaç ay payı var?
     * (0 = atanmamış sayılmaz; yalnız gerçek müşavirler)
     *
     * @param array<int,int> $harita
     * @param int[]|null     $musavirIdler null → tüm müşavirler
     */
    public static function aySayisi(array $harita, ?array $musavirIdler = null): int
    {
        $say = 0;

        foreach ($harita as $mus) {
            if ($mus <= 0) {
                continue;
            }

            if ($musavirIdler === null || in_array($mus, $musavirIdler, true)) {
                $say++;
            }
        }

        return $say;
    }

    /** Ay haritasından müşavir bazlı ay sayıları: [musavir_id => ay] (0 = atanmamış) */
    public static function paylar(array $harita): array
    {
        $out = [];

        foreach ($harita as $mus) {
            $out[$mus] = ($out[$mus] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * Verilen müşavir(ler)in o yıl herhangi bir ayında payı olan
     * (dönemi veya kestiği makbuzu olan) mükellef ID'leri.
     */
    public function mukellefKapsami(array $musavirIdler, int $yil): array
    {
        $ids = array_values(array_filter(array_map('intval', $musavirIdler)));

        if ($ids === []) {
            return [];
        }

        $bas = $yil . '-01-01';
        $bit = $yil . '-12-31';

        // 1) Dönem payı olanlar
        $donemIdler = $this->db->table($this->table)
            ->select('mukellef_id')
            ->distinct()
            ->whereIn('musavir_id', $ids)
            ->where('baslangic <=', $bit)
            ->groupStart()
                ->where('bitis IS NULL', null, false)
                ->orWhere('bitis >=', $bas)
            ->groupEnd()
            ->get()->getResultArray();

        // 2) Hiç dönemi olmayan mükelleflerin güncel müşaviri (dönem öncesi kayıt)
        $donemsiz = $this->db->table('mukellefler m')
            ->select('m.id')
            ->whereIn('m.musavir_id', $ids)
            ->where('m.deleted_at', null)
            ->where('NOT EXISTS (SELECT 1 FROM ' . $this->table . ' d WHERE d.mukellef_id = m.id)', null, false)
            ->get()->getResultArray();

        // Not: makbuz kesen müşavir kapsam belirlemez. Bir mükellefin verisi
        // yalnız portföy (dönem payı) sahibi müşavire açıktır; kesen tarafın
        // makbuz tutarı ise mükellef satırında kesilen olarak görünür.
        $out = [];

        foreach (array_merge($donemIdler, $donemsiz) as $r) {
            $out[(int) ($r['mukellef_id'] ?? $r['id'])] = true;
        }

        return array_map('intval', array_keys($out));
    }

    /**
     * Tarihte mükellefin müşaviri (kapsayan dönem). Yoksa null.
     */
    public function musavirAt(int $mukellefId, string $tarih): ?int
    {
        $donemler = $this->donemler($mukellefId);

        // Hiç dönemi yoksa mükellefin güncel müşaviri geçerlidir
        if ($donemler === []) {
            $m = $this->db->table('mukellefler')->select('musavir_id')
                ->where('id', $mukellefId)->get()->getRowArray();

            return $m && (int) $m['musavir_id'] > 0 ? (int) $m['musavir_id'] : null;
        }

        $ay = self::ayAnahtari($tarih);

        foreach (array_reverse($donemler) as $d) {
            [$s, $e] = self::aralik($d);

            if ($s <= $ay && $ay <= $e) {
                return (int) $d['musavir_id'];
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    //  Yazma
    // -----------------------------------------------------------------

    /**
     * Mükellef oluşturulunca ilk dönemi açar (MukellefModel afterInsert).
     * Aynı mükellef için dönem zaten varsa dokunmaz.
     */
    public function ilkDonem(int $mukellefId, int $musavirId): void
    {
        if ($mukellefId <= 0 || $musavirId <= 0) {
            return;
        }

        if ($this->where('mukellef_id', $mukellefId)->countAllResults() > 0) {
            return;
        }

        $this->insert([
            'mukellef_id' => $mukellefId,
            'musavir_id'  => $musavirId,
            'baslangic'   => self::ILK_BASLANGIC,
            'bitis'       => null,
        ]);
    }

    /**
     * Manuel dönem ekleme (geçmiş dönem girişi dahil).
     *
     * @return string|null hata mesajı; başarılıysa null
     */
    public function donemEkle(int $mukellefId, int $musavirId, string $basAy, ?string $bitAy = null): ?string
    {
        return $this->donemYaz(null, $mukellefId, $musavirId, $basAy, $bitAy);
    }

    /**
     * Mevcut dönemi düzenleme (müşavir, başlangıç ve bitiş ayı).
     * Kayıt kendi çakışma kontrolünden hariç tutulur.
     *
     * @return string|null hata mesajı; başarılıysa null
     */
    public function donemGuncelle(int $mukellefId, int $donemId, int $musavirId, string $basAy, ?string $bitAy = null): ?string
    {
        $mevcut = $this->where('id', $donemId)->where('mukellef_id', $mukellefId)->first();

        if ($mevcut === null) {
            return 'Dönem bulunamadı.';
        }

        return $this->donemYaz($donemId, $mukellefId, $musavirId, $basAy, $bitAy);
    }

    /**
     * Ortak doğrulama + yazma (ekleme: $haricId = null, güncelleme: dönem id).
     *
     * @return string|null hata mesajı; başarılıysa null
     */
    private function donemYaz(?int $haricId, int $mukellefId, int $musavirId, string $basAy, ?string $bitAy): ?string
    {
        $bas = self::ayBasi($basAy);

        if ($bas === null) {
            return 'Başlangıç ayı geçersiz.';
        }

        if (self::ayAnahtari($bas) > self::ayAnahtari(self::buAy() . '-01')) {
            return 'Gelecek bir ay için müşavir dönemi başlatılamaz.';
        }

        $bit = null;

        if (! empty($bitAy)) {
            $bit = self::ayBitisi($bitAy);

            if ($bit === null) {
                return 'Bitiş ayı geçersiz.';
            }

            if (self::ayAnahtari($bit) < self::ayAnahtari($bas)) {
                return 'Bitiş ayı, başlangıç ayından önce olamaz.';
            }
        }

        $yeni = ['baslangic' => $bas, 'bitis' => $bit];
        [$ys, $ye] = self::aralik($yeni);

        foreach ($this->donemler($mukellefId) as $d) {
            if ($haricId !== null && (int) $d['id'] === $haricId) {
                continue;
            }

            [$s, $e] = self::aralik($d);

            if ($ys <= $e && $s <= $ye) {
                return 'Bu dönem mevcut bir dönemle çakışıyor ('
                    . self::ayEtiketi($d['baslangic']) . ' – ' . ($d['bitis'] ? self::ayEtiketi($d['bitis']) : 'açık')
                    . '). Önce çakışan dönemi düzenleyin veya silin.';
            }
        }

        $veri = [
            'mukellef_id' => $mukellefId,
            'musavir_id'  => $musavirId,
            'baslangic'   => $bas,
            'bitis'       => $bit,
        ];

        $this->db->transStart();

        if ($haricId === null) {
            $this->insert($veri);
        } else {
            $this->update($haricId, $veri);
        }

        $this->senkronle($mukellefId);
        $this->db->transComplete();

        return $this->db->transStatus() ? null : 'Dönem kaydedilemedi.';
    }

    /** Dönem silme (yalnız bu mükellefe ait kayıt) */
    public function donemSil(int $mukellefId, int $donemId): bool
    {
        $ok = $this->where('id', $donemId)->where('mukellef_id', $mukellefId)->delete() !== false;

        // Silinen satır sayısı, senkron sorgularından ÖNCE okunmalı
        $silinen = $ok ? $this->db->affectedRows() : 0;

        if ($silinen > 0) {
            $this->senkronle($mukellefId);
        }

        return $silinen > 0;
    }

    /**
     * Müşavir değişikliği: `$ay` ayından itibaren yeni müşavir.
     * Önceki açık dönem, yeni dönemden bir önceki ayın sonunda kapanır.
     *
     * @return string|null hata mesajı; başarılı veya zaten aynı müşavirse null
     */
    public function musavirDegistir(int $mukellefId, int $yeniMusavir, string $ay): ?string
    {
        $bas = self::ayBasi($ay);

        if ($bas === null) {
            return 'Geçerlilik ayı geçersiz.';
        }

        if (self::ayAnahtari($bas) > self::ayAnahtari(self::buAy() . '-01')) {
            return 'Gelecek bir ay seçilemez. En fazla bu ay için değişiklik yapılabilir.';
        }

        $hedefAnahtar = self::ayAnahtari($bas);
        $donemler     = $this->donemler($mukellefId);
        $son          = null;

        foreach ($donemler as $d) {
            if (self::ayAnahtari((string) $d['baslangic']) >= $hedefAnahtar) {
                return 'Seçilen ay (' . self::ayEtiketi($bas) . ') mevcut bir dönemin başlangıcıyla çakışıyor. '
                    . 'Geçiş ayı, kayıtlı son dönemin başlangıcından sonra olmalı.';
            }

            $son = $d;
        }

        // Aynı müşavir zaten bu ayda geçerliyse değişiklik yok
        if ($son !== null && (int) $son['musavir_id'] === $yeniMusavir
            && (empty($son['bitis']) || self::ayAnahtari((string) $son['bitis']) >= $hedefAnahtar)) {
            return null;
        }

        $this->db->transStart();

        if ($son !== null && (empty($son['bitis']) || self::ayAnahtari((string) $son['bitis']) >= $hedefAnahtar)) {
            $oncekiSon = date('Y-m-t', strtotime($bas . ' -1 month'));
            $this->update($son['id'], ['bitis' => $oncekiSon]);
        }

        $this->insert([
            'mukellef_id' => $mukellefId,
            'musavir_id'  => $yeniMusavir,
            'baslangic'   => $bas,
            'bitis'       => null,
        ]);

        $this->senkronle($mukellefId);
        $this->db->transComplete();

        return $this->db->transStatus() ? null : 'Müşavir değişikliği kaydedilemedi.';
    }

    /**
     * mukellefler.musavir_id = başlamış en son dönemin müşaviri.
     * Bu ay ve öncesi başlamış dönemler arasından seçilir.
     */
    public function senkronle(int $mukellefId): void
    {
        $ay    = self::buAy() . '-01';
        $aktif = null;

        foreach ($this->donemler($mukellefId) as $d) {
            if ((string) $d['baslangic'] <= $ay) {
                $aktif = (int) $d['musavir_id'];
            }
        }

        if ($aktif === null) {
            return;
        }

        $this->db->table('mukellefler')
            ->where('id', $mukellefId)
            ->update(['musavir_id' => $aktif]);
    }
}
