<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SİCİL — ŞABLONLAR (sicil_degisiklik_turleri)
 *
 * Sade modül kavramında bu tablo "İşlem Şablonları"nı tutar
 * (Adres Değişikliği, Ortak Değişikliği, Devir, …). Altındaki todo
 * tanımları sicil_bildirim_kurallari tablosundadır.
 *
 * Yönetim Şablonlar ekranından (SicilSablon) yapılır; kodda sabit liste yok.
 * Pasife alınan şablon yeni işlemde seçilemez; geçmiş kayıtlar korunur.
 */
class SicilTurModel extends Model
{
    protected $table         = 'sicil_degisiklik_turleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['kod', 'ad', 'aciklama', 'sira', 'aktif'];

    protected $validationRules = [
        'kod' => 'required|alpha_dash|max_length[50]|is_unique[sicil_degisiklik_turleri.kod,id,{id}]',
        'ad'  => 'required|max_length[150]',
    ];

    protected $validationMessages = [
        'kod' => ['required' => 'Şablon kodu zorunludur.', 'is_unique' => 'Bu kod zaten kullanılıyor.'],
        'ad'  => ['required' => 'Şablon adı zorunludur.'],
    ];

    /** Aktif şablonlar (yeni işlem formu). */
    public function aktifler(): array
    {
        return $this->where('aktif', 1)
            ->orderBy('sira', 'ASC')
            ->orderBy('ad', 'ASC')
            ->findAll();
    }

    /** Açılır liste için [id => ad]. */
    public function secenekler(bool $sadeceAktif = true): array
    {
        $b = $this->select('id, ad');

        if ($sadeceAktif) {
            $b->where('aktif', 1);
        }

        $out = [];

        foreach ($b->orderBy('sira', 'ASC')->orderBy('ad', 'ASC')->findAll() as $r) {
            $out[(int) $r['id']] = $r['ad'];
        }

        return $out;
    }

    /**
     * Şablon listesi (Şablonlar ekranı) — aktif todo tanım sayısıyla.
     *
     * @param array $f ['q', 'aktif'(0|1|null)]
     */
    public function listele(array $f = []): array
    {
        $b = $this->db->table('sicil_degisiklik_turleri t')
            ->select("t.*,
                      (SELECT COUNT(*) FROM sicil_bildirim_kurallari k
                        WHERE k.degisiklik_turu_id = t.id AND k.aktif = 1)  AS aktif_todo,
                      (SELECT COUNT(*) FROM sicil_bildirim_kurallari k2
                        WHERE k2.degisiklik_turu_id = t.id)                 AS toplam_todo,
                      (SELECT COUNT(*) FROM sicil_degisiklikleri d
                        WHERE d.turu_id = t.id AND d.deleted_at IS NULL)    AS islem_sayisi");

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('t.ad', $f['q'])
                ->orLike('t.aciklama', $f['q'])
              ->groupEnd();
        }

        if (array_key_exists('aktif', $f) && $f['aktif'] !== null && $f['aktif'] !== '') {
            $b->where('t.aktif', (int) $f['aktif']);
        }

        return $b->orderBy('t.sira', 'ASC')->orderBy('t.ad', 'ASC')->get()->getResultArray();
    }

    /** Şablonun aktif todo tanım sayısı. */
    public function aktifTodoSayisi(int $id): int
    {
        return (int) $this->db->table('sicil_bildirim_kurallari')
            ->where('degisiklik_turu_id', $id)
            ->where('aktif', 1)
            ->countAllResults();
    }

    /** Şablonu pasife alır (geçmiş işlemler korunur). */
    public function pasifeAl(int $id): bool
    {
        return $this->update($id, ['aktif' => 0]);
    }

    /**
     * Şablonu (başlık + todo tanımları) TEK TRANSACTION'da kaydeder.
     *
     * @param array $veri         ['id','ad','aciklama','aktif','sira']
     * @param array $todoSatirlari SicilKuralModel::senkronla biçiminde
     *
     * @return array{durum:bool, id:?int, hata:?string}
     */
    public function sablonKaydet(array $veri, array $todoSatirlari, int $kullaniciId): array
    {
        $id = (int) ($veri['id'] ?? 0);

        $temiz = [
            'ad'        => trim((string) ($veri['ad'] ?? '')),
            'aciklama'  => trim((string) ($veri['aciklama'] ?? '')) ?: null,
            'aktif'     => empty($veri['aktif']) ? 0 : 1,
            'sira'      => (int) ($veri['sira'] ?? 0),
        ];

        if ($temiz['ad'] === '') {
            return ['durum' => false, 'id' => null, 'hata' => 'Şablon adı zorunludur.'];
        }

        $db = $this->db;
        $db->transBegin();

        try {
            if ($id > 0) {
                $mevcut = $this->find($id);

                if ($mevcut === null) {
                    $db->transRollback();

                    return ['durum' => false, 'id' => null, 'hata' => 'Şablon bulunamadı.'];
                }

                $temiz['kod'] = $mevcut['kod']; // kod değişmez (geçmiş kayıtlar ona bağlıdır)

                if (! $this->update($id, $temiz)) {
                    $db->transRollback();

                    return ['durum' => false, 'id' => null,
                            'hata'  => $this->errors() ? implode(' ', $this->errors()) : 'Şablon güncellenemedi.'];
                }
            } else {
                $temiz['kod'] = $this->kodUret($temiz['ad']);

                if (! $this->insert($temiz)) {
                    $db->transRollback();

                    return ['durum' => false, 'id' => null,
                            'hata'  => $this->errors() ? implode(' ', $this->errors()) : 'Şablon eklenemedi.'];
                }

                $id = (int) $this->getInsertID();
            }

            $sonuc = (new SicilKuralModel())->senkronla($id, $todoSatirlari, $kullaniciId);

            if (! $sonuc['durum']) {
                $db->transRollback();

                return ['durum' => false, 'id' => $id, 'hata' => $sonuc['hata']];
            }

            $db->transCommit();

            return ['durum' => true, 'id' => $id, 'hata' => null];
        } catch (\Throwable $e) {
            $db->transRollback();

            return ['durum' => false, 'id' => $id, 'hata' => $e->getMessage()];
        }
    }

    /** Ad'dan benzersiz makine kodu üretir (Türkçe karakterler ASCII'ye çevrilir). */
    protected function kodUret(string $ad): string
    {
        $tr = [
            'Ç' => 'C', 'Ğ' => 'G', 'İ' => 'I', 'Ö' => 'O', 'Ş' => 'S', 'Ü' => 'U',
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
        ];
        $taban = strtr($ad, $tr);
        $taban = preg_replace('/[^A-Za-z0-9]+/', '_', $taban) ?: '';
        $taban = strtoupper(trim($taban, '_'));

        if ($taban === '') {
            $taban = 'SABLON';
        }

        $taban = substr($taban, 0, 40);
        $kod   = $taban;
        $i     = 1;

        while ($this->where('kod', $kod)->countAllResults() > 0) {
            $kod = substr($taban, 0, 36) . '_' . ($i++);
        }

        return $kod;
    }
}
