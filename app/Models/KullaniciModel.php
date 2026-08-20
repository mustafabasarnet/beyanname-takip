<?php

namespace App\Models;

use CodeIgniter\Model;

class KullaniciModel extends Model
{
    protected $table         = 'kullanicilar';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'musavir_id', 'ad_soyad', 'kullanici_adi', 'eposta', 'sifre',
        'rol', 'telefon', 'aktif', 'son_giris',
    ];

    /**
     * Varsayılan (EKLEME) kuralları.
     *
     * DİKKAT: Güncellemede bu kurallar kullanılmaz; çünkü Model::update()
     * doğrulamayı yalnızca gönderilen veri dizisiyle yapar ve dizide "id"
     * bulunmadığından "{id}" yer tutucusu boş kalır. Bu durumda kayıt
     * kendi kendisiyle çakışır ve "zaten kullanılıyor" hatası üretir.
     * Güncelleme için kurallariGuncelle() kullanılır.
     */
    protected $validationRules = [
        'ad_soyad'      => 'required|min_length[3]|max_length[150]',
        'kullanici_adi' => 'required|alpha_dash|min_length[3]|max_length[60]|is_unique[kullanicilar.kullanici_adi]',
        'eposta'        => 'required|valid_email|is_unique[kullanicilar.eposta]',
        'rol'           => 'required|in_list[admin,musavir,personel]',
    ];

    protected $validationMessages = [
        'kullanici_adi' => [
            'required'   => 'Kullanıcı adı zorunludur.',
            'is_unique'  => 'Bu kullanıcı adı zaten kullanılıyor.',
            'alpha_dash' => 'Kullanıcı adı harf, rakam, alt çizgi ve tire içerebilir.',
        ],
        'eposta' => [
            'required'    => 'E-posta zorunludur.',
            'valid_email' => 'Geçerli bir e-posta giriniz.',
            'is_unique'   => 'Bu e-posta zaten kayıtlı.',
        ],
    ];

    /**
     * GÜNCELLEME kuralları — benzersizlik kontrolünden düzenlenen kaydı hariç tutar.
     *
     * Kullanımı (controller içinde):
     *   $this->model->skipValidation(false);
     *   if (! $this->validateData($veri, $this->model->kurallariGuncelle($id), $this->model->kurallarMesajlari())) { ... }
     *
     * @param int $id Düzenlenen kullanıcının ID'si
     */
    public function kurallariGuncelle(int $id): array
    {
        $kurallar = $this->validationRules;

        $kurallar['kullanici_adi'] = 'required|alpha_dash|min_length[3]|max_length[60]'
            . '|is_unique[kullanicilar.kullanici_adi,id,' . $id . ']';

        $kurallar['eposta'] = 'required|valid_email'
            . '|is_unique[kullanicilar.eposta,id,' . $id . ']';

        return $kurallar;
    }

    /** Doğrulama mesajlarını dışarıya verir (controller'da validateData için) */
    public function kurallarMesajlari(): array
    {
        return $this->validationMessages;
    }


    // =================================================================
    //  MALİ MÜŞAVİR ERİŞİMİ (çoklu)
    //  Mali müşavir = portföy/kurum tanımı
    //  Kullanıcı    = sisteme giren kişi
    // =================================================================

    /**
     * Kullanıcının erişebildiği mali müşavir ID'leri.
     *
     * @return int[] Admin için boş dizi döner (tümüne erişir).
     */
    public function erisilebilirMusavirler(int $kullaniciId): array
    {
        $rows = $this->db->table('kullanici_musavirleri')
            ->select('musavir_id')
            ->where('kullanici_id', $kullaniciId)
            ->get()->getResultArray();

        $idler = array_map('intval', array_column($rows, 'musavir_id'));

        // Geriye dönük uyumluluk: köprü tablo boşsa birincil müşaviri kullan
        if ($idler === []) {
            $user = $this->find($kullaniciId);

            if ($user !== null && ! empty($user['musavir_id'])) {
                $idler[] = (int) $user['musavir_id'];
            }
        }

        return array_values(array_unique($idler));
    }

    /**
     * Kullanıcının müşavir erişimlerini topluca kaydeder.
     *
     * @param int[] $musavirIdler
     */
    public function musavirleriKaydet(int $kullaniciId, array $musavirIdler): void
    {
        $tbl = $this->db->table('kullanici_musavirleri');
        $tbl->where('kullanici_id', $kullaniciId)->delete();

        $musavirIdler = array_values(array_unique(array_filter(array_map('intval', $musavirIdler))));

        if ($musavirIdler === []) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $satir = [];

        foreach ($musavirIdler as $mid) {
            $satir[] = [
                'kullanici_id' => $kullaniciId,
                'musavir_id'   => $mid,
                'created_at'   => $now,
            ];
        }

        $tbl->insertBatch($satir);
    }

    /** Kullanıcı bu müşavire erişebiliyor mu? */
    public function musavireErisebilirMi(int $kullaniciId, int $musavirId, string $rol = ''): bool
    {
        if ($rol === 'admin') {
            return true;
        }

        return in_array($musavirId, $this->erisilebilirMusavirler($kullaniciId), true);
    }

    /** Liste ekranı için: kullanıcı + erişebildiği müşavir adları */
    public function listeMusavirIle(): array
    {
        $kullanicilar = $this->select('kullanicilar.*, musavirler.ad_soyad as musavir_adi')
            ->join('musavirler', 'musavirler.id = kullanicilar.musavir_id', 'left')
            ->orderBy('kullanicilar.ad_soyad', 'ASC')
            ->findAll();

        if ($kullanicilar === []) {
            return [];
        }

        // Tüm erişimleri tek sorguda çek
        $rows = $this->db->table('kullanici_musavirleri km')
            ->select('km.kullanici_id, m.ad_soyad, m.renk')
            ->join('musavirler m', 'm.id = km.musavir_id')
            ->whereIn('km.kullanici_id', array_column($kullanicilar, 'id'))
            ->orderBy('m.ad_soyad', 'ASC')
            ->get()->getResultArray();

        $harita = [];

        foreach ($rows as $r) {
            $harita[(int) $r['kullanici_id']][] = ['ad' => $r['ad_soyad'], 'renk' => $r['renk']];
        }

        foreach ($kullanicilar as &$k) {
            $k['erisim_musavirleri'] = $harita[(int) $k['id']] ?? [];
        }

        return $kullanicilar;
    }

    /** Belirli müşavirlere erişebilen kullanıcılar (sorumlu personel seçimi için) */
    public function musavirinKullanicilari(array $musavirIdler): array
    {
        if ($musavirIdler === []) {
            return [];
        }

        $rows = $this->db->table('kullanici_musavirleri km')
            ->select('k.id, k.ad_soyad, k.rol')
            ->join('kullanicilar k', 'k.id = km.kullanici_id')
            ->whereIn('km.musavir_id', array_map('intval', $musavirIdler))
            ->where('k.aktif', 1)
            ->groupBy('k.id')
            ->orderBy('k.ad_soyad', 'ASC')
            ->get()->getResultArray();

        return $rows;
    }

    /** Kullanıcı adı veya e-posta ile giriş doğrulama */
    public function girisDogrula(string $kimlik, string $sifre): ?array
    {
        $user = $this->groupStart()
                ->where('kullanici_adi', $kimlik)
                ->orWhere('eposta', $kimlik)
            ->groupEnd()
            ->where('aktif', 1)
            ->first();

        if ($user === null) {
            return null;
        }

        if (! password_verify($sifre, $user['sifre'])) {
            return null;
        }

        return $user;
    }

    public function sifreGuncelle(int $id, string $yeniSifre): bool
    {
        return $this->update($id, ['sifre' => password_hash($yeniSifre, PASSWORD_DEFAULT)]);
    }

}
