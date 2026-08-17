<?php

namespace App\Models;

use CodeIgniter\Model;

class EvrakTuruModel extends Model
{
    protected $table         = 'evrak_turleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['ad', 'kisa_ad', 'sira', 'aktif'];

    protected $validationRules = [
        'ad'      => 'required|max_length[120]',
        'kisa_ad' => 'required|max_length[40]',
    ];

    public function aktifler(): array
    {
        return $this->where('aktif', 1)->orderBy('sira', 'ASC')->findAll();
    }
}
