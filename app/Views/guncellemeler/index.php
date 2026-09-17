<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
/**
 * GÜNCELLEME LOGLARI — LİSTE
 *
 * Her rol okur. Yönetici ek olarak kayıt ekler/düzenler/siler.
 * Okunmamış kayıtlar "YENİ" rozetiyle vurgulanır.
 */
$kayitlar   = $kayitlar ?? [];
$okunanIdler = $okunanIdler ?? [];
$adminMi    = $adminMi ?? false;
$yeniSayi   = (int) ($yeniSayi ?? 0);
?>

<style>
.gn-kart{border:1px solid var(--gri-200,#e2e8f0);border-radius:14px;margin-bottom:14px;
  overflow:hidden;background:#fff;transition:.18s}
.gn-kart:hover{box-shadow:0 6px 22px rgba(15,23,42,.08)}
.gn-kart.yeni{border-color:#c4b5fd;box-shadow:0 0 0 3px #f5f3ff inset}
.gn-bas{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:13px 16px;
  background:linear-gradient(120deg,#faf5ff,#f0f9ff);border-bottom:1px solid var(--gri-100,#f1f5f9)}
.gn-bas .surum{background:#ede9fe;color:#5b21b6;font-weight:800;font-size:11.5px;
  padding:3px 10px;border-radius:99px;letter-spacing:.3px}
.gn-bas .tarih{color:var(--gri-500,#64748b);font-size:12px}
.gn-bas .baslik{font-weight:700;font-size:15px;color:var(--gri-900,#0f172a);flex:1 1 260px}
.gn-bas .islem{display:flex;gap:6px;margin-left:auto}
.gn-govde{padding:6px 16px 14px}
.gn-madde{display:flex;gap:10px;align-items:flex-start;padding:8px 0;
  border-bottom:1px dashed var(--gri-100,#f1f5f9);font-size:13.6px;color:var(--gri-700,#334155);line-height:1.55}
.gn-madde:last-child{border-bottom:0}
.gn-madde .rz{flex:0 0 auto;font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:99px;
  white-space:nowrap;margin-top:1px;display:inline-flex;align-items:center;gap:4px}
.gn-madde .rz.yesil{background:#d1fae5;color:#065f46}
.gn-madde .rz.mavi{background:#dbeafe;color:#1e40af}
.gn-madde .rz.turuncu{background:#ffedd5;color:#9a3412}
.gn-madde .rz.gri{background:#e2e8f0;color:#475569}
.gn-madde .mt b{color:var(--gri-900,#0f172a)}
.gn-yeni-rozet{background:#7c3aed;color:#fff;font-size:10px;font-weight:800;
  padding:2px 8px;border-radius:99px;letter-spacing:.4px}
.gn-pasif{opacity:.72;border-style:dashed}
.gn-pasif .gn-bas{background:var(--gri-50,#f8fafc)}
</style>

<div class="kart-baslik" style="margin:0 0 12px">
  <h2>🆕 Güncellemeler</h2>
  <span class="kucuk-yazi">
    Sürüm notları — girişte okumadıklarınız pencere olarak gösterilir.
    <?php if ($yeniSayi > 0): ?>
      <b style="color:#7c3aed">Okunmamış: <?= $yeniSayi ?></b>
    <?php endif; ?>
  </span>
</div>

<?php if ($adminMi): ?>
  <div class="filtre-bar" style="margin-bottom:14px">
    <span class="kucuk-yazi" style="align-self:center">
      Yönetici olarak sürüm notu ekleyip düzenleyebilirsiniz.
      Kaydettiğinizde <b>yayında</b> olan kayıtlar kullanıcılara girişte gösterilir.
    </span>
    <div class="btn-grup" style="margin-left:auto">
      <a href="<?= site_url('guncellemeler/yeni') ?>" class="btn kucuk">➕ Yeni Güncelleme</a>
    </div>
  </div>
<?php endif; ?>

<?php if ($kayitlar === []): ?>
  <div class="kart">
    <div class="kart-govde">
      <div class="tablo-bos">
        <span class="ikon">📜</span>
        <?= $adminMi
            ? 'Henüz güncelleme kaydı yok. "➕ Yeni Güncelleme" ile ilk sürüm notunu ekleyin.'
            : 'Henüz yayınlanmış bir güncelleme notu yok.' ?>
      </div>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($kayitlar as $k): ?>
    <?php
      $okundu = in_array((int) $k['id'], $okunanIdler, true);
      $pasif  = (int) ($k['aktif'] ?? 1) !== 1;
    ?>
    <div class="gn-kart <?= $pasif ? 'gn-pasif' : ($okundu ? '' : 'yeni') ?>">
      <div class="gn-bas">
        <span class="surum">v<?= esc($k['versiyon']) ?></span>
        <span class="tarih"><?= trTarih($k['tarih']) ?></span>
        <span class="baslik"><?= esc($k['baslik']) ?></span>

        <?php if ($pasif): ?>
          <span class="rozet gri" title="Yayında değil — kullanıcılara gösterilmiyor">Yayında değil</span>
        <?php elseif (! $okundu): ?>
          <span class="gn-yeni-rozet">YENİ</span>
        <?php endif; ?>

        <?php if ($adminMi): ?>
          <span class="islem">
            <a href="<?= site_url('guncellemeler/duzenle/' . (int) $k['id']) ?>"
               class="btn ikincil mini">✏️ Düzenle</a>
            <a href="<?= site_url('guncellemeler/sil/' . (int) $k['id']) ?>"
               class="btn kirmizi mini"
               data-onay="Bu güncelleme kaydı silinsin mi? (Kullanıcıların okundu kayıtları da silinir)">🗑 Sil</a>
          </span>
        <?php endif; ?>
      </div>

      <div class="gn-govde">
        <?php foreach (($k['maddeler'] ?? []) as $m): ?>
          <div class="gn-madde">
            <span class="rz <?= esc($m['renk']) ?>"><?= esc($m['ikon']) ?> <?= esc($m['ad']) ?></span>
            <div class="mt"><?= isaretCoz($m['metin']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($adminMi && $tumKayitlar !== null): ?>
  <?php
    $pasifler = array_filter($tumKayitlar, static fn ($k) => (int) ($k['aktif'] ?? 1) !== 1);
  ?>
  <?php if ($pasifler !== []): ?>
    <div class="kart" style="margin-top:6px">
      <div class="kart-baslik">
        <h2>🗂 Yayında Olmayanlar (<?= count($pasifler) ?>)</h2>
        <span class="kucuk-yazi">Yalnız yönetici görür; kullanıcılara gösterilmez.</span>
      </div>
      <div class="kart-govde">
        <?php foreach ($pasifler as $k): ?>
          <div class="gn-madde" style="border-top:1px dashed var(--gri-100,#f1f5f9)">
            <span class="rz gri">v<?= esc($k['versiyon']) ?></span>
            <div class="mt">
              <b><?= esc($k['baslik']) ?></b>
              <span class="kucuk-yazi"> · <?= trTarih($k['tarih']) ?> · <?= count($k['maddeler'] ?? []) ?> madde</span>
            </div>
            <a href="<?= site_url('guncellemeler/duzenle/' . (int) $k['id']) ?>" class="btn ikincil mini">Düzenle</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
