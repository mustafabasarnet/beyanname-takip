<?php

namespace App\Controllers;

use App\Models\AyarModel;
use App\Models\EdefterAdimModel;
use App\Models\EdefterTakipModel;
use App\Models\KullaniciModel;

/**
 * E-DEFTER BERAT TAKİBİ
 *
 * Büro iş akışı adım adım izlenir:
 *   Banka Temin → Banka İşleme → Çek İşleme → Mizan Kontrol → Hazır → Onay
 * Adımlar Tanımlar menüsünden düzenlenebilir.
 */
class Edefter extends BaseController
{
    protected EdefterTakipModel $model;

    public const SAYFA_ADETLERI = [25, 50, 100, 250];

    public const VARSAYILAN_ADET = 50;

    public function __construct()
    {
        $this->model = new EdefterTakipModel();
    }

    // -----------------------------------------------------------------
    public function index()
    {
        $filtre = $this->filtreAl();
        $adet   = $this->adetBelirle();

        $sayfaFiltre          = $filtre;
        $sayfaFiltre['limit'] = $adet;
        $sayfaFiltre['ofset'] = 0;

        $toplam = $this->model->cizelgeSayisi($filtre);

        return $this->goster('edefter/index', [
            'kayitlar'    => $this->model->cizelge($sayfaFiltre),
            'filtre'      => $filtre,
            'adimlar'     => (new EdefterAdimModel())->aktifler(),
            'durumlar'    => EdefterTakipModel::DURUMLAR,
            // _satirlar parçası bu değeri ZORUNLU olarak controller'dan alır:
            // $this->include() üst görünümün yerel değişkenlerini taşımaz.
            'yetki'       => $this->duzenleyebilirMi(),
            'donemTipleri' => EdefterTakipModel::DONEM_TIPLERI,
            'musavirler'  => $this->secilebilirMusavirler(),
            'personeller' => $this->sorumluSecenekleri(),
            'ozet'        => $this->model->ozet(
                (int) $filtre['yil'],
                $filtre['ay'] ?: null,
                $this->musavirFiltresi(),
                $filtre['tarih_modu']
            ),
            'toplamKayit' => $toplam,
            'sayfaAdedi'  => $adet,
            'adetSecenek' => self::SAYFA_ADETLERI,
            'dahaVar'     => $toplam > $adet,
        ], 'E-Defter Takip');
    }

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

        $html = view('edefter/_satirlar', [
            'kayitlar' => $kayitlar,
            'adimlar'  => (new EdefterAdimModel())->aktifler(),
            'durumlar' => EdefterTakipModel::DURUMLAR,
            'yetki'    => $this->duzenleyebilirMi(),
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

    // -----------------------------------------------------------------
    //  ADIM İŞARETLEME
    // -----------------------------------------------------------------

    /** AJAX: tek adımı işaretle / kaldır */
    public function adim()
    {
        if (! $this->duzenleyebilirMi()) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Bu işlem için yetkiniz yok.']);
        }

        $takipId = (int) $this->request->getPost('takip_id');
        $adimId  = (int) $this->request->getPost('adim_id');
        $tamam   = $this->request->getPost('tamam') === '1';

        if ($takipId <= 0 || $adimId <= 0) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Eksik bilgi.']);
        }

        if (! $this->kayitErisilebilirMi($takipId)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Bu kayda erişemezsiniz.']);
        }

        $sonuc = $this->model->adimIsaretle(
            $takipId,
            $adimId,
            $tamam,
            (int) ($this->aktifKullanici['id'] ?? 0) ?: null
        );

        return $this->response->setJSON($this->yanit($sonuc));
    }

    /** AJAX: tüm adımları işaretle / temizle */
    public function hepsi()
    {
        if (! $this->duzenleyebilirMi()) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Bu işlem için yetkiniz yok.']);
        }

        $takipId = (int) $this->request->getPost('takip_id');
        $tamam   = $this->request->getPost('tamam') === '1';

        if ($takipId <= 0 || ! $this->kayitErisilebilirMi($takipId)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Kayıt bulunamadı.']);
        }

        $sonuc = $this->model->hepsiniIsaretle(
            $takipId,
            $tamam,
            (int) ($this->aktifKullanici['id'] ?? 0) ?: null
        );

        return $this->response->setJSON($this->yanit($sonuc));
    }

    /** AJAX: durumu elle değiştir (özellikle "Yüklenmeyecek") */
    public function durum()
    {
        if (! $this->duzenleyebilirMi()) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Bu işlem için yetkiniz yok.']);
        }

        $takipId = (int) $this->request->getPost('id');
        $yeni    = (string) $this->request->getPost('durum');

        if (! isset(EdefterTakipModel::DURUMLAR[$yeni])) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Geçersiz durum.']);
        }

        if ($takipId <= 0 || ! $this->kayitErisilebilirMi($takipId)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Kayıt bulunamadı.']);
        }

        $this->model->update($takipId, ['durum' => $yeni]);

        // "Yüklenmeyecek" dışına çıkılıyorsa durum adımlardan yeniden hesaplanır
        $sonuc = $yeni === 'YUKLENMEYECEK'
            ? ['durum' => $yeni, 'durum_ad' => EdefterTakipModel::DURUMLAR[$yeni]]
            : $this->model->durumYenile($takipId);

        return $this->response->setJSON($this->yanit($sonuc));
    }

    /** AJAX: satır notu */
    public function not()
    {
        if (! $this->duzenleyebilirMi()) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Yetkiniz yok.']);
        }

        $takipId = (int) $this->request->getPost('id');

        if ($takipId <= 0 || ! $this->kayitErisilebilirMi($takipId)) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Kayıt bulunamadı.']);
        }

        $metin = trim((string) $this->request->getPost('not'));
        $this->model->update($takipId, ['not_metni' => $metin !== '' ? mb_substr($metin, 0, 300) : null]);

        return $this->response->setJSON(['durum' => true, 'not' => $metin]);
    }

    // -----------------------------------------------------------------
    //  DÖNEM ÜRETİMİ
    // -----------------------------------------------------------------
    public function topluUret()
    {
        if (! $this->duzenleyebilirMi()) {
            return redirect()->to(site_url('edefter'))->with('hata', 'Bu işlem için yetkiniz yok.');
        }

        $yil  = (int) ($this->request->getGet('yil') ?? date('Y'));
        $ozet = $this->model->topluUret($yil, $this->musavirFiltresi());

        return redirect()->to(site_url('edefter?yil=' . $yil))->with('basari', sprintf(
            '%d mükellef için %d yeni dönem oluşturuldu, %d dönem güncellendi, %d gereksiz dönem silindi.',
            $ozet['mukellef'], $ozet['eklenen'], $ozet['guncellenen'], $ozet['silinen']
        ));
    }

    // -----------------------------------------------------------------
    //  YARDIMCILAR
    // -----------------------------------------------------------------

    /**
     * AJAX yanıtı hazırlar.
     *
     * DİKKAT — 'durum' anahtarı iki farklı anlamda kullanılıyordu:
     * yanıtın başarı bayrağı (true/false) VE kaydın durumu (BEKLIYOR/DEVAM…).
     * ['durum' => true] + $sonuc birleşiminde sol taraf kazandığı için kayıt
     * durumu yanıta hiç girmiyor, tarayıcıda durum kutusu boşalıyordu.
     * Kayıt durumu artık 'kayit_durum' anahtarıyla taşınır.
     */
    protected function yanit(array $sonuc): array
    {
        $kayitDurum = $sonuc['durum'] ?? null;
        unset($sonuc['durum']);

        return ['durum' => true, 'kayit_durum' => $kayitDurum] + $sonuc;
    }

    protected function filtreAl(): array
    {
        return [
            'yil'         => (int) ($this->request->getGet('yil') ?? date('Y')),
            'ay'          => $this->ayBelirle(),
            // 'berat' = beratın yükleneceği tarih (varsayılan)
            // 'donem' = defterin ait olduğu dönem
            'tarih_modu'  => $this->request->getGet('mod') === 'donem' ? 'donem' : 'berat',
            'donem_tipi'  => $this->request->getGet('donem_tipi'),
            'durum'       => $this->request->getGet('durum'),
            'sorumlu_id'  => $this->request->getGet('sorumlu_id'),
            'musavir_id'  => $this->kapsamBelirle($this->request->getGet('musavir_id')),
            'q'           => $this->request->getGet('q'),
            'gecikmis'    => $this->request->getGet('gecikmis'),
        ];
    }

    /**
     * Ay filtresi.
     *
     * Varsayılan İÇİNDE BULUNULAN AY: ekran açıldığında "bu ay hangi
     * beratları yükleyeceğim" sorusuna cevap versin. "Tüm Aylar" için
     * ay=0 gönderilir (boş dize tarayıcıdan da gelebildiği için ayrımı
     * netleştirmek adına 0 kullanılır).
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

    protected function adetBelirle(): int
    {
        $ham = $this->request->getGet('adet');

        if ($ham !== null && in_array((int) $ham, self::SAYFA_ADETLERI, true)) {
            setcookie('edefter_adet', (string) (int) $ham, time() + 31536000, '/');

            return (int) $ham;
        }

        $cerez = (int) ($_COOKIE['edefter_adet'] ?? 0);

        return in_array($cerez, self::SAYFA_ADETLERI, true) ? $cerez : self::VARSAYILAN_ADET;
    }

    /**
     * Adım işaretleme yetkisi.
     * Personel de takip yapabilir — banka/çek/mizan işini zaten o yürütüyor.
     * (tahakkukYetkisiVarMi ile aynı kapsam: admin + müşavir + personel)
     */
    protected function duzenleyebilirMi(): bool
    {
        return $this->tahakkukYetkisiVarMi();
    }

    /** Kayıt kullanıcının yetki kapsamında mı? */
    protected function kayitErisilebilirMi(int $takipId): bool
    {
        if ($this->adminMi()) {
            return true;
        }

        $izin = $this->musavirFiltresi();

        if ($izin === null) {
            return true;
        }

        $row = $this->model->db->table('edefter_takip et')
            ->select('m.musavir_id')
            ->join('mukellefler m', 'm.id = et.mukellef_id')
            ->where('et.id', $takipId)
            ->get()->getRowArray();

        return $row !== null && in_array((int) $row['musavir_id'], array_map('intval', $izin), true);
    }

    /** E-defter sorumlusu seçenekleri (filtre için) */
    protected function sorumluSecenekleri(): array
    {
        $b = $this->model->db->table('mukellefler m')
            ->select('k.id, k.ad_soyad')
            ->join('kullanicilar k', 'k.id = m.edefter_sorumlu_id')
            ->where('m.deleted_at', null)
            ->whereIn('m.edefter_donem', ['AYLIK', 'UC_AYLIK']);

        $izin = $this->musavirFiltresi();

        if (is_array($izin) && $izin !== []) {
            $b->whereIn('m.musavir_id', array_map('intval', $izin));
        }

        $out = [];

        foreach ($b->groupBy('k.id, k.ad_soyad')->orderBy('k.ad_soyad')->get()->getResultArray() as $r) {
            $out[(int) $r['id']] = $r['ad_soyad'];
        }

        return $out;
    }
}
