<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title><?= esc($liste['ad']) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
<style>
  body{background:#fff;padding:22px;max-width:1000px;margin:0 auto}
  h1{font-size:19px;margin-bottom:2px}
  .ust{border-bottom:2px solid #2563eb;padding-bottom:10px;margin-bottom:16px}
  .alt-detay{font-size:11px;color:#64748b}
  @media print{ @page{size:portrait;margin:1cm} }
</style>
</head><body>

<div class="ust">
  <h1><?= esc($liste['ad']) ?></h1>
  <div class="kucuk-yazi">
    <?= $ay !== null ? ayAdi((int) $ay) . ' ' : 'Tüm Yıl ' ?><?= (int) $yil ?> dönemi
    <?php if ($musavir !== null): ?>
      • <?= esc(trim(($musavir['unvan'] ?? '') . ' ' . $musavir['ad_soyad'])) ?>
    <?php endif; ?>
    • Düzenleme: <?= trTarih(date('Y-m-d')) ?>
    • <?= count($satirlar) ?> mükellef
  </div>
  <?php if (! empty($liste['aciklama'])): ?>
    <div class="kucuk-yazi"><?= esc($liste['aciklama']) ?></div>
  <?php endif; ?>
</div>

<?php if ($satirlar === []): ?>
  <div class="uyari dikkat"><span class="ik">⚠</span><div>
    Bu dönemde ödenecek tutar bulunamadı.
  </div></div>
<?php else: ?>

<table class="tablo">
  <thead>
    <tr>
      <th style="width:34px">#</th>
      <th>Mükellef</th>
      <th>VKN / TCKN</th>
      <th class="sag">Beyanname</th>
      <?php if ((int) $liste['ozel_dahil'] === 1): ?><th class="sag">Özel Ödeme</th><?php endif; ?>
      <?php if ((int) $liste['ucret_dahil'] === 1): ?><th class="sag">Muh. Ücreti</th><?php endif; ?>
      <th class="sag">TOPLAM</th>
    </tr>
  </thead>
  <tbody>
  <?php $i = 1; foreach ($satirlar as $s): ?>
    <tr>
      <td><?= $i++ ?></td>
      <td>
        <b><?= esc($s['mukellef']['unvan']) ?></b>
        <?php if (! empty($detayli)): ?>
          <div class="alt-detay">
            <?php foreach ($s['beyannameler'] as $bn): ?>
              <?= esc($bn['tur_kisa']) ?> (<?= esc($bn['donem_adi']) ?>)
              <?= number_format((float) $bn['odenecek'], 2, ',', '.') ?> •
            <?php endforeach; ?>
            <?php foreach ($s['ozel'] as $oz): ?>
              <?= esc($oz['baslik']) ?> <?= number_format((float) $oz['tutar'], 2, ',', '.') ?> •
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </td>
      <td class="kucuk-yazi"><?= esc($s['mukellef']['vergi_kimlik_no'] ?: $s['mukellef']['tc_kimlik_no']) ?></td>
      <td class="sag"><?= number_format($s['beyan_top'], 2, ',', '.') ?></td>
      <?php if ((int) $liste['ozel_dahil'] === 1): ?>
        <td class="sag"><?= $s['ozel_top'] > 0 ? number_format($s['ozel_top'], 2, ',', '.') : '—' ?></td>
      <?php endif; ?>
      <?php if ((int) $liste['ucret_dahil'] === 1): ?>
        <td class="sag"><?= $s['ucret'] > 0 ? number_format($s['ucret'], 2, ',', '.') : '—' ?></td>
      <?php endif; ?>
      <td class="sag kalin"><?= number_format($s['genel'], 2, ',', '.') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr style="background:#eee;font-weight:700;font-size:13px">
      <td colspan="3" class="sag">GENEL TOPLAM</td>
      <td class="sag"><?= number_format($toplam['beyanname'], 2, ',', '.') ?></td>
      <?php if ((int) $liste['ozel_dahil'] === 1): ?>
        <td class="sag"><?= number_format($toplam['ozel'], 2, ',', '.') ?></td>
      <?php endif; ?>
      <?php if ((int) $liste['ucret_dahil'] === 1): ?>
        <td class="sag"><?= number_format($toplam['ucret'], 2, ',', '.') ?></td>
      <?php endif; ?>
      <td class="sag" style="font-size:15px"><?= number_format($toplam['genel'], 2, ',', '.') ?> ₺</td>
    </tr>
  </tfoot>
</table>

<div style="margin-top:22px;display:flex;justify-content:space-between;font-size:12px">
  <div>
    <div style="border-top:1px solid #999;width:190px;padding-top:5px;margin-top:40px">
      Hazırlayan
    </div>
  </div>
  <div>
    <div style="border-top:1px solid #999;width:190px;padding-top:5px;margin-top:40px">
      Teslim Alan
    </div>
  </div>
</div>

<?php endif; ?>

<script>window.onload=function(){window.print()}</script>
</body></html>
