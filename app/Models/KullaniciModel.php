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
        // Görünüm tercihleri (tema / palet / yan menü) — migration_kullanici_tema.sql
        'tema', 'palet', 'yan_menu', 'hizli_gecis',
    ];

    // =================================================================
    //  GÖRÜNÜM TERCİHLERİ (tema)
    // =================================================================

    /** Tema modları (kullanıcı seçebilir). */
    public const TEMA_MODLARI = [
        'sistem'   => 'Sistem',
        'acik'     => 'Açık',
        'karanlik' => 'Karanlık',
    ];

    /** Renk şablonları — anahtar => [etiket, önizleme rengi]. */
    public const PALETLER = [
        'mavi'    => ['Mavi', '#2563eb'],
        'turkuaz' => ['Turkuaz', '#0891b2'],
        'yesil'   => ['Yeşil', '#059669'],
        'mor'     => ['Mor', '#7c3aed'],
        'turuncu' => ['Turuncu', '#ea580c'],
        'bordo'   => ['Bordo', '#be123c'],
        'grafit'  => ['Grafit', '#475569'],
    ];

    /** Yan menü zemin seçenekleri. */
    public const YAN_MENULER = [
        'koyu' => 'Koyu menü',
        'acik' => 'Açık menü',
    ];

    /** Varsayılan görünüm: sistem teması + mavi palet + koyu menü. */
    public const TEMA_VARSAYILAN = ['tema' => 'sistem', 'palet' => 'mavi', 'yan_menu' => 'koyu'];

    /**
     * Kullanıcının görünüm tercihleri.
     *
     * Migration çalıştırılmamışsa (kolonlar yoksa) ya da kayıt bulunamazsa
     * güvenli varsayılan döner — program çökmez, görünüm bugünkü gibi kalır.
     *
     * @return array{tema:string, palet:string, yan_menu:string}
     */
    public function temaTercihleri(int $kullaniciId): array
    {
        $v = self::TEMA_VARSAYILAN;

        if ($kullaniciId <= 0) {
            return $v;
        }

        try {
            $db = $this->db;

            if (! $db->fieldExists('tema', $this->table)) {
                return $v; // migration henüz koşulmamış
            }

            $satir = $db->table($this->table)
                ->select('tema, palet, yan_menu')
                ->where('id', $kullaniciId)
                ->get()->getRowArray();

            if ($satir === null) {
                return $v;
            }

            return [
                'tema'     => $this->gecerli((string) ($satir['tema'] ?? ''), array_keys(self::TEMA_MODLARI), $v['tema']),
                'palet'    => $this->gecerli((string) ($satir['palet'] ?? ''), array_keys(self::PALETLER), $v['palet']),
                'yan_menu' => $this->gecerli((string) ($satir['yan_menu'] ?? ''), array_keys(self::YAN_MENULER), $v['yan_menu']),
            ];
        } catch (\Throwable $e) {
            return $v;
        }
    }

    /** Değer izinli listede mi? Değilse varsayılana düşer (whitelist). */
    protected function gecerli(string $deger, array $izinli, string $varsayilan): string
    {
        return in_array($deger, $izinli, true) ? $deger : $varsayilan;
    }

    /**
     * Görünüm tercihini yazar (whitelist doğrulamalı).
     *
     * @param array{tema?:string, palet?:string, yan_menu?:string} $tercih
     *
     * @return array{durum:bool, mesaj:string, tercih:array}
     */
    public function temaKaydet(int $kullaniciId, array $tercih): array
    {
        $mevcut = $this->temaTercihleri($kullaniciId);
        $veri   = [];

        if (isset($tercih['tema'])) {
            if (! array_key_exists((string) $tercih['tema'], self::TEMA_MODLARI)) {
                return ['durum' => false, 'mesaj' => 'Geçersiz tema modu.', 'tercih' => $mevcut];
            }
            $veri['tema'] = (string) $tercih['tema'];
        }

        if (isset($tercih['palet'])) {
            if (! array_key_exists((string) $tercih['palet'], self::PALETLER)) {
                return ['durum' => false, 'mesaj' => 'Geçersiz renk şablonu.', 'tercih' => $mevcut];
            }
            $veri['palet'] = (string) $tercih['palet'];
        }

        if (isset($tercih['yan_menu'])) {
            if (! array_key_exists((string) $tercih['yan_menu'], self::YAN_MENULER)) {
                return ['durum' => false, 'mesaj' => 'Geçersiz yan menü seçimi.', 'tercih' => $mevcut];
            }
            $veri['yan_menu'] = (string) $tercih['yan_menu'];
        }

        if ($veri === []) {
            return ['durum' => false, 'mesaj' => 'Kaydedilecek görünüm ayarı yok.', 'tercih' => $mevcut];
        }

        try {
            $db = $this->db;

            if (! $db->fieldExists('tema', $this->table)) {
                return ['durum' => false, 'mesaj' => 'Görünüm ayarları için veritabanı güncellemesi gerekli.', 'tercih' => $mevcut];
            }

            /*
             * Query Builder ile yazılır: Model::update() kullanıcı doğrulama
             * kurallarını (ad_soyad, eposta…) çalıştırır; bu dar güncelleme
             * yalnız görünüm alanlarını değiştirdiği için doğrudan yazılır.
             */
            $veri['updated_at'] = date('Y-m-d H:i:s');

            $db->table($this->table)->where('id', $kullaniciId)->update($veri);

            return ['durum' => true, 'mesaj' => 'Görünüm kaydedildi.',
                    'tercih' => array_merge($mevcut, array_intersect_key($veri, self::TEMA_VARSAYILAN))];
        } catch (\Throwable $e) {
            return ['durum' => false, 'mesaj' => 'Görünüm kaydedilemedi.', 'tercih' => $mevcut];
        }
    }

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
