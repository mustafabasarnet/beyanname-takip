<?php

namespace App\Filters;

use App\Models\AyarModel;
use App\Models\KullaniciModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /** "Beni hatırla" çerezi adı (Auth controller ile aynı) */
    private const HATIRLA_COOKIE = 'bt_hatirla';

    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // Oturum yoksa ama "beni hatırla" çerezi varsa oturumu yeniden kur.
        // Böylece tarayıcı kapatılıp oturum süresi dolduğunda bile kullanıcı
        // korumalı bir sayfaya girdiğinde otomatik olarak giriş yapılmış olur.
        if (! $session->get('giris_yapildi')) {
            $cerez = get_cookie(self::HATIRLA_COOKIE);

            if ($cerez !== null && $cerez !== '') {
                $model = new KullaniciModel();
                $user  = $model->hatirlaTokeniDogrula($cerez);

                if ($user !== null) {
                    $session->set([
                        'giris_yapildi' => true,
                        'kullanici_id'  => (int) $user['id'],
                        'ad_soyad'      => $user['ad_soyad'],
                        'kullanici_adi' => $user['kullanici_adi'],
                        'rol'           => $user['rol'],
                        'musavir_id'    => $user['musavir_id'] ? (int) $user['musavir_id'] : null,
                    ]);

                    $model->update((int) $user['id'], ['son_giris' => date('Y-m-d H:i:s')]);

                    // Token rotasyonu: eski çerez geçersiz, yenisini üret
                    $gun      = (int) (new AyarModel())->oku('hatirla_sure_gun', 30);
                    $yeniToken = $model->hatirlaTokeniYenile($cerez, $gun);

                    if ($yeniToken !== null) {
                        service('response')->setCookie([
                            'name'     => self::HATIRLA_COOKIE,
                            'value'    => $yeniToken,
                            'expire'   => $gun * 86400,
                            'path'     => '/',
                            'secure'   => $request->isSecure(),
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }
                } else {
                    // Geçersiz / süresi dolmuş çerez → temizle
                    service('response')->deleteCookie(self::HATIRLA_COOKIE);
                }
            }
        }

        if (! $session->get('giris_yapildi')) {
            if ($request->isAJAX()) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON(['durum' => false, 'mesaj' => 'Oturum sona erdi. Lütfen tekrar giriş yapın.']);
            }

            $session->set('yonlendir_url', current_url());

            return redirect()->to(site_url('giris'))->with('hata', 'Bu sayfa için giriş yapmalısınız.');
        }

        // Rol kontrolü:  filter'a  auth:admin  şeklinde parametre verilebilir
        if ($arguments !== null && $arguments !== []) {
            $rol = $session->get('rol');

            if (! in_array($rol, $arguments, true)) {
                if ($request->isAJAX()) {
                    return service('response')->setStatusCode(403)
                        ->setJSON(['durum' => false, 'mesaj' => 'Bu işlem için yetkiniz yok.']);
                }

                return redirect()->to(site_url('panel'))->with('hata', 'Bu sayfaya erişim yetkiniz bulunmuyor.');
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
