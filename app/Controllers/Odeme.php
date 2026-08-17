<?php

namespace App\Controllers;

use App\Models\AyarModel;
use App\Models\BeyannameTakipModel;
use App\Models\DamgaTutarModel;
use App\Models\MukellefModel;
use App\Models\MusavirModel;
use App\Models\OdemeListesiModel;
use App\Models\OzelOdemeModel;

class Odeme extends BaseController
{
    protected BeyannameTakipModel $model;

    public function __construct()
    {
        $this->model = new BeyannameTakipModel();
    }

    // -----------------------------------------------------------------
    public function index()
    {
        $filtre = $this->filtreAl();

        // Aylık tekrar eden kalemleri (Bağkur vb.) seçilen ay için otomatik üret.
        // Belirli bir ay seçilmişse çalışır; "tüm yıl" görünümünde üretim yapılmaz.
        $uretilen = 0;

        if (! empty($filtre['ay'])) {
            $uretilen = (new OzelOdemeModel())->tekrarlariUret(
                (int) $filtre['yil'],
                (int) $filtre['ay'],
                $this->ozelKalemSahibi()
            );
        }

        $sonuc = $this->model->odemeListesi($filtre);

        // -------------------------------------------------------------
        //  SAYFALAMA (mükellef grubu bazında)
        //
        //  Toplam tutarlar HER ZAMAN tüm listeden hesaplanır; yalnızca
        //  ekrana basılan grup sayısı sınırlanır. Böylece "Genel Toplam"
        //  sayfa değiştikçe değişmez.
        // -------------------------------------------------------------
        $tumGruplar = $sonuc['gruplar'];
        $grupSayisi = count($tumGruplar);
        $adet       = $this->grupAdediBelirle();
        $gosterilen = array_slice($tumGruplar, 0, $adet);

        // Bildirim bağlantıları ekrandaki filtreyi taşımalı. _gruplar parçası
        // $this->include() ile çağrıldığında üst görünümün yerel değişkenlerini
        // GÖREMEZ; bu yüzden 'qs' controller'dan verilir.
        $qsIlk = http_build_query(array_filter([
            'yil' => $filtre['yil'], 'ay' => $filtre['ay'],
            'odendi' => $filtre['odendi'], 'q' => $filtre['q'],
        ], static fn ($v) => $v !== null && $v !== ''));

        return $this->goster('odeme/index', [
            'gruplar'    => $gosterilen,
            'toplam'     => $sonuc['toplam'],
            'filtre'     => $filtre,
            'qs'         => $qsIlk,
            'musavirler' => $this->secilebilirMusavirler(),
            'damgaYil'   => (new DamgaTutarModel())->yilHaritasi((int) $filtre['yil']),
            'mukellefler'=> $this->mukellefSecenekleri(),
            'onerilen'   => OzelOdemeModel::ONERILEN,
            'tekrarUretilen' => $uretilen,
            // Sayfalama bilgileri
            'grupToplam'  => $grupSayisi,
            'grupAdedi'   => $adet,
            'adetSecenek' => self::GRUP_ADETLERI,
            'dahaVar'     => $grupSayisi > $adet,
        ], 'Ödeme Listesi');
    }

    /**
     * AJAX: sonsuz kaydırma — sonraki mükellef gruplarını HTML döndürür.
     * Grup çizimi tek yerden (odeme/_gruplar) yapılır ki sonradan gelen
     * gruplar ilk yüklenenlerle birebir aynı görünsün.
     */
    public function dahaFazla()
    {
        $filtre = $this->filtreAl();
        $ofset  = max(0, (int) $this->request->getGet('ofset'));
        $adet   = $this->grupAdediBelirle();

        $sonuc  = $this->model->odemeListesi($filtre);
        $tumu   = $sonuc['gruplar'];
        $parca  = array_slice($tumu, $ofset, $adet);

        $qs = http_build_query(array_filter([
            'yil' => $filtre['yil'], 'ay' => $filtre['ay'],
            'odendi' => $filtre['odendi'], 'q' => $filtre['q'],
        ], static fn ($v) => $v !== null && $v !== ''));

        $html = view('odeme/_gruplar', [
            'gruplar' => $parca,
            'filtre'  => $filtre,
            'qs'      => $qs,
        ]);

        return $this->response->setJSON([
            'durum'   => true,
            'html'    => $html,
            'adet'    => count($parca),
            'ofset'   => $ofset + count($parca),
            'toplam'  => count($tumu),
            'dahaVar' => ($ofset + count($parca)) < count($tumu),
        ]);
    }

    /** Sayfa başına gösterilecek MÜKELLEF GRUBU sayısı */
    public const GRUP_ADETLERI = [25, 50, 100, 250];

    public const VARSAYILAN_GRUP = 25;

    protected function grupAdediBelirle(): int
    {
        $ham = $this->request->getGet('adet');

        if ($ham !== null && in_array((int) $ham, self::GRUP_ADETLERI, true)) {
            setcookie('odeme_adet', (string) (int) $ham, time() + 31536000, '/');

            return (int) $ham;
        }

        $cerez = (int) ($_COOKIE['odeme_adet'] ?? 0);

        return in_array($cerez, self::GRUP_ADETLERI, true) ? $cerez : self::VARSAYILAN_GRUP;
    }

    /**
     * AJAX/GET: tekrarlı kalemleri seçilen aya elle getir.
     * (Otomatik üretim zaten çalışır; bu düğme tutar değişikliğinden sonra
     *  veya kullanıcı emin olmak istediğinde kullanılır.)
     */
    public function tekrarUret()
    {
        $filtre = $this->filtreAl();

        if (empty($filtre['ay'])) {
            return redirect()->to(site_url('odeme'))
                ->with('hata', 'Tekrarlı kalemleri getirmek için önce bir ay seçin.');
        }

        $adet = (new OzelOdemeModel())->tekrarlariUret(
            (int) $filtre['yil'],
            (int) $filtre['ay'],
            $this->ozelKalemSahibi()
        );

        $adres = site_url('odeme?yil=' . (int) $filtre['yil'] . '&ay=' . (int) $filtre['ay']);

        return redirect()->to($adres)->with(
            $adet > 0 ? 'basari' : 'bilgi',
            $adet > 0
                ? $adet . ' tekrarlı ödeme kalemi bu aya eklendi.'
                : 'Bu ay için eklenecek yeni tekrarlı kalem bulunamadı (hepsi zaten mevcut).'
        );
    }

    /** Bir tekrar serisini durdurur */
    public function tekrarDurdur(int $id)
    {
        $model = new OzelOdemeModel();
        $kayit = $model->find($id);

        if ($kayit === null || ! $this->ozelYetkisi($kayit)) {
            return redirect()->to(site_url('odeme'))->with('hata', 'Kayıt bulunamadı.');
        }

        $silinen = $model->tekrariDurdur($id);

        return redirect()->back()->with(
            'basari',
            'Tekrar durduruldu.' . ($silinen > 0
                ? ' Gelecek aylara ait ' . $silinen . ' ödenmemiş kalem kaldırıldı.'
                : '')
        );
    }

    /**
     * Özel kalemler kullanıcıya özeldir; yönetici hepsini görür.
     * Üretim de aynı kapsamda yapılmalı.
     */
    protected function ozelKalemSahibi(): ?int
    {
        return $this->adminMi() ? null : (int) $this->aktifKullanici['id'];
    }

    /**
     * Başlangıç ayından bu aya (+1 ay ileriye) kadar tekrarları üretir.
     * Böylece geçmişe tarihli tekrarlı kalem eklense bile aradaki aylar dolar.
     *
     * @return int Üretilen toplam kalem sayısı
     */
    protected function tekrarlariBugunekadarUret(OzelOdemeModel $model, int $basYil, int $basAy): int
    {
        $imlec  = mktime(0, 0, 0, $basAy + 1, 1, $basYil);
        // Bir ay ileriye kadar üret (gelecek ayın listesi de hazır olsun)
        $bitis  = mktime(0, 0, 0, (int) date('n') + 1, 1, (int) date('Y'));
        $toplam = 0;
        $guvenlik = 0;

        while ($imlec <= $bitis && $guvenlik < 36) {
            $toplam += $model->tekrarlariUret(
                (int) date('Y', $imlec),
                (int) date('n', $imlec),
                $this->ozelKalemSahibi()
            );

            $imlec = mktime(0, 0, 0, (int) date('n', $imlec) + 1, 1, (int) date('Y', $imlec));
            $guvenlik++;
        }

        return $toplam;
    }

    // -----------------------------------------------------------------
    //  AJAX: tahakkuk tutarı kaydet
    // -----------------------------------------------------------------
    public function tahakkukKaydet()
    {
        if (! $this->tahakkukYetkisiVarMi()) {
            return $this->jsonHata('Bu işlem için yetkiniz yok.', 403);
        }

        $id = (int) $this->request->getPost('id');

        $kayit = $this->model->find($id);

        if ($kayit === null) {
            return $this->jsonHata('Kayıt bulunamadı.', 404);
        }

        if (! $this->kayitYetkisi($kayit)) {
            return $this->jsonHata('Bu kayıt için yetkiniz yok.', 403);
        }

        $ham = trim((string) $this->request->getPost('tutar'));

        if ($ham === '') {
            $tutar = null;
        } else {
            // "1.234,56" ve "1234.56" biçimlerini normalize et
            $temiz = str_replace(' ', '', $ham);

            if (str_contains($temiz, ',')) {
                $temiz = str_replace('.', '', $temiz);
                $temiz = str_replace(',', '.', $temiz);
            }

            if (! is_numeric($temiz)) {
                return $this->jsonHata('Geçerli bir tutar giriniz.');
            }

            $tutar = round((float) $temiz, 2);

            if ($tutar < 0) {
                return $this->jsonHata('Tutar negatif olamaz.');
            }
        }

        $fisNo = $this->request->getPost('fis_no');

        if (! $this->model->tahakkukKaydet($id, $tutar, $fisNo ?: null)) {
            return $this->jsonHata('Tahakkuk kaydedilemedi.');
        }

        $yeni  = $this->model->find($id);
        $damga = (float) $yeni['damga_tutari'];

        $ek = [
            'tutar'          => $tutar,
            'tutar_f'        => $tutar === null ? '' : number_format($tutar, 2, ',', '.'),
            'damga'          => $damga,
            'damga_f'        => number_format($damga, 2, ',', '.'),
            'odenecek'       => ($tutar ?? 0) + $damga,
            'odenecek_f'     => number_format(($tutar ?? 0) + $damga, 2, ',', '.'),
        ];

        // -------------------------------------------------------------
        //  MUHSGK ile birlikte SGK primi
        //
        //  MUHSGK penceresinde ayrıca "SGK Prim Tutarı" alanı vardır.
        //  Alan gönderilmişse eşleşen SGK satırı da AYNI istekte yazılır;
        //  kullanıcı ikinci kez satır açmak zorunda kalmaz.
        //
        //  Alan hiç gönderilmediyse (eski şablon, SGK'sı olmayan mükellef)
        //  eş kayda DOKUNULMAZ.
        // -------------------------------------------------------------
        if ($this->request->getPost('sgk_tutar') !== null) {
            $ek['sgk'] = $this->sgkTarafiniYaz($id, $this->request->getPost('sgk_tutar'),
                $this->request->getPost('sgk_fis_no'));
        }

        return $this->jsonBasarili('Tahakkuk kaydedildi.', $ek);
    }

    /**
     * MUHSGK penceresinden gelen SGK prim tutarını eşleşen SGK satırına yazar.
     *
     * Birden çok eş varsa (üç aylık MUHSGK → üç SGK satırı) tutar İLK
     * eşleşen satıra yazılır; diğerleri kullanıcı tarafından ayrı girilir.
     * Bunun nedeni tutarın aylara nasıl bölüneceğini yalnızca kullanıcının
     * bilmesidir — program kendiliğinden bölerse yanlış veri üretir.
     *
     * @return array Arayüzün SGK satırını tazelemesi için özet
     */
    protected function sgkTarafiniYaz(int $muhsgkId, $hamTutar, $fisNo): array
    {
        $kayit = $this->model->cizelgeKaydi($muhsgkId);

        if ($kayit === null || ! $this->model->muhsgkMi($kayit)) {
            return ['yazildi' => false, 'neden' => 'Bu kayıt MUHSGK değil.'];
        }

        $esler = $this->model->esKayitlar($kayit);

        if ($esler === []) {
            return ['yazildi' => false, 'neden' => 'Eşleşen SGK kaydı yok.'];
        }

        $hedef = null;

        foreach ($esler as $e) {
            if ($this->kayitYetkisi($e)) {
                $hedef = $e;
                break;
            }
        }

        if ($hedef === null) {
            return ['yazildi' => false, 'neden' => 'SGK kaydı için yetkiniz yok.'];
        }

        $ham = trim((string) $hamTutar);

        // Boş gönderildiyse SGK tarafı temizlenir
        if ($ham === '') {
            $this->model->tahakkukKaydet((int) $hedef['id'], null);

            return [
                'yazildi' => true,
                'id'      => (int) $hedef['id'],
                'tutar_f' => '',
                'damga'   => 0.0,
                'silindi' => true,
            ];
        }

        $tutar = trParaCoz($ham);

        if ($tutar === null || $tutar < 0) {
            return ['yazildi' => false, 'neden' => 'SGK tutarı geçersiz.'];
        }

        if (! $this->model->tahakkukKaydet((int) $hedef['id'], round($tutar, 2), $fisNo ?: null)) {
            return ['yazildi' => false, 'neden' => 'SGK tahakkuku kaydedilemedi.'];
        }

        $guncel = $this->model->find((int) $hedef['id']);

        return [
            'yazildi'  => true,
            'id'       => (int) $hedef['id'],
            'tur'      => $hedef['tur_kisa'],
            'donem'    => $hedef['donem_adi'],
            'tutar_f'  => number_format((float) $guncel['tahakkuk_tutari'], 2, ',', '.'),
            'damga'    => (float) $guncel['damga_tutari'],
            'durum'    => $guncel['durum'],
            // Üç aylık MUHSGK'da kalan aylar kullanıcıya hatırlatılır
            'kalan'    => max(0, count($esler) - 1),
        ];
    }

    /** AJAX: ödendi işaretle */
    public function odemeIsaretle()
    {
        $id     = (int) $this->request->getPost('id');
        $odendi = (int) $this->request->getPost('odendi') === 1;

        $kayit = $this->model->find($id);

        if ($kayit === null) {
            return $this->jsonHata('Kayıt bulunamadı.', 404);
        }

        if (! $this->kayitYetkisi($kayit)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        $this->model->odemeIsaretle($id, $odendi);

        return $this->jsonBasarili($odendi ? 'Ödendi olarak işaretlendi.' : 'Ödeme işareti kaldırıldı.', [
            'odendi'       => $odendi ? 1 : 0,
            'odeme_tarihi' => $odendi ? date('d.m.Y') : null,
        ]);
    }

    // -----------------------------------------------------------------
    //  Dışa aktarma
    // -----------------------------------------------------------------
    public function excel()
    {
        $filtre = $this->filtreAl();
        $sonuc  = $this->model->odemeListesi($filtre);

        $csv = "\xEF\xBB\xBF";
        $csv .= "Mükellef;VKN/TCKN;Vergi Dairesi;Beyanname;Dönem;Son Tarih;Tahakkuk (Damga Hariç);Damga Vergisi;Ödenecek;Durum\n";

        foreach ($sonuc['gruplar'] as $g) {
            foreach ($g['satirlar'] as $s) {
                $csv .= implode(';', [
                    str_replace(';', ',', (string) $g['mukellef']['unvan']),
                    $g['mukellef']['vkn'],
                    str_replace(';', ',', (string) $g['mukellef']['vergi_dairesi']),
                    $s['tur_kisa'],
                    $s['donem_adi'],
                    date('d.m.Y', strtotime($s['son_tarih'])),
                    number_format((float) $s['tahakkuk_tutari'], 2, ',', '.'),
                    number_format((float) $s['hesaplanan_damga'], 2, ',', '.'),
                    number_format((float) $s['odenecek'], 2, ',', '.'),
                    (int) $s['odendi'] === 1 ? 'Ödendi' : 'Ödenmedi',
                ]) . "\n";
            }

            // Mükellef ara toplamı
            $csv .= str_replace(';', ',', (string) $g['mukellef']['unvan']) . ' TOPLAM;;;;;;'
                . number_format($g['toplam']['tahakkuk'], 2, ',', '.') . ';'
                . number_format($g['toplam']['damga'], 2, ',', '.') . ';'
                . number_format($g['toplam']['genel_tum'] ?? $g['toplam']['genel'], 2, ',', '.') . ";\n\n";
        }

        $csv .= "GENEL TOPLAM;;;;;;"
            . number_format($sonuc['toplam']['tahakkuk'], 2, ',', '.') . ';'
            . number_format($sonuc['toplam']['damga'], 2, ',', '.') . ';'
            . number_format($sonuc['toplam']['genel'], 2, ',', '.') . ";\n";

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="odeme_listesi_' . date('Ymd_His') . '.csv"')
            ->setBody($csv);
    }

    public function yazdir()
    {
        $filtre = $this->filtreAl();
        $sonuc  = $this->model->odemeListesi($filtre);

        // bicim=detay (varsayılan): yatay tek tablo, her satır bir ödeme
        // bicim=ozet            : çapraz tablo, satır mükellef / sütun tür
        $bicim = $this->request->getGet('bicim') === 'ozet' ? 'ozet' : 'detay';

        $veri = [
            'gruplar' => $sonuc['gruplar'],
            'toplam'  => $sonuc['toplam'],
            'filtre'  => $filtre,
            'bicim'   => $bicim,
        ];

        if ($bicim === 'ozet') {
            $veri += $this->caprazTablo($sonuc['gruplar']);
        }

        return view('odeme/yazdir', $veri);
    }

    /**
     * Çapraz (pivot) tablo verisi hazırlar:
     *   satırlar = mükellef, sütunlar = beyanname türü + özel kalem başlıkları
     *
     * @return array{sutunlar:array<string,string>, satirlar:array, sutunToplam:array}
     */
    protected function caprazTablo(array $gruplar): array
    {
        $sutunlar    = [];   // anahtar => başlık
        $satirlar    = [];
        $sutunToplam = [];

        foreach ($gruplar as $g) {
            $hucre = [];

            foreach ($g['satirlar'] as $s) {
                $anahtar = 'T' . (int) $s['beyanname_turu_id'];
                $sutunlar[$anahtar] = $s['tur_kisa'];
                $hucre[$anahtar]    = ($hucre[$anahtar] ?? 0) + (float) $s['odenecek'];
            }

            foreach ($g['ozel'] ?? [] as $o) {
                // Özel kalemler başlığa göre gruplanır (Bağkur, MTV…)
                $anahtar = 'O' . mb_strtolower(trim((string) $o['baslik']), 'UTF-8');
                $sutunlar[$anahtar] = $o['baslik'];
                $hucre[$anahtar]    = ($hucre[$anahtar] ?? 0) + (float) $o['tutar'];
            }

            foreach ($hucre as $a => $v) {
                $sutunToplam[$a] = ($sutunToplam[$a] ?? 0) + $v;
            }

            $satirlar[] = [
                'mukellef' => $g['mukellef'],
                'hucre'    => $hucre,
                'toplam'   => (float) ($g['toplam']['genel_tum'] ?? $g['toplam']['genel']),
            ];
        }

        // Sütunları düzenli sırala: önce beyanname türleri, sonra özel kalemler
        uksort($sutunlar, static function ($a, $b) use ($sutunlar) {
            $aOzel = $a[0] === 'O';
            $bOzel = $b[0] === 'O';

            if ($aOzel !== $bOzel) {
                return $aOzel ? 1 : -1;
            }

            return strcmp((string) $sutunlar[$a], (string) $sutunlar[$b]);
        });

        return [
            'sutunlar'    => $sutunlar,
            'satirlar'    => $satirlar,
            'sutunToplam' => $sutunToplam,
        ];
    }

    /**
     * Tek mükellefin ödeme bildirimi (yazdırılıp mükellefe verilebilir).
     *
     * ?ucret=1 -> mükellef kartındaki aylık muhasebe ücreti toplama eklenir.
     */
    public function bildirim(int $mukellefId)
    {
        $veri = $this->bildirimVerisi($mukellefId);

        if ($veri === null) {
            return redirect()->to(site_url('odeme'))->with('hata', 'Mükellef bulunamadı.');
        }

        return view('odeme/bildirim', $veri);
    }

    /**
     * Bildirim ekranı ve e-posta için ortak veriyi kurar.
     *
     * @return array|null null = mükellef yok / yetki yok
     */
    protected function bildirimVerisi(int $mukellefId): ?array
    {
        $mukellef = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return null;
        }

        $filtre                = $this->filtreAl();
        $filtre['mukellef_id'] = $mukellefId;
        $sonuc                 = $this->model->odemeListesi($filtre);

        // Muhasebe ücreti dahil edilsin mi?
        $ucretParam = $this->request->getGet('ucret');
        $ucretDahil = $ucretParam !== null
            ? $ucretParam === '1'
            : (int) (new AyarModel())->oku('bildirim_ucret_varsayilan', 0) === 1;

        $ucret = (float) ($mukellef['muhasebe_ucreti'] ?? 0);

        return [
            'grup'       => $sonuc['gruplar'][0] ?? null,
            'filtre'     => $filtre,
            'mukellef'   => $mukellef,
            'ucretDahil' => $ucretDahil && $ucret > 0,
            'ucret'      => $ucret,
            'ucretVar'   => $ucret > 0,
        ];
    }

    /**
     * Ödeme bildirimini mükellef kartındaki e-posta adresine gönderir.
     *
     * POST ile çağrılır (CSRF korumalı). ?ucret=1 -> muhasebe ücreti dahil.
     * Ayarlar:
     *   mail_etkin            = 1 olmalı
     *   mail_gonderici_eposta = boşsa app/Config/Email.php fromEmail kullanılır
     *   mail_konu             = {donem} ve {unvan} yer tutucuları destekler
     */
    public function bildirimMail(int $mukellefId)
    {
        $veri = $this->bildirimVerisi($mukellefId);

        if ($veri === null) {
            return redirect()->to(site_url('odeme'))->with('hata', 'Mükellef bulunamadı.');
        }

        $mukellef = $veri['mukellef'];
        $donem    = (! empty($veri['filtre']['ay']) ? ayAdi((int) $veri['filtre']['ay']) . ' ' : '')
                  . ($veri['filtre']['yil'] ?? '');

        $ayar = (new AyarModel())->tumu();

        // ---- 1) Ayar kapalı mı? ----
        if ((int) ($ayar['mail_etkin'] ?? 0) !== 1) {
            return redirect()->back()
                ->with('hata', 'E-posta gönderimi kapalı. Tanımlar → Ayarlar → "mail_etkin" ayarını açın.');
        }

        // ---- 2) Alıcı e-postası ----
        $hedef = trim((string) ($mukellef['eposta'] ?? ''));

        if ($hedef === '' || ! filter_var($hedef, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()
                ->with('hata', "Mükellef kartında geçerli bir e-posta tanımlı değil. "
                    . 'Mükellef kartından e-posta adresi girin.');
        }

        // ---- 3) Gönderen bilgisi ----
        $emailConfig = config('Email');

        $gondericiEposta = trim((string) ($ayar['mail_gonderici_eposta'] ?? ''));
        if ($gondericiEposta === '') {
            $gondericiEposta = $emailConfig->fromEmail;
        }

        $gondericiAd = trim((string) ($ayar['mail_gonderici_ad'] ?? ''));
        if ($gondericiAd === '') {
            $gondericiAd = trim((string) ($ayar['firma_adi'] ?? ''));
        }
        if ($gondericiAd === '') {
            $gondericiAd = $emailConfig->fromName;
        }

        if ($gondericiEposta === '' || ! filter_var($gondericiEposta, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()
                ->with('hata', 'Gönderici e-posta adresi tanımlı değil. Tanımlar → Ayarlar → '
                    . '"mail_gonderici_eposta" alanını doldurun (veya app/Config/Email.php fromEmail).');
        }

        // ---- 4) Konu ----
        $konuSablon = trim((string) ($ayar['mail_konu'] ?? ''));
        if ($konuSablon === '') {
            $konuSablon = 'Ödeme Bildirimi — {donem}';
        }
        $konu = str_replace(
            ['{donem}', '{unvan}'],
            [$donem, $mukellef['unvan'] ?? ''],
            $konuSablon
        );

        // ---- 5) İçerik (bildirim_mail.php görünümünü dizeye çevir) ----
        $veri['firmaAdi'] = trim((string) ($ayar['firma_adi'] ?? ''));
        $govde = view('odeme/bildirim_mail', $veri);

        // ---- 6) Gönder ----
        $email = \Config\Services::email();

        $email->setFrom($gondericiEposta, $gondericiAd);
        $email->setTo($hedef);
        $email->setSubject($konu);
        $email->setMessage($govde);
        $email->setMailType('html');

        if ($email->send()) {
            return redirect()->back()
                ->with('basari', "Ödeme bildirimi gönderildi: {$hedef} ({$donem})");
        }

        // Hata ayrıntısı: son hata dizesini kısaca göster (geliştirme kolaylığı)
        $hata = (string) ($email->printDebugger(['headers', 'subject']) ?: 'Bilinmeyen e-posta hatası.');
        $hata = trim(preg_replace('/\s+/', ' ', strip_tags($hata)));

        return redirect()->back()
            ->with('hata', 'E-posta gönderilemedi: ' . $hata);
    }

    // =================================================================
    //  ÖZEL ÖDEME KALEMLERİ (Bağkur, MTV, harç vb.)
    // =================================================================

    public function ozelKaydet()
    {
        $model = new OzelOdemeModel();
        $id    = (int) $this->request->getPost('id');

        $mukellefId = (int) $this->request->getPost('mukellef_id');
        $mukellef   = (new MukellefModel())->find($mukellefId);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->back()->withInput()->with('hata', 'Bu mükellef için yetkiniz yok.');
        }

        $veri = [
            'mukellef_id'   => $mukellefId,
            'baslik'        => trim((string) $this->request->getPost('baslik')),
            'aciklama'      => $this->request->getPost('aciklama'),
            'tutar'         => $this->paraCoz($this->request->getPost('tutar')) ?? 0,
            'son_tarih'     => $this->request->getPost('son_tarih'),
            'donem_etiketi' => $this->request->getPost('donem_etiketi') ?: null,
            'durum'         => $this->request->getPost('durum') ?: 'ONAYLANDI',
            'tekrar'        => $this->request->getPost('tekrar') === 'AYLIK' ? 'AYLIK' : 'YOK',
            // Boş bırakılırsa süresiz tekrar eder
            'tekrar_bitis'  => $this->request->getPost('tekrar_bitis') ?: null,
        ];

        // Tekrar kapalıysa bitiş tarihi anlamsızdır
        if ($veri['tekrar'] === 'YOK') {
            $veri['tekrar_bitis'] = null;
        }

        if ($id > 0) {
            $mevcut = $model->find($id);

            if ($mevcut === null || ! $this->ozelYetkisi($mevcut)) {
                return redirect()->to(site_url('odeme'))->with('hata', 'Kayıt bulunamadı.');
            }

            $ok = $model->update($id, $veri);
        } else {
            $veri['kaydeden_id'] = (int) $this->aktifKullanici['id'];
            $ok                  = $model->insert($veri);
        }

        if (! $ok) {
            return redirect()->back()->withInput()->with('hatalar', $model->errors());
        }

        $ay  = (int) date('n', strtotime((string) $veri['son_tarih']));
        $yil = (int) date('Y', strtotime((string) $veri['son_tarih']));

        $mesaj = $id > 0 ? 'Ödeme kalemi güncellendi.' : 'Ödeme kalemi eklendi.';

        // Tekrarlı kalem eklendiyse, içinde bulunduğumuz aya kadar olan
        // dönemleri hemen üret — kullanıcı listeyi açtığında hazır olsun.
        if ($veri['tekrar'] === 'AYLIK') {
            $uretilen = $this->tekrarlariBugunekadarUret($model, $yil, $ay);

            if ($uretilen > 0) {
                $mesaj .= ' İzleyen aylar için ' . $uretilen . ' kalem otomatik oluşturuldu.';
            } else {
                $mesaj .= ' Her ay otomatik tekrar edecek.';
            }
        }

        return redirect()->to(site_url('odeme?yil=' . $yil . '&ay=' . $ay))
            ->with('basari', $mesaj);
    }

    public function ozelSil(int $id)
    {
        $model = new OzelOdemeModel();
        $kayit = $model->find($id);

        if ($kayit === null || ! $this->ozelYetkisi($kayit)) {
            return redirect()->to(site_url('odeme'))->with('hata', 'Kayıt bulunamadı.');
        }

        $model->delete($id);

        return redirect()->back()->with('basari', 'Ödeme kalemi silindi.');
    }

    /** AJAX: özel kalem ödendi işaretle */
    public function ozelOdendi()
    {
        $model  = new OzelOdemeModel();
        $id     = (int) $this->request->getPost('id');
        $odendi = (int) $this->request->getPost('odendi') === 1;

        $kayit = $model->find($id);

        if ($kayit === null || ! $this->ozelYetkisi($kayit)) {
            return $this->jsonHata('Yetkiniz yok.', 403);
        }

        $model->odemeIsaretle($id, $odendi);

        return $this->jsonBasarili($odendi ? 'Ödendi olarak işaretlendi.' : 'Ödeme işareti kaldırıldı.', [
            'odendi' => $odendi ? 1 : 0,
        ]);
    }

    /**
     * Özel ödeme kalemi yetkisi.
     * Kalemler kullanıcıya özeldir: yalnızca oluşturan kişi (ve yönetici)
     * görüntüleyip düzenleyebilir.
     */
    protected function ozelYetkisi(array $kayit): bool
    {
        if ($this->adminMi()) {
            return true;
        }

        if ((int) ($kayit['kaydeden_id'] ?? 0) !== (int) $this->aktifKullanici['id']) {
            return false;
        }

        $mukellef = (new MukellefModel())->find($kayit['mukellef_id']);

        return $mukellef !== null && $this->mukellefeErisebilirMi($mukellef);
    }

    /** Yetkili olunan mükellefler [id => unvan] */
    protected function mukellefSecenekleri(): array
    {
        $rows = (new MukellefModel())->listele([
            'musavir_id' => $this->musavirFiltresi(),
            'durum'      => 'aktif',
        ]);

        $out = [];

        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r['unvan'];
        }

        return $out;
    }

    /** "1.250,50" / "1250.50" -> float */
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

    // =================================================================
    //  KAYITLI ÖDEME LİSTELERİ (kullanıcıya özel)
    // =================================================================

    /** Kullanıcının listeleri */
    public function listeler()
    {
        $model = new OdemeListesiModel();

        return $this->goster('odeme/listeler', [
            'listeler'    => $model->kullaniciListeleri((int) $this->aktifKullanici['id'], $this->adminMi()),
            'mukellefler' => $this->mukellefSecenekleri(),
            'musavirler'  => $this->secilebilirMusavirler(),
        ], 'Ödeme Listelerim');
    }

    public function listeKaydet()
    {
        $model = new OdemeListesiModel();
        $id    = (int) $this->request->getPost('id');

        $veri = [
            'kullanici_id' => (int) $this->aktifKullanici['id'],
            'musavir_id'   => $this->request->getPost('musavir_id') ?: null,
            'ad'           => trim((string) $this->request->getPost('ad')),
            'aciklama'     => $this->request->getPost('aciklama'),
            // Dönem listeye gömülü değildir; bunlar yalnızca VARSAYILAN'dır.
            'yil'          => $this->request->getPost('yil') !== '' ? (int) $this->request->getPost('yil') : null,
            'ay'           => $this->request->getPost('ay') !== '' ? (int) $this->request->getPost('ay') : null,
            'ucret_dahil'  => (int) ($this->request->getPost('ucret_dahil') ?? 0),
            'ozel_dahil'   => (int) ($this->request->getPost('ozel_dahil') ?? 0),
        ];

        if ($id > 0) {
            $mevcut = $model->find($id);

            if ($mevcut === null
                || ! $model->erisebilirMi($mevcut, (int) $this->aktifKullanici['id'], $this->adminMi())) {
                return redirect()->to(site_url('odeme/listeler'))->with('hata', 'Liste bulunamadı.');
            }

            // Sahiplik değişmesin
            unset($veri['kullanici_id']);
            $ok      = $model->update($id, $veri);
            $listeId = $id;
        } else {
            $ok      = $model->insert($veri);
            $listeId = (int) $model->getInsertID();
        }

        if (! $ok) {
            return redirect()->back()->withInput()->with('hatalar', $model->errors());
        }

        // Seçilen mükellefler — yalnızca erişim yetkisi olanlar
        $secilen = array_map('intval', (array) ($this->request->getPost('mukellefler') ?? []));
        $izinli  = array_keys($this->mukellefSecenekleri());
        $secilen = array_values(array_intersect($secilen, $izinli));

        $model->mukellefleriKaydet($listeId, $secilen);

        return redirect()->to(site_url('odeme/liste/' . $listeId))
            ->with('basari', $id > 0 ? 'Liste güncellendi.' : 'Liste oluşturuldu.');
    }

    /** Listeyi görüntüle (güncel tutarlarla) */
    public function liste(int $id)
    {
        $model  = new OdemeListesiModel();
        $liste  = $model->find($id);

        if ($liste === null
            || ! $model->erisebilirMi($liste, (int) $this->aktifKullanici['id'], $this->adminMi())) {
            return redirect()->to(site_url('odeme/listeler'))->with('hata', 'Liste bulunamadı.');
        }

        // Dönem URL'den gelir; yoksa listenin varsayılanı kullanılır.
        [$yil, $ay] = $this->donemAl($model->varsayilanDonem($liste));

        $sonuc = $model->hesapla($liste, $yil, $ay);

        return $this->goster('odeme/liste_detay', [
            'liste'      => $liste,
            'yil'        => $yil,
            'ay'         => $ay,
            'satirlar'   => $sonuc['satirlar'],
            'toplam'     => $sonuc['toplam'],
            'secilenler' => $model->mukellefIdleri($id),
            'mukellefler'=> $this->mukellefSecenekleri(),
            'musavirler' => $this->secilebilirMusavirler(),
        ], $liste['ad']);
    }

    /** Yazdırılabilir çıktı */
    public function listeYazdir(int $id)
    {
        $model = new OdemeListesiModel();
        $liste = $model->find($id);

        if ($liste === null
            || ! $model->erisebilirMi($liste, (int) $this->aktifKullanici['id'], $this->adminMi())) {
            return redirect()->to(site_url('odeme/listeler'));
        }

        [$yil, $ay] = $this->donemAl($model->varsayilanDonem($liste));

        $sonuc   = $model->hesapla($liste, $yil, $ay);
        $musavir = ! empty($liste['musavir_id']) ? (new MusavirModel())->find($liste['musavir_id']) : null;

        return view('odeme/liste_yazdir', [
            'liste'    => $liste,
            'yil'      => $yil,
            'ay'       => $ay,
            'satirlar' => $sonuc['satirlar'],
            'toplam'   => $sonuc['toplam'],
            'musavir'  => $musavir,
            'detayli'  => $this->request->getGet('detay') === '1',
        ]);
    }

    public function listeExcel(int $id)
    {
        $model = new OdemeListesiModel();
        $liste = $model->find($id);

        if ($liste === null
            || ! $model->erisebilirMi($liste, (int) $this->aktifKullanici['id'], $this->adminMi())) {
            return redirect()->to(site_url('odeme/listeler'));
        }

        [$yil, $ay] = $this->donemAl($model->varsayilanDonem($liste));

        $sonuc = $model->hesapla($liste, $yil, $ay);

        $csv = "\xEF\xBB\xBF";
        $csv .= "Sıra;Mükellef;VKN/TCKN;Vergi Dairesi;Beyanname;Özel Ödemeler;Muhasebe Ücreti;TOPLAM\n";

        $i = 1;

        foreach ($sonuc['satirlar'] as $s) {
            $csv .= implode(';', [
                $i++,
                str_replace(';', ',', (string) $s['mukellef']['unvan']),
                $s['mukellef']['vergi_kimlik_no'] ?: $s['mukellef']['tc_kimlik_no'],
                str_replace(';', ',', (string) $s['mukellef']['vergi_dairesi']),
                number_format($s['beyan_top'], 2, ',', '.'),
                number_format($s['ozel_top'], 2, ',', '.'),
                number_format($s['ucret'], 2, ',', '.'),
                number_format($s['genel'], 2, ',', '.'),
            ]) . "\n";
        }

        $csv .= 'GENEL TOPLAM;;;;'
            . number_format($sonuc['toplam']['beyanname'], 2, ',', '.') . ';'
            . number_format($sonuc['toplam']['ozel'], 2, ',', '.') . ';'
            . number_format($sonuc['toplam']['ucret'], 2, ',', '.') . ';'
            . number_format($sonuc['toplam']['genel'], 2, ',', '.') . "\n";

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="odeme_listesi_' . $id . '_'
                . $yil . ($ay !== null ? '_' . str_pad((string) $ay, 2, '0', STR_PAD_LEFT) : '') . '.csv"')
            ->setBody($csv);
    }

    public function listeSil(int $id)
    {
        $model = new OdemeListesiModel();
        $liste = $model->find($id);

        if ($liste === null
            || ! $model->erisebilirMi($liste, (int) $this->aktifKullanici['id'], $this->adminMi())) {
            return redirect()->to(site_url('odeme/listeler'))->with('hata', 'Liste bulunamadı.');
        }

        $model->delete($id);

        return redirect()->to(site_url('odeme/listeler'))->with('basari', 'Liste silindi.');
    }

    /**
     * Liste ekranları için dönem çözümlemesi.
     * URL'de yil/ay varsa onlar, yoksa listenin varsayılanı kullanılır.
     * ay=0 → tüm yıl.
     *
     * @return array{0:int,1:int|null}
     */
    protected function donemAl(array $varsayilan): array
    {
        $yilHam = $this->request->getGet('yil');
        $ayHam  = $this->request->getGet('ay');

        $yil = ($yilHam !== null && $yilHam !== '') ? (int) $yilHam : $varsayilan['yil'];

        if ($ayHam === null || $ayHam === '') {
            $ay = $varsayilan['ay'];
        } elseif ($ayHam === '0' || $ayHam === 'tumu') {
            $ay = null;                       // tüm yıl
        } else {
            $a  = (int) $ayHam;
            $ay = ($a >= 1 && $a <= 12) ? $a : null;
        }

        return [$yil, $ay];
    }

    // -----------------------------------------------------------------
    protected function filtreAl(): array
    {
        return [
            'yil'         => (int) ($this->request->getGet('yil') ?? date('Y')),
            'ay'          => $this->request->getGet('ay') ?? date('n'),
            'musavir_id'  => $this->kapsamBelirle($this->request->getGet('musavir_id')),
            'mukellef_id' => $this->request->getGet('mukellef_id'),
            'odendi'      => $this->request->getGet('odendi'),
            'q'           => $this->request->getGet('q'),
            'durumlar'    => $this->request->getGet('tumu') === '1'
                ? []   // tüm durumlar
                : \App\Models\BeyannameTakipModel::ODENECEK_DURUMLAR,
            // Özel kalemler kullanıcıya özeldir; yönetici hepsini görür.
            'ozel_kaydeden_id' => $this->adminMi() ? null : (int) $this->aktifKullanici['id'],
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
}
