<?php

namespace App\Controllers;

use App\Models\GuncellemeModel;

/**
 * GÜNCELLEME LOGLARI (sürüm notları)
 *
 * Ekranlar:
 *   /guncellemeler              → tüm sürüm notları (her rol okur)
 *   /guncellemeler/yeni         → yeni kayıt formu (yalnız yönetici)
 *   /guncellemeler/duzenle/(:num)→ düzenleme (yalnız yönetici)
 *   AJAX: giris-uyarisi (okunmamışlar), uyari-okundu
 *
 * Okundu bilgisi kişi bazlıdır: bir kullanıcının okuması diğerini etkilemez.
 */
class Guncellemeler extends BaseController
{
    protected GuncellemeModel $model;

    public function __construct()
    {
        $this->model = new GuncellemeModel();
    }

    /** Giriş yapan kullanıcı id */
    protected function ben(): int
    {
        return (int) ($this->aktifKullanici['id'] ?? 0);
    }

    // =================================================================
    //  LİSTE (her rol)
    // =================================================================
    public function index()
    {
        $kid   = $this->ben();
        $admin = $this->adminMi();

        // Kullanıcının okudukları (rozet için)
        $okunan = $this->model->db->table('guncelleme_okundu')
            ->select('guncelleme_id')
            ->where('kullanici_id', $kid)
            ->get()->getResultArray();
        $okunanIdler = array_map(static fn ($r) => (int) $r['guncelleme_id'], $okunan);

        return $this->goster('guncellemeler/index', [
            'kayitlar'    => $this->model->liste(! $admin),
            'tumKayitlar' => $admin ? $this->model->liste(false) : null,
            'okunanIdler' => $okunanIdler,
            'turler'      => GuncellemeModel::TURLER,
            'adminMi'     => $admin,
            'yeniSayi'    => $this->model->okunmamisSayisi($kid),
        ], 'Güncellemeler');
    }

    // =================================================================
    //  YENİ / DÜZENLE (yalnız yönetici)
    // =================================================================
    public function yeni()
    {
        if (! $this->adminMi()) {
            return redirect()->to(site_url('guncellemeler'))
                ->with('hata', 'Güncelleme kaydı eklemeye yetkiniz yok.');
        }

        return $this->goster('guncellemeler/form', [
            'kayit'  => null,
            'turler' => GuncellemeModel::TURLER,
        ], 'Yeni Güncelleme');
    }

    public function duzenle(int $id)
    {
        if (! $this->adminMi()) {
            return redirect()->to(site_url('guncellemeler'))
                ->with('hata', 'Güncelleme kaydını düzenlemeye yetkiniz yok.');
        }

        $kayit = $this->model->find($id);

        if ($kayit === null) {
            return redirect()->to(site_url('guncellemeler'))->with('hata', 'Güncelleme kaydı bulunamadı.');
        }

        return $this->goster('guncellemeler/form', [
            'kayit'  => $kayit,
            'turler' => GuncellemeModel::TURLER,
        ], 'Güncelleme Düzenle');
    }

    /** Kaydet (yeni veya güncelleme) */
    public function kaydet()
    {
        if (! $this->adminMi()) {
            return redirect()->to(site_url('guncellemeler'))
                ->with('hata', 'Güncelleme kaydetmeye yetkiniz yok.');
        }

        $id = (int) $this->request->getPost('id');

        $veri = [
            'versiyon'     => trim((string) $this->request->getPost('versiyon')),
            'tarih'        => (string) $this->request->getPost('tarih'),
            'baslik'       => trim((string) $this->request->getPost('baslik')),
            'icerik'       => trim((string) $this->request->getPost('icerik')),
            'aktif'        => (int) $this->request->getPost('aktif') === 1 ? 1 : 0,
            'olusturan_id' => $this->ben() ?: null,
        ];

        $sonuc = $id > 0
            ? $this->model->update($id, $veri)
            : $this->model->insert($veri);

        if ($sonuc === false) {
            $hatalar = $this->model->errors();

            return redirect()->back()->withInput()
                ->with('hatalar', $hatalar ?: ['Kayıt başarısız.']);
        }

        $mesaj = $id > 0 ? 'Güncelleme kaydı yenilendi.' : 'Güncelleme kaydı eklendi.';

        if ($veri['aktif'] !== 1) {
            $mesaj .= ' (Yayında değil — kullanıcılara gösterilmiyor.)';
        } else {
            $mesaj .= ' Kullanıcılar girişte görecek.';
        }

        return redirect()->to(site_url('guncellemeler'))->with('basari', $mesaj);
    }

    /** Sil (yalnız yönetici) — okundu kayıtları da cascade silinir */
    public function sil(int $id)
    {
        if (! $this->adminMi()) {
            return redirect()->to(site_url('guncellemeler'))
                ->with('hata', 'Güncelleme kaydını silmeye yetkiniz yok.');
        }

        if ($this->model->find($id) === null) {
            return redirect()->to(site_url('guncellemeler'))->with('hata', 'Güncelleme kaydı bulunamadı.');
        }

        $this->model->delete($id);

        return redirect()->to(site_url('guncellemeler'))->with('basari', 'Güncelleme kaydı silindi.');
    }

    // =================================================================
    //  GİRİŞ UYARISI (AJAX) — okunmamışları göster
    // =================================================================

    /**
     * Girişte gösterilecek güncellemeler (yalnız kullanıcının okumadıkları).
     * Pencere kapatılınca `uyariOkundu` ile işaretlenir; bir daha görünmez.
     */
    public function girisUyarisi()
    {
        $kid = $this->ben();

        $kayitlar = $this->model->okunmamislar($kid);

        if ($kayitlar === []) {
            return $this->response->setJSON(['durum' => true, 'goster' => false]);
        }

        $cevir = static fn (array $satirlar) => array_map(static fn ($k) => [
            'id'       => (int) $k['id'],
            'versiyon' => (string) $k['versiyon'],
            'tarih'    => trTarih((string) $k['tarih']),
            'baslik'   => (string) $k['baslik'],
            'maddeler' => array_map(static fn ($m) => [
                'tur'   => $m['tur'],
                'ad'    => $m['ad'],
                'ikon'  => $m['ikon'],
                'renk'  => $m['renk'],
                'metin' => $m['metin'],
            ], $k['maddeler']),
        ], $satirlar);

        return $this->response->setJSON([
            'durum'  => true,
            'goster' => true,
            'sayi'   => count($kayitlar),
            'idler'  => array_map(static fn ($k) => (int) $k['id'], $kayitlar),
            'kayitlar' => $cevir($kayitlar),
        ]);
    }

    /** Pencere kapatıldı — gösterilenleri bu kullanıcı için okundu işaretle. */
    public function uyariOkundu()
    {
        $idler = $this->request->getPost('idler');
        $idler = is_array($idler) ? array_map('intval', $idler) : [];

        // Gönderilmediyse (eski istemci / tek tık) kullanıcının tüm okunmamışlarını kapat
        if ($idler === []) {
            $idler = $this->model->okunmamisSayisi($this->ben()) > 0
                ? array_map(static fn ($k) => (int) $k['id'], $this->model->okunmamislar($this->ben()))
                : [];
        }

        $adet = $this->model->okunduIsaretle($this->ben(), $idler);

        return $this->response->setJSON([
            'durum'  => true,
            'okunan' => $adet,
            'kalan'  => $this->model->okunmamisSayisi($this->ben()),
        ]);
    }
}
