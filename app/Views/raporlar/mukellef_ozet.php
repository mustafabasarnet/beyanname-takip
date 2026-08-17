<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>
<form method="get" class="filtre-bar">
  <div class="form-grup"><label>Yıl</label>
    <select name="yil" data-oto-filtre>
      <?php foreach (yilSecenekleri() as $y): ?><option value="<?= $y ?>" <?= $y === $yil ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
    </select></div>
  <div class="form-grup" style="min-width:200px"><label>Ara</label>
    <input type="text" class="girdi" data-tablo-ara="#ozet-tablo" placeholder="Mükellef ara..."></div>
</form>
<div class="kart">
  <div class="kart-baslik"><h2>🏢 <?= $yil ?> Mükellef Özet Raporu (<?= count($satirlar) ?>)</h2></div>
  <div class="kart-govde sikisik">
    <div class="tablo-sar"><table class="tablo" id="ozet-tablo">
      <thead><tr><th>Mükellef</th><th>İşe Başlama</th><th>Terk</th><th class="orta">Toplam Dönem</th>
        <th class="orta">Onaylandı</th><th class="orta">Gecikmiş</th><th style="width:160px">Tamamlanma</th></tr></thead>
      <tbody>
      <?php foreach ($satirlar as $s):
        $oran = (int) $s['toplam'] > 0 ? round((int) $s['onaylandi'] / (int) $s['toplam'] * 100) : 0; ?>
        <tr class="<?= (int) $s['gecikmis'] > 0 ? 'gecikmis-satir' : '' ?>">
          <td><a href="<?= site_url('mukellefler/detay/' . $s['id']) ?>" class="kalin"><?= esc($s['unvan']) ?></a></td>
          <td class="kucuk-yazi"><?= trTarih($s['ise_baslama_tarihi']) ?></td>
          <td class="kucuk-yazi"><?= ! empty($s['terk_tarihi']) ? '<span class="metin-kirmizi">' . trTarih($s['terk_tarihi']) . '</span>' : '-' ?></td>
          <td class="orta kalin"><?= (int) $s['toplam'] ?></td>
          <td class="orta metin-yesil"><?= (int) $s['onaylandi'] ?></td>
          <td class="orta"><?= (int) $s['gecikmis'] > 0 ? '<span class="rozet kirmizi">' . (int) $s['gecikmis'] . '</span>' : '-' ?></td>
          <td><div class="progress"><div class="dolu" style="width:<?= $oran ?>%;background:<?= $oran >= 70 ? 'var(--yesil)' : ($oran >= 40 ? 'var(--sari)' : 'var(--kirmizi)') ?>"></div></div>
            <span class="kucuk-yazi">%<?= $oran ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
<?= $this->endSection() ?>
