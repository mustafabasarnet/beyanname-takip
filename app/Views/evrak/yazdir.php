<!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8">
<title>Evrak Takip Çizelgesi</title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
<style>
body{background:#fff;padding:18px}h1{font-size:17px;margin-bottom:4px}
/* Stil dosyası kopyalanmamış olsa da takip dışı hücre ayırt edilebilsin */
.evrak-hucre.yok{
  background:repeating-linear-gradient(45deg,#fafafa,#fafafa 4px,#eceff3 4px,#eceff3 8px);
  color:#cbd5e1
}
</style>
</head><body>
<?php
// Eski controller ile de çalışsın
$muafiyet = $muafiyet ?? [];

// Beklenen evrak sayısı: takip dışı hücreler hariç
$bekBeklenen = 0;
$bekGelen    = 0;
$bekMuaf     = 0;

foreach ($mukellefler as $mSay) {
    foreach ($turler as $tSay) {
        $hSay = $matris[(int) $mSay['id']][(int) $tSay['id']] ?? null;
        $dSay = \App\Models\EvrakTakipModel::etkinDurum(
            $hSay,
            isset($muafiyet[(int) $mSay['id']][(int) $tSay['id']])
        );

        if ($dSay === 'YOK') {
            $bekMuaf++;
        } else {
            $bekBeklenen++;
            if ($dSay === 'GELDI') {
                $bekGelen++;
            }
        }
    }
}
?>
<h1>Aylık Evrak Takip Çizelgesi — <?= ayAdi($ay) ?> <?= $yil ?></h1>
<div class="kucuk-yazi mb16">
  Yazdırma: <?= trTarihUzun(date('Y-m-d')) ?> • <?= count($mukellefler) ?> mükellef •
  <?= $bekGelen ?>/<?= $bekBeklenen ?> evrak geldi
  <?php if ($bekMuaf > 0): ?>
    • <?= $bekMuaf ?> hücre takip dışı (—)
  <?php endif; ?>
</div>
<table class="matris">
  <thead><tr><th class="sol-sabit">Mükellef</th>
    <?php foreach ($turler as $t): ?><th><?= esc($t['kisa_ad']) ?></th><?php endforeach; ?>
    <th>Not</th></tr></thead>
  <tbody>
  <?php foreach ($mukellefler as $m): $mid = (int) $m['id']; ?>
    <tr>
      <td class="sol-sabit"><?= esc($m['unvan']) ?></td>
      <?php foreach ($turler as $t):
          $tid   = (int) $t['id'];
          $h     = $matris[$mid][$tid] ?? null;
          $durum = \App\Models\EvrakTakipModel::etkinDurum($h, isset($muafiyet[$mid][$tid]));
      ?>
        <td class="evrak-hucre <?= $durum === 'GELDI' ? 'geldi' : ($durum === 'YOK' ? 'yok' : 'gelmedi') ?>">
          <?= $durum === 'GELDI' ? '✓' : ($durum === 'YOK' ? '—' : '✕') ?>
        </td>
      <?php endforeach; ?>
      <td style="text-align:left;font-size:11px"><?= esc($notlar[$mid] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if ($bekMuaf > 0): ?>
  <div class="kucuk-yazi" style="margin-top:10px">
    <b>—</b> işareti: bu mükellefte o evrak türü bulunmadığı için takip edilmiyor
    (eksik evrak sayılmaz).
  </div>
<?php endif; ?>
<script>window.onload=function(){window.print()}</script>
</body></html>
