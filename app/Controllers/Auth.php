<?php

namespace App\Controllers;

use App\Models\KullaniciModel;

class Auth extends BaseController
{
    public function giris()
    {
        $model = new KullaniciModel();

        // Hiç kullanıcı yoksa kuruluma yönlendir
        try {
            if ($model->countAllResults() === 0) {
                return redirect()->to(site_url('kurulum'));
            }
        } catch (\Throwable $e) {
            return view('auth/db_hata', ['mesaj' => $e->getMessage()]);
        }

        return view('auth/giris');
    }

    public function girisYap()
    {
        $kurallar = [
            'kimlik' => 'required',
            'sifre'  => 'required',
        ];

        if (! $this->validate($kurallar)) {
            return redirect()->back()->withInput()
                ->with('hata', 'Kullanıcı adı ve şifre zorunludur.');
        }

        $model = new KullaniciModel();
        $user  = $model->girisDogrula(
            trim((string) $this->request->getPost('kimlik')),
            (string) $this->request->getPost('sifre')
        );

        if ($user === null) {
            return redirect()->back()->withInput()
                ->with('hata', 'Kullanıcı adı veya şifre hatalı.');
        }

        $model->update($user['id'], ['son_giris' => date('Y-m-d H:i:s')]);

        $this->session->set([
            'giris_yapildi' => true,
            'kullanici_id'  => (int) $user['id'],
            'ad_soyad'      => $user['ad_soyad'],
            'kullanici_adi' => $user['kullanici_adi'],
            'rol'           => $user['rol'],
            'musavir_id'    => $user['musavir_id'] ? (int) $user['musavir_id'] : null,
        ]);

        $hedef = $this->session->get('yonlendir_url') ?: site_url('panel');
        $this->session->remove('yonlendir_url');

        return redirect()->to($hedef)->with('basari', 'Hoş geldiniz, ' . $user['ad_soyad'] . '.');
    }

    public function cikis()
    {
        $this->session->destroy();

        return redirect()->to(site_url('giris'))->with('basari', 'Oturumunuz kapatıldı.');
    }
}
