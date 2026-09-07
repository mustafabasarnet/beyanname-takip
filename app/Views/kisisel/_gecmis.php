<?php
/** KİŞİSEL — GEÇMİŞ GÜNLÜK NOTLAR parçası (AJAX ile yenilenir) */
$gecmis = $gecmis ?? [];
$tarih  = $tarih ?? date('Y-m-d');
?>
<?php if ($gecmis !== []): ?>
  <?php foreach ($gecmis as $n): ?>
    <?php if (($n['tarih'] ?? '') === $tarih) { continue; } /* üstte zaten açık */ ?>
    <div class="gecmis-satir">
      <div class="gecmis-tarih"><?= trTarih($n['tarih']) ?></div>
      <div class="gecmis-metin"><?= esc($n['metin']) ?></div>
      <button type="button" class="sil-btn kn-gecmis-sil" data-id="<?= (int) $n['id'] ?>"
              title="Sil" aria-label="Sil">🗑</button>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="kn-bos" id="kn-gecmis-bos">Henüz geçmiş not yok.</div>
<?php endif; ?>
