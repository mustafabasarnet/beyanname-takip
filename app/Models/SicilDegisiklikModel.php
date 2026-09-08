<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SİCİL — İŞLEMLER (sicil_degisiklikleri)
 *
 * Sade modül kavramında bu tablo, bir mükellef için açılan "işlem"dir:
 *   Mükellef + Şablon + İşlem Tarihi  →  kaydedilince şablonun aktif todo
 *   tanımları otomatik üretilir (sicil_bildirim_gorevleri).
 *
 * Bütünlük ilkeleri:
 *   - Oluşturma TRANSACTION'lıdır: işlem + todo üretimi ya hep ya hiç.
 *   - İşlem tarihi güncellenirse AÇIK todo'ların son tarihi şablon tanımına
 *     göre yeniden hesaplanır; kapanmış (TAMAM/GEREKSIZ) satırlara dokunulmaz.
 *   - Şablon değiştirilemez (yeni işlem açılır — izlenebilirlik).
 *   - Silme yalnız soft (admin).
 */
class SicilDegisiklikModel extends Model
{
    protected $table         = 'sicil_degisiklikleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'mukellef_id', 'turu_id', 'degisiklik_tarihi', 'konu', 'aciklama',
        'durum', 'kaydeden_id', 'deleted_at',
    ];

    /** İşlem durumu görünen adları (durum görevlerden türetilir). */
    public const DURUMLAR = [
        'BEKLIYOR' => 'Todo Yok',
        'ISLEMDE'  => 'Devam Ediyor',
        'TAMAM'    => 'Tamamlandı',
    ];

    /** Formdan güncellenebilir alanlar (kritik alanlar hariç: şablon, mükellef) */
    public const GUNCELLENEBILIR = ['degisiklik_tarihi', 'aciklama', 'konu'];

    // =================================================================
    //  OLUŞTURMA (transaction + todo üretimi)
    // =================================================================

    /**
     * İşlemi kaydeder ve şablonun aktif todo tanımlarından satırları üretir
     * (atomik).
     *
     * @return array{durum:bool, id:?int, hata:?string,
     *               olusan_todolar:array, todo_yoksa:bool}
     */
    public function olustur(array $veri, int $kaydedenId): array
    {
        $veri['kaydeden_id'] = $kaydedenId;

        $db = $this->db;
        $db->transBegin();

        try {
            $ok = $this->insert($veri);

            if (! $ok) {
                $db->transRollback();

                return ['durum' => false, 'id' => null,
                        'hata'  => $this->errors() ? implode(' ', $this->errors()) : 'Kayıt başarısız.',
                        'olusan_todolar' => [], 'todo_yoksa' => false];
            }

            $id     = (int) $this->getInsertID();
            $kayit  = $this->find($id);
            $todolar = (new SicilGorevModel())->islemIcinUret($kayit, $kaydedenId);

            // Üst durum: todo üretildiyse Devam Ediyor, yoksa Todo Yok
            $this->update($id, ['durum' => $todolar === [] ? 'BEKLIYOR' : 'ISLEMDE']);

            $db->transCommit();

            return [
                'durum'         => true,
                'id'            => $id,
                'hata'          => null,
                'olusan_todolar' => $todolar,
                'todo_yoksa'    => $todolar === [],
            ];
        } catch (\Throwable $e) {
            $db->transRollback();

            return ['durum' => false, 'id' => null, 'hata' => $e->getMessage(),
                    'olusan_todolar' => [], 'todo_yoksa' => false];
        }
    }

    // =================================================================
    //  GÜNCELLEME
    // =================================================================

    /**
     * İşlemi günceller.
     *
     * Tarih değiştiyse AÇIK todo'ların son tarihi şablon tanımından yeniden
     * hesaplanır; kapanmış (TAMAM/GEREKSIZ) satırlar aynen kalır.
     */
    public function guncelle(int $id, array $veri, int $kullaniciId): array
    {
        $mevcut = $this->find($id);

        if ($mevcut === null) {
            return ['durum' => false, 'hata' => 'İşlem bulunamadı.'];
        }

        // Yalnız güncellenebilir alanları al
        $temiz = array_intersect_key($veri, array_flip(self::GUNCELLENEBILIR));
        $temiz = array_filter($temiz, static fn ($v) => $v !== null);

        $eskiTarih = (string) $mevcut['degisiklik_tarihi'];
        $yeniTarih = (string) ($temiz['degisiklik_tarihi'] ?? $eskiTarih);

        $db = $this->db;
        $db->transBegin();

        try {
            if ($temiz !== []) {
                if (! $this->update($id, $temiz)) {
                    $db->transRollback();

                    return ['durum' => false, 'hata' => $this->errors() ? implode(' ', $this->errors()) : 'Güncelleme başarısız.'];
                }
            }

            // Tarih değiştiyse açık todo'ların son tarihini yeniden hesapla
            if ($yeniTarih !== $eskiTarih) {
                $gorevModel = new SicilGorevModel();
                $acik       = $gorevModel->where('sicil_degisikligi_id', $id)
                    ->whereIn('durum', SicilGorevModel::ACIK_DURUMLAR)
                    ->where('kural_id !=', null)
                    ->where('deleted_at', null)
                    ->findAll();

                $tanimlar = (new SicilKuralModel())->findAll();
                $tanimMap = [];

                foreach ($tanimlar as $t) {
                    $tanimMap[(int) $t['id']] = $t;
                }

                foreach ($acik as $g) {
                    $tanim = $tanimMap[(int) $g['kural_id']] ?? null;

                    if ($tanim === null || (int) $tanim['aktif'] !== 1) {
                        continue;
                    }

                    $sonuc = SicilGorevModel::tarihHesapla($yeniTarih, $tanim);

                    if ($sonuc === null) {
                        continue;
                    }

                    $gorevModel->update((int) $g['id'], [
                        'son_tarih'       => $sonuc['son_tarih'],
                        'asil_tarih'      => $sonuc['asil_tarih'],
                        'kaydirma_nedeni' => $sonuc['neden'],
                    ]);
                }
            }

            // Üst durumu todo'lardan türet
            $this->durumTure($id);

            $db->transCommit();

            return ['durum' => true, 'hata' => null];
        } catch (\Throwable $e) {
            $db->transRollback();

            return ['durum' => false, 'hata' => $e->getMessage()];
        }
    }

    /**
     * Üst durumu todo'lardan türetir (elle değiştirilmez):
     *  - açık todo varsa              → ISLEMDE (Devam Ediyor)
     *  - todo yoksa                   → BEKLIYOR (Todo Yok)
     *  - tümü kapalıysa (TAMAM/GEREKSIZ) → TAMAM (Tamamlandı)
     */
    public function durumTure(int $id): string
    {
        $gorevModel = new SicilGorevModel();
        $acik       = (int) $gorevModel->where('sicil_degisikligi_id', $id)
            ->whereIn('durum', SicilGorevModel::ACIK_DURUMLAR)
            ->where('deleted_at', null)
            ->countAllResults();
        $toplam     = (int) $gorevModel->where('sicil_degisikligi_id', $id)
            ->where('deleted_at', null)
            ->countAllResults();

        $durum = $toplam === 0 ? 'BEKLIYOR' : ($acik > 0 ? 'ISLEMDE' : 'TAMAM');

        $this->update($id, ['durum' => $durum]);

        return $durum;
    }

    // =================================================================
    //  LİSTE / DETAY / GEÇMİŞ
    // =================================================================

    /**
     * Liste — kapsam + filtrelerle. Her işlem satırına todo ilerlemesi
     * (toplam/tamam/açık), en yakın açık son tarih ve geciken sayaç eklenir.
     *
     * @param array $f ['yil','turu_id'(int|int[]),'durum','mukellef_id',
     *                  'musavir_id','q','aralik'(gecikti|bugun|ic7|ic15|tamamlanan)]
     */
    public function listele(array $f = []): array
    {
        $b = $this->listeBuilder();

        $this->kapsamUygula($b, $f['musavir_id'] ?? null);

        if (! empty($f['mukellef_id'])) {
            $b->where('d.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['turu_id'])) {
            if (is_array($f['turu_id'])) {
                $b->whereIn('d.turu_id', array_map('intval', $f['turu_id']));
            } else {
                $b->where('d.turu_id', (int) $f['turu_id']);
            }
        }

        if (! empty($f['durum'])) {
            $b->where('d.durum', $f['durum']);
        }

        if (! empty($f['yil'])) {
            $b->where('YEAR(d.degisiklik_tarihi)', (int) $f['yil']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('m.unvan', $f['q'])
                ->orLike('m.vergi_kimlik_no', $f['q'])
                ->orLike('m.tc_kimlik_no', $f['q'])
                ->orLike('d.aciklama', $f['q'])
              ->groupEnd();
        }

        $this->aralikUygula($b, $f['aralik'] ?? null);

        return $b->orderBy('d.degisiklik_tarihi', 'DESC')
            ->orderBy('d.id', 'DESC')
            ->get()->getResultArray();
    }

    /** Detay — todo listesiyle birlikte. */
    public function detay(int $id): ?array
    {
        $satir = $this->listeBuilder()
            ->where('d.id', $id)
            ->get()->getRowArray();

        if ($satir === null) {
            return null;
        }

        $satir['todolar'] = (new SicilGorevModel())->islemTodoListesi($id);

        return $satir;
    }

    /** Kronolojik geçmiş: bir mükellefin işlemleri (en yeni üstte). */
    public function gecmis(int $mukellefId, $musavirIdler = null): array
    {
        return $this->listele(['mukellef_id' => $mukellefId, 'musavir_id' => $musavirIdler]);
    }

    /** Özet sayaçlar (liste üstü kartlar). */
    public function ozet($musavirIdler = null): array
    {
        $satirlar = $this->listele(['musavir_id' => $musavirIdler]);
        $acikTodo = 0;

        foreach ($satirlar as $s) {
            $acikTodo += (int) $s['acik_todo'];
        }

        return [
            'toplam'      => count($satirlar),
            'acik_todo'   => $acikTodo,
            'todoyok'     => count(array_filter($satirlar, static fn ($x) => $x['durum'] === 'BEKLIYOR')),
            'islemde'     => count(array_filter($satirlar, static fn ($x) => $x['durum'] === 'ISLEMDE')),
            'tamam'       => count(array_filter($satirlar, static fn ($x) => $x['durum'] === 'TAMAM')),
        ];
    }

    /** Yumuşak silme (yalnız admin — yetki controller'da). */
    public function sicilSil(int $id): bool
    {
        return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Liste sorgusunun ortak başlangıcı (join + soft delete + todo sayaçları).
     *
     * Sayı semantiği — "Takip dışı" (GEREKSIZ) todo'lar ilerlemeye DAHİL
     * EDİLMEZ:
     *   toplam_todo : değerlendirmeye alınan todo sayısı (GEREKSIZ hariç)
     *   tamam_todo  : Yapıldı (TAMAM) sayısı
     *   acik_todo   : açık (yapılmadı) sayısı  → toplam = tamam + açık
     *   gerek_todo  : takip dışı sayısı (yalnız bilgi)
     */
    protected function listeBuilder()
    {
        $acik = "ag.durum IN ('BEKLIYOR','HAZIR','GONDERILDI')";

        return $this->db->table('sicil_degisiklikleri d')
            ->select("d.*, t.ad AS tur_ad, t.kod AS tur_kod,
                      m.unvan AS mukellef_unvan, m.vergi_kimlik_no, m.tc_kimlik_no,
                      m.musavir_id,
                      mus.ad_soyad AS musavir_adi, mus.renk AS musavir_renk,
                      kk.ad_soyad AS kaydeden_adi,
                      (SELECT COUNT(*) FROM sicil_bildirim_gorevleri ag
                        WHERE ag.sicil_degisikligi_id = d.id
                          AND ag.deleted_at IS NULL
                          AND ag.durum <> 'GEREKSIZ') AS toplam_todo,
                      (SELECT COUNT(*) FROM sicil_bildirim_gorevleri ag
                        WHERE ag.sicil_degisikligi_id = d.id
                          AND ag.deleted_at IS NULL AND ag.durum = 'TAMAM') AS tamam_todo,
                      (SELECT COUNT(*) FROM sicil_bildirim_gorevleri ag
                        WHERE ag.sicil_degisikligi_id = d.id
                          AND ag.deleted_at IS NULL AND {$acik}) AS acik_todo,
                      (SELECT COUNT(*) FROM sicil_bildirim_gorevleri ag
                        WHERE ag.sicil_degisikligi_id = d.id
                          AND ag.deleted_at IS NULL AND ag.durum = 'GEREKSIZ') AS gerek_todo,
                      (SELECT MIN(ag.son_tarih) FROM sicil_bildirim_gorevleri ag
                        WHERE ag.sicil_degisikligi_id = d.id
                          AND ag.deleted_at IS NULL AND ag.son_tarih IS NOT NULL
                          AND {$acik}) AS en_yakin_son")
            ->join('sicil_degisiklik_turleri t', 't.id = d.turu_id')
            ->join('mukellefler m', 'm.id = d.mukellef_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->join('kullanicilar kk', 'kk.id = d.kaydeden_id', 'left')
            ->where('m.deleted_at', null)
            ->where('d.deleted_at', null);
    }

    protected function kapsamUygula($b, $musavirIdler): void
    {
        if ($musavirIdler === null || $musavirIdler === []) {
            return;
        }

        $idler = array_values(array_map('intval', $musavirIdler));

        if ($idler !== []) {
            $b->whereIn('m.musavir_id', $idler);
        }
    }

    /**
     * Zaman aralığı filtresi — açık todo'ların son tarihine göre.
     * Dashboard kartından gelen hızlı filtreler.
     */
    protected function aralikUygula($b, ?string $aralik): void
    {
        $aralik = (string) $aralik;

        if ($aralik === '' || $aralik === 'tumu') {
            return;
        }

        $bugun = date('Y-m-d');
        $acik  = "g.durum IN ('BEKLIYOR','HAZIR','GONDERILDI') AND g.deleted_at IS NULL";
        $kaynak = 'sicil_bildirim_gorevleri';

        $kosul = match ($aralik) {
            'gecikti'     => "EXISTS (SELECT 1 FROM {$kaynak} g WHERE g.sicil_degisikligi_id = d.id
                              AND {$acik} AND g.son_tarih IS NOT NULL AND g.son_tarih < '{$bugun}')",
            'bugun'       => "EXISTS (SELECT 1 FROM {$kaynak} g WHERE g.sicil_degisikligi_id = d.id
                              AND {$acik} AND g.son_tarih = '{$bugun}')",
            'ic7'         => "EXISTS (SELECT 1 FROM {$kaynak} g WHERE g.sicil_degisikligi_id = d.id
                              AND {$acik} AND g.son_tarih >= '{$bugun}'
                              AND g.son_tarih <= '" . date('Y-m-d', strtotime('+7 days')) . "')",
            'ic15'        => "EXISTS (SELECT 1 FROM {$kaynak} g WHERE g.sicil_degisikligi_id = d.id
                              AND {$acik} AND g.son_tarih >= '{$bugun}'
                              AND g.son_tarih <= '" . date('Y-m-d', strtotime('+15 days')) . "')",
            'tamamlanan'  => "NOT EXISTS (SELECT 1 FROM {$kaynak} g WHERE g.sicil_degisikligi_id = d.id
                              AND {$acik})
                              AND EXISTS (SELECT 1 FROM {$kaynak} g2
                                WHERE g2.sicil_degisikligi_id = d.id AND g2.deleted_at IS NULL)",
            default       => null,
        };

        if ($kosul !== null) {
            $b->where($kosul);
        }
    }
}
