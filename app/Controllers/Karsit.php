<?php

namespace App\Controllers;

use App\Models\KarsitIncelemeModel;
use App\Models\MukellefModel;

class Karsit extends BaseController
{
    protected KarsitIncelemeModel $model;

    public function __construct()
    {
        $this->model = new KarsitIncelemeModel();
    }

    // -----------------------------------------------------------------
    public function index()
    {
        $filtre = [
            'durum'      => $this->request->getGet('durum'),
            'yil'        => $this->request->getGet('yil'),
            'q'          => $this->request->getGet('q'),
            'gecikmis'   => $this->request->getGet('gecikmis'),
            'musavir_id' => $this->kapsamBelirle($this->request->getGet('musavir_id')),
        ];

        return $this->goster('karsit/index', [
            'kayitlar'    => $this->model->listele($filtre),
            'filtre'      => $filtre,
            'ozet'        => $this->model->ozet($this->musavirFiltresi()),
            'durumlar'    => KarsitIncelemeModel::DURUMLAR,
            'mukellefler' => $this->mukellefSecenekleri(),
            'ymmler'      => $this->model->ymmListesi(),
            'musavirler'  => $this->secilebilirMusavirler(),
        ], 'Karşıt İnceleme Tutanakları');
    }

    // -----------------------------------------------------------------
    public function kaydet()
    {
        $id   = (int) $this->request->getPost('id');
        $veri = $this->formVerisi();

        // Mükellef yetkisi
        $mukellef = (new MukellefModel())->find($veri['mukellef_id']);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->back()->withInput()->with('hata', 'Bu mükellef için yetkiniz yok.');
        }

        if ($id > 0) {
            $mevcut = $this->model->find($id);

            if ($mevcut === null || ! $this->kayitYetkisi($mevcut)) {
                return redirect()->to(site_url('karsit'))->with('hata', 'Kayıt bulunamadı.');
            }

            $ok = $this->model->update($id, $veri);
        } else {
            $veri['kaydeden_id'] = (int) $this->aktifKullanici['id'];
            $ok                  = $this->model->insert($veri);
        }

        if (! $ok) {
            return redirect()->back()->withInput()->with('hatalar', $this->model->errors());
        }

        return redirect()->to(site_url('karsit'))
            ->with('basari', $id > 0 ? 'Tutanak güncellendi.' : 'Tutanak kaydedildi.');
    }

    /** AJAX: durum değiştir */
    public function durumGuncelle()
    {
        $id    = (int) $this->request->getPost('id');
        $durum = (string) $this->request->getPost('durum');

        $kayit = $this->model->find($id);

        if ($kayit === null) {
            return $this->jsonHata('Kayıt bulunamadı.', 404);
        }

        if (! $this->kayitYetkisi($kayit)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        if (! $this->model->durumDegistir($id, $durum)) {
            return $this->jsonHata('Durum güncellenemedi.');
        }

        $yeni = $this->model->find($id);

        return $this->jsonBasarili('Durum güncellendi.', [
            'yeni_durum'      => $yeni['durum'],
            'durum_metin'     => KarsitIncelemeModel::DURUMLAR[$yeni['durum']] ?? $yeni['durum'],
            'gonderim_tarihi' => $yeni['gonderim_tarihi']
                ? date('d.m.Y', strtotime($yeni['gonderim_tarihi'])) : null,
        ]);
    }

    /** AJAX: not kaydet */
    public function notKaydet()
    {
        $id = (int) $this->request->getPost('id');

        $kayit = $this->model->find($id);

        if ($kayit === null || ! $this->kayitYetkisi($kayit)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        $this->model->update($id, ['not_metni' => $this->request->getPost('not')]);

        return $this->jsonBasarili('Not kaydedildi.');
    }

    public function sil(int $id)
    {
        $kayit = $this->model->find($id);

        if ($kayit === null || ! $this->kayitYetkisi($kayit)) {
            return redirect()->to(site_url('karsit'))->with('hata', 'Kayıt bulunamadı.');
        }

        $this->model->delete($id);

        return redirect()->to(site_url('karsit'))->with('basari', 'Tutanak silindi.');
    }

    // -----------------------------------------------------------------
    public function excel()
    {
        $filtre = [
            'durum'      => $this->request->getGet('durum'),
            'yil'        => $this->request->getGet('yil'),
            'q'          => $this->request->getGet('q'),
            'gecikmis'   => $this->request->getGet('gecikmis'),
            'musavir_id' => $this->kapsamBelirle($this->request->getGet('musavir_id')),
        ];

        $kayitlar = $this->model->listele($filtre);

        $csv = "\xEF\xBB\xBF";
        $csv .= "Mükellef;VKN/TCKN;YMM;Geliş Tarihi;Son Cevap Tarihi;Gönderim Tarihi;Durum;Not\n";

        foreach ($kayitlar as $k) {
            $csv .= implode(';', [
                str_replace(';', ',', (string) $k['mukellef_unvan']),
                $k['vergi_kimlik_no'] ?: $k['tc_kimlik_no'],
                str_replace(';', ',', (string) $k['ymm_adi']),
                date('d.m.Y', strtotime($k['gelis_tarihi'])),
                $k['son_cevap_tarihi'] ? date('d.m.Y', strtotime($k['son_cevap_tarihi'])) : '',
                $k['gonderim_tarihi'] ? date('d.m.Y', strtotime($k['gonderim_tarihi'])) : '',
                KarsitIncelemeModel::DURUMLAR[$k['durum']] ?? $k['durum'],
                str_replace([';', "\n", "\r"], [',', ' ', ''], (string) $k['not_metni']),
            ]) . "\n";
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="karsit_inceleme_' . date('Ymd_His') . '.csv"')
            ->setBody($csv);
    }

    public function yazdir()
    {
        $filtre = [
            'durum'      => $this->request->getGet('durum'),
            'yil'        => $this->request->getGet('yil'),
            'gecikmis'   => $this->request->getGet('gecikmis'),
            'musavir_id' => $this->kapsamBelirle($this->request->getGet('musavir_id')),
        ];

        return view('karsit/yazdir', [
            'kayitlar' => $this->model->listele($filtre),
            'filtre'   => $filtre,
            'durumlar' => KarsitIncelemeModel::DURUMLAR,
        ]);
    }

    // -----------------------------------------------------------------
    protected function formVerisi(): array
    {
        return [
            'mukellef_id'      => (int) $this->request->getPost('mukellef_id'),
            'ymm_adi'          => trim((string) $this->request->getPost('ymm_adi')),
            'gelis_tarihi'     => $this->request->getPost('gelis_tarihi'),
            'son_cevap_tarihi' => $this->request->getPost('son_cevap_tarihi') ?: null,
            'gonderim_tarihi'  => $this->request->getPost('gonderim_tarihi') ?: null,
            'durum'            => $this->request->getPost('durum') ?: 'CEVAP_BEKLIYOR',
            'not_metni'        => $this->request->getPost('not_metni'),
        ];
    }

    protected function kayitYetkisi(array $kayit): bool
    {
        if ($this->adminMi()) {
            return true;
        }

        $mukellef = (new MukellefModel())->find($kayit['mukellef_id']);

        return $mukellef !== null && $this->mukellefeErisebilirMi($mukellef);
    }

    /** Yetkili olunan mükellefler [id => unvan] */
    protected function mukellefSecenekleri(): array
    {
        $rows = (new MukellefModel())->listele([
            'musavir_id' => $this->musavirFiltresi(),
            'durum'      => 'hepsi',
        ]);

        $out = [];

        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r['unvan'];
        }

        return $out;
    }
}
