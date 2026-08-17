<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<form method="get" class="filtre-bar">
  <div class="form-grup"><label>Yıl</label>
    <select name="yil" data-oto-filtre>
      <?php foreach (yilSecenekleri() as $y): ?><option value="<?= $y ?>" <?= $y === $yil ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
    </select></div>
</form>
<div class="kart">
  <div class="kart-baslik"><h2>🎯 <?= $yil ?> Mali Müşavir Performansı</h2></div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar"><table class="tablo">
      <thead><tr><th>Mali Müşavir</th><th class="orta">Toplam</th><th class="orta">Bekliyor</th><th class="orta">Hazır</th>
        <th class="orta">Onaylandı</th><th class="orta">Gecikmiş</th><th style="width:170px">Tamamlanma</th></tr></thead>
      <tbody>
      <?php foreach ($satirlar as $s):
        $oran = (int) $s['toplam'] > 0 ? round((int) $s['onaylandi'] / (int) $s['toplam'] * 100) : 0; ?>
        <tr>
          <td><span class="rozet" style="background:<?= esc($s['renk']) ?>22;color:<?= esc($s['renk']) ?>"><?= esc($s['ad_soyad']) ?></span></td>
          <td class="orta kalin"><?= (int) $s['toplam'] ?></td>
          <td class="orta"><?= (int) $s['bekliyor'] ?></td>
          <td class="orta"><?= (int) $s['hazir'] ?></td>
          <td class="orta metin-yesil kalin"><?= (int) $s['onaylandi'] ?></td>
          <td class="orta"><?= (int) $s['gecikmis'] > 0 ? '<span class="rozet kirmizi">' . (int) $s['gecikmis'] . '</span>' : '-' ?></td>
          <td>
            <div class="satir arali kucuk-yazi mb0" style="margin-bottom:3px"><span>%<?= $oran ?></span></div>
            <div class="progress"><div class="dolu" style="width:<?= $oran ?>%;background:<?= $oran >= 70 ? 'var(--yesil)' : ($oran >= 40 ? 'var(--sari)' : 'var(--kirmizi)') ?>"></div></div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
<?= $this->endSection() ?>
