<?php

namespace App\Controllers;

use App\Models\MusavirModel;

class Musavirler extends BaseController
{
    protected MusavirModel $model;

    public function __construct()
    {
        $this->model = new MusavirModel();
    }

    public function index()
    {
        $b = $this->model->orderBy('ad_soyad', 'ASC');

        // Yönetici değilse yalnızca erişim yetkisi olan müşavirler
        if (! $this->adminMi()) {
            $izin = $this->erisilenMusavirler();
            $b->whereIn('id', $izin === [] ? [0] : $izin);
        }

        return $this->goster('musavirler/index', [
            'musavirler' => $b->findAll(),
            'sayilar'    => $this->model->mukellefSayilari(),
            'saltOkunur' => ! $this->adminMi(),
        ], 'Mali Müşavirler');
    }

    public function yeni()
    {
        return $this->goster('musavirler/form', ['musavir' => null], 'Yeni Mali Müşavir');
    }

    public function kaydet()
    {
        if (! $this->model->insert($this->formVerisi())) {
            return redirect()->back()->withInput()->with('hatalar', $this->model->errors());
        }

        return redirect()->to(site_url('musavirler'))->with('basari', 'Mali müşavir kaydedildi.');
    }

    public function duzenle(int $id)
    {
        $musavir = $this->model->find($id);

        if ($musavir === null) {
            return redirect()->to(site_url('musavirler'))->with('hata', 'Kayıt bulunamadı.');
        }

        if (! $this->musavireErisebilirMi($id)) {
            return redirect()->to(site_url('musavirler'))->with('hata', 'Bu kayda erişim yetkiniz yok.');
        }

        return $this->goster('musavirler/form', ['musavir' => $musavir], 'Mali Müşavir Düzenle');
    }

    public function guncelle(int $id)
    {
        if (! $this->musavireErisebilirMi($id)) {
            return redirect()->to(site_url('musavirler'))->with('hata', 'Bu kayda erişim yetkiniz yok.');
        }

        if (! $this->model->update($id, $this->formVerisi())) {
            return redirect()->back()->withInput()->with('hatalar', $this->model->errors());
        }

        return redirect()->to(site_url('musavirler'))->with('basari', 'Mali müşavir güncellendi.');
    }

    public function sil(int $id)
    {
        $sayilar = $this->model->mukellefSayilari();

        if (! empty($sayilar[$id])) {
            return redirect()->to(site_url('musavirler'))
                ->with('hata', 'Bu müşavire bağlı mükellefler var. Önce mükellefleri başka müşavire aktarın.');
        }

        $this->model->delete($id);

        return redirect()->to(site_url('musavirler'))->with('basari', 'Mali müşavir silindi.');
    }

    protected function formVerisi(): array
    {
        return [
            'unvan'        => $this->request->getPost('unvan'),
            'ad_soyad'     => trim((string) $this->request->getPost('ad_soyad')),
            'buro_adi'     => $this->request->getPost('buro_adi'),
            'tc_kimlik'    => $this->request->getPost('tc_kimlik') ?: null,
            'ruhsat_no'    => $this->request->getPost('ruhsat_no'),
            'oda_sicil_no' => $this->request->getPost('oda_sicil_no'),
            'telefon'      => $this->request->getPost('telefon'),
            'eposta'       => $this->request->getPost('eposta') ?: null,
            'adres'        => $this->request->getPost('adres'),
            'renk'         => $this->request->getPost('renk') ?: '#2563eb',
            'aktif'        => (int) ($this->request->getPost('aktif') ?? 1),
        ];
    }
}
