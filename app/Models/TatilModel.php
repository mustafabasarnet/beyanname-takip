<?php

namespace App\Models;

use CodeIgniter\Model;

class TatilModel extends Model
{
    protected $table         = 'tatiller';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['tarih', 'ad', 'tip', 'yarim_gun', 'aktif'];

    /**
     * Varsayılan (EKLEME) kuralları.
     * Güncelleme için kurallariGuncelle() kullanılır — bkz. KullaniciModel açıklaması.
     */
    protected $validationRules = [
        'tarih' => 'required|valid_date[Y-m-d]|is_unique[tatiller.tarih]',
        'ad'    => 'required|max_length[150]',
        'tip'   => 'required|in_list[RESMI,DINI,ARIFE,MALI_TATIL,IDARI_IZIN]',
    ];

    protected $validationMessages = [
        'tarih' => [
            'is_unique'  => 'Bu tarih için zaten bir tatil kaydı var.',
            'valid_date' => 'Geçerli bir tarih giriniz.',
        ],
    ];

    /** Güncellemede benzersizlik kontrolünden düzenlenen kaydı hariç tutar. */
    public function kurallariGuncelle(int $id): array
    {
        $kurallar          = $this->validationRules;
        $kurallar['tarih'] = 'required|valid_date[Y-m-d]|is_unique[tatiller.tarih,id,' . $id . ']';

        return $kurallar;
    }

    public function kurallarMesajlari(): array
    {
        return $this->validationMessages;
    }

    public function yilaGore(int $yil): array
    {
        return $this->where('YEAR(tarih)', $yil)->orderBy('tarih', 'ASC')->findAll();
    }

    public function yillar(): array
    {
        $rows = $this->select('DISTINCT YEAR(tarih) as yil', false)
            ->orderBy('yil', 'DESC')->findAll();

        return array_column($rows, 'yil');
    }
}
