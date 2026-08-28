<?php

namespace App\Controllers;

use App\Models\BeyannameTakipModel;
use App\Models\EdefterTakipModel;
use App\Models\EvrakTakipModel;
use App\Models\KarsitIncelemeModel;
use App\Models\MukellefModel;
use App\Models\MusavirModel;

class Panel extends BaseController
{
    public function index()
    {
        $yil       = (int) ($this->request->getGet('yil') ?? date('Y'));
        $ay        = (int) ($this->request->getGet('ay') ?? date('n'));
        $musavirId = $this->musavirFiltresi();

        // Tür dağılımı tablosu hangi eksende sayılsın?
        //  'beyan' = son tarihi bu ayda olanlar (varsayılan, "bu ay ne vereceğim")
        //  'donem' = ait olduğu dönem bu ayda olanlar
        $mod = $this->request->getGet('mod') === 'donem' ? 'donem' : 'beyan';

        $takip   = new BeyannameTakipModel();
        $evrak   = new EvrakTakipModel();
        $edefter = new EdefterTakipModel();

        // Evrak takibi beyannameye göre 1 ay geriden gelir (ör. Ağustos beyannameleri için Temmuz evrakları toplanır)
        $evrakAy = $ay - 1;
        $evrakYil = $yil;
        if ($evrakAy === 0) {
            $evrakAy = 12;
            $evrakYil--;
        }

        // Ajanda: gecikmiş + yaklaşan işler (tablo yoksa sessizce boş geçer)
        $ajanda = ['liste' => [], 'sayaclar' => ['gecikmis' => 0, 'bugun' => 0, 'yaklasan' => 0, 'toplam' => 0]];

        try {
            $db = \Config\Database::connect();

            if ($db->tableExists('ajanda')) {
                $am  = new \App\Models\AjandaModel();
                $gun = max(1, (int) (new \App\Models\AyarModel())->oku('ajanda_panel_gun', 7));

                $ajanda['liste']    = array_slice(
                    $am->yaklasan($this->aktifKullanici, $this->erisilenMusavirler(), $gun), 0, 8
                );
                $ajanda['sayaclar'] = $am->sayaclar($this->aktifKullanici, $this->erisilenMusavirler(), $gun);
            }
        } catch (\Throwable $e) {
            // Ajanda modülü kurulu değilse panel yine de açılmalı
        }

        return $this->goster('dashboard/index', [
            'ajanda' => $ajanda,
            'yil'          => $yil,
            'ay'           => $ay,
            'ozet'         => $takip->ozet($yil, $musavirId, 'beyan'),
            'mukellefStat' => (new MukellefModel())->istatistik($musavirId),
            'yaklasanlar'  => $takip->yaklasanlar(7, $musavirId, 15),
            'gecikmisler'  => $takip->gecikmisler($musavirId, 15),
            'evrakYil'     => $evrakYil,
            'evrakAy'      => $evrakAy,
            'evrakOzet'    => $evrak->ozet($evrakYil, $evrakAy, $musavirId),
            'evrakGelmeyen'=> array_slice($evrak->evrakiGelmeyenler($evrakYil, $evrakAy, $musavirId), 0, 10),
            'grafik'       => $takip->aylikGrafik($yil, $musavirId, 'beyan'),
            // Beyanname türü bazında durum tablosu. Türler o ay gerçekten
            // var olan kayıtlardan üretilir; geçici vergiler yalnızca
            // verildikleri aylarda listeye girer.
            'turDagilim'   => $takip->turDagilimi($yil, $ay, $musavirId, $mod),
            'dagilimMod'   => $mod,
            // E-defter berat kartı — yalnızca o ay yüklenecek berat varsa çıkar
            'edefterOzet'  => $edefter->ozet($yil, $ay, $musavirId),
            'edefterDonem' => $edefter->donemEtiketi($yil, $ay, $musavirId),
            'musavirler'   => $this->secilebilirMusavirler(),
            'karsitOzet'   => (new KarsitIncelemeModel())->ozet($musavirId),
            'karsitYaklasan' => (new KarsitIncelemeModel())->yaklasanlar(
                (int) ((new \App\Models\AyarModel())->oku('karsit_uyari_gun', 7)), $musavirId, 8),
        ], 'Kontrol Paneli');
    }

    /**
     * AJAX: tür dağılımı tablosundaki bir sayıya tıklanınca açılan liste.
     *
     * Panelden ayrılmadan "KDV1'de bekleyen 118 mükellef kim?" sorusunu
     * yanıtlar. Yetki kapsamı musavirFiltresi() ile korunur — personel/müşavir
     * kendi portföyü dışını göremez.
     */
    public function turListesi()
    {
        $yil    = (int) ($this->request->getGet('yil') ?? date('Y'));
        $ay     = (int) ($this->request->getGet('ay') ?? date('n'));
        $turId  = (int) $this->request->getGet('tur_id');
        $durum  = (string) ($this->request->getGet('durum') ?? '');
        $mod    = $this->request->getGet('mod') === 'donem' ? 'donem' : 'beyan';

        if ($turId <= 0) {
            return $this->response->setJSON(['durum' => false, 'mesaj' => 'Beyanname türü belirtilmedi.']);
        }

        $filtre = [
            'yil'        => $yil,
            'ay'         => $ay ?: null,
            'tarih_modu' => $mod,
            'tur_id'     => $turId,
            'musavir_id' => $this->musavirFiltresi(),
        ];

        // "kalan" gerçek bir durum değil; Bekliyor+Hazır demek.
        // Bu yüzden ayrı bir bayrakla ele alınır.
        if ($durum === 'GECIKMIS') {
            $filtre['gecikmis'] = 1;
        } elseif ($durum === 'KALAN') {
            $filtre['durum_liste'] = ['BEKLIYOR', 'HAZIR'];
        } elseif ($durum !== '') {
            $filtre['durum'] = $durum;
        }

        $kayitlar = (new BeyannameTakipModel())->cizelge($filtre + ['limit' => 500]);
        $liste    = [];

        foreach ($kayitlar as $k) {
            $liste[] = [
                'id'        => (int) $k['id'],
                'mukellef'  => $k['mukellef_unvan'],
                'kimlik'    => $k['vergi_kimlik_no'] ?: $k['tc_kimlik_no'],
                'donem'     => $k['donem_adi'],
                'son_tarih' => trTarih($k['son_tarih']),
                'durum'     => $k['durum'],
                'durum_ad'  => BeyannameTakipModel::DURUMLAR[$k['durum']] ?? $k['durum'],
                'gecikmis'  => $k['son_tarih'] < date('Y-m-d')
                    && in_array($k['durum'], ['BEKLIYOR', 'HAZIR'], true),
            ];
        }

        return $this->response->setJSON([
            'durum'    => true,
            'adet'     => count($liste),
            'kayitlar' => $liste,
        ]);
    }

    /** Takvim görünümü */
    public function takvim()
    {
        return $this->goster('dashboard/takvim', [
            'yil' => (int) ($this->request->getGet('yil') ?? date('Y')),
            'ay'  => (int) ($this->request->getGet('ay') ?? date('n')),
        ], 'Beyanname Takvimi');
    }

    /** Takvim için JSON veri */
    public function takvimVeri()
    {
        $yil = (int) ($this->request->getGet('yil') ?? date('Y'));
        $ay  = (int) ($this->request->getGet('ay') ?? date('n'));

        $rows = (new BeyannameTakipModel())->cizelge([
            'yil'        => $yil,
            'ay'         => $ay,
            'musavir_id' => $this->musavirFiltresi(),
        ]);

        $gunler = [];

        foreach ($rows as $r) {
            $gun = (int) date('j', strtotime($r['son_tarih']));
            $gunler[$gun][] = [
                'id'       => (int) $r['id'],
                'mukellef' => $r['mukellef_unvan'],
                'tur'      => $r['tur_kisa'],
                'renk'     => $r['tur_renk'],
                'durum'    => $r['durum'],
            ];
        }

        return $this->response->setJSON(['durum' => true, 'gunler' => $gunler]);
    }
}
