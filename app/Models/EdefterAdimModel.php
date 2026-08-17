<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * E-DEFTER TAKİP ADIMLARI
 *
 * Büro iş akışının basamakları. Varsayılan olarak altı adım gelir:
 *   Banka Temin → Banka İşleme → Çek İşleme → Mizan Kontrol → Hazır → Onay
 *
 * Adımlar sabit değildir; Tanımlar menüsünden eklenip çıkarılabilir ve
 * sıraları değiştirilebilir (örn. araya "Kasa Kontrolü" eklemek).
 */
class EdefterAdimModel extends Model
{
    protected $table         = 'edefter_adimlari';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['kod', 'ad', 'ikon', 'aciklama', 'sira', 'aktif'];

    protected $validationRules = [
        'kod' => 'required|max_length[40]|regex_match[/^[A-Z0-9_]+$/]',
        'ad'  => 'required|min_length[2]|max_length[100]',
    ];

    protected $validationMessages = [
        'kod' => [
            'required'    => 'Adım kodu zorunludur.',
            'regex_match' => 'Kod yalnızca BÜYÜK harf, rakam ve alt çizgi içerebilir (örn. KASA_KONTROL).',
        ],
        'ad' => ['required' => 'Adım adı zorunludur.'],
    ];

    /**
     * Son adım kodu. Bu adım işaretlenince kayıt "Onaylandı" sayılır.
     * Kod sabit tutulur ki kullanıcı adı değiştirse de mantık bozulmasın.
     */
    public const ONAY_KODU = 'ONAY';

    /** Bu adım işaretlenince kayıt "Hazır" sayılır */
    public const HAZIR_KODU = 'HAZIR';

    /** @return array<int,array> Sıralı aktif adımlar */
    public function aktifler(): array
    {
        return $this->where('aktif', 1)->orderBy('sira', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /** @return array<int,array> Pasifler dahil tümü */
    public function tumu(): array
    {
        return $this->orderBy('sira', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /** id => adım eşlemesi */
    public function haritasi(): array
    {
        $out = [];

        foreach ($this->aktifler() as $a) {
            $out[(int) $a['id']] = $a;
        }

        return $out;
    }

    /** Koda göre adım id'si (yoksa null) */
    public function idBul(string $kod): ?int
    {
        $r = $this->where('kod', $kod)->first();

        return $r === null ? null : (int) $r['id'];
    }

    /** Yeni adım için bir sonraki sıra numarası */
    public function sonrakiSira(): int
    {
        $r = $this->selectMax('sira')->first();

        return (int) ($r['sira'] ?? 0) + 10;
    }
}
