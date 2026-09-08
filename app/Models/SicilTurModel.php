<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SİCİL — DEĞİŞİKLİK TÜRLERİ (tanım tablosu)
 *
 * Değişiklik türleri menüden yönetilir; kodda sabit liste yoktur.
 * Pasife alınan tür yeni değişiklikte seçilemez; geçmiş kayıtlar korunur.
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
        'kod' => ['required' => 'Tür kodu zorunludur.', 'is_unique' => 'Bu kod zaten kullanılıyor.'],
        'ad'  => ['required' => 'Tür adı zorunludur.'],
    ];

    /** Aktif türler (yeni değişiklik formu). */
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
}
