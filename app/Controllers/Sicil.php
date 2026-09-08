<?php

namespace App\Controllers;

use App\Models\MukellefModel;
use App\Models\SicilBelgeModel;
use App\Models\SicilDegisiklikModel;
use App\Models\SicilGorevModel;
use App\Models\SicilKuralModel;
use App\Models\SicilKurumModel;
use App\Models\SicilTurModel;

/**
 * SİCİL DEĞİŞİKLİKLERİ & BİLDİRİM TAKİP — kullanıcı arayüzü
 *
 * Ekranlar:
 *   /sicil                → değişiklik listesi
 *   /sicil/ekle           → yeni değişiklik (mükellef seçimi)
 *   /sicil/duzenle/(:num) → değişiklik düzenle (tarih/adres bilgileri)
 *   /sicil/detay/(:num)   → değişiklik detayı + görevleri
 *   /sicil/gorevler       → görev listesi (günlük iş)
 *   /sicil/gorev/(:num)   → tek görev detayı (belge + durum)
 *   AJAX: kaydet, durum, not, gorev-sil, belge ekle/indir/sil
 */
class Sicil extends BaseController
{
    protected SicilDegisiklikModel $degModel;
    protected SicilGorevModel $gorevModel;
    protected SicilTurModel $turModel;
    protected SicilKurumModel $kurumModel;
    protected SicilBelgeModel $belgeModel;

    public function __construct()
    {
        $this->degModel   = new SicilDegisiklikModel();
        $this->gorevModel = new SicilGorevModel();
        $this->turModel   = new SicilTurModel();
        $this->kurumModel = new SicilKurumModel();
        $this->belgeModel = new SicilBelgeModel();
    }

    /** Giriş yapan kullanıcı id */
    protected function ben(): int
    {
        return (int) ($this->aktifKullanici['id'] ?? 0);
    }

    // =================================================================
    //  SİCİL DEĞİŞİKLİK LİSTESİ
    // =================================================================
    public function index()
    {
        $filtre = [
            'yil'        => $this->request->getGet('yil') ?: null,
            'turu_id'    => $this->cokluAl('tur'),
            'durum'      => $this->request->getGet('durum') ?: null,
            'mukellef_id'=> (int) $this->request->getGet('mukellef_id') ?: null,
            'musavir_id' => $this->kapsamBelirle($this->request->getGet('musavir_id')),
            'q'          => $this->request->getGet('q') ?: null,
        ];

        $kayitlar = $this->degModel->listele($filtre);

        return $this->goster('sicil/index', [
            'kayitlar'    => $kayitlar,
            'filtre'      => $filtre,
            'ozet'        => $this->degModel->ozet($this->musavirFiltresi()),
            'turler'      => $this->turModel->secenekler(),
            'durumlar'    => SicilDegisiklikModel::DURUMLAR,
            'musavirler'  => $this->secilebilirMusavirler(),
            'gorevSayilar'=> $this->gorevModel->sayaclar($this->musavirFiltresi()),
        ], 'Sicil Değişiklikleri');
    }

    // =================================================================
    //  EKLE / DÜZENLE
    // =================================================================
    public function ekle()
    {
        return $this->goster('sicil/form', [
            'degisiklik'   => null,
            'mukellefId'   => (int) $this->request->getGet('mukellef_id') ?: 0,
            'turler'       => $this->turModel->secenekler(),
            'turBilgi'     => $this->turModel->aktifler(),
            'baslik'       => '➕ Sicil Değişikliği Ekle',
            'kurallarOz'   => $this->turIpuclari(),
        ], 'Sicil Değişikliği Ekle');
    }

    public function duzenle(int $id)
    {
        $degisiklik = $this->degModel->find($id);

        if ($degisiklik === null) {
            return redirect()->to(site_url('sicil'))->with('hata', 'Değişiklik bulunamadı.');
        }

        // Erişim kontrolü
        if (! $this->degisiklikYetkisi($degisiklik)) {
            return redirect()->to(site_url('sicil'))->with('hata', 'Bu kayda erişemezsiniz.');
        }

        return $this->goster('sicil/form', [
            'degisiklik'   => $degisiklik,
            'mukellefId'   => (int) $degisiklik['mukellef_id'],
            'turler'       => $this->turModel->secenekler(),
            'turBilgi'     => $this->turModel->aktifler(),
            'baslik'       => '✏️ Sicil Değişikliği Düzenle',
            'kurallarOz'   => $this->turIpuclari(),
        ], 'Sicil Değişikliği Düzenle');
    }

    /** Tür bazında kural ipuçları (formda canlı gösterim için) */
    protected function turIpuclari(): array
    {
        $kurallar = (new SicilKuralModel())->listele([]);
        $out      = [];

        foreach ($kurallar as $k) {
            $out[(int) $k['degisiklik_turu_id']][] = [
                'kurum'    => $k['kurum_ad'],
                'sure_tipi'=> $k['sure_tipi'],
                'sure_deger' => $k['sure_deger'],
                'belirli_tarih' => $k['belirli_tarih'],
            ];
        }

        return $out;
    }

    /** Sicil değişikliğini kaydet (yeni veya güncelleme) — AJAX */
    public function kaydet()
    {
        $id = (int) $this->request->getPost('id');

        // Eski değer opsiyonel, yeni değer + tarih + tür zorunlu
        $veri = [
            'mukellef_id'        => (int) $this->request->getPost('mukellef_id'),
            'turu_id'            => (int) $this->request->getPost('turu_id'),
            'degisiklik_tarihi'  => $this->request->getPost('degisiklik_tarihi'),
            'eski_deger'         => trim((string) $this->request->getPost('eski_deger')) ?: null,
            'yeni_deger'         => trim((string) $this->request->getPost('yeni_deger')) ?: null,
            'aciklama'           => trim((string) $this->request->getPost('aciklama')) ?: null,
            'referans_no'        => trim((string) $this->request->getPost('referans_no')) ?: null,
            'konu'               => trim((string) $this->request->getPost('konu')) ?: null,
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
            return $this->jsonHata('Değişiklik türü zorunludur.');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $veri['degisiklik_tarihi'])) {
            return $this->jsonHata('Geçerli bir değişiklik tarihi giriniz.');
        }

        if ((string) $veri['degisiklik_tarihi'] > date('Y-m-d')) {
            return $this->jsonHata('Değişiklik tarihi bugünden ileri olamaz.');
        }

        if (empty($veri['yeni_deger']) && empty($veri['aciklama'])) {
            return $this->jsonHata('Yeni bilgi veya açıklamadan en az biri gerekli.');
        }

        // Yeni kayıt: transaction ile görev üretimi
        if ($id <= 0) {
            $sonuc = $this->degModel->olustur($veri, $this->ben());

            if (! $sonuc['durum']) {
                return $this->jsonHata($sonuc['hata'] ?? 'Kayıt başarısız.');
            }

            return $this->jsonBasarili('Değişiklik kaydedildi.', [
                'id'        => $sonuc['id'],
                'gorevler'  => $sonuc['olusan_gorevler'],
                'kuralYok'  => $sonuc['kural_yoksa'],
            ]);
        }

        // Güncelleme (değişiklik kaydı + açık görevlerin yeniden hesaplanması)
        $degisiklik = $this->degModel->find($id);

        if ($degisiklik === null || ! $this->degisiklikYetkisi($degisiklik)) {
            return $this->jsonHata('Bu kayda erişemezsiniz.', 403);
        }

        $sonuc = $this->degModel->guncelle($id, $veri, $this->ben());

        if (! $sonuc['durum']) {
            return $this->jsonHata($sonuc['hata'] ?? 'Güncelleme başarısız.');
        }

        return $this->jsonBasarili('Değişiklik güncellendi.', ['id' => $id]);
    }

    // =================================================================
    //  DETAY (değişiklik + görevler)
    // =================================================================
    public function detay(int $id)
    {
        $degisiklik = $this->degModel->find($id);

        if ($degisiklik === null) {
            return redirect()->to(site_url('sicil'))->with('hata', 'Değişiklik bulunamadı.');
        }

        if (! $this->degisiklikYetkisi($degisiklik)) {
            return redirect()->to(site_url('sicil'))->with('hata', 'Bu kayda erişemezsiniz.');
        }

        $gorevler = $this->gorevModel->listele(['degisiklik_id' => $id]);

        return $this->goster('sicil/detay', [
            'degisiklik' => $degisiklik,
            'mukellef'   => (new MukellefModel())->find($degisiklik['mukellef_id']),
            'turAd'      => $this->turModel->find($degisiklik['turu_id'])['ad'] ?? '',
            'gorevler'   => $gorevler,
            'durumlar'   => SicilGorevModel::DURUMLAR,
            'belgeler'   => $this->belgeModel->listele($id, null),
        ], 'Sicil Değişikliği Detay');
    }

    /** Değişikliği sil (soft delete) — yalnız admin */
    public function sil(int $id)
    {
        if (! $this->adminMi()) {
            return redirect()->back()->with('hata', 'Yalnız yönetici silebilir.');
        }

        $this->degModel->sicilSil($id);

        return redirect()->to(site_url('sicil'))->with('basari', 'Değişiklik silindi.');
    }

    // =================================================================
    //  GÖREVLER
    // =================================================================
    public function gorevler()
    {
        // Zaman aralığı (sayaç kartından gelir) + özel "tamamlanan"
        $aralik = (string) $this->request->getGet('aralik');

        if (! in_array($aralik, ['gecikti', 'bugun', 'ic3', 'ic7', 'ic15', 'bekleyen', 'tamamlanan', ''], true)) {
            $aralik = '';
        }

        $filtre = [
            'durum'      => $this->cokluAl('durum', array_keys(SicilGorevModel::DURUMLAR)),
            'kurum_id'   => $this->cokluAl('kurum'),
            'aralik'     => $aralik,
            'mukellef_id'=> (int) $this->request->getGet('mukellef_id') ?: null,
            'musavir_id' => $this->kapsamBelirle($this->request->getGet('musavir_id')),
            'q'          => $this->request->getGet('q') ?: null,
        ];

        // Sayaç kartları durum/aralık kombinasyonuna çevrilir
        if ($aralik === 'tamamlanan' && empty($filtre['durum'])) {
            $filtre['durum'] = 'TAMAM';
            $filtre['aralik'] = '';
        } elseif ($aralik === 'bekleyen' && empty($filtre['durum'])) {
            $filtre['durum'] = SicilGorevModel::ACIK_DURUMLAR;
            $filtre['aralik'] = '';
        }

        return $this->goster('sicil/gorevler', [
            'kayitlar'   => $this->gorevModel->listele($filtre),
            'filtre'     => $filtre,
            'sayac'      => $this->gorevModel->sayaclar($this->musavirFiltresi()),
            'durumlar'   => SicilGorevModel::DURUMLAR,
            'kurumlar'   => $this->kurumModel->secenekler(),
            'musavirler' => $this->secilebilirMusavirler(),
        ], 'Bildirim Görevleri');
    }

    /** Tek görev detayı (belgelerle) */
    public function gorev(int $id)
    {
        $gorev = $this->gorevModel->detay($id);

        if ($gorev === null) {
            return redirect()->to(site_url('sicil/gorevler'))->with('hata', 'Görev bulunamadı.');
        }

        $degisiklik = $this->degModel->find((int) $gorev['sicil_degisikligi_id']);

        if ($degisiklik === null || ! $this->degisiklikYetkisi($degisiklik)) {
            return redirect()->to(site_url('sicil/gorevler'))->with('hata', 'Bu kayda erişemezsiniz.');
        }

        return $this->goster('sicil/gorev_detay', [
            'gorev'      => $gorev,
            'degisiklik' => $degisiklik,
            'durumlar'   => SicilGorevModel::DURUMLAR,
            'belgeler'   => $this->belgeModel->listele(null, $id),
            'ilgiliBelgeler' => $this->belgeModel->listele((int) $degisiklik['id'], null),
        ], 'Bildirim Görevi Detay');
    }

    // =================================================================
    //  AJAX İŞLEMLERİ
    // =================================================================
    /** Görev durumunu değiştir */
    public function gorevDurum()
    {
        $id    = (int) $this->request->getPost('id');
        $durum = (string) $this->request->getPost('durum');

        $gorev = $this->gorevModel->find($id);

        if ($gorev === null) {
            return $this->jsonHata('Görev bulunamadı.', 404);
        }

        $degisiklik = $this->degModel->find((int) $gorev['sicil_degisikligi_id']);

        if ($degisiklik === null || ! $this->degisiklikYetkisi($degisiklik)) {
            return $this->jsonHata('Bu kayda erişemezsiniz.', 403);
        }

        $sonuc = $this->gorevModel->durumDegistir($id, $durum, $this->ben());

        if (! $sonuc['durum']) {
            return $this->jsonHata($sonuc['mesaj'] ?? 'Durum güncellenemedi.');
        }

        // Üst değişiklik durumunu görevlerden türet
        $this->degModel->durumTure((int) $degisiklik['id']);

        $yeni = $sonuc['kayit'];

        return $this->jsonBasarili('Durum güncellendi.', [
            'id'                 => (int) $yeni['id'],
            'yeni_durum'         => $yeni['durum'],
            'durum_metin'        => SicilGorevModel::DURUMLAR[$yeni['durum']] ?? $yeni['durum'],
            'yapan_adi'          => $yeni['yapan_id'] ? ($yeni['yapan_id'] . '') : '',
            'tamamlanma_tarihi'  => $yeni['tamamlanma_tarihi']
                ? date('d.m.Y H:i', strtotime($yeni['tamamlanma_tarihi'])) : null,
            'deg_durum'          => $this->degModel->find((int) $degisiklik['id'])['durum'],
        ]);
    }

    /** Görev notunu kaydet */
    public function gorevNot()
    {
        $id = (int) $this->request->getPost('id');

        $gorev = $this->gorevModel->find($id);

        if ($gorev === null) {
            return $this->jsonHata('Görev bulunamadı.', 404);
        }

        $degisiklik = $this->degModel->find((int) $gorev['sicil_degisikligi_id']);

        if ($degisiklik === null || ! $this->degisiklikYetkisi($degisiklik)) {
            return $this->jsonHata('Bu kayda erişemezsiniz.', 403);
        }

        $this->gorevModel->notKaydet($id, $this->request->getPost('not'));

        return $this->jsonBasarili('Not kaydedildi.');
    }

    /** Görev sil (soft) — yönetici + onay */
    public function gorevSil(int $id)
    {
        if (! $this->adminMi()) {
            return redirect()->to(site_url('sicil/gorevler'))->with('hata', 'Yalnız yönetici silebilir.');
        }

        $gorev = $this->gorevModel->find($id);

        if ($gorev !== null) {
            $degisiklik = $this->degModel->find((int) $gorev['sicil_degisikligi_id']);

            if ($degisiklik !== null) {
                $this->gorevModel->gorevSil($id);
                $this->degModel->durumTure((int) $degisiklik['id']);
            }
        }

        return redirect()->to(site_url('sicil/gorevler'))->with('basari', 'Görev silindi.');
    }

    // =================================================================
    //  BELGELER
    // =================================================================
    public function belgeYukle()
    {
        $degId   = (int) $this->request->getPost('sicil_degisikligi_id') ?: null;
        $gorevId = (int) $this->request->getPost('gorev_id') ?: null;

        // Yetki: bağlı olduğu değişiklik üzerinden
        if ($gorevId > 0) {
            $gorev = $this->gorevModel->find($gorevId);

            if ($gorev === null) {
                return $this->jsonHata('Görev bulunamadı.', 404);
            }

            $degId = (int) $gorev['sicil_degisikligi_id'];
        }

        $degisiklik = $degId > 0 ? $this->degModel->find($degId) : null;

        if ($degisiklik === null || ! $this->degisiklikYetkisi($degisiklik)) {
            return $this->jsonHata('Bu kayda erişemezsiniz.', 403);
        }

        $dosya = $this->request->getFile('dosya');

        if ($dosya === null || ! $dosya->isValid() || $dosya->hasMoved()) {
            return $this->jsonHata('Dosya seçilmedi veya geçersiz.');
        }

        // Güvenlik: uzantı + boyut
        $izinli = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'xlsx', 'xls', 'doc', 'docx', 'csv', 'txt', 'zip'];
        $uzanti = strtolower($dosya->getExtension());

        if (! in_array($uzanti, $izinli, true)) {
            return $this->jsonHata("Bu dosya türü eklenemez (izinli: " . implode(', ', $izinli) . ').');
        }

        if ($dosya->getSize() > 10 * 1024 * 1024) {
            return $this->jsonHata('Dosya en fazla 10 MB olabilir.');
        }

        $klasor = WRITEPATH . 'uploads/sicil';

        if (! is_dir($klasor)) {
            @mkdir($klasor, 0775, true);
        }

        $saklanan = $dosya->getRandomName();
        $dosya->move($klasor, $saklanan);

        $sonuc = $this->belgeModel->ekle([
            'sicil_degisikligi_id' => $degId > 0 ? $degId : null,
            'gorev_id'             => $gorevId > 0 ? $gorevId : null,
            'dosya_adi'            => $dosya->getClientName(),
            'saklanan'             => $saklanan,
            'boyut'                => $dosya->getSize(),
            'tur'                  => $dosya->getMime(),
        ], $this->ben());

        if (! $sonuc['durum']) {
            @unlink($klasor . '/' . $saklanan);

            return $this->jsonHata($sonuc['hata'] ?? 'Belge kaydedilemedi.');
        }

        return $this->jsonBasarili('Belge eklendi.', ['id' => $sonuc['id']]);
    }

    public function belgeIndir(int $id)
    {
        $belge = $this->belgeModel->bul($id);

        if ($belge === null) {
            return redirect()->back()->with('hata', 'Belge bulunamadı.');
        }

        // Yetki (değişiklik üzerinden)
        $degId = $belge['sicil_degisikligi_id'] ?? null;

        if ($degId !== null) {
            $deg = $this->degModel->find((int) $degId);

            if ($deg === null || ! $this->degisiklikYetkisi($deg)) {
                return redirect()->back()->with('hata', 'Bu belgeye erişemezsiniz.');
            }
        } else {
            $gorev = $this->gorevModel->find((int) $belge['gorev_id']);

            if ($gorev === null) {
                return redirect()->back()->with('hata', 'Belge bulunamadı.');
            }

            $deg = $this->degModel->find((int) $gorev['sicil_degisikligi_id']);

            if ($deg === null || ! $this->degisiklikYetkisi($deg)) {
                return redirect()->back()->with('hata', 'Bu belgeye erişemezsiniz.');
            }
        }

        $yol = WRITEPATH . 'uploads/sicil/' . $belge['saklanan'];

        if (! is_file($yol)) {
            return redirect()->back()->with('hata', 'Belge dosyası bulunamadı.');
        }

        return $this->response->download($yol, null)->setFileName($belge['dosya_adi']);
    }

    public function belgeSil(int $id)
    {
        $belge = $this->belgeModel->bul($id);

        if ($belge === null) {
            return $this->jsonHata('Belge bulunamadı.', 404);
        }

        $degId = $belge['sicil_degisikligi_id'] ?? null;

        if ($degId !== null) {
            $deg = $this->degModel->find((int) $degId);
        } else {
            $gorev = $this->gorevModel->find((int) $belge['gorev_id']);
            $deg   = $gorev !== null ? $this->degModel->find((int) $gorev['sicil_degisikligi_id']) : null;
        }

        if ($deg === null || ! $this->degisiklikYetkisi($deg)) {
            return $this->jsonHata('Bu belgeye erişemezsiniz.', 403);
        }

        $this->belgeModel->belgeSil($id, WRITEPATH . 'uploads/sicil/' . $belge['saklanan']);

        return $this->jsonBasarili('Belge silindi.');
    }

    // =================================================================
    //  YARDIMCILAR
    // =================================================================
    /** Değişiklik kaydına erişim yetkisi */
    protected function degisiklikYetkisi(array $degisiklik): bool
    {
        if ($this->adminMi()) {
            return true;
        }

        $mukellef = (new MukellefModel())->find((int) $degisiklik['mukellef_id']);

        return $mukellef !== null && $this->mukellefeErisebilirMi($mukellef);
    }
}
