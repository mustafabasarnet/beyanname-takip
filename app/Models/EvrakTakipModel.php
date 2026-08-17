<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Evrak takip — üç durum: GELDI / GELMEDI / YOK
 *
 * YOK = "bu mükellefte bu evrak türü yok" (takip dışı). Kırmızı eksik
 * sayılmaz, sayaçlara ve yüzdeye girmez.
 *
 * İki katman:
 *   • KALICI   : mukellef_evrak_muafiyet tablosu (her ay geçerli)
 *   • DÖNEMSEL : evrak_takip.durum = 'YOK' (yalnız o ay, kalıcıyı EZER)
 */
class EvrakTakipModel extends Model
{
    protected $table         = 'evrak_takip';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'mukellef_id', 'evrak_turu_id', 'yil', 'ay', 'durum',
        'teslim_tarihi', 'kaydeden_id',
    ];

    public const DURUMLAR = [
        'GELMEDI' => 'Gelmedi',
        'GELDI'   => 'Geldi',
        'YOK'     => 'Takip dışı',
    ];

    /**
     * 'YOK' durumu şemada gerçekten var mı?
     * migration_evrak_muafiyet.sql çalıştırılmamış kurulumda ENUM bu değeri
     * kabul etmez; yazmaya kalkışılırsa MySQL kaydı boş string'e çevirir.
     */
    protected ?bool $yokDestekli = null;

    public function yokDestekliMi(): bool
    {
        if ($this->yokDestekli === null) {
            try {
                $satir = $this->db->query(
                    "SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evrak_takip'
                        AND COLUMN_NAME = 'durum'"
                )->getRowArray();

                $this->yokDestekli = $satir !== null && str_contains((string) $satir['t'], 'YOK');
            } catch (\Throwable $e) {
                $this->yokDestekli = false;
            }
        }

        return $this->yokDestekli;
    }

    /** Sayfa başına gösterilebilecek mükellef sayıları */
    public const SAYFA_ADETLERI = [25, 50, 100, 250];

    public const VARSAYILAN_ADET = 50;

    /**
     * Seçilen "toplama ayı"ndan evrak dönemini hesaplar.
     *
     * Örn. kaydırma=1 iken Ağustos 2026 seçilirse → Temmuz 2026 dönemi.
     * Kaydırma ayarı: ayarlar.evrak_donem_kaydirma (varsayılan 1).
     *
     * @return array{yil:int,ay:int,kaydirma:int}
     */
    public function donemHesapla(int $secilenYil, int $secilenAy, ?int $kaydirma = null): array
    {
        if ($kaydirma === null) {
            $ayarlar  = (new AyarModel())->tumu();
            $kaydirma = (int) ($ayarlar['evrak_donem_kaydirma'] ?? 1);
        }

        $kaydirma = max(0, min(11, $kaydirma));

        $ts = mktime(0, 0, 0, $secilenAy - $kaydirma, 1, $secilenYil);

        return [
            'yil'      => (int) date('Y', $ts),
            'ay'       => (int) date('n', $ts),
            'kaydirma' => $kaydirma,
        ];
    }

    /**
     * Aylık evrak çizelgesi.
     * Mükellefin o ayda FAAL olmaması durumunda satır üretilmez.
     *
     * ÖNEMLİ: Buraya gelen $yil/$ay artık EVRAK DÖNEMİdir
     * (controller donemHesapla() ile çevirir).
     *
     * @param array $filtre ['musavir_id','q','sorumlu_kullanici_id','limit','ofset']
     *
     * @return array{mukellefler:array,turler:array,matris:array,toplam:int}
     */
    public function cizelge(int $yil, int $ay, array $filtre = []): array
    {
        $mukellefModel = new MukellefModel();
        $evrakTuru     = new EvrakTuruModel();

        $tumMukellefler = $mukellefModel->listele([
            'musavir_id'           => $filtre['musavir_id'] ?? null,
            'q'                    => $filtre['q'] ?? null,
            'sorumlu_kullanici_id' => $filtre['sorumlu_kullanici_id'] ?? null,
            'harf'                 => $filtre['harf'] ?? null,
            'durum'                => 'hepsi',
        ]);

        $ayBas = sprintf('%04d-%02d-01', $yil, $ay);
        $ayBit = date('Y-m-t', strtotime($ayBas));

        // O ay faal olan mükellefler
        $faal = array_values(array_filter($tumMukellefler, static function ($m) use ($ayBas, $ayBit) {
            if ((int) $m['aktif'] === 0) {
                return false;
            }

            $bas = substr((string) $m['ise_baslama_tarihi'], 0, 10);

            if ($bas > $ayBit) {
                return false;
            }

            if (! empty($m['terk_tarihi']) && substr((string) $m['terk_tarihi'], 0, 10) < $ayBas) {
                return false;
            }

            return true;
        }));

        $toplam = count($faal);

        // Sayfalama: faal süzgeci PHP tarafında olduğu için dilim burada alınır
        if (! empty($filtre['limit'])) {
            $mukellefler = array_slice(
                $faal,
                (int) ($filtre['ofset'] ?? 0),
                (int) $filtre['limit']
            );
        } else {
            $mukellefler = $faal;
        }

        $turler = $evrakTuru->aktifler();

        // Mevcut kayıtlar (yalnızca gösterilen mükellefler için)
        $ids    = array_column($mukellefler, 'id');
        $matris = [];

        if ($ids !== []) {
            $rows = $this->whereIn('mukellef_id', $ids)
                ->where('yil', $yil)->where('ay', $ay)->findAll();

            foreach ($rows as $r) {
                $matris[(int) $r['mukellef_id']][(int) $r['evrak_turu_id']] = $r;
            }
        }

        // Kalıcı muafiyetler ("bu mükellefte banka/çek yok" gibi)
        $muafiyet = (new EvrakMuafiyetModel())->harita($ids);

        return [
            'mukellefler' => $mukellefler,
            'turler'      => $turler,
            'matris'      => $matris,
            'muafiyet'    => $muafiyet,
            'toplam'      => $toplam,
            // Sayaçlar sayfa dilimini değil, filtreye uyan TÜM faal kümeyi
            // kapsamalı; bu yüzden kimlikler ayrıca döndürülür.
            'faalIdler'   => array_map('intval', array_column($faal, 'id')),
        ];
    }

    /**
     * Sayaçlardan düşülecek "takip dışı" hücre sayısı.
     *
     * İki kaynak toplanır:
     *   • O aya ait durum='YOK' kayıtları (dönemsel istisna)
     *   • Kalıcı muafiyeti olup o ay HİÇ kaydı bulunmayan hücreler
     * Dönemsel kayıt kalıcıyı ezdiği için mükerrer sayım olmaz.
     *
     * @param array<int> $faalIdler
     */
    public function muafHucreSayisi(int $yil, int $ay, array $faalIdler): int
    {
        if ($faalIdler === []) {
            return 0;
        }

        $ids   = array_map('intval', $faalIdler);
        $toplam = 0;

        if ($this->yokDestekliMi()) {
            $toplam += (int) $this->db->table('evrak_takip')
                ->where('yil', $yil)->where('ay', $ay)
                ->where('durum', 'YOK')
                ->whereIn('mukellef_id', $ids)
                ->countAllResults();
        }

        $muafModel = new EvrakMuafiyetModel();

        if (! $muafModel->kullanilabilir()) {
            return $toplam;
        }

        // Yalnızca AKTİF türler çizelgede sütun oluşturur; pasife alınmış
        // bir tür için tanımlı muafiyet sayaçları bozmamalı.
        $aktifTurler = array_map(
            'intval',
            array_column((new EvrakTuruModel())->aktifler(), 'id')
        );

        if ($aktifTurler === []) {
            return $toplam;
        }

        $b = $this->db->table('mukellef_evrak_muafiyet mu')
            ->select('COUNT(*) adet')
            ->whereIn('mu.mukellef_id', $ids)
            ->whereIn('mu.evrak_turu_id', $aktifTurler)
            ->where(
                'NOT EXISTS (SELECT 1 FROM evrak_takip e
                              WHERE e.mukellef_id = mu.mukellef_id
                                AND e.evrak_turu_id = mu.evrak_turu_id
                                AND e.yil = ' . $yil . ' AND e.ay = ' . $ay . ')',
                null,
                false
            );

        $satir = $b->get()->getRowArray();

        return $toplam + (int) ($satir['adet'] ?? 0);
    }

    /**
     * Bir hücrenin ETKİN durumu.
     *
     * Öncelik sırası:
     *   1) O aya ait kayıt varsa kaydın durumu (dönemsel istisna kalıcıyı ezer)
     *   2) Kayıt yoksa kalıcı muafiyet varsa 'YOK'
     *   3) Aksi halde 'GELMEDI'
     *
     * @param array|null $hucre evrak_takip satırı ya da null
     * @param bool       $muaf  kalıcı muafiyet var mı
     */
    public static function etkinDurum(?array $hucre, bool $muaf): string
    {
        if ($hucre !== null && isset($hucre['durum']) && $hucre['durum'] !== '') {
            return (string) $hucre['durum'];
        }

        return $muaf ? 'YOK' : 'GELMEDI';
    }

    /**
     * Çizelge sayaçları — muaf hücreler TOPLAMDAN DÜŞÜLÜR.
     *
     * Ekrandaki "Gelen / Bekleyen / %" değerleri buradan üretilir; muaf
     * hücreler hiç var olmamış gibi davranır (kullanıcı tercihi).
     *
     * @return array{toplam:int,geldi:int,gelmedi:int,muaf:int,oran:int}
     */
    public function cizelgeSayaclari(array $cizelge): array
    {
        $geldi = $muafSayi = $gelmedi = 0;
        $turler = $cizelge['turler'] ?? [];

        foreach (($cizelge['mukellefler'] ?? []) as $m) {
            $mid = (int) $m['id'];

            foreach ($turler as $t) {
                $tid    = (int) $t['id'];
                $hucre  = $cizelge['matris'][$mid][$tid] ?? null;
                $muaf   = isset($cizelge['muafiyet'][$mid][$tid]);
                $durum  = self::etkinDurum($hucre, $muaf);

                if ($durum === 'YOK') {
                    $muafSayi++;
                } elseif ($durum === 'GELDI') {
                    $geldi++;
                } else {
                    $gelmedi++;
                }
            }
        }

        $toplam = $geldi + $gelmedi;

        return [
            'toplam'  => $toplam,
            'geldi'   => $geldi,
            'gelmedi' => $gelmedi,
            'muaf'    => $muafSayi,
            'oran'    => $toplam > 0 ? (int) round($geldi / $toplam * 100) : 0,
        ];
    }

    /** Tek hücre güncelleme (AJAX) */
    public function durumKaydet(int $mukellefId, int $evrakTuruId, int $yil, int $ay, string $durum, ?int $kullaniciId = null): array
    {
        if (! array_key_exists($durum, self::DURUMLAR)) {
            $durum = 'GELMEDI';
        }

        // Şema eski ise 'YOK' yazılamaz; MySQL değeri boş string'e çevirip
        // hücreyi bozardı. Bu kurulumlarda istek sessizce GELMEDI'ye düşer.
        if ($durum === 'YOK' && ! $this->yokDestekliMi()) {
            $durum = 'GELMEDI';
        }

        $mevcut = $this->where([
            'mukellef_id'   => $mukellefId,
            'evrak_turu_id' => $evrakTuruId,
            'yil'           => $yil,
            'ay'            => $ay,
        ])->first();

        $veri = [
            'durum'         => $durum,
            'teslim_tarihi' => $durum === 'GELDI' ? date('Y-m-d') : null,
            'kaydeden_id'   => $kullaniciId,
        ];

        if ($mevcut === null) {
            $veri += [
                'mukellef_id'   => $mukellefId,
                'evrak_turu_id' => $evrakTuruId,
                'yil'           => $yil,
                'ay'            => $ay,
            ];
            $this->insert($veri);
        } else {
            $this->update($mevcut['id'], $veri);
        }

        return $veri;
    }

    /**
     * Bir mükellefin tüm evraklarını tek seferde işaretle.
     *
     * ÖNEMLİ: Kalıcı olarak muaf tutulmuş türler ATLANIR. "Tümü geldi"
     * denildiğinde bankası olmayan mükellefe banka ekstresi geldi
     * yazılması yanlış olurdu; hücre takip dışı kalmaya devam eder.
     */
    public function tumunuIsaretle(int $mukellefId, int $yil, int $ay, string $durum, ?int $kullaniciId = null): int
    {
        $turler = (new EvrakTuruModel())->aktifler();
        $muaf   = (new EvrakMuafiyetModel())->turIdListesi($mukellefId);
        $sayac  = 0;

        foreach ($turler as $t) {
            $tid = (int) $t['id'];

            if (in_array($tid, $muaf, true)) {
                // Dönemsel bir kayıt kalmışsa temizlenir ki hücre
                // kalıcı muafiyete geri dönsün.
                $this->where([
                    'mukellef_id'   => $mukellefId,
                    'evrak_turu_id' => $tid,
                    'yil'           => $yil,
                    'ay'            => $ay,
                ])->delete();

                continue;
            }

            $this->durumKaydet($mukellefId, $tid, $yil, $ay, $durum, $kullaniciId);
            $sayac++;
        }

        return $sayac;
    }

    /**
     * Dashboard / çizelge: ay bazlı evrak özeti.
     *
     * @param array<int>|null $faalIdler Verilirse sayım yalnız bu mükelleflerle
     *                                   sınırlanır (çizelgedeki faal küme).
     */
    public function ozet(int $yil, int $ay, $musavirId = null, ?int $sorumluId = null, ?array $faalIdler = null): array
    {
        // Faal küme boş ise hiç kayıt sayılmaz (whereIn'e boş dizi verilemez)
        if (is_array($faalIdler) && $faalIdler === []) {
            return ['GELDI' => 0, 'GELMEDI' => 0, 'YOK' => 0];
        }

        $b = $this->db->table('evrak_takip e')
            ->select('e.durum, COUNT(*) as adet')
            ->join('mukellefler m', 'm.id = e.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('e.yil', $yil)->where('e.ay', $ay);

        if (is_array($musavirId)) {
            if ($musavirId !== []) {
                $b->whereIn('m.musavir_id', array_map('intval', $musavirId));
            }
        } elseif ($musavirId) {
            $b->where('m.musavir_id', (int) $musavirId);
        }

        // Sayaçlar da listedeki filtreyle aynı kapsamda olmalı
        if ($sorumluId) {
            $b->where('m.sorumlu_kullanici_id', $sorumluId);
        }

        if (is_array($faalIdler)) {
            $b->whereIn('e.mukellef_id', array_map('intval', $faalIdler));
        }

        $rows = $b->groupBy('e.durum')->get()->getResultArray();
        $out  = ['GELDI' => 0, 'GELMEDI' => 0, 'YOK' => 0];

        foreach ($rows as $r) {
            $out[$r['durum']] = (int) $r['adet'];
        }

        return $out;
    }

    /**
     * Evrakı hiç gelmemiş mükellefler (uyarı listesi).
     *
     * Takip dışı (muaf) türler sayılmaz. Tüm türleri muaf olan mükellef
     * hiç beklenen evrağı olmadığı için listeye girmez.
     */
    public function evrakiGelmeyenler(int $yil, int $ay, $musavirId = null): array
    {
        $cizelge = $this->cizelge($yil, $ay, ['musavir_id' => $musavirId]);
        $liste   = [];

        foreach ($cizelge['mukellefler'] as $m) {
            $mid      = (int) $m['id'];
            $gelen    = 0;
            $beklenen = 0;

            foreach ($cizelge['turler'] as $t) {
                $tid   = (int) $t['id'];
                $h     = $cizelge['matris'][$mid][$tid] ?? null;
                $durum = self::etkinDurum($h, isset($cizelge['muafiyet'][$mid][$tid]));

                if ($durum === 'YOK') {
                    continue;
                }

                $beklenen++;

                if ($durum === 'GELDI') {
                    $gelen++;
                }
            }

            if ($gelen === 0 && $beklenen > 0) {
                $liste[] = $m;
            }
        }

        return $liste;
    }
}
