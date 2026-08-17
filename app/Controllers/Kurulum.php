<?php

namespace App\Controllers;

use App\Models\KullaniciModel;
use App\Models\MusavirModel;

/**
 * İlk kurulum: yönetici hesabı + ilk mali müşavir kaydı.
 * Sistemde kullanıcı varsa bu ekran kendini kilitler.
 */
class Kurulum extends BaseController
{
    public function index()
    {
        if ($this->kurulumYapildiMi()) {
            return redirect()->to(site_url('giris'))
                ->with('hata', 'Kurulum daha önce tamamlanmış.');
        }

        return view('auth/kurulum');
    }

    public function kaydet()
    {
        if ($this->kurulumYapildiMi()) {
            return redirect()->to(site_url('giris'));
        }

        $kurallar = [
            'ad_soyad'      => 'required|min_length[3]',
            'kullanici_adi' => 'required|alpha_dash|min_length[3]',
            'eposta'        => 'required|valid_email',
            'sifre'         => 'required|min_length[6]',
            'sifre_tekrar'  => 'required|matches[sifre]',
        ];

        if (! $this->validate($kurallar)) {
            return redirect()->back()->withInput()
                ->with('hatalar', $this->validator->getErrors());
        }

        $musavirModel   = new MusavirModel();
        $kullaniciModel = new KullaniciModel();

        $musavirId = $musavirModel->insert([
            'unvan'    => $this->request->getPost('unvan') ?: 'SMMM',
            'ad_soyad' => $this->request->getPost('ad_soyad'),
            'buro_adi' => $this->request->getPost('buro_adi'),
            'eposta'   => $this->request->getPost('eposta'),
            'telefon'  => $this->request->getPost('telefon'),
            'renk'     => '#2563eb',
            'aktif'    => 1,
        ], true);

        $kullaniciModel->insert([
            'musavir_id'    => $musavirId,
            'ad_soyad'      => $this->request->getPost('ad_soyad'),
            'kullanici_adi' => $this->request->getPost('kullanici_adi'),
            'eposta'        => $this->request->getPost('eposta'),
            'sifre'         => password_hash((string) $this->request->getPost('sifre'), PASSWORD_DEFAULT),
            'rol'           => 'admin',
            'aktif'         => 1,
        ]);

        // Erişim kaydını da oluştur (yönetici zaten tümüne erişir; tutarlılık için)
        $kullaniciModel->musavirleriKaydet((int) $kullaniciModel->getInsertID(), [(int) $musavirId]);

        return redirect()->to(site_url('giris'))
            ->with('basari', 'Kurulum tamamlandı. Artık giriş yapabilirsiniz.');
    }

    protected function kurulumYapildiMi(): bool
    {
        try {
            return (new KullaniciModel())->countAllResults() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
