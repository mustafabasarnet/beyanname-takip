<?= $this->extend('layouts/ana') ?>
<?= $this->section('icerik') ?>

<?php
/**
 * GELİR VERGİSİ TARİFESİ — yıl bazında dilim düzenleme
 *
 * Tarife her yıl yeniden açıklandığı için dilimler elle düzenlenebilir.
 * Yalnızca yönetici değiştirebilir.
 */
$admin = ($aktifKullanici['rol'] ?? '') === 'admin';

/** Dilim tablosu çizen yardımcı */
$tabloCiz = static function (array $dilimler, string $tip, int $yil, bool $admin) {
    $bosSatir = 6 - count($dilimler);
    $bosSatir = max(1, $bosSatir);
    ?>
    <form method="post" action="<?= site_url('gelir-vergisi/tarife/kaydet') ?>" class="tr-form">
      <?= csrf_field() ?>
      <input type="hidden" name="yil" value="<?= $yil ?>">
      <input type="hidden" name="tip" value="<?= $tip ?>">

      <table class="tr-tablo">
        <thead>
          <tr>
            <th style="width:5%">#</th>
            <th>Alt Sınır (Taban)</th>
            <th>Üst Sınır (Tavan)</th>
            <th>Tabana Kadarki Vergi</th>
            <th style="width:12%">Oran (%)</th>
            <th style="width:26%">Okunuşu</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 0; ?>
          <?php foreach ($dilimler as $d): ?>
            <?php $i++; ?>
            <tr>
              <td class="orta"><?= $i ?></td>
              <td><input type="text" name="dilim[<?= $i ?>][taban]" class="tr-para"
                         value="<?= number_format($d['taban'], 2, ',', '.') ?>" <?= $admin ? '' : 'readonly' ?>></td>
              <td><input type="text" name="dilim[<?= $i ?>][tavan]" class="tr-para"
                         value="<?= $d['tavan'] === null ? '' : number_format($d['tavan'], 2, ',', '.') ?>"
                         placeholder="boş = sınırsız" <?= $admin ? '' : 'readonly' ?>></td>
              <td><input type="text" name="dilim[<?= $i ?>][sabit_vergi]" class="tr-para"
                         value="<?= number_format($d['sabit_vergi'], 2, ',', '.') ?>" <?= $admin ? '' : 'readonly' ?>></td>
              <td><input type="text" name="dilim[<?= $i ?>][oran]" class="tr-para"
                         value="<?= rtrim(rtrim(number_format($d['oran'], 2, ',', '.'), '0'), ',') ?>"
                         <?= $admin ? '' : 'readonly' ?>></td>
              <td class="tr-okunus">
                <?php if ($d['tavan'] === null): ?>
                  <?= number_format($d['taban'], 0, ',', '.') ?> TL'den fazlasının
                  <?= number_format($d['taban'], 0, ',', '.') ?> TL'si için
                  <?= number_format($d['sabit_vergi'], 0, ',', '.') ?> TL, fazlası
                <?php elseif ($d['taban'] <= 0): ?>
                  <?= number_format($d['tavan'], 0, ',', '.') ?> TL'ye kadar
                <?php else: ?>
                  <?= number_format($d['tavan'], 0, ',', '.') ?> TL'nin
                  <?= number_format($d['taban'], 0, ',', '.') ?> TL'si için
                  <?= number_format($d['sabit_vergi'], 0, ',', '.') ?> TL, fazlası
                <?php endif; ?>
                <b>%<?= rtrim(rtrim(number_format($d['oran'], 2, ',', '.'), '0'), ',') ?></b>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php for ($b = 1; $b <= $bosSatir; $b++): ?>
            <?php $i++; ?>
            <tr class="tr-bos">
              <td class="orta"><?= $i ?></td>
              <td><input type="text" name="dilim[<?= $i ?>][taban]" class="tr-para" <?= $admin ? '' : 'readonly' ?>></td>
              <td><input type="text" name="dilim[<?= $i ?>][tavan]" class="tr-para"
                         placeholder="boş = sınırsız" <?= $admin ? '' : 'readonly' ?>></td>
              <td><input type="text" name="dilim[<?= $i ?>][sabit_vergi]" class="tr-para" <?= $admin ? '' : 'readonly' ?>></td>
              <td><input type="text" name="dilim[<?= $i ?>][oran]" class="tr-para"
                         placeholder="boş = sil" <?= $admin ? '' : 'readonly' ?>></td>
              <td class="tr-okunus tr-soluk">Yeni dilim — oranı boş bırakılan satır kaydedilmez.</td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>

      <?php if ($admin): ?>
        <button type="submit" class="btn kucuk" style="margin-top:10px">
          💾 <?= $tip === 'ucret' ? 'Ücret' : 'Ücret Dışı' ?> Tarifesini Kaydet
        </button>
      <?php endif; ?>
    </form>
    <?php
};
?>

<style>
.tr-tablo{width:100%;border-collapse:collapse}
.tr-tablo th{font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;
  color:var(--gri-500,#64748b);font-weight:700;padding:7px 8px;
  border-bottom:1px solid var(--gri-200,#e2e8f0);text-align:left}
.tr-tablo td{padding:5px 6px;border-bottom:1px solid var(--gri-100,#f1f5f9);vertical-align:middle}
.tr-tablo td.orta{text-align:center;font-size:12px;color:var(--gri-500,#64748b)}
.tr-para{width:100%;padding:6px 8px;border:1px solid var(--gri-300,#cbd5e1);border-radius:5px;
  font-size:13px;text-align:right;font-variant-numeric:tabular-nums;font-family:inherit}
.tr-para:focus{outline:none;border-color:var(--ana,#2563eb);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.tr-para[readonly]{background:var(--gri-50,#f8fafc);color:var(--gri-500,#64748b)}
.tr-okunus{font-size:11.5px;color:var(--gri-600,#475569);line-height:1.35}
.tr-soluk{color:var(--gri-400,#94a3b8);font-style:italic}
.tr-bos .tr-para{background:#fffdf7}
.tr-sekme{display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap}
.tr-sekme button{font:inherit;padding:7px 14px;border-radius:7px;border:1px solid var(--gri-300,#cbd5e1);
  background:#fff;cursor:pointer;font-size:13px}
.tr-sekme button.aktif{background:#0f172a;color:#fff;border-color:#0f172a;font-weight:600}
.tr-bilgi{padding:10px 12px;border-radius:8px;background:#eff6ff;color:#1e40af;
  font-size:12.5px;margin-bottom:14px;border:1px solid #bfdbfe;line-height:1.5}
.tr-uyari{padding:10px 12px;border-radius:8px;background:#fef3c7;color:#92400e;
  font-size:12.5px;margin-bottom:14px;border:1px solid #fde68a}
.tr-yil-serit{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.tr-yil-serit a{padding:4px 10px;border-radius:99px;border:1px solid var(--gri-300,#cbd5e1);
  font-size:12px;text-decoration:none;color:var(--gri-700,#334155);background:#fff}
.tr-yil-serit a.aktif{background:#2563eb;color:#fff;border-color:#2563eb;font-weight:700}
</style>

<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">
  <a href="<?= site_url('gelir-vergisi?yil=' . (int) $yil) ?>" class="btn ikincil kucuk">← Gelir Vergisi</a>
  <h2 style="margin:0"><?= (int) $yil ?> Gelir Vergisi Tarifesi</h2>
  <span class="kucuk-yazi">GVK md.103</span>

  <form method="get" style="margin-left:auto;display:flex;gap:6px;align-items:center">
    <label class="kucuk-yazi" style="margin:0">Yıl</label>
    <select name="yil" data-oto-filtre style="padding:5px 8px">
      <?php foreach (yilSecenekleri() as $y): ?>
        <option value="<?= $y ?>" <?= (int) $yil === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($tanimliYillar !== []): ?>
  <div class="tr-yil-serit" style="margin-bottom:14px">
    <span class="kucuk-yazi">Tanımlı yıllar:</span>
    <?php foreach ($tanimliYillar as $ty): ?>
      <a href="<?= site_url('gelir-vergisi/tarife?yil=' . $ty) ?>"
         class="<?= $ty === (int) $yil ? 'aktif' : '' ?>"><?= $ty ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="tr-bilgi">
  <b>Bir satır neyi anlatır?</b> "<i>1.000.000 TL'nin 400.000 TL'si için 70.500 TL, fazlası %27</i>"
  satırı şudur: <b>Taban</b> 400.000, <b>Tavan</b> 1.000.000, <b>Tabana kadarki vergi</b> 70.500,
  <b>Oran</b> 27. Son dilimde tavanı boş bırakın (sınırsız).
  Serbest meslek kazancı <b>ücret dışı</b> tarifeye tabidir; gelir vergisi hesabı bu tabloyu kullanır.
</div>

<?php if (! $admin): ?>
  <div class="tr-uyari">Tarife yalnızca <b>yönetici</b> tarafından düzenlenebilir. Salt okunur görüntülüyorsunuz.</div>
<?php endif; ?>

<?php if ($admin): ?>
  <div class="kart" style="margin-bottom:14px">
    <div class="kart-baslik"><h2>📋 Başka Yıldan Kopyala</h2></div>
    <div style="padding:12px 14px">
      <form method="post" action="<?= site_url('gelir-vergisi/tarife/kopyala') ?>"
            style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?>
        <div class="form-grup">
          <label>Kaynak Yıl</label>
          <select name="kaynak_yil">
            <?php foreach ($tanimliYillar ?: [(int) $yil - 1] as $ty): ?>
              <option value="<?= $ty ?>" <?= $ty === (int) $yil - 1 ? 'selected' : '' ?>><?= $ty ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grup">
          <label>Hedef Yıl</label>
          <select name="hedef_yil">
            <?php foreach (yilSecenekleri() as $y): ?>
              <option value="<?= $y ?>" <?= $y === (int) $yil ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grup" style="max-width:150px">
          <label>Artış Oranı (%)</label>
          <input type="text" name="oran" class="girdi" placeholder="0" inputmode="decimal">
        </div>
        <button type="submit" class="btn ikincil kucuk">📋 Kopyala</button>
        <span class="kucuk-yazi" style="max-width:340px">
          Hedef yılda tarife varsa <b>dokunulmaz</b>. Kopya, resmi tebliğ çıkana kadar
          geçici tahmindir — tebliğ yayımlanınca tutarları elle düzeltin.
        </span>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="tr-sekme">
  <button type="button" class="aktif" data-sekme="ucret-disi">💼 Ücret Dışı Gelirler (serbest meslek)</button>
  <button type="button" data-sekme="ucret">👔 Ücret Gelirleri</button>
</div>

<div class="kart" id="sekme-ucret-disi">
  <div class="kart-baslik">
    <h2>Ücret Dışındaki Gelirler — <?= (int) $yil ?></h2>
    <span class="kucuk-yazi">Gelir vergisi hesabı bu tarifeyi kullanır</span>
  </div>
  <div style="padding:10px 14px 14px">
    <?php $tabloCiz($ucretDisi, 'ucret_disi', (int) $yil, $admin); ?>
  </div>
</div>

<div class="kart" id="sekme-ucret" style="display:none">
  <div class="kart-baslik">
    <h2>Ücret Gelirleri — <?= (int) $yil ?></h2>
    <span class="kucuk-yazi">Bilgi amaçlı tutulur</span>
  </div>
  <div style="padding:10px 14px 14px">
    <?php $tabloCiz($ucret, 'ucret', (int) $yil, $admin); ?>
  </div>
</div>

<script>
document.querySelectorAll('.tr-sekme button').forEach(function (d) {
  d.addEventListener('click', function () {
    document.querySelectorAll('.tr-sekme button').forEach(function (x) { x.classList.remove('aktif'); });
    d.classList.add('aktif');
    document.getElementById('sekme-ucret-disi').style.display = d.dataset.sekme === 'ucret-disi' ? '' : 'none';
    document.getElementById('sekme-ucret').style.display      = d.dataset.sekme === 'ucret' ? '' : 'none';
  });
});
</script>

<?= $this->endSection() ?>
