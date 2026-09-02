<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SERBEST MESLEK MAKBUZU TAKİBİ
 *
 * "Hangi mükellefe yıl içinde ne kadar makbuz kestik, ne kadar kaldı?"
 *
 * Hedef  = mukellef_ucretleri.tutar (yıllık sözleşme ücreti)
 * Kesilen = makbuzlar.brut toplamı
 * Kalan  = hedef - kesilen
 *
 * Tutarlar makbuza KAYDEDİLİR (her seferinde hesaplanmaz); stopaj/KDV oranı
 * sonradan değişse bile geçmiş makbuzlar bozulmaz.
 */
class MakbuzModel extends Model
{
    protected $table         = 'makbuzlar';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'mukellef_id', 'musavir_id', 'yil', 'ay', 'makbuz_no', 'tarih',
        'brut', 'stopaj', 'kdv', 'net', 'tahsil_edildi', 'tahsil_tarihi',
        'aciklama', 'kaydeden_id',
    ];

    protected $validationRules = [
        'mukellef_id' => 'required|is_natural_no_zero',
        'tarih'       => 'required|valid_date[Y-m-d]',
        'brut'        => 'required|decimal',
    ];

    protected $validationMessages = [
        'mukellef_id' => ['required' => 'Mükellef seçimi zorunludur.'],
        'tarih'       => ['required' => 'Makbuz tarihi zorunludur.'],
        'brut'        => ['required' => 'Brüt tutar zorunludur.'],
    ];

    /** Ayar okuma (varsayılanlı, süreç içinde önbellekli) */
    protected function ayar(string $anahtar, $varsayilan)
    {
        static $onbellek = [];

        if (! array_key_exists($anahtar, $onbellek)) {
            $onbellek[$anahtar] = (new AyarModel())->oku($anahtar, $varsayilan);
        }

        return $onbellek[$anahtar];
    }

    public function stopajOrani(): float
    {
        return (float) $this->ayar('makbuz_stopaj_oran', 20);
    }

    public function kdvOrani(): float
    {
        return (float) $this->ayar('makbuz_kdv_oran', 20);
    }

    /**
     * Brüt tutardan stopaj / KDV / net hesaplar.
     *
     * @param float      $brut     Brüt (stopaj matrahı)
     * @param float|null $stopaj   Verilirse kullanılır, yoksa orandan hesaplanır
     * @param float|null $kdv      Verilirse kullanılır, yoksa orandan hesaplanır
     *
     * @return array{brut:float,stopaj:float,kdv:float,net:float}
     */
    public function tutarHesapla(float $brut, ?float $stopaj = null, ?float $kdv = null): array
    {
        $brut   = round($brut, 2);
        $stopaj = $stopaj !== null ? round($stopaj, 2) : round($brut * $this->stopajOrani() / 100, 2);
        $kdv    = $kdv !== null ? round($kdv, 2) : round($brut * $this->kdvOrani() / 100, 2);

        // Mükellefin elden ödediği tutar: brütten stopaj düşülür, KDV eklenir
        return [
            'brut'   => $brut,
            'stopaj' => $stopaj,
            'kdv'    => $kdv,
            'net'    => round($brut - $stopaj + $kdv, 2),
        ];
    }

    /** Kaydetmeden önce tutarları tamamlar */
    public function makbuzKaydet(array $veri, ?int $id = null)
    {
        $h = $this->tutarHesapla(
            (float) ($veri['brut'] ?? 0),
            isset($veri['stopaj']) && $veri['stopaj'] !== '' ? (float) $veri['stopaj'] : null,
            isset($veri['kdv']) && $veri['kdv'] !== '' ? (float) $veri['kdv'] : null
        );

        $veri = array_merge($veri, $h);

        // Yıl/ay verilmemişse makbuz tarihinden türetilir
        if (empty($veri['yil']) && ! empty($veri['tarih'])) {
            $veri['yil'] = (int) date('Y', strtotime($veri['tarih']));
        }

        if (empty($veri['ay']) && ! empty($veri['tarih'])) {
            $veri['ay'] = (int) date('n', strtotime($veri['tarih']));
        }

        return $id !== null && $id > 0 ? $this->update($id, $veri) : $this->insert($veri);
    }

    // =================================================================
    //  MÜKELLEF BAZINDA DURUM
    // =================================================================

    protected function musavirKosulu($b, $musavirId, string $alan = 'm.musavir_id')
    {
        if (is_array($musavirId)) {
            if ($musavirId !== []) {
                $b->whereIn($alan, array_map('intval', $musavirId));
            }
        } elseif ($musavirId) {
            $b->where($alan, (int) $musavirId);
        }

        return $b;
    }

    /**
     * Mükellef bazında ücret / kesilen / kalan çizelgesi.
     *
     * Ücreti girilmemiş mükellefler de listelenir (hedef 0 görünür) ki
     * eksik tanım gözden kaçmasın.
     *
     * @param array $f ['yil','musavir_id','durum','q','limit','ofset']
     */
    public function cizelge(array $f): array
    {
        $b = $this->cizelgeSorgusu($f);

        $b->orderBy('m.unvan', 'ASC');

        $rows = ! empty($f['limit'])
            ? $b->get((int) $f['limit'], (int) ($f['ofset'] ?? 0))->getResultArray()
            : $b->get()->getResultArray();

        foreach ($rows as &$r) {
            $r['ucret']   = (float) $r['ucret'];
            $r['kesilen'] = (float) $r['kesilen'];
            $r['kalan']   = round($r['ucret'] - $r['kesilen'], 2);
            $r['oran']    = $r['ucret'] > 0
                ? min(100, (int) round($r['kesilen'] / $r['ucret'] * 100))
                : ($r['kesilen'] > 0 ? 100 : 0);
            $r['adet']    = (int) $r['adet'];
        }

        unset($r);

        return $rows;
    }

    public function cizelgeSayisi(array $f): int
    {
        return $this->cizelgeSorgusu($f)->countAllResults();
    }

    /**
     * cizelge() / cizelgeSayisi() ortak sorgusu.
     *
     * Not: makbuz toplamı ALT SORGU ile alınır. JOIN + GROUP BY yerine bu
     * yöntem seçildi çünkü mükellefin birden çok makbuzu varken ücret
     * satırıyla çarpım (kartezyen) oluşup toplamlar şişebilirdi.
     */
    protected function cizelgeSorgusu(array $f)
    {
        $yil = (int) ($f['yil'] ?? date('Y'));

        $b = $this->db->table('mukellefler m')
            ->select("m.id AS mukellef_id, m.unvan, m.kod, m.vergi_kimlik_no, m.tc_kimlik_no,
                      m.mukellef_tipi, m.musavir_id,
                      mus.ad_soyad AS musavir_adi, mus.renk AS musavir_renk,
                      COALESCE(u.tutar, 0) AS ucret,
                      COALESCE((SELECT SUM(mk.brut) FROM makbuzlar mk
                                WHERE mk.mukellef_id = m.id AND mk.yil = {$yil}), 0) AS kesilen,
                      COALESCE((SELECT COUNT(*) FROM makbuzlar mk
                                WHERE mk.mukellef_id = m.id AND mk.yil = {$yil}), 0) AS adet,
                      (SELECT MAX(mk.tarih) FROM makbuzlar mk
                       WHERE mk.mukellef_id = m.id AND mk.yil = {$yil}) AS son_makbuz")
            ->join("mukellef_ucretleri u", "u.mukellef_id = m.id AND u.yil = {$yil}", 'left')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->where('m.deleted_at', null);

        // Terk etmiş mükellefler varsayılan olarak gizlenir
        if (empty($f['pasif_dahil'])) {
            $b->where('m.aktif', 1);
        }

        if (! empty($f['musavir_id'])) {
            $this->musavirKosulu($b, $f['musavir_id']);
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('m.id', (int) $f['mukellef_id']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('m.unvan', $f['q'])
                ->orLike('m.vergi_kimlik_no', $f['q'])
                ->orLike('m.tc_kimlik_no', $f['q'])
                ->orLike('m.kod', $f['q'])
              ->groupEnd();
        }

        // -------------------------------------------------------------
        //  Durum süzgeci
        //
        //  DİKKAT: MySQL, HAVING içinde SELECT'teki alt sorgu TAKMA ADINI
        //  ("kesilen") tanımaz — "Unknown column 'kesilen' in 'HAVING'"
        //  hatası verir. Bu yüzden ifadeler WHERE içinde, alt sorgu
        //  tekrarlanarak yazılır. Sonuç aynı, hata yok.
        // -------------------------------------------------------------
        $durum   = $f['durum'] ?? '';
        $kesilenA = "COALESCE((SELECT SUM(mk2.brut) FROM makbuzlar mk2
                     WHERE mk2.mukellef_id = m.id AND mk2.yil = {$yil}), 0)";
        $ucretA   = 'COALESCE(u.tutar, 0)';

        if ($durum === 'UCRETSIZ') {
            $b->where("{$ucretA} <= 0", null, false);
        } elseif ($durum === 'BASLAMADI') {
            $b->where("{$ucretA} > 0", null, false)
              ->where("{$kesilenA} <= 0", null, false);
        } elseif ($durum === 'DEVAM') {
            $b->where("{$ucretA} > 0", null, false)
              ->where("{$kesilenA} > 0", null, false)
              ->where("{$kesilenA} < {$ucretA}", null, false);
        } elseif ($durum === 'TAMAM') {
            $b->where("{$ucretA} > 0", null, false)
              ->where("{$kesilenA} >= {$ucretA}", null, false);
        } elseif ($durum === 'ASIM') {
            $b->where("{$ucretA} > 0", null, false)
              ->where("{$kesilenA} > {$ucretA}", null, false);
        }

        return $b;
    }

    /**
     * Sayfa özeti (filtreye uyan TÜM kayıtlar üzerinden).
     * Sayfalama sayıları etkilemez.
     */
    public function ozet(array $f): array
    {
        $satirlar = $this->cizelge(array_diff_key($f, ['limit' => 1, 'ofset' => 1]));

        $o = [
            'mukellef' => count($satirlar),
            'ucret'    => 0.0,
            'kesilen'  => 0.0,
            'kalan'    => 0.0,
            'adet'     => 0,
            'ucretsiz' => 0,
            'tamam'    => 0,
        ];

        foreach ($satirlar as $s) {
            $o['ucret']   += $s['ucret'];
            $o['kesilen'] += $s['kesilen'];
            $o['adet']    += $s['adet'];

            if ($s['ucret'] <= 0) {
                $o['ucretsiz']++;
            } elseif ($s['kesilen'] >= $s['ucret']) {
                $o['tamam']++;
            }
        }

        $o['kalan'] = round($o['ucret'] - $o['kesilen'], 2);
        $o['oran']  = $o['ucret'] > 0 ? (int) round($o['kesilen'] / $o['ucret'] * 100) : 0;

        return $o;
    }

    /**
     * MALİ MÜŞAVİR BAZINDA ÖZET
     * "Hangi müşavir ne kadar makbuz kesmiş?"
     */
    public function musavirOzeti(int $yil, $musavirId = null): array
    {
        // Hedef: müşavirin portföyündeki mükelleflerin yıllık ücret toplamı
        $hedefB = $this->db->table('mukellefler m')
            ->select('m.musavir_id, mus.ad_soyad, mus.renk,
                      COUNT(m.id) AS mukellef,
                      COALESCE(SUM(u.tutar),0) AS ucret')
            ->join("mukellef_ucretleri u", "u.mukellef_id = m.id AND u.yil = {$yil}", 'left')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->where('m.deleted_at', null)
            ->where('m.aktif', 1);

        $this->musavirKosulu($hedefB, $musavirId);

        $hedef = $hedefB->groupBy('m.musavir_id, mus.ad_soyad, mus.renk')->get()->getResultArray();

        // Gerçekleşen: mükellefin BAĞLI OLDUĞU müşavire göre makbuz toplamı.
        // Makbuzu kim keserse kessin (izinli meslektaş vb.), hasılat mükellefin
        // portföy sahibinde görünür — böylece üst özet kartı, mükellef listesi
        // ve Vergi Yükü hesapları tek eksende tutarlı olur.
        $kesB = $this->db->table('makbuzlar mk')
            ->select('m.musavir_id, COUNT(*) AS adet, SUM(mk.brut) AS kesilen,
                      SUM(mk.stopaj) AS stopaj, SUM(mk.kdv) AS kdv')
            ->join('mukellefler m', 'm.id = mk.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('mk.yil', $yil);

        $this->musavirKosulu($kesB, $musavirId);

        $kesilenler = [];

        foreach ($kesB->groupBy('m.musavir_id')->get()->getResultArray() as $r) {
            $kesilenler[(int) $r['musavir_id']] = $r;
        }

        $out = [];

        foreach ($hedef as $h) {
            $mid = (int) $h['musavir_id'];
            $k   = $kesilenler[$mid] ?? ['adet' => 0, 'kesilen' => 0, 'stopaj' => 0, 'kdv' => 0];

            $ucret   = (float) $h['ucret'];
            $kesilen = (float) $k['kesilen'];

            $out[] = [
                'musavir_id' => $mid,
                'ad_soyad'   => $h['ad_soyad'] ?: '— Atanmamış —',
                'renk'       => $h['renk'] ?: '#94a3b8',
                'mukellef'   => (int) $h['mukellef'],
                'ucret'      => $ucret,
                'kesilen'    => $kesilen,
                'kalan'      => round($ucret - $kesilen, 2),
                'adet'       => (int) $k['adet'],
                'stopaj'     => (float) $k['stopaj'],
                'kdv'        => (float) $k['kdv'],
                'oran'       => $ucret > 0 ? min(100, (int) round($kesilen / $ucret * 100)) : 0,
            ];
        }

        usort($out, static fn ($a, $b) => strcoll($a['ad_soyad'], $b['ad_soyad']));

        return $out;
    }

    /** Bir mükellefin yıl içindeki makbuzları */
    public function mukellefMakbuzlari(int $mukellefId, int $yil): array
    {
        return $this->select('makbuzlar.*, mus.ad_soyad AS musavir_adi')
            ->join('musavirler mus', 'mus.id = makbuzlar.musavir_id', 'left')
            ->where('makbuzlar.mukellef_id', $mukellefId)
            ->where('makbuzlar.yil', $yil)
            ->orderBy('makbuzlar.tarih', 'ASC')
            ->orderBy('makbuzlar.id', 'ASC')
            ->findAll();
    }

    /**
     * Aynı makbuz zaten var mı? (Excel içe aktarımda mükerrer önleme)
     * Makbuz no + mükellef + yıl birlikte benzersiz kabul edilir.
     */
    public function mukerrerMi(int $mukellefId, int $yil, ?string $makbuzNo, string $tarih, float $brut): bool
    {
        $b = $this->where('mukellef_id', $mukellefId)->where('yil', $yil);

        if ($makbuzNo !== null && $makbuzNo !== '') {
            return $b->where('makbuz_no', $makbuzNo)->countAllResults() > 0;
        }

        // Makbuz no yoksa tarih + tutar eşleşmesine bakılır
        return $b->where('tarih', $tarih)
            ->where('brut', $brut)
            ->countAllResults() > 0;
    }

    // =================================================================
    //  YILLIK ÜCRET
    // =================================================================

    /** Mükellefin ilgili yıl ücretini okur */
    public function ucretAl(int $mukellefId, int $yil): float
    {
        $r = $this->db->table('mukellef_ucretleri')
            ->select('tutar')
            ->where('mukellef_id', $mukellefId)->where('yil', $yil)
            ->get()->getRowArray();

        return $r === null ? 0.0 : (float) $r['tutar'];
    }

    /** Yıllık ücreti yazar (varsa günceller) */
    public function ucretYaz(int $mukellefId, int $yil, float $tutar, ?string $aciklama = null): bool
    {
        $t   = $this->db->table('mukellef_ucretleri');
        $var = $t->where('mukellef_id', $mukellefId)->where('yil', $yil)->get()->getRowArray();

        $veri = [
            'tutar'      => round($tutar, 2),
            'aciklama'   => $aciklama,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($var === null) {
            return $t->insert($veri + [
                'mukellef_id' => $mukellefId,
                'yil'         => $yil,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->db->table('mukellef_ucretleri')->where('id', $var['id'])->update($veri);
    }

    /**
     * Bir yılın ücretlerini başka yıla kopyalar (zam oranı uygulanabilir).
     * Tarife her yıl değiştiği için pratik bir başlangıç sağlar.
     *
     * @return array{eklenen:int,atlanan:int}
     */
    public function ucretKopyala(int $kaynakYil, int $hedefYil, float $zamYuzde = 0, $musavirId = null): array
    {
        $b = $this->db->table('mukellef_ucretleri u')
            ->select('u.mukellef_id, u.tutar')
            ->join('mukellefler m', 'm.id = u.mukellef_id')
            ->where('u.yil', $kaynakYil)
            ->where('m.deleted_at', null)
            ->where('m.aktif', 1);

        $this->musavirKosulu($b, $musavirId);

        $sonuc = ['eklenen' => 0, 'atlanan' => 0];
        $carpan = 1 + ($zamYuzde / 100);

        foreach ($b->get()->getResultArray() as $r) {
            $mid = (int) $r['mukellef_id'];

            // Hedef yılda kayıt varsa DOKUNULMAZ (elle girilmiş olabilir)
            if ($this->db->table('mukellef_ucretleri')
                ->where('mukellef_id', $mid)->where('yil', $hedefYil)
                ->countAllResults() > 0) {
                $sonuc['atlanan']++;

                continue;
            }

            $this->ucretYaz($mid, $hedefYil, round((float) $r['tutar'] * $carpan, 2),
                $kaynakYil . ' yılından kopyalandı' . ($zamYuzde != 0 ? ' (%' . $zamYuzde . ' zam)' : ''));
            $sonuc['eklenen']++;
        }

        return $sonuc;
    }

    /** Ücret tanımlı yıllar (filtre için) */
    public function ucretYillari(): array
    {
        $rows = $this->db->table('mukellef_ucretleri')
            ->select('DISTINCT yil', false)->orderBy('yil', 'DESC')->get()->getResultArray();

        return array_map(static fn ($r) => (int) $r['yil'], $rows);
    }
}
