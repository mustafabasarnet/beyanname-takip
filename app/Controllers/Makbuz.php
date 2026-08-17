<?php

namespace App\Controllers;

use App\Libraries\MakbuzIceAktar;
use App\Models\MakbuzModel;
use App\Models\MukellefModel;
use App\Models\MusavirModel;

/**
 * SERBEST MESLEK MAKBUZU TAKİBİ
 *
 * Yıllık sözleşme ücreti (hedef) ile kesilen makbuzları karşılaştırır:
 * hangi mükellefe ne kadar kesildi, ne kadar kaldı.
 *
 * Mali bilgi olduğu için personel erişemez (rota filtresi: admin,musavir).
 */
class Makbuz extends BaseController
{
    protected MakbuzModel $model;

    public const SAYFA_ADETLERI = [25, 50, 100, 250];

    public const VARSAYILAN_ADET = 50;

    public function __construct()
    {
        $this->model = new MakbuzModel();
    }

    // -----------------------------------------------------------------
    //  LİSTE
    // -----------------------------------------------------------------
    public function index()
    {
        $filtre = $this->filtreAl();
        $adet   = $this->adetBelirle();

        $sayfaFiltre          = $filtre;
        $sayfaFiltre['limit'] = $adet;
        $sayfaFiltre['ofset'] = 0;

        $toplam = $this->model->cizelgeSayisi($filtre);

        return $this->goster('makbuz/index', [
            'kayitlar'    => $this->model->cizelge($sayfaFiltre),
            'filtre'      => $filtre,
            'ozet'        => $this->model->ozet($filtre),
            'musavirOzet' => $this->model->musavirOzeti((int) $filtre['yil'], $this->musavirFiltresi()),
            'musavirler'  => $this->secilebilirMusavirler(),
            'durumlar'    => self::DURUMLAR,
            'toplamKayit' => $toplam,
            'sayfaAdedi'  => $adet,
            'adetSecenek' => self::SAYFA_ADETLERI,
            'dahaVar'     => $toplam > $adet,
            'stopajOran'  => $this->model->stopajOrani(),
            'kdvOran'     => $this->model->kdvOrani(),
        ], 'Makbuz Takip');
    }

    public const DURUMLAR = [
        'UCRETSIZ'  => 'Ücreti girilmemiş',
        'BASLAMADI' => 'Hiç kesilmemiş',
        'DEVAM'     => 'Kısmen kesilmiş',
        'TAMAM'     => 'Tamamlanmış (aşanlar dahil)',
        'ASIM'      => 'Ücreti aşmış',
    ];

    /** AJAX: sonsuz kaydırma */
    public function dahaFazla()
    {
        $filtre = $this->filtreAl();
        $adet   = $this->adetBelirle();
        $ofset  = max(0, (int) $this->request->getGet('ofset'));

        $sayfaFiltre          = $filtre;
        $sayfaFiltre['limit'] = $adet;
        $sayfaFiltre['ofset'] = $ofset;

        $kayitlar = $this->model->cizelge($sayfaFiltre);
        $toplam   = $this->model->cizelgeSayisi($filtre);

        $html = view('makbuz/_satirlar', [
            'kayitlar' => $kayitlar,
            'filtre'   => $filtre,
        ]);

        return $this->response->setJSON([
            'durum'   => true,
            'html'    => $html,
            'adet'    => count($kayitlar),
            'ofset'   => $ofset + count($kayitlar),
            'toplam'  => $toplam,
            'dahaVar' => ($ofset + count($kayitlar)) < $toplam,
        ]);
    }

    /** Bir mükellefin makbuz dökümü */
    public function detay(int $mukellefId)
    {
        $yil      = (int) ($this->request->getGet('yil') ?? date('Y'));
        $mukellef = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null) {
            return redirect()->to(site_url('makbuz'))->with('hata', 'Mükellef bulunamadı.');
        }

        if (! $this->musavireErisebilirMi((int) $mukellef['musavir_id'])) {
            return redirect()->to(site_url('makbuz'))->with('hata', 'Bu mükellefe erişemezsiniz.');
        }

        $ucret    = $this->model->ucretAl($mukellefId, $yil);
        $makbuzlar = $this->model->mukellefMakbuzlari($mukellefId, $yil);
        $kesilen  = 0.0;

        foreach ($makbuzlar as $m) {
            $kesilen += (float) $m['brut'];
        }

        return $this->goster('makbuz/detay', [
            'mukellef'   => $mukellef,
            'yil'        => $yil,
            'ucret'      => $ucret,
            'makbuzlar'  => $makbuzlar,
            'kesilen'    => $kesilen,
            'kalan'      => round($ucret - $kesilen, 2),
            'musavirler' => $this->secilebilirMusavirler(),
            'stopajOran' => $this->model->stopajOrani(),
            'kdvOran'    => $this->model->kdvOrani(),
        ], 'Makbuz Dökümü — ' . $mukellef['unvan']);
    }

    // -----------------------------------------------------------------
    //  MAKBUZ KAYDI
    // -----------------------------------------------------------------
    public function kaydet()
    {
        $id  = (int) $this->request->getPost('id');
        $mid = (int) $this->request->getPost('mukellef_id');

        if (! $this->mukellefErisilebilirMi($mid)) {
            return redirect()->back()->with('hata', 'Bu mükellefe erişemezsiniz.');
        }

        $veri = [
            'mukellef_id'   => $mid,
            'musavir_id'    => $this->request->getPost('musavir_id') ?: null,
            'yil'           => (int) ($this->request->getPost('yil') ?: date('Y')),
            'makbuz_no'     => $this->request->getPost('makbuz_no') ?: null,
            'tarih'         => $this->request->getPost('tarih'),
            'brut'          => $this->paraCoz($this->request->getPost('brut')),
            'stopaj'        => $this->paraCoz($this->request->getPost('stopaj')),
            'kdv'           => $this->paraCoz($this->request->getPost('kdv')),
            'tahsil_edildi' => (int) ($this->request->getPost('tahsil_edildi') ?? 0),
            'tahsil_tarihi' => $this->request->getPost('tahsil_tarihi') ?: null,
            'aciklama'      => $this->request->getPost('aciklama') ?: null,
            'kaydeden_id'   => (int) ($this->aktifKullanici['id'] ?? 0) ?: null,
        ];

        // Müşavir seçilmemişse mükellefin portföy sahibi varsayılır
        if (empty($veri['musavir_id'])) {
            $muk = (new MukellefModel())->find($mid);
            $veri['musavir_id'] = $muk['musavir_id'] ?? null;
        }

        if ($this->model->makbuzKaydet($veri, $id ?: null) === false) {
            return redirect()->back()->withInput()->with('hatalar', $this->model->errors());
        }

        return redirect()->to(site_url('makbuz/detay/' . $mid . '?yil=' . $veri['yil']))
            ->with('basari', 'Makbuz kaydedildi.');
    }

    public function sil(int $id)
    {
        $m = $this->model->find($id);

        if ($m === null) {
            return redirect()->to(site_url('makbuz'))->with('hata', 'Makbuz bulunamadı.');
        }

        if (! $this->mukellefErisilebilirMi((int) $m['mukellef_id'])) {
            return redirect()->to(site_url('makbuz'))->with('hata', 'Bu kayda erişemezsiniz.');
        }

        $this->model->delete($id);

        return redirect()->to(site_url('makbuz/detay/' . $m['mukellef_id'] . '?yil=' . $m['yil']))
            ->with('basari', 'Makbuz silindi.');
    }

    /** AJAX: tahsil edildi işareti */
    public function tahsil()
    {
        $id = (int) $this->request->getPost('id');
        $m  = $this->model->find($id);

        if ($m === null || ! $this->mukellefErisilebilirMi((int) $m['mukellef_id'])) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Kayıt bulunamadı.']);
        }

        $edildi = $this->request->getPost('tahsil') === '1';

        $this->model->update($id, [
            'tahsil_edildi' => $edildi ? 1 : 0,
            'tahsil_tarihi' => $edildi ? date('Y-m-d') : null,
        ]);

        return $this->response->setJSON([
            'durum' => true,
            'mesaj' => $edildi ? 'Tahsil edildi olarak işaretlendi.' : 'Tahsil işareti kaldırıldı.',
        ]);
    }

    // -----------------------------------------------------------------
    //  YILLIK ÜCRET
    // -----------------------------------------------------------------

    /** AJAX: tek mükellefin yıllık ücretini kaydet */
    public function ucret()
    {
        $mid = (int) $this->request->getPost('mukellef_id');
        $yil = (int) ($this->request->getPost('yil') ?: date('Y'));

        if (! $this->mukellefErisilebilirMi($mid)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Bu mükellefe erişemezsiniz.']);
        }

        $tutar = $this->paraCoz($this->request->getPost('tutar'));

        if ($tutar === null) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Tutar okunamadı.']);
        }

        $this->model->ucretYaz($mid, $yil, $tutar, $this->request->getPost('aciklama') ?: null);
        $kesilen = 0.0;

        foreach ($this->model->mukellefMakbuzlari($mid, $yil) as $m) {
            $kesilen += (float) $m['brut'];
        }

        return $this->response->setJSON([
            'durum'      => true,
            'mesaj'      => 'Yıllık ücret kaydedildi.',
            'tutar'      => $tutar,
            'tutar_f'    => number_format($tutar, 2, ',', '.'),
            'kalan'      => round($tutar - $kesilen, 2),
            'kalan_f'    => number_format($tutar - $kesilen, 2, ',', '.'),
            'oran'       => $tutar > 0 ? min(100, (int) round($kesilen / $tutar * 100)) : 0,
        ]);
    }

    /** Bir yılın ücretlerini başka yıla kopyala */
    public function ucretKopyala()
    {
        $kaynak = (int) $this->request->getPost('kaynak_yil');
        $hedef  = (int) $this->request->getPost('hedef_yil');
        $zam    = (float) str_replace(',', '.', (string) $this->request->getPost('zam'));

        if ($kaynak <= 0 || $hedef <= 0 || $kaynak === $hedef) {
            return redirect()->back()->with('hata', 'Kaynak ve hedef yıl farklı olmalıdır.');
        }

        $s = $this->model->ucretKopyala($kaynak, $hedef, $zam, $this->musavirFiltresi());

        return redirect()->to(site_url('makbuz?yil=' . $hedef))->with('basari', sprintf(
            '%d mükellefin ücreti %d yılından %d yılına kopyalandı%s. %d mükellefte zaten kayıt vardı, dokunulmadı.',
            $s['eklenen'], $kaynak, $hedef, $zam != 0 ? ' (%' . $zam . ' zam ile)' : '', $s['atlanan']
        ));
    }

    // -----------------------------------------------------------------
    //  EXCEL İÇE AKTARMA
    // -----------------------------------------------------------------
    public function iceAktar()
    {
        $kip = $this->request->getGet('kip') === 'makbuz' ? 'makbuz' : 'ucret';

        return $this->goster('makbuz/ice_aktar', [
            'kip'        => $kip,
            'yil'        => (int) ($this->request->getGet('yil') ?? date('Y')),
            'musavirler' => $this->secilebilirMusavirler(),
        ], 'Excel\'den İçe Aktar');
    }

    /** Şablon indir */
    public function sablon()
    {
        $kip = $this->request->getGet('kip') === 'makbuz' ? 'makbuz' : 'ucret';
        $yil = (int) ($this->request->getGet('yil') ?? date('Y'));
        $lib = new MakbuzIceAktar();

        $icerik = $kip === 'makbuz'
            ? $lib->makbuzSablonu($yil, (int) $this->request->getGet('ay'))
            : $lib->ucretSablonu($yil);

        $ad = $kip === 'makbuz' ? "makbuz_sablon_{$yil}.csv" : "ucret_sablon_{$yil}.csv";

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $ad . '"')
            ->setBody($icerik);
    }

    /** Yüklenen dosyayı çözümle ve önizleme göster */
    public function onizle()
    {
        $kip = $this->request->getPost('kip') === 'makbuz' ? 'makbuz' : 'ucret';
        $yil = (int) ($this->request->getPost('yil') ?: date('Y'));

        $dosya = $this->request->getFile('dosya');

        if ($dosya === null || ! $dosya->isValid()) {
            return redirect()->to(site_url('makbuz/ice-aktar?kip=' . $kip))
                ->with('hata', 'Dosya yüklenemedi: ' . ($dosya ? $dosya->getErrorString() : 'dosya seçilmedi'));
        }

        $uzanti = strtolower($dosya->getClientExtension());

        if (! in_array($uzanti, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            return redirect()->to(site_url('makbuz/ice-aktar?kip=' . $kip))
                ->with('hata', 'Yalnızca CSV dosyası yükleyebilirsiniz (.csv).');
        }

        $lib    = new MakbuzIceAktar();
        $kapsam = $this->adminMi() ? null : $this->tekMusavirId();
        $sonuc  = $lib->cozumle($dosya->getTempName(), $kip, $yil, $kapsam);

        if (! $sonuc['durum']) {
            return redirect()->to(site_url('makbuz/ice-aktar?kip=' . $kip))
                ->with('hata', $sonuc['mesaj']);
        }

        // Önizleme oturumda saklanır; onayda buradan okunur
        session()->set('makbuz_ice_aktar', $sonuc);

        return $this->goster('makbuz/onizle', [
            'sonuc' => $sonuc,
            'kip'   => $kip,
            'yil'   => $yil,
        ], 'İçe Aktarma Önizleme');
    }

    /** Önizlemede seçilen satırları kaydet */
    public function onayla()
    {
        $sonuc = session()->get('makbuz_ice_aktar');

        if (! is_array($sonuc) || empty($sonuc['satirlar'])) {
            return redirect()->to(site_url('makbuz/ice-aktar'))
                ->with('hata', 'Önizleme bulunamadı, dosyayı yeniden yükleyin.');
        }

        $secilen = $this->request->getPost('sec');
        $secilen = is_array($secilen) ? array_map('intval', $secilen) : [];

        if ($secilen === []) {
            return redirect()->to(site_url('makbuz/ice-aktar?kip=' . ($sonuc['kip'] ?? 'ucret')))
                ->with('hata', 'Hiçbir satır seçilmedi; aktarma yapılmadı.');
        }

        // Yalnızca işaretli satırlar aktarılır
        $aktarilacak = [];

        foreach ($sonuc['satirlar'] as $s) {
            if (in_array((int) $s['satir_no'], $secilen, true)) {
                $aktarilacak[] = $s;
            }
        }

        $lib = new MakbuzIceAktar();
        $r   = $lib->aktar($aktarilacak, $sonuc['kip'], (int) ($this->aktifKullanici['id'] ?? 0) ?: null);

        session()->remove('makbuz_ice_aktar');

        $mesaj = $sonuc['kip'] === 'ucret'
            ? sprintf('%d ücret eklendi, %d ücret güncellendi.', $r['eklenen'], $r['guncellenen'])
            : sprintf('%d makbuz eklendi.', $r['eklenen']);

        if ($r['atlanan'] > 0) {
            $mesaj .= ' ' . $r['atlanan'] . ' satır atlandı.';
        }

        return redirect()->to(site_url('makbuz?yil=' . ($sonuc['yil'] ?? date('Y'))))
            ->with($r['eklenen'] + $r['guncellenen'] > 0 ? 'basari' : 'hata', $mesaj);
    }

    // -----------------------------------------------------------------
    //  YAZDIRMA
    // -----------------------------------------------------------------

    /**
     * Makbuz takip listesi yazdırma.
     *
     * bicim=liste (varsayılan) : mükellef bazında ücret/kesilen/kalan dökümü
     * bicim=ozet               : mali müşavir bazında özet tablo
     *
     * Ekrandaki filtre (yıl, müşavir, durum, arama) çıktıya taşınır;
     * sayfalama UYGULANMAZ — kâğıda tam liste dökülür.
     */
    public function yazdir()
    {
        $filtre = $this->filtreAl();
        $bicim  = $this->request->getGet('bicim') === 'ozet' ? 'ozet' : 'liste';

        $veri = [
            'filtre'   => $filtre,
            'bicim'    => $bicim,
            'ozet'     => $this->model->ozet($filtre),
            'durumlar' => self::DURUMLAR,
        ];

        if ($bicim === 'ozet') {
            $veri['musavirOzet'] = $this->model->musavirOzeti(
                (int) $filtre['yil'],
                $filtre['musavir_id'] ?: $this->musavirFiltresi()
            );
        } else {
            // Sayfalama yok: filtreye uyan TÜM satırlar
            $veri['kayitlar'] = $this->model->cizelge($filtre);
        }

        return view('makbuz/yazdir', $veri);
    }

    /** Tek mükellefin makbuz dökümünü yazdır */
    public function detayYazdir(int $mukellefId)
    {
        $yil      = (int) ($this->request->getGet('yil') ?? date('Y'));
        $mukellef = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null || ! $this->mukellefErisilebilirMi($mukellefId)) {
            return redirect()->to(site_url('makbuz'))->with('hata', 'Mükellefe erişilemedi.');
        }

        $makbuzlar = $this->model->mukellefMakbuzlari($mukellefId, $yil);
        $ucret     = $this->model->ucretAl($mukellefId, $yil);
        $kesilen   = 0.0;

        foreach ($makbuzlar as $m) {
            $kesilen += (float) $m['brut'];
        }

        return view('makbuz/detay_yazdir', [
            'mukellef'  => $mukellef,
            'yil'       => $yil,
            'ucret'     => $ucret,
            'makbuzlar' => $makbuzlar,
            'kesilen'   => $kesilen,
            'kalan'     => round($ucret - $kesilen, 2),
        ]);
    }

    // -----------------------------------------------------------------
    //  DIŞA AKTARMA
    // -----------------------------------------------------------------
    public function excel()
    {
        $filtre = $this->filtreAl();
        $rows   = $this->model->cizelge($filtre);
        $yil    = (int) $filtre['yil'];

        $csv  = "\xEF\xBB\xBF";
        $csv .= "Mukellef;VKN/TCKN;Mali Musavir;Yillik Ucret;Kesilen;Kalan;Makbuz Adedi;Oran (%)\n";

        $t = ['ucret' => 0.0, 'kesilen' => 0.0, 'kalan' => 0.0, 'adet' => 0];

        foreach ($rows as $r) {
            $csv .= '"' . str_replace('"', '""', $r['unvan']) . '";'
                . ($r['vergi_kimlik_no'] ?: $r['tc_kimlik_no']) . ';'
                . '"' . str_replace('"', '""', (string) $r['musavir_adi']) . '";'
                . number_format($r['ucret'], 2, ',', '.') . ';'
                . number_format($r['kesilen'], 2, ',', '.') . ';'
                . number_format($r['kalan'], 2, ',', '.') . ';'
                . $r['adet'] . ';'
                . $r['oran'] . "\n";

            $t['ucret']   += $r['ucret'];
            $t['kesilen'] += $r['kesilen'];
            $t['kalan']   += $r['kalan'];
            $t['adet']    += $r['adet'];
        }

        $csv .= "TOPLAM;;;" . number_format($t['ucret'], 2, ',', '.') . ';'
            . number_format($t['kesilen'], 2, ',', '.') . ';'
            . number_format($t['kalan'], 2, ',', '.') . ';'
            . $t['adet'] . ";\n";

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="makbuz_takip_' . $yil . '.csv"')
            ->setBody($csv);
    }

    // -----------------------------------------------------------------
    //  YARDIMCILAR
    // -----------------------------------------------------------------
    protected function filtreAl(): array
    {
        return [
            'yil'         => (int) ($this->request->getGet('yil') ?? date('Y')),
            'musavir_id'  => $this->kapsamBelirle($this->request->getGet('musavir_id')),
            'durum'       => $this->request->getGet('durum'),
            'q'           => $this->request->getGet('q'),
            'pasif_dahil' => $this->request->getGet('pasif'),
        ];
    }

    protected function adetBelirle(): int
    {
        $ham = $this->request->getGet('adet');

        if ($ham !== null && in_array((int) $ham, self::SAYFA_ADETLERI, true)) {
            setcookie('makbuz_adet', (string) (int) $ham, time() + 31536000, '/');

            return (int) $ham;
        }

        $cerez = (int) ($_COOKIE['makbuz_adet'] ?? 0);

        return in_array($cerez, self::SAYFA_ADETLERI, true) ? $cerez : self::VARSAYILAN_ADET;
    }

    /** Mükellef kullanıcının yetki kapsamında mı? */
    protected function mukellefErisilebilirMi(int $mukellefId): bool
    {
        if ($mukellefId <= 0) {
            return false;
        }

        if ($this->adminMi()) {
            return true;
        }

        $m = (new MukellefModel())->find($mukellefId);

        return $m !== null && $this->musavireErisebilirMi((int) $m['musavir_id']);
    }

    /** Kullanıcı tek bir müşavire bağlıysa onun id'si */
    protected function tekMusavirId(): ?int
    {
        $izin = $this->musavirFiltresi();

        return is_array($izin) && count($izin) === 1 ? (int) $izin[0] : null;
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
