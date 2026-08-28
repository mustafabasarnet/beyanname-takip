<?php

namespace App\Controllers;

use App\Models\AyarModel;
use App\Models\HatirlatmaJetonModel;
use App\Models\KullaniciModel;

class Auth extends BaseController
{
    /**
     * "Beni hatırla" jetonundan oturum açmayı dener.
     *
     * AuthFilter, oturumu olmayan istekleri giriş sayfasına yollamadan
     * önce burayı çağırır. Statik metot olmasının nedeni filtrenin
     * controller örneği oluşturmadan kullanabilmesidir.
     *
     * @return bool Oturum açıldıysa true
     */
    public static function jetonlaGirisDene(): bool
    {
        $cerez = $_COOKIE[HatirlatmaJetonModel::CEREZ] ?? null;

        if ($cerez === null || $cerez === '') {
            return false;
        }

        try {
            $jetonModel = new HatirlatmaJetonModel();

            if (! $jetonModel->kullanilabilir()) {
                return false;
            }

            $kayit = $jetonModel->dogrula($cerez);

            if ($kayit === null) {
                // Geçersiz/çalıntı çerez: tarayıcıdan da temizle
                self::cerezSil();

                return false;
            }

            $kullanici = (new KullaniciModel())->find((int) $kayit['kullanici_id']);

            // Kullanıcı silinmiş ya da pasife alınmışsa jeton geçersizdir
            if ($kullanici === null || (int) ($kullanici['aktif'] ?? 1) !== 1) {
                $jetonModel->delete($kayit['id']);
                self::cerezSil();

                return false;
            }

            // Jeton tek kullanımlık: her girişte yenilenir
            $gun   = self::hatirlaGun();
            $yeni  = $jetonModel->yenile($kayit, $gun, self::tarayiciOzeti(), self::istemciIp());

            if ($yeni !== null) {
                self::cerezYaz($yeni, $gun);
            }

            (new KullaniciModel())->update($kullanici['id'], ['son_giris' => date('Y-m-d H:i:s')]);

            session()->set([
                'giris_yapildi' => true,
                'kullanici_id'  => (int) $kullanici['id'],
                'ad_soyad'      => $kullanici['ad_soyad'],
                'kullanici_adi' => $kullanici['kullanici_adi'],
                'rol'           => $kullanici['rol'],
                'musavir_id'    => $kullanici['musavir_id'] ? (int) $kullanici['musavir_id'] : null,
                'hatirlandi'    => true,
            ]);

            return true;
        } catch (\Throwable $e) {
            // Migration çalıştırılmamışsa ya da beklenmedik bir sorun varsa
            // program normal giriş akışıyla devam etmeli.
            log_message('error', 'Beni hatırla başarısız: ' . $e->getMessage());

            return false;
        }
    }

    /** Hatırlama süresi (gün) — Ayarlar'dan okunur */
    public static function hatirlaGun(): int
    {
        try {
            $gun = (int) (new AyarModel())->oku('hatirla_gun', 90);
        } catch (\Throwable $e) {
            $gun = 90;
        }

        return max(1, min(365, $gun ?: 90));
    }

    /** "Beni hatırla" kutusu gösterilsin mi? */
    public static function hatirlaAcikMi(): bool
    {
        try {
            return (int) (new AyarModel())->oku('hatirla_acik', 1) === 1;
        } catch (\Throwable $e) {
            return true;
        }
    }

    protected static function cerezYaz(string $deger, int $gun): void
    {
        setcookie(HatirlatmaJetonModel::CEREZ, $deger, [
            'expires'  => time() + ($gun * 86400),
            'path'     => '/',
            'httponly' => true,               // JS erişemez (XSS koruması)
            'samesite' => 'Lax',
            'secure'   => ! empty($_SERVER['HTTPS']),
        ]);
    }

    protected static function cerezSil(): void
    {
        setcookie(HatirlatmaJetonModel::CEREZ, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[HatirlatmaJetonModel::CEREZ]);
    }

    protected static function tarayiciOzeti(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return $ua === null ? null : mb_substr($ua, 0, 255);
    }

    protected static function istemciIp(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function giris()
    {
        // Oturum yok ama geçerli bir "beni hatırla" jetonu varsa
        // kullanıcıyı giriş formuyla oyalamadan içeri al.
        if (! $this->session->get('giris_yapildi') && self::jetonlaGirisDene()) {
            $hedef = $this->session->get('yonlendir_url') ?: site_url('panel');
            $this->session->remove('yonlendir_url');

            return redirect()->to($hedef);
        }

        $model = new KullaniciModel();

        // Hiç kullanıcı yoksa kuruluma yönlendir
        try {
            if ($model->countAllResults() === 0) {
                return redirect()->to(site_url('kurulum'));
            }
        } catch (\Throwable $e) {
            return view('auth/db_hata', ['mesaj' => $e->getMessage()]);
        }

        return view('auth/giris', [
            'hatirlaAcik' => self::hatirlaAcikMi(),
            'hatirlaGun'  => self::hatirlaGun(),
        ]);
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

        // ---- Beni hatırla ----
        if ((string) $this->request->getPost('hatirla') === '1' && self::hatirlaAcikMi()) {
            try {
                $gun   = self::hatirlaGun();
                $model = new HatirlatmaJetonModel();

                // Bakım: süresi dolmuş jetonlar biriktirmesin
                $model->suresiDolanlariSil();

                $jeton = $model->uret(
                    (int) $user['id'],
                    $gun,
                    self::tarayiciOzeti(),
                    self::istemciIp()
                );

                if ($jeton !== null) {
                    self::cerezYaz($jeton, $gun);
                }
            } catch (\Throwable $e) {
                // Jeton üretilemezse giriş yine de başarılıdır
                log_message('error', 'Hatırlama jetonu üretilemedi: ' . $e->getMessage());
            }
        }

        $hedef = $this->session->get('yonlendir_url') ?: site_url('panel');
        $this->session->remove('yonlendir_url');

        return redirect()->to($hedef)->with('basari', 'Hoş geldiniz, ' . $user['ad_soyad'] . '.');
    }

    public function cikis()
    {
        /*
         * Çıkışta jeton da silinir; yoksa "Çıkış Yap" dedikten sonra
         * bir sonraki istekte hatırlama devreye girip kullanıcıyı
         * tekrar içeri alırdı.
         */
        try {
            (new HatirlatmaJetonModel())->cerezSil($_COOKIE[HatirlatmaJetonModel::CEREZ] ?? null);
        } catch (\Throwable $e) {
            log_message('error', 'Hatırlama jetonu silinemedi: ' . $e->getMessage());
        }

        self::cerezSil();
        $this->session->destroy();

        return redirect()->to(site_url('giris'))->with('basari', 'Oturumunuz kapatıldı.');
    }
}
