<?php

namespace App\Controllers;

use App\Models\AyarModel;
use App\Models\KisiselNotModel;

/**
 * KİŞİSEL GÜNLÜK NOT + TO-DO
 *
 * Her kullanıcının kendine özel not/todo alanı. Veri tamamen izole:
 * tüm işlemler giriş yapan kullanıcının id'siyle yapılır; yönetici dahil
 * hiçbir başka rol başkasının kayıtlarını göremez veya değiştiremez.
 *
 * To-Do işlemleri (ekle / tamamla / güncelle / sil) AJAX üzerinden JSON
 * yanıt döner; yanıta güncel liste HTML'i eklenir (BT.post kullanılır).
 */
class Kisisel extends BaseController
{
    protected KisiselNotModel $model;

    public function __construct()
    {
        $this->model = new KisiselNotModel();
    }

    /** Giriş yapan kullanıcının id'si (her zaman var: auth filtresi) */
    protected function ben(): int
    {
        return (int) ($this->aktifKullanici['id'] ?? 0);
    }

    /** Veritabanı bağlantısı (proje deseni: Ajanda::db() ile aynı) */
    protected function db()
    {
        return \Config\Database::connect();
    }

    /**
     * AJAX yanıtı: işlem sonucu + güncel liste HTML + sayaçlar.
     */
    protected function todoYanit(string $mesaj = '', bool $durum = true, int $kod = 200)
    {
        $kid = $this->ben();

        $acik  = $this->model->acikGorevler($kid);
        $biten = $this->model->bitenGorevler($kid);

        $html = view('kisisel/_todo_liste', [
            'acik'  => $acik,
            'biten' => $biten,
        ]);

        return $this->response->setStatusCode($durum ? $kod : ($kod === 200 ? 400 : $kod))
            ->setJSON([
                'durum'      => $durum,
                'mesaj'      => $mesaj,
                'listeHtml'  => $html,
                'acikSayisi' => count($acik),
                'bitenSayisi'=> count($biten),
            ]);
    }

    public function index()
    {
        $kid = $this->ben();

        // Varsayılan bugün; ?tarih= ile geçmişe not bakılabilir
        $tarihHam = $this->request->getGet('tarih');
        $tarih    = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tarihHam)
            ? $tarihHam : date('Y-m-d');

        return $this->goster('kisisel/index', [
            'bugun'   => date('Y-m-d'),
            'tarih'   => $tarih,
            'gunNotu' => $this->model->gunNotu($kid, $tarih),
            'gecmis'  => $this->model->gecmisNotlar($kid),
            'acik'    => $this->model->acikGorevler($kid),
            'biten'   => $this->model->bitenGorevler($kid),
            'oncelikler' => KisiselNotModel::ONCELIKLER,
        ], 'Kişisel Notlarım');
    }

    /** Günlük notu kaydet / sil (AJAX) */
    public function notKaydet()
    {
        $tarih = (string) $this->request->getPost('tarih');
        $metin = trim((string) $this->request->getPost('metin'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih)) {
            $tarih = date('Y-m-d');
        }

        $silindi = $this->model->gunNotuKaydet($this->ben(), $tarih, $metin) === 0 && $metin === '';
        $gecmis  = $this->model->gecmisNotlar($this->ben());
        $gecmisHtml = view('kisisel/_gecmis', ['gecmis' => $gecmis, 'tarih' => $tarih]);

        return $this->response->setJSON([
            'durum'      => true,
            'mesaj'      => $silindi ? 'Not silindi.' : 'Not kaydedildi.',
            'silindi'    => $silindi,
            'gecmisHtml' => $gecmisHtml,
        ]);
    }

    /** Görev ekle (AJAX) */
    public function gorevEkle()
    {
        $baslik = trim((string) $this->request->getPost('baslik'));

        if ($baslik === '') {
            return $this->todoYanit('Görev adı boş olamaz.', false, 400);
        }

        $this->model->gorevEkle(
            $this->ben(),
            $baslik,
            $this->request->getPost('metin'),
            (string) $this->request->getPost('oncelik'),
            $this->request->getPost('etiket'),
            $this->request->getPost('son_tarih')
        );

        return $this->todoYanit('Görev eklendi.');
    }

    /** Görevi tamamla / geri aç (AJAX) */
    public function gorevTers()
    {
        $id = (int) $this->request->getPost('id');

        if ($id <= 0 || ! $this->model->gorevTersCevir($this->ben(), $id)) {
            return $this->todoYanit('Görev bulunamadı.', false, 404);
        }

        return $this->todoYanit('Durum güncellendi.');
    }

    /** Görevi güncelle — başlık/not/öncelik/etiket/son tarih (AJAX) */
    public function gorevGuncelle()
    {
        $id = (int) $this->request->getPost('id');

        $veri = [
            'baslik'   => $this->request->getPost('baslik'),
            'metin'    => $this->request->getPost('metin'),
            'oncelik'  => $this->request->getPost('oncelik'),
            'etiket'   => $this->request->getPost('etiket'),
            'son_tarih'=> $this->request->getPost('son_tarih'),
        ];

        if ($id <= 0 || ! $this->model->gorevGuncelle($this->ben(), $id, $veri)) {
            return $this->todoYanit('Görev güncellenemedi (ad boş olabilir).', false, 400);
        }

        return $this->todoYanit('Görev güncellendi.');
    }

    /** Görevi sil (AJAX) */
    public function gorevSil()
    {
        $id = (int) $this->request->getPost('id');

        if ($id <= 0 || ! $this->model->kisiselSil($this->ben(), $id)) {
            return $this->todoYanit('Görev bulunamadı.', false, 404);
        }

        return $this->todoYanit('Görev silindi.');
    }

    /** Geçmiş günlük notu sil (AJAX) */
    public function gecmisSil()
    {
        $id = (int) $this->request->getPost('id');

        if ($id <= 0 || ! $this->model->kisiselSil($this->ben(), $id)) {
            return $this->response->setStatusCode(404)->setJSON([
                'durum' => false, 'mesaj' => 'Kayıt bulunamadı.',
            ]);
        }

        $gecmis = $this->model->gecmisNotlar($this->ben());
        $tarih  = (string) $this->request->getPost('tarih');

        return $this->response->setJSON([
            'durum'      => true,
            'mesaj'      => 'Not silindi.',
            'gecmisHtml' => view('kisisel/_gecmis', ['gecmis' => $gecmis, 'tarih' => $tarih]),
        ]);
    }

    // =================================================================
    //  GİRİŞ HATIRLATMASI (kişisel To-Do — son tarihe göre)
    // =================================================================

    /**
     * Giriş hatırlatma penceresi verisi (AJAX/JSON).
     *
     * Günde BİR kez gösterilir: pencere kapatılınca `kisisel_uyari_okundu`
     * tablosuna o günün kaydı yazılır (ajanda uyarısı ile aynı desen) — çıkış
     * yapıp aynı gün yeniden girilse de pencere tekrar açılmaz. Ayar kapalıysa
     * veya hatırlatılacak görev yoksa goster=false.
     *
     * Yalnız giriş yapan kullanıcının KENDİ görevleri döner.
     */
    public function girisUyarisi()
    {
        $ayar = new AyarModel();

        if ($ayar->oku('kisisel_giris_uyari', '1') !== '1') {
            return $this->response->setJSON(['durum' => true, 'goster' => false]);
        }

        $kid = $this->ben();

        if ($this->uyariOkunduMu($kid)) {
            return $this->response->setJSON(['durum' => true, 'goster' => false]);
        }

        $gun   = (int) $ayar->oku('kisisel_uyari_gun', '3');
        $liste = $this->model->uyarilacakGorevler($kid, $gun);

        if ($liste['toplam'] === 0) {
            return $this->response->setJSON([
                'durum'     => true,
                'goster'    => false,
                'tarihsiz'  => $this->model->tarihsizAcikSayisi($kid),
            ]);
        }

        $cevir = static fn (array $satirlar) => array_map(static fn ($g) => [
            'id'          => (int) $g['id'],
            'baslik'      => (string) $g['baslik'],
            'metin'       => $g['metin'] === null ? null : kisalt((string) $g['metin'], 90),
            'oncelik'     => (string) ($g['oncelik'] ?? 'normal'),
            'etiket'      => $g['etiket'] === null ? null : (string) $g['etiket'],
            'son_tarih'   => trTarih((string) $g['son_tarih']),
            'kalan_gun'   => (int) $g['kalan_gun'],
            'gecikme_gun' => (int) $g['gecikme_gun'],
            'gecikmis'    => (bool) $g['gecikmis'],
        ], $satirlar);

        return $this->response->setJSON([
            'durum'    => true,
            'goster'   => true,
            'gecikmis' => $cevir($liste['gecikmis']),
            'bugun'    => $cevir($liste['bugun']),
            'yaklasan' => $cevir($liste['yaklasan']),
            'toplam'   => (int) $liste['toplam'],
            'tarihsiz' => $this->model->tarihsizAcikSayisi($kid),
            // Menü rozeti için: kullanıcının TÜM açık görevi (pencerede
            // görünmeyen uzak tarihli/tarihsiz görevler dahil)
            'acik'     => $this->model->acikGorevSayisi($kid),
            'gun'      => max(0, min(30, $gun)),
        ]);
    }

    /** Hatırlatma penceresi kapatıldı — o gün bir daha gösterilmez. */
    public function uyariOkundu()
    {
        $this->db()->table('kisisel_uyari_okundu')->ignore(true)->insert([
            'kullanici_id' => $this->ben(),
            'tarih'        => date('Y-m-d'),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['durum' => true]);
    }

    /** Bu kullanıcı için bugünün hatırlatması kapatıldı mı? */
    protected function uyariOkunduMu(int $kullaniciId): bool
    {
        if ($kullaniciId <= 0) {
            return false;
        }

        return $this->db()->table('kisisel_uyari_okundu')
            ->where('kullanici_id', $kullaniciId)
            ->where('tarih', date('Y-m-d'))
            ->countAllResults() > 0;
    }
}
