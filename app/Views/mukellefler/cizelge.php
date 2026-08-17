<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title><?= esc($mukellef['unvan']) ?> — <?= $yil ?> Beyanname Çizelgesi</title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
<style>body{background:#fff;padding:18px}h1{font-size:16px}</style>
</head><body>
<h1><?= esc($mukellef['unvan']) ?></h1>
<div class="kucuk-yazi mb16">
  VKN/TCKN: <?= esc(vknTckn($mukellef)) ?> •
  İşe başlama: <?= trTarih($mukellef['ise_baslama_tarihi']) ?>
  <?= ! empty($mukellef['terk_tarihi']) ? ' • Terk: ' . trTarih($mukellef['terk_tarihi']) : '' ?>
  • <?= $yil ?> yılı çizelgesi
  <?php $ggC = gencGirisimciDurum($mukellef, $yil); ?>
  <?php if ($ggC['var']): ?>
    • <b>🌱 <?= esc($ggC['metin']) ?></b>
  <?php endif; ?>
</div>

<table class="matris">
  <thead><tr><th class="sol-sabit">Beyanname</th>
  <?php for ($i = 1; $i <= 12; $i++): ?><th><?= ayKisa($i) ?></th><?php endfor; ?></tr></thead>
  <tbody>
  <?php foreach ($matris as $bilgi):
    $donemler = $bilgi['donemler'] ?? [];
    $ayHarita = [];
    foreach ($donemler as $d) { $ayHarita[(int) date('n', strtotime($d['son_tarih']))][] = $d; } ?>
    <tr>
      <td class="sol-sabit"><?= esc($bilgi['tur']['kisa']) ?></td>
      <?php for ($ay = 1; $ay <= 12; $ay++):
        $h = $ayHarita[$ay] ?? []; ?>
        <?php if ($h === []): ?><td class="hucre bos"></td>
        <?php else: foreach ($h as $d):
          $s = ['BEKLIYOR'=>'○','HAZIR'=>'◐','ONAYLANDI'=>'●','VERILMEYECEK'=>'—'][$d['durum']] ?? '○'; ?>
          <td class="hucre d-<?= strtolower($d['durum']) ?>"><?= $s ?><span class="tarih"><?= date('d', strtotime($d['son_tarih'])) ?><?php
            $sy = (int) date('Y', strtotime($d['son_tarih']));
            echo $sy !== (int) $yil ? '<b>/' . date('y', strtotime($d['son_tarih'])) . '</b>' : '';
          ?></span></td>
        <?php endforeach; endif; ?>
      <?php endfor; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h3 style="font-size:13px;margin:18px 0 8px">Aylık Notlar</h3>
<table class="tablo"><tbody>
<?php for ($ay = 1; $ay <= 12; $ay++): if (empty($notlar[$ay])) continue; ?>
  <tr><td style="width:100px"><b><?= ayAdi($ay) ?></b></td><td><?= esc($notlar[$ay]) ?></td></tr>
<?php endfor; ?>
</tbody></table>

<script>window.onload=function(){window.print()}</script>
</body></html>
