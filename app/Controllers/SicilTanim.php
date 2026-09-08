<?php

namespace App\Controllers;

use App\Models\SicilKuralModel;
use App\Models\SicilKurumModel;
use App\Models\SicilTurModel;

/**
 * SİCİL — TANIM YÖNETİMİ
 *
 *   /sicil-tanim/turler       değişiklik türleri
 *   /sicil-tanim/kurumlar     bildirim kurumları
 *   /sicil-tanim/kurallar     bildirim kuralları (süreler)
 *
 * Yetki: admin ve musavir erişebilir; KAYDETME yalnız admin (tanım değişikliği
 * mevzuat/sistem davranışını etkilediği için yönetici işidir).
 */
class SicilTanim extends BaseController
{
    protected SicilTurModel $turModel;
    protected SicilKurumModel $kurumModel;
    protected SicilKuralModel $kuralModel;

    public function __construct()
    {
        $this->turModel   = new SicilTurModel();
        $this->kurumModel = new SicilKurumModel();
        $this->kuralModel = new SicilKuralModel();
    }

    // =================================================================
    //  DEĞİŞİKLİK TÜRLERİ
    // =================================================================
    public function turler()
    {
        return $this->goster('sicil/tanim_turler', [
            'turler' => $this->turModel->orderBy('sira', 'ASC')->orderBy('ad', 'ASC')->findAll(),
        ], 'Değişiklik Türleri');
    }

    public function turKaydet()
    {
        if (! $this->adminMi()) {
            return redirect()->back()->with('hata', 'Yalnız yönetici tanım ekleyebilir.');
        }

        $id  = (int) $this->request->getPost('id');
        $veri = [
            'kod'      => trim((string) $this->request->getPost('kod')),
            'ad'       => trim((string) $this->request->getPost('ad')),
            'aciklama' => trim((string) $this->request->getPost('aciklama')) ?: null,
            'sira'     => (int) $this->request->getPost('sira'),
            'aktif'    => $this->request->getPost('aktif') ? 1 : 0,
        ];

        if ($veri['ad'] === '' || $veri['kod'] === '') {
            return redirect()->back()->with('hata', 'Tür adı ve kodu zorunludur.');
        }

        if ($id > 0) {
            unset($veri['kod']); // kod değişmez
            $this->turModel->update($id, $veri);
            $mesaj = 'Tür güncellendi.';
        } else {
            $this->turModel->insert($veri);
            $mesaj = 'Tür eklendi.';
        }

        return redirect()->to(site_url('sicil-tanim/turler'))->with('basari', $mesaj);
    }

    public function turPasif(int $id)
    {
        if (! $this->adminMi()) {
            return redirect()->back()->with('hata', 'Yalnız yönetici pasife alabilir.');
        }

        $mevcut = $this->turModel->find($id);

        if ($mevcut !== null) {
            $this->turModel->update($id, ['aktif' => $mevcut['aktif'] ? 0 : 1]);
        }

        return redirect()->to(site_url('sicil-tanim/turler'))->with('basari', 'Durum güncellendi.');
    }

    // =================================================================
    //  KURUMLAR
    // =================================================================
    public function kurumlar()
    {
        return $this->goster('sicil/tanim_kurumlar', [
            'kurumlar' => $this->kurumModel->orderBy('ad', 'ASC')->findAll(),
        ], 'Bildirim Kurumları');
    }

    public function kurumKaydet()
    {
        if (! $this->adminMi()) {
            return redirect()->back()->with('hata', 'Yalnız yönetici tanım ekleyebilir.');
        }

        $id   = (int) $this->request->getPost('id');
        $veri = [
            'kod'      => trim((string) $this->request->getPost('kod')),
            'ad'       => trim((string) $this->request->getPost('ad')),
            'kisa_ad'  => trim((string) $this->request->getPost('kisa_ad')) ?: null,
            'aciklama' => trim((string) $this->request->getPost('aciklama')) ?: null,
            'aktif'    => $this->request->getPost('aktif') ? 1 : 0,
        ];

        if ($veri['ad'] === '' || $veri['kod'] === '') {
            return redirect()->back()->with('hata', 'Kurum adı ve kodu zorunludur.');
        }

        if ($id > 0) {
            unset($veri['kod']);
            $this->kurumModel->update($id, $veri);
            $mesaj = 'Kurum güncellendi.';
        } else {
            $this->kurumModel->insert($veri);
            $mesaj = 'Kurum eklendi.';
        }

        return redirect()->to(site_url('sicil-tanim/kurumlar'))->with('basari', $mesaj);
    }

    public function kurumPasif(int $id)
    {
        if (! $this->adminMi()) {
            return redirect()->back()->with('hata', 'Yalnız yönetici pasife alabilir.');
        }

        $mevcut = $this->kurumModel->find($id);

        if ($mevcut !== null) {
            $this->kurumModel->update($id, ['aktif' => $mevcut['aktif'] ? 0 : 1]);
        }

        return redirect()->to(site_url('sicil-tanim/kurumlar'))->with('basari', 'Durum güncellendi.');
    }

    // =================================================================
    //  BİLDİRİM KURALLARI
    // =================================================================
    public function kurallar()
    {
        return $this->goster('sicil/tanim_kurallar', [
            'kurallar' => $this->kuralModel->listele([]),
            'turler'   => $this->turModel->secenekler(),
            'kurumlar' => $this->kurumModel->secenekler(),
            'sureTipleri' => SicilKuralModel::SURE_TIPLERI,
        ], 'Bildirim Kuralları');
    }

    public function kuralKaydet()
    {
        if (! $this->adminMi()) {
            return redirect()->back()->with('hata', 'Yalnız yönetici kural ekleyebilir.');
        }

        $id = (int) $this->request->getPost('id');

        $veri = [
            'degisiklik_turu_id' => (int) $this->request->getPost('degisiklik_turu_id'),
            'kurum_id'           => (int) $this->request->getPost('kurum_id'),
            'sure_tipi'          => $this->request->getPost('sure_tipi'),
            'sure_deger'         => $this->request->getPost('sure_deger') !== ''
                ? (int) $this->request->getPost('sure_deger') : null,
            'belirli_tarih'      => $this->request->getPost('belirli_tarih') ?: null,
            'oncelik'            => (int) $this->request->getPost('oncelik'),
            'aciklama'           => trim((string) $this->request->getPost('aciklama')) ?: null,
            'aktif'              => $this->request->getPost('aktif') ? 1 : 0,
            'olusturan_id'       => (int) $this->aktifKullanici['id'],
        ];

        $sonuc = $this->kuralModel->kaydet($veri, $id > 0 ? $id : null);

        if (! $sonuc['durum']) {
            return redirect()->back()->with('hata', $sonuc['hata'] ?? 'Kural kaydedilemedi.');
        }

        return redirect()->to(site_url('sicil-tanim/kurallar'))
            ->with('basari', $id > 0 ? 'Kural güncellendi.' : 'Kural eklendi.');
    }

    /** Kuralı pasife al (kullanımdaki kurallar silinmez) */
    public function kuralPasif(int $id)
    {
        if (! $this->adminMi()) {
            return redirect()->back()->with('hata', 'Yalnız yönetici pasife alabilir.');
        }

        $mevcut = $this->kuralModel->find($id);

        if ($mevcut !== null) {
            $this->kuralModel->update($id, ['aktif' => $mevcut['aktif'] ? 0 : 1]);
        }

        return redirect()->to(site_url('sicil-tanim/kurallar'))->with('basari', 'Durum güncellendi.');
    }
}
