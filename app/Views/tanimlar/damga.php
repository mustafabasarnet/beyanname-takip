<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<div class="uyari bilgi"><span class="ik">ℹ</span><div>
  Beyanname takip ekranında tahakkuk tutarını <b>damga vergisi hariç</b> girersiniz.
  <b>Ödeme Listesi</b> hesaplanırken buradaki sabit tutar otomatik eklenir.<br>
  Tutar alanını <b>boş bırakırsanız</b> o türe damga vergisi eklenmez.
  Damga vergisi her yıl yeniden değerlendiği için tanımlar <b>yıl bazlıdır</b>.
</div></div>

<form method="get" class="filtre-bar">
  <div class="form-grup">
    <label>Yıl</label>
    <select name="yil" data-oto-filtre>
      <?php
      $tumYillar = array_unique(array_merge($yillar, yilSecenekleri(2, 2)));
      rsort($tumYillar);
      foreach ($tumYillar as $y): ?>
        <option value="<?= $y ?>" <?= (int) $y === (int) $yil ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="btn-grup">
    <button type="button" class="btn ikincil kucuk" data-modal-ac="kopyala-modal">📋 Başka Yıldan Kopyala</button>
  </div>
</form>

<form method="post" action="<?= site_url('tanimlar/damga-kaydet') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="yil" value="<?= (int) $yil ?>">

  <div class="kart">
    <div class="kart-baslik">
      <h2>🧾 <?= $yil ?> Yılı Damga Vergisi Tutarları</h2>
      <div class="sag"><button type="submit" class="btn kucuk">💾 Tümünü Kaydet</button></div>
    </div>

    <div class="kart-govde sikisik">
      <div class="tablo-sar">
        <table class="tablo">
          <thead>
            <tr>
              <th>Beyanname Türü</th>
              <th>Kod</th>
              <th>Periyot</th>
              <th style="width:190px">Damga Vergisi (₺)</th>
              <th>Durum</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($liste as $l): ?>
            <tr class="<?= $l['aktif'] ? '' : 'metin-gri' ?>">
              <td>
                <span class="tur-rozet" style="background:<?= esc($l['renk']) ?>"><?= esc($l['kisa_ad']) ?></span>
                <div class="kucuk-yazi"><?= esc($l['ad']) ?></div>
              </td>
              <td class="kucuk-yazi"><?= esc($l['kod']) ?></td>
              <td class="kucuk-yazi"><?= periyotAdi($l['periyot']) ?></td>
              <td>
                <input type="text" name="tutar[<?= $l['tur_id'] ?>]" class="girdi damga-girdi"
                       inputmode="decimal" placeholder="Tanımsız"
                       style="text-align:right;font-weight:600"
                       value="<?= $l['tutar'] !== null ? number_format((float) $l['tutar'], 2, ',', '.') : '' ?>">
              </td>
              <td>
                <?php if ($l['tutar'] !== null && (float) $l['tutar'] > 0): ?>
                  <span class="rozet yesil">Eklenecek</span>
                <?php else: ?>
                  <span class="rozet gri">Eklenmeyecek</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="padding:14px 18px;border-top:1px solid var(--gri-200)">
      <button type="submit" class="btn">💾 Tümünü Kaydet</button>
      <span class="kucuk-yazi" style="margin-left:10px">
        Değişiklik yalnızca <b>bundan sonra girilecek</b> tahakkukları etkiler;
        daha önce kaydedilenlerde o günkü tutar korunur.
      </span>
    </div>
  </div>
</form>

<!-- Kopyalama modalı -->
<div class="modal-arka" id="kopyala-modal">
  <div class="modal">
    <form method="post" action="<?= site_url('tanimlar/damga-kopyala') ?>">
      <?= csrf_field() ?>
      <div class="modal-baslik">
        <h3>📋 Başka Yıldan Kopyala</h3>
        <button type="button" class="modal-kapat" data-modal-kapat>&times;</button>
      </div>
      <div class="modal-govde">
        <p class="yardim mb16">
          Seçtiğiniz yılın tutarları hedef yıla kopyalanır.
          Hedef yılda <b>zaten tanımlı olan</b> türler değiştirilmez.
        </p>
        <div class="form-grid">
          <div class="form-grup">
            <label>Kaynak Yıl</label>
            <select name="kaynak_yil" required>
              <?php foreach ($tumYillar as $y): ?>
                <option value="<?= $y ?>" <?= (int) $y === (int) $yil - 1 ? 'selected' : '' ?>><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-grup">
            <label>Hedef Yıl</label>
            <select name="hedef_yil" required>
              <?php foreach ($tumYillar as $y): ?>
                <option value="<?= $y ?>" <?= (int) $y === (int) $yil ? 'selected' : '' ?>><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-alt">
        <button type="button" class="btn ikincil" data-modal-kapat>İptal</button>
        <button type="submit" class="btn">Kopyala</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
// Tutar alanlarında sadece rakam, virgül ve nokta
document.querySelectorAll('.damga-girdi').forEach(function (i) {
  i.addEventListener('blur', function () {
    var v = i.value.trim();
    if (v === '') return;
    v = v.replace(/\s/g, '');
    if (v.indexOf(',') > -1) { v = v.replace(/\./g, '').replace(',', '.'); }
    var s = parseFloat(v);
    i.value = isNaN(s) ? '' : s.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  });
});
</script>
<?= $this->endSection() ?>
