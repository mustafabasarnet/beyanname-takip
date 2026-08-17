<?php

namespace App\Models;

use CodeIgniter\Model;

class BeyannameTuruModel extends Model
{
    protected $table         = 'beyanname_turleri';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'kod', 'ad', 'kisa_ad', 'periyot', 'son_gun_offset_ay', 'son_gun_tipi',
        'son_gun', 'atlanan_donemler', 'celisen_kodlar', 'mukellef_tipi',
        'renk', 'aciklama', 'sira', 'aktif',
    ];

    /**
     * Varsayılan (EKLEME) kuralları.
     * Güncelleme için kurallariGuncelle() kullanılır — bkz. KullaniciModel açıklaması.
     */
    protected $validationRules = [
        'kod'     => 'required|max_length[30]|is_unique[beyanname_turleri.kod]',
        'ad'      => 'required|max_length[150]',
        'kisa_ad' => 'required|max_length[40]',
        'periyot' => 'required|in_list[AYLIK,UC_AYLIK,ALTI_AYLIK,YILLIK]',
    ];

    protected $validationMessages = [
        'kod' => [
            'required'  => 'Beyanname kodu zorunludur.',
            'is_unique' => 'Bu kod başka bir beyanname türünde kullanılıyor.',
        ],
        'ad'      => ['required' => 'Beyanname adı zorunludur.'],
        'kisa_ad' => ['required' => 'Kısa ad zorunludur.'],
    ];

    /** Güncellemede benzersizlik kontrolünden düzenlenen kaydı hariç tutar. */
    public function kurallariGuncelle(int $id): array
    {
        $kurallar        = $this->validationRules;
        $kurallar['kod'] = 'required|max_length[30]|is_unique[beyanname_turleri.kod,id,' . $id . ']';

        return $kurallar;
    }

    public function kurallarMesajlari(): array
    {
        return $this->validationMessages;
    }

    public function aktifler(): array
    {
        return $this->where('aktif', 1)->orderBy('sira', 'ASC')->findAll();
    }

    /** kod => satır şeklinde harita */
    public function kodHaritasi(): array
    {
        return array_column($this->findAll(), null, 'kod');
    }

    /**
     * Çakışma haritası: hangi tür seçilince hangileri pasifleşecek.
     * Örn: YILLIK_GV seçilirse KURUMLAR ve KURUM_GECICI pasif olur.
     *
     * @return array<int,int[]> [tur_id => [pasif_yapilacak_tur_id, ...]]
     */
    public function celiskiHaritasi(): array
    {
        $turler = $this->aktifler();
        $kodId  = array_column($turler, 'id', 'kod');
        $harita = [];

        foreach ($turler as $t) {
            $harita[(int) $t['id']] = [];

            if (empty($t['celisen_kodlar'])) {
                continue;
            }

            foreach (explode(',', (string) $t['celisen_kodlar']) as $kod) {
                $kod = trim($kod);

                if ($kod !== '' && isset($kodId[$kod])) {
                    $harita[(int) $t['id']][] = (int) $kodId[$kod];
                }
            }
        }

        return $harita;
    }
}
