<?php

namespace App\Controllers;

use App\Models\AyarModel;
use App\Models\BeyannameTakipModel;
use App\Models\BeyannameTuruModel;
use App\Models\DamgaTutarModel;
use App\Models\EdefterAdimModel;
use App\Models\EvrakTuruModel;
use App\Models\TatilModel;

class Tanimlar extends BaseController
{
    // ============ BEYANNAME TÜRLERİ ============
    public function beyannameTurleri()
    {
        return $this->goster('tanimlar/beyanname_turleri', [
            'turler' => (new BeyannameTuruModel())->orderBy('sira', 'ASC')->findAll(),
        ], 'Beyanname Türleri');
    }

    public function beyannameTuruKaydet()
    {
        $model = new BeyannameTuruModel();
        $id    = (int) $this->request->getPost('id');

        $veri = [
            'kod'               => strtoupper(trim((string) $this->request->getPost('kod'))),
            'ad'                => $this->request->getPost('ad'),
            'kisa_ad'           => $this->request->getPost('kisa_ad'),
            'periyot'           => $this->request->getPost('periyot'),
            'son_gun_offset_ay' => (int) $this->request->getPost('son_gun_offset_ay'),
            'son_gun_tipi'      => $this->request->getPost('son_gun_tipi'),
            'son_gun'           => (int) ($this->request->getPost('son_gun') ?: 28),
            'atlanan_donemler'  => $this->request->getPost('atlanan_donemler') ?: null,
            'celisen_kodlar'    => $this->request->getPost('celisen_kodlar') ?: null,
            'mukellef_tipi'     => $this->request->getPost('mukellef_tipi') ?: 'hepsi',
            'renk'              => $this->request->getPost('renk') ?: '#64748b',
            'aciklama'          => $this->request->getPost('aciklama'),
            'sira'              => (int) $this->request->getPost('sira'),
            'aktif'             => (int) ($this->request->getPost('aktif') ?? 1),
        ];

        if ($id > 0) {
            // Güncelleme: benzersizlik kontrolünden düzenlenen kaydı hariç tut
            if (! $this->validate($model->kurallariGuncelle($id), $model->kurallarMesajlari())) {
                return redirect()->back()->withInput()->with('hatalar', $this->validator->getErrors());
            }

            $ok = $model->skipValidation(true)->update($id, $veri);
        } else {
            $ok = $model->skipValidation(false)->insert($veri);
        }

        if (! $ok) {
            return redirect()->back()->withInput()->with('hatalar', $model->errors());
        }

        return redirect()->to(site_url('tanimlar/beyanname-turleri'))
            ->with('basari', 'Beyanname türü kaydedildi. Değişikliğin çizelgeye yansıması için dönemleri yeniden üretin.');
    }

    public function beyannameTuruSil(int $id)
    {
        $kullanim = (new BeyannameTakipModel())->where('beyanname_turu_id', $id)->countAllResults();

        if ($kullanim > 0) {
            // Silmek yerine pasifleştir (veri kaybı olmasın)
            (new BeyannameTuruModel())->update($id, ['aktif' => 0]);

            return redirect()->to(site_url('tanimlar/beyanname-turleri'))
                ->with('basari', 'Bu türe ait kayıtlar olduğu için tür silinmedi, pasife alındı.');
        }

        (new BeyannameTuruModel())->delete($id);

        return redirect()->to(site_url('tanimlar/beyanname-turleri'))->with('basari', 'Beyanname türü silindi.');
    }


    // ============ DAMGA VERGİSİ TUTARLARI ============
    public function damga()
    {
        $yil   = (int) ($this->request->getGet('yil') ?? date('Y'));
        $model = new DamgaTutarModel();

        return $this->goster('tanimlar/damga', [
            'liste'  => $model->yilListesi($yil),
            'yil'    => $yil,
            'yillar' => $model->yillar(),
        ], 'Damga Vergisi Tutarları');
    }

    public function damgaKaydet()
    {
        $yil      = (int) $this->request->getPost('yil');
        $tutarlar = $this->request->getPost('tutar') ?? [];

        if ($yil < 2000 || $yil > 2100) {
            return redirect()->back()->with('hata', 'Geçerli bir yıl giriniz.');
        }

        $adet = (new DamgaTutarModel())->yilKaydet($yil, $tutarlar);

        return redirect()->to(site_url('tanimlar/damga?yil=' . $yil))
            ->with('basari', $adet . ' beyanname türü için damga tutarı kaydedildi.');
    }

    public function damgaKopyala()
    {
        $kaynak = (int) $this->request->getPost('kaynak_yil');
        $hedef  = (int) $this->request->getPost('hedef_yil');

        if ($kaynak === $hedef || $kaynak < 2000 || $hedef < 2000) {
            return redirect()->back()->with('hata', 'Geçerli kaynak ve hedef yıl seçiniz.');
        }

        $adet = (new DamgaTutarModel())->yilKopyala($kaynak, $hedef);

        return redirect()->to(site_url('tanimlar/damga?yil=' . $hedef))
            ->with('basari', $kaynak . ' yılından ' . $hedef . ' yılına ' . $adet . ' kayıt kopyalandı.');
    }

    // ============ EVRAK TÜRLERİ ============
    public function evrakTurleri()
    {
        return $this->goster('tanimlar/evrak_turleri', [
            'turler' => (new EvrakTuruModel())->orderBy('sira', 'ASC')->findAll(),
        ], 'Evrak Türleri');
    }

    public function evrakTuruKaydet()
    {
        $model = new EvrakTuruModel();
        $id    = (int) $this->request->getPost('id');

        $veri = [
            'ad'      => $this->request->getPost('ad'),
            'kisa_ad' => $this->request->getPost('kisa_ad'),
            'sira'    => (int) $this->request->getPost('sira'),
            'aktif'   => (int) ($this->request->getPost('aktif') ?? 1),
        ];

        // Evrak türünde benzersizlik kuralı yok; doğrulama her iki durumda da aynı
        $model->skipValidation(false);
        $ok = $id > 0 ? $model->update($id, $veri) : $model->insert($veri);

        if (! $ok) {
            return redirect()->back()->withInput()->with('hatalar', $model->errors());
        }

        return redirect()->to(site_url('tanimlar/evrak-turleri'))->with('basari', 'Evrak türü kaydedildi.');
    }

    public function evrakTuruSil(int $id)
    {
        (new EvrakTuruModel())->update($id, ['aktif' => 0]);

        return redirect()->to(site_url('tanimlar/evrak-turleri'))->with('basari', 'Evrak türü pasife alındı.');
    }

    // ============ E-DEFTER ADIMLARI ============
    public function edefterAdimlari()
    {
        return $this->goster('tanimlar/edefter_adimlari', [
            'adimlar' => (new EdefterAdimModel())->tumu(),
            'sonraki' => (new EdefterAdimModel())->sonrakiSira(),
        ], 'E-Defter Takip Adımları');
    }

    public function edefterAdimKaydet()
    {
        $model = new EdefterAdimModel();
        $id    = (int) $this->request->getPost('id');

        $veri = [
            'ad'       => trim((string) $this->request->getPost('ad')),
            'ikon'     => trim((string) $this->request->getPost('ikon')) ?: null,
            'aciklama' => trim((string) $this->request->getPost('aciklama')) ?: null,
            'sira'     => (int) $this->request->getPost('sira'),
            'aktif'    => (int) ($this->request->getPost('aktif') ?? 0),
        ];

        if ($id > 0) {
            // Kod DEĞİŞTİRİLEMEZ: HAZIR/ONAY kodları durum hesabında kullanılır,
            // değişirse iş akışı sessizce bozulurdu.
            $ok = $model->update($id, $veri);
        } else {
            $kod = strtoupper(trim((string) $this->request->getPost('kod')));
            $kod = preg_replace('/[^A-Z0-9_]/', '_', $this->trBuyuk($kod));

            if ($kod === '' || $kod === null) {
                return redirect()->back()->withInput()->with('hata', 'Adım kodu zorunludur.');
            }

            if ($model->where('kod', $kod)->first() !== null) {
                return redirect()->back()->withInput()->with('hata', 'Bu kod zaten kullanılıyor: ' . $kod);
            }

            $ok = $model->insert($veri + ['kod' => $kod]);
        }

        if (! $ok) {
            return redirect()->back()->withInput()->with('hatalar', $model->errors());
        }

        return redirect()->to(site_url('tanimlar/edefter-adimlari'))
            ->with('basari', 'Adım kaydedildi.');
    }

    public function edefterAdimSil(int $id)
    {
        $model = new EdefterAdimModel();
        $adim  = $model->find($id);

        if ($adim === null) {
            return redirect()->to(site_url('tanimlar/edefter-adimlari'))->with('hata', 'Adım bulunamadı.');
        }

        // HAZIR ve ONAY adımları durum hesabının çekirdeği; silinemez,
        // yalnızca pasife alınabilir (o da uyarıyla).
        if (in_array($adim['kod'], [EdefterAdimModel::HAZIR_KODU, EdefterAdimModel::ONAY_KODU], true)) {
            return redirect()->to(site_url('tanimlar/edefter-adimlari'))
                ->with('hata', $adim['ad'] . ' adımı iş akışının temelidir, kaldırılamaz.');
        }

        $model->update($id, ['aktif' => 0]);

        return redirect()->to(site_url('tanimlar/edefter-adimlari'))
            ->with('basari', 'Adım pasife alındı. İşaretlenmiş geçmiş veriler korunur.');
    }

    /** Türkçe büyük harf (i → İ) */
    protected function trBuyuk(string $m): string
    {
        return mb_strtoupper(str_replace(['i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'],
            ['I', 'I', 'G', 'U', 'S', 'O', 'C'], $m), 'UTF-8');
    }

    // ============ TATİLLER ============
    public function tatiller()
    {
        $yil   = (int) ($this->request->getGet('yil') ?? date('Y'));
        $model = new TatilModel();

        return $this->goster('tanimlar/tatiller', [
            'tatiller' => $model->yilaGore($yil),
            'yil'      => $yil,
            'yillar'   => $model->yillar(),
        ], 'Resmi Tatiller');
    }

    public function tatilKaydet()
    {
        $model = new TatilModel();
        $id    = (int) $this->request->getPost('id');

        $veri = [
            'tarih'     => $this->request->getPost('tarih'),
            'ad'        => $this->request->getPost('ad'),
            'tip'       => $this->request->getPost('tip'),
            'yarim_gun' => (int) ($this->request->getPost('yarim_gun') ?? 0),
            'aktif'     => (int) ($this->request->getPost('aktif') ?? 1),
        ];

        if ($id > 0) {
            // Güncelleme: aynı tarihli diğer kayıtlara bakılır, kendisi hariç tutulur
            if (! $this->validate($model->kurallariGuncelle($id), $model->kurallarMesajlari())) {
                return redirect()->back()->withInput()->with('hatalar', $this->validator->getErrors());
            }

            $ok = $model->skipValidation(true)->update($id, $veri);
        } else {
            $ok = $model->skipValidation(false)->insert($veri);
        }

        if (! $ok) {
            return redirect()->back()->withInput()->with('hatalar', $model->errors());
        }

        $yil = (int) date('Y', strtotime((string) $veri['tarih']));

        return redirect()->to(site_url('tanimlar/tatiller?yil=' . $yil))
            ->with('basari', 'Tatil kaydedildi. Son tarihlerin güncellenmesi için "Toplu Dönem Üretimi" çalıştırın.');
    }

    public function tatilSil(int $id)
    {
        $model = new TatilModel();
        $tatil = $model->find($id);
        $yil   = $tatil ? (int) date('Y', strtotime((string) $tatil['tarih'])) : (int) date('Y');

        $model->delete($id);

        return redirect()->to(site_url('tanimlar/tatiller?yil=' . $yil))->with('basari', 'Tatil silindi.');
    }

    // ============ AYARLAR ============
    public function ayarlar()
    {
        return $this->goster('tanimlar/ayarlar', [
            'ayarlar' => (new AyarModel())->findAll(),
        ], 'Sistem Ayarları');
    }

    public function ayarlarKaydet()
    {
        $model  = new AyarModel();
        $post   = $this->request->getPost('ayar') ?? [];
        $tanim  = ayarTanimlari();

        foreach ($post as $anahtar => $deger) {
            $anahtar = (string) $anahtar;
            $deger   = (string) $deger;

            /*
             * TUTAR ALANLARI
             *
             * Ekranda binlik ayırıcıyla gösterilir (12.000.000). Doğrudan
             * kaydedilseydi veritabanına "12.000.000" yazılır, sayıya
             * çevrilirken 12'ye düşerdi. trParaCoz() Türkçe biçimi çözer.
             */
            if (($tanim[$anahtar]['tip'] ?? '') === 'para') {
                $sayi = trParaCoz($deger);

                if ($sayi !== null) {
                    // Tam sayıysa ondalık yazma (12000000, 12000000.00 değil)
                    $deger = (string) ($sayi == (int) $sayi ? (int) $sayi : $sayi);
                }
            }

            $model->yaz($anahtar, $deger);
        }

        // Checkbox'lar işaretli değilken tarayıcı hiç göndermez;
        // bu yüzden gelmeyenlere elle 0 yazılır. Yeni bir onay kutusu
        // eklerken anahtarını BURAYA DA eklemeyi unutmayın.
        $onayKutulari = [
            'cumartesi_tatil',
            'pazar_tatil',
            'arife_tatil_sayilsin',
            'mali_tatil_uygula',
            'otomatik_donem_uret',
            'damga_otomatik_ekle',
            'bildirim_ucret_varsayilan',
            'edefter_otomatik_uret',
        ];

        foreach ($onayKutulari as $k) {
            if (! isset($post[$k])) {
                $model->yaz($k, '0');
            }
        }

        return redirect()->to(site_url('tanimlar/ayarlar'))
            ->with('basari', 'Ayarlar kaydedildi. Tarih kurallarını değiştirdiyseniz dönemleri yeniden üretin.');
    }
}
