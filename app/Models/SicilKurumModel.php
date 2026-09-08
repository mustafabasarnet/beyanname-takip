<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SİCİL — BİLDİRİM KURUMLARI (tanım tablosu)
 *
 * Kurumlar menüden yönetilir; kodda sabit liste yoktur.
 */
class SicilKurumModel extends Model
{
    protected $table         = 'kurumlar';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['kod', 'ad', 'kisa_ad', 'aciklama', 'aktif'];

    protected $validationRules = [
        'kod' => 'required|alpha_dash|max_length[50]|is_unique[kurumlar.kod,id,{id}]',
        'ad'  => 'required|max_length[150]',
    ];

    protected $validationMessages = [
        'kod' => ['required' => 'Kurum kodu zorunludur.', 'is_unique' => 'Bu kod zaten kullanılıyor.'],
        'ad'  => ['required' => 'Kurum adı zorunludur.'],
    ];

    /** Açılır liste için [id => ad]. */
    public function secenekler(bool $sadeceAktif = true): array
    {
        $b = $this->select('id, ad');

        if ($sadeceAktif) {
            $b->where('aktif', 1);
        }

        $out = [];

        foreach ($b->orderBy('ad', 'ASC')->findAll() as $r) {
            $out[(int) $r['id']] = $r['ad'];
        }

        return $out;
    }

    /** Kısa ad içeren liste (görev tablosunda rozet için). */
    public function idHaritasi(): array
    {
        $out = [];

        foreach ($this->select('id, ad, kisa_ad')->findAll() as $r) {
            $out[(int) $r['id']] = $r;
        }

        return $out;
    }
}
