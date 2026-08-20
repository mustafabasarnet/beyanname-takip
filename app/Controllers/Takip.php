<?php

namespace App\Controllers;

use App\Models\AylikNotModel;
use App\Models\BeyannameTakipModel;
use App\Models\BeyannameTuruModel;
use App\Models\DamgaTutarModel;
use App\Models\MukellefModel;
use App\Models\MusavirModel;

class Takip extends BaseController
{
    protected BeyannameTakipModel $model;

    public function __construct()
    {
        $this->model = new BeyannameTakipModel();
    }

    // -----------------------------------------------------------------
    /** Sayfa başına gösterilebilecek kayıt sayıları */
    public const SAYFA_ADETLERI = [25, 50, 100, 250];

    public const VARSAYILAN_ADET = 100;

    public function index()
    {
        $filtre = $this->filtreAl();
        $adet   = $this->adetBelirle();

        // İlk parça: 0'dan başlayarak $adet kadar kayıt
        $sayfaFiltre          = $filtre;
        $sayfaFiltre['limit'] = $adet;
        $sayfaFiltre['ofset'] = 0;

        $toplam   = $this->model->cizelgeSayisi($filtre);
        $kayitlar = $this->model->cizelge($sayfaFiltre);

        return $this->goster('takip/index', [
            'kayitlar'   => $kayitlar,
            // MUHSGK ↔ SGK eşleşmesi: rozetler ve tek ekrandan tahakkuk için
            'esHarita'   => $this->model->esHarita($kayitlar),
            'filtre'     => $filtre,
            'turler'     => (new BeyannameTuruModel())->aktifler(),
            'musavirler' => $this->secilebilirMusavirler(),
            'durumlar'   => BeyannameTakipModel::DURUMLAR,
            // Sayaçlar ekrandaki filtrenin AYNISINI kullanır (tür, ay, mükellef,
            // arama dahil). Yalnızca "durum" ve "sadece gecikmişler" dışarıda
            // bırakılır ki kartlar dağılımı göstermeye devam etsin.
            'ozet'       => $this->model->ozetCizelge($filtre),
            'damgaTanim' => (new DamgaTutarModel())->yilHaritasi((int) $filtre['yil']),
            'maliYetki'    => $this->maliYetkiVarMi(),
            'tahakkukYetki'=> $this->tahakkukYetkisiVarMi(),
            // Sonsuz kaydırma bilgileri
            'toplamKayit'  => $toplam,
            'sayfaAdedi'   => $adet,
            'adetSecenek'  => self::SAYFA_ADETLERI,
            'dahaVar'      => $toplam > $adet,
        ], 'Beyanname Takip Çizelgesi');
    }

    /**
     * AJAX: sonsuz kaydırma — sonraki kayıt parçasını HTML olarak döndürür.
     * Satır çizimi tek yerden (takip/_satirlar) yapılır ki ilk yükleme ile
     * sonradan eklenen satırlar birebir aynı görünsün.
     */
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

        $esHarita = $this->model->esHarita($kayitlar);

        $html = view('takip/_satirlar', [
            'kayitlar'      => $kayitlar,
            'filtre'        => $filtre,
            'mod'           => $filtre['tarih_modu'],
            'durumlar'      => BeyannameTakipModel::DURUMLAR,
            'tahakkukYetki' => $this->tahakkukYetkisiVarMi(),
            'esHarita'      => $esHarita,
        ]);

        // Yeni satırların tahakkuk penceresi verisi (JS tarafına)
        $satirVeri = [];

        foreach ($kayitlar as $k) {
            $ggBilgi  = gencGirisimciDurum($k, (int) $k['yil']);
            $ggIlgili = in_array($k['tur_kodu'], ['YILLIK_GV', 'GELIR_GECICI'], true);

            $satirVeri[(int) $k['id']] = [
                'mukellef'  => $k['mukellef_unvan'],
                'tur'       => $k['tur_kisa'],
                'gg'        => $ggBilgi['var'] && $ggIlgili,
                'ggGecerli' => (bool) $ggBilgi['gecerli'],
                'ggMetin'   => $ggBilgi['metin'],
                'ggNot'     => $k['gg_not'] ?? '',
                'ggAralik'  => $ggBilgi['baslangic'] !== null
                    ? $ggBilgi['baslangic'] . ' – ' . $ggBilgi['bitis'] : '',
                'donem'     => $k['donem_adi'],
                'sonTarih'  => trTarih($k['son_tarih']),
                'tutar'     => $k['tahakkuk_tutari'] !== null
                    ? number_format((float) $k['tahakkuk_tutari'], 2, ',', '.') : '',
                'fis'       => $k['tahakkuk_fis_no'] ?? '',
                'damga'     => (float) $k['damga_tutari'],
                'turId'     => (int) $k['beyanname_turu_id'],
                // MUHSGK ↔ SGK eşleşmesi (tek ekrandan giriş için)
                'es'        => $this->esVerisi($esHarita[(int) $k['id']] ?? null),
            ];
        }

        $sonrakiOfset = $ofset + count($kayitlar);

        return $this->response->setJSON([
            'durum'     => true,
            'html'      => $html,
            'satirVeri' => $satirVeri,
            'esHarita'  => $esHarita,
            'yuklenen'  => count($kayitlar),
            'ofset'     => $sonrakiOfset,
            'toplam'    => $toplam,
            'dahaVar'   => $sonrakiOfset < $toplam,
        ]);
    }

    /**
     * AJAX: filtreye uyan TÜM kayıtların id listesi.
     * "Filtredeki N kaydın hepsini seç" bağlantısı kullanır.
     */
    public function tumIdler()
    {
        $filtre = $this->filtreAl();
        $idler  = $this->model->cizelgeIdleri($filtre);

        return $this->jsonBasarili(count($idler) . ' kayıt seçildi.', [
            'idler' => $idler,
            'adet'  => count($idler),
        ]);
    }

    /** Sayfa başına kayıt adedi (kullanıcı seçimi, çerezde saklanır) */
    protected function adetBelirle(): int
    {
        $ham = (int) ($this->request->getGet('adet') ?? 0);

        if (in_array($ham, self::SAYFA_ADETLERI, true)) {
            return $ham;
        }

        $cerez = (int) ($this->request->getCookie('bt_sayfa_adedi') ?? 0);

        if (in_array($cerez, self::SAYFA_ADETLERI, true)) {
            return $cerez;
        }

        return self::VARSAYILAN_ADET;
    }

    // -----------------------------------------------------------------
    //  AJAX: durum güncelleme
    // -----------------------------------------------------------------
    public function durumGuncelle()
    {
        $id    = (int) $this->request->getPost('id');
        $durum = (string) $this->request->getPost('durum');

        $kayit = $this->model->cizelgeKaydi($id);

        if ($kayit === null) {
            return $this->jsonHata('Kayıt bulunamadı.', 404);
        }

        if (! $this->kayitYetkisi($kayit)) {
            return $this->jsonHata('Bu kayıt için yetkiniz yok.', 403);
        }

        if (! $this->model->durumDegistir($id, $durum, (int) $this->aktifKullanici['id'])) {
            return $this->jsonHata('Durum güncellenemedi.');
        }

        $yeni = $this->model->find($id);

        // Onay geri alındığında (Bekliyor / Hazır / Verilmeyecek) daha önce
        // girilmiş bir tahakkuk varsa, arayüz kullanıcıya "silinsin mi?" diye
        // sorabilsin diye bunu yanıtta bildiriyoruz.
        $tahakkukKaldi = $yeni['durum'] !== 'ONAYLANDI'
            && ($yeni['tahakkuk_tutari'] !== null || (float) $yeni['damga_tutari'] > 0);

        $ek = [
            'id'             => $id,
            'yeni_durum'     => $yeni['durum'],
            'durum_metin'    => BeyannameTakipModel::DURUMLAR[$yeni['durum']] ?? $yeni['durum'],
            'onay_tarihi'    => $yeni['onay_tarihi'],
            'tahakkuk_kaldi' => $tahakkukKaldi,
            'tahakkuk_f'     => $yeni['tahakkuk_tutari'] === null
                ? '' : number_format((float) $yeni['tahakkuk_tutari'], 2, ',', '.'),
            'damga_f'        => number_format((float) $yeni['damga_tutari'], 2, ',', '.'),
        ];

        // -------------------------------------------------------------
        //  MUHSGK ↔ SGK BAĞI
        //
        //  MUHSGK onaylandığında eşleşen SGK satırı da kendiliğinden
        //  onaylanır — kullanıcı aynı işi iki kez yapmasın.
        //
        //  Onay GERİ ALINDIĞINDA ise SGK'ya dokunulmaz; arayüz
        //  "SGK da geri alınsın mı?" diye sorar (kullanıcı tercihi).
        //  Bu yüzden yalnızca bilgi döndürülür.
        // -------------------------------------------------------------
        $esler = $this->model->esKayitlar($kayit);

        if ($esler !== []) {
            $ek['es_var'] = true;
            $ek['es_rol'] = $this->model->muhsgkMi($kayit) ? 'ana' : 'bagli';

            if ($this->model->muhsgkMi($kayit)) {
                if ($durum === 'ONAYLANDI') {
                    $ek['es_guncellenen'] = $this->esDurumUygula($esler, 'ONAYLANDI');
                } else {
                    // Geri alma: karar kullanıcıya bırakılır
                    $ek['es_geri_sor'] = $this->esGeriAlmaBilgisi($esler, $durum);
                }
            } elseif ($durum === 'ONAYLANDI') {
                // -----------------------------------------------------
                //  SGK tek başına onaylandı.
                //  MUHSGK henüz onaylı değilse UYARILIR ama engellenmez
                //  (kullanıcı tercihi: "uyarı verilsin ama izin verilsin").
                // -----------------------------------------------------
                $bekleyen = array_values(array_filter(
                    $esler,
                    static fn ($e) => $e['durum'] !== 'ONAYLANDI'
                ));

                if ($bekleyen !== []) {
                    $ek['es_uyari'] = 'Bu SGK kaydı onaylandı ancak eşleşen '
                        . $bekleyen[0]['tur_kisa'] . ' beyannamesi (' . $bekleyen[0]['donem_adi']
                        . ') henüz onaylanmadı. SGK genellikle MUHSGK ile birlikte verilir.';
                }
            }
        }

        return $this->jsonBasarili('Durum güncellendi.', $ek);
    }

    /**
     * Eş kayıtlara durum uygular (yetkisi olmayanlar atlanır).
     *
     * @param array<int,array> $esler
     *
     * @return array<int,array> Arayüzün satırları tazeleyebilmesi için özet
     */
    protected function esDurumUygula(array $esler, string $durum): array
    {
        $sonuc = [];

        foreach ($esler as $e) {
            if (! $this->kayitYetkisi($e)) {
                continue;
            }

            // Zaten aynı durumdaysa boşuna yazma
            if ($e['durum'] === $durum) {
                $sonuc[] = $this->esOzet($e, false);

                continue;
            }

            if ($this->model->durumDegistir((int) $e['id'], $durum, (int) $this->aktifKullanici['id'])) {
                $guncel   = $this->model->find((int) $e['id']);
                $sonuc[]  = $this->esOzet($guncel + ['tur_kisa' => $e['tur_kisa']], true);
            }
        }

        return $sonuc;
    }

    /**
     * "SGK da geri alınsın mı?" penceresi için bilgi paketi.
     *
     * @param array<int,array> $esler
     */
    protected function esGeriAlmaBilgisi(array $esler, string $hedefDurum): array
    {
        $liste = [];

        foreach ($esler as $e) {
            // Yalnızca hâlâ onaylı olanlar sorulur
            if ($e['durum'] !== 'ONAYLANDI' || ! $this->kayitYetkisi($e)) {
                continue;
            }

            $liste[] = [
                'id'       => (int) $e['id'],
                'tur'      => $e['tur_kisa'],
                'donem'    => $e['donem_adi'],
                'tutar_f'  => $e['tahakkuk_tutari'] === null
                    ? '' : number_format((float) $e['tahakkuk_tutari'], 2, ',', '.'),
            ];
        }

        return ['durum' => $hedefDurum, 'kayitlar' => $liste];
    }

    /** Eş kaydın arayüze dönen özeti */
    protected function esOzet(array $e, bool $degisti): array
    {
        return [
            'id'       => (int) $e['id'],
            'tur'      => $e['tur_kisa'] ?? 'SGK',
            'durum'    => $e['durum'],
            'degisti'  => $degisti,
            'tutar_f'  => $e['tahakkuk_tutari'] === null
                ? '' : number_format((float) $e['tahakkuk_tutari'], 2, ',', '.'),
            'damga'    => (float) ($e['damga_tutari'] ?? 0),
        ];
    }

    /**
     * AJAX: eş (SGK) kayıtların durumunu topluca değiştirir.
     * "MUHSGK geri alındı, SGK da geri alınsın mı?" onayından çağrılır.
     */
    public function esDurum()
    {
        $idler = $this->request->getPost('idler') ?? [];
        $durum = (string) $this->request->getPost('durum');

        if (! is_array($idler) || $idler === []) {
            return $this->jsonHata('Hiç kayıt belirtilmedi.');
        }

        if (! array_key_exists($durum, BeyannameTakipModel::DURUMLAR)) {
            return $this->jsonHata('Geçersiz durum.');
        }

        $sonuc = [];

        foreach ($idler as $ham) {
            $kayit = $this->model->cizelgeKaydi((int) $ham);

            if ($kayit === null || ! $this->kayitYetkisi($kayit)) {
                continue;
            }

            if ($this->model->durumDegistir((int) $kayit['id'], $durum, (int) $this->aktifKullanici['id'])) {
                $guncel  = $this->model->find((int) $kayit['id']);
                $sonuc[] = $this->esOzet($guncel + ['tur_kisa' => $kayit['tur_kisa']], true);
            }
        }

        if ($sonuc === []) {
            return $this->jsonHata('Hiçbir kayıt güncellenemedi.');
        }

        return $this->jsonBasarili(
            count($sonuc) . ' SGK kaydı güncellendi.',
            ['esler' => $sonuc]
        );
    }

    /** Eş harita girdisini arayüzün beklediği biçime çevirir */
    protected function esVerisi(?array $giris): ?array
    {
        if ($giris === null) {
            return null;
        }

        $esler = [];

        foreach ($giris['esler'] as $e) {
            $esler[] = [
                'id'      => (int) $e['id'],
                'tur'     => $e['tur_kisa'],
                'donem'   => $e['donem_adi'],
                'durum'   => $e['durum'],
                'tutar'   => $e['tahakkuk_tutari'] === null
                    ? '' : number_format((float) $e['tahakkuk_tutari'], 2, ',', '.'),
                'fis'     => $e['tahakkuk_fis_no'] ?? '',
                'damga'   => (float) $e['damga_tutari'],
                'turId'   => (int) $e['beyanname_turu_id'],
            ];
        }

        return ['rol' => $giris['rol'], 'esler' => $esler];
    }

    // -----------------------------------------------------------------
    //  AJAX: tahakkuk bilgisini sil
    //  (durum "Onaylandı"dan geri alındığında kullanıcı onayıyla çağrılır)
    // -----------------------------------------------------------------
    public function tahakkukSil()
    {
        if (! $this->tahakkukYetkisiVarMi()) {
            return $this->jsonHata('Bu işlem için yetkiniz yok.', 403);
        }

        // Tek kayıt (id) veya toplu (idler[]) çalışır
        $idler = $this->request->getPost('idler') ?? [];

        if ($idler === [] && $this->request->getPost('id') !== null) {
            $idler = [$this->request->getPost('id')];
        }

        if ($idler === []) {
            return $this->jsonHata('Hiç kayıt belirtilmedi.');
        }

        $sayac    = 0;
        $temizler = [];

        foreach ($idler as $ham) {
            $id    = (int) $ham;
            $kayit = $this->model->find($id);

            if ($kayit === null || ! $this->kayitYetkisi($kayit)) {
                continue;
            }

            if ($this->model->tahakkukTemizle($id)) {
                $sayac++;
                $temizler[] = $id;
            }
        }

        if ($sayac === 0) {
            return $this->jsonHata('Tahakkuk silinemedi.');
        }

        return $this->jsonBasarili(
            $sayac === 1 ? 'Tahakkuk bilgisi silindi.' : $sayac . ' kaydın tahakkuk bilgisi silindi.',
            ['idler' => $temizler, 'adet' => $sayac]
        );
    }

    /** AJAX: satır notu */
    public function notKaydet()
    {
        $id  = (int) $this->request->getPost('id');
        $not = $this->request->getPost('not');

        $kayit = $this->model->find($id);

        if ($kayit === null) {
            return $this->jsonHata('Kayıt bulunamadı.', 404);
        }

        if (! $this->kayitYetkisi($kayit)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        $this->model->update($id, ['not_metni' => $not]);

        return $this->jsonBasarili('Not kaydedildi.', ['not' => $not]);
    }

    /** AJAX: seçili satırların durumunu topluca değiştir */
    public function topluDurum()
    {
        $idler = $this->request->getPost('idler') ?? [];
        $durum = (string) $this->request->getPost('durum');

        if ($idler === []) {
            return $this->jsonHata('Hiç kayıt seçilmedi.');
        }

        $sayac = 0;

        // Onay geri alındığında tahakkuk bilgisi duran kayıtların id'leri
        $tahakkukKalanlar = [];

        foreach ($idler as $id) {
            $kayit = $this->model->find((int) $id);

            if ($kayit !== null && $this->kayitYetkisi($kayit)) {
                $this->model->durumDegistir((int) $id, $durum, (int) $this->aktifKullanici['id']);
                $sayac++;

                if ($durum !== 'ONAYLANDI'
                    && ($kayit['tahakkuk_tutari'] !== null || (float) $kayit['damga_tutari'] > 0)) {
                    $tahakkukKalanlar[] = (int) $id;
                }
            }
        }

        return $this->jsonBasarili($sayac . ' kayıt güncellendi.', [
            'adet'             => $sayac,
            'tahakkuk_kalanlar' => $tahakkukKalanlar,
        ]);
    }

    // -----------------------------------------------------------------
    //  Toplu dönem üretimi
    // -----------------------------------------------------------------
    public function topluUret()
    {
        return $this->goster('takip/toplu_uret', [
            'musavirler' => $this->secilebilirMusavirler(),
            'yil'        => (int) date('Y'),
        ], 'Toplu Dönem Üretimi');
    }

    public function topluUretCalistir()
    {
        $yil       = (int) $this->request->getPost('yil');
        $musavirId = $this->kapsamBelirle($this->request->getPost('musavir_id'));

        if ($yil < 2000 || $yil > 2100) {
            return redirect()->back()->with('hata', 'Geçerli bir yıl giriniz.');
        }

        $ozet = $this->model->topluUret($yil, $musavirId);

        return redirect()->to(site_url('takip?yil=' . $yil))
            ->with('basari', sprintf(
                '%d mükellef işlendi. %d dönem eklendi, %d güncellendi, %d kaldırıldı.',
                $ozet['mukellef'], $ozet['eklenen'], $ozet['guncellenen'], $ozet['silinen']
            ));
    }

    // -----------------------------------------------------------------
    //  Dışa aktarma
    // -----------------------------------------------------------------
    public function excel()
    {
        $kayitlar = $this->model->cizelge($this->filtreAl());

        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM (Excel Türkçe karakter)
        $csv .= "Mükellef;VKN/TCKN;Defter Tipi;Beyanname;Dönem;Yasal Son Tarih;Son Tarih;Kaydırma;Durum;Not;Mali Müşavir\n";

        foreach ($kayitlar as $k) {
            $csv .= implode(';', [
                str_replace(';', ',', (string) $k['mukellef_unvan']),
                $k['vergi_kimlik_no'] ?: $k['tc_kimlik_no'],
                defterTipiKisa($k['defter_tipi']),
                $k['tur_kisa'],
                $k['donem_adi'],
                date('d.m.Y', strtotime($k['yasal_son_tarih'])),
                date('d.m.Y', strtotime($k['son_tarih'])),
                str_replace(';', ',', (string) $k['kaydirma_nedeni']),
                BeyannameTakipModel::DURUMLAR[$k['durum']] ?? $k['durum'],
                str_replace([';', "\n", "\r"], [',', ' ', ''], (string) $k['not_metni']),
                str_replace(';', ',', (string) $k['musavir_adi']),
            ]) . "\n";
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="beyanname_takip_' . date('Ymd_His') . '.csv"')
            ->setBody($csv);
    }

    public function yazdir()
    {
        $filtre = $this->filtreAl();

        return view('takip/yazdir', [
            'kayitlar' => $this->model->cizelge($filtre),
            'filtre'   => $filtre,
            'durumlar' => BeyannameTakipModel::DURUMLAR,
        ]);
    }

    // -----------------------------------------------------------------
    protected function filtreAl(): array
    {
        return [
            'yil'         => (int) ($this->request->getGet('yil') ?? date('Y')),
            // Varsayılan: içinde bulunduğumuz ay.
            // "Tüm Aylar" için ay=0 gönderilir (boş string tarayıcıdan da gelebildiği
            // için ayrımı netleştirmek adına 0 kullanılır).
            'ay'          => $this->ayBelirle(),
            'tarih_modu'  => $this->request->getGet('mod') === 'donem' ? 'donem' : 'beyan',
            'musavir_id'  => $this->kapsamBelirle($this->request->getGet('musavir_id')),
            'mukellef_id' => $this->request->getGet('mukellef_id'),
            'tur_id'      => $this->turFiltresi(),
            'durum'       => $this->request->getGet('durum'),
            'defter_tipi' => $this->request->getGet('defter_tipi'),
            'q'           => $this->request->getGet('q'),
            'gecikmis'    => $this->request->getGet('gecikmis'),
        ];
    }

    /**
     * Beyanname türü filtresi — tek veya çoklu değer destekler.
     *
     *   ?tur_id=1        → [1]          (tek seçim, eski davranış)
     *   ?tur_id[]=1&...  → [1,4]        (çoklu seçim)
     *
     * @return int[]|null null = filtre yok (tümü)
     */
    protected function turFiltresi()
    {
        $ham = $this->request->getGet('tur_id');

        if ($ham === null || $ham === '' || $ham === []) {
            return null;
        }

        $dizi = is_array($ham) ? $ham : [$ham];
        $dizi = array_values(array_unique(array_filter(
            array_map('intval', $dizi),
            static fn ($v) => $v > 0
        )));

        if ($dizi === []) {
            return null;
        }

        // Tek seçimde skaler (tur_id=1) — eski davranış, linklerde kısa URL;
        // birden çok seçimde dizi (tur_id[]=1&tur_id[]=4).
        return count($dizi) === 1 ? $dizi[0] : $dizi;
    }

    /**
     * Ay filtresi:
     *   parametre yok      -> içinde bulunduğumuz ay (varsayılan)
     *   ay=0 veya ay=tumu  -> tüm aylar
     *   ay=1..12           -> seçilen ay
     */
    protected function ayBelirle(): ?int
    {
        $ham = $this->request->getGet('ay');

        if ($ham === null) {
            return (int) date('n');
        }

        if ($ham === '' || $ham === '0' || $ham === 'tumu') {
            return null;
        }

        $ay = (int) $ham;

        return ($ay >= 1 && $ay <= 12) ? $ay : null;
    }

    protected function kayitYetkisi(array $kayit): bool
    {
        if ($this->adminMi()) {
            return true;
        }

        $mukellef = (new MukellefModel())->find($kayit['mukellef_id']);

        return $mukellef !== null && $this->mukellefeErisebilirMi($mukellef);
    }
}
