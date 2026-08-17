<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Beyanname Takip Çizelgesi</title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
<style>body{background:#fff;padding:18px}h1{font-size:17px;margin-bottom:4px}</style>
</head><body>
<h1>Beyanname Takip Çizelgesi</h1>
<div class="kucuk-yazi mb16">
  <?php $md = $filtre['tarih_modu'] ?? 'beyan'; ?>
  <?= esc($filtre['yil']) ?> <?= $md === 'donem' ? 'dönemi' : 'beyan yılı' ?><?= ! empty($filtre['ay']) ? ' — ' . ayAdi((int) $filtre['ay']) : '' ?>
  <?php if (! empty($filtre['defter_tipi'])): ?>
    • Defter: <b><?= esc(defterTipiAdi($filtre['defter_tipi'])) ?></b>
  <?php endif; ?>
  • Yazdırma: <?= trTarihUzun(date('Y-m-d')) ?> • Toplam <?= count($kayitlar) ?> kayıt
</div>
<table class="tablo">
  <thead><tr><th>Mükellef</th><th>VKN/TCKN</th><th>Defter</th><th>Beyanname</th><th>Dönem</th>
    <th>Son Tarih</th><th>Durum</th><th>Not</th></tr></thead>
  <tbody>
  <?php foreach ($kayitlar as $k): ?>
    <tr>
      <td><?= esc($k['mukellef_unvan']) ?></td>
      <td><?= esc($k['vergi_kimlik_no'] ?: $k['tc_kimlik_no']) ?></td>
      <td><?= esc(defterTipiKisa($k['defter_tipi'])) ?></td>
      <td><?= esc($k['tur_kisa']) ?></td>
      <td><?= esc($k['donem_adi']) ?></td>
      <td><?= trTarih($k['son_tarih']) ?><?= $k['kaydirma_nedeni'] ? ' *' : '' ?></td>
      <td><?= esc($durumlar[$k['durum']] ?? $k['durum']) ?></td>
      <td><?= esc($k['not_metni'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="kucuk-yazi mt16">* Tatil/hafta sonu nedeniyle ilk iş gününe kaydırılmıştır.</p>
<script>window.onload=function(){window.print()}</script>
</body></html>
