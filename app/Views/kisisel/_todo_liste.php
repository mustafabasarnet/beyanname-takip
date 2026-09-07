<?php
/**
 * KİŞİSEL TO-DO — LİSTE PARÇASI (AJAX ile yeniden çizilir)
 *
 * Hem sayfa açılışında hem her AJAX işleminde tek kaynaktır. Satırlar
 * düzenleme formunu doldurabilmesi için data-* öznitelikleri taşır.
 */
$acik   = $acik ?? [];
$biten  = $biten ?? [];
$bugun  = date('Y-m-d');

/** Öncelik rozeti rengi + simgesi */
$oncelikMeta = [
    'acil'   => ['Acil',   'kirmizi'],
    'yuksek' => ['Yüksek', 'turuncu'],
    'normal' => ['Normal', 'mavi'],
    'dusuk'  => ['Düşük',  'gri'],
];
?>

<!-- AÇIK GÖREVLER -->
<?php if ($acik === []): ?>
  <div class="kn-bos" id="kn-acik-bos">Henüz açık görev yok.</div>
<?php else: ?>
  <?php foreach ($acik as $g): ?>
    <?php
    $oncelik = $g['oncelik'] ?? 'normal';
    $oMeta   = $oncelikMeta[$oncelik] ?? $oncelikMeta['normal'];
    $gecikti = ! empty($g['son_tarih']) && $g['son_tarih'] < $bugun;
    $bugunMu = $g['son_tarih'] === $bugun;
    ?>
    <div class="gorev-satir" data-gorev="<?= (int) $g['id'] ?>"
         data-baslik="<?= esc($g['baslik'], 'attr') ?>"
         data-metin="<?= esc($g['metin'] ?? '', 'attr') ?>"
         data-oncelik="<?= esc($oncelik, 'attr') ?>"
         data-etiket="<?= esc($g['etiket'] ?? '', 'attr') ?>"
         data-son="<?= esc($g['son_tarih'] ?? '', 'attr') ?>">
      <button type="button" class="gorev-kutu kn-tamamla" title="Tamamlandı olarak işaretle" aria-label="Tamamla"></button>
      <div class="gorev-metin">
        <div class="gorev-baslik">
          <?= esc($g['baslik']) ?>
          <?php if (! empty($g['etiket'])): ?>
            <span class="kn-etiket"><?= esc($g['etiket']) ?></span>
          <?php endif; ?>
        </div>
        <?php if (! empty($g['metin'])): ?><div class="gorev-not"><?= esc($g['metin']) ?></div><?php endif; ?>
        <div class="gorev-tarih">
          <?php if (! empty($g['son_tarih'])): ?>
            <?php if ($gecikti): ?><span style="color:#dc2626;font-weight:700">⏰ <?= trTarih($g['son_tarih']) ?> geçti</span>
            <?php elseif ($bugunMu): ?><span style="color:#dc2626;font-weight:700">🔴 Bugün son gün</span>
            <?php else: ?>📅 Son: <?= trTarih($g['son_tarih']) ?><?php endif; ?>
            ·
          <?php endif; ?>
          <span class="kn-onc <?= $oMeta[1] ?>"><?= $oMeta[0] ?></span>
        </div>
      </div>
      <div class="gorev-islem">
        <button type="button" class="sil-btn kn-duzenle" title="Düzenle" aria-label="Düzenle">✏️</button>
        <button type="button" class="sil-btn kn-sil" title="Sil" aria-label="Sil">🗑</button>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- TAMAMLANANLAR -->
<?php if ($biten !== []): ?>
  <details class="kn-biten" style="margin-top:8px">
    <summary class="kucuk-yazi" style="cursor:pointer;color:var(--gri-500,#64748b)">
      ✓ Tamamlananlar (<?= count($biten) ?>)
    </summary>
    <?php foreach ($biten as $g): ?>
      <div class="gorev-satir" data-gorev="<?= (int) $g['id'] ?>">
        <button type="button" class="gorev-kutu kn-tamamla"
                style="border:2px solid #059669;border-radius:5px;background:#059669;color:#fff"
                title="Geri aç" aria-label="Geri aç">✓</button>
        <div class="gorev-metin">
          <div class="gorev-baslik tamam"><?= esc($g['baslik']) ?>
            <?php if (! empty($g['etiket'])): ?>
              <span class="kn-etiket"><?= esc($g['etiket']) ?></span>
            <?php endif; ?>
          </div>
          <?php if (! empty($g['metin'])): ?><div class="gorev-not tamam"><?= esc($g['metin']) ?></div><?php endif; ?>
          <?php if (! empty($g['son_tarih'])): ?>
            <div class="gorev-tarih">📅 Son: <?= trTarih($g['son_tarih']) ?></div>
          <?php endif; ?>
        </div>
        <div class="gorev-islem">
          <button type="button" class="sil-btn kn-sil" title="Kalıcı sil" aria-label="Sil">🗑</button>
        </div>
      </div>
    <?php endforeach; ?>
  </details>
<?php endif; ?>
