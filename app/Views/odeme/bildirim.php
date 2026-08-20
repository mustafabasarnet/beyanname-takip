<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Ödeme Bildirimi<?= isset($mukellef) ? ' — ' . esc($mukellef['unvan']) : '' ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/stil.css') ?>">
<style>
  body{background:#fff;padding:26px;max-width:820px;margin:0 auto}
  h1{font-size:19px}
  .ust{border-bottom:2px solid #2563eb;padding-bottom:10px;margin-bottom:16px}
  .secenek-bar{
    background:var(--gri-50);border:1px solid var(--gri-200);border-radius:10px;
    padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap
  }
  @media print{ .secenek-bar{display:none!important} }
</style>
</head>
<body>

<?php
$mid = $grup['mukellef']['id'] ?? ($mukellef['id'] ?? 0);
$bosDurum = ($grup === null && empty($ucretDahil));
?>

<!-- ===== Yazdırmada gizlenen seçenek çubuğu (her durumda görünür) ===== -->
<div class="secenek-bar">
  <b style="font-size:13px">Bildirim Seçenekleri:</b>

  <?php if (! empty($ucretVar)): ?>
    <label class="onay">
      <input type="checkbox" id="ucret-kutu" <?= ! empty($ucretDahil) ? 'checked' : '' ?>>
      <span>Muhasebe ücreti dahil edilsin
        <b>(<?= number_format((float) $ucret, 2, ',', '.') ?> ₺)</b></span>
    </label>
  <?php else: ?>
    <span class="kucuk-yazi">
      Bu mükellef için muhasebe ücreti tanımlanmamış.
      <a href="<?= site_url('mukellefler/duzenle/' . $mid) ?>" target="_blank">Mükellef kartından girin</a>.
    </span>
  <?php endif; ?>

  <button type="button" class="btn kucuk" onclick="window.print()" style="margin-left:auto">🖨️ Yazdır</button>
</div>

<?php if ($bosDurum): ?>

  <div class="uyari dikkat"><span class="ik">⚠</span><div>
    <b><?= ! empty($filtre['ay']) ? ayAdi((int) $filtre['ay']) . ' ' : '' ?><?= esc($filtre['yil']) ?></b>
    döneminde bu mükellef için ödenecek beyanname bulunamadı.
    <?php if (! empty($ucretVar)): ?>
      <br>Yalnızca muhasebe ücretini bildirmek isterseniz yukarıdaki kutuyu işaretleyin.
    <?php else: ?>
      <br><span class="kucuk-yazi">
        Beyanname listeye girmesi için <a href="<?= site_url('takip') ?>">Beyanname Takip</a>
        ekranında durumun <b>Onaylandı</b> veya <b>Gönderildi</b> olması gerekir.
      </span>
    <?php endif; ?>
  </div></div>

<?php else: ?>

  <?php
  // Beyanname satırlarının kendi toplamı (özel kalemler hariç)
  $beyanToplam = 0.0;
  foreach (($grup['satirlar'] ?? []) as $bs) {
      $beyanToplam += (float) $bs['odenecek'];
  }

  // Beyanname dışı kalemler (Bağkur, MTV vb.)
  $ozelToplam = 0.0;
  foreach (($grup['ozel'] ?? []) as $oz) {
      $ozelToplam += (float) $oz['tutar'];
  }

  $ucretTutar  = ! empty($ucretDahil) ? (float) $ucret : 0.0;
  $genelToplam = $beyanToplam + $ozelToplam + $ucretTutar;

  // Seçenek çubuğu bağlantıları için mevcut sorgu dizesi
  ?>

  <!-- ===== Başlık ===== -->
  <div class="ust">
    <h1>Ödeme Bildirimi</h1>
    <div class="kucuk-yazi">
      <?= ! empty($filtre['ay']) ? ayAdi((int) $filtre['ay']) . ' ' : '' ?><?= esc($filtre['yil']) ?> dönemi
      • Düzenleme: <?= trTarih(date('Y-m-d')) ?>
    </div>
  </div>

  <!-- ===== Mükellef bilgileri ===== -->
  <div class="bilgi-liste mb16">
    <div class="oge"><div class="et">Mükellef</div>
      <div class="dg"><?= esc($grup['mukellef']['unvan'] ?? $mukellef['unvan']) ?></div></div>
    <div class="oge"><div class="et">VKN / TCKN</div>
      <div class="dg"><?= esc($grup['mukellef']['vkn'] ?? vknTckn($mukellef)) ?></div></div>
    <div class="oge"><div class="et">Vergi Dairesi</div>
      <div class="dg"><?= esc(($grup['mukellef']['vergi_dairesi'] ?? $mukellef['vergi_dairesi']) ?: '-') ?></div></div>
    <div class="oge"><div class="et">Mali Müşavir</div>
      <div class="dg"><?= esc($grup['mukellef']['musavir_adi'] ?? '-') ?></div></div>
  </div>

  <!-- ===== Beyanname tablosu ===== -->
  <?php if (! empty($grup['satirlar'])): ?>
    <table class="tablo">
      <thead>
        <tr>
          <th>Beyanname</th><th>Dönem</th><th>Son Ödeme</th>
          <th class="sag">Tahakkuk</th><th class="sag">Damga</th><th class="sag">Ödenecek</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($grup['satirlar'] as $s): ?>
        <tr>
          <td><?= esc($s['tur_adi']) ?></td>
          <td><?= esc($s['donem_adi']) ?></td>
          <td class="kalin"><?= trTarih($s['efektif_odeme_tarihi'] ?? $s['son_tarih']) ?></td>
          <td class="sag"><?= number_format((float) $s['tahakkuk_tutari'], 2, ',', '.') ?></td>
          <td class="sag"><?= (float) $s['hesaplanan_damga'] > 0
              ? number_format((float) $s['hesaplanan_damga'], 2, ',', '.') : '—' ?></td>
          <td class="sag kalin"><?= number_format((float) $s['odenecek'], 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--gri-50);font-weight:700">
          <td colspan="5" class="sag">BEYANNAME TOPLAMI</td>
          <td class="sag"><?= number_format($beyanToplam, 2, ',', '.') ?> ₺</td>
        </tr>
      </tfoot>
    </table>
  <?php endif; ?>

  <!-- ===== Diğer ödemeler (Bağkur, MTV vb.) ===== -->
  <?php if (! empty($grup['ozel'])): ?>
    <table class="tablo" style="margin-top:12px">
      <thead><tr><th>Diğer Ödemeler</th><th>Dönem</th><th>Son Tarih</th><th class="sag">Tutar</th></tr></thead>
      <tbody>
      <?php foreach ($grup['ozel'] as $o): ?>
        <tr>
          <td><b><?= esc($o['baslik']) ?></b>
            <?php if (! empty($o['aciklama'])): ?>
              <div class="kucuk-yazi"><?= esc($o['aciklama']) ?></div>
            <?php endif; ?>
          </td>
          <td class="kucuk-yazi"><?= esc($o['donem_etiketi'] ?: '—') ?></td>
          <td class="kalin"><?= trTarih($o['son_tarih']) ?></td>
          <td class="sag kalin"><?= number_format((float) $o['tutar'], 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- ===== Muhasebe ücreti ===== -->
  <?php if (! empty($ucretDahil)): ?>
    <table class="tablo" style="margin-top:12px">
      <thead><tr><th>Hizmet</th><th class="sag">Tutar</th></tr></thead>
      <tbody>
        <tr>
          <td>
            <b>Muhasebe Ücreti</b>
            <?php if (! empty($mukellef['ucret_aciklama'])): ?>
              <div class="kucuk-yazi"><?= esc($mukellef['ucret_aciklama']) ?></div>
            <?php else: ?>
              <div class="kucuk-yazi">
                <?= ! empty($filtre['ay']) ? ayAdi((int) $filtre['ay']) . ' ' . $filtre['yil'] : $filtre['yil'] ?> dönemi
              </div>
            <?php endif; ?>
          </td>
          <td class="sag kalin"><?= number_format($ucretTutar, 2, ',', '.') ?> ₺</td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- ===== Genel toplam ===== -->
  <div style="text-align:right;margin-top:18px;padding:14px;background:#f0fdf4;
              border:2px solid #059669;border-radius:10px">
    <div class="kucuk-yazi">
      <?php if ($beyanToplam > 0): ?>
        Beyanname <?= number_format($beyanToplam, 2, ',', '.') ?>
      <?php endif; ?>
      <?php if ($ozelToplam > 0): ?>
        + Diğer Ödemeler <?= number_format($ozelToplam, 2, ',', '.') ?>
      <?php endif; ?>
      <?php if (! empty($ucretDahil)): ?>
        + Muhasebe Ücreti <?= number_format($ucretTutar, 2, ',', '.') ?>
      <?php endif; ?>
    </div>
    <div style="font-size:24px;font-weight:800;color:#059669">
      TOPLAM: <?= number_format($genelToplam, 2, ',', '.') ?> ₺
    </div>
  </div>

  <p class="kucuk-yazi" style="margin-top:20px">
    Bu bildirim bilgilendirme amaçlıdır. Vergi ödemelerinizi son ödeme tarihine kadar
    vergi dairesine veya anlaşmalı bankalara yapabilirsiniz.
  </p>

<?php endif; ?>

<script>
(function () {
  var kutu = document.getElementById('ucret-kutu');
  if (!kutu) return;

  kutu.addEventListener('change', function () {
    var u = new URL(window.location.href);
    u.searchParams.set('ucret', kutu.checked ? '1' : '0');
    window.location.href = u.toString();
  });
})();
</script>

</body>
</html>
