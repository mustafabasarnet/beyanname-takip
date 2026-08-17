<?php

namespace App\Controllers;

use App\Models\AylikGiderModel;
use App\Models\GelirVergisiModel;
use App\Models\IndirimKalemModel;
use App\Models\KdvModel;
use App\Models\MusavirModel;
use App\Models\VergiTarifeModel;

/**
 * MALİ MÜŞAVİR GELİR VERGİSİ HESAPLAMA
 *
 * Hasılat Makbuz Takip'ten otomatik gelir; kullanıcı gider ve mahsup
 * kalemlerini girer, sistem GVK md.103 tarifesine göre vergiyi hesaplar.
 *
 * Mali bilgi olduğu için personel erişemez (rota filtresi: admin,musavir).
 */
class GelirVergisi extends BaseController
{
    protected GelirVergisiModel $model;

    public function __construct()
    {
        $this->model = new GelirVergisiModel();
    }

    // -----------------------------------------------------------------
    //  LİSTE — tüm müşavirlerin özeti
    // -----------------------------------------------------------------
    public function index()
    {
        $yil      = $this->yilAl();
        $satirlar = $this->model->toplu($yil, $this->musavirFiltresi());

        return $this->goster('gelir_vergisi/index', [
            'yil'        => $yil,
            'satirlar'   => $satirlar,
            'ozet'       => $this->model->topluOzet($satirlar),
            'tarifeVar'  => (new VergiTarifeModel())->tarifeVarMi($yil),
            'kaynak'     => $this->model->hasilatKaynagi(),
        ], 'Vergi Yükü Hesabı');
    }

    // -----------------------------------------------------------------
    //  TEK MÜŞAVİR — hesap ekranı (gider girişi burada)
    // -----------------------------------------------------------------
    public function detay(int $musavirId)
    {
        if (! $this->musavireErisebilirMi($musavirId)) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Bu müşavire erişemezsiniz.');
        }

        $musavir = (new MusavirModel())->find($musavirId);

        if ($musavir === null) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Mali müşavir bulunamadı.');
        }

        $yil    = $this->yilAl();
        $tarife = new VergiTarifeModel();
        $kalem  = new IndirimKalemModel();

        return $this->goster('gelir_vergisi/detay', [
            'musavir'  => $musavir,
            'yil'      => $yil,
            'h'        => $this->model->hesapla($musavirId, $yil),
            'aylik'    => $this->model->aylikDagilim($musavirId, $yil),
            'dilimler' => $tarife->dilimler($yil),
            'kaynak'   => $this->model->hasilatKaynagi(),
            'kdv'      => (new KdvModel())->cizelge($musavirId, $yil),
            'giderler' => (new AylikGiderModel())->cizelge($musavirId, $yil),
            'kalemler' => [
                'egitim_saglik' => $kalem->listele($musavirId, $yil, 'egitim_saglik'),
                'sigorta'       => $kalem->listele($musavirId, $yil, 'sigorta'),
            ],
        ], 'Vergi Yükü — ' . $musavir['ad_soyad']);
    }

    /** Gider/mahsup kaydını kaydet */
    public function kaydet()
    {
        $mid = (int) $this->request->getPost('musavir_id');
        $yil = (int) ($this->request->getPost('yil') ?: date('Y'));

        if (! $this->musavireErisebilirMi($mid)) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Bu müşavire erişemezsiniz.');
        }

        $this->model->kayitYaz($mid, $yil, $this->formVerisi() + [
            'kaydeden_id' => (int) ($this->aktifKullanici['id'] ?? 0) ?: null,
        ]);

        return redirect()->to(site_url('gelir-vergisi/detay/' . $mid . '?yil=' . $yil))
            ->with('basari', 'Gelir vergisi hesabı kaydedildi.');
    }

    /**
     * AYLIK KDV TABLOSUNU KAYDET
     *
     * 12 ay tek gönderimde yazılır. İki değeri de 0 olan ay kaydedilmez
     * (KdvModel::ayYaz boş satırı siler).
     */
    public function kdvKaydet()
    {
        $mid = (int) $this->request->getPost('musavir_id');
        $yil = (int) ($this->request->getPost('yil') ?: date('Y'));

        if (! $this->musavireErisebilirMi($mid)) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Bu müşavire erişemezsiniz.');
        }

        (new KdvModel())->topluYaz($mid, $yil, $this->kdvFormu());

        return redirect()->to(site_url('gelir-vergisi/detay/' . $mid . '?yil=' . $yil) . '#kdv')
            ->with('basari', $yil . ' yılı KDV tablosu kaydedildi.');
    }

    /** POST'tan 12 aylık KDV dizisini okur */
    protected function kdvFormu(): array
    {
        $ham = $this->request->getPost('kdv');
        $out = [];

        if (! is_array($ham)) {
            return $out;
        }

        for ($ay = 1; $ay <= 12; $ay++) {
            if (! isset($ham[$ay])) {
                continue;
            }

            $out[$ay] = [
                'odenen'      => $this->paraCoz($ham[$ay]['odenen'] ?? null) ?? 0.0,
                'indirilecek' => $this->paraCoz($ham[$ay]['indirilecek'] ?? null) ?? 0.0,
                'aciklama'    => $ham[$ay]['aciklama'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * HESAP KİPİNİ DEĞİŞTİR (yıllık ücret projeksiyonu ↔ kesilen makbuzlar)
     *
     * Diğer alanlara dokunmadan yalnız kipi günceller; kayıt yoksa oluşturur.
     */
    public function kipDegistir()
    {
        $mid = (int) $this->request->getPost('musavir_id');
        $yil = (int) ($this->request->getPost('yil') ?: date('Y'));
        $kip = $this->request->getPost('kip') === 'makbuz' ? 'makbuz' : 'ucret';

        if (! $this->musavireErisebilirMi($mid)) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Bu müşavire erişemezsiniz.');
        }

        $mevcut = $this->model->kayitAl($mid, $yil);

        // Var olan değerleri koru, yalnız kipi değiştir
        $veri = [];

        foreach (array_keys(GelirVergisiModel::BOS_KAYIT) as $alan) {
            $veri[$alan] = $mevcut[$alan] ?? null;
        }

        $veri['hesap_kipi']  = $kip;
        $veri['kaydeden_id'] = (int) ($this->aktifKullanici['id'] ?? 0) ?: null;

        $this->model->kayitYaz($mid, $yil, $veri);

        return redirect()->to(site_url('gelir-vergisi/detay/' . $mid . '?yil=' . $yil))
            ->with('basari', $kip === 'ucret'
                ? 'Yıllık ücret projeksiyonuna geçildi.'
                : 'Kesilen makbuzlar kipine geçildi.');
    }

    /**
     * AYLIK GİDER TABLOSUNU KAYDET
     *
     * 12 ay tek gönderimde yazılır. Tutarı 0 ve açıklaması boş olan ay
     * kaydedilmez (AylikGiderModel::ayYaz boş satırı siler).
     */
    public function giderKaydet()
    {
        $mid = (int) $this->request->getPost('musavir_id');
        $yil = (int) ($this->request->getPost('yil') ?: date('Y'));

        if (! $this->musavireErisebilirMi($mid)) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Bu müşavire erişemezsiniz.');
        }

        $ham = $this->request->getPost('agider');
        $out = [];

        if (is_array($ham)) {
            for ($ay = 1; $ay <= 12; $ay++) {
                if (! isset($ham[$ay])) {
                    continue;
                }

                $out[$ay] = [
                    'tutar'    => $this->paraCoz($ham[$ay]['tutar'] ?? null) ?? 0.0,
                    'aciklama' => $ham[$ay]['aciklama'] ?? null,
                ];
            }
        }

        (new AylikGiderModel())->topluYaz($mid, $yil, $out);

        return redirect()->to(site_url('gelir-vergisi/detay/' . $mid . '?yil=' . $yil) . '#aylik-gider')
            ->with('basari', $yil . ' yılı aylık gider tablosu kaydedildi.');
    }

    // -----------------------------------------------------------------
    //  İNDİRİM KALEMLERİ (eğitim-sağlık / sigorta primi listesi)
    // -----------------------------------------------------------------

    /** Tek kalem ekle veya güncelle */
    public function kalemKaydet()
    {
        $mid   = (int) $this->request->getPost('musavir_id');
        $yil   = (int) ($this->request->getPost('yil') ?: date('Y'));
        $kalem = IndirimKalemModel::kalemGecerli($this->request->getPost('kalem'));

        if (! $this->musavireErisebilirMi($mid)) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Bu müşavire erişemezsiniz.');
        }

        $id    = (int) $this->request->getPost('id');
        $tutar = $this->paraCoz($this->request->getPost('tutar'));
        $tarih = $this->request->getPost('tarih');

        $donus = redirect()->to(site_url('gelir-vergisi/detay/' . $mid . '?yil=' . $yil) . '#' . $kalem);

        if ($tutar === null || $tutar <= 0) {
            return $donus->with('hata', 'Geçerli bir tutar girin.');
        }

        if (empty($tarih) || strtotime($tarih) === false) {
            return $donus->with('hata', 'Geçerli bir tarih girin.');
        }

        $model = new IndirimKalemModel();

        // Düzenlemede kaydın gerçekten bu müşavire ait olduğu doğrulanır
        if ($id > 0) {
            $var = $model->find($id);

            if ($var === null || (int) $var['musavir_id'] !== $mid) {
                return $donus->with('hata', 'Kayıt bulunamadı.');
            }
        }

        $model->kalemKaydet([
            'musavir_id'  => $mid,
            'yil'         => $yil,
            'kalem'       => $kalem,
            'tur'         => $this->request->getPost('tur'),
            'tarih'       => date('Y-m-d', strtotime($tarih)),
            'aciklama'    => $this->request->getPost('aciklama') ?: null,
            'tutar'       => $tutar,
            'kaydeden_id' => (int) ($this->aktifKullanici['id'] ?? 0) ?: null,
        ], $id ?: null);

        return $donus->with('basari', $id > 0 ? 'Kalem güncellendi.' : 'Kalem eklendi.');
    }

    /** Kalem sil */
    public function kalemSil(int $id)
    {
        $model = new IndirimKalemModel();
        $k     = $model->find($id);

        if ($k === null) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Kayıt bulunamadı.');
        }

        if (! $this->musavireErisebilirMi((int) $k['musavir_id'])) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Bu kayda erişemezsiniz.');
        }

        $model->delete($id);

        return redirect()
            ->to(site_url('gelir-vergisi/detay/' . $k['musavir_id'] . '?yil=' . $k['yil']) . '#' . $k['kalem'])
            ->with('basari', 'Kalem silindi.');
    }

    /** Bir yılın kalemlerini başka yıla kopyala */
    public function kalemKopyala()
    {
        $mid    = (int) $this->request->getPost('musavir_id');
        $kaynak = (int) $this->request->getPost('kaynak_yil');
        $hedef  = (int) $this->request->getPost('hedef_yil');
        $kalem  = IndirimKalemModel::kalemGecerli($this->request->getPost('kalem'));

        if (! $this->musavireErisebilirMi($mid)) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Bu müşavire erişemezsiniz.');
        }

        $donus = redirect()->to(site_url('gelir-vergisi/detay/' . $mid . '?yil=' . $hedef) . '#' . $kalem);

        if ($kaynak <= 0 || $hedef <= 0 || $kaynak === $hedef) {
            return $donus->with('hata', 'Kaynak ve hedef yıl farklı olmalıdır.');
        }

        $adet = (new IndirimKalemModel())->yilKopyala($mid, $kaynak, $hedef, $kalem);

        return $donus->with(
            $adet > 0 ? 'basari' : 'hata',
            $adet > 0
                ? sprintf('%d kalem %d yılından %d yılına kopyalandı.', $adet, $kaynak, $hedef)
                : $kaynak . ' yılında kopyalanacak kalem bulunamadı.'
        );
    }

    /**
     * AJAX: kaydetmeden canlı hesap.
     * Kullanıcı gider kutusuna yazdıkça sonuç anında güncellenir.
     */
    public function hesapla()
    {
        $mid = (int) $this->request->getPost('musavir_id');
        $yil = (int) ($this->request->getPost('yil') ?: date('Y'));

        if (! $this->musavireErisebilirMi($mid)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Bu müşavire erişemezsiniz.']);
        }

        $h = $this->model->hesapla($mid, $yil, $this->formVerisi());

        // Ekranda gösterilecek biçimli değerler
        $bicimli = [];

        foreach ([
            'hasilat', 'gider', 'kazanc', 'bagkur', 'indirim_toplam', 'matrah', 'vergi',
            'uyumlu', 'odenmesi_gereken', 'stopaj', 'diger_mahsup',
            'mahsup_toplam', 'odenecek', 'iade', 'sonuc',
            'sigorta', 'egitim', 'sigorta_tavan', 'egitim_tavan',
            'sigorta_asim', 'egitim_asim', 'kdv',
            'gider_elle', 'gider_aylik', 'gv_alacak', 'gv_borc', 'vergi_yuku',
            'kdv_yukumluluk', 'kdv_odenen', 'kdv_kalan', 'kdv_borc', 'kdv_alacak',
            'ucret_brut', 'ucret_stopaj', 'ucret_kdv', 'indirim_taban',
        ] as $a) {
            $bicimli[$a] = number_format((float) $h[$a], 2, ',', '.');
        }

        return $this->response->setJSON([
            'durum'      => true,
            'hesap'      => $h,
            'bicimli'    => $bicimli,
            'dilim_no'   => $h['dilim_no'],
            'dilim_oran' => $h['dilim'] === null ? 0 : (float) $h['dilim']['oran'],
            'ort_oran'   => $h['ortalama_oran'],
            'tarife_var' => $h['tarife_var'],
        ]);
    }

    /** POST'tan gider/mahsup alanlarını okur */
    protected function formVerisi(): array
    {
        $v = [];

        foreach ([
            'gider', 'bagkur', 'sigorta_primi', 'egitim_saglik', 'diger_mahsup',
        ] as $a) {
            $v[$a] = $this->paraCoz($this->request->getPost($a)) ?? 0.0;
        }

        // Boş bırakılırsa null kalır → makbuzlardan gelen değer kullanılır
        $v['stopaj_elle']  = $this->paraCoz($this->request->getPost('stopaj_elle'));
        $v['hasilat_elle'] = $this->paraCoz($this->request->getPost('hasilat_elle'));

        $v['hesap_kipi']     = $this->request->getPost('hesap_kipi') === 'makbuz' ? 'makbuz' : 'ucret';
        $v['uyumlu_indirim'] = $this->request->getPost('uyumlu_indirim') ? 1 : 0;
        $v['aciklama']       = $this->request->getPost('aciklama') ?: null;

        return $v;
    }

    // -----------------------------------------------------------------
    //  YAZDIRMA
    // -----------------------------------------------------------------
    public function yazdir(int $musavirId)
    {
        if (! $this->musavireErisebilirMi($musavirId)) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Bu müşavire erişemezsiniz.');
        }

        $musavir = (new MusavirModel())->find($musavirId);

        if ($musavir === null) {
            return redirect()->to(site_url('gelir-vergisi'))->with('hata', 'Mali müşavir bulunamadı.');
        }

        $yil = $this->yilAl();

        return view('gelir_vergisi/yazdir', [
            'musavir'  => $musavir,
            'yil'      => $yil,
            'h'        => $this->model->hesapla($musavirId, $yil),
            'aylik'    => $this->model->aylikDagilim($musavirId, $yil),
            'dilimler' => (new VergiTarifeModel())->dilimler($yil),
            'kaynak'   => $this->model->hasilatKaynagi(),
            'kdv'      => (new KdvModel())->cizelge($musavirId, $yil),
            'giderler' => (new AylikGiderModel())->cizelge($musavirId, $yil),
            'kalemler' => [
                'egitim_saglik' => (new IndirimKalemModel())->listele($musavirId, $yil, 'egitim_saglik'),
                'sigorta'       => (new IndirimKalemModel())->listele($musavirId, $yil, 'sigorta'),
            ],
        ]);
    }

    /** Liste yazdırma (tüm müşavirler) */
    public function listeYazdir()
    {
        $yil      = $this->yilAl();
        $satirlar = $this->model->toplu($yil, $this->musavirFiltresi());

        return view('gelir_vergisi/liste_yazdir', [
            'yil'      => $yil,
            'satirlar' => $satirlar,
            'ozet'     => $this->model->topluOzet($satirlar),
            'kaynak'   => $this->model->hasilatKaynagi(),
        ]);
    }

    // -----------------------------------------------------------------
    //  TARİFE YÖNETİMİ (yıl bazında dilimler)
    // -----------------------------------------------------------------
    public function tarife()
    {
        $tarife = new VergiTarifeModel();
        $yil    = $this->yilAl();

        return $this->goster('gelir_vergisi/tarife', [
            'yil'          => $yil,
            'ucretDisi'    => $tarife->dilimler($yil, false),
            'ucret'        => $tarife->dilimler($yil, true),
            'tanimliYillar' => $tarife->tanimliYillar(),
        ], 'Gelir Vergisi Tarifesi');
    }

    public function tarifeKaydet()
    {
        if (! $this->adminMi()) {
            return redirect()->to(site_url('gelir-vergisi/tarife'))
                ->with('hata', 'Tarife düzenlemek için yönetici olmalısınız.');
        }

        $yil   = (int) ($this->request->getPost('yil') ?: date('Y'));
        $tip   = $this->request->getPost('tip') === 'ucret' ? 'ucret' : 'ucret_disi';
        $ham   = $this->request->getPost('dilim');
        $satir = [];

        if (is_array($ham)) {
            foreach ($ham as $d) {
                $satir[] = [
                    'taban'       => $this->paraCoz($d['taban'] ?? null) ?? 0,
                    'tavan'       => $this->paraCoz($d['tavan'] ?? null),
                    'sabit_vergi' => $this->paraCoz($d['sabit_vergi'] ?? null) ?? 0,
                    'oran'        => (float) str_replace(',', '.', (string) ($d['oran'] ?? 0)),
                ];
            }
        }

        $adet = (new VergiTarifeModel())->dilimleriYaz($yil, $tip === 'ucret', $satir);

        return redirect()->to(site_url('gelir-vergisi/tarife?yil=' . $yil))
            ->with('basari', sprintf('%d yılı %s tarifesi kaydedildi (%d dilim).',
                $yil, $tip === 'ucret' ? 'ücret' : 'ücret dışı', $adet));
    }

    public function tarifeKopyala()
    {
        if (! $this->adminMi()) {
            return redirect()->to(site_url('gelir-vergisi/tarife'))
                ->with('hata', 'Tarife düzenlemek için yönetici olmalısınız.');
        }

        $kaynak = (int) $this->request->getPost('kaynak_yil');
        $hedef  = (int) $this->request->getPost('hedef_yil');
        $oran   = (float) str_replace(',', '.', (string) $this->request->getPost('oran'));

        if ($kaynak <= 0 || $hedef <= 0 || $kaynak === $hedef) {
            return redirect()->back()->with('hata', 'Kaynak ve hedef yıl farklı olmalıdır.');
        }

        $s = (new VergiTarifeModel())->tarifeKopyala($kaynak, $hedef, $oran);

        if ($s['atlanan']) {
            return redirect()->to(site_url('gelir-vergisi/tarife?yil=' . $hedef))
                ->with('hata', $hedef . ' yılında zaten tarife tanımlı, dokunulmadı. '
                    . 'Değiştirmek için dilimleri elle düzenleyin.');
        }

        return redirect()->to(site_url('gelir-vergisi/tarife?yil=' . $hedef))
            ->with('basari', sprintf('%d tarifesi %d yılına kopyalandı (%d dilim%s). '
                . 'Resmi tebliğ yayımlandığında tutarları elle düzeltin.',
                $kaynak, $hedef, $s['eklenen'], $oran != 0 ? ', %' . $oran . ' artışla' : ''));
    }

    // -----------------------------------------------------------------
    //  YARDIMCILAR
    // -----------------------------------------------------------------
    protected function yilAl(): int
    {
        $y = (int) ($this->request->getGet('yil') ?? 0);

        return $y > 1990 && $y < 2200 ? $y : (int) date('Y');
    }

    /**
     * Para metnini sayıya çevirir.
     *
     * Ortak trParaCoz() yardımcısına devreder — eski yerel sürüm "400.000"
     * gibi virgülsüz binlikli girdiyi 400,00 olarak okuyordu.
     */
    protected function paraCoz($ham): ?float
    {
        helper('beyanname');

        return trParaCoz($ham);
    }
}
