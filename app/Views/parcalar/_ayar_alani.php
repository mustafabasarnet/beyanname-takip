<?php
/**
 * TEK BİR AYAR ALANI (ortak parça)
 *
 * Ayarın tipine göre uygun girdi öğesini çizer: aç/kapa kutusu, sayı,
 * yüzde, tutar ya da açılır menü. Böylece kullanıcı serbest metin alanına
 * yanlış değer yazamaz.
 *
 * BEKLENEN DEĞİŞKENLER
 *   $aa_anahtar  string  Ayar anahtarı (form alanı: ayar[anahtar])
 *   $aa_tanim    array   ayarTanimlari() girdisi
 *   $aa_deger    string  Mevcut değer
 *
 * NOT: include ile çağrılır ($this->include() yerel değişkenleri taşımaz).
 */
$aa_tip = $aa_tanim['tip'] ?? 'metin';

/*
 * Form alan adı.
 *
 * esc(..., 'attr') KULLANILMAZ: köşeli parantezleri &#x5B; / &#x5D; olarak
 * kaçırır ve sayfadaki diğer ayar alanlarıyla (name="ayar[x]") biçim
 * tutarsızlığı doğar. Anahtar kullanıcı girdisi değil, ayarTanimlari()
 * içindeki sabit listeden gelir; yine de güvenlik için harf/rakam/alt
 * çizgi dışındaki her şey temizlenir.
 */
$aa_anahtar = preg_replace('/[^a-z0-9_]/i', '', (string) $aa_anahtar);
$aa_ad      = 'ayar[' . $aa_anahtar . ']';
?>
<div class="form-grup<?= $aa_tip === 'onay' ? ' tam' : '' ?>">

  <?php if ($aa_tip === 'onay'): ?>
    <?php /* Aç/kapa: etiket kutunun yanında durur */ ?>
    <label class="onay">
      <?php /* Kapalıyken POST'a hiç gelmez; gizli alan 0 gönderir ki
               ayar kapatılabilsin. */ ?>
      <input type="hidden" name="<?= $aa_ad ?>" value="0">
      <input type="checkbox" name="<?= $aa_ad ?>" value="1"
             <?= (string) $aa_deger === '1' ? 'checked' : '' ?>>
      <span><?= esc($aa_tanim['ad']) ?></span>
    </label>

  <?php else: ?>
    <label><?= esc($aa_tanim['ad']) ?></label>

    <?php if ($aa_tip === 'secim'): ?>
      <select name="<?= $aa_ad ?>" class="girdi">
        <?php foreach (($aa_tanim['secenekler'] ?? []) as $aa_k => $aa_v): ?>
          <option value="<?= esc($aa_k, 'attr') ?>"
            <?= (string) $aa_deger === (string) $aa_k ? 'selected' : '' ?>>
            <?= esc($aa_v) ?>
          </option>
        <?php endforeach; ?>
      </select>

    <?php elseif ($aa_tip === 'oran'): ?>
      <div style="display:flex;align-items:center;gap:6px">
        <input type="number" name="<?= $aa_ad ?>" class="girdi"
               min="0" max="100" step="0.01" value="<?= esc($aa_deger) ?>"
               style="max-width:110px">
        <span class="kucuk-yazi" style="font-weight:600">%</span>
      </div>

    <?php elseif ($aa_tip === 'sayi'): ?>
      <div style="display:flex;align-items:center;gap:6px">
        <input type="number" name="<?= $aa_ad ?>" class="girdi"
               min="<?= (int) ($aa_tanim['min'] ?? 0) ?>"
               max="<?= (int) ($aa_tanim['max'] ?? 999999) ?>"
               step="<?= esc($aa_tanim['adim'] ?? '1', 'attr') ?>"
               value="<?= esc($aa_deger) ?>" style="max-width:130px">
        <?php if (! empty($aa_tanim['birim'])): ?>
          <span class="kucuk-yazi" style="font-weight:600"><?= esc($aa_tanim['birim']) ?></span>
        <?php endif; ?>
      </div>

    <?php elseif ($aa_tip === 'para'): ?>
      <?php /* Tutar okunaklı olsun diye binlik ayırıcıyla gösterilir;
               kaydederken trParaCoz() ile çözülür. */ ?>
      <div style="display:flex;align-items:center;gap:6px">
        <input type="text" name="<?= $aa_ad ?>" class="girdi"
               inputmode="decimal" style="max-width:170px;text-align:right"
               value="<?= is_numeric($aa_deger)
                   ? esc(number_format((float) $aa_deger, 0, ',', '.'))
                   : esc($aa_deger) ?>">
        <span class="kucuk-yazi" style="font-weight:600"><?= esc($aa_tanim['birim'] ?? '₺') ?></span>
      </div>

    <?php else: ?>
      <input type="text" name="<?= $aa_ad ?>" class="girdi"
             value="<?= esc($aa_deger) ?>">
    <?php endif; ?>
  <?php endif; ?>

  <?php if (! empty($aa_tanim['aciklama'])): ?>
    <span class="yardim"><?= esc($aa_tanim['aciklama']) ?></span>
  <?php endif; ?>
</div>
