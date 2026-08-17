<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

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
