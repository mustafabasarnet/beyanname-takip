<?php

namespace App\Models;

use CodeIgniter\Model;

class AyarModel extends Model
{
    protected $table         = 'ayarlar';
    protected $primaryKey    = 'anahtar';
    protected $returnType    = 'array';
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;
    protected $allowedFields = ['anahtar', 'deger', 'aciklama', 'updated_at'];

    /** Tüm ayarları anahtar=>deger dizisi olarak verir */
    public function tumu(): array
    {
        return array_column($this->findAll(), 'deger', 'anahtar');
    }

    public function oku(string $anahtar, $varsayilan = null)
    {
        $row = $this->find($anahtar);

        return $row === null ? $varsayilan : $row['deger'];
    }

    public function yaz(string $anahtar, $deger): void
    {
        $var = $this->find($anahtar);
        $now = date('Y-m-d H:i:s');

        if ($var === null) {
            $this->insert(['anahtar' => $anahtar, 'deger' => (string) $deger, 'updated_at' => $now]);
        } else {
            $this->update($anahtar, ['deger' => (string) $deger, 'updated_at' => $now]);
        }
    }
}
