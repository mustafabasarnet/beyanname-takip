<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * YMM'lerden gelen karşıt inceleme tutanaklarının takibi.
 */
class KarsitIncelemeModel extends Model
{
    protected $table         = 'karsit_inceleme';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'mukellef_id', 'ymm_adi', 'gelis_tarihi', 'son_cevap_tarihi',
        'gonderim_tarihi', 'durum', 'not_metni', 'kaydeden_id',
    ];

    public const DURUMLAR = [
        'CEVAP_BEKLIYOR' => 'Cevap Bekliyor',
        'HAZIRLANIYOR'   => 'Hazırlanıyor',
        'GONDERILDI'     => 'Gönderildi',
        'IPTAL'          => 'İptal',
    ];

    protected $validationRules = [
        'mukellef_id'      => 'required|is_natural_no_zero',
        'ymm_adi'          => 'required|min_length[2]|max_length[200]',
        'gelis_tarihi'     => 'required|valid_date[Y-m-d]',
        'son_cevap_tarihi' => 'permit_empty|valid_date[Y-m-d]',
        'gonderim_tarihi'  => 'permit_empty|valid_date[Y-m-d]',
        'durum'            => 'required|in_list[CEVAP_BEKLIYOR,HAZIRLANIYOR,GONDERILDI,IPTAL]',
    ];

    protected $validationMessages = [
        'mukellef_id'  => ['required' => 'Mükellef seçimi zorunludur.'],
        'ymm_adi'      => ['required' => 'YMM adı zorunludur.'],
        'gelis_tarihi' => [
            'required'   => 'Geliş tarihi zorunludur.',
            'valid_date' => 'Geçerli bir geliş tarihi giriniz.',
        ],
    ];

    /**
     * Liste sorgusu.
     *
     * @param array $f ['durum','musavir_id'(int|int[]),'mukellef_id','q','yil','gecikmis']
     */
    public function listele(array $f = []): array
    {
        $b = $this->select('karsit_inceleme.*,
                            m.unvan as mukellef_unvan, m.vergi_kimlik_no, m.tc_kimlik_no,
                            mus.ad_soyad as musavir_adi, mus.renk as musavir_renk,
                            k.ad_soyad as kaydeden_adi')
            ->join('mukellefler m', 'm.id = karsit_inceleme.mukellef_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id', 'left')
            ->join('kullanicilar k', 'k.id = karsit_inceleme.kaydeden_id', 'left')
            ->where('m.deleted_at', null);

        if (! empty($f['durum'])) {
            $b->where('karsit_inceleme.durum', $f['durum']);
        }

        if (! empty($f['mukellef_id'])) {
            $b->where('karsit_inceleme.mukellef_id', (int) $f['mukellef_id']);
        }

        if (! empty($f['musavir_id'])) {
            if (is_array($f['musavir_id'])) {
                $b->whereIn('m.musavir_id', array_map('intval', $f['musavir_id']));
            } else {
                $b->where('m.musavir_id', (int) $f['musavir_id']);
            }
        }

        if (! empty($f['yil'])) {
            $b->where('YEAR(karsit_inceleme.gelis_tarihi)', (int) $f['yil']);
        }

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('m.unvan', $f['q'])
                ->orLike('karsit_inceleme.ymm_adi', $f['q'])
                ->orLike('m.vergi_kimlik_no', $f['q'])
              ->groupEnd();
        }

        // Süresi geçmiş ve hâlâ gönderilmemiş
        if (! empty($f['gecikmis'])) {
            $b->where('karsit_inceleme.son_cevap_tarihi IS NOT NULL', null, false)
              ->where('karsit_inceleme.son_cevap_tarihi <', date('Y-m-d'))
              ->whereIn('karsit_inceleme.durum', ['CEVAP_BEKLIYOR', 'HAZIRLANIYOR']);
        }

        return $b->orderBy('karsit_inceleme.durum = "GONDERILDI"', 'ASC', false)
            ->orderBy('karsit_inceleme.son_cevap_tarihi IS NULL', 'ASC', false)
            ->orderBy('karsit_inceleme.son_cevap_tarihi', 'ASC')
            ->orderBy('karsit_inceleme.gelis_tarihi', 'DESC')
            ->findAll();
    }

    /** Durum sayaçları */
    public function ozet($musavirId = null): array
    {
        $temel = function () use ($musavirId) {
            $b = $this->db->table('karsit_inceleme ki')
                ->join('mukellefler m', 'm.id = ki.mukellef_id')
                ->where('m.deleted_at', null);

            if (is_array($musavirId)) {
                if ($musavirId !== []) {
                    $b->whereIn('m.musavir_id', array_map('intval', $musavirId));
                }
            } elseif ($musavirId) {
                $b->where('m.musavir_id', (int) $musavirId);
            }

            return $b;
        };

        $sonuc = ['toplam' => $temel()->countAllResults()];

        foreach (array_keys(self::DURUMLAR) as $d) {
            $sonuc[strtolower($d)] = $temel()->where('ki.durum', $d)->countAllResults();
        }

        $sonuc['gecikmis'] = $temel()
            ->where('ki.son_cevap_tarihi IS NOT NULL', null, false)
            ->where('ki.son_cevap_tarihi <', date('Y-m-d'))
            ->whereIn('ki.durum', ['CEVAP_BEKLIYOR', 'HAZIRLANIYOR'])
            ->countAllResults();

        return $sonuc;
    }

    /** Durum değiştir (gönderildi ise tarihi damgala) */
    public function durumDegistir(int $id, string $durum): bool
    {
        if (! array_key_exists($durum, self::DURUMLAR)) {
            return false;
        }

        $veri = ['durum' => $durum];

        if ($durum === 'GONDERILDI') {
            $mevcut = $this->find($id);

            if ($mevcut !== null && empty($mevcut['gonderim_tarihi'])) {
                $veri['gonderim_tarihi'] = date('Y-m-d');
            }
        } elseif ($durum === 'CEVAP_BEKLIYOR' || $durum === 'HAZIRLANIYOR') {
            $veri['gonderim_tarihi'] = null;
        }

        return $this->update($id, $veri);
    }

    /** Dashboard: yaklaşan/geciken tutanaklar */
    public function yaklasanlar(int $gun = 7, $musavirId = null, int $limit = 10): array
    {
        $b = $this->select('karsit_inceleme.*, m.unvan as mukellef_unvan')
            ->join('mukellefler m', 'm.id = karsit_inceleme.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('karsit_inceleme.son_cevap_tarihi IS NOT NULL', null, false)
            ->where('karsit_inceleme.son_cevap_tarihi <=', date('Y-m-d', strtotime("+{$gun} days")))
            ->whereIn('karsit_inceleme.durum', ['CEVAP_BEKLIYOR', 'HAZIRLANIYOR']);

        if (is_array($musavirId)) {
            if ($musavirId !== []) {
                $b->whereIn('m.musavir_id', array_map('intval', $musavirId));
            }
        } elseif ($musavirId) {
            $b->where('m.musavir_id', (int) $musavirId);
        }

        return $b->orderBy('karsit_inceleme.son_cevap_tarihi', 'ASC')->findAll($limit);
    }

    /** Sık kullanılan YMM adları (otomatik tamamlama) */
    public function ymmListesi(): array
    {
        $rows = $this->select('ymm_adi, COUNT(*) as adet')
            ->groupBy('ymm_adi')
            ->orderBy('adet', 'DESC')
            ->findAll(50);

        return array_column($rows, 'ymm_adi');
    }
}
