<?php

namespace App\Controllers;

use App\Models\BeyannameTakipModel;
use App\Models\MukellefModel;
use App\Models\MusavirModel;

class Raporlar extends BaseController
{
    public function index()
    {
        $yil = (int) ($this->request->getGet('yil') ?? date('Y'));

        return $this->goster('raporlar/index', [
            'yil'  => $yil,
            'ozet' => (new BeyannameTakipModel())->ozet($yil, $this->musavirFiltresi(), 'beyan'),
        ], 'Raporlar');
    }

    public function gecikmis()
    {
        return $this->goster('raporlar/gecikmis', [
            'kayitlar' => (new BeyannameTakipModel())->gecikmisler($this->musavirFiltresi(), 500),
        ], 'Gecikmiş Beyannameler');
    }

    /** Müşavir bazlı performans tablosu */
    public function musavirPerformans()
    {
        $yil = (int) ($this->request->getGet('yil') ?? date('Y'));
        $db  = \Config\Database::connect();

        $b = $db->table('beyanname_takip bt')
            ->select('mus.id, mus.ad_soyad, mus.renk,
                      COUNT(*) as toplam,
                      SUM(CASE WHEN bt.durum = "ONAYLANDI" THEN 1 ELSE 0 END) as onaylandi,
                      SUM(CASE WHEN bt.durum = "HAZIR" THEN 1 ELSE 0 END) as hazir,
                      SUM(CASE WHEN bt.durum = "BEKLIYOR" THEN 1 ELSE 0 END) as bekliyor,
                      SUM(CASE WHEN bt.son_tarih < CURDATE() AND bt.durum IN ("BEKLIYOR","HAZIR") THEN 1 ELSE 0 END) as gecikmis', false)
            ->join('mukellefler m', 'm.id = bt.mukellef_id')
            ->join('musavirler mus', 'mus.id = m.musavir_id')
            ->where('m.deleted_at', null)
            ->where('bt.yil', $yil);

        if ($mid = $this->musavirFiltresi()) {
            $b->whereIn('mus.id', array_map('intval', $mid));
        }

        return $this->goster('raporlar/musavir_performans', [
            'satirlar' => $b->groupBy('mus.id')->orderBy('mus.ad_soyad', 'ASC')->get()->getResultArray(),
            'yil'      => $yil,
        ], 'Mali Müşavir Performansı');
    }

    /** Mükellef bazlı özet */
    public function mukellefOzet()
    {
        $yil = (int) ($this->request->getGet('yil') ?? date('Y'));
        $db  = \Config\Database::connect();

        $b = $db->table('beyanname_takip bt')
            ->select('m.id, m.unvan, m.ise_baslama_tarihi, m.terk_tarihi,
                      COUNT(*) as toplam,
                      SUM(CASE WHEN bt.durum = "ONAYLANDI" THEN 1 ELSE 0 END) as onaylandi,
                      SUM(CASE WHEN bt.son_tarih < CURDATE() AND bt.durum IN ("BEKLIYOR","HAZIR") THEN 1 ELSE 0 END) as gecikmis', false)
            ->join('mukellefler m', 'm.id = bt.mukellef_id')
            ->where('m.deleted_at', null)
            ->where('bt.yil', $yil);

        if ($mid = $this->musavirFiltresi()) {
            $b->whereIn('m.musavir_id', array_map('intval', $mid));
        }

        return $this->goster('raporlar/mukellef_ozet', [
            'satirlar' => $b->groupBy('m.id')->orderBy('gecikmis', 'DESC')->orderBy('m.unvan', 'ASC')->get()->getResultArray(),
            'yil'      => $yil,
        ], 'Mükellef Özet Raporu');
    }
}
