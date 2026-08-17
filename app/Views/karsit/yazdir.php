<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Karşıt İnceleme Tutanakları</title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
<style>body{background:#fff;padding:18px}h1{font-size:17px;margin-bottom:3px}</style>
</head><body>
<h1>Karşıt İnceleme Tutanakları</h1>
<div class="kucuk-yazi mb16">
  Yazdırma: <?= trTarihUzun(date('Y-m-d')) ?> • <?= count($kayitlar) ?> kayıt
</div>
<table class="tablo">
  <thead><tr><th>Mükellef</th><th>YMM</th><th>Geliş</th><th>Son Cevap</th>
    <th>Gönderim</th><th>Durum</th><th>Not</th></tr></thead>
  <tbody>
  <?php foreach ($kayitlar as $k): ?>
    <tr>
      <td><?= esc($k['mukellef_unvan']) ?></td>
      <td><?= esc($k['ymm_adi']) ?></td>
      <td><?= trTarih($k['gelis_tarihi']) ?></td>
      <td><?= $k['son_cevap_tarihi'] ? trTarih($k['son_cevap_tarihi']) : '—' ?></td>
      <td><?= $k['gonderim_tarihi'] ? trTarih($k['gonderim_tarihi']) : '—' ?></td>
      <td><?= esc($durumlar[$k['durum']] ?? $k['durum']) ?></td>
      <td><?= esc($k['not_metni'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<script>window.onload=function(){window.print()}</script>
</body></html>
