<?php

namespace App\Controllers;

use App\Models\AjandaModel;
use App\Models\KullaniciModel;
use App\Models\MukellefModel;
use App\Models\MusavirModel;

/**
 * AJANDA / HATIRLATICI
 *
 * Elle girilen işler: toplantı, arama, sözleşme yenileme, vergi dairesi
 * ziyareti… Beyanname/e-defter/evrak uyarıları ayrı modüllerde otomatik
 * üretiliyor, burada tekrarlanmaz.
 *
 * Tüm roller erişebilir (personel dahil) — herkesin kendi işi olur.
 */
class Ajanda extends BaseController
{
    protected AjandaModel $model;

    public const SAYFA_ADEDI = 50;

    /** İzin verilen dosya uzantıları (ek yükleme) */
    public const EK_UZANTI = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xlsx', 'xls', 'csv', 'docx', 'doc', 'txt', 'zip'];

    public function __construct()
    {
        $this->model = new AjandaModel();
    }

    /** Görünürlük süzmesi için kullanıcı + erişilen müşavirler */
    protected function kapsam(): array
    {
        return [$this->aktifKullanici, $this->erisilenMusavirler()];
    }

    // -----------------------------------------------------------------
    //  LİSTE
    // -----------------------------------------------------------------
    public function index()
    {
        [$kul, $mus] = $this->kapsam();

        $filtre = $this->filtreAl();
        $sayfa  = max(1, (int) $this->request->getGet('sayfa'));

        $sf          = $filtre;
        $sf['limit'] = self::SAYFA_ADEDI;
        $sf['ofset'] = ($sayfa - 1) * self::SAYFA_ADEDI;

        $toplam = $this->model->listeSayisi($filtre, $kul, $mus);

        return $this->goster('ajanda/index', [
            'kayitlar'    => $this->model->liste($sf, $kul, $mus),
            'filtre'      => $filtre,
            'sayfa'       => $sayfa,
            'toplamKayit' => $toplam,
            'toplamSayfa' => max(1, (int) ceil($toplam / self::SAYFA_ADEDI)),
            'sayaclar'    => $this->model->sayaclar($kul, $mus, $this->panelGun()),
            'etiketler'   => $this->model->etiketler($kul, $mus),
            'kullanicilar' => $this->kullaniciSecenekleri(),
            'gorunurluk'  => AjandaModel::GORUNURLUK,
            'oncelikler'  => AjandaModel::ONCELIK,
            'durumlar'    => AjandaModel::DURUMLAR,
        ], 'Ajanda');
    }

    // -----------------------------------------------------------------
    //  TAKVİM
    // -----------------------------------------------------------------
    public function takvim()
    {
        [$kul, $mus] = $this->kapsam();

        $yil = (int) ($this->request->getGet('yil') ?: date('Y'));
        $ay  = (int) ($this->request->getGet('ay') ?: date('n'));

        if ($ay < 1 || $ay > 12) {
            $ay = (int) date('n');
        }

        $filtre = $this->filtreAl();

        // Takvimde tarih aralığı ay tarafından belirlenir
        unset($filtre['bas'], $filtre['bit']);

        return $this->goster('ajanda/takvim', [
            'yil'         => $yil,
            'ay'          => $ay,
            'gunler'      => $this->model->takvim($yil, $ay, $kul, $mus, $filtre),
            'filtre'      => $filtre,
            'sayaclar'    => $this->model->sayaclar($kul, $mus, $this->panelGun()),
            'etiketler'   => $this->model->etiketler($kul, $mus),
            'kullanicilar' => $this->kullaniciSecenekleri(),
            'gorunurluk'  => AjandaModel::GORUNURLUK,
            'oncelikler'  => AjandaModel::ONCELIK,
            'durumlar'    => AjandaModel::DURUMLAR,
        ], 'Ajanda Takvimi');
    }

    // -----------------------------------------------------------------
    //  FORM
    // -----------------------------------------------------------------
    public function yeni()
    {
        return $this->form(null);
    }

    public function duzenle(int $id)
    {
        [$kul, $mus] = $this->kapsam();
        $kayit = $this->model->detay($id);

        if ($kayit === null) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Kayıt bulunamadı.');
        }

        if (! $this->model->gorebilirMi($kayit, $kul, $mus)) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Bu kayda erişemezsiniz.');
        }

        if (! $this->model->duzenleyebilirMi($kayit, $kul)) {
            return redirect()->to(site_url('ajanda/detay/' . $id))
                ->with('hata', 'Bu kaydı yalnızca oluşturan veya atanan kişi düzenleyebilir.');
        }

        return $this->form($kayit);
    }

    protected function form(?array $kayit)
    {
        return $this->goster('ajanda/form', [
            'kayit'        => $kayit,
            'ekler'        => $kayit === null ? [] : $this->ekleriAl((int) $kayit['id']),
            'kullanicilar' => $this->kullaniciSecenekleri(),
            'musavirler'   => $this->secilebilirMusavirler(),
            'mukellefler'  => $this->mukellefSecenekleri(),
            'gorunurluk'   => AjandaModel::GORUNURLUK,
            'oncelikler'   => AjandaModel::ONCELIK,
            'tekrarlar'    => AjandaModel::TEKRAR,
            'onOnTarih'    => $this->request->getGet('tarih') ?: date('Y-m-d'),
            'onMukellef'   => (int) $this->request->getGet('mukellef_id'),
            'ekBoyut'      => $this->ekBoyutKb(),
        ], $kayit === null ? 'Yeni Ajanda Kaydı' : 'Ajanda Kaydını Düzenle');
    }

    public function kaydet()
    {
        [$kul, $mus] = $this->kapsam();

        $id  = (int) $this->request->getPost('id');
        $kid = (int) ($this->aktifKullanici['id'] ?? 0);

        // Düzenlemede yetki denetimi
        if ($id > 0) {
            $var = $this->model->find($id);

            if ($var === null) {
                return redirect()->to(site_url('ajanda'))->with('hata', 'Kayıt bulunamadı.');
            }

            if (! $this->model->duzenleyebilirMi($var, $kul)) {
                return redirect()->to(site_url('ajanda'))->with('hata', 'Bu kaydı düzenleyemezsiniz.');
            }
        }

        $veri = $this->formVerisi($kid);

        if ($id > 0) {
            $sonuc = $this->model->update($id, $veri);
        } else {
            $veri['olusturan_id'] = $kid;
            $sonuc = $this->model->insert($veri);
            $id    = $sonuc ? (int) $this->model->getInsertID() : 0;
        }

        if ($sonuc === false) {
            return redirect()->back()->withInput()->with('hatalar', $this->model->errors());
        }

        // Dosya ekleri
        $ekMesaj = $this->ekleriYukle($id, $kid);

        return redirect()->to(site_url('ajanda/detay/' . $id))
            ->with('basari', 'Ajanda kaydı kaydedildi.' . $ekMesaj);
    }

    /** POST'tan kayıt alanlarını okur ve tutarlı hale getirir */
    protected function formVerisi(int $kid): array
    {
        $gorunurluk = $this->request->getPost('gorunurluk');
        $gorunurluk = isset(AjandaModel::GORUNURLUK[$gorunurluk]) ? $gorunurluk : 'kisisel';

        $oncelik = $this->request->getPost('oncelik');
        $oncelik = isset(AjandaModel::ONCELIK[$oncelik]) ? $oncelik : 'normal';

        $tekrar = $this->request->getPost('tekrar');
        $tekrar = isset(AjandaModel::TEKRAR[$tekrar]) ? $tekrar : 'yok';

        $atanan  = (int) $this->request->getPost('atanan_id') ?: null;
        $musavir = (int) $this->request->getPost('musavir_id') ?: null;

        // Görünürlükle tutarsız alanlar temizlenir; yoksa "kişisel" seçilip
        // atanan kalırsa kayıt yanlış kişiye görünür.
        if ($gorunurluk !== 'gorev') {
            $atanan = null;
        }

        if ($gorunurluk !== 'musavir') {
            $musavir = null;
        }

        // Görev seçilip kişi seçilmemişse kişisele düşürülür
        if ($gorunurluk === 'gorev' && $atanan === null) {
            $gorunurluk = 'kisisel';
        }

        // Müşavir ekibi seçilip müşavir seçilmemişse de öyle
        if ($gorunurluk === 'musavir' && $musavir === null) {
            $gorunurluk = 'kisisel';
        }

        // Atanan kişi gerçekten erişilebilir mi? (yetki sızıntısı olmasın)
        if ($atanan !== null && ! array_key_exists($atanan, $this->kullaniciSecenekleri())) {
            $atanan     = null;
            $gorunurluk = 'kisisel';
        }

        if ($musavir !== null && ! $this->musavireErisebilirMi($musavir)) {
            $musavir    = null;
            $gorunurluk = 'kisisel';
        }

        $tarih = $this->request->getPost('tarih') ?: date('Y-m-d');
        $bitis = $this->request->getPost('bitis_tarihi') ?: null;

        // Bitiş başlangıçtan önce olamaz
        if ($bitis !== null && $bitis < $tarih) {
            $bitis = null;
        }

        $tekrarBitis = $tekrar === 'yok' ? null : ($this->request->getPost('tekrar_bitis') ?: null);

        return [
            'baslik'       => trim((string) $this->request->getPost('baslik')),
            'aciklama'     => $this->request->getPost('aciklama') ?: null,
            'tarih'        => $tarih,
            'saat'         => $this->request->getPost('saat') ?: null,
            'bitis_tarihi' => $bitis,
            'gorunurluk'   => $gorunurluk,
            'atanan_id'    => $atanan,
            'musavir_id'   => $musavir,
            'oncelik'      => $oncelik,
            'etiket'       => trim((string) $this->request->getPost('etiket')) ?: null,
            'renk'         => $this->renkCoz($this->request->getPost('renk')),
            'mukellef_id'  => (int) $this->request->getPost('mukellef_id') ?: null,
            'tekrar'       => $tekrar,
            'tekrar_bitis' => $tekrarBitis,
            'hatirlat_gun' => min(365, max(0, (int) $this->request->getPost('hatirlat_gun'))),
        ];
    }

    /** #rrggbb dışındaki değerleri reddeder */
    protected function renkCoz($ham): ?string
    {
        $ham = trim((string) $ham);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $ham) === 1 ? $ham : null;
    }

    // -----------------------------------------------------------------
    //  DETAY
    // -----------------------------------------------------------------
    public function detay(int $id)
    {
        [$kul, $mus] = $this->kapsam();
        $kayit = $this->model->detay($id);

        if ($kayit === null) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Kayıt bulunamadı.');
        }

        if (! $this->model->gorebilirMi($kayit, $kul, $mus)) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Bu kayda erişemezsiniz.');
        }

        return $this->goster('ajanda/detay', [
            'k'           => $kayit,
            'ekler'       => $this->ekleriAl($id),
            'duzenlenir'  => $this->model->duzenleyebilirMi($kayit, $kul),
            'gorunurluk'  => AjandaModel::GORUNURLUK,
            'oncelikler'  => AjandaModel::ONCELIK,
            'tekrarlar'   => AjandaModel::TEKRAR,
        ], 'Ajanda — ' . $kayit['baslik']);
    }

    // -----------------------------------------------------------------
    //  DURUM İŞLEMLERİ (AJAX)
    // -----------------------------------------------------------------
    public function yapildi()
    {
        [$kul, $mus] = $this->kapsam();

        $id = (int) $this->request->getPost('id');
        $k  = $this->model->find($id);

        if ($k === null || ! $this->model->gorebilirMi($k, $kul, $mus)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Kayıt bulunamadı.']);
        }

        if (! $this->model->duzenleyebilirMi($k, $kul)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Bu kaydı değiştiremezsiniz.']);
        }

        $s = $this->model->yapildiIsaretle($id, (int) ($this->aktifKullanici['id'] ?? 0));

        return $this->response->setJSON([
            'durum'    => $s['durum'],
            'mesaj'    => $s['mesaj'],
            'kapandi'  => $s['kapandi'] ?? true,
            'yeni'     => $s['yeni'] ?? null,
            'sayaclar' => $this->model->sayaclar($kul, $mus, $this->panelGun()),
        ]);
    }

    public function geriAl()
    {
        [$kul, $mus] = $this->kapsam();

        $id = (int) $this->request->getPost('id');
        $k  = $this->model->find($id);

        if ($k === null || ! $this->model->duzenleyebilirMi($k, $kul)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'İşlem yapılamadı.']);
        }

        $this->model->geriAl($id);

        return $this->response->setJSON([
            'durum'    => true,
            'mesaj'    => 'Kayıt yeniden açıldı.',
            'sayaclar' => $this->model->sayaclar($kul, $mus, $this->panelGun()),
        ]);
    }

    public function iptal()
    {
        [$kul, $mus] = $this->kapsam();

        $id = (int) $this->request->getPost('id');
        $k  = $this->model->find($id);

        if ($k === null || ! $this->model->duzenleyebilirMi($k, $kul)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'İşlem yapılamadı.']);
        }

        $this->model->update($id, ['durum' => 'IPTAL']);

        return $this->response->setJSON([
            'durum'    => true,
            'mesaj'    => 'Kayıt iptal edildi.',
            'sayaclar' => $this->model->sayaclar($kul, $mus, $this->panelGun()),
        ]);
    }

    /** Tarihi sürükleyerek/hızlı ötelemek için */
    public function ertele()
    {
        [$kul, $mus] = $this->kapsam();

        $id    = (int) $this->request->getPost('id');
        $tarih = $this->request->getPost('tarih');
        $k     = $this->model->find($id);

        if ($k === null || ! $this->model->duzenleyebilirMi($k, $kul)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'İşlem yapılamadı.']);
        }

        if (empty($tarih) || strtotime($tarih) === false) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Geçersiz tarih.']);
        }

        $yeni = date('Y-m-d', strtotime($tarih));
        $this->model->update($id, ['tarih' => $yeni, 'durum' => 'BEKLIYOR']);

        return $this->response->setJSON([
            'durum'    => true,
            'mesaj'    => 'Yeni tarih: ' . date('d.m.Y', strtotime($yeni)),
            'tarih'    => $yeni,
            'sayaclar' => $this->model->sayaclar($kul, $mus, $this->panelGun()),
        ]);
    }

    public function sil(int $id)
    {
        [$kul, ] = $this->kapsam();
        $k = $this->model->find($id);

        if ($k === null) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Kayıt bulunamadı.');
        }

        if (! $this->model->duzenleyebilirMi($k, $kul)) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Bu kaydı silemezsiniz.');
        }

        $this->model->delete($id);   // yumuşak silme

        return redirect()->to(site_url('ajanda'))->with('basari', 'Ajanda kaydı silindi.');
    }

    // -----------------------------------------------------------------
    //  GİRİŞ UYARISI (AJAX)
    // -----------------------------------------------------------------

    /** Girişte gösterilecek işler; günde bir kez döner */
    public function girisUyarisi()
    {
        [$kul, $mus] = $this->kapsam();

        if ((new \App\Models\AyarModel())->oku('ajanda_giris_uyari', '1') !== '1') {
            return $this->response->setJSON(['durum' => true, 'goster' => false]);
        }

        $kid   = (int) ($this->aktifKullanici['id'] ?? 0);
        $bugun = date('Y-m-d');

        $okundu = $this->db()->table('ajanda_uyari_okundu')
            ->where('kullanici_id', $kid)->where('tarih', $bugun)
            ->countAllResults() > 0;

        if ($okundu) {
            return $this->response->setJSON(['durum' => true, 'goster' => false]);
        }

        $isler = $this->model->bugunkuIsler($kul, $mus);

        return $this->response->setJSON([
            'durum'  => true,
            'goster' => $isler !== [],
            'isler'  => array_map(static fn ($k) => [
                'id'       => (int) $k['id'],
                'baslik'   => $k['baslik'],
                'tarih'    => trTarih($k['tarih']),
                'saat'     => $k['saat'] ? substr($k['saat'], 0, 5) : null,
                'gecikmis' => (bool) $k['gecikmis'],
                'oncelik'  => $k['oncelik'],
                'renk'     => $k['renk_efektif'],
                'mukellef' => $k['mukellef_unvan'],
            ], $isler),
        ]);
    }

    /** Uyarı penceresi kapatıldı — bugün bir daha gösterme */
    public function uyariOkundu()
    {
        $kid = (int) ($this->aktifKullanici['id'] ?? 0);

        $this->db()->table('ajanda_uyari_okundu')->ignore(true)->insert([
            'kullanici_id' => $kid,
            'tarih'        => date('Y-m-d'),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['durum' => true]);
    }

    // -----------------------------------------------------------------
    //  DOSYA EKLERİ
    // -----------------------------------------------------------------

    protected function ekleriAl(int $ajandaId): array
    {
        return $this->db()->table('ajanda_ek')
            ->select('ajanda_ek.*, k.ad_soyad AS yukleyen_adi')
            ->join('kullanicilar k', 'k.id = ajanda_ek.yukleyen_id', 'left')
            ->where('ajanda_id', $ajandaId)
            ->orderBy('ajanda_ek.id', 'ASC')
            ->get()->getResultArray();
    }

    /** Yüklenen dosyaları kaydeder, kullanıcıya gösterilecek mesajı döner */
    protected function ekleriYukle(int $ajandaId, int $kid): string
    {
        $dosyalar = $this->request->getFileMultiple('ekler');

        if (empty($dosyalar)) {
            return '';
        }

        $klasor = WRITEPATH . 'uploads/ajanda';

        if (! is_dir($klasor)) {
            mkdir($klasor, 0o775, true);
        }

        $enBuyuk = $this->ekBoyutKb() * 1024;
        $eklenen = 0;
        $atlanan = [];

        foreach ($dosyalar as $d) {
            if ($d === null || ! $d->isValid()) {
                continue;
            }

            $uzanti = strtolower($d->getClientExtension());

            if (! in_array($uzanti, self::EK_UZANTI, true)) {
                $atlanan[] = $d->getClientName() . ' (tür)';

                continue;
            }

            if ($d->getSize() > $enBuyuk) {
                $atlanan[] = $d->getClientName() . ' (boyut)';

                continue;
            }

            $yeniAd = $d->getRandomName();
            $d->move($klasor, $yeniAd);

            $this->db()->table('ajanda_ek')->insert([
                'ajanda_id'   => $ajandaId,
                'dosya_adi'   => mb_substr($d->getClientName(), 0, 255),
                'saklanan'    => $yeniAd,
                'boyut'       => $d->getSize(),
                'tur'         => $d->getClientMimeType(),
                'yukleyen_id' => $kid,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            $eklenen++;
        }

        $mesaj = $eklenen > 0 ? " {$eklenen} dosya eklendi." : '';

        if ($atlanan !== []) {
            $mesaj .= ' Atlanan: ' . implode(', ', $atlanan) . '.';
        }

        return $mesaj;
    }

    public function ekIndir(int $ekId)
    {
        [$kul, $mus] = $this->kapsam();

        $ek = $this->db()->table('ajanda_ek')->where('id', $ekId)->get()->getRowArray();

        if ($ek === null) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Dosya bulunamadı.');
        }

        $kayit = $this->model->detay((int) $ek['ajanda_id']);

        if ($kayit === null || ! $this->model->gorebilirMi($kayit, $kul, $mus)) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Bu dosyaya erişemezsiniz.');
        }

        $yol = WRITEPATH . 'uploads/ajanda/' . $ek['saklanan'];

        if (! is_file($yol)) {
            return redirect()->to(site_url('ajanda/detay/' . $ek['ajanda_id']))
                ->with('hata', 'Dosya diskte bulunamadı.');
        }

        return $this->response->download($yol, null)->setFileName($ek['dosya_adi']);
    }

    public function ekSil(int $ekId)
    {
        [$kul, ] = $this->kapsam();

        $ek = $this->db()->table('ajanda_ek')->where('id', $ekId)->get()->getRowArray();

        if ($ek === null) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Dosya bulunamadı.');
        }

        $kayit = $this->model->find((int) $ek['ajanda_id']);

        if ($kayit === null || ! $this->model->duzenleyebilirMi($kayit, $kul)) {
            return redirect()->to(site_url('ajanda'))->with('hata', 'Bu dosyayı silemezsiniz.');
        }

        $yol = WRITEPATH . 'uploads/ajanda/' . $ek['saklanan'];

        if (is_file($yol)) {
            unlink($yol);
        }

        $this->db()->table('ajanda_ek')->where('id', $ekId)->delete();

        return redirect()->to(site_url('ajanda/detay/' . $ek['ajanda_id']))
            ->with('basari', 'Dosya silindi.');
    }

    // -----------------------------------------------------------------
    //  YAZDIRMA
    // -----------------------------------------------------------------
    public function yazdir()
    {
        [$kul, $mus] = $this->kapsam();
        $filtre = $this->filtreAl();

        return view('ajanda/yazdir', [
            'kayitlar'   => $this->model->liste($filtre, $kul, $mus),
            'filtre'     => $filtre,
            'gorunurluk' => AjandaModel::GORUNURLUK,
            'oncelikler' => AjandaModel::ONCELIK,
            'durumlar'   => AjandaModel::DURUMLAR,
            'aktifKullanici' => $this->aktifKullanici,
        ]);
    }

    // -----------------------------------------------------------------
    //  YARDIMCILAR
    // -----------------------------------------------------------------
    protected function filtreAl(): array
    {
        return [
            'bas'         => $this->request->getGet('bas') ?: null,
            'bit'         => $this->request->getGet('bit') ?: null,
            'durum'       => $this->request->getGet('durum'),
            'gorunurluk'  => $this->request->getGet('gorunurluk'),
            'oncelik'     => $this->request->getGet('oncelik'),
            'etiket'      => $this->request->getGet('etiket'),
            'mukellef_id' => (int) $this->request->getGet('mukellef_id') ?: null,
            'atanan_id'   => (int) $this->request->getGet('atanan_id') ?: null,
            'q'           => $this->request->getGet('q'),
        ];
    }

    protected function panelGun(): int
    {
        return max(1, (int) (new \App\Models\AyarModel())->oku('ajanda_panel_gun', 7));
    }

    protected function ekBoyutKb(): int
    {
        return max(64, (int) (new \App\Models\AyarModel())->oku('ajanda_ek_boyut', 5120));
    }

    /**
     * Görev atanabilecek kullanıcılar.
     *
     * Admin herkesi görür; diğerleri yalnız kendi müşavir kapsamındaki
     * kullanıcıları (ve kendini).
     *
     * @return array<int,string>
     */
    protected function kullaniciSecenekleri(): array
    {
        static $onbellek = null;

        if ($onbellek !== null) {
            return $onbellek;
        }

        $b = $this->db()->table('kullanicilar')
            ->select('id, ad_soyad, rol')
            ->where('aktif', 1)
            ->orderBy('ad_soyad', 'ASC');

        $out = [];

        foreach ($b->get()->getResultArray() as $k) {
            $out[(int) $k['id']] = $k['ad_soyad'];
        }

        if (! $this->adminMi()) {
            $izin = $this->erisilenMusavirler();
            $kid  = (int) ($this->aktifKullanici['id'] ?? 0);

            // Aynı müşavir kapsamındaki kullanıcılar + kendisi
            $kapsam = [$kid => $out[$kid] ?? 'Ben'];

            if ($izin !== []) {
                $satirlar = $this->db()->table('kullanicilar k')
                    ->select('DISTINCT k.id, k.ad_soyad', false)
                    ->join('kullanici_musavirleri km', 'km.kullanici_id = k.id', 'left')
                    ->where('k.aktif', 1)
                    ->groupStart()
                        ->whereIn('km.musavir_id', $izin)
                        ->orWhereIn('k.musavir_id', $izin)
                    ->groupEnd()
                    ->get()->getResultArray();

                foreach ($satirlar as $s) {
                    $kapsam[(int) $s['id']] = $s['ad_soyad'];
                }
            }

            asort($kapsam);
            $out = $kapsam;
        }

        return $onbellek = $out;
    }

    /** Mükellef açılır listesi (yetki kapsamında) */
    protected function mukellefSecenekleri(): array
    {
        $b = $this->db()->table('mukellefler')
            ->select('id, unvan, kod')
            ->where('deleted_at', null)
            ->where('aktif', 1)
            ->orderBy('unvan', 'ASC');

        $izin = $this->musavirFiltresi();

        if ($izin !== null) {
            $b->whereIn('musavir_id', $izin === [] ? [0] : $izin);
        }

        $out = [];

        foreach ($b->get()->getResultArray() as $m) {
            $out[(int) $m['id']] = $m['unvan'];
        }

        return $out;
    }

    protected function db()
    {
        return \Config\Database::connect();
    }
}
