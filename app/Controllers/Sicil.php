<?php

namespace App\Controllers;

use App\Models\MukellefModel;
use App\Models\KullaniciModel;
use App\Models\SicilDegisiklikModel;
use App\Models\SicilGorevModel;
use App\Models\SicilKuralModel;
use App\Models\SicilTurModel;

/**
 * SİCİL — İŞLEMLER (sade şablon / işlem / todo)
 *
 * Ekranlar:
 *   /sicil                 → işlem listesi (Şablon seç → Tarih gir → Todo üretilir)
 *   /sicil/ekle            → yeni işlem (mükellef + şablon + tarih)
 *   /sicil/duzenle/(:num)  → işlem düzenle (tarih/açıklama)
 *   /sicil/detay/(:num)    → işlem detayı + TODO listesi (checkbox)
 *   AJAX: kaydet, todo-durum
 */
class Sicil extends BaseController
{
    protected SicilDegisiklikModel $degModel;
    protected SicilGorevModel $gorevModel;
    protected SicilTurModel $turModel;

    /** Dashboard hızlı filtrelerinden gelen izinli aralıklar. */
    protected const ARALIKLAR = ['gecikti', 'bugun', 'ic7', 'ic15', 'tamamlanan'];

    public function __construct()
    {
        $this->degModel   = new SicilDegisiklikModel();
        $this->gorevModel = new SicilGorevModel();
        $this->turModel   = new SicilTurModel();
    }

    /** Giriş yapan kullanıcı id */
    protected function ben(): int
    {
        return (int) ($this->aktifKullanici['id'] ?? 0);
    }

    // =================================================================
    //  İŞLEM LİSTESİ
    // =================================================================
    public function index()
    {
        $aralik = (string) $this->request->getGet('aralik');

        if (! in_array($aralik, self::ARALIKLAR, true)) {
            $aralik = '';
        }

        $filtre = [
            'yil'         => $this->request->getGet('yil') ?: null,
            'turu_id'     => $this->cokluAl('tur'),
            'durum'       => $this->request->getGet('durum') ?: null,
            'mukellef_id' => (int) $this->request->getGet('mukellef_id') ?: null,
            'musavir_id'  => $this->kapsamBelirle($this->request->getGet('musavir_id')),
            'q'           => $this->request->getGet('q') ?: null,
            'aralik'      => $aralik,
        ];

        $kayitlar = $this->degModel->listele($filtre);
        $kapsam   = $this->musavirFiltresi();

        return $this->goster('sicil/index', [
            'kayitlar'   => $kayitlar,
            'filtre'     => $filtre,
            'ozet'       => $this->degModel->ozet($kapsam),
            'sayac'      => $this->gorevModel->sayaclar($kapsam),
            'turler'     => $this->turModel->secenekler(),
            'durumlar'   => SicilDegisiklikModel::DURUMLAR,
            'musavirler' => $this->secilebilirMusavirler(),
        ], 'Sicil İşlemleri');
    }

    // =================================================================
    //  EKLE / DÜZENLE
    // =================================================================
    public function ekle()
    {
        return $this->goster('sicil/form', [
            'degisiklik'  => null,
            'mukellefId'  => (int) $this->request->getGet('mukellef_id') ?: 0,
            'turler'      => $this->turModel->secenekler(),
            'sablonOz'    => $this->sablonIpuclari(),
            'baslik'      => '➕ Yeni Sicil İşlemi',
        ], 'Yeni Sicil İşlemi');
    }

    public function duzenle(int $id)
    {
        $degisiklik = $this->degModel->find($id);

        if ($degisiklik === null) {
            return redirect()->to(site_url('sicil'))->with('hata', 'İşlem bulunamadı.');
        }

        if (! $this->degisiklikYetkisi($degisiklik)) {
            return redirect()->to(site_url('sicil'))->with('hata', 'Bu kayda erişemezsiniz.');
        }

        return $this->goster('sicil/form', [
            'degisiklik'  => $degisiklik,
            'mukellefId'  => (int) $degisiklik['mukellef_id'],
            'turler'      => $this->turModel->secenekler(),
            'sablonOz'    => $this->sablonIpuclari(),
            'baslik'      => '✏️ İşlemi Düzenle',
        ], 'Sicil İşlemi Düzenle');
    }

    /**
     * Şablon seçilince formda gösterilecek todo önizlemesi.
     * [şablon_id => [['ad','sure_tipi','sure_deger','belirli_tarih','ozet']]]
     */
    protected function sablonIpuclari(): array
    {
        $out = [];

        foreach ($this->turModel->aktifler() as $sablon) {
            $sid  = (int) $sablon['id'];
            $rows = [];

            foreach ((new SicilKuralModel())->aktifTodoTanimlari($sid) as $t) {
                $rows[] = [
                    'ad'            => $t['ad'],
                    'sure_tipi'     => $t['sure_tipi'],
                    'sure_deger'    => $t['sure_deger'],
                    'belirli_tarih' => $t['belirli_tarih'],
                    'ozet'          => $this->sureOzeti($t),
                ];
            }

            $out[$sid] = $rows;
        }

        return $out;
    }

    /** Todo tanımı için kısa "10 gün / 1 ay / 31.12.2026" özet metni. */
    protected function sureOzeti(array $t): string
    {
        return match ($t['sure_tipi']) {
            'BELIRLI_TARIH' => 'belirli tarih: ' . trTarih($t['belirli_tarih']),
            'AY'            => (int) $t['sure_deger'] . ' ay',
            'IS_GUNU'       => (int) $t['sure_deger'] . ' iş günü',
            'TAKVIM_GUNU'   => (int) $t['sure_deger'] . ' takvim günü',
            default         => (int) $t['sure_deger'] . ' gün',
        };
    }

    // =================================================================
    //  KAYDET (AJAX) — ŞABLON SEÇ → TARİH GİR → TODO LİSTESİ ÜRETİLİR
    // =================================================================
    public function kaydet()
    {
        $id = (int) $this->request->getPost('id');

        $veri = [
            'mukellef_id'       => (int) $this->request->getPost('mukellef_id'),
            'turu_id'           => (int) $this->request->getPost('turu_id'),
            'degisiklik_tarihi' => $this->request->getPost('degisiklik_tarihi'),
            'aciklama'          => trim((string) $this->request->getPost('aciklama')) ?: null,
            'konu'              => trim((string) $this->request->getPost('konu')) ?: null,
        ];

        // Doğrulama
        if ($veri['mukellef_id'] <= 0) {
            return $this->jsonHata('Mükellef seçimi zorunludur.');
        }

        $mukellef = (new MukellefModel())->find($veri['mukellef_id']);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return $this->jsonHata('Bu mükellef için yetkiniz yok.', 403);
        }

        if ($veri['turu_id'] <= 0) {
            return $this->jsonHata('Şablon seçimi zorunludur.');
        }

        $sablon = $this->turModel->find($veri['turu_id']);

        if ($sablon === null || (int) $sablon['aktif'] !== 1) {
            return $this->jsonHata('Seçilen şablon bulunamadı veya pasif.');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $veri['degisiklik_tarihi'])) {
            return $this->jsonHata('Geçerli bir işlem tarihi giriniz.');
        }

        if ((string) $veri['degisiklik_tarihi'] > date('Y-m-d')) {
            return $this->jsonHata('İşlem tarihi bugünden ileri olamaz.');
        }

        // Yeni işlem: transaction ile şablonun todo listesini üret
        if ($id <= 0) {
            $sonuc = $this->degModel->olustur($veri, $this->ben());

            if (! $sonuc['durum']) {
                return $this->jsonHata($sonuc['hata'] ?? 'Kayıt başarısız.');
            }

            return $this->jsonBasarili('İşlem kaydedildi.', [
                'id'        => $sonuc['id'],
                'todolar'   => $sonuc['olusan_todolar'],
                'todoYok'   => $sonuc['todo_yoksa'],
                'sablon'    => $sablon['ad'],
            ]);
        }

        // Güncelleme (işlem + açık todo'ların yeniden hesaplanması)
        $degisiklik = $this->degModel->find($id);

        if ($degisiklik === null || ! $this->degisiklikYetkisi($degisiklik)) {
            return $this->jsonHata('Bu kayda erişemezsiniz.', 403);
        }

        $sonuc = $this->degModel->guncelle($id, $veri, $this->ben());

        if (! $sonuc['durum']) {
            return $this->jsonHata($sonuc['hata'] ?? 'Güncelleme başarısız.');
        }

        return $this->jsonBasarili('İşlem güncellendi.', ['id' => $id]);
    }

    // =================================================================
    //  DETAY (işlem + TODO listesi)
    // =================================================================
    public function detay(int $id)
    {
        $degisiklik = $this->degModel->find($id);

        if ($degisiklik === null) {
            return redirect()->to(site_url('sicil'))->with('hata', 'İşlem bulunamadı.');
        }

        if (! $this->degisiklikYetkisi($degisiklik)) {
            return redirect()->to(site_url('sicil'))->with('hata', 'Bu kayda erişemezsiniz.');
        }

        $detay = $this->degModel->detay($id);

        return $this->goster('sicil/detay', [
            'degisiklik' => $detay,
            'mukellef'   => (new MukellefModel())->find((int) $degisiklik['mukellef_id']),
            'durumlar'   => SicilGorevModel::DURUMLAR,
        ], 'İşlem Detayı');
    }

    /** İşlemi sil (soft delete) — yalnız admin */
    public function sil(int $id)
    {
        if (! $this->adminMi()) {
            return redirect()->back()->with('hata', 'Yalnız yönetici silebilir.');
        }

        $degisiklik = $this->degModel->find($id);

        if ($degisiklik !== null) {
            $this->degModel->sicilSil($id);
        }

        return redirect()->to(site_url('sicil'))->with('basari', 'İşlem silindi.');
    }

    // =================================================================
    //  TODO DURUMU (AJAX) — checkbox: yapıldı / geri aç / takip dışı
    // =================================================================
    public function todoDurum()
    {
        $id    = (int) $this->request->getPost('id');
        $durum = (string) $this->request->getPost('durum');

        $todo = $this->gorevModel->find($id);

        if ($todo === null) {
            return $this->jsonHata('Todo bulunamadı.', 404);
        }

        $degisiklik = $this->degModel->find((int) $todo['sicil_degisikligi_id']);

        if ($degisiklik === null || ! $this->degisiklikYetkisi($degisiklik)) {
            return $this->jsonHata('Bu kayda erişemezsiniz.', 403);
        }

        $sonuc = $this->gorevModel->durumDegistir($id, $durum, $this->ben());

        if (! $sonuc['durum']) {
            return $this->jsonHata($sonuc['mesaj'] ?? 'Durum güncellenemedi.');
        }

        // Üst işlem durumunu todo'lardan türet
        $this->degModel->durumTure((int) $degisiklik['id']);

        $yeni = $sonuc['kayit'];
        $det  = $this->degModel->detay((int) $degisiklik['id']);

        $toplam = is_array($det['todolar'] ?? null) ? count($det['todolar']) : 0;
        $tamam  = 0;

        foreach ($det['todolar'] ?? [] as $t) {
            if ($t['durum'] === 'TAMAM') {
                $tamam++;
            }
        }

        $yapanAd = $yeni['yapan_id']
            ? ((new KullaniciModel())->find((int) $yeni['yapan_id'])['ad_soyad'] ?? null)
            : null;

        $degDurum = $this->degModel->find((int) $degisiklik['id'])['durum'];

        return $this->jsonBasarili('Durum güncellendi.', [
            'id'                 => (int) $yeni['id'],
            'yeni_durum'         => $yeni['durum'],
            'durum_metin'        => SicilGorevModel::DURUMLAR[$yeni['durum']] ?? $yeni['durum'],
            'etik'               => todoKalanEtiketi($yeni['son_tarih'], (string) $yeni['durum']),
            'son_tarih'          => $yeni['son_tarih'] ? trTarih($yeni['son_tarih']) : null,
            'yapan_adi'          => $yapanAd,
            'tamamlanma_tarihi'  => $yeni['tamamlanma_tarihi']
                ? date('d.m.Y H:i', strtotime($yeni['tamamlanma_tarihi'])) : null,
            'deg_id'             => (int) $degisiklik['id'],
            'deg_durum'          => $degDurum,
            'deg_durum_metin'    => SicilDegisiklikModel::DURUMLAR[$degDurum] ?? '',
            'tamam'              => $tamam,
            'toplam'             => $toplam,
        ]);
    }

    // =================================================================
    //  YETKİ
    // =================================================================
    /** Kayıt bazlı erişim: admin → tümü; diğerleri → mükellefin müşaviri. */
    protected function degisiklikYetkisi(array $degisiklik): bool
    {
        if ($this->adminMi()) {
            return true;
        }

        $mukellef = (new MukellefModel())->find((int) $degisiklik['mukellef_id']);

        return $mukellef !== null && $this->mukellefeErisebilirMi($mukellef);
    }
}
