<?php

namespace App\Models;

use CodeIgniter\Model;

class MusavirModel extends Model
{
    protected $table            = 'musavirler';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'unvan', 'ad_soyad', 'buro_adi', 'tc_kimlik', 'ruhsat_no', 'oda_sicil_no',
        'telefon', 'eposta', 'adres', 'renk', 'aktif',
    ];

    protected $validationRules = [
        'ad_soyad' => 'required|min_length[3]|max_length[150]',
        'eposta'   => 'permit_empty|valid_email',
    ];

    protected $validationMessages = [
        'ad_soyad' => [
            'required'   => 'Mali müşavir adı soyadı zorunludur.',
            'min_length' => 'Ad soyad en az 3 karakter olmalıdır.',
        ],
        'eposta' => ['valid_email' => 'Geçerli bir e-posta adresi giriniz.'],
    ];

    /** Aktif müşavirleri dropdown için döndürür */
    public function seceneklar(): array
    {
        $rows = $this->where('aktif', 1)->orderBy('ad_soyad', 'ASC')->findAll();
        $out  = [];

        foreach ($rows as $r) {
            $out[$r['id']] = trim(($r['unvan'] ? $r['unvan'] . ' ' : '') . $r['ad_soyad']);
        }

        return $out;
    }

    /** Müşavir bazlı mükellef sayıları */
    public function mukellefSayilari(): array
    {
        $db = \Config\Database::connect();

        $rows = $db->table('mukellefler')
            ->select('musavir_id, COUNT(*) as adet')
            ->where('deleted_at', null)
            ->where('aktif', 1)
            ->groupBy('musavir_id')
            ->get()->getResultArray();

        return array_column($rows, 'adet', 'musavir_id');
    }
}
