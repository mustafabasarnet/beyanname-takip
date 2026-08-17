<?php

namespace App\Libraries;

use App\Models\BeyannameTakipModel;
use App\Models\MukellefModel;
use Config\Database;

/**
 * Toplu veri silme işlemleri (yalnızca yönetici).
 *
 * İki kademeli güvenlik:
 *   1. Mükellefler ÇÖP KUTUSUNA taşınır (soft delete) — geri alınabilir
 *   2. Çöp kutusundan "kalıcı sil" ile veritabanından tamamen kaldırılır
 *
 * Beyanname/evrak kayıtları yeniden üretilebilir olduğundan doğrudan silinir.
 */
class TopluSilici
{
    protected $db;
    protected MukellefModel $mukellefModel;

    public function __construct()
    {
        $this->db            = Database::connect();
        $this->mukellefModel = new MukellefModel();
    }

    // =================================================================
    //  MÜKELLEF — ÇÖP KUTUSU
    // =================================================================

    /**
     * Mükellefleri çöp kutusuna taşır (soft delete).
     *
     * @param int[] $idler
     *
     * @return array{silinen:int,atlanan:int,adlar:array}
     */
    public function mukellefleriCopeAt(array $idler): array
    {
        $silinen = 0;
        $adlar   = [];

        foreach ($idler as $ham) {
            $id = (int) $ham;
            $m  = $this->mukellefModel->find($id);

            if ($m === null) {
                continue;
            }

            if ($this->mukellefModel->delete($id)) {
                $silinen++;
                $adlar[] = $m['unvan'];
            }
        }

        return [
            'silinen' => $silinen,
            'atlanan' => count($idler) - $silinen,
            'adlar'   => $adlar,
        ];
    }

    /** Çöp kutusundaki mükellefler */
    public function copKutusu(?array $musavirIdler = null): array
    {
        $b = $this->db->table('mukellefler m')
            ->select('m.id, m.kod, m.unvan, m.mukellef_tipi, m.vergi_kimlik_no,
                      m.tc_kimlik_no, m.defter_tipi, m.deleted_at,
                      mus.ad_soyad AS musavir_adi')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->where('m.deleted_at IS NOT NULL');

        if ($musavirIdler !== null && $musavirIdler !== []) {
            $b->whereIn('m.musavir_id', array_map('intval', $musavirIdler));
        }

        $rows = $b->orderBy('m.deleted_at', 'DESC')->get()->getResultArray();

        // Her kaydın bağlı veri sayıları (kalıcı silmede ne gideceği)
        foreach ($rows as &$r) {
            $r['beyanname'] = (int) $this->db->table('beyanname_takip')
                ->where('mukellef_id', $r['id'])->countAllResults();
            $r['evrak'] = (int) $this->db->table('evrak_takip')
                ->where('mukellef_id', $r['id'])->countAllResults();
        }

        return $rows;
    }

    /**
     * Çöp kutusundaki mükellefleri geri yükler.
     *
     * @param int[] $idler
     */
    public function geriYukle(array $idler): int
    {
        if ($idler === []) {
            return 0;
        }

        $this->db->table('mukellefler')
            ->whereIn('id', array_map('intval', $idler))
            ->where('deleted_at IS NOT NULL')
            ->update(['deleted_at' => null]);

        return $this->db->affectedRows();
    }

    /**
     * Mükellefi ve TÜM bağlı verisini kalıcı olarak siler.
     * Yabancı anahtarlar CASCADE olduğundan bağlı kayıtlar da gider.
     *
     * @param int[] $idler
     * @param bool  $sadeceCopten true ise yalnızca çöp kutusundakiler silinir
     *
     * @return array{silinen:int,beyanname:int,evrak:int,adlar:array}
     */
    public function mukellefleriKaliciSil(array $idler, bool $sadeceCopten = true): array
    {
        if ($idler === []) {
            return ['silinen' => 0, 'beyanname' => 0, 'evrak' => 0, 'adlar' => []];
        }

        $idler = array_map('intval', $idler);

        $b = $this->db->table('mukellefler')->whereIn('id', $idler);

        if ($sadeceCopten) {
            $b->where('deleted_at IS NOT NULL');
        }

        $hedefler = $b->get()->getResultArray();

        if ($hedefler === []) {
            return ['silinen' => 0, 'beyanname' => 0, 'evrak' => 0, 'adlar' => []];
        }

        $gercekIdler = array_column($hedefler, 'id');

        $beyanname = (int) $this->db->table('beyanname_takip')
            ->whereIn('mukellef_id', $gercekIdler)->countAllResults();
        $evrak = (int) $this->db->table('evrak_takip')
            ->whereIn('mukellef_id', $gercekIdler)->countAllResults();

        $this->db->transStart();
        $this->db->table('mukellefler')->whereIn('id', $gercekIdler)->delete();
        $this->db->transComplete();

        return [
            'silinen'   => count($gercekIdler),
            'beyanname' => $beyanname,
            'evrak'     => $evrak,
            'adlar'     => array_column($hedefler, 'unvan'),
        ];
    }

    /** Çöp kutusunu tamamen boşaltır */
    public function copKutusunuBosalt(?array $musavirIdler = null): array
    {
        $b = $this->db->table('mukellefler')->where('deleted_at IS NOT NULL');

        if ($musavirIdler !== null && $musavirIdler !== []) {
            $b->whereIn('musavir_id', array_map('intval', $musavirIdler));
        }

        $idler = array_column($b->get()->getResultArray(), 'id');

        return $this->mukellefleriKaliciSil($idler, true);
    }

    // =================================================================
    //  BEYANNAME KAYITLARI — FİLTRELİ TOPLU TEMİZLİK
    // =================================================================

    /**
     * Filtreye uyan beyanname takip kayıtlarını sayar (ÖNİZLEME).
     *
     * @param array $f ['yil'=>, 'tur_id'=>, 'durum'=>, 'mukellef_id'=>, 'musavir_id'=>]
     *
     * @return array{adet:int,ornekler:array,durum_dagilimi:array}
     */
    public function beyannameOnizle(array $f): array
    {
        $b = $this->beyannameSorgusu($f);

        $adet = (int) $b->countAllResults(false);

        $ornekler = $b->select('beyanname_takip.id, beyanname_takip.donem_adi,
                                beyanname_takip.son_tarih, beyanname_takip.durum,
                                beyanname_takip.tahakkuk_tutari,
                                m.unvan AS mukellef_unvan, bt.kisa_ad AS tur_kisa')
            ->orderBy('beyanname_takip.son_tarih', 'DESC')
            ->limit(20)
            ->get()->getResultArray();

        // Durum dağılımı — kullanıcı ne sildiğini görsün
        $dagilim = [];

        foreach (array_keys(BeyannameTakipModel::DURUMLAR) as $d) {
            $s = $this->beyannameSorgusu($f)
                ->where('beyanname_takip.durum', $d)
                ->countAllResults();

            if ($s > 0) {
                $dagilim[$d] = $s;
            }
        }

        return ['adet' => $adet, 'ornekler' => $ornekler, 'durum_dagilimi' => $dagilim];
    }

    /**
     * Filtreye uyan beyanname takip kayıtlarını siler.
     *
     * @return array{silinen:int}
     */
    public function beyannameSil(array $f): array
    {
        // Güvenlik: hiçbir filtre yoksa TÜM tabloyu silmeyi reddet
        if (! $this->filtreVarMi($f)) {
            return ['silinen' => 0, 'hata' => 'En az bir filtre seçmelisiniz.'];
        }

        $idler = array_column(
            $this->beyannameSorgusu($f)->select('beyanname_takip.id')->get()->getResultArray(),
            'id'
        );

        if ($idler === []) {
            return ['silinen' => 0];
        }

        // Parça parça sil (çok büyük IN listesi sorgu sınırını aşmasın)
        $silinen = 0;

        foreach (array_chunk($idler, 1000) as $parca) {
            $this->db->table('beyanname_takip')->whereIn('id', $parca)->delete();
            $silinen += $this->db->affectedRows();
        }

        return ['silinen' => $silinen];
    }

    protected function beyannameSorgusu(array $f)
    {
        $b = $this->db->table('beyanname_takip')
            ->join('mukellefler m', 'm.id = beyanname_takip.mukellef_id')
            ->join('beyanname_turleri bt', 'bt.id = beyanname_takip.beyanname_turu_id')
            ->where('m.deleted_at', null);

        if (! empty($f['yil'])) {
            $b->where('beyanname_takip.yil', (int) $f['yil']);
        }

        if (! empty($f['tur_id'])) {
            $b->where('beyanname_takip.beyanname_turu_id', (int) $f['tur_id']);
        }

        if (! empty($f['durum'])) {
            $b->where('beyanname_takip.durum', $f['durum']);
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('beyanname_takip.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['musavir_id'])) {
            $b->where('m.musavir_id', (int) $f['musavir_id']);
        }

        return $b;
    }

    /** En az bir anlamlı filtre var mı? */
    public function filtreVarMi(array $f): bool
    {
        foreach (['yil', 'tur_id', 'durum', 'mukellef_id', 'musavir_id'] as $a) {
            if (! empty($f[$a])) {
                return true;
            }
        }

        return false;
    }

    // =================================================================
    //  EVRAK KAYITLARI
    // =================================================================

    public function evrakOnizle(array $f): array
    {
        $b = $this->evrakSorgusu($f);

        return ['adet' => (int) $b->countAllResults()];
    }

    public function evrakSil(array $f): array
    {
        if (empty($f['yil'])) {
            return ['silinen' => 0, 'hata' => 'Yıl seçmelisiniz.'];
        }

        $this->evrakSorgusu($f)->delete();

        return ['silinen' => $this->db->affectedRows()];
    }

    protected function evrakSorgusu(array $f)
    {
        $b = $this->db->table('evrak_takip');

        if (! empty($f['yil'])) {
            $b->where('yil', (int) $f['yil']);
        }

        if (! empty($f['ay'])) {
            $b->where('ay', (int) $f['ay']);
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('mukellef_id', (int) $f['mukellef_id']);
        }

        return $b;
    }

    // =================================================================
    //  GENEL İSTATİSTİK
    // =================================================================

    /** Veri Yönetimi ekranının üst sayaçları */
    public function istatistik(): array
    {
        return [
            'mukellef'     => (int) $this->db->table('mukellefler')
                ->where('deleted_at', null)->countAllResults(),
            'cop'          => (int) $this->db->table('mukellefler')
                ->where('deleted_at IS NOT NULL')->countAllResults(),
            'beyanname'    => (int) $this->db->table('beyanname_takip')->countAllResults(),
            'evrak'        => (int) $this->db->table('evrak_takip')->countAllResults(),
            'karsit'       => (int) $this->db->table('karsit_inceleme')->countAllResults(),
            'ozel_odeme'   => (int) $this->db->table('ozel_odemeler')->countAllResults(),
        ];
    }

    /** Beyanname kayıtlarının yıllara göre dağılımı */
    public function yilDagilimi(): array
    {
        return $this->db->table('beyanname_takip')
            ->select('yil, COUNT(*) AS adet')
            ->groupBy('yil')
            ->orderBy('yil', 'DESC')
            ->get()->getResultArray();
    }
}
