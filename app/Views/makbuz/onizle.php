<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
$ucretMi = $kip === 'ucret';
$o       = $sonuc['ozet'];
?>

<style>
.oz-tablo td{font-size:12.5px;vertical-align:middle}
.oz-tablo tr.oz-hatali{background:#fef2f2}
.oz-tablo tr.oz-mukerrer{background:#fffbeb}
.oz-uyari{font-size:11px;color:var(--turuncu,#ea580c);display:block}
.oz-hata{font-size:11.5px;color:var(--kirmizi,#dc2626)}
</style>

<div class="kart">
  <div class="kart-baslik">
    <h2>🔍 İçe Aktarma Önizleme — <?= $ucretMi ? 'Yıllık Ücretler' : 'Kesilen Makbuzlar' ?></h2>
    <div class="sag">
      <a href="<?= site_url('makbuz/ice-aktar?kip=' . $kip . '&yil=' . (int) $yil) ?>"
         class="btn ikincil mini">← Yeni Dosya</a>
    </div>
  </div>

  <div class="kart-govde">
    <?php if (! empty($sonuc['mesaj'])): ?>
      <div class="uyari" style="padding:10px 14px;font-size:13px">
        <span class="ik">⚠</span><div><?= esc($sonuc['mesaj']) ?></div>
      </div>
    <?php endif; ?>

    <div class="stat-grid">
      <div class="stat yesil">
        <div class="etiket">Aktarılacak</div>
        <div class="deger"><?= (int) $o['gecerli'] ?></div>
        <div class="alt"><?= number_format($o['tutar'], 2, ',', '.') ?> ₺</div>
      </div>
      <div class="stat sari">
        <div class="etiket">Mükerrer</div>
        <div class="deger"><?= (int) $o['mukerrer'] ?></div>
        <div class="alt">zaten kayıtlı</div>
      </div>
      <div class="stat kirmizi">
        <div class="etiket">Hatalı</div>
        <div class="deger"><?= (int) $o['hatali'] ?></div>
        <div class="alt">aktarılamaz</div>
      </div>
      <div class="stat">
        <div class="etiket">Toplam Satır</div>
        <div class="deger"><?= (int) $o['toplam'] ?></div>
        <div class="alt"><?= (int) $yil ?> yılı</div>
      </div>
    </div>

    <?php if ((int) $o['gecerli'] === 0): ?>
      <div class="uyari" style="padding:10px 14px">
        <span class="ik">⚠</span>
        <div>
          Aktarılabilecek geçerli satır yok. Hatalı satırların nedenlerini
          aşağıdaki listede görebilirsiniz.
        </div>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('makbuz/ice-aktar/onayla') ?>" id="oz-form">
      <?= csrf_field() ?>

      <div class="satir arali" style="margin:12px 0">
        <div>
          <label class="onay" style="margin:0">
            <input type="checkbox" id="oz-hepsi" checked>
            <b>Geçerli satırların tümünü seç</b>
          </label>
        </div>
        <button type="submit" class="btn" <?= (int) $o['gecerli'] === 0 ? 'disabled' : '' ?>>
          💾 Seçilenleri Aktar
        </button>
      </div>

      <div class="tablo-sar">
        <table class="tablo oz-tablo">
          <thead>
            <tr>
              <th style="width:36px"></th>
              <th style="width:50px">Satır</th>
              <th>Mükellef</th>
              <?php if ($ucretMi): ?>
                <th class="sag">Yıllık Ücret</th>
              <?php else: ?>
                <th>Makbuz No</th><th>Tarih</th>
                <th class="sag">Brüt</th><th class="sag">Stopaj</th><th class="sag">KDV</th>
              <?php endif; ?>
              <th>Durum / Not</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($sonuc['satirlar'] as $s):
              $gecerli = $s['durum'] === 'GECERLI';
              $sinif   = $s['durum'] === 'HATALI' ? 'oz-hatali'
                       : ($s['durum'] === 'MUKERRER' ? 'oz-mukerrer' : ''); ?>
            <tr class="<?= $sinif ?>">
              <td class="orta">
                <?php if ($gecerli): ?>
                  <input type="checkbox" name="sec[]" value="<?= (int) $s['satir_no'] ?>"
                         class="oz-sec" checked style="width:17px;height:17px;cursor:pointer">
                <?php else: ?>
                  <span class="kucuk-yazi">—</span>
                <?php endif; ?>
              </td>
              <td class="kucuk-yazi"><?= (int) $s['satir_no'] ?></td>
              <td>
                <?php if ($gecerli): ?>
                  <b><?= esc(kisalt($s['veri']['unvan'] ?? '', 30)) ?></b>
                <?php else: ?>
                  <span class="kucuk-yazi"><?= esc(kisalt($s['ham']['unvan'] ?: $s['ham']['kimlik'], 30)) ?></span>
                <?php endif; ?>
                <div class="kucuk-yazi"><?= esc($s['ham']['kimlik']) ?></div>
              </td>

              <?php if ($ucretMi): ?>
                <td class="sag kalin">
                  <?= $gecerli ? number_format((float) $s['veri']['tutar'], 2, ',', '.') : esc($s['ham']['tutar']) ?>
                </td>
              <?php else: ?>
                <td class="kucuk-yazi"><?= esc($s['ham']['no'] ?: '—') ?></td>
                <td class="kucuk-yazi">
                  <?= $gecerli ? trTarih($s['veri']['tarih']) : esc($s['ham']['tarih']) ?>
                </td>
                <td class="sag kalin">
                  <?= $gecerli ? number_format((float) $s['veri']['brut'], 2, ',', '.') : esc($s['ham']['brut']) ?>
                </td>
                <td class="sag" style="color:var(--turuncu)">
                  <?= $gecerli ? number_format((float) $s['veri']['stopaj'], 2, ',', '.') : '—' ?>
                </td>
                <td class="sag" style="color:var(--mor)">
                  <?= $gecerli ? number_format((float) $s['veri']['kdv'], 2, ',', '.') : '—' ?>
                </td>
              <?php endif; ?>

              <td>
                <?php if ($s['durum'] === 'HATALI'): ?>
                  <span class="rozet kirmizi" style="font-size:10px">Hatalı</span>
                  <span class="oz-hata"><?= esc($s['hata']) ?></span>
                <?php elseif ($s['durum'] === 'MUKERRER'): ?>
                  <span class="rozet sari" style="font-size:10px">Mükerrer</span>
                  <span class="oz-hata"><?= esc($s['hata']) ?></span>
                <?php else: ?>
                  <span class="rozet yesil" style="font-size:10px">Aktarılacak</span>
                <?php endif; ?>
                <?php foreach ($s['uyari'] as $u): ?>
                  <span class="oz-uyari">⚠ <?= esc($u) ?></span>
                <?php endforeach; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
// "Tümünü seç" — yalnızca geçerli satırları etkiler
(function () {
  var hepsi = document.getElementById('oz-hepsi');
  if (!hepsi) { return; }

  hepsi.addEventListener('change', function () {
    document.querySelectorAll('.oz-sec').forEach(function (c) { c.checked = hepsi.checked; });
  });

  // Tek tek kaldırılınca üst kutu da güncellensin
  document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('oz-sec')) { return; }
    var t = document.querySelectorAll('.oz-sec');
    var s = document.querySelectorAll('.oz-sec:checked');
    hepsi.checked = t.length === s.length;
  });
})();

// Hiç satır seçilmeden gönderim engellenir
document.getElementById('oz-form').addEventListener('submit', function (e) {
  if (document.querySelectorAll('.oz-sec:checked').length === 0) {
    e.preventDefault();
    alert('Hiçbir satır seçmediniz. Aktarılacak satırları işaretleyin.');
  }
});
</script>
<?= $this->endSection() ?>
