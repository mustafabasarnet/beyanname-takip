<?php

namespace App\Controllers;

use App\Models\KullaniciModel;
use App\Models\MusavirModel;

class Kullanicilar extends BaseController
{
    protected KullaniciModel $model;

    public function __construct()
    {
        $this->model = new KullaniciModel();
    }

    public function index()
    {
        return $this->goster('kullanicilar/index', [
            'kullanicilar' => $this->model->listeMusavirIle(),
        ], 'Kullanıcılar');
    }

    public function yeni()
    {
        return $this->goster('kullanicilar/form', [
            'kullanici'      => null,
            'musavirler'     => (new MusavirModel())->seceneklar(),
            'secilenErisim'  => [],
        ], 'Yeni Kullanıcı');
    }

    public function kaydet()
    {
        $sifre = (string) $this->request->getPost('sifre');

        if (strlen($sifre) < 6) {
            return redirect()->back()->withInput()->with('hata', 'Şifre en az 6 karakter olmalıdır.');
        }

        $veri          = $this->formVerisi();
        $veri['sifre'] = password_hash($sifre, PASSWORD_DEFAULT);

        // Ekleme: model kuralları (is_unique tüm tabloya bakar) geçerli
        if (! $this->model->skipValidation(false)->insert($veri)) {
            return redirect()->back()->withInput()->with('hatalar', $this->model->errors());
        }

        $yeniId = (int) $this->model->getInsertID();
        $this->model->musavirleriKaydet($yeniId, $this->erisimListesi($veri));

        return redirect()->to(site_url('kullanicilar'))->with('basari', 'Kullanıcı oluşturuldu.');
    }

    public function duzenle(int $id)
    {
        $kullanici = $this->model->find($id);

        if ($kullanici === null) {
            return redirect()->to(site_url('kullanicilar'))->with('hata', 'Kullanıcı bulunamadı.');
        }

        return $this->goster('kullanicilar/form', [
            'kullanici'     => $kullanici,
            'musavirler'    => (new MusavirModel())->seceneklar(),
            'secilenErisim' => $this->model->erisilebilirMusavirler($id),
        ], 'Kullanıcı Düzenle');
    }

    public function guncelle(int $id)
    {
        $mevcut = $this->model->find($id);

        if ($mevcut === null) {
            return redirect()->to(site_url('kullanicilar'))->with('hata', 'Kullanıcı bulunamadı.');
        }

        $veri  = $this->formVerisi();
        $sifre = (string) $this->request->getPost('sifre');

        $sifreDegisti = false;

        if ($sifre !== '') {
            if (strlen($sifre) < 6) {
                return redirect()->back()->withInput()->with('hata', 'Şifre en az 6 karakter olmalıdır.');
            }
            $veri['sifre'] = password_hash($sifre, PASSWORD_DEFAULT);
            $sifreDegisti  = true;
        }

        // Benzersizlik kontrolünde DÜZENLENEN KAYDI hariç tut.
        // (Model::update() veri dizisinde "id" olmadığı için {id} yer tutucusunu
        //  dolduramaz; bu yüzden doğrulamayı burada açıkça yapıyoruz.)
        if (! $this->validate($this->model->kurallariGuncelle($id), $this->model->kurallarMesajlari())) {
            return redirect()->back()->withInput()->with('hatalar', $this->validator->getErrors());
        }

        // Doğrulama yapıldı; modelde tekrar çalışmasın.
        if (! $this->model->skipValidation(true)->update($id, $veri)) {
            return redirect()->back()->withInput()->with('hatalar', $this->model->errors());
        }

        $this->model->musavirleriKaydet($id, $this->erisimListesi($veri));

        // Şifre değiştiyse o kullanıcının kalıcı oturumları iptal edilir
        if ($sifreDegisti) {
            $this->hatirlamaJetonlariniSil($id);
        }

        // Kendi hesabını düzenlediyse oturumdaki bilgileri tazele
        if ($id === (int) $this->aktifKullanici['id']) {
            $this->session->set([
                'ad_soyad'      => $veri['ad_soyad'],
                'kullanici_adi' => $veri['kullanici_adi'],
                'rol'           => $veri['rol'],
                'musavir_id'    => $veri['musavir_id'] ? (int) $veri['musavir_id'] : null,
            ]);
        }

        return redirect()->to(site_url('kullanicilar'))->with('basari', 'Kullanıcı güncellendi.');
    }

    public function sil(int $id)
    {
        if ($id === (int) $this->aktifKullanici['id']) {
            return redirect()->to(site_url('kullanicilar'))->with('hata', 'Kendi hesabınızı silemezsiniz.');
        }

        $this->model->delete($id);

        return redirect()->to(site_url('kullanicilar'))->with('basari', 'Kullanıcı silindi.');
    }

    // ---------------- Profil ----------------
    public function profil()
    {
        $kid = (int) $this->aktifKullanici['id'];

        return $this->goster('kullanicilar/profil', [
            'kullanici' => $this->model->find($kid),
            // Görünüm tercihleri + seçenek listeleri (🎨 Görünüm kartı)
            'tercih'    => $this->model->temaTercihleri($kid),
            'temaModlari' => KullaniciModel::TEMA_MODLARI,
            'paletler'    => KullaniciModel::PALETLER,
            'yanMenuler'  => KullaniciModel::YAN_MENULER,
            'temaHazir'   => $this->model->db->fieldExists('tema', 'kullanicilar'),
        ], 'Profilim');
    }

    /**
     * GÖRÜNÜM (TEMA) — üst bardaki hızlı geçiş düğmesi (AJAX).
     *
     * Yalnız açık ↔ karanlık modu çevirir; palet ve yan menü seçimi
     * Profil → Görünüm kartından yapılır. Tercih kullanıcı bazlıdır.
     */
    public function temaHizliGecis()
    {
        $kid  = (int) $this->aktifKullanici['id'];
        $mod  = (string) $this->request->getPost('tema');

        $sonuc = $this->model->temaKaydet($kid, ['tema' => $mod]);

        if (! $sonuc['durum']) {
            return $this->jsonHata($sonuc['mesaj']);
        }

        $this->temaOturumGuncelle($sonuc['tercih']);

        $etiket = KullaniciModel::TEMA_MODLARI[$sonuc['tercih']['tema']] ?? '';

        return $this->jsonBasarili('Tema güncellendi (' . $etiket . ').', [
            'tema'   => $sonuc['tercih']['tema'],
            'etiket' => $etiket,
        ]);
    }

    public function profilKaydet()
    {
        $id   = (int) $this->aktifKullanici['id'];
        $user = $this->model->find($id);

        if ($user === null) {
            return redirect()->to(site_url('panel'));
        }

        $veri = [
            'ad_soyad' => $this->request->getPost('ad_soyad'),
            'telefon'  => $this->request->getPost('telefon'),
        ];

        $yeni = (string) $this->request->getPost('yeni_sifre');

        if ($yeni !== '') {
            $mevcut = (string) $this->request->getPost('mevcut_sifre');

            if (! password_verify($mevcut, $user['sifre'])) {
                return redirect()->back()->with('hata', 'Mevcut şifreniz hatalı.');
            }

            if (strlen($yeni) < 6) {
                return redirect()->back()->with('hata', 'Yeni şifre en az 6 karakter olmalıdır.');
            }

            if ($yeni !== (string) $this->request->getPost('yeni_sifre_tekrar')) {
                return redirect()->back()->with('hata', 'Yeni şifreler eşleşmiyor.');
            }

            $veri['sifre'] = password_hash($yeni, PASSWORD_DEFAULT);
        }

        $this->model->update($id, $veri);
        $this->session->set('ad_soyad', $veri['ad_soyad']);

        /*
         * GÖRÜNÜM (TEMA) TERCİHLERİ
         *
         * Aynı formda gönderilen tema/palet/yan menü seçimleri ayrı ve
         * whitelist doğrulamalı yazılır (bkz. KullaniciModel::temaKaydet).
         * Geçersiz değer gelirse diğer profil alanları yine kaydedilir;
         * kullanıcı yalnız görünüm için uyarı alır.
         */
        $gorunumHata = null;

        if ($this->request->getPost('tema') !== null) {
            $tercih = array_filter([
                'tema'     => (string) $this->request->getPost('tema'),
                'palet'    => (string) $this->request->getPost('palet'),
                'yan_menu' => (string) $this->request->getPost('yan_menu'),
            ], static fn ($v) => $v !== '');

            if ($tercih !== []) {
                $g = $this->model->temaKaydet($id, $tercih);

                if (! $g['durum']) {
                    $gorunumHata = $g['mesaj'];
                } else {
                    $this->temaOturumGuncelle($g['tercih']);
                }
            }
        }

        /*
         * Şifre değiştiyse "beni hatırla" jetonları iptal edilir.
         * Aksi halde çalınmış bir çerez, şifre değiştirilse bile
         * geçerli kalmaya devam ederdi.
         */
        if (isset($veri['sifre'])) {
            $this->hatirlamaJetonlariniSil((int) $id);
        }

        if ($gorunumHata !== null) {
            return redirect()->to(site_url('profil'))->with('hata', $gorunumHata);
        }

        return redirect()->to(site_url('profil'))->with('basari', 'Profiliniz güncellendi.');
    }

    /**
     * Oturumdaki görünüm tercihini tazeler.
     *
     * BaseController her istekte ek sorgu yapmasın diye tercih oturumda
     * tutulur; buradan güncel değer yazılır.
     */
    protected function temaOturumGuncelle(array $tercih): void
    {
        $this->session->set('tema_tercih', [
            'tema'     => (string) ($tercih['tema'] ?? 'sistem'),
            'palet'    => (string) ($tercih['palet'] ?? 'mavi'),
            'yan_menu' => (string) ($tercih['yan_menu'] ?? 'koyu'),
        ]);
    }

    /**
     * Kullanıcının tüm "beni hatırla" jetonlarını siler.
     *
     * Şifre değiştiğinde çağrılır. Tablo yoksa (migration çalıştırılmamış)
     * sessizce geçer; program çökmez.
     */
    protected function hatirlamaJetonlariniSil(int $kullaniciId): void
    {
        try {
            (new \App\Models\HatirlatmaJetonModel())->kullaniciyiTemizle($kullaniciId);
        } catch (\Throwable $e) {
            log_message('error', 'Hatırlama jetonları silinemedi: ' . $e->getMessage());
        }
    }

    /**
     * Kullanıcının erişebileceği müşavir ID listesini derler.
     * Yönetici tüm müşavirlere zaten erişir; yine de seçim saklanır.
     *
     * @return int[]
     */
    protected function erisimListesi(array $veri): array
    {
        $secilen = $this->request->getPost('erisim_musavirleri') ?? [];
        $secilen = array_map('intval', (array) $secilen);

        // Birincil müşavir seçilmişse listeye dahil et
        if (! empty($veri['musavir_id'])) {
            $secilen[] = (int) $veri['musavir_id'];
        }

        return array_values(array_unique(array_filter($secilen)));
    }

    protected function formVerisi(): array
    {
        return [
            'musavir_id'    => $this->request->getPost('musavir_id') ?: null,
            'ad_soyad'      => trim((string) $this->request->getPost('ad_soyad')),
            'kullanici_adi' => trim((string) $this->request->getPost('kullanici_adi')),
            'eposta'        => trim((string) $this->request->getPost('eposta')),
            'rol'           => $this->request->getPost('rol'),
            'telefon'       => $this->request->getPost('telefon'),
            'aktif'         => (int) ($this->request->getPost('aktif') ?? 1),
        ];
    }
}
