<?php

namespace App\Controllers;

use App\Models\AyarModel;
use App\Models\KullaniciModel;

class Auth extends BaseController
{
    /** "Beni hatırla" çerezi adı */
    private const HATIRLA_COOKIE = 'bt_hatirla';

    /** Oturuma yazılan kullanıcı alanları (giriş + otomatik giriş ortak) */
    private function oturumVerisi(array $user): array
    {
        return [
            'giris_yapildi' => true,
            'kullanici_id'  => (int) $user['id'],
            'ad_soyad'      => $user['ad_soyad'],
            'kullanici_adi' => $user['kullanici_adi'],
            'rol'           => $user['rol'],
            'musavir_id'    => $user['musavir_id'] ? (int) $user['musavir_id'] : null,
        ];
    }

    /** "Beni hatırla" çerezi için ayar (güvenli bayraklar, 30 gün vb.) */
    private function hatirlaCerezi(string $token): array
    {
        $gun = (int) (new AyarModel())->oku('hatirla_sure_gun', 30);

        return [
            'name'     => self::HATIRLA_COOKIE,
            'value'    => $token,
            'expire'   => $gun * 86400,
            'path'     => '/',
            'secure'   => service('request')->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

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

        // "Beni hatırla" çerezi varsa oturumu yeniden kur (kalıcı giriş)
        $cerez = get_cookie(self::HATIRLA_COOKIE);
        if ($cerez !== null && $cerez !== '' && ! $this->session->get('giris_yapildi')) {
            $user = $model->hatirlaTokeniDogrula($cerez);

            if ($user !== null) {
                $yeniToken = $model->hatirlaIleGiris($cerez, $this->oturumVerisi($user));

                $yanit = redirect()->to(site_url('panel'));
                if ($yeniToken !== null) {
                    $yanit->setCookie($this->hatirlaCerezi($yeniToken));
                }

                return $yanit;
            }

            // Geçersiz çerez → temizle
            service('response')->deleteCookie(self::HATIRLA_COOKIE);
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

        $this->session->set($this->oturumVerisi($user));

        $hedef = $this->session->get('yonlendir_url') ?: site_url('panel');
        $this->session->remove('yonlendir_url');

        $yanit = redirect()->to($hedef)->with('basari', 'Hoş geldiniz, ' . $user['ad_soyad'] . '.');

        // "Beni hatırla" işaretliyse kalıcı çerez oluştur
        if ((string) $this->request->getPost('beni_hatirla') === '1') {
            $gun = (int) (new AyarModel())->oku('hatirla_sure_gun', 30);

            if ($gun > 0) {
                $token = $model->hatirlaTokeniOlustur((int) $user['id'], $gun);
                $yanit->setCookie($this->hatirlaCerezi($token));
            }
        }

        return $yanit;
    }

    public function cikis()
    {
        // "Beni hatırla" çerezi varsa DB karşılığını sil
        $cerez = get_cookie(self::HATIRLA_COOKIE);
        if ($cerez !== null && $cerez !== '') {
            (new KullaniciModel())->hatirlaTokeniSil($cerez);
        }

        $this->session->destroy();

        $yanit = redirect()->to(site_url('giris'))->with('basari', 'Oturumunuz kapatıldı.');
        $yanit->deleteCookie(self::HATIRLA_COOKIE);

        return $yanit;
    }
}
