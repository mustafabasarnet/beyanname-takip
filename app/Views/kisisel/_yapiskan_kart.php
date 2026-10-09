<?php
/*
 * TEK YAPIŞKAN KART
 * Beklenen: $n (kart satırı), $renkler (palet)
 */
use App\Models\StickyNotModel;

$renkAnahtar = StickyNotModel::renkGecerli((string) $n['renk']) ? (string) $n['renk'] : 'sari';
$p           = $renkler[$renkAnahtar];
$egim        = StickyNotModel::egim((int) $n['id']);
$uzunluk     = mb_strlen((string) $n['metin']);
?>
<div class="yk-kart" draggable="true"
     data-id="<?= (int) $n['id'] ?>"
     data-sabit="<?= (int) $n['sabit'] ?>"
     data-renk="<?= esc($renkAnahtar, 'attr') ?>"
     style="--kart:<?= esc($p['kart'], 'attr') ?>;--bant:<?= esc($p['bant'], 'attr') ?>;--egim:<?= number_format($egim, 2, '.', '') ?>deg">
  <span class="yk-raptiye" aria-hidden="true"></span>
  <div class="yk-bant">
    <span class="yk-durum"><?= (int) $n['sabit'] === 1 ? '📌 Sabit' : '' ?></span>
    <span class="yk-ikonlar">
      <button type="button" class="yk-ikon<?= (int) $n['sabit'] === 1 ? ' acik' : '' ?>" data-islem="sabit"
              title="<?= (int) $n['sabit'] === 1 ? 'Sabitlemeyi kaldır' : 'Sabitle (en üstte tut)' ?>">📌</button>
      <button type="button" class="yk-ikon" data-islem="renk" title="Renk değiştir">🎨</button>
      <button type="button" class="yk-ikon" data-islem="sil" title="Notu sil">🗑</button>
    </span>
  </div>
  <div class="yk-govde" contenteditable="true" spellcheck="false" data-alan="metin"
       aria-label="Not metni"><?= esc((string) $n['metin']) ?></div>
  <div class="yk-alt">
    <span class="yk-say<?= $uzunluk > StickyNotModel::MAX_METIN ? ' asim' : '' ?>"><?= $uzunluk ?>/<?= StickyNotModel::MAX_METIN ?></span>
    <span class="yk-kaydet" aria-live="polite"></span>
  </div>
  <div class="yk-renkmenu" role="menu">
    <?php foreach ($renkler as $anahtar => $r): ?>
      <button type="button" class="yk-renknokta<?= $anahtar === $renkAnahtar ? ' secili' : '' ?>"
              data-renk="<?= esc($anahtar, 'attr') ?>" title="<?= esc($r['ad'], 'attr') ?>"
              style="--nokta:<?= esc($r['kart'], 'attr') ?>"></button>
    <?php endforeach; ?>
  </div>
</div>
