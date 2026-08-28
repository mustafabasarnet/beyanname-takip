<?php

namespace App\Controllers;

use App\Models\AyarModel;
use App\Models\AylikNotModel;
use App\Models\EvrakMuafiyetModel;
use App\Models\EvrakTakipModel;
use App\Models\KullaniciModel;
use App\Models\MukellefModel;
use App\Models\MusavirModel;

class Evrak extends BaseController
{
    protected EvrakTakipModel $model;

    public function __construct()
    {
        $this->model = new EvrakTakipModel();
    }

    /**
     * Varsayılan seçilen ay (toplama ayı).
     *
     * Evrak listesi ekranı açıldığında varsayılan olarak BİR ÖNCEKİ
     * ayın evrakları gösterilir (muhasebe pratiği: içinde bulunulan ayın
     * evrakları bir sonraki ay toplanır — örn. Ağustos'ta Temmuz evrakları).
     *
     * Dönem kaydırma ayarı (evrak_donem_kaydirma) da hesaba katılarak
     * "toplama ayı" buna göre geri hesaplanır; böylece kullanıcı
     * kaydırma=0 yapsa bile ilk açılışta bir önceki ayı görür.
     *
     * @return array{yil:int,ay:int}
     */
    protected function varsayilanSecilenAy(): array
    {
        $ayarlar  = (new AyarModel())->tumu();
        $kaydirma = max(0, min(11, (int) ($ayarlar['evrak_donem_kaydirma'] ?? 1)));

        // Ekstra 1 ay geri al: amaç "evrak dönemi = önceki ay" olsun.
        //   dönem = seçilen_ay - kaydırma
        //   dönem = şu_an - 1 ay
        //   => seçilen_ay = şu_an - 1 + kaydırma
        // Bu formül kaydırma=1 iken seçilen_ay = şu_an (mevcut davranış),
        // kaydırma=0 iken seçilen_ay = şu_an - 1 (yani doğrudan evrak
        // dönemi = önceki ay) sonucunu verir.
        $ts = mktime(0, 0, 0, (int) date('n') - 1 + $kaydirma, 1, (int) date('Y'));

        return [
            'yil' => (int) date('Y', $ts),
            'ay'  => (int) date('n', $ts),
        ];
    }

    public function index()
    {
        // Seçilen ay = evrakların TOPLANDIĞI ay (varsayılan: önceki ayın evrakları)
        $varsayilan = $this->varsayilanSecilenAy();
        $secilenYil = (int) ($this->request->getGet('yil') ?? $varsayilan['yil']);
        $secilenAy  = (int) ($this->request->getGet('ay') ?? $varsayilan['ay']);

        // Evrak dönemi = seçilen ay - kaydırma (varsayılan 1 ay geri)
        $donem = $this->model->donemHesapla($secilenYil, $secilenAy);
        $yil   = $donem['yil'];
        $ay    = $donem['ay'];

        $filtre = $this->filtreAl();
        $adet   = $this->adetBelirle();

        $sayfaFiltre          = $filtre;
        $sayfaFiltre['limit'] = $adet;
        $sayfaFiltre['ofset'] = 0;

        $cizelge = $this->model->cizelge($yil, $ay, $sayfaFiltre);

        // Aylık notlar (yalnızca gösterilen mükellefler için)
        $notModel = new AylikNotModel();
        $notlar   = [];

        foreach ($cizelge['mukellefler'] as $m) {
            $notlar[(int) $m['id']] = $notModel->notAl((int) $m['id'], $yil, $ay);
        }

        // Sayaçlar: filtreye uyan TÜM faal küme üzerinden (sayfa dilimi değil).
        // Takip dışı hücreler toplamdan düşülür.
        $ozet = $this->model->ozet(
            $yil,
            $ay,
            $this->musavirFiltresi(),
            $filtre['sorumlu_kullanici_id'] ? (int) $filtre['sorumlu_kullanici_id'] : null,
            $cizelge['faalIdler'] ?? null
        );

        return $this->goster('evrak/index', [
            // Evrak dönemi (kayıtların yazıldığı ay)
            'yil'         => $yil,
            'ay'          => $ay,
            // Toplama ayı (filtrede seçili olan)
            'secilenYil'  => $secilenYil,
            'secilenAy'   => $secilenAy,
            'kaydirma'    => $donem['kaydirma'],
            'filtre'      => $filtre,
            'mukellefler' => $cizelge['mukellefler'],
            'turler'      => $cizelge['turler'],
            'matris'      => $cizelge['matris'],
            'muafiyet'    => $cizelge['muafiyet'] ?? [],
            'notlar'      => $notlar,
            'musavirler'  => $this->secilebilirMusavirler(),
            'personeller' => $this->personelSecenekleri(),
            'ozet'        => $ozet,
            // Takip dışı hücre sayısı (toplam hücreden düşülür)
            'muafHucre'   => $this->model->muafHucreSayisi($yil, $ay, $cizelge['faalIdler'] ?? []),
            // Sayfalama
            'toplamKayit' => $cizelge['toplam'],
            'sayfaAdedi'  => $adet,
            'adetSecenek' => EvrakTakipModel::SAYFA_ADETLERI,
            'dahaVar'     => $cizelge['toplam'] > $adet,
        ], 'Aylık Evrak Takibi');
    }

    /**
     * AJAX: sonsuz kaydırma — sonraki mükellef satırlarını HTML döndürür.
     * Satır çizimi evrak/_satirlar parçasından yapılır ki ilk yükleme ile
     * sonradan eklenenler birebir aynı görünsün.
     */
    public function dahaFazla()
    {
        $varsayilan = $this->varsayilanSecilenAy();
        $secilenYil = (int) ($this->request->getGet('yil') ?? $varsayilan['yil']);
        $secilenAy  = (int) ($this->request->getGet('ay') ?? $varsayilan['ay']);

        $donem = $this->model->donemHesapla($secilenYil, $secilenAy);
        $yil   = $donem['yil'];
        $ay    = $donem['ay'];

        $filtre = $this->filtreAl();
        $adet   = $this->adetBelirle();
        $ofset  = max(0, (int) $this->request->getGet('ofset'));

        $sayfaFiltre          = $filtre;
        $sayfaFiltre['limit'] = $adet;
        $sayfaFiltre['ofset'] = $ofset;

        $cizelge = $this->model->cizelge($yil, $ay, $sayfaFiltre);

        $notModel = new AylikNotModel();
        $notlar   = [];

        foreach ($cizelge['mukellefler'] as $m) {
            $notlar[(int) $m['id']] = $notModel->notAl((int) $m['id'], $yil, $ay);
        }

        $html = view('evrak/_satirlar', [
            'mukellefler' => $cizelge['mukellefler'],
            'turler'      => $cizelge['turler'],
            'matris'      => $cizelge['matris'],
            'muafiyet'    => $cizelge['muafiyet'] ?? [],
            'notlar'      => $notlar,
            'yil'         => $yil,
            'ay'          => $ay,
        ]);

        $sonrakiOfset = $ofset + count($cizelge['mukellefler']);

        return $this->response->setJSON([
            'durum'    => true,
            'html'     => $html,
            'yuklenen' => count($cizelge['mukellefler']),
            'ofset'    => $sonrakiOfset,
            'toplam'   => $cizelge['toplam'],
            'dahaVar'  => $sonrakiOfset < $cizelge['toplam'],
        ]);
    }

    /** Ortak filtre okuma */
    protected function filtreAl(): array
    {
        return [
            'musavir_id'           => $this->kapsamBelirle($this->request->getGet('musavir_id')),
            'q'                    => $this->request->getGet('q'),
            'sorumlu_kullanici_id' => $this->request->getGet('sorumlu_kullanici_id'),
        ];
    }

    /**
     * Sayfa başına mükellef adedi.
     * Öncelik: adres çubuğu → kullanıcının çerezi → sistem ayarı → varsayılan
     */
    protected function adetBelirle(): int
    {
        $ham = (int) ($this->request->getGet('adet') ?? 0);

        if (in_array($ham, EvrakTakipModel::SAYFA_ADETLERI, true)) {
            return $ham;
        }

        $cerez = (int) ($this->request->getCookie('bt_evrak_adedi') ?? 0);

        if (in_array($cerez, EvrakTakipModel::SAYFA_ADETLERI, true)) {
            return $cerez;
        }

        // Tanımlar → Ayarlar → evrak_sayfa_adedi
        $ayar = (int) ((new AyarModel())->tumu()['evrak_sayfa_adedi'] ?? 0);

        if (in_array($ayar, EvrakTakipModel::SAYFA_ADETLERI, true)) {
            return $ayar;
        }

        return EvrakTakipModel::VARSAYILAN_ADET;
    }

    /**
     * "Sorumlu Personel" açılır menüsü.
     * Kullanıcının erişebildiği müşavirlere bağlı kullanıcılar listelenir.
     *
     * @return array<int,string>
     */
    protected function personelSecenekleri(): array
    {
        $musavirler = array_keys($this->secilebilirMusavirler());

        if ($musavirler === []) {
            return [];
        }

        $db  = db_connect();
        $out = [];

        // 1) Çoklu erişim tablosundan (kullanici_musavirleri)
        foreach ((new KullaniciModel())->musavirinKullanicilari($musavirler) as $k) {
            $out[(int) $k['id']] = $k['ad_soyad'];
        }

        // 2) Birincil müşaviri bu listede olan kullanıcılar.
        //    Bağlantı tablosu boş olan kurulumlarda menü boş kalmasın diye
        //    bu ikinci kaynak da taranır.
        $rows = $db->table('kullanicilar')
            ->select('id, ad_soyad')
            ->whereIn('musavir_id', array_map('intval', $musavirler))
            ->where('aktif', 1)
            ->orderBy('ad_soyad', 'ASC')
            ->get()->getResultArray();

        foreach ($rows as $k) {
            $out[(int) $k['id']] = $k['ad_soyad'];
        }

        // 3) Mükelleflere fiilen atanmış sorumlular (silinmiş bağlantı olsa bile)
        $rows = $db->table('mukellefler m')
            ->select('k.id, k.ad_soyad')
            ->join('kullanicilar k', 'k.id = m.sorumlu_kullanici_id')
            ->whereIn('m.musavir_id', array_map('intval', $musavirler))
            ->where('m.deleted_at', null)
            ->where('m.sorumlu_kullanici_id IS NOT NULL', null, false)
            ->groupBy('k.id')
            ->get()->getResultArray();

        foreach ($rows as $k) {
            $out[(int) $k['id']] = $k['ad_soyad'];
        }

        asort($out, SORT_LOCALE_STRING);

        return $out;
    }

    /** AJAX: tek hücre Geldi/Gelmedi */
    public function durumGuncelle()
    {
        $mukellefId = (int) $this->request->getPost('mukellef_id');
        $turId      = (int) $this->request->getPost('evrak_turu_id');
        $yil        = (int) $this->request->getPost('yil');
        $ay         = (int) $this->request->getPost('ay');
        $durum      = (string) $this->request->getPost('durum');

        $mukellef = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        $veri = $this->model->durumKaydet($mukellefId, $turId, $yil, $ay, $durum, (int) $this->aktifKullanici['id']);

        return $this->jsonBasarili('Kaydedildi.', [
            'yeni_durum'    => $veri['durum'],
            'teslim_tarihi' => $veri['teslim_tarihi'],
        ]);
    }

    /**
     * AJAX: hücreyi "bu dönem takip edilmiyor" (YOK) yapar veya geri alır.
     *
     * Bu yalnızca SEÇİLİ AYI etkiler; kalıcı ayar mükellef kartındadır.
     * "Geri al" isteği o aya ait kaydı siler; hücre böylece kalıcı ayara
     * (muaf ise YOK, değilse GELMEDI) geri döner.
     */
    public function donemMuaf()
    {
        $mukellefId = (int) $this->request->getPost('mukellef_id');
        $turId      = (int) $this->request->getPost('evrak_turu_id');
        $yil        = (int) $this->request->getPost('yil');
        $ay         = (int) $this->request->getPost('ay');
        $isaretle   = (string) $this->request->getPost('isaretle') !== '0';

        $mukellef = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        if (! $this->model->yokDestekliMi()) {
            return $this->jsonHata(
                'Bu özellik için veritabanı güncellemesi gerekli: migration_evrak_muafiyet.sql',
                400
            );
        }

        $muafModel = new EvrakMuafiyetModel();
        $kaliciMi  = $muafModel->muafMi($mukellefId, $turId);

        if ($isaretle) {
            $this->model->durumKaydet($mukellefId, $turId, $yil, $ay, 'YOK', (int) $this->aktifKullanici['id']);
            $etkin = 'YOK';
        } else {
            // Kaydı tamamen sil → kalıcı ayar yeniden geçerli olur
            $this->model->where([
                'mukellef_id'   => $mukellefId,
                'evrak_turu_id' => $turId,
                'yil'           => $yil,
                'ay'            => $ay,
            ])->delete();

            $etkin = $kaliciMi ? 'YOK' : 'GELMEDI';
        }

        return $this->jsonBasarili(
            $isaretle ? 'Bu dönem takip dışı bırakıldı.' : 'Takibe geri alındı.',
            [
                'yeni_durum' => $etkin,
                'kalici'     => $kaliciMi,
            ]
        );
    }

    /**
     * AJAX: kalıcı muafiyet (tüm aylar) aç/kapat.
     *
     * Çizelgeden hızlı erişim içindir; mükellef kartındaki onay kutusuyla
     * aynı tabloyu günceller. Açılırken geçmişteki boş (GELMEDI) kayıtlar
     * temizlenir, "geldi" işaretlileri korunur.
     */
    public function kaliciMuaf()
    {
        $mukellefId = (int) $this->request->getPost('mukellef_id');
        $turId      = (int) $this->request->getPost('evrak_turu_id');
        $isaretle   = (string) $this->request->getPost('isaretle') !== '0';
        $aciklama   = trim((string) $this->request->getPost('aciklama'));

        $mukellef = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        $muafModel = new EvrakMuafiyetModel();

        if (! $muafModel->kullanilabilir()) {
            return $this->jsonHata(
                'Bu özellik için veritabanı güncellemesi gerekli: migration_evrak_muafiyet.sql',
                400
            );
        }

        if ($isaretle) {
            $muafModel->ekle($mukellefId, $turId, $aciklama ?: null);
            $silinen = $muafModel->bosKayitlariTemizle($mukellefId);
            $mesaj   = 'Bu evrak türü mükellefte artık takip edilmiyor.';
        } else {
            $muafModel->kaldir($mukellefId, $turId);
            $silinen = 0;
            $mesaj   = 'Evrak türü yeniden takibe alındı.';
        }

        return $this->jsonBasarili($mesaj, [
            'yeni_durum' => $isaretle ? 'YOK' : 'GELMEDI',
            'kalici'     => $isaretle,
            'temizlenen' => $silinen,
        ]);
    }

    /** AJAX: mükellefin tüm evraklarını işaretle */
    public function tumunuIsaretle()
    {
        $mukellefId = (int) $this->request->getPost('mukellef_id');
        $yil        = (int) $this->request->getPost('yil');
        $ay         = (int) $this->request->getPost('ay');
        $durum      = (string) $this->request->getPost('durum');

        $mukellef = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        $adet = $this->model->tumunuIsaretle($mukellefId, $yil, $ay, $durum, (int) $this->aktifKullanici['id']);

        return $this->jsonBasarili($adet . ' evrak güncellendi.', ['yeni_durum' => $durum, 'adet' => $adet]);
    }

    /** AJAX: aylık mükellef notu */
    public function aylikNot()
    {
        $mukellefId = (int) $this->request->getPost('mukellef_id');
        $yil        = (int) $this->request->getPost('yil');
        $ay         = (int) $this->request->getPost('ay');
        $metin      = $this->request->getPost('not');

        $mukellef = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        (new AylikNotModel())->notKaydet($mukellefId, $yil, $ay, $metin);

        return $this->jsonBasarili('Not kaydedildi.', ['not' => $metin]);
    }

    public function excel()
    {
        // Ekranla aynı dönem mantığı: seçilen ay - kaydırma
        $varsayilan = $this->varsayilanSecilenAy();
        $donem = $this->model->donemHesapla(
            (int) ($this->request->getGet('yil') ?? $varsayilan['yil']),
            (int) ($this->request->getGet('ay') ?? $varsayilan['ay'])
        );
        $yil = $donem['yil'];
        $ay  = $donem['ay'];

        $filtre = $this->filtreAl();
        // Dışa aktarımda sayfalama YOK — tüm kayıtlar
        $cizelge = $this->model->cizelge($yil, $ay, $filtre);

        $csv = "\xEF\xBB\xBF";
        $csv .= 'Mükellef;VKN/TCKN';

        foreach ($cizelge['turler'] as $t) {
            $csv .= ';' . str_replace(';', ',', $t['kisa_ad']);
        }
        $csv .= ";Not\n";

        $notModel = new AylikNotModel();

        // Takip dışı hücrelerin çıktıdaki karşılığı (Ayarlar'dan değiştirilir)
        $muafEtiket = (string) ((new AyarModel())->tumu()['evrak_muaf_etiket'] ?? 'Takip dışı');

        foreach ($cizelge['mukellefler'] as $m) {
            $mid = (int) $m['id'];
            $csv .= str_replace(';', ',', (string) $m['unvan']) . ';'
                 . ($m['vergi_kimlik_no'] ?: $m['tc_kimlik_no']);

            foreach ($cizelge['turler'] as $t) {
                $tid   = (int) $t['id'];
                $h     = $cizelge['matris'][$mid][$tid] ?? null;
                $durum = EvrakTakipModel::etkinDurum($h, isset($cizelge['muafiyet'][$mid][$tid]));

                $csv .= ';' . match ($durum) {
                    'GELDI' => 'Geldi',
                    'YOK'   => str_replace(';', ',', $muafEtiket),
                    default => 'Gelmedi',
                };
            }

            $not = (string) $notModel->notAl($mid, $yil, $ay);
            $csv .= ';' . str_replace([';', "\n", "\r"], [',', ' ', ''], $not) . "\n";
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="evrak_takip_' . $yil . '_' . $ay . '.csv"')
            ->setBody($csv);
    }

    public function yazdir()
    {
        $varsayilan = $this->varsayilanSecilenAy();
        $secilenYil = (int) ($this->request->getGet('yil') ?? $varsayilan['yil']);
        $secilenAy  = (int) ($this->request->getGet('ay') ?? $varsayilan['ay']);

        // Ekranla aynı dönem mantığı
        $donem = $this->model->donemHesapla($secilenYil, $secilenAy);
        $yil   = $donem['yil'];
        $ay    = $donem['ay'];

        $filtre = $this->filtreAl();
        // Dışa aktarımda sayfalama YOK — tüm kayıtlar
        $cizelge = $this->model->cizelge($yil, $ay, $filtre);

        $notModel = new AylikNotModel();
        $notlar   = [];

        foreach ($cizelge['mukellefler'] as $m) {
            $notlar[(int) $m['id']] = $notModel->notAl((int) $m['id'], $yil, $ay);
        }

        return view('evrak/yazdir', [
            'yil'         => $yil,
            'ay'          => $ay,
            'mukellefler' => $cizelge['mukellefler'],
            'turler'      => $cizelge['turler'],
            'matris'      => $cizelge['matris'],
            'muafiyet'    => $cizelge['muafiyet'] ?? [],
            'notlar'      => $notlar,
        ]);
    }
}
