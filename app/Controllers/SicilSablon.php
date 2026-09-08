<?php

namespace App\Controllers;

use App\Models\SicilKuralModel;
use App\Models\SicilTurModel;

/**
 * SİCİL — ŞABLON YÖNETİMİ (admin / müşavir)
 *
 * Şablon = sicil_degisiklik_turleri; altındaki todo tanımları =
 * sicil_bildirim_kurallari. Tek ekranda şablon adı + todo listesi
 * (ad, süre, süre türü, aktif) tanımlanır.
 *
 * Ekranlar:
 *   /sicil-sablon            → şablon listesi
 *   /sicil-sablon/yeni       → yeni şablon + todo tanımları
 *   /sicil-sablon/duzenle/(:num)
 */
class SicilSablon extends BaseController
{
    protected SicilTurModel $turModel;
    protected SicilKuralModel $kuralModel;

    public function __construct()
    {
        $this->turModel   = new SicilTurModel();
        $this->kuralModel = new SicilKuralModel();
    }

    public function index()
    {
        $filtre = [
            'q'     => $this->request->getGet('q') ?: null,
            'aktif' => $this->request->getGet('aktif') !== '' && $this->request->getGet('aktif') !== null
                        ? (int) $this->request->getGet('aktif') : null,
        ];

        return $this->goster('sicil_sablon/index', [
            'kayitlar' => $this->turModel->listele($filtre),
            'filtre'   => $filtre,
        ], 'Sicil Şablonları');
    }

    public function yeni()
    {
        return $this->goster('sicil_sablon/form', [
            'sablon'   => null,
            'satirlar' => [],
            'baslik'   => '➕ Yeni Şablon',
        ], 'Yeni Şablon');
    }

    public function duzenle(int $id)
    {
        $sablon = $this->turModel->find($id);

        if ($sablon === null) {
            return redirect()->to(site_url('sicil-sablon'))->with('hata', 'Şablon bulunamadı.');
        }

        return $this->goster('sicil_sablon/form', [
            'sablon'   => $sablon,
            'satirlar' => $this->kuralModel->sablonTodoTanimlari($id),
            'baslik'   => '✏️ Şablonu Düzenle — ' . $sablon['ad'],
        ], 'Şablon Düzenle');
    }

    public function kaydet()
    {
        $kullaniciId = (int) ($this->aktifKullanici['id'] ?? 0);

        $veri = [
            'id'       => (int) $this->request->getPost('id'),
            'ad'       => trim((string) $this->request->getPost('ad')),
            'aciklama' => trim((string) $this->request->getPost('aciklama')),
            'aktif'    => (int) $this->request->getPost('aktif'),
        ];

        // Form satırları: todo_ad[] dizisi referans — diğer dizilerle aynı sırada
        $adlar     = (array) $this->request->getPost('todo_ad');
        $idler     = (array) $this->request->getPost('todo_id');
        $tipler    = (array) $this->request->getPost('todo_sure_tipi');
        $degerler  = (array) $this->request->getPost('todo_sure_deger');
        $belirliler = (array) $this->request->getPost('todo_belirli_tarih');
        $aktifler  = (array) $this->request->getPost('todo_aktif');

        $satirlar = [];

        foreach (array_keys($adlar) as $i) {
            $satirlar[] = [
                'id'             => (int) ($idler[$i] ?? 0),
                'ad'             => (string) ($adlar[$i] ?? ''),
                'sure_tipi'      => (string) ($tipler[$i] ?? 'GUN'),
                'sure_deger'     => (string) ($degerler[$i] ?? ''),
                'belirli_tarih'  => (string) ($belirliler[$i] ?? ''),
                'aktif'          => isset($aktifler[$i]) ? 1 : 0,
            ];
        }

        $sonuc = $this->turModel->sablonKaydet($veri, $satirlar, $kullaniciId);

        if (! $sonuc['durum']) {
            return redirect()->back()->withInput()
                ->with('hata', $sonuc['hata'] ?? 'Şablon kaydedilemedi.');
        }

        $mesaj = $veri['id'] > 0 ? 'Şablon güncellendi.' : 'Şablon oluşturuldu.';

        return redirect()->to(site_url('sicil-sablon/duzenle/' . (int) $sonuc['id']))
            ->with('basari', $mesaj);
    }

    /** Şablonu pasife alır (yeni işlemde seçilemez; geçmiş korunur). */
    public function pasif(int $id)
    {
        $sablon = $this->turModel->find($id);

        if ($sablon === null) {
            return redirect()->to(site_url('sicil-sablon'))->with('hata', 'Şablon bulunamadı.');
        }

        $yeniAktif = (int) $sablon['aktif'] === 1 ? 0 : 1;

        $this->turModel->update($id, ['aktif' => $yeniAktif]);

        return redirect()->to(site_url('sicil-sablon'))
            ->with('basari', $yeniAktif ? 'Şablon aktifleştirildi.' : 'Şablon pasife alındı.');
    }
}
