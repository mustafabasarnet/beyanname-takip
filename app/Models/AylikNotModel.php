<?php

namespace App\Models;

use CodeIgniter\Model;

class AylikNotModel extends Model
{
    protected $table         = 'mukellef_aylik_not';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['mukellef_id', 'yil', 'ay', 'not_metni'];

    public function notAl(int $mukellefId, int $yil, int $ay): ?string
    {
        $row = $this->where(['mukellef_id' => $mukellefId, 'yil' => $yil, 'ay' => $ay])->first();

        return $row['not_metni'] ?? null;
    }

    public function notKaydet(int $mukellefId, int $yil, int $ay, ?string $metin): void
    {
        $row = $this->where(['mukellef_id' => $mukellefId, 'yil' => $yil, 'ay' => $ay])->first();

        if ($row === null) {
            $this->insert([
                'mukellef_id' => $mukellefId,
                'yil'         => $yil,
                'ay'          => $ay,
                'not_metni'   => $metin,
            ]);
        } else {
            $this->update($row['id'], ['not_metni' => $metin]);
        }
    }

    /** Bir yıl için mükellefin tüm ay notları [ay => metin] */
    public function yilNotlari(int $mukellefId, int $yil): array
    {
        $rows = $this->where(['mukellef_id' => $mukellefId, 'yil' => $yil])->findAll();

        return array_column($rows, 'not_metni', 'ay');
    }
}
