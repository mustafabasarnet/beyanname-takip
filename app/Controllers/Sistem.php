<?php

namespace App\Controllers;

use App\Libraries\TopluSilici;
use App\Libraries\Yedekleyici;
use App\Models\BeyannameTakipModel;
use App\Models\BeyannameTuruModel;
use App\Models\MukellefModel;
use App\Models\MusavirModel;

/**
 * Sistem yönetimi: veritabanı yedekleme, geri yükleme ve toplu veri silme.
 *
 * TAMAMI YALNIZCA YÖNETİCİ (admin) rolüne açıktır.
 * Rota tanımında `auth:admin` filtresi vardır; ayrıca her metotta
 * ikinci bir kontrol yapılır (savunmada derinlik).
 */
class Sistem extends BaseController
{
    /** Her metotta çalışan ikinci kapı — rota filtresi atlanırsa diye */
    protected function yoneticiMi()
    {
        if (! $this->adminMi()) {
            return redirect()->to(site_url('panel'))
                ->with('hata', 'Bu bölüm yalnızca yönetici hesabına açıktır.');
        }

        return null;
    }

    // =================================================================
    //  YEDEKLEME
    // =================================================================

    public function yedekleme()
    {
        if ($r = $this->yoneticiMi()) {
            return $r;
        }

        $y = new Yedekleyici();

        return $this->goster('sistem/yedekleme', [
            'tablolar' => $y->tablolar(),
            'toplam'   => $y->toplamBoyut(),
            'azamiYukleme' => $this->azamiYuklemeBoyutu(),
        ], 'Veritabanı Yedekleme');
    }

    /** Yedeği .sql dosyası olarak indirir */
    public function yedekIndir()
    {
        if (! $this->adminMi()) {
            return redirect()->to(site_url('panel'))
                ->with('hata', 'Bu işlem yalnızca yönetici hesabına açıktır.');
        }

        $y = new Yedekleyici();

        $secili    = $this->request->getPost('tablolar') ?? [];
        $veriDahil = $this->request->getPost('sema_only') === null;

        // Güvenlik: yalnızca gerçekten var olan tablo adları kabul edilir
        $gecerli = array_column($y->tablolar(), 'ad');
        $secili  = array_values(array_intersect((array) $secili, $gecerli));

        $ad = $y->dosyaAdi();

        // Çıktı tamponunu kapat — büyük yedeklerde bellek şişmesin
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        @set_time_limit(0);

        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $ad . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        $y->akitarakUret($secili, $veriDahil);

        exit;
    }

    /** Geri yükleme onay ekranı */
    public function geriYukleme()
    {
        if ($r = $this->yoneticiMi()) {
            return $r;
        }

        $y = new Yedekleyici();

        return $this->goster('sistem/geri_yukleme', [
            'toplam'       => $y->toplamBoyut(),
            'azamiYukleme' => $this->azamiYuklemeBoyutu(),
        ], 'Yedekten Geri Yükleme');
    }

    /** .sql dosyasını çalıştırır */
    public function geriYukleCalistir()
    {
        if ($r = $this->yoneticiMi()) {
            return $r;
        }

        // Yazılı onay: kullanıcı "GERI YUKLE" yazmalı
        $onay = mb_strtoupper(trim((string) $this->request->getPost('onay')), 'UTF-8');
        $onay = str_replace(['İ', 'Ü'], ['I', 'U'], $onay);

        if ($onay !== 'GERI YUKLE') {
            return redirect()->to(site_url('sistem/geri-yukleme'))
                ->with('hata', 'Onay metnini doğru yazmadınız. İşlem yapılmadı.');
        }

        $dosya = $this->request->getFile('yedek');

        if ($dosya === null || ! $dosya->isValid()) {
            return redirect()->to(site_url('sistem/geri-yukleme'))
                ->with('hata', 'Dosya yüklenemedi: '
                    . ($dosya !== null ? $dosya->getErrorString() : 'Dosya seçilmedi.'));
        }

        if (strtolower($dosya->getClientExtension()) !== 'sql') {
            return redirect()->to(site_url('sistem/geri-yukleme'))
                ->with('hata', 'Yalnızca .sql uzantılı yedek dosyası yükleyebilirsiniz.');
        }

        $sonuc = (new Yedekleyici())->geriYukle($dosya->getTempName());

        if (! $sonuc['basarili'] && $sonuc['calisan'] === 0) {
            return redirect()->to(site_url('sistem/geri-yukleme'))
                ->with('hata', $sonuc['mesaj']);
        }

        if ($sonuc['hatalar'] !== []) {
            session()->setFlashdata('geri_yukleme_hatalari', $sonuc['hatalar']);
        }

        // Oturum bilgileri artık geçersiz olabilir (kullanıcı tablosu değişti)
        return redirect()->to(site_url('cikis'))
            ->with('basari', $sonuc['mesaj'] . ' Güvenlik için yeniden giriş yapın.');
    }

    // =================================================================
    //  VERİ YÖNETİMİ (TOPLU SİLME)
    // =================================================================

    public function veriYonetimi()
    {
        if ($r = $this->yoneticiMi()) {
            return $r;
        }

        $s = new TopluSilici();

        return $this->goster('sistem/veri_yonetimi', [
            'istatistik'  => $s->istatistik(),
            'yilDagilimi' => $s->yilDagilimi(),
            'turler'      => (new BeyannameTuruModel())->aktifler(),
            'durumlar'    => BeyannameTakipModel::DURUMLAR,
            'musavirler'  => (new MusavirModel())->seceneklar(),
        ], 'Veri Yönetimi');
    }

    // ---------------- Mükellef toplu silme (çöp kutusuna) ----------------

    public function mukellefTopluSil()
    {
        if (! $this->adminMi()) {
            return $this->jsonHata('Bu işlem yalnızca yönetici hesabına açıktır.', 403);
        }

        $idler = $this->request->getPost('idler') ?? [];

        if (! is_array($idler) || $idler === []) {
            return $this->jsonHata('Hiç mükellef seçilmedi.');
        }

        $sonuc = (new TopluSilici())->mukellefleriCopeAt($idler);

        if ($sonuc['silinen'] === 0) {
            return $this->jsonHata('Hiçbir mükellef silinemedi.');
        }

        return $this->jsonBasarili(
            $sonuc['silinen'] . ' mükellef çöp kutusuna taşındı. '
            . 'Geri almak için Sistem → Veri Yönetimi → Çöp Kutusu.',
            $sonuc
        );
    }

    // ---------------- Çöp kutusu ----------------

    public function copKutusu()
    {
        if ($r = $this->yoneticiMi()) {
            return $r;
        }

        $s = new TopluSilici();

        return $this->goster('sistem/cop_kutusu', [
            'kayitlar' => $s->copKutusu(),
        ], 'Çöp Kutusu');
    }

    public function copGeriYukle()
    {
        if ($r = $this->yoneticiMi()) {
            return $r;
        }

        $idler = $this->request->getPost('idler') ?? [];

        if (! is_array($idler) || $idler === []) {
            return redirect()->to(site_url('sistem/cop-kutusu'))
                ->with('hata', 'Hiç kayıt seçilmedi.');
        }

        $adet = (new TopluSilici())->geriYukle($idler);

        return redirect()->to(site_url('sistem/cop-kutusu'))
            ->with($adet > 0 ? 'basari' : 'hata',
                $adet > 0 ? $adet . ' mükellef geri yüklendi.' : 'Geri yükleme yapılamadı.');
    }

    public function copKaliciSil()
    {
        if ($r = $this->yoneticiMi()) {
            return $r;
        }

        $onay = mb_strtoupper(trim((string) $this->request->getPost('onay')), 'UTF-8');
        $onay = str_replace(['İ'], ['I'], $onay);

        if ($onay !== 'SIL') {
            return redirect()->to(site_url('sistem/cop-kutusu'))
                ->with('hata', 'Onay kutusuna SİL yazmadınız. İşlem yapılmadı.');
        }

        $s = new TopluSilici();

        if ($this->request->getPost('tumu') !== null) {
            $sonuc = $s->copKutusunuBosalt();
        } else {
            $idler = $this->request->getPost('idler') ?? [];

            if (! is_array($idler) || $idler === []) {
                return redirect()->to(site_url('sistem/cop-kutusu'))
                    ->with('hata', 'Hiç kayıt seçilmedi.');
            }

            $sonuc = $s->mukellefleriKaliciSil($idler, true);
        }

        if ($sonuc['silinen'] === 0) {
            return redirect()->to(site_url('sistem/cop-kutusu'))
                ->with('hata', 'Kalıcı silme yapılamadı.');
        }

        return redirect()->to(site_url('sistem/cop-kutusu'))->with(
            'basari',
            $sonuc['silinen'] . ' mükellef kalıcı olarak silindi ('
            . $sonuc['beyanname'] . ' beyanname, ' . $sonuc['evrak'] . ' evrak kaydı ile birlikte).'
        );
    }

    // ---------------- Beyanname kayıtları toplu temizlik ----------------

    /** AJAX: filtreye kaç kayıt uyuyor? (silmeden önce) */
    public function beyannameOnizle()
    {
        if (! $this->adminMi()) {
            return $this->jsonHata('Bu işlem yalnızca yönetici hesabına açıktır.', 403);
        }

        $f     = $this->silmeFiltresi();
        $s     = new TopluSilici();

        if (! $s->filtreVarMi($f)) {
            return $this->jsonHata('En az bir filtre seçmelisiniz (yıl, tür, durum veya mükellef).');
        }

        $sonuc = $s->beyannameOnizle($f);

        $dagilim = [];

        foreach ($sonuc['durum_dagilimi'] as $k => $v) {
            $dagilim[BeyannameTakipModel::DURUMLAR[$k] ?? $k] = $v;
        }

        $sonuc['durum_dagilimi'] = $dagilim;

        return $this->jsonBasarili($sonuc['adet'] . ' kayıt bulundu.', $sonuc);
    }

    public function beyannameSil()
    {
        if ($r = $this->yoneticiMi()) {
            return $r;
        }

        $onay = mb_strtoupper(trim((string) $this->request->getPost('onay')), 'UTF-8');
        $onay = str_replace(['İ'], ['I'], $onay);

        if ($onay !== 'SIL') {
            return redirect()->to(site_url('sistem/veri-yonetimi'))
                ->with('hata', 'Onay kutusuna SİL yazmadınız. İşlem yapılmadı.');
        }

        $f     = $this->silmeFiltresi();
        $sonuc = (new TopluSilici())->beyannameSil($f);

        if (! empty($sonuc['hata'])) {
            return redirect()->to(site_url('sistem/veri-yonetimi'))->with('hata', $sonuc['hata']);
        }

        return redirect()->to(site_url('sistem/veri-yonetimi'))->with(
            $sonuc['silinen'] > 0 ? 'basari' : 'hata',
            $sonuc['silinen'] > 0
                ? $sonuc['silinen'] . ' beyanname takip kaydı silindi. '
                    . 'Gerekirse Toplu Dönem Üretimi ile yeniden oluşturabilirsiniz.'
                : 'Filtreye uyan kayıt bulunamadı.'
        );
    }

    // ---------------- Evrak kayıtları toplu temizlik ----------------

    public function evrakSil()
    {
        if ($r = $this->yoneticiMi()) {
            return $r;
        }

        $onay = mb_strtoupper(trim((string) $this->request->getPost('onay')), 'UTF-8');
        $onay = str_replace(['İ'], ['I'], $onay);

        if ($onay !== 'SIL') {
            return redirect()->to(site_url('sistem/veri-yonetimi'))
                ->with('hata', 'Onay kutusuna SİL yazmadınız. İşlem yapılmadı.');
        }

        $f = [
            'yil'         => $this->request->getPost('evrak_yil'),
            'ay'          => $this->request->getPost('evrak_ay'),
            'mukellef_id' => $this->request->getPost('evrak_mukellef_id'),
        ];

        $sonuc = (new TopluSilici())->evrakSil($f);

        if (! empty($sonuc['hata'])) {
            return redirect()->to(site_url('sistem/veri-yonetimi'))->with('hata', $sonuc['hata']);
        }

        return redirect()->to(site_url('sistem/veri-yonetimi'))->with(
            $sonuc['silinen'] > 0 ? 'basari' : 'hata',
            $sonuc['silinen'] > 0
                ? $sonuc['silinen'] . ' evrak kaydı silindi.'
                : 'Filtreye uyan evrak kaydı bulunamadı.'
        );
    }

    // -----------------------------------------------------------------

    protected function silmeFiltresi(): array
    {
        return [
            'yil'         => $this->request->getPost('yil'),
            'tur_id'      => $this->request->getPost('tur_id'),
            'durum'       => $this->request->getPost('durum'),
            'mukellef_id' => $this->request->getPost('mukellef_id'),
            'musavir_id'  => $this->request->getPost('musavir_id'),
        ];
    }

    /** PHP'nin izin verdiği azami yükleme boyutu (insan okur biçimde) */
    protected function azamiYuklemeBoyutu(): string
    {
        $bayta = static function (string $v): int {
            $v    = trim($v);
            $son  = strtolower($v[strlen($v) - 1] ?? '');
            $sayi = (int) $v;

            return match ($son) {
                'g'     => $sayi * 1024 * 1024 * 1024,
                'm'     => $sayi * 1024 * 1024,
                'k'     => $sayi * 1024,
                default => $sayi,
            };
        };

        $u = $bayta((string) ini_get('upload_max_filesize'));
        $p = $bayta((string) ini_get('post_max_size'));

        $en = min(array_filter([$u, $p])) ?: 0;

        return (new Yedekleyici())->boyutYaz($en);
    }
}
