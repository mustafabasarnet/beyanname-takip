<?php

namespace App\Controllers;

use App\Libraries\MukellefIceAktar;
use App\Models\AylikNotModel;
use App\Models\BeyannameTakipModel;
use App\Models\BeyannameTuruModel;
use App\Models\EvrakMuafiyetModel;
use App\Models\EvrakTuruModel;
use App\Models\KullaniciModel;
use App\Models\MukellefModel;
use App\Models\MusavirModel;

class Mukellefler extends BaseController
{
    protected MukellefModel $model;

    public function __construct()
    {
        $this->model = new MukellefModel();
    }

    // -----------------------------------------------------------------
    public function index()
    {
        // Kullanıcı belirli bir müşaviri filtreledi mi? (yalnızca yetkili olduğu)
        $secilen = $this->request->getGet('musavir_id');
        $izin    = $this->musavirFiltresi();   // admin -> null, diğerleri -> [id,...]

        if ($secilen !== null && $secilen !== '') {
            $secilen = (int) $secilen;
            $kapsam  = $this->musavireErisebilirMi($secilen) ? [$secilen] : ($izin ?? [$secilen]);
        } else {
            $kapsam = $izin;
        }

        $filtre = [
            'q'          => $this->request->getGet('q'),
            'durum'      => $this->request->getGet('durum') ?? 'aktif',
            'tip'        => $this->request->getGet('tip'),
            'genc_girisimci' => $this->request->getGet('gg'),
            'musavir_id' => $kapsam,
            'harf'       => $this->harfBelirle(),
        ];

        // Alfabe şeridi harf dağılımı (harf filtresi hariç diğer filtrelerle)
        $dagilim = $this->model->harfDagilimi($filtre);
        $toplam  = $this->model->listeSayisi($filtre);

        return $this->goster('mukellefler/index', [
            'mukellefler'   => $this->model->listele($filtre),
            'musavirler'    => $this->secilebilirMusavirler(),
            'filtre'        => $filtre,
            'secilenMusavir'=> $secilen !== '' ? $secilen : null,
            'istatistik'    => $this->model->istatistik($izin),
            'maliYetki'     => $this->maliYetkiVarMi(),
            // Toplu silme yalnızca yöneticiye görünür
            'yoneticiMi'    => $this->adminMi(),
            // Alfabe şeridi
            'alfabe'        => MukellefModel::ALFABE,
            'harfDagilimi'  => $dagilim,
            'seciliHarf'    => $filtre['harf'],
            'harfsizToplam' => array_sum($dagilim),
            'toplamKayit'   => $toplam,
        ], 'Mükellefler');
    }

    // -----------------------------------------------------------------
    public function yeni()
    {
        $musavirler = $this->secilebilirMusavirler();

        return $this->goster('mukellefler/form', [
            'mukellef'      => null,
            'musavirler'    => $musavirler,
            'varsayilan'    => $this->varsayilanMusavir(),
            'personeller'   => (new KullaniciModel())->musavirinKullanicilari(array_keys($musavirler)),
            'turler'        => (new BeyannameTuruModel())->aktifler(),
            'secilenTurler' => [],
            'celiski'       => (new BeyannameTuruModel())->celiskiHaritasi(),
            'maliYetki'    => $this->maliYetkiVarMi(),
            // Takip edilmeyen evrak türleri (yeni kayıtta hiçbiri seçili değil)
            'evrakTurleri'      => $this->evrakTurSecenekleri(),
            'muafEvrakTurleri'  => [],
            'muafEvrakNotlari'  => [],
        ], 'Yeni Mükellef');
    }

    public function kaydet()
    {
        $veri = $this->formVerisi();

        if (! $this->model->insert($veri)) {
            return redirect()->back()->withInput()->with('hatalar', $this->model->errors());
        }

        $id = $this->model->getInsertID();

        $turler = $this->request->getPost('turler') ?? [];
        $this->model->turleriKaydet((int) $id, $turler);

        $this->evrakMuafiyetiKaydet((int) $id);

        $this->yillikUcretKaydet((int) $id);

        // Dönemleri otomatik üret (bu yıl + gerekiyorsa izleyen yıl)
        $this->donemleriOtomatikUret((int) $id);

        return redirect()->to(site_url('mukellefler/detay/' . $id))
            ->with('basari', 'Mükellef kaydedildi ve beyanname dönemleri oluşturuldu.');
    }

    // -----------------------------------------------------------------
    public function duzenle(int $id)
    {
        $mukellef = $this->model->find($id);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->to(site_url('mukellefler'))->with('hata', 'Mükellef bulunamadı.');
        }

        $musavirler = $this->secilebilirMusavirler();

        // Kaydın mevcut müşaviri listede yoksa (yetki değişmiş olabilir) ekle
        if (! isset($musavirler[$mukellef['musavir_id']])) {
            $mus = (new MusavirModel())->find($mukellef['musavir_id']);
            if ($mus !== null) {
                $musavirler[(int) $mus['id']] = trim(($mus['unvan'] ? $mus['unvan'] . ' ' : '') . $mus['ad_soyad']);
            }
        }

        return $this->goster('mukellefler/form', [
            'mukellef'      => $mukellef,
            'musavirler'    => $musavirler,
            'varsayilan'    => (int) $mukellef['musavir_id'],
            'personeller'   => (new KullaniciModel())->musavirinKullanicilari(array_keys($musavirler)),
            'turler'        => (new BeyannameTuruModel())->aktifler(),
            'secilenTurler' => $this->model->turIdListesi($id),
            'celiski'       => (new BeyannameTuruModel())->celiskiHaritasi(),
            'maliYetki'     => $this->maliYetkiVarMi(),
            // Takip edilmeyen evrak türleri (kalıcı muafiyet)
            'evrakTurleri'      => $this->evrakTurSecenekleri(),
            'muafEvrakTurleri'  => (new EvrakMuafiyetModel())->turIdListesi($id),
            'muafEvrakNotlari'  => (new EvrakMuafiyetModel())->aciklamalar($id),
        ], 'Mükellef Düzenle');
    }

    public function guncelle(int $id)
    {
        $mukellef = $this->model->find($id);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->to(site_url('mukellefler'))->with('hata', 'Mükellef bulunamadı.');
        }

        $veri = $this->formVerisi();

        if (! $this->model->update($id, $veri)) {
            return redirect()->back()->withInput()->with('hatalar', $this->model->errors());
        }

        $turler = $this->request->getPost('turler') ?? [];
        $this->model->turleriKaydet($id, $turler);

        $this->evrakMuafiyetiKaydet($id);

        $this->yillikUcretKaydet($id);

        // Tarih/tür değişmiş olabilir -> dönemleri yeniden senkronize et
        $this->donemleriOtomatikUret($id);

        return redirect()->to(site_url('mukellefler/detay/' . $id))
            ->with('basari', 'Mükellef güncellendi, dönemler yeniden hesaplandı.');
    }

    // -----------------------------------------------------------------
    public function detay(int $id)
    {
        $mukellef = $this->model->find($id);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->to(site_url('mukellefler'))->with('hata', 'Mükellef bulunamadı.');
        }

        $yil    = (int) ($this->request->getGet('yil') ?? date('Y'));
        $takip  = new BeyannameTakipModel();

        return $this->goster('mukellefler/detay', [
            'mukellef' => $mukellef,
            'musavir'  => (new MusavirModel())->find($mukellef['musavir_id']),
            'sorumlu'  => ! empty($mukellef['sorumlu_kullanici_id'])
                ? (new KullaniciModel())->find($mukellef['sorumlu_kullanici_id']) : null,
            'turler'   => $this->model->beyannameTurleri($id),
            'matris'   => $takip->mukellefMatrisi($id, $yil),
            'notlar'   => (new AylikNotModel())->yilNotlari($id, $yil),
            'yil'      => $yil,
            'durumlar' => BeyannameTakipModel::DURUMLAR,
            'maliYetki'    => $this->maliYetkiVarMi(),
        ], $mukellef['unvan']);
    }

    /** Tek mükellefin yıllık çizelgesi (yazdırılabilir) */
    public function cizelge(int $id)
    {
        $mukellef = $this->model->find($id);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->to(site_url('mukellefler'));
        }

        $yil   = (int) ($this->request->getGet('yil') ?? date('Y'));
        $takip = new BeyannameTakipModel();

        return view('mukellefler/cizelge', [
            'mukellef' => $mukellef,
            'matris'   => $takip->mukellefMatrisi($id, $yil),
            'notlar'   => (new AylikNotModel())->yilNotlari($id, $yil),
            'yil'      => $yil,
        ]);
    }

    // -----------------------------------------------------------------
    public function terk(int $id)
    {
        $mukellef = $this->model->find($id);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->to(site_url('mukellefler'))->with('hata', 'Mükellef bulunamadı.');
        }

        $terkTarihi = $this->request->getPost('terk_tarihi');

        $this->model->update($id, [
            'terk_tarihi' => $terkTarihi ?: null,
            'terk_nedeni' => $this->request->getPost('terk_nedeni'),
        ]);

        // Terk sonrası geçersiz kalan dönemleri temizle
        $this->donemleriOtomatikUret($id);

        $mesaj = $terkTarihi
            ? 'Terk tarihi kaydedildi. Terk sonrası dönemler çizelgeden kaldırıldı.'
            : 'Terk kaydı kaldırıldı, dönemler yeniden oluşturuldu.';

        return redirect()->to(site_url('mukellefler/detay/' . $id))->with('basari', $mesaj);
    }

    public function sil(int $id)
    {
        $mukellef = $this->model->find($id);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->to(site_url('mukellefler'))->with('hata', 'Mükellef bulunamadı.');
        }

        $this->model->delete($id);

        return redirect()->to(site_url('mukellefler'))->with('basari', 'Mükellef silindi.');
    }

    /**
     * Geçmiş dönemleri topluca kapat.
     * Mükellefi sonradan devraldıysanız, eski dönemleri tek tıkla
     * "Gönderildi" veya "Verilmeyecek" yapar.
     */
    public function gecmisiKapat(int $id)
    {
        $mukellef = $this->model->find($id);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->to(site_url('mukellefler'))->with('hata', 'Mükellef bulunamadı.');
        }

        $tarih = $this->request->getPost('tarih') ?: date('Y-m-d');
        $durum = $this->request->getPost('durum') ?: 'ONAYLANDI';

        $adet = (new BeyannameTakipModel())->gecmisiKapat($id, $tarih, $durum);

        $etiket = BeyannameTakipModel::DURUMLAR[$durum] ?? $durum;

        return redirect()->to(site_url('mukellefler/detay/' . $id))
            ->with('basari', sprintf(
                '%s tarihinden önceki %d bekleyen dönem "%s" olarak işaretlendi.',
                date('d.m.Y', strtotime($tarih)), $adet, $etiket
            ));
    }

    /** Manuel dönem üretimi */
    public function donemUret(int $id)
    {
        $mukellef = $this->model->find($id);

        if ($mukellef === null || ! $this->mukellefeErisebilirMi($mukellef)) {
            return redirect()->to(site_url('mukellefler'));
        }

        $yil   = (int) ($this->request->getGet('yil') ?? date('Y'));
        $ozet  = (new BeyannameTakipModel())->donemleriUret($id, $yil);

        return redirect()->to(site_url('mukellefler/detay/' . $id . '?yil=' . $yil))
            ->with('basari', sprintf(
                '%d yılı için: %d eklendi, %d güncellendi, %d kaldırıldı.',
                $yil, $ozet['eklenen'], $ozet['guncellenen'], $ozet['silinen']
            ));
    }

    // -----------------------------------------------------------------
    //  Yardımcılar
    // -----------------------------------------------------------------

    /**
     * Formdan gelen müşavir ID'sini yetki kontrolünden geçirerek döndürür.
     *
     * ÖNEMLİ: Eskiden burada musavirFiltresi() kullanılıyordu; bu, admin
     * olmayan kullanıcıda formdaki seçimi yok sayıp kullanıcının kendi
     * müşavirini zorluyordu ("mükellef kartında hep ilk kullanıcı çıkıyor"
     * hatasının kaynağı). Artık form seçimi esas alınır, yalnızca yetki
     * doğrulanır.
     */

    // =================================================================
    //  EXCEL / CSV'DEN TOPLU MÜKELLEF AKTARMA
    // =================================================================

    /** Aktarma ekranı (dosya yükleme formu) */
    public function iceAktar()
    {
        return $this->goster('mukellefler/ice_aktar', [
            'musavirler' => $this->secilebilirMusavirler(),
            'varsayilan' => $this->varsayilanMusavir(),
            'turler'     => (new BeyannameTuruModel())->aktifler(),
            'sutunlar'   => MukellefIceAktar::SUTUNLAR,
            'zorunlu'    => MukellefIceAktar::ZORUNLU,
        ], 'Excel’den Mükellef Aktarma');
    }

    /** Örnek CSV şablonunu indirir */
    public function sablonIndir()
    {
        $ornekli = $this->request->getGet('bos') === null;
        $csv     = (new MukellefIceAktar())->sablon($ornekli);
        $ad      = $ornekli ? 'mukellef_sablon_ornekli' : 'mukellef_sablon_bos';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $ad . '.csv"')
            ->setBody($csv);
    }

    /** Yüklenen dosyayı çözümler, ÖNİZLEME gösterir (DB'ye yazmaz) */
    public function onizle()
    {
        $dosya = $this->request->getFile('dosya');

        if ($dosya === null || ! $dosya->isValid()) {
            $mesaj = $dosya !== null ? $dosya->getErrorString() : 'Dosya seçilmedi.';

            return redirect()->to(site_url('mukellefler/ice-aktar'))
                ->with('hata', 'Dosya yüklenemedi: ' . $mesaj);
        }

        $uzanti = strtolower($dosya->getClientExtension());

        if (! in_array($uzanti, ['csv', 'txt'], true)) {
            return redirect()->to(site_url('mukellefler/ice-aktar'))
                ->with('hata', 'Yalnızca CSV dosyası yükleyebilirsiniz. Excel’de '
                    . '"Farklı Kaydet → CSV (Ayırıcı sınırlı) (*.csv)" seçin.');
        }

        $musavirId = $this->secilenMusavirId();

        if ($musavirId <= 0) {
            return redirect()->to(site_url('mukellefler/ice-aktar'))
                ->with('hata', 'Mali müşavir seçilmedi.');
        }

        $sonuc = (new MukellefIceAktar())->cozumle($dosya->getTempName(), $musavirId);

        if (! $sonuc['basarili']) {
            return redirect()->to(site_url('mukellefler/ice-aktar'))->with('hata', $sonuc['mesaj']);
        }

        // Önizlemeyi oturumda tut — onaylanınca buradan aktarılır
        session()->set('ice_aktar_veri', [
            'satirlar'   => $sonuc['satirlar'],
            'musavir_id' => $musavirId,
            'dosya_adi'  => $dosya->getClientName(),
            'zaman'      => time(),
        ]);

        $musavirler = $this->secilebilirMusavirler();

        return $this->goster('mukellefler/ice_aktar_onizleme', [
            'satirlar'   => $sonuc['satirlar'],
            'ozet'       => $sonuc['ozet'],
            'dosyaAdi'   => $dosya->getClientName(),
            'musavirAdi' => $musavirler[$musavirId] ?? '—',
            'sutunlar'   => MukellefIceAktar::SUTUNLAR,
        ], 'Aktarma Önizlemesi');
    }

    /** Önizlemedeki "eklenecek" satırları veritabanına yazar */
    public function aktarmaOnayla()
    {
        $paket = session()->get('ice_aktar_veri');

        if (! is_array($paket) || empty($paket['satirlar'])) {
            return redirect()->to(site_url('mukellefler/ice-aktar'))
                ->with('hata', 'Önizleme bulunamadı veya süresi doldu. Lütfen dosyayı yeniden yükleyin.');
        }

        // Kullanıcı önizlemede satır çıkarmış olabilir.
        // Önizleme formu her zaman "secim=1" gönderir; bu varken hiçbir kutu
        // işaretli değilse HİÇBİR ŞEY aktarılmaz (aksi hâlde sessizce hepsi
        // eklenirdi — tehlikeli).
        $secili = $this->request->getPost('satirlar');
        $secili = is_array($secili) ? array_map('intval', $secili) : [];

        if ($this->request->getPost('secim') !== null) {
            if ($secili === []) {
                return redirect()->to(site_url('mukellefler/ice-aktar'))
                    ->with('hata', 'Hiçbir satır seçilmedi, aktarma yapılmadı.');
            }

            $satirlar = array_filter(
                $paket['satirlar'],
                static fn ($s) => in_array((int) $s['satir_no'], $secili, true)
            );
        } elseif ($secili !== []) {
            $satirlar = array_filter(
                $paket['satirlar'],
                static fn ($s) => in_array((int) $s['satir_no'], $secili, true)
            );
        } else {
            $satirlar = $paket['satirlar'];
        }

        // Çok mükellefli dosyalarda dönem üretimi uzun sürebilir;
        // sunucunun varsayılan zaman aşımına takılmayalım.
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $iceAktar = new MukellefIceAktar();
        $sonuc    = $iceAktar->aktar($satirlar);

        // Dönemleri üret
        $donem = 0;

        if ($this->request->getPost('donem_uret') !== null) {
            foreach ($sonuc['idler'] as $id) {
                $this->donemleriOtomatikUret((int) $id);
                $donem++;
            }
        }

        session()->remove('ice_aktar_veri');

        $mesaj = $sonuc['eklenen'] . ' mükellef eklendi';

        if ($donem > 0) {
            $mesaj .= ', ' . $donem . ' mükellef için beyanname dönemleri üretildi';
        }

        if ($sonuc['atlanan'] > 0) {
            $mesaj .= '. ' . $sonuc['atlanan'] . ' satır atlandı';
        }

        if ($sonuc['hatali'] > 0) {
            $mesaj .= '. ' . $sonuc['hatali'] . ' satır hatalı';
        }

        $tur = $sonuc['eklenen'] > 0 ? 'basari' : 'hata';

        if ($sonuc['hatalar'] !== []) {
            session()->setFlashdata('aktarma_hatalari', $sonuc['hatalar']);
        }

        return redirect()->to(site_url('mukellefler'))->with($tur, $mesaj . '.');
    }

    // -----------------------------------------------------------------
    /** Alfabe şeridinden gelen harf (geçersizse null) */
    protected function harfBelirle(): ?string
    {
        $ham = trim((string) $this->request->getGet('harf'));

        if ($ham === '') {
            return null;
        }

        $harf = mb_strtoupper($ham, 'UTF-8');

        return in_array($harf, MukellefModel::ALFABE, true) ? $harf : null;
    }

    protected function secilenMusavirId(): int
    {
        $secilen = (int) ($this->request->getPost('musavir_id') ?: 0);

        if ($secilen > 0 && $this->musavireErisebilirMi($secilen)) {
            return $secilen;
        }

        // Seçim yoksa/yetkisizse: kullanıcının varsayılan müşaviri
        $vars = $this->varsayilanMusavir();

        if ($vars !== null) {
            return $vars;
        }

        $izin = $this->erisilenMusavirler();

        return $izin !== [] ? (int) $izin[0] : $secilen;
    }

    /** "1.250,50" / "1250.50" biçimlerini float'a çevirir */
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

    protected function formVerisi(): array
    {
        $terk = $this->request->getPost('terk_tarihi');

        $veri = [
            'musavir_id'         => $this->secilenMusavirId(),
            'sorumlu_kullanici_id' => $this->request->getPost('sorumlu_kullanici_id') ?: null,
            'kod'                => $this->request->getPost('kod'),
            'unvan'              => trim((string) $this->request->getPost('unvan')),
            'mukellef_tipi'      => $this->request->getPost('mukellef_tipi'),
            'vergi_kimlik_no'    => $this->request->getPost('vergi_kimlik_no') ?: null,
            'tc_kimlik_no'       => $this->request->getPost('tc_kimlik_no') ?: null,
            'vergi_dairesi'      => $this->request->getPost('vergi_dairesi'),
            'defter_tipi'        => $this->request->getPost('defter_tipi'),
            // Genç girişimci alanları aşağıda mükellef tipine göre düzeltilir
            'genc_girisimci'     => (int) ($this->request->getPost('genc_girisimci') ?? 0),
            'gg_baslangic_yili'  => $this->request->getPost('gg_baslangic_yili') ?: null,
            'gg_not'             => $this->request->getPost('gg_not'),
            'faaliyet_konusu'    => $this->request->getPost('faaliyet_konusu'),
            'nace_kodu'          => $this->request->getPost('nace_kodu'),
            'sgk_isyeri_sicil'   => $this->request->getPost('sgk_isyeri_sicil'),
            // Mali bilgiler yalnızca admin/müşavir tarafından yazılabilir.
            // (Personel formda görmez; POST ile göndermeye çalışsa da yok sayılır.)
            'ise_baslama_tarihi' => $this->request->getPost('ise_baslama_tarihi'),
            'takip_baslangic'    => $this->request->getPost('takip_baslangic') ?: null,
            'terk_tarihi'        => $terk ?: null,
            'terk_nedeni'        => $this->request->getPost('terk_nedeni'),
            'telefon'            => $this->request->getPost('telefon'),
            'eposta'             => $this->request->getPost('eposta') ?: null,
            'yetkili_kisi'       => $this->request->getPost('yetkili_kisi'),
            'adres'              => $this->request->getPost('adres'),
            'notlar'             => $this->request->getPost('notlar'),
            'aktif'              => (int) ($this->request->getPost('aktif') ?? 1),
        ];

        // -------------------------------------------------------------
        //  İndirim / kısıtlama kalemleri
        //
        //  İşaretlenmemiş checkbox POST'a HİÇ GELMEZ; bu yüzden her kalem
        //  açıkça 0'a çekilir — yoksa bir kez işaretlenen indirim asla
        //  kapatılamazdı. Kalem kapalıysa notu da temizlenir ki ekranda
        //  "uygulanmıyor ama notu duruyor" tutarsızlığı oluşmasın.
        // -------------------------------------------------------------
        // -------------------------------------------------------------
        //  GENÇ GİRİŞİMCİ — yalnızca gerçek kişi
        //
        //  GVK mükerrer 20 istisnası şirketlere uygulanmaz. Form tüzel kişide
        //  bu bölümü gizler, ancak istek elle de gönderilebileceği için karar
        //  SUNUCUDA verilir. Tüzel seçiliyse işaret ve yardımcı alanlar
        //  temizlenir; böylece gerçek kişiden tüzele çevrilen bir mükellefte
        //  eski istisna kaydı da geride kalmaz.
        // -------------------------------------------------------------
        if (($veri['mukellef_tipi'] ?? '') === 'tuzel') {
            $veri['genc_girisimci']    = 0;
            $veri['gg_baslangic_yili'] = null;
            $veri['gg_not']            = null;
        }

        // -------------------------------------------------------------
        //  E-defter alanları (migration çalıştırılmışsa)
        //  "Yok" seçiliyse sorumlu ve başlangıç temizlenir; aksi halde
        //  listeye girmeyen mükellefte hayalet sorumlu kalırdı.
        // -------------------------------------------------------------
        if (in_array('edefter_donem', db_connect()->getFieldNames('mukellefler'), true)) {
            $edDonem = (string) ($this->request->getPost('edefter_donem') ?? 'YOK');

            if (! in_array($edDonem, ['YOK', 'AYLIK', 'UC_AYLIK'], true)) {
                $edDonem = 'YOK';
            }

            $veri['edefter_donem'] = $edDonem;

            if ($edDonem === 'YOK') {
                $veri['edefter_sorumlu_id'] = null;
                $veri['edefter_baslangic']  = null;
            } else {
                $veri['edefter_sorumlu_id'] = $this->request->getPost('edefter_sorumlu_id') ?: null;
                $veri['edefter_baslangic']  = $this->request->getPost('edefter_baslangic') ?: null;
            }
        }

        //  Not: migration_indirimler.sql çalıştırılmamış bir kurulumda bu
        //  kolonlar yoktur; veriye eklenirse KAYIT TAMAMEN BAŞARISIZ olur.
        //  Bu yüzden önce şemada gerçekten var mı diye bakılır.
        if (in_array('ind_bagkur', db_connect()->getFieldNames('mukellefler'), true)) {
            foreach (indirimTanimlari() as $t) {
                $acik = (int) ($this->request->getPost($t['alan']) ?? 0) === 1;

                $veri[$t['alan']]     = $acik ? 1 : 0;
                $veri[$t['not_alan']] = $acik
                    ? (trim((string) $this->request->getPost($t['not_alan'])) ?: null)
                    : null;
            }
        }

        if ($this->maliYetkiVarMi()) {
            $veri['muhasebe_ucreti'] = $this->paraCoz($this->request->getPost('muhasebe_ucreti'));
            $veri['ucret_aciklama']  = $this->request->getPost('ucret_aciklama');
        }

        return $veri;
    }

    /**
     * Mükellefin faaliyet aralığına giren yıllar için dönem üretir.
     * (İşe başlama yılı .. terk yılı / bu yıl+1)
     */
        /**
     * Yıllık sözleşme ücretini kaydeder (Makbuz Takip modülü hedefi).
     *
     * Ayrı tabloda (mukellef_ucretleri) yıl bazında tutulur; bu yüzden
     * formVerisi() dizisine girmez, kayıttan SONRA yazılır.
     */
    /**
     * Mükellef kartındaki "Takip Edilmeyen Evrak Türleri" bölümü için
     * seçenek listesi.
     *
     * migration_evrak_muafiyet.sql çalıştırılmamışsa boş dizi döner ve
     * görünüm bölümü hiç çizmez.
     *
     * @return array<int,array>
     */
    protected function evrakTurSecenekleri(): array
    {
        if (! (new EvrakMuafiyetModel())->kullanilabilir()) {
            return [];
        }

        try {
            return (new EvrakTuruModel())->aktifler();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Formdan gelen kalıcı evrak muafiyetlerini yazar.
     *
     * ÖNEMLİ: Bölüm formda hiç yoksa (eski görünüm dosyası ya da migration
     * çalıştırılmamış kurulum) POST'ta işaret alanı bulunmaz. Bu durumda
     * mevcut muafiyetler SİLİNMEMELİDİR; yoksa eski bir form dosyası her
     * kaydetmede kullanıcının ayarlarını sıfırlardı. Gizli alan bu ayrımı
     * yapar: alan varsa bölüm gerçekten gösterilmiştir.
     */
    protected function evrakMuafiyetiKaydet(int $mukellefId): void
    {
        $muafModel = new EvrakMuafiyetModel();

        if (! $muafModel->kullanilabilir()) {
            return;
        }

        if ((string) $this->request->getPost('evrak_muaf_gonderildi') !== '1') {
            return;   // bölüm formda yok -> mevcut ayara dokunma
        }

        $secilen = (array) ($this->request->getPost('evrak_muaf') ?? []);
        $notlar  = (array) ($this->request->getPost('evrak_muaf_not') ?? []);

        $temizNot = [];

        foreach ($notlar as $tid => $metin) {
            $temizNot[(int) $tid] = mb_substr(trim((string) $metin), 0, 200);
        }

        $muafModel->eslestir($mukellefId, $secilen, $temizNot);

        // Muaf türlerde birikmiş boş kayıtlar sayaçları şişirmesin
        $muafModel->bosKayitlariTemizle($mukellefId);
    }

    protected function yillikUcretKaydet(int $mukellefId): void
    {
        if (! db_connect()->tableExists('mukellef_ucretleri')) {
            return;
        }

        $ham = $this->request->getPost('yillik_ucret');

        if ($ham === null) {
            return;   // alan formda yoksa dokunma
        }

        $tutar = $this->paraCoz($ham);
        $yil   = (int) date('Y');

        // Boş bırakıldıysa kayıt 0'a çekilir (silinmez; geçmiş korunur)
        (new \App\Models\MakbuzModel())->ucretYaz($mukellefId, $yil, $tutar ?? 0.0);
    }

    protected function donemleriOtomatikUret(int $id): void
    {
        $mukellef = $this->model->find($id);

        if ($mukellef === null) {
            return;
        }

        $takip = new BeyannameTakipModel();

        $baslangic = ! empty($mukellef['takip_baslangic'])
            ? max($mukellef['ise_baslama_tarihi'], $mukellef['takip_baslangic'])
            : $mukellef['ise_baslama_tarihi'];

        $basYil = (int) date('Y', strtotime((string) $baslangic));
        $bitYil = ! empty($mukellef['terk_tarihi'])
            ? (int) date('Y', strtotime((string) $mukellef['terk_tarihi']))
            : (int) date('Y') + 1;

        // Çok geniş aralıkları sınırla (performans)
        $basYil = max($basYil, (int) date('Y') - 5);
        $bitYil = min($bitYil, (int) date('Y') + 2);

        for ($y = $basYil; $y <= $bitYil; $y++) {
            $takip->donemleriUret($id, $y);
        }

        // -------------------------------------------------------------
        //  E-defter dönemleri
        //  Mükellef kartında Aylık/Üç Aylık seçilmişse beratlar da üretilir.
        //  "Yok" seçiliyse donemleriUret() işlenmemiş satırları temizler,
        //  yani seçim geri alındığında liste kendiliğinden boşalır.
        // -------------------------------------------------------------
        if (in_array('edefter_donem', db_connect()->getFieldNames('mukellefler'), true)
            && (int) (new \App\Models\AyarModel())->oku('edefter_otomatik_uret', 1) === 1) {
            $edefter = new \App\Models\EdefterTakipModel();

            for ($y = $basYil; $y <= $bitYil; $y++) {
                $edefter->donemleriUret($id, $y);
            }
        }
    }
}
