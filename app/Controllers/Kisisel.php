<?php

namespace App\Controllers;

use App\Models\KisiselNotModel;

/**
 * KİŞİSEL GÜNLÜK NOT + TO-DO
 *
 * Her kullanıcının kendine özel not/todo alanı. Veri tamamen izole:
 * tüm işlemler giriş yapan kullanıcının id'siyle yapılır; yönetici dahil
 * hiçbir başka rol başkasının kayıtlarını göremez veya değiştiremez.
 */
class Kisisel extends BaseController
{
    protected KisiselNotModel $model;

    public function __construct()
    {
        $this->model = new KisiselNotModel();
    }

    /** Giriş yapan kullanıcının id'si (her zaman var: auth filtresi) */
    protected function ben(): int
    {
        return (int) ($this->aktifKullanici['id'] ?? 0);
    }

    public function index()
    {
        $kid = $this->ben();

        // Varsayılan bugün; ?tarih= ile geçmişe not bakılabilir
        $tarihHam = $this->request->getGet('tarih');
        $tarih    = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tarihHam)
            ? $tarihHam : date('Y-m-d');

        return $this->goster('kisisel/index', [
            'bugun'   => date('Y-m-d'),
            'tarih'   => $tarih,
            'gunNotu' => $this->model->gunNotu($kid, $tarih),
            'gecmis'  => $this->model->gecmisNotlar($kid),
            'acik'    => $this->model->acikGorevler($kid),
            'biten'   => $this->model->bitenGorevler($kid),
        ], 'Kişisel Notlarım');
    }

    /** Günlük notu kaydet (POST) */
    public function notKaydet()
    {
        $tarih = (string) $this->request->getPost('tarih');
        $metin = trim((string) $this->request->getPost('metin'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih)) {
            $tarih = date('Y-m-d');
        }

        $this->model->gunNotuKaydet($this->ben(), $tarih, $metin);

        return redirect()->to(site_url('kisisel?tarih=' . $tarih))
            ->with('basari', $metin === '' ? 'Not silindi.' : 'Not kaydedildi.');
    }

    /** Görev ekle (POST) */
    public function gorevEkle()
    {
        $baslik = trim((string) $this->request->getPost('baslik'));
        $metin  = trim((string) $this->request->getPost('metin'));

        if ($baslik === '') {
            return redirect()->back()->with('hata', 'Görev adı boş olamaz.');
        }

        $this->model->gorevEkle($this->ben(), $baslik, $metin);

        return redirect()->to(site_url('kisisel'))->with('basari', 'Görev eklendi.');
    }

    /** Görevi tamamla / geri aç (POST) */
    public function gorevTers()
    {
        $id = (int) $this->request->getPost('id');

        if ($id > 0 && ! $this->model->gorevTersCevir($this->ben(), $id)) {
            return redirect()->to(site_url('kisisel'))->with('hata', 'Görev bulunamadı.');
        }

        return redirect()->to(site_url('kisisel'));
    }

    /** Görev veya geçmiş not sil (POST) */
    public function sil()
    {
        $id  = (int) $this->request->getPost('id');
        $tur = $this->request->getPost('tur');

        if ($id > 0 && ! $this->model->kisiselSil($this->ben(), $id)) {
            return redirect()->back()->with('hata', 'Kayıt bulunamadı.');
        }

        if ($tur === 'not') {
            return redirect()->back()->with('basari', 'Not silindi.');
        }

        return redirect()->to(site_url('kisisel'))->with('basari', 'Görev silindi.');
    }
}
