<?php
/*
 * ARŞİV LİSTESİ — arşivlenmiş kartlar (yalnız sahibi)
 * Beklenen: $arsivNotlar
 */
use App\Models\StickyNotModel;

$arsivNotlar = $arsivNotlar ?? [];
?>
<?php if ($arsivNotlar === []): ?>
  <div class="yk-arsiv-bos">Arşiv boş. Bir kartın 🗃 düğmesiyle notu buraya taşıyabilirsiniz.</div>
<?php else: ?>
  <?php foreach ($arsivNotlar as $n): ?>
    <?php $ozet = trim((string) $n['metin']) === '' ? '(metin yok)' : kisalt((string) $n['metin'], 80); ?>
    <div class="yk-arsiv-satir" data-id="<?= (int) $n['id'] ?>">
      <div class="yk-arsiv-ac">
        <b><?= esc(($n['baslik'] ?? '') !== '' ? (string) $n['baslik'] : '(başlıksız)') ?></b>
        <span> — <?= esc($ozet) ?></span>
      </div>
      <button type="button" class="yk-kucuk" data-arsiv-islem="geri">↩ Geri al</button>
      <button type="button" class="yk-kucuk ik" data-arsiv-islem="sil">🗑 Kalıcı sil</button>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
