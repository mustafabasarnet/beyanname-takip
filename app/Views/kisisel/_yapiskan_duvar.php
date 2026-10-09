<?php
/*
 * YAPIŞKAN NOT DUVARI — kart listesi (sunucu tarafından üretilir)
 * Beklenen: $notlar, $renkler, $yazi
 *
 * Güvenlik: metin esc() ile basılır; renk ve sabit değerleri sunucu
 * whitelist'inden gelir (stil değerleri sabit palet anahtarından türetilir).
 */
$notlar  = $notlar ?? [];
$renkler = $renkler ?? [];
$yazi    = $yazi ?? '#1f2937';
?>
<?php if ($notlar === []): ?>
  <div class="yk-bos">
    <div class="yk-bos-ikon">📌</div>
    <div><b>Henüz yapışkan notunuz yok.</b></div>
    <div class="kucuk-yazi">“➕ Yeni not” ile ilk kartınızı ekleyin. Kartlar tarihe bağlı değildir;
      kapatıp açsanız da yerinde durur.</div>
  </div>
<?php else: ?>
  <?php
  $sabitler = array_filter($notlar, static fn ($n) => (int) $n['sabit'] === 1);
  $digerler = array_filter($notlar, static fn ($n) => (int) $n['sabit'] !== 1);
  ?>
  <?php if ($sabitler !== []): ?>
    <div class="yk-baslik">📌 Sabitlenenler</div>
  <?php endif; ?>
  <?php foreach ($sabitler as $n): ?>
    <?= view('kisisel/_yapiskan_kart', ['n' => $n, 'renkler' => $renkler]) ?>
  <?php endforeach; ?>

  <?php if ($sabitler !== [] && $digerler !== []): ?>
    <div class="yk-baslik">Diğer notlar</div>
  <?php endif; ?>
  <?php foreach ($digerler as $n): ?>
    <?= view('kisisel/_yapiskan_kart', ['n' => $n, 'renkler' => $renkler]) ?>
  <?php endforeach; ?>
<?php endif; ?>
