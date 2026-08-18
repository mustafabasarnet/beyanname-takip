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

    // =================================================================
    //  "BENİ HATIRLA" — kalıcı oturum çerezi
    //
    //  Güvenlik ilkeleri:
    //   • Çerezde ham rastgele token saklanır; veritabanına yalnızca
    //     SHA-256 karşılığı yazılır (DB sızdırılsa çerez işe yaramaz).
    //   • Her otomatik girişte token YENİLENİR (eski çerez geçersizleşir).
    //   • Çıkışta token ve çerez silinir.
    // =================================================================

    /**
     * Yeni bir "beni hatırla" tokeni üretir, hash'ini DB'ye kaydeder
     * ve ham tokeni döndürür (bu değer çereze yazılır).
     *
     * @return string ham token
     */
    public function hatirlaTokeniOlustur(int $kullaniciId, int $gun): string
    {
        $simdi = date('Y-m-d H:i:s');

        // Kullanıcının süresi dolmuş eski kayıtlarını temizle (birikme olmasın)
        $this->db->table('hatirlanan_oturumlar')
            ->where('kullanici_id', $kullaniciId)
            ->where('son_kullanma <', $simdi)
            ->delete();

        $token = bin2hex(random_bytes(32));

        $this->db->table('hatirlanan_oturumlar')->insert([
            'kullanici_id' => $kullaniciId,
            'token_hash'   => hash('sha256', $token),
            'ip'           => service('request')->getIPAddress() ?: null,
            'user_agent'   => service('request')->getUserAgent()->getAgentString() ?: null,
            'son_kullanma' => date('Y-m-d H:i:s', time() + $gun * 86400),
            'olusturulma'  => $simdi,
            'updated_at'   => $simdi,
        ]);

        return $token;
    }

    /**
     * Çerezin doğrulama karşılığını arar. Token geçerliyse ve kullanıcı
     * aktifse kullanıcı kaydını döndürür; aksi halde null.
     *
     * @return array|null ['id'=>.., 'ad_soyad'=>.., ...]
     */
    public function hatirlaTokeniDogrula(string $token): ?array
    {
        $row = $this->db->table('hatirlanan_oturumlar')
            ->where('token_hash', hash('sha256', $token))
            ->where('son_kullanma >', date('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        if ($row === null) {
            // Süresi dolmuş veya geçersiz çerez: kalıcı kaydı temizle
            $this->db->table('hatirlanan_oturumlar')
                ->where('token_hash', hash('sha256', $token))
                ->delete();

            return null;
        }

        $user = $this->find((int) $row['kullanici_id']);

        if ($user === null || (int) $user['aktif'] !== 1) {
            // Kullanıcı silinmiş/pasifse kalıcı kaydı temizle
            $this->db->table('hatirlanan_oturumlar')->where('id', (int) $row['id'])->delete();

            return null;
        }

        return $user;
    }

    /**
     * Eski tokeni geçersiz kılıp yerine yenisini üretir (rotasyon).
     * Eski token bulunamazsa null döner.
     *
     * @return string|null yeni ham token
     */
    public function hatirlaTokeniYenile(string $eskiToken, int $gun): ?string
    {
        $row = $this->db->table('hatirlanan_oturumlar')
            ->where('token_hash', hash('sha256', $eskiToken))
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        $this->db->table('hatirlanan_oturumlar')->where('id', (int) $row['id'])->delete();

        return $this->hatirlaTokeniOlustur((int) $row['kullanici_id'], $gun);
    }

    /**
     * Çerezi ve DB karşılığını siler (çıkışta çağrılır).
     */
    public function hatirlaTokeniSil(string $token): void
    {
        $this->db->table('hatirlanan_oturumlar')
            ->where('token_hash', hash('sha256', $token))
            ->delete();
    }

    /**
     * Çerezi doğrulayıp oturumu yeniden kuran yardımcı.
     *
     * @param array $oturumVerisi girisYap ile aynı oturum alanları
     *
     * @return string|null başarılıysa YENİ token (çereze yazılır), değilse null
     */
    public function hatirlaIleGiris(string $token, array $oturumVerisi): ?string
    {
        $user = $this->hatirlaTokeniDogrula($token);

        if ($user === null) {
            return null;
        }

        session()->set($oturumVerisi);
        $this->update((int) $user['id'], ['son_giris' => date('Y-m-d H:i:s')]);

        $gun = (int) (new AyarModel())->oku('hatirla_sure_gun', 30);

        return $this->hatirlaTokeniYenile($token, $gun);
    }

}
